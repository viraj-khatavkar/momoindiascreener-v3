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
        Schema::dropIfExists('backtest_nse_fno_imports');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('backtest_nse_fno_imports', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->text('source');
            $table->char('sha256', 64);
            $table->json('source_symbols');
            $table->json('symbol_mappings');
            $table->json('missing_symbols');
            $table->unsignedInteger('member_count');
            $table->unsignedInteger('matched_count');
            $table->timestamps();
        });
    }
};
