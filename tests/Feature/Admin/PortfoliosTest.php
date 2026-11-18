<?php

use App\Actions\BuildPortfolioValuationAction;
use App\Models\BacktestNseInstrument;
use App\Models\Portfolio;
use App\Models\PortfolioHolding;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->portfolio = Portfolio::factory()->for($this->admin)->create(['name' => 'Momentum Portfolio']);
});

it('requires admin access for portfolio pages and mutations', function (string $method, string $suffix) {
    $url = '/admin/portfolios'.str_replace('{portfolio}', (string) $this->portfolio->id, $suffix);
    $this->{$method}($url)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->{$method}($url)->assertNotFound();
})->with([
    ['get', ''], ['get', '/create'], ['post', ''],
    ['get', '/{portfolio}'], ['get', '/{portfolio}/edit'], ['put', '/{portfolio}'],
    ['get', '/{portfolio}/download'], ['post', '/{portfolio}/holdings'],
    ['get', '/symbols?q=REL'], ['get', '/quote?symbol=RELIANCE&date=2026-07-31'],
]);

it('requires verified email even for an admin', function () {
    $this->admin->update(['email_verified_at' => null]);
    $this->actingAs($this->admin)->get(route('admin.portfolios.index'))->assertRedirect(route('verification.notice'));
});

it('lists only owner portfolios and supports search and pagination', function () {
    Portfolio::factory()->create(['name' => 'Another account']);
    Portfolio::factory()->for($this->admin)->count(24)->create(['name' => 'Other portfolio']);
    PortfolioHolding::factory()->for($this->portfolio)->count(2)->create();

    $this->actingAs($this->admin)->get(route('admin.portfolios.index'))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Portfolios/Index')
            ->has('portfolios.data', 20)
            ->where('portfolios.total', 25)
        );

    $this->get(route('admin.portfolios.index', ['search' => 'Momentum']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('portfolios.data', 1)
            ->where('portfolios.data.0.id', $this->portfolio->id)
            ->where('portfolios.data.0.holdings_count', 2)
            ->where('filters.search', 'Momentum')
            ->missing('portfolios.data.0.user_id')
        );
});

it('creates portfolios for the signed-in admin and opens the editor', function () {
    $this->actingAs($this->admin)->get(route('admin.portfolios.create'))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Portfolios/Create'));

    $response = $this->post(route('admin.portfolios.store'), [
        'name' => 'New Portfolio', 'user_id' => User::factory()->create()->id,
    ])->assertSessionHasNoErrors();
    $created = Portfolio::query()->where('name', 'New Portfolio')->sole();

    expect($created->user_id)->toBe($this->admin->id);
    $response->assertRedirect(route('admin.portfolios.edit', $created));
});

it('validates portfolio names', function (mixed $name) {
    $this->actingAs($this->admin)->post(route('admin.portfolios.store'), ['name' => $name])
        ->assertSessionHasErrors('name');
    $this->put(route('admin.portfolios.update', $this->portfolio), ['name' => $name])
        ->assertSessionHasErrors('name');
})->with(['empty' => '', 'too long' => str_repeat('A', 256), 'array' => [['invalid']]]);

it('renames a portfolio without changing its owner', function () {
    $this->actingAs($this->admin)->from(route('admin.portfolios.edit', $this->portfolio))
        ->put(route('admin.portfolios.update', $this->portfolio), [
            'name' => 'Renamed Portfolio', 'user_id' => User::factory()->create()->id,
        ])->assertRedirect(route('admin.portfolios.edit', $this->portfolio));

    expect($this->portfolio->fresh()->name)->toBe('Renamed Portfolio')
        ->and($this->portfolio->fresh()->user_id)->toBe($this->admin->id);
});

