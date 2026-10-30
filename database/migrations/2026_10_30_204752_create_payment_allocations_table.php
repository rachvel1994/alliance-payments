<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_run_id')->constrained();
            $table->foreignId('payment_id')->constrained();
            $table->foreignId('installment_id')->constrained();
            $table->bigInteger('amount_minor');
            $table->timestamps();

            $table->unique([
                'allocation_run_id',
                'payment_id',
                'installment_id',
            ], 'payment_allocation_unique');

            $table->index(['installment_id', 'allocation_run_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
