---
paths:
  - app/Console/Commands/ImportNseIndicesCommand.php
  - app/Console/Commands/ReloadNseIndexHistoryCommand.php
  - 'app/Console/Commands/**'
---

# Commands

## Allow zero closing values for Nifty50 Dividend Points
Nifty50 Dividend Points resets to zero each year after the March derivatives settlement. Accept a finite, non-negative close for the normalized slug nifty50-dividend-points. All other index closes must remain strictly positive. Missing, non-numeric, negative and out-of-range closes must still fail before any records are saved. Source: https://archives.nseindia.com/content/indices/Nifty50_Dividend_Points.pdf

## Compare complete index history before replacing the table
Compare OHLC, P/E, P/B and dividend yield with the current table before a full reload. Fill missing OHL with the same row's close; keep missing valuations null. Nifty50 Dividend Points can close at zero: the historical endpoint can omit valid zero rows, so verify these against official daily reports. For conflicting BHARAT Bond closes, use the historical page values, as selected by the user. Preserve checked source files and refuse a reload if the source or current table changed after comparison.

## Start IDs at one when reloading complete index history
The user requires a full nse_indices history reload to assign IDs from 1 and set the next auto-increment value above the loaded maximum. DELETE alone does not reset the counter. Check for ID references before renumbering, preserve every non-ID value, and verify the replacement before switching tables.

## Use daily files for index history scope and report first imports
Build historical import lists from the union of indices in uploaded index.csv files over the last 30 days; also check the last 30 files before excluding a series. Do not exclude an index because one partial daily file omits it. Continue accepting new indices from daily files. After a successful import, report each index name whose slug had no stored history on any date before that import; repeated imports and validation-only runs must not report a first import.

## Allow repeated imports of partial daily index files
Daily processing can start with Nifty 50 and receive other indices later. For each uploaded row, match slug and date, add missing records, update changed prices and valuations, and leave matching values unchanged. Keep stored indices omitted from a later partial file and preserve other dates. Report added, updated and unchanged counts, plus first-import index names. Compare against the uploaded file; do not require the full daily index set.

## Keep daily-file history while accepting new indices
The user selected the 167 indices in uploads/2026-11-13/index.csv as the retained historical set and authorized a one-time removal of histories outside that list. This is not a permanent import allowlist: continue importing new indices found in later daily files and report each new name so the user can obtain its history manually or with AI. Do not remove stored history merely because a later partial daily file omits an index.

## Remove completed index import tools and temporary sources
The user requested cleanup after the index history and constituent imports. Remove the one-time full-history reload, index comparison and temporary flag commands, saved downloads, preparation scripts, audit files and obsolete import tables. This replaces the earlier requirement to preserve checked historical source files after the import is complete. Keep all active index history, price membership flags, the backtest_nse_index_constituents master, instrument and industry data, uploaded daily files, daily import commands, repeat index imports and the local industry CSV importer.
