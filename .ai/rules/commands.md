---
paths:
  - app/Console/Commands/ImportNseIndicesCommand.php
  - app/Console/Commands/ReloadNseIndexHistoryCommand.php
---

# Commands

## Allow zero closing values for Nifty50 Dividend Points
Nifty50 Dividend Points resets to zero each year after the March derivatives settlement. Accept a finite, non-negative close for the normalized slug nifty50-dividend-points. All other index closes must remain strictly positive. Missing, non-numeric, negative and out-of-range closes must still fail before any records are saved. Source: https://archives.nseindia.com/content/indices/Nifty50_Dividend_Points.pdf

## Compare complete index history before replacing the table
Compare OHLC, P/E, P/B and dividend yield with the current table before a full reload. Fill missing OHL with the same row's close; keep missing valuations null. Nifty50 Dividend Points can close at zero: the historical endpoint can omit valid zero rows, so verify these against official daily reports. For conflicting BHARAT Bond closes, use the historical page values, as selected by the user. Preserve checked source files and refuse a reload if the source or current table changed after comparison.

## Start IDs at one when reloading complete index history
The user requires a full nse_indices history reload to assign IDs from 1 and set the next auto-increment value above the loaded maximum. DELETE alone does not reset the counter. Check for ID references before renumbering, preserve every non-ID value, and verify the replacement before switching tables.
