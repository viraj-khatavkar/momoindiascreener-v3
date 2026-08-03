<?php

use App\Enums\CorporateActionTypeEnum;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the corporate actions page to guests', function () {
    $this->get('/corporate-actions')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('CorporateActions/Index'));
});

it('lists corporate actions ordered by date descending with 50 per page', function () {
    createCorporateAction('OLDEST', '2019-05-01', ['type' => CorporateActionTypeEnum::BONUS]);
    createCorporateAction('NEWEST', '2020-01-27', ['type' => CorporateActionTypeEnum::DIVIDEND]);
    createCorporateAction('MIDDLE', '2019-12-15', ['type' => CorporateActionTypeEnum::SPLIT]);

    $this->get('/corporate-actions')
        ->assertInertia(fn (Assert $page) => $page
            ->component('CorporateActions/Index')
            ->has('corporateActions.data', 3)
            ->where('corporateActions.data.0.symbol', 'NEWEST')
            ->where('corporateActions.data.1.symbol', 'MIDDLE')
            ->where('corporateActions.data.2.symbol', 'OLDEST')
            ->where('corporateActions.per_page', 50)
        );
});

it('searches by exact symbol only', function () {
    createCorporateAction('ITC', '2020-01-27');
    createCorporateAction('MITC', '2020-01-27');

    $this->get('/corporate-actions?search=ITC')
        ->assertInertia(fn (Assert $page) => $page
            ->has('corporateActions.data', 1)
            ->where('corporateActions.data.0.symbol', 'ITC')
            ->where('filters.search', 'ITC')
        );

    $this->get('/corporate-actions?search=IT')
        ->assertInertia(fn (Assert $page) => $page
            ->has('corporateActions.data', 0)
        );
});

it('matches the searched symbol case-insensitively', function () {
    createCorporateAction('ITC', '2020-01-27');

    $this->get('/corporate-actions?search=itc')
        ->assertInertia(fn (Assert $page) => $page
            ->has('corporateActions.data', 1)
            ->where('corporateActions.data.0.symbol', 'ITC')
        );
});

it('filters by corporate action type', function () {
    createCorporateAction('TCS', '2020-01-27', ['type' => CorporateActionTypeEnum::DIVIDEND]);
    createCorporateAction('WIPRO', '2020-01-27', ['type' => CorporateActionTypeEnum::BONUS]);

    $this->get('/corporate-actions?type=dividend')
        ->assertInertia(fn (Assert $page) => $page
            ->has('corporateActions.data', 1)
            ->where('corporateActions.data.0.symbol', 'TCS')
            ->where('filters.type', 'dividend')
        );
});

it('omits internal adjustment fields from the payload', function () {
    createCorporateAction('TCS', '2020-01-27', [
        'type' => CorporateActionTypeEnum::DIVIDEND,
        'description' => 'Corporate Action: EQ TCS DIVIDEND RS 5',
        'dividend' => '5',
        'dividend_adjustment_factor' => '0.97',
        'price_adjustment_factor' => '0.5',
        'dividend_adjustment_applied_at' => now(),
        'price_adjustment_applied_at' => now(),
    ]);

    $this->get('/corporate-actions')
        ->assertInertia(fn (Assert $page) => $page
            ->where('corporateActions.data.0.symbol', 'TCS')
            ->where('corporateActions.data.0.type', 'dividend')
            ->where('corporateActions.data.0.dividend', '5')
            ->missing('corporateActions.data.0.series')
            ->missing('corporateActions.data.0.dividend_adjustment_factor')
            ->missing('corporateActions.data.0.price_adjustment_factor')
            ->missing('corporateActions.data.0.dividend_adjustment_applied_at')
            ->missing('corporateActions.data.0.price_adjustment_applied_at')
        );
});

it('returns displayable type options', function () {
    $this->get('/corporate-actions')
        ->assertInertia(fn (Assert $page) => $page
            ->has('types', 5)
            ->where('types.0.id', 'bonus')
            ->where('types.0.name', 'Bonus')
        );
});

it('suggests distinct symbols matching the typed prefix', function () {
    createCorporateAction('ITC', '2020-01-27');
    createCorporateAction('ITC', '2021-03-15');
    createCorporateAction('ITDC', '2020-01-27');
    createCorporateAction('MITC', '2020-01-27');

    $response = $this->getJson('/corporate-actions/search?q=it')->assertSuccessful();

    expect($response->json())->toBe(['ITC', 'ITDC']);
});

it('limits symbol suggestions to ten', function () {
    foreach (range(1, 11) as $i) {
        createCorporateAction(sprintf('SYM%02d', $i), '2020-01-27');
    }

    $this->getJson('/corporate-actions/search?q=SYM')
        ->assertSuccessful()
        ->assertJsonCount(10);
});

it('validates the symbol suggestion query', function () {
    $this->getJson('/corporate-actions/search')->assertUnprocessable();
});
