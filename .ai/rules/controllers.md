---
paths:
  - app/Http/Controllers/BacktestsController.php
---

# Controllers

## Serialize large backtest trade logs in bounded model batches
Return trade logs through a deferred Inertia scroll prop with at most 100 trades per page and a stable date/type/ID order. Do not collect the full trade log, even with lazy model batches. Search, type, reason, year, and sorting must apply in SQL across the full saved run. Reset merged trade data when filters change; use SQL aggregates for counts instead of counting only loaded rows.

## Expose only selected completed backtests through public read routes
Use backtests.is_public, with a default of false, to select guest examples. Only completed backtests with this flag enabled can appear on the home page or public results routes. Use the publiclyVisible scope and viewPublic policy for public pages, deferred requests and benchmark data, including after the flag is cleared. Reuse the results page with readOnly enabled and hide owner IDs, errors, settings, run, duplicate and CSV controls. Private routes retain authentication, verification, paid access and ownership checks. Copies start private even when the source is an example.
