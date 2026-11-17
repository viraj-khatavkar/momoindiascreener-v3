<?php

use Illuminate\Queue\Worker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\Horizon\Events\WorkerProcessRestarting;
use Laravel\Horizon\WorkerProcess;
use Symfony\Component\Process\Process;

it('logs unexpected worker exits before Horizon replaces the process', function (int $exitCode, ?int $signal) {
    $command = 'php artisan horizon:work redis --queue=default';
    $process = Mockery::mock(Process::class);
    $process->shouldReceive('getExitCode')->once()->andReturn($exitCode);
    $process->shouldReceive('hasBeenSignaled')->once()->andReturn($signal !== null);
    $process->shouldReceive('getCommandLine')->once()->andReturn($command);

    if ($signal !== null) {
        $process->shouldReceive('getTermSignal')->once()->andReturn($signal);
    } else {
        $process->shouldNotReceive('getTermSignal');
    }

    Log::shouldReceive('error')->once()->with('Horizon queue worker exited unexpectedly.', [
        'exit_code' => $exitCode,
        'signal' => $signal,
        'command' => $command,
    ]);

    Event::dispatch(new WorkerProcessRestarting(new WorkerProcess($process)));
})->with([
    'fatal PHP error' => [255, null],
    'worker error' => [Worker::EXIT_ERROR, null],
    'killed process' => [137, 9],
]);

it('does not report normal worker restarts as failures', function (?int $exitCode) {
    $process = Mockery::mock(Process::class);
    $process->shouldReceive('getExitCode')->once()->andReturn($exitCode);
    $process->shouldNotReceive('hasBeenSignaled');
    $process->shouldNotReceive('getCommandLine');
    Log::shouldReceive('error')->never();

    Event::dispatch(new WorkerProcessRestarting(new WorkerProcess($process)));
})->with([
    'graceful stop' => [Worker::EXIT_SUCCESS],
    'memory limit restart between jobs' => [Worker::EXIT_MEMORY_LIMIT],
    'no exit code' => [null],
]);
