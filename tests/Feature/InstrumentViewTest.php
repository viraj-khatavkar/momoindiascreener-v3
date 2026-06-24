<?php

use App\Enums\CorporateActionTypeEnum;
use Inertia\Testing\AssertableInertia as Assert;

it('serves dividends and corporate actions from the corporate actions table', function () {
    createBacktestPriceRow('TCS', '2020-01-27', [
        'is_nifty_allcap' => true,
        'beta' => 1.0,
        'median_volume_one_year' => 50000000,
        'marketcap' => 500000,
    ]);

    createCorporateAction('TCS', '2020-01-27', [
        'type' => CorporateActionTypeEnum::DIVIDEND,
        'description' => 'Corporate Action: EQ TCS DIV RS 5 PER SH',
        'dividend' => '5',
        'dividend_adjustment_factor' => '0.98',
    ]);
    createCorporateAction('TCS', '2019-06-10', [
        'type' => CorporateActionTypeEnum::DIVIDEND,
        'description' => 'Corporate Action: EQ TCS DIV RS 3 PER SH',
        'dividend' => '3',
        'dividend_adjustment_factor' => '0.99',
    ]);
    createCorporateAction('TCS', '2020-01-20', [
        'type' => CorporateActionTypeEnum::BONUS,
        'description' => 'Corporate Action: EQ TCS BONUS 1:2',
        'price_adjustment_factor' => '0.5',
    ]);
    // A backfilled orphan (factors without a sentence) stays hidden from both lists.
    createCorporateAction('TCS', '2020-01-21', [
        'dividend_adjustment_factor' => '0.97',
        'price_adjustment_factor' => '0.6',
    ]);

    $this->get('/instruments/TCS')
        ->assertInertia(fn (Assert $page) => $page
            ->component('InstrumentView')
            ->missing('dividends')
            ->missing('corporateActions')
            ->loadDeferredProps('extras', fn (Assert $reload) => $reload
                ->has('dividends', 2)
                ->where('dividends.0.date', '2020-01-27')
                ->where('dividends.0.description', 'Corporate Action: EQ TCS DIV RS 5 PER SH')
                ->where('dividends.0.dividend', '5')
                ->where('dividends.1.date', '2019-06-10')
                ->where('dividends.1.dividend', '3')
                ->has('corporateActions', 1)
                ->where('corporateActions.0.date', '2020-01-20')
                ->where('corporateActions.0.description', 'Corporate Action: EQ TCS BONUS 1:2')
            )
        );
});

it('serves a historical instrument that is absent on the latest market date', function () {
    createBacktestPriceRow('TCS', '2020-01-27', ['is_nifty_allcap' => true]);

    createBacktestPriceRow('GITANJALI', '2019-03-29', [
        'close_adjusted' => 20,
        'beta' => 1.0,
        'median_volume_one_year' => 50000000,
        'marketcap' => 500000,
    ]);

    createBacktestPriceRow('GITANJALI', '2019-04-01', [
        'name' => 'Gitanjali Gems',
        'close_adjusted' => 25,
        'beta' => 1.0,
        'median_volume_one_year' => 50000000,
        'marketcap' => 500000,
    ]);

    $this->get('/instruments/GITANJALI')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('InstrumentView')
            ->where('instrument.symbol', 'GITANJALI')
            ->where('instrument.name', 'Gitanjali Gems')
            ->where('instrument.close_adjusted', '25.00')
        );
});

it('returns 404 for an unknown symbol', function () {
    createBacktestPriceRow('TCS', '2020-01-27', ['is_nifty_allcap' => true]);

    $this->get('/instruments/UNKNOWN')->assertNotFound();
});
