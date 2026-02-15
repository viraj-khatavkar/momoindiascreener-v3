<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    public function __invoke(Request $request, Order $order)
    {
        if ($request->user()->id !== $order->user_id) {
            abort(404);
        }

        if ($order->status === 'paid') {
            return redirect()->to('/pricing')->with('error', 'You have already paid for this order.');
        }

        return inertia('Billing/Payment', [
            'apiKey' => config('razorpay.key'),
            'amount' => $order->amount * 100,
            'description' => 'Payment for '.$order->plan->value,
            'order_id' => $order->order_id,
            'callback_url' => config('razorpay.callback_url'),
        ]);
    }
}
