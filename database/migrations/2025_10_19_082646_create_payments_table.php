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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('provider', 32)->default('fake');
            $table->string('provider_ref', 64)->nullable();
            $table->unsignedInteger('amount_cents');
            $table->enum('status', ['SUCCEEDED','FAILED']);
            $table->dateTimeTz('paid_at', 6)->nullable();

            $table->timestamps(6);

            $table->unique('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
