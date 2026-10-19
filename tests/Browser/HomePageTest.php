<?php

use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use App\Models\Screen;
use App\Models\User;

beforeEach(function () {
    $publicOwner = User::factory()->create(['is_paid' => true]);
    foreach (Screen::PUBLIC_SCREENS as $screenId) {
        Screen::factory()->create(['id' => $screenId, 'user_id' => $publicOwner->id, 'name' => 'Example Screen '.$screenId]);
    }
    $this->member = User::factory()->create(['is_paid' => true]);
    $this->personalScreen = Screen::factory()->create(['user_id' => $this->member->id, 'name' => 'My momentum screen']);
    Screen::factory()->create(['user_id' => $this->member->id, 'name' => 'My second screen']);
    $this->publicBacktest = Backtest::factory()->create([
        'user_id' => $publicOwner->id, 'name' => 'Example momentum strategy', 'status' => BacktestStatusEnum::Completed,
        'is_public' => true,
    ]);
    $this->personalBacktest = Backtest::factory()->create([
        'user_id' => $this->member->id, 'name' => 'My completed strategy', 'status' => BacktestStatusEnum::Completed,
    ]);
    foreach ([$this->publicBacktest, $this->personalBacktest] as $backtest) {
        foreach (['2024-01-08' => 100, '2024-01-09' => 98, '2024-01-10' => 105] as $date => $nav) {
            $backtest->dailySnapshots()->create([
                'date' => $date, 'nav' => $nav, 'portfolio_value' => 0, 'cash' => $nav * 50000,
                'total_value' => $nav * 50000, 'holdings_count' => 0,
            ]);
        }
        app(CalculateBacktestMetricsAction::class)->execute($backtest);
        $backtest->summaryMetrics()->update(['cagr' => 0.184, 'max_drawdown' => -0.224]);
    }
    Backtest::factory()->create(['user_id' => $this->member->id, 'name' => 'My running strategy', 'status' => BacktestStatusEnum::Running, 'progress' => 42]);
    Backtest::factory()->create(['user_id' => $this->member->id, 'name' => 'My failed strategy', 'status' => BacktestStatusEnum::Failed]);
    createScreenResultRow('ALPHA', 'Alpha Industries', '2026-07-31');
});

it('shows example lists to guests and uses the same screen cards on both pages', function (int $width, int $height) {
    $page = visit('/')->resize($width, $height)
        ->assertSee('Example screens')->assertSee('Example backtests')->assertSee('Example momentum strategy')
        ->assertSee('18.40%')->assertSee('-22.40%')->assertDontSee('My momentum screen')->assertDontSee('My completed strategy')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    $cardClass = $page->script('document.querySelector("a[aria-label=\"Open Example Screen 1\"]").className');
    $page->click('View all screens')->assertPathIs('/screens')->assertSee('Example Screens');
    expect($page->script('document.querySelector("a[aria-label=\"Open Example Screen 1\"]").className'))->toBe($cardClass);
    $page->click('a[aria-label="Open Example Screen 1"]')->assertPathIs('/screens/1')->assertNoJavaScriptErrors();
})->with(['desktop' => [1440, 1000], 'mobile' => [390, 844]]);

it('shows only the signed in users lists with working edit links', function (int $width, int $height) {
    $page = loginAs($this->member->email)->resize($width, $height)
        ->assertSee('Your workspace')->assertSee('Your screens')->assertSee('Your backtests')
        ->assertSee('My completed strategy')->assertSee('My running strategy')->assertSee('My failed strategy')->assertSee('42% complete')
        ->assertDontSee('Example screens')->assertDontSee('Example momentum strategy')->assertNoJavaScriptErrors();

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    $cardClass = $page->script('document.querySelector("a[aria-label=\"Edit My momentum screen\"]").className');
    $page->click('View all screens')->assertPathIs('/screens');
    expect($page->script('document.querySelector("a[aria-label=\"Edit My momentum screen\"]").className'))->toBe($cardClass);
    $page->click('a[aria-label="Edit My momentum screen"]')->assertPathIs('/screens/'.$this->personalScreen->id.'/edit')->assertNoJavaScriptErrors();
})->with(['desktop' => [1440, 1000], 'mobile' => [390, 844]]);

it('opens example results without showing settings or write actions', function () {
    $page = visit('/')->click('a[aria-label="Open Example momentum strategy"]')
        ->assertPathIs('/public-backtests/'.$this->publicBacktest->id)
        ->assertSee('Example backtest')->assertSee('Strategy Rules')->assertSee('CAGR')
        ->assertDontSee('Run Backtest')->assertDontSee('Duplicate')->assertDontSee('Edit settings')->assertDontSee('Export CSV')
        ->assertNoJavaScriptErrors();

    visit('/public-backtests/'.$this->publicBacktest->id.'?tab=settings')
        ->assertSee('Strategy Rules')->assertDontSee('Save Settings')->assertDontSee('Run Backtest')->assertNoJavaScriptErrors();
});

it('shows useful empty states for a new account', function () {
    $user = User::factory()->create(['is_paid' => true]);
    loginAs($user->email)->assertSee('No screens yet')->assertSee('No backtests yet')
        ->assertDontSee('Example momentum strategy')->click('Create screen')->assertPathIs('/screens/create')->assertNoJavaScriptErrors();
});

it('shows an unpaid users saved list and explains the access requirement', function () {
    $this->member->update(['is_paid' => false]);
    $page = loginAs($this->member->email)->assertSee('My completed strategy')->assertSee('Your screens')
        ->assertSee('Paid access is needed to open or run your backtests.')->assertSee('View plans')
        ->assertDontSee('Create backtest')->assertNoJavaScriptErrors();

    expect($page->script('document.querySelector("a[aria-label=\"View plans to open My completed strategy\"]").getAttribute("href")'))->toBe('/pricing');
});
