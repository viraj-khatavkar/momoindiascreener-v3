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
        Schema::table('backtest_summary_metrics', function (Blueprint $table) {
            $table->decimal('sharpe_ratio', 10, 4)->nullable()->after('max_drawdown_end_date');
            $table->decimal('winners_percentage', 10, 4)->nullable()->after('sharpe_ratio');
            $table->decimal('ulcer_index', 10, 4)->nullable()->after('winners_percentage');
            $table->decimal('k_ratio', 10, 4)->nullable()->after('ulcer_index');
            $table->decimal('profit_factor', 10, 4)->nullable()->after('k_ratio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backtest_summary_metrics', function (Blueprint $table) {
            $table->dropColumn(['sharpe_ratio', 'winners_percentage', 'ulcer_index', 'k_ratio', 'profit_factor']);
        });
    }
};
