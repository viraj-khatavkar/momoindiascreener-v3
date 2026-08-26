<?php

namespace App\Http\Controllers;

use App\Actions\BuildHomePageDataAction;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, BuildHomePageDataAction $buildHomePageData): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return Inertia::render('Welcome', $buildHomePageData->execute($user));
    }
}
