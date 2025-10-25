<?php

namespace App\Console\Commands;

use App\Jobs\ProcessRefundJob;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Console\Command;

class RefundOrderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:refund {order_id} {amount_cents} {--key=} {--reason=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Request a partial or full refund for an order';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $orderId = (int) $this->argument('order_id');
        $amount = (int) $this->argument('amount_cents');
        $key = $this->option('key');
        $reason = $this->option('reason');

        $order = Order::find($orderId);

        if (! $order) {
            $this->error("Order {$orderId} not found.");

            return self::FAILURE;
        }

        if ($amount < 1 || $amount > $order->total_cents) {
            $this->error("Invalid amount. Must be between 1 and {$order->total_cents}.");

            return self::FAILURE;
        }

        $refund = Refund::create([
            'order_id' => $order->id,
            'amount_cents' => $amount,
            'reason' => $reason,
            'status' => Refund::STATUS_REQUESTED,
            'idempotency_key' => $key,
        ]);

        ProcessRefundJob::dispatch($refund->id)->onQueue('refunds');

        $this->info("Refund {$refund->id} queued for order {$order->id} (amount {$amount}).");

        return self::SUCCESS;
    }
}
