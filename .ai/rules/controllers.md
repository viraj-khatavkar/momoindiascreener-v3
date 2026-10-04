---
paths:
  - app/Http/Controllers/BacktestsController.php
---

# Controllers

## Serialize large backtest trade logs in bounded model batches
Return trade logs through a deferred Inertia scroll prop with at most 100 trades per page and a stable date/type/ID order. Do not collect the full trade log, even with lazy model batches. Search, type, reason, year, and sorting must apply in SQL across the full saved run. Reset merged trade data when filters change; use SQL aggregates for counts instead of counting only loaded rows.
