---
paths:
  - app/Providers/HorizonServiceProvider.php
---

# Providers

## Keep diagnostics for unexpected Horizon worker exits
Horizon disables child worker output. A worker can exit without a job exception and leave a Redis reservation active until retry_after expires. Keep WorkerProcessRestarting diagnostics for abnormal exit codes and termination signals. Exit codes 0 and Worker::EXIT_MEMORY_LIMIT (12) are normal restarts between jobs and must not be reported as failures. Do not retry failed backtest jobs; a user must start a new run with Save and Run.

## Apply the queue memory setting to PHP as well as Horizon
User-approved queue memory default is 512 MB for local and future production, configured through QUEUE_WORKER_MEMORY_LIMIT. Horizon memory only recycles workers between jobs; it does not raise PHP memory_limit. At worker command startup, raise a lower PHP limit and preserve higher or unlimited PHP limits. Do not apply this runtime override to unrelated Artisan commands or change the no-retry backtest policy.
