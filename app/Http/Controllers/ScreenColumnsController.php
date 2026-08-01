<?php

namespace App\Http\Controllers;

use App\Enums\ScreenResultColumnEnum;
use App\Models\Screen;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScreenColumnsController extends Controller
{
    /**
     * Columns are now edited in a slide-over on the screen edit page; this
     * route only remains so old links keep working.
     */
    public function edit(Screen $screen, Request $request)
    {
        if ($request->user()->cannot('update', $screen)) {
            abort(404);
        }

        return redirect()->to('/screens/'.$screen->getKey().'/edit');
    }

    public function update(Screen $screen, Request $request)
    {
        if ($request->user()->cannot('update', $screen)) {
            abort(404);
        }

        $validated = $request->validate([
            'columns' => ['present', 'array'],
            'columns.*' => [Rule::enum(ScreenResultColumnEnum::class)],
        ]);

        $screen->update(['columns' => $validated['columns']]);

        return redirect()->to('/screens/'.$screen->getKey().'/edit');
    }
}
