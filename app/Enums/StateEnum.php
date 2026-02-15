<?php

namespace App\Enums;

use App\Contracts\ResolveDisplayableValueListForEnum;
use App\Traits\ResolveDisplayableValueListForEnumTrait;

enum StateEnum: string implements ResolveDisplayableValueListForEnum
{
    use ResolveDisplayableValueListForEnumTrait;

    case AN = 'Andaman and Nicobar Islands';
    case AD = 'Andhra Pradesh';
    case AR = 'Arunachal Pradesh';
    case AS = 'Assam';
    case BR = 'Bihar';
    case CH = 'Chandigarh';
    case CG = 'Chhattisgarh';
    case DN = 'Dadra and Nagar Haveli and Daman and Diu';
    case DD = 'Daman and Diu';
    case DL = 'Delhi';
    case GA = 'Goa';
    case GJ = 'Gujarat';
    case HR = 'Haryana';
    case HP = 'Himachal Pradesh';
    case JK = 'Jammu and Kashmir';
    case JH = 'Jharkhand';
    case KA = 'Karnataka';
    case KL = 'Kerala';
    case LA = 'Ladakh';
    case LD = 'Lakshadweep';
    case MP = 'Madhya Pradesh';
    case MH = 'Maharashtra';
    case MN = 'Manipur';
    case ML = 'Meghalaya';
    case MZ = 'Mizoram';
    case NL = 'Nagaland';
    case OD = 'Odisha';
    case PY = 'Puducherry';
    case PB = 'Punjab';
    case RJ = 'Rajasthan';
    case SK = 'Sikkim';
    case TN = 'Tamil Nadu';
    case TS = 'Telangana';
    case TR = 'Tripura';
    case UP = 'Uttar Pradesh';
    case UK = 'Uttarakhand';
    case WB = 'West Bengal';
}
