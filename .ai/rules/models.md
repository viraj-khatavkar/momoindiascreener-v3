---
paths:
  - app/Models/BacktestNseInstrument.php
  - app/Models/NseIndex.php
  - app/Models/Screen.php
---

# Models

## Verify local index audits without the MySQL MRR read bug
On local MySQL 8.0.33 (macOS arm64), verify nse_indices date-range reads with FORCE INDEX (PRIMARY) or NO_MRR(nse_indices). A reproduced MRR read bug returns repeated IDs despite unique stored rows. Do not delete apparent duplicates based only on an MRR result. Confirm stored rows with a primary-key read. Related verified MySQL bug: https://bugs.mysql.com/bug.php?id=112642

## Use an industry lookup with stable public slugs
Use `backtest:import-industries` with local official NSE/Nifty constituent CSVs. The Nifty 1000 `Industry` column contains 22 broad groups. These are current labels. Store each name and stable public slug in `industries`. Leave `industry_id` null for an unknown label. Preserve links during daily copies and symbol changes. On a merge, fill an empty target only.

Use the lookup for industry display and public industry pages. Keep the slug when the name changes. The approved scope excludes historical industry fields on prices, scheduled classification syncs, and new screen or backtest filters.

## Keep the production public screen list explicit
The production public screen IDs are 1, 2, 3, 55, 377 and 1068. Public visibility is controlled by Screen::PUBLIC_SCREENS, not by a column in the SQL export. Use the same explicit list for home cards, screen lists, guest access, duplication and exports. Do not infer public status from the owner or publish other imported screens.
