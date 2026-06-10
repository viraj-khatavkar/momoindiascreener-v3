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
            $table->dropColumn([
                'corporate_actions',
                'price_adjustment_factor',
                'dividend_adjustment_factor',
                'dividend',
            ]);
        });
    }

    /**
     * Recreates the columns empty; the data itself lives in backtest_nse_corporate_actions
     * and is only restorable onto these columns from a pre-drop dump.
     */
    public function down(): void
    {
        Schema::table('backtest_nse_instrument_prices', function (Blueprint $table) {
            $table->json('corporate_actions')->nullable();
            $table->string('price_adjustment_factor')->nullable();
            $table->string('dividend_adjustment_factor')->nullable();
            $table->string('dividend')->nullable();
        });
    }
};