it('adds normalized symbols and preserves separate purchase entries', function () {
    createScreenResultRow('M&M', 'Mahindra', '2026-07-31');
    $this->actingAs($this->admin);
    foreach ([100.25, 120] as $price) {
        $this->post(route('admin.portfolios.holdings.store', $this->portfolio), [
            'symbol' => ' m&m ', 'quantity' => 10, 'price' => $price,
            'date' => '2026-07-31', 'portfolio_id' => 999999,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.portfolios.edit', [
            'portfolio' => $this->portfolio, 'date' => '2026-07-31',
        ]));
    }

    expect($this->portfolio->holdings()->count())->toBe(2)
        ->and($this->portfolio->holdings()->pluck('symbol')->all())->toBe(['M&M', 'M&M'])
        ->and($this->portfolio->holdings()->oldest('id')->first()->price)->toBe('100.25');
});

it('edits a holding without moving it to another portfolio', function () {
    createScreenResultRow('AAA', 'Alpha', '2026-07-31');
    $holding = PortfolioHolding::factory()->for($this->portfolio)->create(['symbol' => 'AAA', 'quantity' => 10, 'price' => 100]);

    $this->actingAs($this->admin)->put(route('admin.portfolios.holdings.update', [$this->portfolio, $holding]), [
        'symbol' => 'AAA', 'quantity' => 25, 'price' => 120.50, 'portfolio_id' => 999999,
    ])->assertSessionHasNoErrors();

    expect($holding->fresh()->quantity)->toBe(25)
        ->and($holding->fresh()->price)->toBe('120.50')
        ->and($holding->fresh()->portfolio_id)->toBe($this->portfolio->id);
});

it('validates holding input on creation and editing', function (array $overrides, string $error) {
    createScreenResultRow('AAA', 'Alpha', '2026-07-31');
    $holding = PortfolioHolding::factory()->for($this->portfolio)->create(['symbol' => 'AAA']);
    $data = array_merge(['symbol' => 'AAA', 'quantity' => 10, 'price' => 100], $overrides);

    $this->actingAs($this->admin)->post(route('admin.portfolios.holdings.store', $this->portfolio), $data)->assertSessionHasErrors($error);
    $this->put(route('admin.portfolios.holdings.update', [$this->portfolio, $holding]), $data)->assertSessionHasErrors($error);
})->with([
    'unknown symbol' => [['symbol' => 'UNKNOWN'], 'symbol'],
    'empty symbol' => [['symbol' => ''], 'symbol'],
    'fractional quantity' => [['quantity' => 1.5], 'quantity'],
    'zero quantity' => [['quantity' => 0], 'quantity'],
    'negative quantity' => [['quantity' => -10], 'quantity'],
    'zero price' => [['price' => 0], 'price'],
    'negative price' => [['price' => -10], 'price'],
    'too many decimal places' => [['price' => 10.123], 'price'],
    'invalid date' => [['date' => 'invalid'], 'date'],
    'future date' => [['date' => '2099-01-01'], 'date'],
]);

it('removes a holding only with a DELETE request', function () {
    $holding = PortfolioHolding::factory()->for($this->portfolio)->create();
    $url = route('admin.portfolios.holdings.destroy', [$this->portfolio, $holding]);
    $this->actingAs($this->admin)->get($url)->assertStatus(405);
    $this->assertModelExists($holding);
    $this->delete($url)->assertSessionHasNoErrors()->assertRedirect(route('admin.portfolios.edit', $this->portfolio));
    $this->assertModelMissing($holding);
});

it('scopes holding edits and removals to their parent portfolio', function (string $method) {
    $anotherPortfolio = Portfolio::factory()->for($this->admin)->create();
    $holding = PortfolioHolding::factory()->for($anotherPortfolio)->create(['quantity' => 10]);

    $this->actingAs($this->admin)->{$method}(
        route('admin.portfolios.holdings.update', [$this->portfolio, $holding]),
        ['symbol' => 'RELIANCE', 'quantity' => 25, 'price' => 100],
    )->assertNotFound();

    expect($holding->fresh()->quantity)->toBe(10);
})->with(['put', 'delete']);

