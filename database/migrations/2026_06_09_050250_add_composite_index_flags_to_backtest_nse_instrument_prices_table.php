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
            $table->boolean('is_nifty_largemidcap_250')->default(false)->after('is_nifty_midcap_150');
            $table->boolean('is_nifty_midsmallcap_400')->default(false)->after('is_nifty_smallcap_250');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backtest_nse_instrument_prices', function (Blueprint $table) {
            $table->dropColumn(['is_nifty_largemidcap_250', 'is_nifty_midsmallcap_400']);
        });
    }
};
