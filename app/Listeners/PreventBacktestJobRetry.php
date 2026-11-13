<?php

namespace App\Listeners;

use App\Jobs\RunBacktestJob;
use Illuminate\Queue\Events\JobRetryRequested;
use Illuminate\Validation\ValidationException;

class PreventBacktestJobRetry
{
    public function handle(JobRetryRequested $event): void
    {
        $this->assertCanRetry($event->payload() ?? []);
    }

    /** @param array<string, mixed> $payload */
    public function assertCanRetry(array $payload): void
    {
        if (($payload['data']['commandName'] ?? null) === RunBacktestJob::class) {
            throw ValidationException::withMessages([
                'run' => 'Failed backtest jobs cannot be retried. Open the backtest and select Save and Run to start a new run.',
            ]);
        }
    }
}
