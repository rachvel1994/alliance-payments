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
        Schema::create('payment_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_import_id')->constrained();
            $table->foreignId('payment_id')->nullable()->constrained();
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->string('status', 30);
            $table->text('message')->nullable();
            $table->timestamps();

            $table->unique(['payment_import_id', 'row_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_import_rows');
    }
};
