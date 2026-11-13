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
            $table->string('cash_call_source')->default('index');
            $table->foreignId('cash_call_backtest_id')->nullable()->constrained('backtests')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backtests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_call_backtest_id');
            $table->dropColumn('cash_call_source');
        });
    }
};
