<?php

use App\Actions\ReadPortfolioCsvAction;
use App\Enums\NseIndexEnum;
use App\Models\BacktestNseInstrument;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create(['is_paid' => true]);
});

it('requires authentication for each tracker endpoint', function (string $method, string $routeName) {
    $this->{$method}(route($routeName))->assertRedirect(route('login'));
})->with([
    ['get', 'rebalance-tracker.index'],
    ['post', 'rebalance-tracker.upload'],
    ['post', 'rebalance-tracker.analyze'],
]);

it('requires verified paid access for each tracker endpoint', function (string $method, string $routeName) {
    $this->user->update(['is_paid' => false]);
    $this->actingAs($this->user)->{$method}(route($routeName))->assertRedirect('/pricing');

    $this->user->update(['is_paid' => true, 'email_verified_at' => null]);
    $this->actingAs($this->user)->{$method}(route($routeName))->assertRedirect(route('verification.notice'));
})->with([
    ['get', 'rebalance-tracker.index'],
    ['post', 'rebalance-tracker.upload'],
    ['post', 'rebalance-tracker.analyze'],
]);

it('lists only the signed-in user screens with the fields needed by the tracker', function () {
    Screen::factory()->create();
    $screen = Screen::factory()->for($this->user)->create(['name' => 'My Momentum']);
    createScreenResultRow('AAA', 'Alpha', '2026-07-31');

    $this->actingAs($this->user)->get(route('rebalance-tracker.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('RebalanceTracker/Index')
            ->has('screens', 1)
            ->where('screens.0.id', $screen->id)
            ->missing('screens.0.user_id')
            ->missing('screens.0.columns')
            ->where('latestDate', '2026-07-31')
            ->where('portfolio', null)
            ->where('analysis', null)
        );
});

it('shows an empty tracker without screens or market data', function () {
    $this->actingAs($this->user)->get(route('rebalance-tracker.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('screens', 0)->where('latestDate', null));
});

it('normalizes CSV symbols and reports duplicates and missing latest prices', function () {
    createScreenResultRow('RELIANCE', 'Reliance', '2026-07-31');
    createScreenResultRow('M&M', 'Mahindra', '2026-07-31');
    createScreenResultRow('OLD', 'Old price', '2026-07-30');
    $csv = UploadedFile::fake()->createWithContent('portfolio.csv', "\xEF\xBB\xBF Symbol ,quantity\r\n reliance ,5\r\nM&M,2\r\nRELIANCE,3\r\nOLD,1\r\nUNKNOWN,1\r\n,0\r\n");

    $this->actingAs($this->user)->post(route('rebalance-tracker.upload'), ['csv' => $csv])
        ->assertRedirect(route('rebalance-tracker.index'))
        ->assertSessionHas('rebalance.portfolio', [
            'matched' => ['RELIANCE', 'M&M'],
            'unmatched' => ['OLD', 'UNKNOWN'],
            'total' => 4,
            'duplicates' => 1,
            'date' => '2026-07-31',
        ]);

    $this->get(route('rebalance-tracker.index'))->assertInertia(fn (Assert $page) => $page
        ->where('portfolio.matched', ['RELIANCE', 'M&M'])
        ->where('portfolio.unmatched', ['OLD', 'UNKNOWN'])
    );
});

it('reads quoted CSV columns and ignores blank lines', function (string $prefix) {
    createScreenResultRow('RELIANCE', 'Reliance', '2026-07-31');
    $csv = UploadedFile::fake()->createWithContent('portfolio.csv', $prefix."\"symbol\",\"name\"\n\"reliance\",\"Company, Limited\"\n\n");

    $this->actingAs($this->user)->post(route('rebalance-tracker.upload'), ['csv' => $csv])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('rebalance.portfolio.matched', ['RELIANCE']);
})->with(['without BOM' => '', 'with UTF-8 BOM' => "\xEF\xBB\xBF"]);

it('rejects invalid portfolio CSV content', function (string $content) {
    $csv = UploadedFile::fake()->createWithContent('portfolio.csv', $content);

    $this->actingAs($this->user)->from(route('rebalance-tracker.index'))
        ->post(route('rebalance-tracker.upload'), ['csv' => $csv])
        ->assertRedirect(route('rebalance-tracker.index'))
        ->assertSessionHasErrors('csv')
        ->assertSessionMissing('rebalance.portfolio');
})->with([
    'wrong header' => "ticker\nRELIANCE\n",
    'duplicate symbol header' => "symbol,Symbol\nRELIANCE,TCS\n",
    'no records' => "symbol\n\n",
    'blank symbol' => "symbol,quantity\n,1\n",
    'invalid symbol' => "symbol\n<script>\n",
    'long symbol' => "symbol\n".str_repeat('A', 51)."\n",
    'incomplete row' => "symbol,quantity\nRELIANCE\n",
    'extra column' => "symbol\nRELIANCE,1\n",
    'too many records' => "symbol\n".str_repeat("RELIANCE\n", ReadPortfolioCsvAction::MAX_SYMBOLS + 1),
]);

it('rejects missing files, other file types, and oversized files', function () {
    $this->actingAs($this->user)->post(route('rebalance-tracker.upload'))->assertSessionHasErrors('csv');
    $this->post(route('rebalance-tracker.upload'), ['csv' => UploadedFile::fake()->image('portfolio.png')])
        ->assertSessionHasErrors('csv');
    $this->post(route('rebalance-tracker.upload'), ['csv' => UploadedFile::fake()->create('portfolio.csv', 1025, 'text/csv')])
        ->assertSessionHasErrors('csv');
});

it('reports missing market data during upload', function () {
    $csv = UploadedFile::fake()->createWithContent('portfolio.csv', "symbol\nRELIANCE\n");

    $this->actingAs($this->user)->post(route('rebalance-tracker.upload'), ['csv' => $csv])
        ->assertSessionHasErrors('csv');
});

it('compares holdings against the inclusive rank limit and selects entries in screen order', function () {
    $screen = Screen::factory()->for($this->user)->create();
    foreach (['AAA' => 50, 'BBB' => 40, 'CCC' => 30, 'DDD' => 20] as $symbol => $rankValue) {
        createScreenResultRow($symbol, $symbol, '2026-07-31', ['sharpe_return_one_year' => $rankValue]);
    }
    createScreenResultRow('FILTERED', 'Fails filter', '2026-07-31', ['absolute_return_one_year' => 0]);
    createScreenResultRow('OUTSIDE', 'Other index', '2026-07-31', ['is_nifty_allcap' => false]);

    $this->actingAs($this->user)->post(route('rebalance-tracker.analyze'), [
        'screen_id' => $screen->id,
        'symbols' => ['CCC', 'DDD', 'FILTERED', 'OUTSIDE'],
        'worst_rank_held' => 3,
    ])->assertRedirect(route('rebalance-tracker.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('rebalance.analysis.sells', ['DDD', 'FILTERED', 'OUTSIDE'])
        ->assertSessionHas('rebalance.analysis.holds', ['CCC'])
        ->assertSessionHas('rebalance.analysis.buys', ['AAA', 'BBB'])
        ->assertSessionHas('rebalance.analysis.date', '2026-07-31');

    $this->get(route('rebalance-tracker.index'))->assertInertia(fn (Assert $page) => $page
        ->where('analysis.holds', ['CCC'])
        ->where('analysis.worstRankHeld', 3)
    );
});

it('uses the exit count by default and allows an explicit entry limit', function (array $symbols, ?int $limit, array $expectedBuys) {
    $screen = Screen::factory()->for($this->user)->create();
    foreach (['AAA' => 40, 'BBB' => 30, 'CCC' => 20, 'DDD' => 10] as $symbol => $value) {
        createScreenResultRow($symbol, $symbol, '2026-07-31', ['sharpe_return_one_year' => $value]);
    }

    $this->actingAs($this->user)->post(route('rebalance-tracker.analyze'), [
        'screen_id' => $screen->id,
        'symbols' => $symbols,
        'worst_rank_held' => 3,
        'max_buy_recommendations' => $limit,
    ])->assertSessionHasNoErrors()->assertSessionHas('rebalance.analysis.buys', $expectedBuys);
})->with([
    'one exit' => [['BBB', 'DDD'], null, ['AAA']],
    'no exits' => [['BBB'], null, []],
    'explicit entries without exits' => [['BBB'], 2, ['AAA', 'CCC']],
    'fewer candidates than limit' => [['BBB', 'DDD'], 100, ['AAA', 'CCC']],
    'duplicate and lower-case holdings' => [[' bbb ', 'BBB', 'DDD', 'DDD'], null, ['AAA']],
]);

it('uses the saved combined ranking', function () {
    $screen = Screen::factory()->for($this->user)->create(['apply_factor_two' => true]);
    createScreenResultRow('AAA', 'Alpha', '2026-07-31', ['sharpe_return_one_year' => 30, 'volatility_one_year' => 0.3]);
    createScreenResultRow('BBB', 'Beta', '2026-07-31', ['sharpe_return_one_year' => 20, 'volatility_one_year' => 0.1]);
    createScreenResultRow('CCC', 'Gamma', '2026-07-31', ['sharpe_return_one_year' => 10, 'volatility_one_year' => 0.2]);

    $this->actingAs($this->user)->post(route('rebalance-tracker.analyze'), [
        'screen_id' => $screen->id, 'symbols' => ['AAA'], 'worst_rank_held' => 1,
    ])->assertSessionHasNoErrors()
        ->assertSessionHas('rebalance.analysis.sells', ['AAA'])
        ->assertSessionHas('rebalance.analysis.buys', ['BBB']);
});

it('keeps unique ETF selection and valuation warnings from the screen engine', function () {
    $screen = Screen::factory()->for($this->user)->create([
        'index' => NseIndexEnum::NIFTY_ETF, 'unique_etf_per_index' => true,
    ]);
    foreach (['LOW' => 20000000, 'HIGH' => 50000000] as $symbol => $volume) {
        BacktestNseInstrument::query()->create(['symbol' => $symbol, 'etf_index' => 'nifty-50']);
        createScreenResultRow($symbol, $symbol, '2026-07-31', [
            'is_etf' => true, 'marketcap' => null, 'median_volume_one_year' => $volume,
        ]);
    }

    $this->actingAs($this->user)->post(route('rebalance-tracker.analyze'), [
        'screen_id' => $screen->id, 'symbols' => ['LOW'], 'worst_rank_held' => 10,
    ])->assertSessionHasNoErrors()
        ->assertSessionHas('rebalance.analysis.sells', ['LOW'])
        ->assertSessionHas('rebalance.analysis.buys', ['HIGH'])
        ->assertSessionHas('rebalance.analysis.warnings.0', 'Market cap data is not available for ETFs. The market cap filter was not applied.');
});

it('honors historical screen settings and reports the resolved trading date', function () {
    $screen = Screen::factory()->for($this->user)->create([
        'apply_historical_date' => true, 'historical_date' => '2026-07-26',
    ]);
    createScreenResultRow('AAA', 'Alpha', '2026-07-24', ['sharpe_return_one_year' => 10]);
    createScreenResultRow('BBB', 'Beta', '2026-07-24', ['sharpe_return_one_year' => 20]);
    createScreenResultRow('AAA', 'Alpha', '2026-07-31', ['sharpe_return_one_year' => 20]);
    createScreenResultRow('BBB', 'Beta', '2026-07-31', ['sharpe_return_one_year' => 10]);
    $columns = $screen->columns;

    $this->actingAs($this->user)->post(route('rebalance-tracker.analyze'), [
        'screen_id' => $screen->id, 'symbols' => ['AAA'], 'worst_rank_held' => 1,
    ])->assertSessionHasNoErrors()
        ->assertSessionHas('rebalance.analysis.buys', ['BBB'])
        ->assertSessionHas('rebalance.analysis.historical', true)
        ->assertSessionHas('rebalance.analysis.date', '2026-07-24');

    expect($screen->fresh()->columns)->toBe($columns);
});

it('rejects missing history and empty screen results instead of suggesting exits', function (array $settings) {
    $screen = Screen::factory()->for($this->user)->create($settings);
    createScreenResultRow('AAA', 'Alpha', '2026-07-31');

    $this->actingAs($this->user)->post(route('rebalance-tracker.analyze'), [
        'screen_id' => $screen->id, 'symbols' => ['AAA'], 'worst_rank_held' => 10,
    ])->assertSessionHasErrors('screen_id')->assertSessionMissing('rebalance.analysis');
})->with([
    'missing history' => [['apply_historical_date' => true, 'historical_date' => '2010-01-04']],
    'empty results' => [['minimum_return_one_year' => 100]],
]);

it('does not analyze another user private screen', function () {
    $screen = Screen::factory()->create(['id' => 100]);

    $this->actingAs($this->user)->post(route('rebalance-tracker.analyze'), [
        'screen_id' => $screen->id, 'symbols' => ['AAA'], 'worst_rank_held' => 10,
    ])->assertForbidden();
});

it('rechecks portfolio symbols at analysis time', function () {
    $screen = Screen::factory()->for($this->user)->create();
    createScreenResultRow('OLD', 'Old price', '2026-07-30');
    createScreenResultRow('AAA', 'Alpha', '2026-07-31');

    $this->actingAs($this->user)->post(route('rebalance-tracker.analyze'), [
        'screen_id' => $screen->id, 'symbols' => ['OLD', 'UNKNOWN'], 'worst_rank_held' => 10,
    ])->assertSessionHasErrors('symbols')->assertSessionMissing('rebalance.analysis');
});

it('validates analysis input', function (array $overrides, string $errorKey) {
    $screen = Screen::factory()->for($this->user)->create();

    $this->actingAs($this->user)->post(route('rebalance-tracker.analyze'), array_merge([
        'screen_id' => $screen->id, 'symbols' => ['AAA'], 'worst_rank_held' => 10,
    ], $overrides))->assertSessionHasErrors($errorKey);
})->with([
    'unknown screen' => [['screen_id' => 999999], 'screen_id'],
    'no symbols' => [['symbols' => []], 'symbols'],
    'nested symbol' => [['symbols' => [['AAA']]], 'symbols.0'],
    'non-list symbols' => [['symbols' => ['first' => 'AAA']], 'symbols'],
    'bad symbol' => [['symbols' => ['A/B']], 'symbols.0'],
    'too many symbols' => [['symbols' => array_fill(0, 1001, 'AAA')], 'symbols'],
    'zero rank' => [['worst_rank_held' => 0], 'worst_rank_held'],
    'fractional rank' => [['worst_rank_held' => 1.5], 'worst_rank_held'],
    'excessive rank' => [['worst_rank_held' => 10001], 'worst_rank_held'],
    'zero entry limit' => [['max_buy_recommendations' => 0], 'max_buy_recommendations'],
    'excessive entry limit' => [['max_buy_recommendations' => 101], 'max_buy_recommendations'],
]);
