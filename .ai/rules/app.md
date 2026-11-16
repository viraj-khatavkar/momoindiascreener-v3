---
paths:
  - 'app/**/*ScreenExport*.php'
---

# App

## Keep historical screen ZIP exports bounded and private
Paid verified users may export their own saved screens or example screens. Limit each request to 30 calendar days, including both dates, and allow one active export per user. Copy saved settings at submission. Reuse screen filters and ranking for each exact historical date, ignoring the saved single date. Process one date at a time on the separate screen-exports queue. Report missing or incomplete data separately from no matches. Keep files private, expire downloads after seven days, and remove expired files and files for deleted accounts. Do not change backtest execution for this feature.
