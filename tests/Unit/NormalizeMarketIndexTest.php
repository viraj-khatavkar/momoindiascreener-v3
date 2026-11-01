<?php

use App\Actions\NormalizeMarketIndexAction;

it('uses the same slug for equivalent index labels', function (string $label, string $slug) {
    $normalizer = new NormalizeMarketIndexAction;

    expect($normalizer->execute($label))->toBe($slug)
        ->and($normalizer->execute($slug))->toBe($slug);
})->with([
    ['NIFTY INDIA MANUFACTURING TOTAL RETURN INDEX', 'nifty-india-manufacturing'],
    ['NIFTY INDIA MANUFACTURING TRI', 'nifty-india-manufacturing'],
    ['NIFTY 50 INDEX – TRI', 'nifty-50'],
    ['NIFTY 500 LOW VOLATILITY 50 INDEX TRI', 'nifty-500-low-volatility-50'],
    ['NIFTY50 VALUE20 TRI', 'nifty-50-value-20'],
    ['HDFC NIFTY100 LOW VOLATILITY 30 ETF', 'nifty-100-low-volatility-30'],
    ['KOTAK NIFTY 100 LOW VOL 30 ETF', 'nifty-100-low-volatility-30'],
    ['MIRAE ASSET MUTUAL FUND - MIRAE ASSET NIFTY MIDCAP 150 ETF', 'nifty-midcap-150'],
    ['UTI NIFTY MIDCAP 150 EXCHANGE TRADED FUND (ETF)', 'nifty-midcap-150'],
    ['NIPPON INDIA ETF NIFTY NEXT 50 JUNIOR BEES', 'nifty-next-50'],
    ['NIFTY SMLCAP250 MOMENTUM QLTY100 TOTAL RETRN INDEX', 'nifty-smallcap-250-momentum-quality-100'],
    ['NIFTY MIDSMALLCAP400 MOMENTUM QUALITY 100 TOTAL RE', 'nifty-midsmallcap-400-momentum-quality-100'],
    ['MOTILAL OSWAL S&P BSE HEALTHCARE ETF', 'bse-healthcare'],
    ['S & P BSE SENSEX TRI', 'sensex'],
    ['NYSE FANG+ TOTAL RETURN INDEX', 'nyse-fang-plus'],
    ['NIFTY 1D RATE INDEX (DEBT INDEX)', 'nifty-1d-rate'],
    ['HDFC NIFTY 1D RATE LIQUID ETF', 'nifty-1d-rate'],
    ['NIFTY BHARAT BOND INDEX - APRIL 2033', 'nifty-bharat-bond-apr-2033'],
    ['NIFTY500 MULTICAP 50:25:25 INDEX', 'nifty-500-multicap-50-25-25'],
    ['TATA GOLD EXCHANGE TRADED FUND', 'gold'],
    ['COMMODITY-GOLD', 'gold'],
    ['DOMESTIC PRICE OF PHYSICAL GOLD', 'gold'],
    ['DOMESTIC PRICES OF SILVER', 'silver'],
    ['DOMESTIC PRICE OF SILVER- BASED ON LBMA SILVER DAILY SPOT FIXING PRICE', 'silver'],
    ['AXIS SILVER ETF', 'silver'],
    ['UTI SILVER EXCHANGE TRADED FUND (UTI SILVER ETF)', 'silver'],
    ['PHYSICAL PRICE OF SILVER', 'silver'],
    ['CPSE ETF', 'nifty-cpse'],
    ['BHARAT BOND ETF-APRIL 2033', 'nifty-bharat-bond-apr-2033'],
]);

it('keeps different index strategies and maturities separate', function (string $first, string $second) {
    $normalizer = new NormalizeMarketIndexAction;

    expect($normalizer->execute($first))->not->toBe($normalizer->execute($second));
})->with([
    ['Nifty Midcap 150', 'Nifty Midcap 150 Momentum 50 TRI'],
    ['Nifty 50', 'Nifty 50 Equal Weight'],
    ['Nifty 200 Quality 30', 'Nifty 100 Quality 30'],
    ['Gold', 'Gold Miners Index'],
    ['Hang Seng', 'Hang Seng Tech'],
    ['Nifty Bharat Bond Index April 2032', 'Nifty Bharat Bond Index April 2033'],
    ['CRISIL 10 Year Gilt Index', 'Nifty 10 Yr Benchmark G-Sec Index'],
]);

it('requires symbol scope for generic labels', function (string $label) {
    expect((new NormalizeMarketIndexAction)->isGeneric($label))->toBeTrue();
})->with(['INDEX', 'EQUITY INDEX', 'COMMODITY', 'TOTAL RETURN INDEX', 'GOVERNMENT SECURITIES', '']);
