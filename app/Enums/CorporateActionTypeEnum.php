<?php

namespace App\Enums;

use App\Contracts\ResolveDisplayableValueListForEnum;
use App\Traits\ResolveDisplayableValueListForEnumTrait;

enum CorporateActionTypeEnum: string implements ResolveDisplayableValueListForEnum
{
    use ResolveDisplayableValueListForEnumTrait;

    case BONUS = 'bonus';
    case SPLIT = 'split';
    case DIVIDEND = 'dividend';
    case RIGHTS = 'rights';
    case DEMERGER = 'demerger';
}
