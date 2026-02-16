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
        Schema::create('market_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('index');
            $table->decimal('percentage_above_ma_200', 10, 2);
            $table->decimal('percentage_above_ma_100', 10, 2);
            $table->decimal('percentage_above_ma_50', 10, 2);
            $table->decimal('percentage_above_ma_20', 10, 2);
            $table->decimal('percentage_of_stocks_with_returns_one_year_above_zero', 10, 2);
            $table->decimal('percentage_of_stocks_with_returns_one_year_above_ten', 10, 2);
            $table->decimal('percentage_of_stocks_with_returns_one_year_above_hundred', 10, 2);
            $table->decimal('percentage_of_stocks_within_ten_percent_of_ath', 10, 2);
            $table->decimal('percentage_of_stocks_within_twenty_percent_of_ath', 10, 2);
            $table->decimal('percentage_of_stocks_within_thirty_percent_of_ath', 10, 2);
            $table->integer('advances');
            $table->integer('declines');
            $table->decimal('advance_decline_ratio', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_heartbeats');
    }
};
