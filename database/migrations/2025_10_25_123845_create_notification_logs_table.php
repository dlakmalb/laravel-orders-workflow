<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('channel', 32)->default('log');
            $table->string('status', 16); // 'PAID' | 'FAILED' (order status at send time)
            $table->unsignedInteger('total_cents');

            $table->json('payload')->nullable();
            $table->boolean('success')->default(true);
            $table->text('error')->nullable();
            $table->timestamp('sent_at', 6)->nullable();

            $table->timestamps(6);

            $table->index(['order_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
