---
paths:
  - app/Console/Commands/Backtest/ImportNseInstrumentsCommand.php
  - 'app/Console/Commands/Backtest/*ConstituentsCommand.php'
  - app/Console/Commands/Backtest/ImportNseCorporateActionsCommand.php
---

# Commands Backtest

## Accept only UDiFF bhavcopy
The instrument import accepts only NSE UDiFF bhavcopy; do not add support for the older layout. Map by header names. Save FinInstrmNm in the existing daily price name column. TtlTradgVol is share volume and TtlTrfVal is turnover in rupees. Keep CM rows in EQ, BE, SM, ST, SZ, or BZ and exclude rights symbols. The 2024-03-04 upload has a trailing empty header with no corresponding data field; validate required columns without requiring equal row and header lengths.

## Keep Total Market and Microcap membership together
Nifty Total Market is the union of Nifty 500 and Nifty Microcap 250. Daily constituent imports must set both date-specific flags from backtest_nse_index_constituents, and refreshes must write both the 750-member and 250-member sets. Do not infer Microcap 250 from all stocks outside Nifty 500.

## Accept only ISO ex-dates in corporate action files
The user requires only the new corporate_actions.csv date format. Match EX_DT (column 6) directly to the requested YYYY-MM-DD date. Do not support or infer the legacy DD/MM/YYYY format, and do not use RECORD_DT to choose actions. Keep current-file precedence and the previous-trading-day fallback; each file must use ISO ex-dates.
