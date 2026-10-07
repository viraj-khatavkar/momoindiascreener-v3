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
        Schema::create('backtest_nse_trading_gaps', function (Blueprint $table) {
            $table->id();
            $table->string('symbol');
            $table->date('last_traded_date');
            $table->date('confirmation_date')->nullable();
            $table->date('resumed_date')->nullable();
            $table->unique(['symbol', 'last_traded_date'], 'bntg_symbol_date_unique');
            $table->index(['last_traded_date', 'confirmation_date'], 'bntg_exit_confirmation_index');
            $table->index(['resumed_date', 'symbol'], 'bntg_open_symbol_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backtest_nse_trading_gaps');
    }
};
