<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RazorpayController extends Controller
{
    public function callback(Request $request)
    {
        $request->validate([
            'razorpay_order_id' => 'required',
            'razorpay_payment_id' => 'required',
            'razorpay_signature' => 'required',
        ]);

        $order = Order::where('order_id', $request->get('razorpay_order_id'))->first();
        if (! $order) {
            abort(404);
        }

        $generatedSignature = hash_hmac(
            'sha256',
            $order->order_id.'|'.$request->get('razorpay_payment_id'),
            config('razorpay.secret')
        );

        if ($generatedSignature == $request->get('razorpay_signature')) {

            $lastInvoiceNumber = Order::query()
                ->whereNotNull('invoice_number')
                ->select('invoice_number')
                ->latest()
                ->first();

            if (is_null($lastInvoiceNumber)) {
                $lastInvoiceNumber = 0;
            } else {
                $lastInvoiceNumber = $lastInvoiceNumber->invoice_number;
            }

            $order->status = 'paid';
            $order->razorpay_payment_id = $request->get('razorpay_payment_id');
            $order->razorpay_order_id = $request->get('razorpay_order_id');
            $order->razorpay_signature = $request->get('razorpay_signature');
            $order->invoice_number = $lastInvoiceNumber + 1;
            $order->save();

            $user = User::find($order->user_id);

            $planEndsAt = $user->plan_ends_at ? new Carbon($user->plan_ends_at) : now();
            if ($planEndsAt->gt(now())) {
                $order->plan_starts_at = $planEndsAt;
                $user->plan_ends_at = $planEndsAt->copy()->addMonths($order->plan->monthsToAdd());
            } else {
                $order->plan_starts_at = now();
                $user->plan_ends_at = now()->addMonths($order->plan->monthsToAdd());
            }
            $order->plan_ends_at = $user->plan_ends_at;
            $order->save();

            $user->is_paid = true;
            $user->is_newsletter_paid = true;
            $user->save();

            return redirect('/pricing')
                ->with(
                    'success',
                    'Payment successful! You now have full access to momoindiascreener.in until '.$user->plan_ends_at->format(
                        'd M Y'
                    ).'.'
                );
        }

        return redirect()->to('/pricing');
    }

    public function cancel(Request $request)
    {
        return redirect()
            ->to('/pricing')
            ->with('error', 'There was some issue with your payment. Please try again!');
    }
}
