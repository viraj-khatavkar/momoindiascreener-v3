<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlanEnum;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrdersController extends Controller
{
    public function index(Request $request): Response
    {
        $fromDate = $request->get('from_date', now()->startOfMonth()->format('Y-m-d'));
        $toDate = $request->get('to_date', now()->format('Y-m-d'));
        $toDateEnd = Carbon::parse($toDate)->addDay();

        $ordersQuery = Order::query()
            ->with('user')
            ->paid()
            ->whereBetween('created_at', [$fromDate, $toDateEnd])
            ->when($request->search, function ($query, $search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest();

        $count = $ordersQuery->count();
        $totalRevenue = $ordersQuery->sum('amount');

        return inertia('Admin/Orders/Index', [
            'orders' => $ordersQuery->paginate(50)->withQueryString(),
            'count' => $count,
            'totalRevenue' => $totalRevenue,
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'search' => $request->search,
            ],
            'planCounts' => [
                'monthly' => Order::paid()->where('plan', PlanEnum::Monthly)->whereBetween('created_at', [$fromDate, $toDateEnd])->count(),
                'yearly' => Order::paid()->where('plan', PlanEnum::Yearly)->whereBetween('created_at', [$fromDate, $toDateEnd])->count(),
                'forever' => Order::paid()->where('plan', PlanEnum::Forever)->whereBetween('created_at', [$fromDate, $toDateEnd])->count(),
                'newsletter' => Order::paid()->where('plan', PlanEnum::Newsletter)->whereBetween('created_at', [$fromDate, $toDateEnd])->count(),
            ],
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $fromDate = $request->get('from_date', now()->startOfMonth()->format('Y-m-d'));
        $toDate = $request->get('to_date', now()->format('Y-m-d'));

        $orders = Order::query()
            ->with('user')
            ->paid()
            ->whereBetween('created_at', [$fromDate, Carbon::parse($toDate)->addDay()])
            ->when($request->search, function ($query, $search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->lazy(1000);

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Name', 'Email', 'Invoice Number', 'Plan', 'Amount']);

            foreach ($orders as $order) {
                fputcsv($handle, [
                    $order->created_at?->format('d-m-Y'),
                    $order->user->name,
                    $order->user->email,
                    $order->invoice_number,
                    $order->plan?->value,
                    $order->amount,
                ]);
            }

            fclose($handle);
        }, 'orders.csv', ['Content-Type' => 'text/csv']);
    }
}
