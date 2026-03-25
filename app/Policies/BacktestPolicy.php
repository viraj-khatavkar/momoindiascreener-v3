<?php

namespace App\Policies;

use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use App\Models\User;

class BacktestPolicy
{
    public function view(User $user, Backtest $backtest): bool
    {
        return $backtest->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->is_paid;
    }

    public function update(User $user, Backtest $backtest): bool
    {
        return $backtest->user_id === $user->id;
    }

    public function delete(User $user, Backtest $backtest): bool
    {
        return $backtest->user_id === $user->id;
    }

    public function run(User $user, Backtest $backtest): bool
    {
        return $backtest->user_id === $user->id
            && $backtest->status !== BacktestStatusEnum::Running;
    }
}
