---
paths:
  - app/Console/Commands/Backtest/ImportNseInstrumentsCommand.php
---

# Commands Backtest

## Accept only UDiFF bhavcopy
The instrument import accepts only NSE UDiFF bhavcopy; do not add support for the older layout. Map by header names. Save FinInstrmNm in the existing daily price name column. TtlTradgVol is share volume and TtlTrfVal is turnover in rupees. Keep CM rows in EQ, BE, SM, ST, SZ, or BZ and exclude rights symbols. The 2024-03-04 upload has a trailing empty header with no corresponding data field; validate required columns without requiring equal row and header lengths.
