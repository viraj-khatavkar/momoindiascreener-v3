<?php

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SplFileObject;

class ReadPortfolioCsvAction
{
    public const MAX_SYMBOLS = 1000;

    public const SYMBOL_PATTERN = '/\A[A-Z0-9][A-Z0-9&._-]*\z/';

    /** @return list<string> */
    public function execute(UploadedFile $upload): array
    {
        $file = new SplFileObject($upload->getPathname());
        $file->setCsvControl(',', '"', '');

        if ($file->fread(3) !== "\xEF\xBB\xBF") {
            $file->rewind();
        }

        $header = $file->fgetcsv();
        $headers = array_map(
            fn (?string $value): string => Str::lower(trim($value ?? '')),
            $header ?: [],
        );

        if (count(array_keys($headers, 'symbol', true)) !== 1) {
            throw ValidationException::withMessages(['csv' => 'The CSV must have one column named "symbol" in its header row.']);
        }

        $symbolColumn = array_search('symbol', $headers, true);
        $symbols = [];

        while (! $file->eof()) {
            $row = $file->fgetcsv();

            if ($row === false || $row === [null]) {
                continue;
            }

            if (count($row) !== count($headers)) {
                throw ValidationException::withMessages(['csv' => 'Each CSV row must have the same number of columns as the header.']);
            }

            $symbol = Str::upper(trim($row[$symbolColumn] ?? ''));

            if ($symbol === '') {
                continue;
            }

            if (Str::length($symbol) > 50 || ! preg_match(self::SYMBOL_PATTERN, $symbol)) {
                throw ValidationException::withMessages(['csv' => 'The CSV contains an invalid symbol. Use NSE symbols, such as RELIANCE or M&M.']);
            }

            $symbols[] = $symbol;

            if (count($symbols) > self::MAX_SYMBOLS) {
                throw ValidationException::withMessages(['csv' => 'The CSV must contain no more than '.self::MAX_SYMBOLS.' symbols.']);
            }
        }

        if ($symbols === []) {
            throw ValidationException::withMessages(['csv' => 'No symbols were found in the CSV. Add symbols below the header row.']);
        }

        return $symbols;
    }
}
