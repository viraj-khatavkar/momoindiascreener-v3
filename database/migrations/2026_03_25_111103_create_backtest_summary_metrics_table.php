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
        Schema::create('backtest_summary_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backtest_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('cagr', 10, 4);
            $table->decimal('benchmark_cagr', 10, 4);
            $table->decimal('max_drawdown', 10, 4);
            $table->date('max_drawdown_start_date')->nullable();
            $table->date('max_drawdown_end_date')->nullable();
            $table->decimal('benchmark_max_drawdown', 10, 4);
            $table->unsignedInteger('total_trades');
            $table->decimal('total_charges_paid', 20, 2);
            $table->decimal('final_value', 20, 2);
            $table->json('rolling_returns_one_year')->nullable();
            $table->json('rolling_returns_three_year')->nullable();
            $table->json('rolling_returns_five_year')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backtest_summary_metrics');
    }
};
