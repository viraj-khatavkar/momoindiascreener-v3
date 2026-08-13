<?php

namespace Database\Seeders;

use App\Enums\MarketIndexAliasStatusEnum;
use App\Models\MarketIndex;
use App\Models\MarketIndexAlias;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MarketIndexSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var array<string, string> */
    private const ALIASES = [
        'NIFTY' => 'nifty-50',
        'NIFTY 50' => 'nifty-50',
        'NIFTY50' => 'nifty-50',
        'NIFTY 100' => 'nifty-100',
        'LIC MF EXCHANGE TRADED FUND- NIFTY 100' => 'nifty-100',
        'NIFTY BANK' => 'nifty-bank',
        'NIFTY BANK INDEX' => 'nifty-bank',
        'NIFTY FINANCIAL SERVICES TOTAL RETURN INDEX' => 'nifty-financial-services',
        'NIFTY NEXT 50' => 'nifty-next-50',
        'NIFTY NEXT 50 ETF' => 'nifty-next-50',
        'NIFTY MIDCAP 100' => 'nifty-midcap-100',
        'NIFTY MIDCAP 150' => 'nifty-midcap-150',
        'NIFTY CPSE' => 'nifty-cpse',
        'NIFTY PRIVATE BANK INDEX' => 'nifty-private-bank',
        'NIFTY PSU BANK INDEX' => 'nifty-psu-bank',
        'NIFTY1D RATE INDEX' => 'nifty-1d-rate-index',
        'NIFTY 1D RATE INDEX' => 'nifty-1d-rate-index',
        'NIFTY50 VALUE 20' => 'nifty50-value-20',
        'NIFTY50 EQUAL WEIGHT INDEX' => 'nifty50-equal-weight',
        'NIFTY 100 LOW VOLATILITY 30 INDEX' => 'nifty100-low-volatility-30',
        'NIFTY ALPHA LOW-VOLATILITY 30 INDEX' => 'nifty-alpha-low-volatility-30',
        'NIFTY 200 QUALITY 30 INDEX' => 'nifty200-quality-30',
        'NIFTY100 ESG SECTOR LEADERS TRI INDEX' => 'nifty100-esg-sector-leaders',
        'NIFTY QUALITY 30' => 'nifty100-quality-30',
        'NIFTY CONSUMPTION INDEX' => 'nifty-india-consumption',
        'NIFTY INDIA CONSUMPTION INDEX' => 'nifty-india-consumption',
        'NIFTY FMCG INDEX' => 'nifty-fmcg',
        'NIFTY HEALTHCARE INDEX' => 'nifty-healthcare-index',
        'NIFTY HEALTHCARE TRI' => 'nifty-healthcare-index',
        'NIFTY PHARMA INDEX' => 'nifty-pharma',
        'NIFTY INFRA' => 'nifty-infrastructure',
        'NIFTY IT INDEX' => 'nifty-it',
        'NIFTY IT' => 'nifty-it',
        'NIFTY IT TRI' => 'nifty-it',
        'NIFTYIT' => 'nifty-it',
        'NIFTY DIV OPPS 50' => 'nifty-dividend-opportunities-50',
        'NIFTY 10 YR BENCHMARK G-SEC INDEX' => 'nifty-10-yr-benchmark-g-sec',
        'NIFTY 5 YR BENCHMARK G - SEC INDEX TRI' => 'nifty-5-yr-benchmark-g-sec',
        'NIFTY 5 YR BENCHMARK G-SEC INDEX' => 'nifty-5-yr-benchmark-g-sec',
        'NIFTY GS 8 13YR' => 'nifty-8-13-yr-g-sec',
        'NIFTY BHARAT BOND' => 'nifty-bharat-bond',
        'NIFTY AAA BOND PLUS SDL APR 2026 50:50 INDEX' => 'nifty-aaa-bond-plus-sdl-apr-2026',
        'NIFTY CPSE BOND PLUS SDL SEP 2024 50:50 INDEX' => 'nifty-cpse-bond-plus-sdl-sep-2024',
        'NIFTY SDL APR 2026 TOP 20 EQUAL WEIGHT' => 'nifty-sdl-apr-2026-top-20-equal-weight',
        'GSEC 10 NSE INDEX' => 'gsec-10',
        'SENSEX' => 'sensex',
        'BSE SENSEX INDEX' => 'sensex',
        'BSE SENSEX NEXT 50' => 'bse-sensex-next-50',
        'NASDAQ100' => 'nasdaq-100',
        'S&P 500 TOP 50 TOTAL RETURN INDEX' => 'sp-500-top-50',
        'HANG SENG INDEX' => 'hang-seng',
        'TOTAL RETURN INDEX' => 'nyse-fang-plus',
        'S&P BSE 500 INDEX' => 'bse-500',
        'S&P BSE BHARAT 22 INDEX' => 'bse-bharat-22',
        'S&P BSE LIQUID RATE INDEX' => 'bse-liquid-rate',
        'S&P BSE MIDCAP SELECT INDEX' => 'bse-midcap-select',
        'GOLD' => 'gold',
        'CALL MONEY SHORT TERM G-SECS & MONEY MARKET INSTR' => 'liquid',
        'SHARIAH INDEX' => 'nifty50-shariah',
    ];

    /** @var array<string, string> */
    private const NAMES = [
        'nifty-50' => 'Nifty 50',
        'nifty-100' => 'Nifty 100',
        'nifty-bank' => 'Nifty Bank',
        'nifty-financial-services' => 'Nifty Financial Services',
        'nifty-next-50' => 'Nifty Next 50',
        'nifty-midcap-100' => 'Nifty Midcap 100',
        'nifty-midcap-150' => 'Nifty Midcap 150',
        'nifty-cpse' => 'Nifty CPSE',
        'nifty-private-bank' => 'Nifty Private Bank',
        'nifty-psu-bank' => 'Nifty PSU Bank',
        'nifty-1d-rate-index' => 'Nifty 1D Rate Index',
        'nifty50-value-20' => 'Nifty50 Value 20',
        'nifty50-equal-weight' => 'Nifty50 Equal Weight',
        'nifty100-low-volatility-30' => 'Nifty100 Low Volatility 30',
        'nifty-alpha-low-volatility-30' => 'Nifty Alpha Low-Volatility 30',
        'nifty200-quality-30' => 'Nifty200 Quality 30',
        'nifty100-esg-sector-leaders' => 'Nifty100 ESG Sector Leaders',
        'nifty100-quality-30' => 'Nifty100 Quality 30',
        'nifty-india-consumption' => 'Nifty India Consumption',
        'nifty-fmcg' => 'Nifty FMCG',
        'nifty-healthcare-index' => 'Nifty Healthcare Index',
        'nifty-pharma' => 'Nifty Pharma',
        'nifty-infrastructure' => 'Nifty Infrastructure',
        'nifty-it' => 'Nifty IT',
        'nifty-dividend-opportunities-50' => 'Nifty Dividend Opportunities 50',
        'nifty-10-yr-benchmark-g-sec' => 'Nifty 10 Year Benchmark G-Sec',
        'nifty-5-yr-benchmark-g-sec' => 'Nifty 5 Year Benchmark G-Sec',
        'nifty-8-13-yr-g-sec' => 'Nifty 8-13 Year G-Sec',
        'nifty-bharat-bond' => 'Nifty Bharat Bond',
        'nifty-aaa-bond-plus-sdl-apr-2026' => 'Nifty AAA Bond Plus SDL April 2026',
        'nifty-cpse-bond-plus-sdl-sep-2024' => 'Nifty CPSE Bond Plus SDL September 2024',
        'nifty-sdl-apr-2026-top-20-equal-weight' => 'Nifty SDL April 2026 Top 20 Equal Weight',
        'gsec-10' => 'G-Sec 10',
        'sensex' => 'Sensex',
        'bse-sensex-next-50' => 'BSE Sensex Next 50',
        'nasdaq-100' => 'Nasdaq 100',
        'sp-500-top-50' => 'S&P 500 Top 50',
        'hang-seng' => 'Hang Seng',
        'nyse-fang-plus' => 'NYSE FANG+',
        'bse-500' => 'BSE 500',
        'bse-bharat-22' => 'BSE Bharat 22',
        'bse-liquid-rate' => 'BSE Liquid Rate',
        'bse-midcap-select' => 'BSE Midcap Select',
        'gold' => 'Gold',
        'liquid' => 'Liquid',
        'nifty50-shariah' => 'Nifty50 Shariah',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::ALIASES as $sourceLabel => $slug) {
            $marketIndex = MarketIndex::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => self::NAMES[$slug] ?? Str::headline($slug),
                    'provider' => $this->providerFor($slug),
                    'is_active' => true,
                ],
            );

            $normalizedLabel = Str::of($sourceLabel)->squish()->upper()->toString();
            $marketIndexAlias = MarketIndexAlias::query()->firstOrNew([
                'normalized_label' => $normalizedLabel,
            ]);

            if ($marketIndexAlias->exists && $marketIndexAlias->status !== MarketIndexAliasStatusEnum::Pending) {
                continue;
            }

            $marketIndexAlias->fill([
                'source_label' => $sourceLabel,
                'market_index_id' => $marketIndex->id,
                'suggested_market_index_id' => $marketIndex->id,
                'suggested_slug' => $marketIndex->slug,
                'status' => MarketIndexAliasStatusEnum::Approved,
            ])->save();
        }
    }

    private function providerFor(string $slug): ?string
    {
        return match (true) {
            Str::startsWith($slug, 'nifty'), $slug === 'gsec-10' => 'NSE',
            Str::startsWith($slug, 'bse'), $slug === 'sensex' => 'BSE',
            Str::startsWith($slug, 'sp-') => 'S&P Dow Jones Indices',
            Str::startsWith($slug, 'nasdaq-') => 'Nasdaq',
            Str::startsWith($slug, 'hang-seng') => 'Hang Seng Indexes',
            Str::startsWith($slug, 'nyse-') => 'ICE Data Indices',
            default => null,
        };
    }
}
