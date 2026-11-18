<?php

namespace App\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class LogoutUserAction
{
    public function execute(Request $request): void
    {
        if ($request->session()->has('ghosting')) {
            Log::info('User ghosting ended on sign-out.', $request->session()->get('ghosting'));
            Auth::logoutCurrentDevice();
        } else {
            Auth::logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::clearHistory();
    }
}
