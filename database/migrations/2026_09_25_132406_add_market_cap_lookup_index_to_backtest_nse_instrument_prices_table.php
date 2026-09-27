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
        Schema::table('backtest_nse_instrument_prices', function (Blueprint $table) {
            $table->index(['date', 'is_nifty_100', 'is_nifty_midcap_150'], 'bnip_date_market_cap_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backtest_nse_instrument_prices', function (Blueprint $table) {
            $table->dropIndex('bnip_date_market_cap_index');
        });
    }
};
