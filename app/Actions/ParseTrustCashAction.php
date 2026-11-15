<?php

namespace App\Actions;

use App\Enums\CorporateActionTypeEnum;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ParseTrustCashAction
{
    /**
     * A declared distribution includes its components exactly once. Without a
     * total, only explicit dividend and interest amounts qualify.
     *
     * @return array{type: CorporateActionTypeEnum, amount: string}|null
     */
    public function execute(string $purpose): ?array
    {
        $text = Str::upper(Str::squish(str_replace(['–', '—', '₹'], ['-', '-', 'RS '], $purpose)));
        $text = strtr($text, [
            'DISTRITBUTION' => 'DISTRIBUTION',
            'DISTRIBUTN' => 'DISTRIBUTION',
            'DISTRIBTN' => 'DISTRIBUTION',
            'DISTRIBTIN' => 'DISTRIBUTION',
            'INTERST' => 'INTEREST',
            'INTERSET' => 'INTEREST',
            'DEPORSITS' => 'DEPOSITS',
        ]);

        if (! preg_match('/\b(DISTRIBUTION|DIVIDEND|DIV|INTDV|SPDV|DV|INTEREST)\b/', $text)) {
            return null;
        }

        if (preg_match('/\bDISTRIBUTION\s*(?:(?:OF|AMOUNT|CASH)\s*)?[-:]?\s*(?:(?:RS\.?|RE\.?|INR)\s*[-:]?\s*)?(\d+(?:\.\d{1,6})?)(?![\d.])(?:\s|\/|-|$)/', $text, $total)) {
            return ['type' => CorporateActionTypeEnum::DISTRIBUTION, 'amount' => $this->sum([$total[1]])];
        }

        preg_match_all('/\b(DIVIDEND|DIV|INTDV|SPDV|DV|INTEREST)(?:\s+(?:PAYMENT|AMOUNT|INCOME|ON\s+CAPITAL|ON\s+FIXED\s+DEP[OA]SITS?))?\s*[-:]?\s*(?:RS\.?|RE\.?|INR)\s*(?:PER\s*)?[-:]?\s*(\d+(?:\.\d{1,6})?)(?![\d.])/', $text, $parts, PREG_SET_ORDER);
        $keywords = preg_match_all('/\b(?:DIVIDEND|DIV|INTDV|SPDV|DV|INTEREST)\b/', $text);

        if ($parts === [] || count($parts) !== $keywords || str_contains($text, '%')) {
            throw new InvalidArgumentException('Unresolved trust cash amount: '.$purpose);
        }

        $hasInterest = collect($parts)->contains(fn (array $part): bool => $part[1] === 'INTEREST');
        $hasDividend = collect($parts)->contains(fn (array $part): bool => $part[1] !== 'INTEREST');
        $type = str_contains($text, 'DISTRIBUTION') || ($hasInterest && $hasDividend)
            ? CorporateActionTypeEnum::DISTRIBUTION
            : ($hasInterest ? CorporateActionTypeEnum::INTEREST : CorporateActionTypeEnum::DIVIDEND);

        return ['type' => $type, 'amount' => $this->sum(array_column($parts, 2))];
    }

    /** @param list<string> $amounts */
    public function sum(array $amounts): string
    {
        $total = 0;

        foreach ($amounts as $amount) {
            if (! preg_match('/^\d{1,9}(?:\.\d{1,6})?$/', $amount)) {
                throw new InvalidArgumentException('Invalid cash amount: '.$amount);
            }

            [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
            $total += (int) $whole * 1000000 + (int) str_pad($fraction, 6, '0');
        }

        if ($total <= 0) {
            throw new InvalidArgumentException('The cash amount must be positive.');
        }

        return rtrim(rtrim(sprintf('%d.%06d', intdiv($total, 1000000), $total % 1000000), '0'), '.');
    }
}
