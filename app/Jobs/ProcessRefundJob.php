<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Refund;
use App\Services\KpiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $refundId)
    {
        $this->onQueue('refunds');
    }

    public function middleware(): array
    {
        // prevent two workers processing the same refund simultaneously
        return [new WithoutOverlapping("refund:{$this->refundId}")];
    }

    /**
     * Execute the job.
     */
    public function handle(KpiService $kpiService): void
    {
        $refund = Refund::find($this->refundId);

        if (! $refund || $refund->status !== Refund::STATUS_REQUESTED) {
            return;
        }

        $order = Order::find($refund->order_id);

        if (! $order) {
            $refund->update(['status' => Refund::STATUS_FAILED]);

            return;
        }

        $amount = (int) $refund->amount_cents;

        if ($amount < 1 || $amount > $order->total_cents) {
            $refund->update(['status' => Refund::STATUS_FAILED]);

            return;
        }

        DB::transaction(function () use ($refund, $order, $amount, $kpiService) {
            // Update KPIs/leaderboard first (real-time effect)
            $kpiService->recordRefund($order->customer_id, $amount);

            // Mark refund processed
            $refund->update([
                'status' => Refund::STATUS_PROCESSED,
                'processed_at' => now(),
            ]);
        });
    }
}
