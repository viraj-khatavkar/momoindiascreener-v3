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
            // Defaults mirror the previously hardcoded statutory rates so
            // existing backtests keep producing identical results.
            $table->decimal('brokerage_rate', 10, 5)->default(0)->after('cash_return_rate');
            $table->decimal('stt_rate', 10, 5)->default(0.1)->after('brokerage_rate');
            $table->decimal('transaction_charges_rate', 10, 5)->default(0.00307)->after('stt_rate');
            $table->decimal('sebi_charges_rate', 10, 5)->default(0.00001)->after('transaction_charges_rate');
            $table->decimal('gst_rate', 10, 5)->default(18)->after('sebi_charges_rate');
            $table->decimal('stamp_charges_rate', 10, 5)->default(0.015)->after('gst_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backtests', function (Blueprint $table) {
            $table->dropColumn([
                'brokerage_rate',
                'stt_rate',
                'transaction_charges_rate',
                'sebi_charges_rate',
                'gst_rate',
                'stamp_charges_rate',
            ]);
        });
    }
};
