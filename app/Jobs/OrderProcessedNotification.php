<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OrderProcessedNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $orderId,
        public bool $succeeded,
        public string $channel = 'log'
    ) {
        // Create a separate queue for notifications.
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order = Order::with('customer')->find($this->orderId);

        if (! $order) {
            return;
        }

        $payload = [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'status' => $this->succeeded ? 'PAID' : 'FAILED',
            'total_cents' => $order->total_cents,
        ];

        $success = true;
        $error = null;

        try {
            // For this example, we just log the notification.
            Log::info('Order processed notification', $payload);
        } catch (\Throwable $e) {
            $success = false;
            $error = $e->getMessage();
        }

        NotificationLog::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'channel' => $this->channel,
            'status' => $payload['status'],
            'total_cents' => $order->total_cents,
            'payload' => $payload,
            'success' => $success,
            'error' => $error,
            'sent_at' => now(),
        ]);
    }
}
