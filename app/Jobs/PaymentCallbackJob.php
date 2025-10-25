<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Services\KpiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $orderId,
        public bool $succeeded,
        public ?string $providerRef = null
    ) {
        //
    }

     public function middleware(): array
    {
        return [new WithoutOverlapping("callback:order:{$this->orderId}")];
    }

    /**
     * Execute the job.
     */
    public function handle(KpiService $kpiService): void
    {
        $order = Order::find($this->orderId);

        if (! $order || $order->isTerminal()) {
            Log::info("Order {$order->id} is already in terminal state {$order->status}, skipping processing.");
            return;
        }

        if ($this->succeeded) {
            $this->finalizePaid($order);

            $kpiService->recordSuccess($order->customer_id, $order->total_cents);

            OrderProcessedNotification::dispatch($order, true);
        } else {
            $this->finalizeFailedWithRollback($order);

            $kpiService->recordFailure($order->customer_id, $order->total_cents);

            OrderProcessedNotification::dispatch($order, false);
        }
    }

    private function finalizePaid(Order $order): void
    {
        DB::transaction(function () use ($order) {
            // Upsert a payment record (1:1 enforced by unique index)
            Payment::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'provider' => 'fake',
                    'provider_ref' => $this->providerRef,
                    'amount_cents' => $order->total_cents,
                    'status' => 'SUCCEEDED',
                    'paid_at' => now(),
                ]
            );

            $order->update(['status' => Order::STATUS_PAID]);
        });
    }

    private function finalizeFailedWithRollback(Order $order): void
    {
        DB::transaction(function () use ($order) {
            // Put stock back (rollback the reservation)
            $items = OrderItem::where('order_id', $order->id)
                ->select(['product_id', 'qty'])
                ->get();

            // Lock the products we need to adjust
            $need = [];
            foreach ($items as $it) {
                $need[$it->product_id] = ($need[$it->product_id] ?? 0) + (int) $it->qty;
            }

            $products = Product::whereIn('id', array_keys($need))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($need as $pid => $qty) {
                $p = $products[$pid];
                $p->increment('stock_qty', $qty);
            }

            // Record failed payment
            Payment::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'provider'     => 'fake',
                    'provider_ref' => $this->providerRef,
                    'amount_cents' => $order->total_cents,
                    'status'       => 'FAILED',
                    'paid_at'      => null,
                ]
            );

            $order->update(['status' => Order::STATUS_FAILED]);
        });
    }
}
