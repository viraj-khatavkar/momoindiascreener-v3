<?php

namespace App\Actions\Backtest;

use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DuplicateBacktestAction
{
    public function execute(Backtest $backtest): Backtest
    {
        return DB::transaction(function () use ($backtest): Backtest {
            $user = $backtest->user()->lockForUpdate()->firstOrFail(['id']);

            $copy = $backtest->replicate([
                'name', 'user_id', 'status', 'progress', 'started_at',
                'completed_at', 'settings_changed_at', 'error_message',
            ])->unsetRelations();

            $copy->fill([
                'name' => $this->copyName($backtest->name, $user),
                'status' => BacktestStatusEnum::Pending,
                'progress' => 0,
                'started_at' => null,
                'completed_at' => null,
                'settings_changed_at' => null,
                'error_message' => null,
            ]);

            $user->backtests()->save($copy);

            return $copy;
        });
    }

    private function copyName(string $originalName, User $user): string
    {
        $baseName = Str::replaceMatches('/ \(Copy(?: [0-9]+)?\)$/u', '', $originalName);
        $number = 1;

        do {
            $suffix = $number === 1 ? ' (Copy)' : " (Copy {$number})";
            $name = Str::substr($baseName, 0, 250 - Str::length($suffix)).$suffix;
            $number++;
        } while ($user->backtests()->where('name', $name)->exists());

        return $name;
    }
}
