<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const COLUMNS = [
        'is_nifty_ai_catalysts',
        'is_nifty_alpha_50',
        'is_nifty_alpha_low_volatility_30',
        'is_nifty_alpha_quality_low_volatility_30',
        'is_nifty_alpha_quality_value_low_volatility_30',
        'is_nifty_auto',
        'is_nifty_bank',
        'is_nifty_capital_goods',
        'is_nifty_capital_markets',
        'is_nifty_cement',
        'is_nifty_chemicals',
        'is_nifty_commercial_transport_services',
        'is_nifty_commodities',
        'is_nifty_conglomerate_50',
        'is_nifty_construction',
        'is_nifty_consumer_durables',
        'is_nifty_consumer_services',
        'is_nifty_core_housing',
        'is_nifty_cpse',
        'is_nifty_dividend_opportunities_50',
        'is_nifty_energy',
        'is_nifty_ev_new_age_automotive',
        'is_nifty_financial_services',
        'is_nifty_financial_services_2550',
        'is_nifty_financial_services_ex_bank',
        'is_nifty_fmcg',
        'is_nifty_growth_sectors_15',
        'is_nifty_healthcare_index',
        'is_nifty_high_beta_50',
        'is_nifty_high_dividend_yield_15',
        'is_nifty_hospitals',
        'is_nifty_housing_finance',
        'is_nifty_india_consumption',
        'is_nifty_india_corporate_group_index_aditya_birla_group',
        'is_nifty_india_corporate_group_index_mahindra_group',
        'is_nifty_india_corporate_group_index_tata_group',
        'is_nifty_india_corporate_group_index_tata_group_25_cap',
        'is_nifty_india_defence',
        'is_nifty_india_defence_equal_weight',
        'is_nifty_india_digital',
        'is_nifty_india_fpi_150',
        'is_nifty_india_infrastructure_logistics',
        'is_nifty_india_internet',
        'is_nifty_india_manufacturing',
        'is_nifty_india_new_age_consumption',
        'is_nifty_india_railways_psu',
        'is_nifty_india_select_5_corporate_groups_maatr',
        'is_nifty_india_tourism',
        'is_nifty_infrastructure',
        'is_nifty_insurance',
        'is_nifty_ipo',
        'is_nifty_it',
        'is_nifty_low_volatility_50',
        'is_nifty_media',
        'is_nifty_metal',
        'is_nifty_midcap_50',
        'is_nifty_midcap_liquid_15',
        'is_nifty_midcap_select',
        'is_nifty_midcap150_momentum_50',
        'is_nifty_midcap150_quality_50',
        'is_nifty_midsmall_financial_services',
        'is_nifty_midsmall_healthcare',
        'is_nifty_midsmall_it_telecom',
        'is_nifty_midsmallcap400_5050',
        'is_nifty_midsmallcap400_momentum_quality_100',
        'is_nifty_mnc',
        'is_nifty_mobility',
        'is_nifty_nbfc',
        'is_nifty_next_100',
        'is_nifty_non_cyclical_consumer',
        'is_nifty_oil_gas',
        'is_nifty_pharma',
        'is_nifty_power',
        'is_nifty_private_bank',
        'is_nifty_pse',
        'is_nifty_psu_bank',
        'is_nifty_quality_low_volatility_30',
        'is_nifty_realty',
        'is_nifty_reits_invits',
        'is_nifty_reits_invits_9010',
        'is_nifty_reits_realty',
        'is_nifty_retail',
        'is_nifty_rural',
        'is_nifty_services_sector',
        'is_nifty_small_finance_banks_microfinance_institutions',
        'is_nifty_smallcap_100',
        'is_nifty_smallcap_50',
        'is_nifty_smallcap_500',
        'is_nifty_smallcap250_momentum_quality_100',
        'is_nifty_smallcap250_quality_50',
        'is_nifty_sme_emerge',
        'is_nifty_sugar_ethanol',
        'is_nifty_telecommunications',
        'is_nifty_top_10_equal_weight',
        'is_nifty_top_15_equal_weight',
        'is_nifty_top_20_equal_weight',
        'is_nifty_total_market_momentum_quality_50',
        'is_nifty_transportation_logistics',
        'is_nifty_waves',
        'is_nifty100_alpha_30',
        'is_nifty100_equal_weight',
        'is_nifty100_liquid_15',
        'is_nifty100_low_volatility_30',
        'is_nifty100_quality_30',
        'is_nifty1000_equal_weight',
        'is_nifty200_alpha_30',
        'is_nifty200_momentum_30',
        'is_nifty200_quality_30',
        'is_nifty200_value_30',
        'is_nifty50_equal_weight',
        'is_nifty50_value_20',
        'is_nifty500_ahimsa',
        'is_nifty500_equal_weight',
        'is_nifty500_flexicap_quality_30',
        'is_nifty500_growth_50',
        'is_nifty500_healthcare',
        'is_nifty500_largemidsmall_equal_cap_weighted',
        'is_nifty500_low_volatility_50',
        'is_nifty500_momentum_50',
        'is_nifty500_multicap_502525',
        'is_nifty500_multicap_india_manufacturing_503020',
        'is_nifty500_multicap_infrastructure_503020',
        'is_nifty500_multicap_momentum_quality_50',
        'is_nifty500_multifactor_mqvlv_50',
        'is_nifty500_quality_50',
        'is_nifty500_value_50',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            /** One ALTER avoids one InnoDB instant row version per added column. */
            $columns = array_map(fn (string $column): string => "ADD COLUMN `{$column}` TINYINT(1) NOT NULL DEFAULT 0", self::COLUMNS);
            DB::statement('ALTER TABLE `backtest_nse_instrument_prices` '.implode(', ', $columns).', ALGORITHM=INSTANT');

            return;
        }

        Schema::table('backtest_nse_instrument_prices', function (Blueprint $table): void {
            foreach (self::COLUMNS as $column) {
                $table->boolean($column)->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('backtest_nse_instrument_prices', function (Blueprint $table): void {
            $table->dropColumn(self::COLUMNS);
        });
    }
};
