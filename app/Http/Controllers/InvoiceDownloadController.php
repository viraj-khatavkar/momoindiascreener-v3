<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Invoice;

class InvoiceDownloadController extends Controller
{
    public function __invoke(Request $request, Order $order)
    {
        if ($request->user()->cannot('view', $order)) {
            abort(404);
        }

        $customer = new Buyer([
            'name' => $order->user->name,
            'address' => $order->address_line_one.$order->address_line_two.' '.$order->city.' '.$order->state.' '.$order->country.' '.
                $order->postal_code,
            'custom_fields' => [
                'email' => $order->email,
            ],
        ]);

        $item = (new InvoiceItem)
            ->title($order->description)
            ->pricePerUnit($order->amount);

        $invoice = Invoice::make()
            ->sequence($order->invoice_number)
            ->buyer($customer)
            ->date($order->created_at)
            ->status(__('paid'))
            ->addItem($item);

        return $invoice->stream();
    }
}
