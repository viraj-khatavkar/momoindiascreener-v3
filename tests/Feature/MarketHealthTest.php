<?php

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

it('shows all tracked equity universes on the market health page', function () {
    DB::table('market_heartbeats')->insert([
        'index' => 'nifty-midcap-100',
        'date' => '2020-01-01',
        'percentage_above_ma_200' => 75,
        'percentage_above_ma_100' => 80,
        'percentage_above_ma_50' => 85,
        'percentage_above_ma_20' => 90,
        'percentage_of_stocks_with_returns_one_year_above_zero' => 70,
        'percentage_of_stocks_with_returns_one_year_above_ten' => 60,
        'percentage_of_stocks_with_returns_one_year_above_hundred' => 5,
        'percentage_of_stocks_within_ten_percent_of_ath' => 20,
        'percentage_of_stocks_within_twenty_percent_of_ath' => 40,
        'percentage_of_stocks_within_thirty_percent_of_ath' => 60,
        'advances' => 75,
        'declines' => 25,
        'advance_decline_ratio' => 3,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->get('/market-health/nifty-midcap-100')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MarketHealth')
            ->where('index', 'nifty-midcap-100')
            ->where('indexName', 'Nifty Midcap 100')
            ->where('availableIndices', [
                'nifty-50',
                'nifty-next-50',
                'nifty-100',
                'nifty-200',
                'nifty-midcap-100',
                'nifty-midcap-150',
                'nifty-500',
                'nifty-smallcap-250',
                'nifty-largemidcap-250',
                'nifty-midsmallcap-400',
                'nifty-allcap',
            ])
            ->has('heartbeats', 1)
        );
});
