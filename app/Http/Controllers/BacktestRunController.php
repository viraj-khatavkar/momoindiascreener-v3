<?php

namespace App\Http\Controllers;

use App\Actions\Backtest\StartBacktestRunAction;
use App\Models\Backtest;
use Illuminate\Http\Request;

class BacktestRunController extends Controller
{
    public function __invoke(Request $request, Backtest $backtest, StartBacktestRunAction $startRun)
    {
        if ($request->user()->cannot('run', $backtest)) {
            abort(404);
        }

        $startRun->execute($backtest);

        return redirect()->to('/backtests/'.$backtest->getKey())
            ->with('success', 'Backtest queued for execution');
    }
}
