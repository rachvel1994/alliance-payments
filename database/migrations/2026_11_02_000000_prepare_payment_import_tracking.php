<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_imports', function (Blueprint $table): void {
            $table->text('error_message')->nullable();
            $table->string('status', 30)->default('queued')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_imports', function (Blueprint $table): void {
            $table->dropColumn('error_message');
            $table->string('status', 30)->default('processing')->change();
        });
    }
};
