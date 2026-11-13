<?php

use App\Enums\BacktestCashCallEnum;
use App\Jobs\RunBacktestJob;
use App\Models\Backtest;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('offers and saves cash for unfilled slots with help for the selected weighting method', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);
    $mode = BacktestCashCallEnum::CashCallIfNotEnoughStocks->value;
    loginAs($user->email);

    $page = visit('/backtests/'.$backtest->id.'?tab=settings')
        ->select('cash_call', $mode)
        ->select('weightage', 'equal_weight')
        ->assertValue('cash_call', $mode)
        ->assertSee('4 empty slots and 2 entries use half the available cash.')
        ->assertMissing('[name="cash_call_index"]')
        ->assertMissing('[name="cash_call_dma_period"]')
        ->press('Save')
        ->assertSee('Settings saved.')
        ->refresh()
        ->assertValue('cash_call', $mode)
        ->assertNoJavaScriptErrors();

    expect($backtest->refresh()->cash_call)->toBe(BacktestCashCallEnum::CashCallIfNotEnoughStocks);

    $page->select('weightage', 'rank_weighted')
        ->assertSee('80% in stocks and 20% in cash.')
        ->assertNoJavaScriptErrors();
});

it('shows conditional gold DMA controls and saves their values', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);
    $mode = BacktestCashCallEnum::OnlyExitsAllocateToGoldAboveDmaBelowIndexDma->value;

    loginAs($user->email);

    $page = visit('/backtests/'.$backtest->id.'?tab=settings')
        ->assertMissing('[name="cash_call_gold_dma_period"]')
        ->select('cash_call', $mode)
        ->assertVisible('[name="cash_call_gold_dma_period"]')
        ->select('cash_call_index', 'nifty-500')
        ->select('cash_call_dma_period', '200')
        ->select('cash_call_gold_dma_period', '100')
        ->press('Save')
        ->assertSee('Settings saved.')
        ->refresh()
        ->assertValue('cash_call', $mode)
        ->assertValue('cash_call_index', 'nifty-500')
        ->assertValue('cash_call_dma_period', '200')
        ->assertValue('cash_call_gold_dma_period', '100')
        ->assertNoJavaScriptErrors();

    expect($backtest->refresh()->cash_call_gold_dma_period)->toBe(100)
        ->and($backtest->cash_call)->toBe(BacktestCashCallEnum::OnlyExitsAllocateToGoldAboveDmaBelowIndexDma);

    $page->select('cash_call', 'only_exits_allocate_to_gold_below_index_dma')
        ->assertMissing('[name="cash_call_gold_dma_period"]')
        ->select('cash_call', 'no_cash_call')
        ->assertMissing('[name="cash_call_index"]')
        ->assertMissing('[name="cash_call_dma_period"]')
        ->assertNoJavaScriptErrors();
});

it('selects an owned backtest NAV source and explains source reruns', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $source = Backtest::factory()->create(['user_id' => $user->id, 'name' => 'My Nifty 200 source']);
    $dependent = Backtest::factory()->create(['user_id' => $user->id]);
    Backtest::factory()->create(['name' => 'Another users private source']);
    loginAs($user->email);

    $page = visit('/backtests/'.$dependent->id.'?tab=settings')
        ->select('cash_call', 'full_cash_below_index_dma')
        ->select('cash_call_source', 'backtest')
        ->assertMissing('[name="cash_call_index"]')
        ->assertSee('Source Backtest')
        ->assertDontSee('Another users private source')
        ->select('cash_call_backtest_id', (string) $source->id)
        ->select('cash_call_dma_period', '100')
        ->assertSee('Each run first reruns the source backtest.')
        ->assertSee('Saved results remain unchanged until you rerun this backtest.')
        ->press('Save')->assertSee('Settings saved.')
        ->refresh()
        ->assertValue('cash_call_source', 'backtest')
        ->assertValue('cash_call_backtest_id', (string) $source->id)
        ->assertValue('cash_call_dma_period', '100')
        ->assertSelected('cash_call', 'full_cash_below_index_dma')
        ->assertSee('Sell everything and hold cash while the source NAV trades below its DMA')
        ->assertNoJavaScriptErrors();

    $page->select('cash_call_source', 'index')
        ->assertVisible('[name="cash_call_index"]')
        ->assertMissing('[name="cash_call_backtest_id"]')
        ->press('Save')->assertSee('Settings saved.')->assertNoJavaScriptErrors();
    expect($dependent->refresh()->cash_call_backtest_id)->toBeNull();
});

