---
paths:
  - 'app/Actions/AdminProcess/**'
---

# Admin Process

## Run valuation imports before ETF marking
From 2024-03-01, put Import market cap and then Import price to earnings immediately after Apply corporate action adjustments and before Mark ETFs. Keep existing step IDs, results, and output when changing the order of unfinished runs.

## Update shared assumed delistings as the final daily step
Append Update assumed delistings (backtest:update-assumed-delistings --date=...) after Copy instruments. It must wait for every earlier step, including daily calculation jobs. Add it to unfinished saved checklists without replacing existing step IDs, output, or status. Completed checklists stay unchanged; the command also runs directly for initial builds and historical repairs. Backtests reuse the shared market-data tables instead of calculating gaps for each run.
