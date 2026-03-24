<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UsersController extends Controller
{
    public function index(Request $request): Response
    {
        $usersQuery = User::query()
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->is_paid !== null && $request->is_paid !== '', function ($query) use ($request) {
                $query->where('is_paid', $request->boolean('is_paid'));
            })
            ->when($request->from_date, function ($query, $fromDate) {
                $query->where('created_at', '>=', $fromDate);
            })
            ->when($request->to_date, function ($query, $toDate) {
                $query->where('created_at', '<=', $toDate.' 23:59:59');
            })
            ->latest();

        $now = now();

        return inertia('Admin/Users/Index', [
            'users' => $usersQuery->paginate(50)->withQueryString(),
            'filters' => $request->only(['search', 'is_paid', 'from_date', 'to_date']),
            'stats' => [
                'total' => User::count(),
                'paid' => User::where('is_paid', true)->count(),
                'newThisMonth' => User::where('created_at', '>=', $now->copy()->startOfMonth())->count(),
                'newsletter' => User::where('is_newsletter_paid', true)->count(),
                'expiringIn7Days' => User::where('is_paid', true)
                    ->whereBetween('plan_ends_at', [$now->format('Y-m-d'), $now->copy()->addDays(7)->format('Y-m-d')])
                    ->count(),
            ],
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $users = User::query()
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->is_paid !== null && $request->is_paid !== '', function ($query) use ($request) {
                $query->where('is_paid', $request->boolean('is_paid'));
            })
            ->when($request->from_date, function ($query, $fromDate) {
                $query->where('created_at', '>=', $fromDate);
            })
            ->when($request->to_date, function ($query, $toDate) {
                $query->where('created_at', '<=', $toDate.' 23:59:59');
            })
            ->latest()
            ->lazy(1000);

        return response()->streamDownload(function () use ($users) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Email', 'Paid', 'Newsletter Paid', 'Plan Ends At', 'Registered At']);

            foreach ($users as $user) {
                fputcsv($handle, [
                    $user->name,
                    $user->email,
                    $user->is_paid ? 'Yes' : 'No',
                    $user->is_newsletter_paid ? 'Yes' : 'No',
                    $user->plan_ends_at,
                    $user->created_at?->format('d-m-Y'),
                ]);
            }

            fclose($handle);
        }, 'users.csv', ['Content-Type' => 'text/csv']);
    }
}
