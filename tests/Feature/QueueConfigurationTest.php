<?php

use App\Jobs\RunBacktestJob;
use App\Models\Backtest;
use Illuminate\Support\Facades\Artisan;
use Laravel\Horizon\ProvisioningPlan;

it('uses one Redis queue with multiple processes and safe timeouts', function () {
    $job = new RunBacktestJob(Backtest::factory()->make());
    $supervisor = ProvisioningPlan::get('test')
        ->optionsFor('local', 'supervisor-1');
    $redisConnection = config('queue.connections.redis');

    expect(config('queue.connections'))->not->toHaveKey('redis-backtests')
        ->and(config('horizon.defaults'))->toHaveCount(1)
        ->and(config('horizon.defaults'))->toHaveKey('supervisor-1')
        ->and($job->connection)->toBeNull()
        ->and($job->queue)->toBeNull()
        ->and($redisConnection['driver'])->toBe('redis')
        ->and($redisConnection['queue'])->toBe('default')
        ->and($supervisor->connection)->toBe('redis')
        ->and($supervisor->queue)->toBe('default')
        ->and($supervisor->maxProcesses)->toBeGreaterThan(1)
        ->and($job->timeout)->toBeLessThan($supervisor->timeout)
        ->and($supervisor->timeout)->toBeLessThan($redisConnection['retry_after']);
});

it('uses Horizon as the local Composer queue worker', function () {
    $composer = json_decode(
        file_get_contents(base_path('composer.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $developmentScript = implode(' ', $composer['scripts']['dev']);
    $serverRenderedDevelopmentScript = implode(' ', $composer['scripts']['dev:ssr']);

    expect($developmentScript)->toContain('php artisan horizon:listen')
        ->and($developmentScript)->not->toContain('queue:listen')
        ->and($serverRenderedDevelopmentScript)->toContain('php artisan horizon:listen')
        ->and($serverRenderedDevelopmentScript)->not->toContain('queue:listen');
});

it('uses a Horizon worker that supports the Laravel worker options', function () {
    $horizonWorkCommand = Artisan::all()['horizon:work'];

    expect($horizonWorkCommand->getDefinition()->hasOption('stop-when-empty-for'))
        ->toBeTrue();
});

it('uses Redis queues in the example environment', function () {
    $exampleEnvironment = file_get_contents(base_path('.env.example'));

    expect($exampleEnvironment)->toMatch('/^QUEUE_CONNECTION=redis$/m')
        ->and($exampleEnvironment)->toMatch('/^REDIS_QUEUE_RETRY_AFTER=3900$/m')
        ->and($exampleEnvironment)->not->toContain('REDIS_BACKTEST_');
});
