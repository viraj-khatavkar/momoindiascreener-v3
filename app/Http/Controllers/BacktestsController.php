<?php

namespace App\Http\Controllers;

use App\Actions\Backtest\BacktestNavSourceAction;
use App\Actions\Backtest\BacktestStartDateAction;
use App\Actions\Backtest\LoadBacktestTradeLogAction;
use App\Actions\Backtest\LoadBenchmarkSeriesAction;
use App\Actions\Backtest\LoadMarketCapAllocationAction;
use App\Actions\Backtest\StartBacktestRunAction;
use App\Actions\CreateDefaultBacktestAction;
use App\Enums\ApplyFiltersOnOptionEnum;
use App\Enums\BacktestCashCallEnum;
use App\Enums\BacktestCashCallSourceEnum;
use App\Enums\BacktestRebalanceFrequencyEnum;
use App\Enums\BacktestStatusEnum;
use App\Enums\BacktestStopLossProceedsEnum;
use App\Enums\BacktestWeightageEnum;
use App\Enums\CustomFilterComparatorOptionEnum;
use App\Enums\CustomFilterValueOptionEnum;
use App\Enums\NseIndexEnum;
use App\Enums\ScreenSortByOptionEnum;
use App\Http\Requests\ShowBacktestRequest;
use App\Http\Requests\UpdateBacktestRequest;
use App\Models\Backtest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BacktestsController extends Controller
{
    private const CASH_CALL_INDEX_OPTIONS = [
        ['id' => 'nifty-50', 'name' => 'Nifty 50'],
        ['id' => 'nifty-100', 'name' => 'Nifty 100'],
        ['id' => 'nifty-500', 'name' => 'Nifty 500'],
        ['id' => 'nifty200-momentum-30', 'name' => 'Nifty 200 Momentum 30'],
        ['id' => 'nifty500-momentum-50', 'name' => 'Nifty 500 Momentum 50'],
    ];

    private const BENCHMARK_OPTIONS = [
        ...self::CASH_CALL_INDEX_OPTIONS,
        ['id' => 'nifty-total-market', 'name' => 'Nifty Total Market (750)'],
        ['id' => 'nifty-1000', 'name' => 'Nifty 1000'],
        ['id' => 'nifty-largemidcap-250', 'name' => 'Nifty LargeMidcap 250'],
        ['id' => 'nifty-smallcap-250', 'name' => 'Nifty Smallcap 250'],
        ['id' => 'nifty-midcap150-momentum-50', 'name' => 'Nifty Midcap150 Momentum 50'],
    ];

    public function index(Request $request)
    {
        return inertia('Backtests/Index', [
            'backtests' => $request->user()->backtests()
                ->with(['summaryMetrics:id,backtest_id,cagr,max_drawdown,total_trades,total_charges_paid,final_value'])
                ->latest()
                ->get(),
        ]);
    }

    public function create()
    {
        return inertia('Backtests/Create');
    }

    public function store(Request $request, CreateDefaultBacktestAction $action)
    {
        $request->validate([
            'name' => 'required|max:250',
        ]);

        $backtest = $action->execute($request->name, $request->user());

        return redirect()->to('/backtests/'.$backtest->getKey().'?tab=settings');
    }

    public function show(ShowBacktestRequest $request, Backtest $backtest, LoadBenchmarkSeriesAction $loadBenchmark, LoadMarketCapAllocationAction $marketCapAllocation, LoadBacktestTradeLogAction $tradeLog, BacktestNavSourceAction $navSource, BacktestStartDateAction $startDates): Response
    {
        $readOnly = $request->routeIs('public-backtests.show');
        $isCompleted = $backtest->status === BacktestStatusEnum::Completed;
        $tradeFilters = $request->filters();

        return inertia('Backtests/Show', [
            'backtest' => $readOnly ? $backtest->makeHidden(['user_id', 'error_message', 'cash_call_backtest_id']) : $backtest,
            'readOnly' => $readOnly,
            'settingsLocked' => fn () => $readOnly || $navSource->isInUse($backtest),
            'activeBacktest' => fn () => $readOnly ? null : $request->user()->backtests()
                ->where('status', BacktestStatusEnum::Running)->orderBy('id')->first(['id', 'name']),
            'summaryMetrics' => fn () => $backtest->summaryMetrics,
            'dailySnapshots' => $isCompleted
                ? Inertia::defer(fn () => $backtest->dailySnapshots()->orderBy('date')->get([
                    'id', 'backtest_id', 'date', 'nav', 'portfolio_value', 'cash', 'total_value', 'holdings_count',
                ]), 'charts')
                : [],
            'defaultBenchmark' => $isCompleted
                ? Inertia::defer(fn () => $request->query('benchmark', 'nifty-50') === 'nifty-50'
                    ? $loadBenchmark->execute($backtest, 'nifty-50') : null, 'charts')
                : [],
            'marketCapAllocation' => $isCompleted
                ? Inertia::defer(fn () => $marketCapAllocation->execute($backtest), 'allocation')
                : null,
            'trades' => $isCompleted
                ? Inertia::scroll(fn () => $tradeLog->execute($backtest, $tradeFilters))->matchOn('data.id')->defer('trades')
                : null,
            'tradeLogSummary' => $isCompleted
                ? Inertia::defer(fn () => $tradeLog->summary($backtest, $tradeFilters), 'trades')
                : null,
            'tradeFilters' => $tradeFilters,
            'benchmarkOptions' => self::BENCHMARK_OPTIONS,
            'indices' => array_values(NseIndexEnum::getOptionsForFilters()),
            'sortByOptions' => array_values(ScreenSortByOptionEnum::getOptionsForFilters()),
            'startDateLimits' => $startDates->limits(),
            'applyFiltersOnOptions' => array_values(ApplyFiltersOnOptionEnum::getOptionsForFilters()),
            'customFilterValueOptions' => array_values(CustomFilterValueOptionEnum::getOptionsForFilters()),
            'customFilterComparatorOptions' => array_values(CustomFilterComparatorOptionEnum::resolveDisplayableValueList()),
            'rebalanceFrequencyOptions' => array_values(BacktestRebalanceFrequencyEnum::resolveDisplayableValueList()),
            'weightageOptions' => array_values(BacktestWeightageEnum::resolveDisplayableValueList()),
            'stopLossProceedsOptions' => BacktestStopLossProceedsEnum::resolveDisplayableValueList(),
            'cashCallOptions' => array_values(BacktestCashCallEnum::resolveDisplayableValueList()),
            'cashCallIndexOptions' => self::CASH_CALL_INDEX_OPTIONS,
            'cashCallBacktestOptions' => fn () => $readOnly ? [] : $request->user()->backtests()
                ->where('id', '!=', $backtest->id)
                ->where('cash_call', BacktestCashCallEnum::NoCashCall)
                ->where('cash_call_source', BacktestCashCallSourceEnum::Index)
                ->whereNull('cash_call_backtest_id')->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'cashCallBacktest' => fn () => $readOnly || ! $backtest->usesBacktestNav() ? null : $backtest->cashCallBacktest()
                ->where('user_id', $request->user()->id)->first(['id', 'name', 'status', 'progress']),
        ]);
    }

    public function edit(Request $request, Backtest $backtest)
    {
        if ($request->user()->cannot('update', $backtest)) {
            abort(404);
        }

        return redirect()->to('/backtests/'.$backtest->getKey().'?tab=settings');
    }

    public function update(Backtest $backtest, UpdateBacktestRequest $request, StartBacktestRunAction $startRun, BacktestNavSourceAction $navSource)
    {
        if ($request->user()->cannot('update', $backtest)) {
            abort(404);
        }

        if ($request->boolean('run')) {
            $startRun->execute($backtest, $request->validated());

            return redirect()->to('/backtests/'.$backtest->getKey())
                ->with('success', 'Settings saved. Backtest queued for execution.');
        }

        DB::transaction(function () use ($backtest, $request, $navSource): void {
            User::query()->lockForUpdate()->findOrFail($backtest->user_id);
            $current = Backtest::query()->lockForUpdate()->findOrFail($backtest->id);
            $backtest->setRawAttributes($current->getAttributes(), true);
            $navSource->assertNotInUse($backtest);
            $backtest->fill($request->validated());

            // Track strategy-affecting changes only — renames must not flag results as stale.
            if (collect($backtest->getDirty())->except(['name'])->isNotEmpty()) {
                $backtest->settings_changed_at = now();
            }

            $backtest->save();
        });

        return redirect()->to('/backtests/'.$backtest->getKey().'?tab=settings')
            ->with('success', 'Settings saved.');
    }

    public function destroy(Backtest $backtest, Request $request, BacktestNavSourceAction $navSource)
    {
        if ($request->user()->cannot('delete', $backtest)) {
            abort(404);
        }

        DB::transaction(function () use ($backtest, $navSource): void {
            User::query()->lockForUpdate()->findOrFail($backtest->user_id);
            $current = Backtest::query()->lockForUpdate()->findOrFail($backtest->id);
            $navSource->assertNotInUse($current);
            $current->delete();
        });

        return redirect()->to('/backtests')->with('success', 'Backtest deleted successfully');
    }
}
