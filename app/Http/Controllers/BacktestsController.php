<?php

namespace App\Http\Controllers;

use App\Actions\CreateDefaultBacktestAction;
use App\Enums\ApplyFiltersOnOptionEnum;
use App\Enums\BacktestCashCallEnum;
use App\Enums\BacktestRebalanceFrequencyEnum;
use App\Enums\BacktestWeightageEnum;
use App\Enums\CustomFilterComparatorOptionEnum;
use App\Enums\CustomFilterValueOptionEnum;
use App\Enums\NseIndexEnum;
use App\Enums\ScreenSortByOptionEnum;
use App\Http\Requests\UpdateBacktestRequest;
use App\Models\Backtest;
use Illuminate\Http\Request;

class BacktestsController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Backtests/Index', [
            'backtests' => $request->user()->backtests()->latest()->get(),
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

        return redirect()->to('/backtests/'.$backtest->getKey().'/edit');
    }

    public function show(Request $request, Backtest $backtest)
    {
        if ($request->user()->cannot('view', $backtest)) {
            abort(404);
        }

        return inertia('Backtests/Show', [
            'backtest' => $backtest,
            'summaryMetrics' => $backtest->summaryMetrics,
            'dailySnapshots' => $backtest->dailySnapshots()->orderBy('date')->get(),
            'trades' => $backtest->trades()->orderBy('date')->orderBy('trade_type')->get(),
        ]);
    }

    public function edit(Request $request, Backtest $backtest)
    {
        if ($request->user()->cannot('update', $backtest)) {
            abort(404);
        }

        return inertia('Backtests/Edit', [
            'backtest' => $backtest,
            'indices' => array_values(NseIndexEnum::getOptionsForFilters()),
            'sortByOptions' => array_values(ScreenSortByOptionEnum::getOptionsForFilters()),
            'applyFiltersOnOptions' => array_values(ApplyFiltersOnOptionEnum::getOptionsForFilters()),
            'customFilterValueOptions' => array_values(CustomFilterValueOptionEnum::getOptionsForFilters()),
            'customFilterComparatorOptions' => array_values(CustomFilterComparatorOptionEnum::resolveDisplayableValueList()),
            'rebalanceFrequencyOptions' => array_values(BacktestRebalanceFrequencyEnum::resolveDisplayableValueList()),
            'weightageOptions' => array_values(BacktestWeightageEnum::resolveDisplayableValueList()),
            'cashCallOptions' => array_values(BacktestCashCallEnum::resolveDisplayableValueList()),
            'cashCallIndexOptions' => [
                ['id' => 'nifty-50', 'name' => 'Nifty 50'],
                ['id' => 'nifty200-momentum-30', 'name' => 'Nifty 200 Momentum 30'],
                ['id' => 'nifty500-momentum-50', 'name' => 'Nifty 500 Momentum 50'],
            ],
        ]);
    }

    public function update(Backtest $backtest, UpdateBacktestRequest $request)
    {
        if ($request->user()->cannot('update', $backtest)) {
            abort(404);
        }

        $backtest->update($request->validated());

        return redirect()->to('/backtests/'.$backtest->getKey().'/edit');
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
