<?php

namespace App\Enums;

use App\Contracts\ResolveDisplayableValueListForEnum;
use App\Traits\ResolveDisplayableValueListForEnumTrait;

enum CountryEnum: string implements ResolveDisplayableValueListForEnum
{
    use ResolveDisplayableValueListForEnumTrait;

    case India = 'india';
}
