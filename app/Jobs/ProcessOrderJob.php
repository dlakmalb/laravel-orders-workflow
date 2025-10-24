<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $orderId)
    {
        //
    }

    // Prevent two ProcessOrderJob for the same order from running at once
    public function middleware(): array
    {
        return [new WithoutOverlapping("order:{$this->orderId}")];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order = Order::findorFail($this->orderId);

        if (! $order) {
            return;
        }

        if ($order->isTerminal()) {
            Log::info("Order {$order->id} is already in terminal state {$order->status}, skipping processing.");
            return;
        }

        if (! $this->reserveStock($order)) {
            $order->update(['status' => Order::STATUS_FAILED]);
            return;
        }

        FakeGatewayChargeJob::dispatch(
            orderId: $order->id,
            amountCents: $order->total_cents
        )->delay(now()->addSeconds(2));
    }

    /**
     * Try to reserve stock for all items in the order atomically.
     * Returns true on success, false if reservation not possible.
     */
    private function reserveStock(Order $order): bool
    {
        // Load items with the product ids and quantities
        $items = OrderItem::query()
            ->where('order_id', $order->id)
            ->select(['product_id', 'qty'])
            ->get();

        if ($items->isEmpty()) {
            return false;
        }

        $locks = [];

        try {
            foreach ($items as $item) {
                $key = "stock:product:{$item->product_id}";
                // 5-second lock; wait up to 2s to acquire
                $lock = Cache::lock($key, 5);
                $lock->block(2);
                $locks[] = $lock;
            }

            // With locks held, do a single transaction to check & decrement stock
            return DB::transaction(function () use ($items): bool {
                // Re-read current stocks inside the transaction to avoid old reads
                // Build a map product_id => needed qty
                $need = $items
                    ->groupBy('product_id')
                    ->map(fn($group) => (int) $group->sum('qty'));

                // Check availability
                $products = Product::query()
                    ->whereIn('id', $need->keys())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // Validate all first
                $insufficient = $need->first(fn ($qty, $pid) =>
                    ! isset($products[$pid]) || $products[$pid]->stock_qty < $qty
                );

                if ($insufficient !== null) {
                    return false; // any shortage → abort with no changes
                }

                // Decrement stock
                foreach ($need as $pid => $qty) {
                    $products[$pid]->decrement('stock_qty', $qty);
                }

                return true;
            });
        } catch (QueryException $e) {
            throw $e;
        } finally {
            // Always release locks
            foreach ($locks as $lock) {
                try { $lock?->release(); } catch (\Throwable) {}
            }
        }
    }
}
