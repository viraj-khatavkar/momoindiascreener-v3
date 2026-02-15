<?php

namespace App\Actions;

class CalculatePercentageDifferenceAction
{
    public function execute($startValue, $endValue): float|int
    {
        return ($endValue / $startValue - 1) * 100;
    }
}
