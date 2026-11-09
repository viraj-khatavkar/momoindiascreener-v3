<?php

namespace App\Http\Controllers\Admin;

use App\Actions\BuildPortfolioValuationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PortfolioDateRequest;
use App\Models\Portfolio;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortfolioCsvController extends Controller
{
    /** @var array<string, string> */
    private const COLUMNS = [
        'symbol' => 'symbol',
        'quantity' => 'quantity',
        'price' => 'price',
        'close' => 'close',
        'open' => 'open',
        'high' => 'high',
        'low' => 'low',
        'marketcap' => 'marketcap',
        'volatility_one_year' => 'volatility_one_year',
        'beta' => 'beta',
        'price_date' => 'priceDate',
        'cost' => 'cost',
        'value' => 'value',
        'profit_loss' => 'profitLoss',
        'percent_change' => 'percentChange',
    ];

    public function __invoke(PortfolioDateRequest $request, Portfolio $portfolio, BuildPortfolioValuationAction $buildValuation): StreamedResponse
    {
        $valuation = $buildValuation->execute($portfolio, $request->validated('date'));
        $filename = (Str::slug($portfolio->name) ?: 'portfolio').'-'.$valuation['date'].'.csv';

        return response()->streamDownload(
            fn () => $this->writeHoldings($valuation['holdings']),
            $filename,
            ['Content-Type' => 'text/csv'],
        );
    }

    /** @param list<array<string, mixed>> $holdings */
    private function writeHoldings(array $holdings): void
    {
        $writer = SimpleExcelWriter::create('php://output', 'csv');

        try {
            $writer->addHeader(array_keys(self::COLUMNS));

            foreach ($holdings as $holding) {
                $writer->addRow(array_map(fn (string $key): mixed => $holding[$key], self::COLUMNS));
            }
        } finally {
            $writer->close();
        }
    }
}
