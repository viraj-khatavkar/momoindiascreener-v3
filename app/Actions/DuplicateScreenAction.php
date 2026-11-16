<?php

namespace App\Actions;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DuplicateScreenAction
{
    public function execute(Screen $screen, User $owner): Screen
    {
        return DB::transaction(function () use ($screen, $owner): Screen {
            $user = User::query()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail(['id']);

            $copy = $screen->replicate(['name', 'user_id'])->unsetRelations();
            $copy->name = $this->copyName($screen->name, $user);
            $user->screens()->save($copy);

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
        } while ($user->screens()->where('name', $name)->exists());

        return $name;
    }
}