it('prevents other admins from reading or changing a private portfolio', function () {
    $holding = PortfolioHolding::factory()->for($this->portfolio)->create();
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    foreach (['show', 'edit', 'download'] as $action) {
        $this->get(route('admin.portfolios.'.$action, $this->portfolio))->assertForbidden();
    }
    $this->put(route('admin.portfolios.update', $this->portfolio), ['name' => 'Wrong owner'])->assertForbidden();
    $this->post(route('admin.portfolios.holdings.store', $this->portfolio), [])->assertForbidden();
    $this->put(route('admin.portfolios.holdings.update', [$this->portfolio, $holding]), [])->assertForbidden();
    $this->delete(route('admin.portfolios.holdings.destroy', [$this->portfolio, $holding]))->assertForbidden();
    $this->assertModelExists($holding);
});

it('shows raw-price values and the last available price for a missing trading day', function () {
    PortfolioHolding::factory()->for($this->portfolio)->create(['symbol' => 'AAA', 'quantity' => 10, 'price' => 100]);
    PortfolioHolding::factory()->for($this->portfolio)->create(['symbol' => 'BBB', 'quantity' => 5, 'price' => 200]);
    createScreenResultRow('AAA', 'Alpha', '2026-07-31', ['close_raw' => 120, 'close_adjusted' => 30]);
    createScreenResultRow('BBB', 'Beta', '2026-07-30', ['close_raw' => 150, 'series' => 'BE']);

    $this->actingAs($this->admin)->get(route('admin.portfolios.edit', $this->portfolio))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Portfolios/Edit')
            ->where('valuation.date', '2026-07-31')
            ->where('valuation.holdings.0.value', 1200)
            ->where('valuation.holdings.0.percentChange', 20)
            ->where('valuation.holdings.1.priceDate', '2026-07-30')
            ->where('valuation.holdings.1.earlierPrice', true)
            ->where('valuation.holdings.1.series', 'BE')
            ->where('valuation.summary.cost', 2000)
            ->where('valuation.summary.value', 1950)
            ->where('valuation.summary.profitLoss', -50)
            ->where('valuation.summary.percentChange', -2.5)
            ->where('valuation.summary.stocks', 2)
            ->where('valuation.summary.earlierPrices', 1)
        );
});

