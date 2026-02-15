<?php

namespace App\Http\Controllers;

use App\Enums\PlanEnum;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Razorpay\Api\Api;

class OrdersController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'plan' => ['required', Rule::enum(PlanEnum::class)],
        ]);

        $plan = PlanEnum::from($request->plan);

        $user = User::find($request->user()->getKey());

        $razorpay = new Api(config('razorpay.key'), config('razorpay.secret'));

        $planEndsAt = $user->plan_ends_at ? new Carbon($user->plan_ends_at) : now();

        if ($planEndsAt->gt(now()->addDays(7))) {
            return redirect()->to('/pricing')->with(
                'error',
                'You already have an active subscription. Please wait until 7 days before expiration to purchase a new one.'
            );
        }

        $response = $razorpay->order->create([
            'amount' => $plan->getAmountInPaise(),
            'currency' => 'INR',
            'receipt' => Str::uuid(),
        ]);

        $order = Order::create([
            'order_id' => $response->id,
            'user_id' => $request->user()->id,
            'plan' => $plan->value,
            'description' => $plan->getDisplayName(),
            'amount' => $plan->getAmountInRupees(),
            'status' => 'pending',
            'email' => $request->user()->email,
            'address_line_one' => $request->user()->address_line_one,
            'address_line_two' => $request->user()->address_line_two,
            'city' => $request->user()->city,
            'state' => $request->user()->state,
            'country' => $request->user()->country,
            'postal_code' => $request->user()->postal_code,
            'terms_accepted' => now(),
        ]);

        return redirect()->to('/billing/payment/'.$order->id);
    }
}
