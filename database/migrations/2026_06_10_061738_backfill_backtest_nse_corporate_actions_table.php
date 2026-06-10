<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Copy corporate-action data stored on backtest_nse_instrument_prices rows into
     * backtest_nse_corporate_actions. Factor-bearing rows are stamped as applied:
     * historical prices were already adjusted with those factors, so the adjust
     * commands must never pick them up again.
     */
    public function up(): void
    {
        DB::table('backtest_nse_instrument_prices')
            ->select(
                'id',
                'date',
                'symbol',
                'series',
                'corporate_actions',
                'dividend',
                'dividend_adjustment_factor',
                'price_adjustment_factor'
            )
            ->where(function ($query) {
                $query->whereNotNull('corporate_actions')
                    ->orWhereNotNull('dividend')
                    ->orWhereNotNull('dividend_adjustment_factor')
                    ->orWhereNotNull('price_adjustment_factor');
            })
            ->chunkById(1000, function (Collection $prices) {
                $now = now();

                $actions = $prices->flatMap(function ($price) use ($now) {
                    $sentences = collect(json_decode($price->corporate_actions ?? '[]') ?: [])
                        ->unique()
                        ->values();

                    if ($sentences->isEmpty()) {
                        $sentences = collect([null]);
                    }

                    return $sentences->map(fn (?string $sentence) => [
                        'date' => $price->date,
                        'symbol' => $price->symbol,
                        'series' => $price->series,
                        'type' => $this->inferType($sentence),
                        'description' => $sentence,
                        'ratio' => null,
                        'dividend' => $price->dividend,
                        'dividend_adjustment_factor' => $price->dividend_adjustment_factor,
                        'price_adjustment_factor' => $price->price_adjustment_factor,
                        'dividend_adjustment_applied_at' => is_null($price->dividend_adjustment_factor) ? null : $now,
                        'price_adjustment_applied_at' => is_null($price->price_adjustment_factor) ? null : $now,
                    ]);
                });

                DB::table('backtest_nse_corporate_actions')->insert($actions->all());
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('backtest_nse_corporate_actions')->delete();
    }

    /**
     * Mirrors the classification order of ImportNseCorporateActionsCommand, writing
     * the CorporateActionTypeEnum backing values. Sentences that match no keyword
     * (e.g. "FV SPLIT ...") stay untyped, exactly as they were never classified before.
     */
    protected function inferType(?string $sentence): ?string
    {
        if (is_null($sentence)) {
            return null;
        }

        if (Str::contains($sentence, 'SPLT')) {
            return 'split';
        }

        if (Str::contains($sentence, 'BONUS')) {
            return 'bonus';
        }

        if (Str::contains($sentence, 'DIV')) {
            return 'dividend';
        }

        if (Str::contains($sentence, ['RIGHTS', 'RGHTS'])) {
            return 'rights';
        }

        if (Str::contains($sentence, 'DEMERGER')) {
            return 'demerger';
        }

        return null;
    }
};
