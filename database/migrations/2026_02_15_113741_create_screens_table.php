<?php

use App\Models\User;
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
        Schema::create('screens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignIdFor(User::class);
            $table->string('index');
            $table->string('sort_by');
            $table->string('sort_direction');
            $table->unsignedBigInteger('median_volume_one_year');
            $table->decimal('minimum_return_one_year');
            $table->boolean('apply_ma');
            $table->boolean('above_ma_200');
            $table->boolean('above_ma_100');
            $table->boolean('above_ma_50');
            $table->boolean('above_ma_20');
            $table->boolean('below_ma_200');
            $table->boolean('below_ma_100');
            $table->boolean('below_ma_50');
            $table->boolean('below_ma_20');
            $table->integer('away_from_high_one_year');
            $table->integer('away_from_high_all_time');
            $table->integer('positive_days_percent_one_year');
            $table->integer('positive_days_percent_nine_months');
            $table->integer('positive_days_percent_six_months');
            $table->integer('positive_days_percent_three_months');
            $table->integer('positive_days_percent_one_months');
            $table->integer('circuits_one_year');
            $table->integer('circuits_nine_months');
            $table->integer('circuits_six_months');
            $table->integer('circuits_three_months');
            $table->integer('circuits_one_months');
            $table->unsignedBigInteger('marketcap_from');
            $table->unsignedBigInteger('marketcap_to');
            $table->boolean('apply_pe');
            $table->integer('price_to_earnings_from');
            $table->integer('price_to_earnings_to');
            $table->boolean('series_eq');
            $table->boolean('series_be');
            $table->integer('price_from');
            $table->integer('price_to');
            $table->string('apply_filters_on');
            $table->boolean('apply_factor_two');
            $table->string('factor_two_sort_by');
            $table->string('factor_two_sort_direction');
            $table->boolean('apply_factor_three');
            $table->string('factor_three_sort_by');
            $table->string('factor_three_sort_direction');
            $table->json('columns');
            $table->boolean('apply_historical_date');
            $table->date('historical_date');
            $table->boolean('apply_custom_filter_one');
            $table->string('custom_filter_one_value_one');
            $table->string('custom_filter_one_operator');
            $table->string('custom_filter_one_value_two');
            $table->boolean('apply_custom_filter_two');
            $table->string('custom_filter_two_value_one');
            $table->string('custom_filter_two_operator');
            $table->string('custom_filter_two_value_two');
            $table->boolean('apply_custom_filter_three');
            $table->string('custom_filter_three_value_one');
            $table->string('custom_filter_three_operator');
            $table->string('custom_filter_three_value_two');
            $table->boolean('apply_custom_filter_four');
            $table->string('custom_filter_four_value_one');
            $table->string('custom_filter_four_operator');
            $table->string('custom_filter_four_value_two');
            $table->boolean('apply_custom_filter_five');
            $table->string('custom_filter_five_value_one');
            $table->string('custom_filter_five_operator');
            $table->string('custom_filter_five_value_two');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screens');
    }
};
