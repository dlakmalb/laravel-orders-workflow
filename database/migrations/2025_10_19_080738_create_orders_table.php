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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('external_order_id', 64)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('status', ['PENDING','PAID','FAILED','CANCELLED'])->default('PENDING');
            $table->char('currency', 3);
            $table->unsignedInteger('total_cents')->default(0); // Total amount computed in cents for fast retrieval
            $table->dateTimeTz('placed_at', 6);

            $table->timestamps();

            $table->index('status');
            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
