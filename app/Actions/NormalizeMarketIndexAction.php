<?php

namespace App\Actions;

use Illuminate\Support\Str;

class NormalizeMarketIndexAction
{
    public function execute(string $label): string
    {
        $slug = Str::of($label)->upper()
            ->replaceMatches('/\s*\(UTI SILVER ETF\)$/', '')
            ->replaceMatches('/S\s*&\s*P/', 'SP')
            ->replace('FANG+', 'FANG PLUS')
            ->replace('&', ' AND ')
            ->slug()->toString();

        $issuers = 'aditya-birla-sun-life|baroda-bnp-paribas|bajaj-finserv|icici-prudential|motilal-oswal|nippon-india|mirae-asset|lic-mf|axis|dsp|edelweiss|groww|hdfc|kotak|sbi|shriram|tata|union|uti|zerodha';

        do {
            $previous = $slug;
            $slug = preg_replace('/^(?:'.$issuers.')-(?:mutual-fund-)?/', '', $slug);
            $slug = preg_replace('/^(?:etf|exchange-traded-fund)-/', '', $slug);
        } while ($slug !== $previous);

        $slug = str($slug)
            ->replaceMatches('/\b(nifty|nasdaq|midcap|smallcap|midsmallcap|largemidcap|value|quality|qlty|smlcap|gsec)(?=\d)/', '$1-')
            ->replace(['smlcap-', 'qlty-', 'ageconsumption', '-retrn-', '-rtn-', '-indx'], ['smallcap-', 'quality-', 'age-consumption', '-return-', '-return-', '-index'])
            ->replaceMatches('/\b(?:years?|yrs)\b/', 'yr')
            ->replaceMatches('/\bapril\b/', 'apr')
            ->replaceMatches('/\bseptember\b/', 'sep')
            ->replaceMatches('/\blow-vol\b/', 'low-volatility')
            ->replaceMatches('/^sp-bse-/', 'bse-')
            ->replaceMatches('/-index-(?=apr-\d{4})/', '-')
            ->replaceMatches('/^bharat-bond-(?:etf-)?(apr-\d{4})$/', 'nifty-bharat-bond-$1')
            ->replace('-502525', '-50-25-25')
            ->toString();

        do {
            $previous = $slug;
            $slug = preg_replace('/(?:^|-)(?:total-returns?(?:-index)?|total-re|ttl-return-index|tri|tr|index|etf|exchange-traded-fund|growth-option|debt-index|junior-bees)$/', '', $slug);
        } while ($slug !== $previous);

        if (preg_match('/^(?:(?:(?:domestic|physical)-)?prices?-of-(?:physical-)?|commodity-)?(gold|silver)(?:-based-on-lbma-silver-daily-spot-fixing-price)?$/', $slug, $matches)) {
            return $matches[1];
        }

        return match ($slug) {
            'cpse' => 'nifty-cpse',
            'bse-sensex' => 'sensex',
            'g-sec-10', 'gsec-10-nse' => 'gsec-10',
            'nifty-1d-rate-liquid' => 'nifty-1d-rate',
            default => $slug,
        };
    }

    public function isGeneric(string $label): bool
    {
        return in_array($this->execute($label), ['', 'equity', 'commodity', 'debt', 'government-securities'], true);
    }

    public function name(string $slug): string
    {
        return str($slug)->headline()
            ->replaceMatches('/\b(Bse|Nse|Sp|Psu|Cpse|Sdl|Aaa|It|Fmcg|Esg|Ev|Mnc|Ipo|Msci|Ai|Crisil)\b/', fn (array $matches): string => Str::upper($matches[0]))
            ->replace(['SP ', 'Nyse', 'Fang Plus', '1d', 'G Sec'], ['S&P ', 'NYSE', 'FANG+', '1D', 'G-Sec'])
            ->toString();
    }
}
