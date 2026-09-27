<?php

use App\Enums\AdminProcessRunStatusEnum;
use App\Enums\CorporateActionTypeEnum;
use App\Models\AdminProcessRun;
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
            ->where('instrument.date', '2020-01-27')
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
            ->where('instrument.date', '2019-04-01')
            ->where('instrument.name', 'Gitanjali Gems')
            ->where('instrument.close_adjusted', '25.00')
        );
});

it('returns 404 for an unknown symbol', function () {
    createBacktestPriceRow('TCS', '2020-01-27', ['is_nifty_allcap' => true]);

    $this->get('/instruments/UNKNOWN')->assertNotFound();
});

it('excludes unfinished process dates from all instrument data', function (AdminProcessRunStatusEnum $status) {
    createBacktestPriceRow('TCS', '2020-01-23');
    createBacktestPriceRow('TCS', '2020-01-24', ['close_adjusted' => 50]);
    createBacktestPriceRow('TCS', '2020-01-27', [
        'close_adjusted' => 100,
        'ma_200' => 90,
    ]);
    createBacktestPriceRow('TCS', '2020-01-28', [
        'close_adjusted' => 200,
        'ma_200' => 300,
    ]);

    AdminProcessRun::factory()->create([
        'process_date' => '2020-01-27',
        'status' => AdminProcessRunStatusEnum::Completed,
    ]);

    foreach (['2020-01-24', '2020-01-28'] as $date) {
        AdminProcessRun::factory()->create([
            'process_date' => $date,
            'status' => $status,
        ]);
    }

    foreach (['2020-01-23', '2020-01-24', '2020-01-27', '2020-01-28'] as $date) {
        createCorporateAction('TCS', $date, [
            'type' => CorporateActionTypeEnum::DIVIDEND,
            'description' => 'Corporate Action: EQ TCS DIV RS 5 PER SH',
            'dividend' => '5',
            'dividend_adjustment_factor' => '0.98',
        ]);
        createCorporateAction('TCS', $date, [
            'type' => CorporateActionTypeEnum::BONUS,
            'description' => 'Corporate Action: EQ TCS BONUS 1:2',
            'price_adjustment_factor' => '0.5',
        ]);
    }

    $this->get('/instruments/TCS')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('InstrumentView')
            ->where('instrument.date', '2020-01-27')
            ->where('instrument.close_adjusted', '100.00')
            ->where('instrument.ma_200', '90.0000000000')
            ->where('pros', fn ($pros) => $pros->contains('The close is above 200-day moving average.'))
            ->where('cons', fn ($cons) => ! $cons->contains('The close is below 200-day moving average.'))
            ->loadDeferredProps('chart', fn (Assert $reload) => $reload
                ->has('priceHistory', 2)
                ->where('priceHistory.0.close_adjusted', '0.00')
                ->where('priceHistory.1.close_adjusted', '100.00')
            )
            ->loadDeferredProps('extras', fn (Assert $reload) => $reload
                ->has('dividends', 2)
                ->where('dividends.0.date', '2020-01-27')
                ->where('dividends.1.date', '2020-01-23')
                ->has('corporateActions', 2)
                ->where('corporateActions.0.date', '2020-01-27')
                ->where('corporateActions.1.date', '2020-01-23')
            )
        );
})->with([
    'pending' => AdminProcessRunStatusEnum::Pending,
    'in progress' => AdminProcessRunStatusEnum::InProgress,
    'failed' => AdminProcessRunStatusEnum::Failed,
]);

it('shows the new instrument data after its process completes', function () {
    createBacktestPriceRow('TCS', '2020-01-27', ['close_adjusted' => 100]);
    createBacktestPriceRow('TCS', '2020-01-28', ['close_adjusted' => 200]);
    createCorporateAction('TCS', '2020-01-28', [
        'type' => CorporateActionTypeEnum::DIVIDEND,
        'description' => 'Corporate Action: EQ TCS DIV RS 5 PER SH',
        'dividend' => '5',
        'dividend_adjustment_factor' => '0.98',
    ]);

    $run = AdminProcessRun::factory()->create([
        'process_date' => '2020-01-28',
        'status' => AdminProcessRunStatusEnum::InProgress,
    ]);

    $this->get('/instruments/TCS')
        ->assertInertia(fn (Assert $page) => $page
            ->where('instrument.date', '2020-01-27')
            ->loadDeferredProps('chart', fn (Assert $reload) => $reload->has('priceHistory', 1))
            ->loadDeferredProps('extras', fn (Assert $reload) => $reload->has('dividends', 0))
        );

    $run->update([
        'status' => AdminProcessRunStatusEnum::Completed,
        'completed_at' => now(),
    ]);

    $this->get('/instruments/TCS')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('instrument.date', '2020-01-28')
            ->where('instrument.close_adjusted', '200.00')
            ->loadDeferredProps('chart', fn (Assert $reload) => $reload
                ->has('priceHistory', 2)
                ->where('priceHistory.1.close_adjusted', '200.00')
            )
            ->loadDeferredProps('extras', fn (Assert $reload) => $reload
                ->has('dividends', 1)
                ->where('dividends.0.date', '2020-01-28')
            )
        );
});

it('returns 404 when an instrument only has data for an unfinished process', function () {
    createBacktestPriceRow('TCS', '2020-01-28');

    AdminProcessRun::factory()->create([
        'process_date' => '2020-01-28',
        'status' => AdminProcessRunStatusEnum::InProgress,
    ]);

    $this->get('/instruments/TCS')->assertNotFound();
});
