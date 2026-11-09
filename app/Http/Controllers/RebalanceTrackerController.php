<?php

namespace App\Http\Controllers;

use App\Actions\AnalyzeRebalancePortfolioAction;
use App\Actions\MatchPortfolioSymbolsAction;
use App\Actions\ReadPortfolioCsvAction;
use App\Http\Requests\AnalyzeRebalancePortfolioRequest;
use App\Http\Requests\UploadRebalancePortfolioRequest;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\Screen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

class RebalanceTrackerController extends Controller
{
    public function index(Request $request): Response
    {
        return inertia('RebalanceTracker/Index', [
            'screens' => $request->user()->screens()->orderBy('name')->orderBy('id')
                ->get(['id', 'name', 'apply_historical_date', 'historical_date']),
            'latestDate' => BacktestNseInstrumentPrice::query()->max('date'),
            'portfolio' => $request->session()->get('rebalance.portfolio'),
            'analysis' => $request->session()->get('rebalance.analysis'),
            'trackerRoutes' => [
                'upload' => route('rebalance-tracker.upload'),
                'analyze' => route('rebalance-tracker.analyze'),
            ],
        ]);
    }

    public function upload(
        UploadRebalancePortfolioRequest $request,
        ReadPortfolioCsvAction $readPortfolioCsv,
        MatchPortfolioSymbolsAction $matchPortfolioSymbols,
    ): RedirectResponse {
        $symbols = $readPortfolioCsv->execute($request->file('csv'));

        return to_route('rebalance-tracker.index')->with('rebalance.portfolio', $matchPortfolioSymbols->execute($symbols, 'csv'));
    }

    public function analyze(AnalyzeRebalancePortfolioRequest $request, AnalyzeRebalancePortfolioAction $analyzePortfolio): RedirectResponse
    {
        $validated = $request->validated();
        $screen = Screen::query()->findOrFail($validated['screen_id']);
        Gate::authorize('view', $screen);

        $analysis = $analyzePortfolio->execute(
            $screen,
            $validated['symbols'],
            (int) $validated['worst_rank_held'],
            isset($validated['max_buy_recommendations']) ? (int) $validated['max_buy_recommendations'] : null,
        );

        return to_route('rebalance-tracker.index')->with('rebalance.analysis', $analysis);
    }
}
