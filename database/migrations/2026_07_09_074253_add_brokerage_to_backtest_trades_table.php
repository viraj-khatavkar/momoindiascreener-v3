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
        Schema::table('backtest_trades', function (Blueprint $table) {
            $table->decimal('brokerage', 20, 2)->default(0)->after('stamp_charges');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backtest_trades', function (Blueprint $table) {
            $table->dropColumn('brokerage');
        });
    }
};
