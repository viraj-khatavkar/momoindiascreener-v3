---
paths:
  - 'app/Actions/AdminProcess/**'
---

# Admin Process

## Apply the import order for the process date
From 2024-03-01, run Import market cap and then Import price to earnings after Apply corporate action adjustments and before Mark ETFs. From 2026-02-17, require `index.csv` and insert Import indices (`nse:import-indices --date=...`) immediately before Import market cap. Apply each change to eligible unfinished runs. Preserve existing step IDs, results, and output. Leave completed checklists as saved.

## Update shared assumed delistings as the final daily step
Append Update assumed delistings (backtest:update-assumed-delistings --date=...) after Copy instruments. It must wait for every earlier step, including daily calculation jobs. Add it to unfinished saved checklists without replacing existing step IDs, output, or status. Completed checklists stay unchanged; the command also runs directly for initial builds and historical repairs. Backtests reuse the shared market-data tables instead of calculating gaps for each run.

## Repeat only the index import from the daily process page
The completed import-indices step may run again, including after the daily run is complete. Show one Import indices again button in its existing command block. Reuse the queue action and output history. Keep every other completed command locked. Block repeat imports while any step is queued or running or a prior step is incomplete. Restart live output updates after a successful repeat request and preserve all other steps.
