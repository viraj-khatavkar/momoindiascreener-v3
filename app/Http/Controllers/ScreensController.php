<?php

namespace App\Http\Controllers;

use App\Actions\ApplyScreenFiltersAction;
use App\Actions\CreateDefaultScreenAction;
use App\Enums\ApplyFiltersOnOptionEnum;
use App\Enums\CustomFilterComparatorOptionEnum;
use App\Enums\CustomFilterValueOptionEnum;
use App\Enums\NseIndexEnum;
use App\Enums\ScreenResultColumnEnum;
use App\Enums\ScreenSortByOptionEnum;
use App\Http\Requests\UpdateScreenRequest;
use App\Http\Resources\BacktestNseInstrumentPriceResource;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\Screen;
use Illuminate\Http\Request;

class ScreensController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Screens/Index', [
            'publicScreens' => Screen::whereIn('id', Screen::PUBLIC_SCREENS)->get(),
            'screens' => $request->user() ? $request->user()->screens : [],
        ]);
    }

    public function create()
    {
        return inertia('Screens/Create');
    }

    public function store(Request $request, CreateDefaultScreenAction $createDefaultScreenAction)
    {
        $request->validate([
            'name' => 'required|max:250',
        ]);

        $screen = $createDefaultScreenAction->execute($request->name, $request->user());

        return redirect()->to('/screens/'.$screen->getKey().'/edit');
    }

    public function show(Request $request, Screen $screen, ApplyScreenFiltersAction $applyScreenFiltersAction)
    {
        if (! in_array($screen->getKey(), Screen::PUBLIC_SCREENS)) {
            abort(404);
        }

        $date = $this->getLatestDate();

        $results = BacktestNseInstrumentPriceResource::collection(
            $applyScreenFiltersAction->execute($screen, $date)
        );

        $columns = collect($screen->columns)->map(function ($column) {
            return [
                'name' => $column,
                'display_name' => ScreenResultColumnEnum::from($column)->getDisplayName(),
                'sort_order' => ScreenResultColumnEnum::from($column)->getSortOrder(),
            ];
        })->sortBy('sort_order')->values()->all();

        return inertia('Screens/Show', [
            'screen' => $screen,
            'indices' => array_values(NseIndexEnum::getOptionsForFilters()),
            'sortByOptions' => array_values(ScreenSortByOptionEnum::getOptionsForFilters()),
            'applyFiltersOnOptions' => array_values(ApplyFiltersOnOptionEnum::getOptionsForFilters()),
            'customFilterValueOptions' => array_values(CustomFilterValueOptionEnum::getOptionsForFilters()),
            'customFilterComparatorOptions' => array_values(CustomFilterComparatorOptionEnum::resolveDisplayableValueList()),
            'results' => $results,
            'columns' => $columns,
        ]);
    }

    public function edit(Request $request, Screen $screen, ApplyScreenFiltersAction $applyScreenFiltersAction)
    {
        if ($request->user()->cannot('update', $screen)) {
            abort(404);
        }

        $date = $this->getLatestDate();

        $results = BacktestNseInstrumentPriceResource::collection(
            $applyScreenFiltersAction->execute($screen, $date)
        );

        $columns = collect($screen->columns)->map(function ($column) {
            return [
                'name' => $column,
                'display_name' => ScreenResultColumnEnum::from($column)->getDisplayName(),
                'sort_order' => ScreenResultColumnEnum::from($column)->getSortOrder(),
            ];
        })->sortBy('sort_order')->values()->all();

        return inertia('Screens/Edit', [
            'screen' => $screen,
            'indices' => array_values(NseIndexEnum::getOptionsForFilters()),
            'sortByOptions' => array_values(ScreenSortByOptionEnum::getOptionsForFilters()),
            'applyFiltersOnOptions' => array_values(ApplyFiltersOnOptionEnum::getOptionsForFilters()),
            'customFilterValueOptions' => array_values(CustomFilterValueOptionEnum::getOptionsForFilters()),
            'customFilterComparatorOptions' => array_values(CustomFilterComparatorOptionEnum::resolveDisplayableValueList()),
            'results' => $results,
            'columns' => $columns,
        ]);
    }

    public function update(Screen $screen, UpdateScreenRequest $request)
    {
        if ($request->user()->cannot('update', $screen)) {
            abort(404);
        }

        $screen->update($request->validated());

        return redirect()->to('/screens/'.$screen->getKey().'/edit');
    }

    public function destroy(Screen $screen, Request $request)
    {
        if ($request->user()->cannot('delete', $screen)) {
            abort(404);
        }

        $screen->delete();

        return redirect()->to('/screens')->with('success', 'Screen deleted successfully');
    }

    protected function getLatestDate()
    {
        return BacktestNseInstrumentPrice::query()
            ->where('is_nifty_allcap', true)
            ->orderBy('date', 'desc')
            ->limit(1)
            ->first()
            ->date;
    }
}
