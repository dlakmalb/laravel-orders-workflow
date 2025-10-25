<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FakeGatewayChargeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $orderId, public int $amountCents)
    {
        //
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping("charge:order:{$this->orderId}")];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order = Order::find($this->orderId);

        if (! $order || $order->isTerminal()) {
            Log::info("Order {$order->id} is already in terminal state {$order->status}, skipping processing.");

            return;
        }

        $success = ($order->total_cents % 2) === 0;

        PaymentCallbackJob::dispatch(
            orderId: $order->id,
            succeeded: $success,
            providerRef: 'FAKE-'.uniqid()
        );
    }
}