it('shows that the dependent waits for its source to complete', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $source = Backtest::factory()->create([
        'user_id' => $user->id, 'name' => 'Source before dependent', 'status' => 'running', 'progress' => 45,
    ]);
    $dependent = Backtest::factory()->create([
        'user_id' => $user->id, 'status' => 'running', 'progress' => 0,
        'cash_call' => 'full_cash_below_index_dma', 'cash_call_source' => 'backtest', 'cash_call_backtest_id' => $source->id,
    ]);
    loginAs($user->email);

    visit('/backtests/'.$dependent->id)
        ->assertSee('Running source backtest: Source before dependent (45%)')
        ->assertSee('This backtest will start only after the source completes successfully.')
        ->assertNoJavaScriptErrors();
});

it('locks settings while the backtest is queued or running', function (int $progress) {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create([
        'user_id' => $user->id, 'status' => 'running', 'progress' => $progress,
    ]);
    loginAs($user->email);

    visit('/backtests/'.$backtest->id.'?tab=settings')
        ->assertSee('Settings are locked')
        ->assertDisabled('[name="name"]')
        ->assertDisabled('[name="max_stocks_to_hold"]')
        ->assertDisabled('[data-test="save-and-run"]')
        ->assertDisabled('Save')
        ->assertNoJavaScriptErrors();
})->with([0, 50]);

it('blocks another run but allows saving idle settings and unlocks after completion', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $active = Backtest::factory()->create([
        'user_id' => $user->id, 'name' => 'Current strategy', 'status' => 'running',
    ]);
    $idle = Backtest::factory()->create(['user_id' => $user->id]);
    loginAs($user->email);

    $page = visit('/backtests/'.$idle->id.'?tab=settings')
        ->assertSee('Current strategy')
        ->assertSee('You can start another backtest after this run sequence finishes.')
        ->assertDisabled('[data-test="save-and-run"]')
        ->assertEnabled('[name="name"]')
        ->fill('name', 'Saved for later')
        ->press('Save')->assertSee('Settings saved.')
        ->assertDisabled('[data-test="save-and-run"]');

    expect($idle->refresh()->name)->toBe('Saved for later');
    Queue::assertNothingPushed();

    $active->update(['status' => 'completed']);
    $page->assertEnabled('[data-test="save-and-run"]')
        ->press('[data-test="save-and-run"]')
        ->assertDisabled('[data-test="save-and-run"]')
        ->assertNoJavaScriptErrors();
    Queue::assertPushed(RunBacktestJob::class, fn (RunBacktestJob $job): bool => $job->backtest->is($idle));
});

it('keeps a completed source locked while its dependent runs', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $source = Backtest::factory()->create(['user_id' => $user->id, 'status' => 'completed']);
    Backtest::factory()->create([
        'user_id' => $user->id, 'status' => 'running', 'cash_call' => 'full_cash_below_index_dma',
        'cash_call_source' => 'backtest', 'cash_call_backtest_id' => $source->id,
    ]);
    loginAs($user->email);

    visit('/backtests/'.$source->id.'?tab=settings')
        ->assertSee('Settings are locked')
        ->assertDisabled('[name="name"]')
        ->assertDisabled('[data-test="save-and-run"]')
        ->assertDisabled('Save')
        ->assertNoJavaScriptErrors();
});
