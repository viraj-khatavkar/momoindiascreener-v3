<?php

namespace App\Actions;

use Exception;
use Illuminate\Support\Collection;

class ReadCsvAction
{
    protected array $csvArray = [];

    public function execute(string $filePath, bool $skipFirstRow = true): static
    {
        if (file_exists($filePath) === false) {
            throw new Exception('csv file does not exists at given path');
        }

        $file = fopen($filePath, 'r');

        $csvValues = [];
        $i = 0;

        while (($fileData = fgetcsv($file, 1000, ',')) !== false) {
            $num = count($fileData);

            if ($skipFirstRow && $i == 0) {
                $i++;

                continue;
            }

            for ($c = 0; $c < $num; $c++) {
                $csvValues[$i][] = trim($fileData[$c]);
            }

            $i++;
        }

        fclose($file);

        foreach ($csvValues as $csvRow) {
            $this->csvArray[] = $csvRow;
        }

        return $this;
    }

    public function toArray(): array
    {
        return $this->csvArray;
    }

    public function toCollection(): Collection
    {
        return collect($this->csvArray);
    }
}
