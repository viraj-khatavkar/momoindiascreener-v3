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
        Schema::table('backtest_nse_instruments', function (Blueprint $table) {
            $table->foreignId('market_index_alias_id')
                ->nullable()
                ->after('etf_index')
                ->constrained()
                ->nullOnDelete();
            $table->date('etf_index_source_date')->nullable()->after('market_index_alias_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backtest_nse_instruments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('market_index_alias_id');
            $table->dropColumn('etf_index_source_date');
        });
    }
};
