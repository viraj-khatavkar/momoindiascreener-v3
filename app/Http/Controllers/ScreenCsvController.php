<?php

namespace App\Http\Controllers;

use App\Actions\ApplyScreenFiltersAction;
use App\Actions\WriteScreenCsvAction;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\Screen;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScreenCsvController extends Controller
{
    public function __invoke(Screen $screen, Request $request, ApplyScreenFiltersAction $applyScreenFiltersAction, WriteScreenCsvAction $writer): StreamedResponse
    {
        if ($request->user()->cannot('update', $screen)) {
            abort(404);
        }

        $results = $applyScreenFiltersAction->execute($screen, $this->getLatestDate(), true);

        return response()->streamDownload(
            fn () => $writer->execute('php://output', $results),
            str($screen->name)->replace(' ', '_').'.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    protected function getLatestDate(): CarbonInterface
    {
        return BacktestNseInstrumentPrice::query()
            ->where('is_nifty_allcap', true)
            ->orderBy('date', 'desc')
            ->limit(1)
            ->first()
            ->date;
    }
}
