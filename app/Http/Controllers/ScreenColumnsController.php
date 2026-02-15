<?php

namespace App\Http\Controllers;

use App\Enums\ScreenResultColumnEnum;
use App\Models\Screen;
use Illuminate\Http\Request;

class ScreenColumnsController extends Controller
{
    public function edit(Screen $screen, Request $request)
    {
        if ($request->user()->cannot('update', $screen)) {
            abort(404);
        }

        return inertia('Screens/Columns/Edit', [
            'screen' => $screen,
            'columns' => ScreenResultColumnEnum::resolveDisplayableValueList(),
        ]);
    }

    public function update(Screen $screen, Request $request)
    {
        if ($request->user()->cannot('update', $screen)) {
            abort(404);
        }

        $screen->update([
            'columns' => $request->input('columns'),
        ]);

        return redirect()->to('/screens/'.$screen->id.'/edit');
    }
}
