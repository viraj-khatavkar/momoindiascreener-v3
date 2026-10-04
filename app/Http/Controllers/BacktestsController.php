<?php

namespace App\Http\Controllers;

use App\Actions\Backtest\LoadBacktestTradeLogAction;
use App\Actions\Backtest\LoadBenchmarkSeriesAction;
use App\Actions\Backtest\LoadMarketCapAllocationAction;
use App\Actions\Backtest\StartBacktestRunAction;
use App\Actions\CreateDefaultBacktestAction;
use App\Enums\ApplyFiltersOnOptionEnum;
use App\Enums\BacktestCashCallEnum;
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
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BacktestsController extends Controller
{
    private const INDEX_SLUG_OPTIONS = [
        ['id' => 'nifty-50', 'name' => 'Nifty 50'],
        ['id' => 'nifty-100', 'name' => 'Nifty 100'],
        ['id' => 'nifty-500', 'name' => 'Nifty 500'],
        ['id' => 'nifty200-momentum-30', 'name' => 'Nifty 200 Momentum 30'],
        ['id' => 'nifty500-momentum-50', 'name' => 'Nifty 500 Momentum 50'],
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

    public function show(ShowBacktestRequest $request, Backtest $backtest, LoadBenchmarkSeriesAction $loadBenchmark, LoadMarketCapAllocationAction $marketCapAllocation, LoadBacktestTradeLogAction $tradeLog): Response
    {
        if ($request->user()->cannot('view', $backtest)) {
            abort(404);
        }

        $isCompleted = $backtest->status === BacktestStatusEnum::Completed;
        $tradeFilters = $request->filters();

        return inertia('Backtests/Show', [
            'backtest' => $backtest,
            'summaryMetrics' => fn () => $backtest->summaryMetrics,
            'dailySnapshots' => $isCompleted
                ? Inertia::defer(fn () => $backtest->dailySnapshots()->orderBy('date')->get([
                    'id', 'backtest_id', 'date', 'nav', 'portfolio_value', 'cash', 'total_value', 'holdings_count',
                ]), 'charts')
                : [],
            'defaultBenchmark' => $isCompleted
                ? Inertia::defer(fn () => $loadBenchmark->execute($backtest, 'nifty-50'), 'charts')
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
            'benchmarkOptions' => self::INDEX_SLUG_OPTIONS,
            'indices' => array_values(NseIndexEnum::getOptionsForFilters()),
            'sortByOptions' => array_values(ScreenSortByOptionEnum::getOptionsForFilters()),
            'applyFiltersOnOptions' => array_values(ApplyFiltersOnOptionEnum::getOptionsForFilters()),
            'customFilterValueOptions' => array_values(CustomFilterValueOptionEnum::getOptionsForFilters()),
            'customFilterComparatorOptions' => array_values(CustomFilterComparatorOptionEnum::resolveDisplayableValueList()),
            'rebalanceFrequencyOptions' => array_values(BacktestRebalanceFrequencyEnum::resolveDisplayableValueList()),
            'weightageOptions' => array_values(BacktestWeightageEnum::resolveDisplayableValueList()),
            'stopLossProceedsOptions' => BacktestStopLossProceedsEnum::resolveDisplayableValueList(),
            'cashCallOptions' => collect(BacktestCashCallEnum::resolveDisplayableValueList())
                ->reject(fn (array $option): bool => $option['id'] === BacktestCashCallEnum::CashCallIfNotEnoughStocks->value
                    && $backtest->cash_call !== BacktestCashCallEnum::CashCallIfNotEnoughStocks)
                ->values()->all(),
            'cashCallIndexOptions' => self::INDEX_SLUG_OPTIONS,
        ]);
    }

    public function edit(Request $request, Backtest $backtest)
    {
        if ($request->user()->cannot('update', $backtest)) {
            abort(404);
        }

        return redirect()->to('/backtests/'.$backtest->getKey().'?tab=settings');
    }

    public function update(Backtest $backtest, UpdateBacktestRequest $request, StartBacktestRunAction $startRun)
    {
        if ($request->user()->cannot('update', $backtest)) {
            abort(404);
        }

        $backtest->fill($request->validated());

        // Track strategy-affecting changes only — renames must not flag results as stale.
        if (collect($backtest->getDirty())->except(['name'])->isNotEmpty()) {
            $backtest->settings_changed_at = now();
        }

        $backtest->save();

        if ($request->boolean('run')) {
            if ($request->user()->can('run', $backtest)) {
                $startRun->execute($backtest);

                return redirect()->to('/backtests/'.$backtest->getKey())
                    ->with('success', 'Settings saved. Backtest queued for execution.');
            }

            return redirect()->to('/backtests/'.$backtest->getKey().'?tab=settings')
                ->with('success', 'Settings saved — a run is already in progress, so no new run was queued.');
        }

        return redirect()->to('/backtests/'.$backtest->getKey().'?tab=settings')
            ->with('success', 'Settings saved.');
    }

    public function destroy(Backtest $backtest, Request $request)
    {
        if ($request->user()->cannot('delete', $backtest)) {
            abort(404);
        }

        $backtest->delete();

        return redirect()->to('/backtests')->with('success', 'Backtest deleted successfully');
    }
}
