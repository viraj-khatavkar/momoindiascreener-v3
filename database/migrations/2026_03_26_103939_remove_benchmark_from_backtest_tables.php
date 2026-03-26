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
        Schema::table('backtest_daily_snapshots', function (Blueprint $table) {
            $table->dropColumn(['benchmark_close', 'benchmark_nav']);
        });

        Schema::table('backtest_summary_metrics', function (Blueprint $table) {
            $table->dropColumn(['benchmark_cagr', 'benchmark_max_drawdown']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backtest_daily_snapshots', function (Blueprint $table) {
            $table->decimal('benchmark_close', 20, 2)->after('holdings_count');
            $table->decimal('benchmark_nav', 20, 6)->after('benchmark_close');
        });

        Schema::table('backtest_summary_metrics', function (Blueprint $table) {
            $table->decimal('benchmark_cagr', 10, 4)->after('cagr');
            $table->decimal('benchmark_max_drawdown', 10, 4)->after('max_drawdown_end_date');
        });
    }
};
