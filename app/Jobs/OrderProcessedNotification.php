<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OrderProcessedNotification implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Order $order,
        public bool $succeeded
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Log-based for now (could later be mail or database notification)
        Log::info('Order processed notification', [
            'order_id' => $this->order->id,
            'customer_id' => $this->order->customer_id,
            'status' => $this->succeeded ? 'PAID' : 'FAILED',
            'total' => $this->order->total_cents,
        ]);
    }
}
