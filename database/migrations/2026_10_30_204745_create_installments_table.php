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
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained();
            $table->unsignedInteger('sequence');
            $table->date('due_date');
            $table->bigInteger('amount_minor');
            $table->timestamps();

            $table->unique(['contract_id', 'sequence']);
            $table->index(['due_date', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
