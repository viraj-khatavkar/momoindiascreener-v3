---
paths:
  - 'app/Actions/AdminProcess/**'
---

# Admin Process

## Run valuation imports before ETF marking
From 2024-03-01, put Import market cap and then Import price to earnings immediately after Apply corporate action adjustments and before Mark ETFs. Keep existing step IDs, results, and output when changing the order of unfinished runs.

## Update shared assumed delistings as the final daily step
Append Update assumed delistings (backtest:update-assumed-delistings --date=...) after Copy instruments. It must wait for every earlier step, including daily calculation jobs. Add it to unfinished saved checklists without replacing existing step IDs, output, or status. Completed checklists stay unchanged; the command also runs directly for initial builds and historical repairs. Backtests reuse the shared market-data tables instead of calculating gaps for each run.

## Import indices before market cap from 17 February 2026
From 2026-02-17, require index.csv and place Import indices (nse:import-indices --date=...) immediately before Import market cap, after Apply corporate action adjustments. This date-specific order takes precedence over the earlier valuation ordering rule. Add the step to eligible unfinished saved runs while preserving existing IDs, results and output. Leave completed runs and earlier dates unchanged.
