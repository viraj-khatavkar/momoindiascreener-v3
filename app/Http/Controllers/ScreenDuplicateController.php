<?php

namespace App\Http\Controllers;

use App\Actions\DuplicateScreenAction;
use App\Models\Screen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScreenDuplicateController extends Controller
{
    public function __invoke(Request $request, Screen $screen, DuplicateScreenAction $duplicate): RedirectResponse
    {
        if ($request->user()->cannot('duplicate', $screen)) {
            abort(404);
        }

        $copy = $duplicate->execute($screen, $request->user());

        return redirect()->route('screens.edit', ['screen' => $copy, 'tab' => 'rules'])
            ->with('success', 'Screen copied. You can edit your private copy.');
    }
}
