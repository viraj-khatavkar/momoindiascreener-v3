<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifyGhostingSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class GhostingController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is_admin || $request->session()->has('ghosting'), 403);

        $accounts = ['admin_id' => $request->user()->id, 'user_id' => $user->id];
        $this->switchAccount($request, $user);
        $request->session()->put('ghosting', $accounts);
        Log::info('User ghosting started.', $accounts);

        return to_route('home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $admin = $request->attributes->get(VerifyGhostingSession::ADMIN_ATTRIBUTE);
        abort_unless($admin instanceof User, 403);

        $accounts = $request->session()->get('ghosting');
        $this->switchAccount($request, $admin);
        Log::info('User ghosting ended on return to admin.', $accounts);

        return to_route('admin.users.index');
    }

    private function switchAccount(Request $request, User $user): void
    {
        $request->session()->flush();
        Auth::login($user);
        Inertia::clearHistory();
    }
}
