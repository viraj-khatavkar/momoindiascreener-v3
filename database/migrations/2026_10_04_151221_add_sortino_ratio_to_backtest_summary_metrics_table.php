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
            $table->decimal('sortino_ratio', 20, 4)->nullable()->after('sharpe_ratio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backtest_summary_metrics', function (Blueprint $table) {
            $table->dropColumn('sortino_ratio');
        });
    }
};
