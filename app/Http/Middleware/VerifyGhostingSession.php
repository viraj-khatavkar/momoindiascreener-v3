<?php

namespace App\Http\Middleware;

use App\Actions\LogoutUserAction;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyGhostingSession
{
    public const string ADMIN_ATTRIBUTE = 'ghostingAdmin';

    public function __construct(private LogoutUserAction $logoutUser) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $accounts = $request->session()->get('ghosting');
        if ($accounts === null) {
            return $next($request);
        }

        $user = $request->user();
        $admin = User::query()
            ->where('is_admin', true)
            ->whereNotNull('email_verified_at')
            ->find($accounts['admin_id']);

        if ($admin === null || $user === null || $user->is_admin || $user->id !== $accounts['user_id']) {
            $this->logoutUser->execute($request);

            return to_route('login');
        }

        $request->attributes->set(self::ADMIN_ATTRIBUTE, $admin);

        return $next($request);
    }
}
