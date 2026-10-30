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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained();
            $table->date('paid_at');
            $table->bigInteger('amount_minor');
            $table->string('personal_number', 11)->nullable();
            $table->string('payer_name');
            $table->text('purpose')->nullable();
            $table->string('bank_reference')->unique();
            $table->timestamps();

            $table->index(['client_id', 'paid_at', 'id']);
            $table->index('personal_number');
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
