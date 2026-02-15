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
        Schema::create('backtest_nse_instrument_prices', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('symbol');
            $table->string('series')->nullable();
            $table->boolean('is_delisted')->default(false);
            $table->decimal('open_adjusted', 20, 2);
            $table->decimal('high_adjusted', 20, 2);
            $table->decimal('low_adjusted', 20, 2);
            $table->decimal('close_adjusted', 20, 2);
            $table->unsignedBigInteger('volume_adjusted');
            $table->unsignedBigInteger('volume_shares_adjusted');
            $table->decimal('open_raw', 20, 2);
            $table->decimal('high_raw', 20, 2);
            $table->decimal('low_raw', 20, 2);
            $table->decimal('close_raw', 20, 2);
            $table->unsignedBigInteger('volume_raw');
            $table->unsignedBigInteger('volume_shares_raw');
            $table->decimal('t_percent', 20, 2);
            $table->decimal('t_percent_raw', 20, 10);
            $table->unsignedBigInteger('marketcap')->nullable();
            $table->decimal('price_to_earnings', 8, 2)->nullable();
            $table->json('corporate_actions')->nullable();
            $table->string('price_adjustment_factor')->nullable();
            $table->string('dividend_adjustment_factor')->nullable();
            $table->string('dividend')->nullable();
            $table->decimal('variance_one_year', 20, 10)->nullable();
            $table->decimal('variance_nine_months', 20, 10)->nullable();
            $table->decimal('variance_six_months', 20, 10)->nullable();
            $table->decimal('variance_three_months', 20, 10)->nullable();
            $table->decimal('variance_one_months', 20, 10)->nullable();
            $table->decimal('standard_deviation_one_year', 20, 10)->nullable();
            $table->decimal('standard_deviation_nine_months', 20, 10)->nullable();
            $table->decimal('standard_deviation_six_months', 20, 10)->nullable();
            $table->decimal('standard_deviation_three_months', 20, 10)->nullable();
            $table->decimal('standard_deviation_one_months', 20, 10)->nullable();
            $table->decimal('volatility_one_year', 20, 10)->nullable();
            $table->decimal('volatility_nine_months', 20, 10)->nullable();
            $table->decimal('volatility_six_months', 20, 10)->nullable();
            $table->decimal('volatility_three_months', 20, 10)->nullable();
            $table->decimal('volatility_one_months', 20, 10)->nullable();
            $table->decimal('covariance', 20, 10)->nullable();
            $table->decimal('beta', 20, 10)->nullable();
            // Absolute Return
            $table->decimal('absolute_return_one_year', 20, 2)->nullable();
            $table->decimal('absolute_return_nine_months', 20, 2)->nullable();
            $table->decimal('absolute_return_six_months', 20, 2)->nullable();
            $table->decimal('absolute_return_three_months', 20, 2)->nullable();
            $table->decimal('absolute_return_one_months', 20, 2)->nullable();
            // Average Absolute Return
            $table->decimal('average_absolute_return_twelve_nine_six_three_one_months', 20, 2)->nullable();
            $table->decimal('average_absolute_return_twelve_nine_six_three_months', 20, 2)->nullable();
            $table->decimal('average_absolute_return_twelve_nine_six_months', 20, 2)->nullable();
            $table->decimal('average_absolute_return_twelve_nine_months', 20, 2)->nullable();
            $table->decimal('average_absolute_return_twelve_six_three_one_months', 20, 2)->nullable();
            $table->decimal('average_absolute_return_twelve_six_three_months', 20, 2)->nullable();
            $table->decimal('average_absolute_return_twelve_six_months', 20, 2)->nullable();
            $table->decimal('average_absolute_return_twelve_three_one_months', 20, 2)->nullable();
            $table->decimal('average_absolute_return_twelve_three_months', 20, 2)->nullable();
            $table->decimal('average_absolute_return_twelve_nine_three_one_months', 20, 2)->nullable();
            $table->decimal('average_absolute_return_twelve_nine_three_months', 20, 2)->nullable();
            $table->decimal('average_absolute_return_nine_six_three_one_months', 20, 2)->nullable();
            $table->decimal('average_absolute_return_nine_six_three_months', 20, 2)->nullable();
            $table->decimal('average_absolute_return_nine_six_months', 20, 2)->nullable();
            $table->decimal('average_absolute_return_six_three_one_months', 20, 2)->nullable();
            $table->decimal('average_absolute_return_six_three_months', 20, 2)->nullable();
            $table->decimal('average_absolute_return_three_one_months', 20, 2)->nullable();
            // Sharpe Return
            $table->decimal('sharpe_return_one_year', 20, 2)->nullable();
            $table->decimal('sharpe_return_nine_months', 20, 2)->nullable();
            $table->decimal('sharpe_return_six_months', 20, 2)->nullable();
            $table->decimal('sharpe_return_three_months', 20, 2)->nullable();
            $table->decimal('sharpe_return_one_months', 20, 2)->nullable();
            // Average Sharpe Return
            $table->decimal('average_sharpe_return_twelve_nine_six_three_one_months', 20, 2)->nullable();
            $table->decimal('average_sharpe_return_twelve_nine_six_three_months', 20, 2)->nullable();
            $table->decimal('average_sharpe_return_twelve_nine_six_months', 20, 2)->nullable();
            $table->decimal('average_sharpe_return_twelve_nine_months', 20, 2)->nullable();
            $table->decimal('average_sharpe_return_twelve_six_three_one_months', 20, 2)->nullable();
            $table->decimal('average_sharpe_return_twelve_six_three_months', 20, 2)->nullable();
            $table->decimal('average_sharpe_return_twelve_six_months', 20, 2)->nullable();
            $table->decimal('average_sharpe_return_twelve_three_one_months', 20, 2)->nullable();
            $table->decimal('average_sharpe_return_twelve_three_months', 20, 2)->nullable();
            $table->decimal('average_sharpe_return_twelve_nine_three_one_months', 20, 2)->nullable();
            $table->decimal('average_sharpe_return_twelve_nine_three_months', 20, 2)->nullable();
            $table->decimal('average_sharpe_return_nine_six_three_one_months', 20, 2)->nullable();
            $table->decimal('average_sharpe_return_nine_six_three_months', 20, 2)->nullable();
            $table->decimal('average_sharpe_return_nine_six_months', 20, 2)->nullable();
            $table->decimal('average_sharpe_return_six_three_one_months', 20, 2)->nullable();
            $table->decimal('average_sharpe_return_six_three_months', 20, 2)->nullable();
            $table->decimal('average_sharpe_return_three_one_months', 20, 2)->nullable();
            // RSI
            $table->decimal('rsi_one_year', 8, 2)->nullable();
            $table->decimal('rsi_nine_months', 8, 2)->nullable();
            $table->decimal('rsi_six_months', 8, 2)->nullable();
            $table->decimal('rsi_three_months', 8, 2)->nullable();
            $table->decimal('rsi_one_months', 8, 2)->nullable();
            // Average RSI
            $table->decimal('average_rsi_twelve_nine_six_three_one_months', 8, 2)->nullable();
            $table->decimal('average_rsi_twelve_nine_six_three_months', 8, 2)->nullable();
            $table->decimal('average_rsi_twelve_nine_six_months', 8, 2)->nullable();
            $table->decimal('average_rsi_twelve_nine_months', 8, 2)->nullable();
            $table->decimal('average_rsi_twelve_six_three_one_months', 8, 2)->nullable();
            $table->decimal('average_rsi_twelve_six_three_months', 8, 2)->nullable();
            $table->decimal('average_rsi_twelve_six_months', 8, 2)->nullable();
            $table->decimal('average_rsi_twelve_three_one_months', 8, 2)->nullable();
            $table->decimal('average_rsi_twelve_three_months', 8, 2)->nullable();
            $table->decimal('average_rsi_twelve_nine_three_one_months', 8, 2)->nullable();
            $table->decimal('average_rsi_twelve_nine_three_months', 8, 2)->nullable();
            $table->decimal('average_rsi_nine_six_three_one_months', 8, 2)->nullable();
            $table->decimal('average_rsi_nine_six_three_months', 8, 2)->nullable();
            $table->decimal('average_rsi_nine_six_months', 8, 2)->nullable();
            $table->decimal('average_rsi_six_three_one_months', 8, 2)->nullable();
            $table->decimal('average_rsi_six_three_months', 8, 2)->nullable();
            $table->decimal('average_rsi_three_one_months', 8, 2)->nullable();
            // Beta Return
            $table->decimal('absolute_divide_beta_return_one_year', 20, 2)->nullable();
            $table->decimal('sharpe_divide_beta_return_one_year', 20, 2)->nullable();
            $table->decimal('average_sharpe_divide_beta_return_twelve_nine_six_three_months', 20, 2)->nullable();
            $table->decimal('average_sharpe_divide_beta_return_twelve_six_three_months', 20, 2)->nullable();
            $table->decimal('average_sharpe_divide_beta_return_twelve_six_months', 20, 2)->nullable();
            // Other Returns
            $table->decimal('return_twelve_minus_one_months', 20, 2)->nullable();
            $table->decimal('return_twelve_minus_two_months', 20, 2)->nullable();
            // Circuits
            $table->integer('circuits_one_year')->default(0)->nullable();
            $table->integer('circuits_nine_months')->default(0)->nullable();
            $table->integer('circuits_six_months')->default(0)->nullable();
            $table->integer('circuits_three_months')->default(0)->nullable();
            $table->integer('circuits_one_months')->default(0)->nullable();
            // Positive days
            $table->decimal('positive_days_percent_one_year', 20, 10)->nullable();
            $table->decimal('positive_days_percent_nine_months', 20, 10)->nullable();
            $table->decimal('positive_days_percent_six_months', 20, 10)->nullable();
            $table->decimal('positive_days_percent_three_months', 20, 10)->nullable();
            $table->decimal('positive_days_percent_one_months', 20, 10)->nullable();
            // Away from high
            $table->decimal('away_from_high_one_year', 20, 10)->nullable();
            $table->decimal('away_from_high_all_time', 20, 10)->nullable();
            // Highs
            $table->decimal('high_one_year', 20, 10)->nullable();
            $table->decimal('high_all_time', 20, 10)->nullable();
            // Moving Averages
            $table->decimal('ma_200', 20, 10)->nullable();
            $table->decimal('ma_100', 20, 10)->nullable();
            $table->decimal('ma_50', 20, 10)->nullable();
            $table->decimal('ma_20', 20, 10)->nullable();
            $table->decimal('ema_200', 20, 10)->nullable();
            $table->decimal('ema_100', 20, 10)->nullable();
            $table->decimal('ema_50', 20, 10)->nullable();
            $table->decimal('ema_20', 20, 10)->nullable();
            // Volume
            $table->unsignedBigInteger('median_volume_one_year')->nullable();
            $table->unsignedBigInteger('volume_day')->nullable();
            $table->unsignedBigInteger('volume_one_year_average')->nullable();
            $table->unsignedBigInteger('volume_nine_months_average')->nullable();
            $table->unsignedBigInteger('volume_six_months_average')->nullable();
            $table->unsignedBigInteger('volume_three_months_average')->nullable();
            $table->unsignedBigInteger('volume_one_months_average')->nullable();
            $table->unsignedBigInteger('volume_week_average')->nullable();
            // Indices
            $table->boolean('is_nifty_50')->default(false);
            $table->boolean('is_nifty_next_50')->default(false);
            $table->boolean('is_nifty_100')->default(false);
            $table->boolean('is_nifty_200')->default(false);
            $table->boolean('is_nifty_midcap_100')->default(false);
            $table->boolean('is_nifty_500')->default(false);
            $table->boolean('is_nifty_smallcap_250')->default(false);
            $table->boolean('is_nifty_allcap')->default(false);
            // ETF
            $table->boolean('is_etf')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backtest_nse_instrument_prices');
    }
};
