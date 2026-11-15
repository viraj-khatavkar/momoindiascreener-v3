<?php

namespace App\Http\Controllers;

use App\Actions\QueryNseIndexConstituentsAction;
use App\Http\Requests\NseIndexConstituentsRequest;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NseIndexConstituentsCsvController extends Controller
{
    public function __invoke(NseIndexConstituentsRequest $request, QueryNseIndexConstituentsAction $queryConstituents): StreamedResponse
    {
        $index = $request->index();
        ['date' => $date, 'query' => $query] = $queryConstituents->execute($index, $request->filters());

        return response()->streamDownload(function () use ($query): void {
            $writer = SimpleExcelWriter::create('php://output', 'csv');
            try {
                $writer->addHeader(['date', 'symbol', 'name', 'close_adjusted', 'marketcap_crore', 'price_to_earnings', 'absolute_return_one_year', 'beta']);
                foreach ($query->cursor() as $price) {
                    $writer->addRow([
                        $price->date->toDateString(), $this->csvText($price->symbol), $this->csvText($price->name),
                        $price->close_adjusted, $price->marketcap, $price->price_to_earnings, $price->absolute_return_one_year, $price->beta,
                    ]);
                }
            } finally {
                $writer->close();
            }
        }, $index->slug().'-constituents'.($date !== null ? '-'.$date : '').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csvText(?string $value): ?string
    {
        return $value !== null && preg_match('/^[=+\-@\t\r\n]/', $value) === 1 ? "'".$value : $value;
    }
}
