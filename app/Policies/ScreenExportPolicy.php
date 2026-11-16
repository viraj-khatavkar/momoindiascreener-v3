<?php

namespace App\Policies;

use App\Models\ScreenExport;
use App\Models\User;

class ScreenExportPolicy
{
    public function view(User $user, ScreenExport $screenExport): bool
    {
        return $user->is_paid && $screenExport->user_id === $user->id;
    }
}
