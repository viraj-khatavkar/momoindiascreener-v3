<?php

namespace App\Enums;

enum MarketIndexAliasStatusEnum: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Ignored = 'ignored';
}
