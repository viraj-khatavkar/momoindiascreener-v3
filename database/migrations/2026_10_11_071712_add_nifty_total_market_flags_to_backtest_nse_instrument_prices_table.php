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
            $table->boolean('is_nifty_total_market')->default(false)->after('is_nifty_500');
            $table->boolean('is_nifty_microcap_250')->default(false)->after('is_nifty_smallcap_250');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backtest_nse_instrument_prices', function (Blueprint $table) {
            $table->dropColumn(['is_nifty_total_market', 'is_nifty_microcap_250']);
        });
    }
};
