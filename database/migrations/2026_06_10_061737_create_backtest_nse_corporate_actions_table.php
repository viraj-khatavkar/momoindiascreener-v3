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
        Schema::create('backtest_nse_corporate_actions', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index(); // the ex-date of the action
            $table->string('symbol');
            $table->string('series')->nullable();
            $table->string('type')->nullable();
            $table->string('description')->nullable();
            $table->string('ratio')->nullable();
            $table->string('dividend')->nullable();
            $table->string('dividend_adjustment_factor')->nullable();
            $table->string('price_adjustment_factor')->nullable();
            $table->timestamp('dividend_adjustment_applied_at')->nullable();
            $table->timestamp('price_adjustment_applied_at')->nullable();
            $table->index(['symbol', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backtest_nse_corporate_actions');
    }
};
