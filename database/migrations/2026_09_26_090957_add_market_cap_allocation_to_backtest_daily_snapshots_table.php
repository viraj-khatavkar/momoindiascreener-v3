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
        Schema::table('backtest_daily_snapshots', function (Blueprint $table) {
            $table->json('market_cap_allocation')->nullable();
            $table->timestamp('market_cap_allocation_calculated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backtest_daily_snapshots', function (Blueprint $table) {
            $table->dropColumn(['market_cap_allocation', 'market_cap_allocation_calculated_at']);
        });
    }
};
