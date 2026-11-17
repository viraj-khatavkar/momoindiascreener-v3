<?php

use App\Jobs\RunBacktestJob;
use App\Models\Backtest;
use Illuminate\Support\Facades\Artisan;
use Laravel\Horizon\ProvisioningPlan;
use Symfony\Component\Process\Process;

it('keeps backtests on the default Redis queue with safe worker limits', function (string $environment) {
    $job = new RunBacktestJob(Backtest::factory()->make());
    $supervisor = ProvisioningPlan::get('test')
        ->optionsFor($environment, 'supervisor-1');
    $redisConnection = config('queue.connections.redis');

    expect(config('queue.connections'))->not->toHaveKey('redis-backtests')
        ->and(config('horizon.defaults'))->toHaveKeys(['supervisor-1', 'screen-exports'])
        ->and(config('queue.worker_memory_limit'))->toBe(512)
        ->and($supervisor->memory)->toBe(512)
        ->and(ProvisioningPlan::get('test')->optionsFor($environment, 'screen-exports')->memory)->toBe(512)
        ->and($job->connection)->toBeNull()
        ->and($job->queue)->toBeNull()
        ->and($job->tries)->toBe(1)
        ->and($job->failOnTimeout)->toBeTrue()
        ->and($redisConnection['driver'])->toBe('redis')
        ->and($redisConnection['queue'])->toBe('default')
        ->and($supervisor->connection)->toBe('redis')
        ->and($supervisor->queue)->toBe('default')
        ->and($supervisor->maxProcesses)->toBeGreaterThan(1)
        ->and($job->timeout)->toBeLessThan($supervisor->timeout)
        ->and($supervisor->timeout)->toBeLessThan($redisConnection['retry_after']);
})->with(['local', 'production']);

it('raises a lower PHP worker limit and preserves higher or unlimited limits', function (string $command, string $initialLimit, string $expectedLimit) {
    $code = <<<'PHP'
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    config(['queue.worker_memory_limit' => 512]);
    Illuminate\Support\Facades\Event::dispatch(new Illuminate\Console\Events\CommandStarting(
        $argv[1], new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\NullOutput(),
    ));
    echo ini_get('memory_limit');
    PHP;

    $process = new Process([PHP_BINARY, '-d', 'memory_limit='.$initialLimit, '-r', $code, $command], base_path());
    $process->mustRun();

    expect($process->getOutput())->toBe($expectedLimit);
})->with(['horizon:work', 'queue:work', 'queue:listen'])->with([
    'lower limit' => ['128M', '512M'],
    'equal limit' => ['512M', '512M'],
    'higher limit' => ['1G', '1G'],
    'unlimited' => ['-1', '-1'],
]);

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
        ->and($exampleEnvironment)->toMatch('/^QUEUE_WORKER_MEMORY_LIMIT=512$/m')
        ->and($exampleEnvironment)->toMatch('/^REDIS_QUEUE_RETRY_AFTER=3900$/m')
        ->and($exampleEnvironment)->not->toContain('REDIS_BACKTEST_');
});
