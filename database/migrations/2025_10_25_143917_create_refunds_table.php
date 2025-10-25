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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('reason', 255)->nullable();
            $table->enum('status', ['REQUESTED', 'PROCESSED', 'FAILED'])->default('REQUESTED');

            $table->string('idempotency_key', 64)->nullable()->unique();

            $table->timestamp('processed_at', 6)->nullable();
            $table->timestamps(6);

            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
