<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait ResolveDisplayableValueListForEnumTrait
{
    public static function resolveDisplayableValueList(): array
    {
        return collect(static::cases())
            ->map(fn ($record) => [
                'id' => $record->value,
                'name' => ucwords(Str::of($record->value)->replace('_', ' ')),
            ])
            ->all();
    }
}
