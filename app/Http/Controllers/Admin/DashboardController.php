<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        $recentOrders = Order::query()
            ->with('user')
            ->paid()
            ->latest()
            ->limit(10)
            ->get();

        $expiringUsers = User::query()
            ->where('is_paid', true)
            ->whereBetween('plan_ends_at', [$now->format('Y-m-d'), $now->copy()->addDays(14)->format('Y-m-d')])
            ->orderBy('plan_ends_at')
            ->limit(10)
            ->get();

        return inertia('Admin/Dashboard', [
            'stats' => [
                'totalUsers' => User::count(),
                'paidUsers' => User::where('is_paid', true)->count(),
                'newThisMonth' => User::where('created_at', '>=', $startOfMonth)->count(),
                'newsletterUsers' => User::where('is_newsletter_paid', true)->count(),
                'expiringIn7Days' => User::where('is_paid', true)
                    ->whereBetween('plan_ends_at', [$now->format('Y-m-d'), $now->copy()->addDays(7)->format('Y-m-d')])
                    ->count(),
            ],
            'ordersComparison' => [
                'thisMonth' => [
                    'count' => Order::paid()->where('created_at', '>=', $startOfMonth)->count(),
                    'revenue' => Order::paid()->where('created_at', '>=', $startOfMonth)->sum('amount'),
                ],
                'lastMonth' => [
                    'count' => Order::paid()->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count(),
                    'revenue' => Order::paid()->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->sum('amount'),
                ],
            ],
            'recentOrders' => $recentOrders,
            'expiringUsers' => $expiringUsers,
        ]);
    }
}
