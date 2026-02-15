<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class InvoicesController extends Controller
{
    public function __invoke(Request $request)
    {
        return inertia('Invoices', [
            'orders' => Order::query()
                ->whereNotNull('invoice_number')
                ->where('user_id', $request->user()->id)
                ->get(),
        ]);
    }
}
