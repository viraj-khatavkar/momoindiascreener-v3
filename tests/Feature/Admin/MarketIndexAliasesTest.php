<?php

use App\Enums\MarketIndexAliasStatusEnum;
use App\Models\BacktestNseInstrument;
use App\Models\MarketIndex;
use App\Models\MarketIndexAlias;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('redirects guests to login', function () {
    $this->get('/admin/market-index-aliases')->assertRedirect('/login');
});

it('returns 404 for a non-admin user', function () {
    $user = User::factory()->create();
    $marketIndexAlias = MarketIndexAlias::factory()->create();

    $this->actingAs($user)
        ->get('/admin/market-index-aliases')
        ->assertNotFound();

    $this->actingAs($user)
        ->put("/admin/market-index-aliases/{$marketIndexAlias->id}", ['action' => 'ignore'])
        ->assertNotFound();
});

it('lists pending aliases with suggestions and status counts', function () {
    $suggestedIndex = MarketIndex::factory()->create([
        'name' => 'Nifty New Sector',
        'slug' => 'nifty-new-sector',
    ]);
    $pendingAlias = MarketIndexAlias::factory()->create([
        'source_label' => 'NIFTY NEW SECTOR INDEX',
        'normalized_label' => 'NIFTY NEW SECTOR INDEX',
        'suggested_market_index_id' => $suggestedIndex->id,
        'suggested_slug' => $suggestedIndex->slug,
    ]);
    MarketIndexAlias::factory()->create([
        'status' => MarketIndexAliasStatusEnum::Approved,
    ]);
    BacktestNseInstrument::query()->create([
        'symbol' => 'NEWETF',
        'market_index_alias_id' => $pendingAlias->id,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/market-index-aliases')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/MarketIndexAliases/Index')
            ->has('marketIndexAliases.data', 1)
            ->where('marketIndexAliases.data.0.source_label', 'NIFTY NEW SECTOR INDEX')
            ->where('marketIndexAliases.data.0.suggested_market_index.slug', 'nifty-new-sector')
            ->where('marketIndexAliases.data.0.instruments_count', 1)
            ->where('filters.status', 'pending')
            ->where('statusCounts.pending', 1)
            ->where('statusCounts.approved', 1)
            ->has('marketIndices', 1)
        );
});

