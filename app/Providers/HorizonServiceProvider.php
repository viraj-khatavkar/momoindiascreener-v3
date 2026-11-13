<?php

namespace App\Providers;

use App\Listeners\PreventBacktestJobRetry;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use Laravel\Horizon\Jobs\RetryFailedJob;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

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
        Gate::define('viewHorizon', function ($user = null) {
            return in_array(optional($user)->email, [
                //
            ]);
        });
    }
}
