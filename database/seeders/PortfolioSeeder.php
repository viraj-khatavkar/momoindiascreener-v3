<?php

namespace Database\Seeders;

use App\Models\BacktestNseInstrumentPrice;
use App\Models\User;
use Illuminate\Database\Seeder;

class PortfolioSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $admin = User::query()->where('is_admin', true)->first();

        if ($admin === null) {
            return;
        }

        $portfolio = $admin->portfolios()->firstOrCreate(['name' => 'Sample Portfolio']);

        if ($portfolio->holdings()->exists()) {
            return;
        }

        $prices = BacktestNseInstrumentPrice::query()
            ->where('date', BacktestNseInstrumentPrice::query()->max('date'))
            ->where('close_raw', '>', 0)
            ->orderBy('symbol')
            ->limit(3)
            ->get(['symbol', 'close_raw']);

        foreach ($prices as $price) {
            $portfolio->holdings()->create([
                'symbol' => $price->symbol,
                'quantity' => 10,
                'price' => $price->close_raw,
            ]);
        }
    }
}
