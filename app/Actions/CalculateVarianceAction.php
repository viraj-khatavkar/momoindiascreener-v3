<?php

namespace App\Actions;

class CalculateVarianceAction
{
    public function execute(array $population): float
    {
        $population = collect($population);

        $average = $population->average();

        $dispersionSquared = $population->map(function ($person) use ($average) {
            return $person - $average;
        })->map(function ($dispersion) {
            return round(pow($dispersion, 2), 8);
        })->sum();

        return $dispersionSquared / $population->count();
    }
}
