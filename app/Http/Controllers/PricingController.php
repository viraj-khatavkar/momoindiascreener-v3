<?php

namespace App\Http\Controllers;

use App\Enums\PlanEnum;
use Inertia\Response;

class PricingController extends Controller
{
    public function __invoke(): Response
    {
        return inertia('Pricing', [
            'prices' => [
                PlanEnum::Monthly->value => PlanEnum::Monthly->getAmountInRupees(),
                PlanEnum::Yearly->value => PlanEnum::Yearly->getAmountInRupees(),
                PlanEnum::Forever->value => PlanEnum::Forever->getAmountInRupees(),
            ],
        ]);
    }
}
