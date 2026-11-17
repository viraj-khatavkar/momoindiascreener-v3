<?php

namespace App\Actions;

use App\Enums\AdminProcessRunStatusEnum;
use App\Models\AdminProcessRun;
use App\Models\BacktestNseInstrument;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\Industry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

class QueryIndustryStocksAction
{
    public const SORT_COLUMNS = ['symbol', 'name', 'close_adjusted', 'marketcap', 'price_to_earnings', 'absolute_return_one_year', 'beta'];

    /**
     * @param  array{search: string, sort: string, direction: string}  $filters
     * @return array{date: ?string, query: Builder<BacktestNseInstrument>}
     */
    public function execute(Industry $industry, array $filters): array
    {
        $unfinishedDates = AdminProcessRun::query()->select('process_date')
            ->where('status', '!=', AdminProcessRunStatusEnum::Completed->value);
        $date = BacktestNseInstrumentPrice::query()->forceIndex('bnip_date_symbol_unique')
            ->whereNotIn('date', $unfinishedDates)->orderByDesc('date')->toBase()->value('date');

        $instruments = (new BacktestNseInstrument)->getTable();
        $prices = (new BacktestNseInstrumentPrice)->getTable();
        $companyName = "COALESCE(NULLIF(prices.name, ''), NULLIF(".$instruments.".name, ''))";
        $sort = in_array($filters['sort'], self::SORT_COLUMNS, true) ? $filters['sort'] : 'marketcap';
        $sortColumn = $sort === 'name' ? $companyName : ($sort === 'symbol' ? $instruments : 'prices').'.'.$sort;
        $direction = $filters['direction'] === 'asc' ? 'asc' : 'desc';
        $search = '%'.addcslashes($filters['search'], '%_\\').'%';

        $query = BacktestNseInstrument::query()->where('industry_id', $industry->id)
            ->leftJoin($prices.' as prices', fn (JoinClause $join) => $join
                ->on('prices.symbol', '=', $instruments.'.symbol')->where('prices.date', $date))
            ->select([
                $instruments.'.id', $instruments.'.symbol', 'prices.symbol as quoted_symbol',
                'prices.close_adjusted', 'prices.marketcap', 'prices.price_to_earnings', 'prices.absolute_return_one_year', 'prices.beta',
            ])
            ->selectRaw($companyName.' AS name')
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->where($instruments.'.symbol', 'like', $search)->orWhereRaw($companyName.' LIKE ?', [$search]),
            ))
            ->orderByRaw($sortColumn.' IS NULL')->orderByRaw($sortColumn.' '.$direction)
            ->when($sort !== 'symbol', fn (Builder $query) => $query->orderBy($instruments.'.symbol'));

        return ['date' => $date, 'query' => $query];
    }
}
