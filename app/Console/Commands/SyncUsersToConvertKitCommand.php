<?php

namespace App\Console\Commands;

use App\Jobs\SyncUserToConvertKitJob;
use App\Models\User;
use Illuminate\Console\Command;

class SyncUsersToConvertKitCommand extends Command
{
    protected $signature = 'convertkit:sync-users';

    protected $description = 'Queue all verified users for ConvertKit subscriber and paid tag sync';

    public function handle(): int
    {
        if (blank(config('convertkit.key')) || (int) config('convertkit.paid_tag_id') <= 0) {
            $this->error('Set CONVERTKIT_API_KEY and CONVERTKIT_PAID_TAG_ID before starting the sync.');

            return self::FAILURE;
        }

        $count = 0;

        User::query()
            ->whereNotNull('email_verified_at')
            ->select('id')
            ->eachById(function (User $user) use (&$count): void {
                SyncUserToConvertKitJob::dispatch($user->id);
                $count++;
            });

        $this->info("Queued {$count} users for ConvertKit sync.");

        return self::SUCCESS;
    }
}
