<?php

namespace App\Policies;

use App\Models\Screen;
use App\Models\User;

class ScreenPolicy
{
    public function view(User $user, Screen $screen): bool
    {
        return $screen->user_id === $user->id || in_array($screen->id, Screen::PUBLIC_SCREENS);
    }

    public function create(User $user): bool
    {
        return $user->is_paid;
    }

    public function update(User $user, Screen $screen): bool
    {
        return $screen->user_id === $user->id;
    }

    public function delete(User $user, Screen $screen): bool
    {
        return $screen->user_id === $user->id;
    }
}
