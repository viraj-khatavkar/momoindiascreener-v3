<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('backtest_summary_metrics', function (Blueprint $table) {
            $table->double('cagr')->nullable()->change();
            $table->double('profit_factor')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('backtest_summary_metrics')->whereNull('cagr')
            ->orWhereRaw('ABS(cagr) > 999999.9999')
            ->orWhereRaw('ABS(profit_factor) > 999999.9999')->exists()) {
            throw new RuntimeException('Cannot reduce metric storage while results contain unavailable or large values. The existing results have been preserved.');
        }

        Schema::table('backtest_summary_metrics', function (Blueprint $table) {
            $table->decimal('cagr', 10, 4)->nullable(false)->change();
            $table->decimal('profit_factor', 10, 4)->nullable()->change();
        });
    }
};
