<?php

use App\Enums\BacktestCashCallEnum;
use App\Models\Backtest;
use App\Models\User;

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
