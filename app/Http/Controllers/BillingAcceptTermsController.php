<?php

namespace App\Http\Controllers;

use App\Enums\PlanEnum;
use Illuminate\Http\Request;

class BillingAcceptTermsController extends Controller
{
    public function __invoke(Request $request, PlanEnum $plan)
    {
        return inertia('Billing/AcceptTerms', [
            'plan' => $plan,
        ]);
    }
}
