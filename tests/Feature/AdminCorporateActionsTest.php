<?php

use App\Enums\CorporateActionTypeEnum;
use App\Models\BacktestNseCorporateAction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests to login', function () {
    $this->get('/admin/corporate-actions')->assertRedirect('/login');
});

it('returns 404 for non-admin users', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/corporate-actions')
        ->assertNotFound();
});

it('lists corporate actions ordered by date descending with 100 per page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    createCorporateAction('OLDEST', '2019-05-01', ['type' => CorporateActionTypeEnum::BONUS]);
    createCorporateAction('NEWEST', '2020-01-27', ['type' => CorporateActionTypeEnum::DIVIDEND]);
    createCorporateAction('MIDDLE', '2019-12-15', ['type' => CorporateActionTypeEnum::SPLIT]);

    $this->actingAs($admin)
        ->get('/admin/corporate-actions')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/CorporateActions/Index')
            ->has('corporateActions.data', 3)
            ->where('corporateActions.data.0.symbol', 'NEWEST')
            ->where('corporateActions.data.1.symbol', 'MIDDLE')
            ->where('corporateActions.data.2.symbol', 'OLDEST')
            ->where('corporateActions.per_page', 100)
            ->has('types', 5)
        );
});

it('searches by symbol', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    createCorporateAction('TCS', '2020-01-27');
    createCorporateAction('INFY', '2020-01-27');

    $this->actingAs($admin)
        ->get('/admin/corporate-actions?search=TC')
        ->assertInertia(fn (Assert $page) => $page
            ->has('corporateActions.data', 1)
            ->where('corporateActions.data.0.symbol', 'TCS')
            ->where('filters.search', 'TC')
        );
});

it('filters by corporate action type', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    createCorporateAction('TCS', '2020-01-27', ['type' => CorporateActionTypeEnum::DIVIDEND]);
    createCorporateAction('WIPRO', '2020-01-27', ['type' => CorporateActionTypeEnum::BONUS]);

    $this->actingAs($admin)
        ->get('/admin/corporate-actions?type=dividend')
        ->assertInertia(fn (Assert $page) => $page
            ->has('corporateActions.data', 1)
            ->where('corporateActions.data.0.symbol', 'TCS')
            ->where('filters.type', 'dividend')
        );
});

it('renders the create page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get('/admin/corporate-actions/create')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/CorporateActions/Create')
            ->has('types', 5)
        );
});

it('stores a corporate action and uppercases the symbol', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post('/admin/corporate-actions', [
            'date' => '2020-01-27',
            'symbol' => 'tcs',
            'series' => 'EQ',
            'type' => 'bonus',
            'description' => 'Corporate Action: EQ TCS BONUS 1:1',
            'ratio' => '1:1',
        ])
        ->assertRedirect('/admin/corporate-actions');

    $action = BacktestNseCorporateAction::sole();

    expect($action->symbol)->toBe('TCS')
        ->and($action->date->format('Y-m-d'))->toBe('2020-01-27')
        ->and($action->type)->toBe(CorporateActionTypeEnum::BONUS)
        ->and($action->ratio)->toBe('1:1');
});

it('validates input when storing', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post('/admin/corporate-actions', [
            'type' => 'not-a-type',
            'dividend' => 'not-a-number',
        ])
        ->assertSessionHasErrors(['date', 'symbol', 'type', 'dividend']);

    expect(BacktestNseCorporateAction::count())->toBe(0);
});

it('renders the edit page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $action = createCorporateAction('TCS', '2020-01-27');

    $this->actingAs($admin)
        ->get("/admin/corporate-actions/{$action->id}/edit")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/CorporateActions/Edit')
            ->where('corporateAction.id', $action->id)
            ->where('corporateAction.symbol', 'TCS')
            ->has('types', 5)
        );
});

it('updates a corporate action', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $action = createCorporateAction('TCS', '2020-01-27', ['type' => CorporateActionTypeEnum::DIVIDEND]);

    $this->actingAs($admin)
        ->put("/admin/corporate-actions/{$action->id}", [
            'date' => '2020-01-28',
            'symbol' => 'tcs',
            'series' => 'BE',
            'type' => 'dividend',
            'dividend' => '7.5',
            'dividend_adjustment_factor' => '0.98',
        ])
        ->assertRedirect('/admin/corporate-actions');

    $action = $action->fresh();

    expect($action->date->format('Y-m-d'))->toBe('2020-01-28')
        ->and($action->symbol)->toBe('TCS')
        ->and($action->series)->toBe('BE')
        ->and($action->dividend)->toBe('7.5')
        ->and($action->dividend_adjustment_factor)->toBe('0.98');
});

it('deletes a corporate action', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $action = createCorporateAction('TCS', '2020-01-27');

    $this->actingAs($admin)
        ->delete("/admin/corporate-actions/{$action->id}")
        ->assertRedirect('/admin/corporate-actions');

    expect(BacktestNseCorporateAction::count())->toBe(0);
});
