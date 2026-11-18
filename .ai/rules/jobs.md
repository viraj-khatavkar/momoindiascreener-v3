---
paths:
  - app/Jobs/RunBacktestJob.php
  - app/Jobs/SyncUserToConvertKitJob.php
---

# Jobs

## Validate queued backtests before deleting saved results
Keep market-data validation in the worker, outside Save and Run requests. Validate required dates, shared gaps, index DMA and completed source NAV before deleting that run’s result rows. Mark validation failures Failed with the reason and retain existing rows. For source runs, capture one end date in the source worker, validate both calendars and append the dependent job with that date. Laravel dispatches the dependent only after the source simulation, metrics and allocation all complete. Preserve run timestamp guards when handling failures.

## Require Save and Run after a failed backtest
User decision: failed backtest jobs must not be retried from the queue. Keep RunBacktestJob tries at one and failOnTimeout enabled. Block queue:retry through JobRetryRequested and Horizon RetryFailedJob before either pushes the old job. Preserve retries for other job types. The backtest page starts a new run through Save and Run; a failed source sequence must start with the source again.

## Sync ConvertKit through the default queue
User decision: use the default queue for ConvertKit updates. Keep the verified-email requirement from the old site. Queue changes after the database commit, and read the latest saved user state in the worker. Paid tag 6008806 follows users.is_paid, including expiry updates. Do not restore the nightly subscriber sync or use a separate queue.
