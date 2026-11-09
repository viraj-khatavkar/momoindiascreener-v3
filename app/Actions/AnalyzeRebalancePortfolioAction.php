<?php

namespace App\Actions;

use App\Models\BacktestNseInstrumentPrice;
use App\Models\Screen;
use Illuminate\Validation\ValidationException;

class AnalyzeRebalancePortfolioAction
{
    public function __construct(
        private ApplyScreenFiltersAction $applyScreenFilters,
        private MatchPortfolioSymbolsAction $matchPortfolioSymbols,
    ) {}

    /**
     * @param  list<string>  $symbols
     * @return array{screenName: string, date: string, historical: bool, worstRankHeld: int, sells: list<string>, holds: list<string>, buys: list<string>, warnings: list<string>}
     */
    public function execute(Screen $screen, array $symbols, int $worstRankHeld, ?int $maxBuyRecommendations): array
    {
        $portfolio = $this->matchPortfolioSymbols->execute($symbols);

        if ($portfolio['unmatched'] !== []) {
            throw ValidationException::withMessages(['symbols' => 'Some portfolio symbols have no prices on '.$portfolio['date'].'. Check the CSV and upload it again.']);
        }

        if ($screen->apply_historical_date && ! BacktestNseInstrumentPrice::query()
            ->where('is_nifty_allcap', true)
            ->where('date', '<=', $screen->historical_date)
            ->exists()) {
            throw ValidationException::withMessages(['screen_id' => 'No market data is available for this screen’s historical date.']);
        }

        $rankingScreen = clone $screen;
        $rankingScreen->columns = [];
        $results = $this->applyScreenFilters->execute($rankingScreen, $portfolio['date']);

        if ($results->isEmpty()) {
            throw ValidationException::withMessages(['screen_id' => 'No symbols match this screen. Check its filters before you analyze the portfolio.']);
        }

        $topSymbols = $results->take($worstRankHeld)->pluck('symbol')->all();
        $heldSymbols = $portfolio['matched'];
        $sells = array_values(array_diff($heldSymbols, $topSymbols));
        $buyCount = $maxBuyRecommendations ?? count($sells);

        return [
            'screenName' => $screen->name,
            'date' => $results->first()->date->toDateString(),
            'historical' => $screen->apply_historical_date,
            'worstRankHeld' => $worstRankHeld,
            'sells' => $sells,
            'holds' => array_values(array_intersect($heldSymbols, $topSymbols)),
            'buys' => array_slice(array_values(array_diff($topSymbols, $heldSymbols)), 0, $buyCount),
            'warnings' => $this->applyScreenFilters->getValuationFilterWarnings(),
        ];
    }
}
