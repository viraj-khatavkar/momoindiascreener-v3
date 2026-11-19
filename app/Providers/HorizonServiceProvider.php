<?php

namespace App\Providers;

use App\Listeners\PreventBacktestJobRetry;
use App\Models\User;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Foundation\Application;
use Illuminate\Queue\Worker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Events\WorkerProcessRestarting;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use Laravel\Horizon\Jobs\RetryFailedJob;
use RuntimeException;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if (! in_array($event->command, ['horizon:work', 'queue:work', 'queue:listen'], true)) {
                return;
            }

            $memoryLimit = (int) config('queue.worker_memory_limit');
            if ($memoryLimit <= 0) {
                throw new RuntimeException('QUEUE_WORKER_MEMORY_LIMIT must be a positive number of megabytes.');
            }

            $currentLimit = ini_parse_quantity((string) ini_get('memory_limit'));
            if ($currentLimit !== -1 && $currentLimit < $memoryLimit * 1024 * 1024) {
                if (ini_set('memory_limit', $memoryLimit.'M') === false) {
                    throw new RuntimeException('Unable to raise the PHP queue worker memory limit to '.$memoryLimit.' MB. Update the server PHP configuration.');
                }
            }
        });

        Event::listen(WorkerProcessRestarting::class, function (WorkerProcessRestarting $event): void {
            $process = $event->process->process;
            $exitCode = $process->getExitCode();

            if (in_array($exitCode, [null, Worker::EXIT_SUCCESS, Worker::EXIT_MEMORY_LIMIT], true)) {
                return;
            }

            Log::error('Horizon queue worker exited unexpectedly.', [
                'exit_code' => $exitCode,
                'signal' => $process->hasBeenSignaled() ? $process->getTermSignal() : null,
                'command' => $process->getCommandLine(),
            ]);
        });

        $this->app->bindMethod([RetryFailedJob::class, 'handle'], function (RetryFailedJob $retry, Application $app): void {
            $jobs = $app->make(JobRepository::class);
            $failed = $jobs->findFailed($retry->id);
            if ($failed === null) {
                return;
            }

            $app->make(PreventBacktestJobRetry::class)->assertCanRetry(json_decode($failed->payload, true) ?? []);
            $retry->handle($app->make(QueueFactory::class), $jobs);
        });

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (User $user): bool => $user->is_admin && $user->hasVerifiedEmail());
    }
}
