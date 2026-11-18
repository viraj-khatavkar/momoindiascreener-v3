---
paths:
  - app/Models/BacktestNseInstrument.php
  - app/Models/NseIndex.php
  - app/Models/Screen.php
---

# Models

## Exclude sector and industry classification features
User decision: do not display, import, export, or schedule updates for sector or industry classifications. The proposed local NSE lookup and CSV sync workflow was cancelled. Keep these features out unless the user explicitly changes this decision.

## Verify local index audits without the MySQL MRR read bug
On local MySQL 8.0.33 (macOS arm64), verify nse_indices date-range reads with FORCE INDEX (PRIMARY) or NO_MRR(nse_indices). A reproduced MRR read bug returns repeated IDs despite unique stored rows. Do not delete apparent duplicates based only on an MRR result. Confirm stored rows with a primary-key read. Related verified MySQL bug: https://bugs.mysql.com/bug.php?id=112642

## Store the requested current industry labels on instruments
User now authorizes importing industry labels from official NSE/Nifty constituent CSVs, superseding the earlier exclusion for this storage/import scope. Store the CSV Industry value once in backtest_nse_instruments.industry, nullable when unknown; Nifty 1000 uses 22 broad sector-level groups under that header. These are current labels, not historical classifications on price rows. Use the separate local-file backtest:import-industries command; preserve metadata during daily copies and carry it on symbol renames/merges when the target value is empty. Do not infer missing labels.

## Use an industry lookup with stable public slugs
User now authorizes industry display and per-industry stock pages. This supersedes the earlier classification exclusion and string-storage choice. Store names and unique stable slugs in industries; instruments have nullable industry_id. Import current official CSV labels through backtest:import-industries and preserve links on daily copies and symbol changes. Keep slugs when display names change. Do not infer missing labels, add price-table industry fields, or expand screen/backtest filter choices.

## Keep the production public screen list explicit
The production public screen IDs are 1, 2, 3, 55, 377 and 1068. Public visibility is controlled by Screen::PUBLIC_SCREENS, not by a column in the SQL export. Use the same explicit list for home cards, screen lists, guest access, duplication and exports. Do not infer public status from the owner or publish other imported screens.
