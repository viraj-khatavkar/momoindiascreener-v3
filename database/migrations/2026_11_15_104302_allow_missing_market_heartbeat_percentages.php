<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const PERCENTAGES = [
        'percentage_above_ma_200',
        'percentage_above_ma_100',
        'percentage_above_ma_50',
        'percentage_above_ma_20',
        'percentage_of_stocks_with_returns_one_year_above_zero',
        'percentage_of_stocks_with_returns_one_year_above_ten',
        'percentage_of_stocks_with_returns_one_year_above_hundred',
        'percentage_of_stocks_within_ten_percent_of_ath',
        'percentage_of_stocks_within_twenty_percent_of_ath',
        'percentage_of_stocks_within_thirty_percent_of_ath',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('market_heartbeats', function (Blueprint $table) {
            foreach (self::PERCENTAGES as $column) {
                $table->decimal($column, 10, 2)->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('market_heartbeats', function (Blueprint $table) {
            foreach (self::PERCENTAGES as $column) {
                $table->decimal($column, 10, 2)->nullable(false)->change();
            }
        });
    }
};
