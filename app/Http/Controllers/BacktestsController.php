<?php

namespace App\Http\Controllers;

use App\Actions\Backtest\LoadBenchmarkSeriesAction;
use App\Actions\Backtest\StartBacktestRunAction;
use App\Actions\CreateDefaultBacktestAction;
use App\Enums\ApplyFiltersOnOptionEnum;
use App\Enums\BacktestCashCallEnum;
use App\Enums\BacktestRebalanceFrequencyEnum;
use App\Enums\BacktestStatusEnum;
use App\Enums\BacktestWeightageEnum;
use App\Enums\CustomFilterComparatorOptionEnum;
use App\Enums\CustomFilterValueOptionEnum;
use App\Enums\NseIndexEnum;
use App\Enums\ScreenSortByOptionEnum;
use App\Http\Requests\UpdateBacktestRequest;
use App\Models\Backtest;
use Illuminate\Http\Request;
use Inertia\Inertia;

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

    public function show(Request $request, Backtest $backtest, LoadBenchmarkSeriesAction $loadBenchmark)
    {
        if ($request->user()->cannot('view', $backtest)) {
            abort(404);
        }

        $isCompleted = $backtest->status === BacktestStatusEnum::Completed;

        return inertia('Backtests/Show', [
            'backtest' => $backtest,
            'summaryMetrics' => fn () => $backtest->summaryMetrics,
            'dailySnapshots' => $isCompleted
                ? Inertia::defer(fn () => $backtest->dailySnapshots()->orderBy('date')->get(), 'charts')
                : [],
            'defaultBenchmark' => $isCompleted
                ? Inertia::defer(fn () => $loadBenchmark->execute($backtest, 'nifty-50'), 'charts')
                : [],
            'trades' => $isCompleted
                ? Inertia::defer(fn () => $backtest->trades()->orderBy('date')->orderBy('trade_type')->get(), 'trades')
                : [],
            'benchmarkOptions' => self::INDEX_SLUG_OPTIONS,
            'indices' => array_values(NseIndexEnum::getOptionsForFilters()),
            'sortByOptions' => array_values(ScreenSortByOptionEnum::getOptionsForFilters()),
            'applyFiltersOnOptions' => array_values(ApplyFiltersOnOptionEnum::getOptionsForFilters()),
            'customFilterValueOptions' => array_values(CustomFilterValueOptionEnum::getOptionsForFilters()),
            'customFilterComparatorOptions' => array_values(CustomFilterComparatorOptionEnum::resolveDisplayableValueList()),
            'rebalanceFrequencyOptions' => array_values(BacktestRebalanceFrequencyEnum::resolveDisplayableValueList()),
            'weightageOptions' => array_values(BacktestWeightageEnum::resolveDisplayableValueList()),
            'cashCallOptions' => array_values(BacktestCashCallEnum::resolveDisplayableValueList()),
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

        $backtest->update($request->validated());

        if ($request->boolean('run') && $request->user()->can('run', $backtest)) {
            $startRun->execute($backtest);

            return redirect()->to('/backtests/'.$backtest->getKey())
                ->with('success', 'Settings saved. Backtest queued for execution.');
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
