<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // backtest_nse_instrument_prices (1.9M rows, 2.2GB)
        Schema::table('backtest_nse_instrument_prices', function (Blueprint $table) {
            // Covers: WHERE date = ? AND symbol = ? (exact lookups, enforces data integrity)
            $table->unique(['date', 'symbol'], 'bnip_date_symbol_unique');

            // Covers: WHERE symbol = ? ORDER BY date (price history — eliminates filesort)
            $table->index(['symbol', 'date'], 'bnip_symbol_date_index');
        });

        // market_heartbeats (5K rows)
        // updateOrInsert uses (index, date); MarketHealthController queries WHERE index ORDER BY date
        Schema::table('market_heartbeats', function (Blueprint $table) {
            $table->unique(['index', 'date'], 'market_heartbeats_index_date_unique');
        });

        // backtest_nse_index_constituents (1K rows)
        // Queried by index column; enforces data integrity
        Schema::table('backtest_nse_index_constituents', function (Blueprint $table) {
            $table->unique(['index', 'symbol'], 'bnc_index_symbol_unique');
        });
    }

    public function down(): void
    {
        Schema::table('backtest_nse_instrument_prices', function (Blueprint $table) {
            $table->dropUnique('bnip_date_symbol_unique');
            $table->dropIndex('bnip_symbol_date_index');
        });

        Schema::table('market_heartbeats', function (Blueprint $table) {
            $table->dropUnique('market_heartbeats_index_date_unique');
        });

        Schema::table('backtest_nse_index_constituents', function (Blueprint $table) {
            $table->dropUnique('bnc_index_symbol_unique');
        });
    }
};