it('filters aliases by status and search text', function () {
    MarketIndexAlias::factory()->create([
        'source_label' => 'NIFTY HEALTHCARE TRI',
        'normalized_label' => 'NIFTY HEALTHCARE TRI',
        'sample_symbol' => 'HEALTHY',
        'status' => MarketIndexAliasStatusEnum::Approved,
    ]);
    MarketIndexAlias::factory()->create([
        'source_label' => 'NIFTY IT TRI',
        'normalized_label' => 'NIFTY IT TRI',
        'sample_symbol' => 'TECH',
        'status' => MarketIndexAliasStatusEnum::Pending,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/market-index-aliases?status=all&search=HEALTHY')
        ->assertInertia(fn (Assert $page) => $page
            ->has('marketIndexAliases.data', 1)
            ->where('marketIndexAliases.data.0.source_label', 'NIFTY HEALTHCARE TRI')
            ->where('filters.status', 'all')
            ->where('filters.search', 'HEALTHY')
        );
});

it('links an alias to an existing index and repairs its instruments', function () {
    $marketIndex = MarketIndex::factory()->create([
        'name' => 'Nifty IT',
        'slug' => 'nifty-it',
    ]);
    $marketIndexAlias = MarketIndexAlias::factory()->create([
        'source_label' => 'NIFTY IT TRI',
        'normalized_label' => 'NIFTY IT TRI',
    ]);
    BacktestNseInstrument::query()->create([
        'symbol' => 'TECH',
        'market_index_alias_id' => $marketIndexAlias->id,
    ]);

    $this->actingAs($this->admin)
        ->put("/admin/market-index-aliases/{$marketIndexAlias->id}", [
            'action' => 'link',
            'market_index_id' => $marketIndex->id,
        ])
        ->assertSessionHasNoErrors();

    $marketIndexAlias->refresh();

    expect($marketIndexAlias->status)->toBe(MarketIndexAliasStatusEnum::Approved)
        ->and($marketIndexAlias->market_index_id)->toBe($marketIndex->id)
        ->and($marketIndexAlias->reviewed_by_user_id)->toBe($this->admin->id)
        ->and($marketIndexAlias->reviewed_at)->not->toBeNull()
        ->and(BacktestNseInstrument::query()->where('symbol', 'TECH')->value('etf_index'))->toBe('nifty-it');
});

it('creates a new index and links the alias in one review', function () {
    $marketIndexAlias = MarketIndexAlias::factory()->create([
        'source_label' => 'NIFTY FUTURE SECTOR INDEX',
        'normalized_label' => 'NIFTY FUTURE SECTOR INDEX',
        'suggested_slug' => 'nifty-future-sector-index',
    ]);
    BacktestNseInstrument::query()->create([
        'symbol' => 'FUTUREETF',
        'market_index_alias_id' => $marketIndexAlias->id,
    ]);

    $this->actingAs($this->admin)
        ->put("/admin/market-index-aliases/{$marketIndexAlias->id}", [
            'action' => 'create',
            'name' => '  Nifty Future Sector  ',
            'slug' => 'Nifty Future Sector',
            'provider' => ' NSE ',
        ])
        ->assertSessionHasNoErrors();

    $marketIndex = MarketIndex::query()->where('slug', 'nifty-future-sector')->sole();

    expect($marketIndex->name)->toBe('Nifty Future Sector')
        ->and($marketIndex->provider)->toBe('NSE')
        ->and($marketIndexAlias->fresh()->market_index_id)->toBe($marketIndex->id)
        ->and(BacktestNseInstrument::query()->where('symbol', 'FUTUREETF')->value('etf_index'))
        ->toBe('nifty-future-sector');
});

it('ignores a source label and clears its instrument index value', function () {
    $marketIndex = MarketIndex::factory()->create();
    $marketIndexAlias = MarketIndexAlias::factory()->create([
        'market_index_id' => $marketIndex->id,
        'status' => MarketIndexAliasStatusEnum::Approved,
    ]);
    BacktestNseInstrument::query()->create([
        'symbol' => 'BADLABEL',
        'etf_index' => $marketIndex->slug,
        'market_index_alias_id' => $marketIndexAlias->id,
    ]);

    $this->actingAs($this->admin)
        ->put("/admin/market-index-aliases/{$marketIndexAlias->id}", [
            'action' => 'ignore',
        ])
        ->assertSessionHasNoErrors();

    $marketIndexAlias->refresh();

    expect($marketIndexAlias->status)->toBe(MarketIndexAliasStatusEnum::Ignored)
        ->and($marketIndexAlias->market_index_id)->toBeNull()
        ->and($marketIndexAlias->reviewed_by_user_id)->toBe($this->admin->id)
        ->and(BacktestNseInstrument::query()->where('symbol', 'BADLABEL')->value('etf_index'))->toBeNull();
});

it('validates the selected review action', function () {
    $marketIndexAlias = MarketIndexAlias::factory()->create();

    $this->actingAs($this->admin)
        ->put("/admin/market-index-aliases/{$marketIndexAlias->id}", [
            'action' => 'link',
        ])
        ->assertSessionHasErrors('market_index_id');

    $this->actingAs($this->admin)
        ->put("/admin/market-index-aliases/{$marketIndexAlias->id}", [
            'action' => 'create',
        ])
        ->assertSessionHasErrors(['name', 'slug']);

    expect($marketIndexAlias->fresh()->status)->toBe(MarketIndexAliasStatusEnum::Pending);
});
