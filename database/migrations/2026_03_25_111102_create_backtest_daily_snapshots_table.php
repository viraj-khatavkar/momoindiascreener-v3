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
        Schema::create('backtest_daily_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backtest_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('nav', 20, 6);
            $table->decimal('portfolio_value', 20, 2);
            $table->decimal('cash', 20, 2);
            $table->decimal('total_value', 20, 2);
            $table->unsignedInteger('holdings_count');
            $table->decimal('benchmark_close', 20, 2);
            $table->decimal('benchmark_nav', 20, 6);

            $table->unique(['backtest_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backtest_daily_snapshots');
    }
};
