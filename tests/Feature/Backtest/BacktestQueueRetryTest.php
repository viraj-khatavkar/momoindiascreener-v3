<?php

use App\Jobs\RunBacktestJob;
use Illuminate\Contracts\Queue\Queue as QueueConnection;
use Illuminate\Queue\Events\JobRetryRequested;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Jobs\RetryFailedJob;

it('rejects CLI retries of backtest jobs before pushing or forgetting the failed job', function () {
    $id = 'failed-backtest';
    $failed = (object) [
        'id' => $id, 'connection' => 'redis', 'queue' => 'default',
        'payload' => json_encode(['data' => ['commandName' => RunBacktestJob::class]]),
    ];
    $failer = Mockery::mock(FailedJobProviderInterface::class);
    $failer->shouldReceive('find')->once()->with($id)->andReturn($failed);
    $failer->shouldNotReceive('forget');
    app()->instance('queue.failer', $failer);
    Queue::shouldReceive('connection')->never();

    expect(fn () => Artisan::call('queue:retry', ['id' => [$id]]))
        ->toThrow(ValidationException::class, 'Failed backtest jobs cannot be retried');
});

it('rejects Horizon retries of backtest jobs before pushing or recording a retry', function () {
    $jobs = Mockery::mock(JobRepository::class);
    $jobs->shouldReceive('findFailed')->once()->with('failed-backtest')->andReturn((object) [
        'payload' => json_encode(['data' => ['commandName' => RunBacktestJob::class]]),
    ]);
    $jobs->shouldNotReceive('storeRetryReference');
    app()->instance(JobRepository::class, $jobs);
    Queue::shouldReceive('connection')->never();

    expect(fn () => Bus::dispatch(new RetryFailedJob('failed-backtest')))
        ->toThrow(ValidationException::class, 'Failed backtest jobs cannot be retried');
});

it('leaves CLI retry events for other jobs available', function () {
    expect(fn () => Event::dispatch(new JobRetryRequested((object) [
        'payload' => json_encode(['data' => ['commandName' => 'App\\Jobs\\OtherJob']]),
    ])))->not->toThrow(ValidationException::class);
});

it('leaves Horizon retries for other jobs available', function () {
    $jobs = Mockery::mock(JobRepository::class);
    $jobs->shouldReceive('findFailed')->twice()->with('other-job')->andReturn((object) [
        'connection' => 'redis', 'queue' => 'default',
        'payload' => json_encode(['data' => ['commandName' => 'App\\Jobs\\OtherJob']]),
    ]);
    $jobs->shouldReceive('storeRetryReference')->once()->with('other-job', Mockery::any());
    app()->instance(JobRepository::class, $jobs);
    $connection = Mockery::mock(QueueConnection::class);
    $connection->shouldReceive('pushRaw')->once()->withArgs(function (string $payload, string $queue): bool {
        $decoded = json_decode($payload, true);

        return $queue === 'default' && $decoded['retry_of'] === 'other-job' && $decoded['attempts'] === 0;
    });
    Queue::shouldReceive('connection')->once()->with('redis')->andReturn($connection);

    Bus::dispatch(new RetryFailedJob('other-job'));
});
