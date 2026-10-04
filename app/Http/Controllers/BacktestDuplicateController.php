<?php

namespace App\Http\Controllers;

use App\Actions\Backtest\DuplicateBacktestAction;
use App\Models\Backtest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BacktestDuplicateController extends Controller
{
    public function __invoke(Request $request, Backtest $backtest, DuplicateBacktestAction $duplicate): RedirectResponse
    {
        if ($request->user()->cannot('duplicate', $backtest)) {
            abort(404);
        }

        $copy = $duplicate->execute($backtest);

        return redirect()->route('backtests.show', ['backtest' => $copy, 'tab' => 'settings'])
            ->with('success', 'Backtest copied. You can edit the settings or run the new backtest.');
    }
}
