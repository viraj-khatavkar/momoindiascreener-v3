<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyIsNewsletterPaid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->is_newsletter_paid) {
            return $next($request);
        }

        if (! $request->blog->is_paid) {
            return $next($request);
        }

        return redirect()->to('/pricing')->with('error', 'Please upgrade to the paid plan to access this page.');
    }
}
