<?php

namespace App\Actions;

use App\Enums\AdminProcessRunStatusEnum;
use App\Enums\NseIndexEnum;
use App\Models\AdminProcessRun;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Database\Eloquent\Builder;

class QueryNseIndexConstituentsAction
{
    public const SORT_COLUMNS = ['symbol', 'name', 'close_adjusted', 'marketcap', 'price_to_earnings', 'absolute_return_one_year', 'beta'];

    /**
     * @param  array{date: ?string, search: string, sort: string, direction: string}  $filters
     * @return array{date: ?string, query: Builder<BacktestNseInstrumentPrice>}
     */
    public function execute(NseIndexEnum $index, array $filters): array
    {
        $unfinishedDates = AdminProcessRun::query()->select('process_date')
            ->where('status', '!=', AdminProcessRunStatusEnum::Completed->value);
        $date = BacktestNseInstrumentPrice::query()->forceIndex('bnip_date_symbol_unique')
            ->whereNotIn('date', $unfinishedDates)
            ->when($filters['date'] !== null, fn (Builder $query) => $query->where('date', '<=', $filters['date']))
            ->orderByDesc('date')->toBase()->value('date');

        $sort = in_array($filters['sort'], self::SORT_COLUMNS, true) ? $filters['sort'] : 'marketcap';
        $search = '%'.addcslashes($filters['search'], '%_\\').'%';
        $query = BacktestNseInstrumentPrice::query()
            ->select(['id', 'date', 'symbol', 'name', 'close_adjusted', 'marketcap', 'price_to_earnings', 'absolute_return_one_year', 'beta'])
            ->where('date', $date)
            ->where($index->isIndexFieldName(), true)
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->where('symbol', 'like', $search)->orWhere('name', 'like', $search),
            ))
            ->orderByRaw($sort.' IS NULL')
            ->orderBy($sort, $filters['direction'])
            ->when($sort !== 'symbol', fn (Builder $query) => $query->orderBy('symbol'));

        return ['date' => $date, 'query' => $query];
    }
}
