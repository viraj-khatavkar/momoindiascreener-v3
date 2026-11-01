<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('market_index_aliases', function (Blueprint $table) {
            $table->string('symbol')->default('')->after('normalized_label');
            $table->dropUnique(['normalized_label']);
            $table->unique(['normalized_label', 'symbol']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('market_index_aliases')->select('normalized_label')->groupBy('normalized_label')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Symbol-specific ETF mappings must be resolved before this migration can be reversed.');
        }

        Schema::table('market_index_aliases', function (Blueprint $table) {
            $table->dropUnique(['normalized_label', 'symbol']);
            $table->dropColumn('symbol');
            $table->unique('normalized_label');
        });
    }
};
