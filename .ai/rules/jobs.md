---
paths:
  - app/Jobs/RunBacktestJob.php
---

# Jobs

## Validate queued backtests before deleting saved results
Keep market-data validation in the worker, outside Save and Run requests. Validate required dates, shared gaps, index DMA and completed source NAV before deleting that run’s result rows. Mark validation failures Failed with the reason and retain existing rows. For source runs, capture one end date in the source worker, validate both calendars and append the dependent job with that date. Laravel dispatches the dependent only after the source simulation, metrics and allocation all complete. Preserve run timestamp guards when handling failures.

## Require Save and Run after a failed backtest
User decision: failed backtest jobs must not be retried from the queue. Keep RunBacktestJob tries at one and failOnTimeout enabled. Block queue:retry through JobRetryRequested and Horizon RetryFailedJob before either pushes the old job. Preserve retries for other job types. The backtest page starts a new run through Save and Run; a failed source sequence must start with the source again.