it('never uses future prices and does not flag a normal weekend quote as old', function () {
    PortfolioHolding::factory()->for($this->portfolio)->create(['symbol' => 'AAA', 'quantity' => 10, 'price' => 100]);
    createScreenResultRow('AAA', 'Alpha', '2026-07-24', ['close_raw' => 110]);
    createScreenResultRow('AAA', 'Alpha', '2026-07-31', ['close_raw' => 999]);

    $this->actingAs($this->admin)->get(route('admin.portfolios.edit', ['portfolio' => $this->portfolio, 'date' => '2026-07-26']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('valuation.date', '2026-07-26')
            ->where('valuation.marketDate', '2026-07-24')
            ->where('valuation.holdings.0.close', 110)
            ->where('valuation.summary.value', 1100)
            ->where('valuation.summary.earlierPrices', 0)
        );
});

it('marks unpriced holdings and prevents misleading total profit', function () {
    PortfolioHolding::factory()->for($this->portfolio)->create(['symbol' => 'AAA', 'quantity' => 10, 'price' => 100]);
    PortfolioHolding::factory()->for($this->portfolio)->create(['symbol' => 'NEW', 'quantity' => 5, 'price' => 200]);
    createScreenResultRow('AAA', 'Alpha', '2026-07-24', ['close_raw' => 120]);
    createScreenResultRow('NEW', 'New listing', '2026-07-31', ['close_raw' => 250]);

    $this->actingAs($this->admin)->get(route('admin.portfolios.edit', ['portfolio' => $this->portfolio, 'date' => '2026-07-24']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('valuation.holdings.1.close', null)
            ->where('valuation.holdings.1.value', null)
            ->where('valuation.holdings.1.profitLoss', null)
            ->where('valuation.summary.cost', 2000)
            ->where('valuation.summary.value', 1200)
            ->where('valuation.summary.profitLoss', null)
            ->where('valuation.summary.percentChange', null)
            ->where('valuation.summary.missingPrices', 1)
        );
});

it('handles empty portfolios and missing market data', function () {
    $this->actingAs($this->admin)->get(route('admin.portfolios.edit', $this->portfolio))
        ->assertInertia(fn (Assert $page) => $page
            ->has('valuation.holdings', 0)
            ->where('valuation.summary.value', 0)
            ->where('valuation.summary.percentChange', null)
            ->where('valuation.marketDate', null)
        );
    PortfolioHolding::factory()->for($this->portfolio)->create();
    $this->get(route('admin.portfolios.edit', $this->portfolio))
        ->assertInertia(fn (Assert $page) => $page->where('valuation.summary.missingPrices', 1));
});

it('counts stocks separately from multiple purchase entries and skips zero quotes', function () {
    PortfolioHolding::factory()->for($this->portfolio)->create(['symbol' => 'AAA', 'quantity' => 10, 'price' => 100]);
    PortfolioHolding::factory()->for($this->portfolio)->create(['symbol' => 'AAA', 'quantity' => 5, 'price' => 80]);
    createScreenResultRow('AAA', 'Alpha', '2026-07-30', ['close_raw' => 120]);
    createScreenResultRow('AAA', 'Alpha', '2026-07-31', ['close_raw' => 0]);
    $value = app(BuildPortfolioValuationAction::class)->execute($this->portfolio);

    expect($value['summary'])->toMatchArray([
        'stocks' => 1, 'lots' => 2, 'cost' => 1400.0, 'value' => 1800.0,
        'profitLoss' => 400.0, 'earlierPrices' => 2,
    ]);
});

it('loads prices in a constant number of queries', function () {
    foreach (range(1, 30) as $number) {
        $symbol = 'STOCK'.$number;
        PortfolioHolding::factory()->for($this->portfolio)->create(['symbol' => $symbol]);
        createScreenResultRow($symbol, $symbol, '2026-07-30');
    }
    createScreenResultRow('MARKET', 'Market', '2026-07-31');
    DB::enableQueryLog();
    DB::flushQueryLog();

    $valuation = app(BuildPortfolioValuationAction::class)->execute($this->portfolio);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($valuation['holdings'])->toHaveCount(30)
        ->and($valuation['summary']['earlierPrices'])->toBe(30)
        ->and($queries)->toHaveCount(3);
});

it('seeks to historical quotes without reading the full price history for each holding', function () {
    $firstDate = CarbonImmutable::parse('2025-01-01');
    $selectedDay = 60;

    foreach (['AAA' => 100, 'M&M' => 200] as $symbol => $basePrice) {
        PortfolioHolding::factory()->for($this->portfolio)->create(['symbol' => $symbol]);
        foreach (range(0, 120) as $day) {
            createBacktestPriceRow($symbol, $firstDate->addDays($day)->toDateString(), [
                'close_raw' => $day === $selectedDay ? 0 : $basePrice + $day,
            ]);
        }
    }

    $rowReads = static fn (): int => (int) collect(DB::select(
        "SHOW SESSION STATUS WHERE Variable_name IN ('Handler_read_next', 'Handler_read_prev', 'Handler_read_rnd_next')"
    ))->sum('Value');

    $before = $rowReads();
    $valuation = app(BuildPortfolioValuationAction::class)->execute($this->portfolio, $firstDate->addDays($selectedDay)->toDateString());
    $reads = $rowReads() - $before;

    expect(array_column($valuation['holdings'], 'close'))->toBe([159.0, 259.0])
        ->and($reads)->toBeLessThan(30);
});

it('shows corporate actions only for held symbols and the selected date', function () {
    PortfolioHolding::factory()->for($this->portfolio)->create(['symbol' => 'AAA']);
    createScreenResultRow('AAA', 'Alpha', '2026-07-31');
    $action = createCorporateAction('AAA', '2026-07-31', ['type' => 'dividend', 'dividend' => '5']);
    createCorporateAction('OTHER', '2026-07-31', ['type' => 'split']);
    createCorporateAction('AAA', '2026-07-30', ['type' => 'bonus']);

    $this->actingAs($this->admin)->get(route('admin.portfolios.edit', $this->portfolio))
        ->assertInertia(fn (Assert $page) => $page
            ->has('corporateActions', 1)
            ->where('corporateActions.0.id', $action->id)
            ->where('corporateActions.0.type', 'dividend')
            ->where('corporateActions.0.dividend', '5')
        );
});

it('validates valuation dates for pages and CSV exports', function (string $date) {
    $this->actingAs($this->admin);
    foreach (['edit', 'download'] as $action) {
        $this->get(route('admin.portfolios.'.$action, ['portfolio' => $this->portfolio, 'date' => $date]))
            ->assertSessionHasErrors('date');
    }
})->with(['invalid', '2026-02-30', '2099-01-01']);

it('exports the same values as the page and keeps the legacy CSV columns', function () {
    PortfolioHolding::factory()->for($this->portfolio)->create(['symbol' => 'AAA', 'quantity' => 10, 'price' => 100.25]);
    PortfolioHolding::factory()->for($this->portfolio)->create(['symbol' => 'MISSING', 'quantity' => 5, 'price' => 50]);
    createScreenResultRow('AAA', 'Alpha', '2026-07-30', [
        'close_raw' => 120, 'close_adjusted' => 30, 'open_raw' => 115, 'high_raw' => 125, 'low_raw' => 110,
    ]);
    createScreenResultRow('AAA', 'Alpha', '2026-07-31', ['close_raw' => 999]);

    $response = $this->actingAs($this->admin)->get(route('admin.portfolios.download', [
        'portfolio' => $this->portfolio, 'date' => '2026-07-30',
    ]))->assertDownload('momentum-portfolio-2026-07-30.csv');
    $lines = preg_split('/\r?\n/', trim($response->streamedContent()));
    $header = str_getcsv(ltrim($lines[0], "\xEF\xBB\xBF"), ',', '"', '');
    $first = array_combine($header, str_getcsv($lines[1], ',', '"', ''));
    $missing = array_combine($header, str_getcsv($lines[2], ',', '"', ''));

    expect(array_slice($header, 0, 10))->toBe(['symbol', 'quantity', 'price', 'close', 'open', 'high', 'low', 'marketcap', 'volatility_one_year', 'beta'])
        ->and($first)->toMatchArray(['symbol' => 'AAA', 'quantity' => '10', 'price' => '100.25', 'close' => '120', 'price_date' => '2026-07-30', 'value' => '1200', 'profit_loss' => '197.5'])
        ->and($missing['close'])->toBe('')
        ->and($missing['value'])->toBe('');
});

it('exports a valid header for an empty portfolio', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.portfolios.download', $this->portfolio))->assertSuccessful();
    expect(ltrim(trim($response->streamedContent()), "\xEF\xBB\xBF"))->toStartWith('symbol,quantity,price,close');
});

it('searches symbols and company names and previews raw quotes without future data', function () {
    BacktestNseInstrument::query()->create(['symbol' => 'AAA', 'name' => 'Alpha Industries']);
    createScreenResultRow('AAA', 'Alpha', '2026-07-30', ['close_raw' => 120, 'close_adjusted' => 30]);
    createScreenResultRow('AAA', 'Alpha', '2026-07-31', ['close_raw' => 999]);

    $this->actingAs($this->admin)->getJson(route('admin.portfolios.symbols', ['q' => 'Alpha']))->assertExactJson(['AAA']);
    $this->getJson(route('admin.portfolios.quote', ['symbol' => ' aaa ', 'date' => '2026-07-30']))
        ->assertJsonPath('quote.close', 120)->assertJsonPath('quote.date', '2026-07-30');
    $this->getJson(route('admin.portfolios.quote', ['symbol' => 'AAA', 'date' => '2026-07-01']))->assertExactJson(['quote' => null]);
});

it('keeps the old view URL as a redirect to the editor with its date', function () {
    $this->actingAs($this->admin)->get(route('admin.portfolios.show', ['portfolio' => $this->portfolio, 'date' => '2026-07-30']))
        ->assertRedirect(route('admin.portfolios.edit', ['portfolio' => $this->portfolio, 'date' => '2026-07-30']));
});
