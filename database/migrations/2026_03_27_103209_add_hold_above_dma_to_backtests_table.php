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
        Schema::table('backtests', function (Blueprint $table) {
            $table->boolean('apply_hold_above_dma')->default(false)->after('worst_rank_held');
            $table->unsignedSmallInteger('hold_above_dma_period')->default(200)->after('apply_hold_above_dma');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backtests', function (Blueprint $table) {
            $table->dropColumn(['apply_hold_above_dma', 'hold_above_dma_period']);
        });
    }
};
