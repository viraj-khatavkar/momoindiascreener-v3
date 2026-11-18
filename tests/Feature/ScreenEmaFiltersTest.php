<?php

use App\Actions\ApplyScreenFiltersAction;
use App\Actions\CreateDefaultScreenAction;
use App\Models\Screen;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('uses adjusted closes and strict comparisons for every screen EMA period', function (string $side, int $period) {
    $screen = Screen::factory()->create(['apply_ema' => true, $side.'_ema_'.$period => true]);
    foreach (['ABOVE' => [100, 50], 'BELOW' => [50, 100], 'EQUAL' => [75, 75], 'MISSING' => [80, 80]] as $symbol => [$adjusted, $raw]) {
        createScreenResultRow($symbol, $symbol, '2026-07-31', [
            'close_adjusted' => $adjusted, 'close_raw' => $raw, 'ema_'.$period => $symbol === 'MISSING' ? null : 75,
        ]);
    }

    expect(app(ApplyScreenFiltersAction::class)->execute($screen, '2026-07-31')->pluck('symbol')->all())
        ->toBe([strtoupper($side)]);
})->with(['above', 'below'])->with([20, 50, 100, 200]);

it('requires all selected EMA rules and ignores them when disabled', function () {
    $screen = Screen::factory()->create(['apply_ema' => true, 'above_ema_20' => true, 'below_ema_200' => true]);
    createScreenResultRow('PASS', 'Pass', '2026-07-31', ['ema_20' => 90, 'ema_200' => 110]);
    createScreenResultRow('FAIL', 'Fail', '2026-07-31', ['ema_20' => 110, 'ema_200' => 110]);
    createScreenResultRow('MISSING', 'Missing', '2026-07-31');
    $action = app(ApplyScreenFiltersAction::class);
    expect($action->execute($screen, '2026-07-31')->pluck('symbol')->all())->toBe(['PASS']);
    $screen->apply_ema = false;
    expect($action->execute($screen, '2026-07-31'))->toHaveCount(3);
});

it('defaults screen EMA rules off including old export snapshots', function () {
    $screen = app(CreateDefaultScreenAction::class)->execute('EMA screen', User::factory()->create());
    expect($screen->apply_ema)->toBeFalse()->and($screen->fresh()->above_ema_20)->toBeFalse()
        ->and((new Screen)->apply_ema)->toBeFalse();
});

it('saves EMA rules and uses them for historical results and CSV downloads', function () {
    $screen = Screen::factory()->create();
    createScreenResultRow('PASS', 'Pass ETF', '2026-07-31', ['ema_50' => 80]);
    createScreenResultRow('FAIL', 'Fail ETF', '2026-07-31', ['ema_50' => 120]);
    $this->actingAs($screen->user)->put('/screens/'.$screen->id, array_replace($screen->toArray(), [
        'apply_ema' => true, 'above_ema_50' => true, 'apply_historical_date' => true, 'historical_date' => '2026-07-31',
    ]))->assertSessionHasNoErrors();
    $this->get('/screens/'.$screen->id.'/edit')->assertInertia(fn (Assert $page) => $page
        ->where('screen.apply_ema', true)->where('screen.above_ema_50', true)->has('results', 1));
    $response = $this->get('/screens/'.$screen->id.'/csv');
    $response->assertSuccessful();
    expect($response->streamedContent())->toContain('PASS')->not->toContain('FAIL');
});

it('validates screen EMA boolean flags', function () {
    $screen = Screen::factory()->create();
    $this->actingAs($screen->user)->put('/screens/'.$screen->id, array_replace($screen->toArray(), [
        'apply_ema' => 'invalid', 'above_ema_20' => 'invalid',
    ]))->assertSessionHasErrors(['apply_ema', 'above_ema_20']);
});
