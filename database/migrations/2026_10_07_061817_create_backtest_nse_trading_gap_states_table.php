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
        Schema::create('backtest_nse_trading_gap_states', function (Blueprint $table) {
            $table->id();
            $table->date('processed_through')->nullable();
            $table->boolean('requires_rebuild')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backtest_nse_trading_gap_states');
    }
};
