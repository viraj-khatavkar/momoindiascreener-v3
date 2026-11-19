---
paths:
  - app/Console/Commands/Backtest/ImportNseInstrumentsCommand.php
  - 'app/Console/Commands/Backtest/*ConstituentsCommand.php'
  - app/Console/Commands/Backtest/ImportNseCorporateActionsCommand.php
  - 'app/Console/Commands/Backtest/*TrustHistoryCommand.php'
  - app/Console/Commands/Backtest/CalculateCovarianceCommand.php
  - 'app/Console/Commands/Backtest/*MarketHeartbeatCommand.php'
  - 'app/Console/Commands/Backtest/*Fno*'
---

# Commands Backtest

## Accept only UDiFF bhavcopy
The daily instrument import accepts NSE UDiFF bhavcopy. Match columns by header name. Use FinInstrmNm for the daily name, TtlTradgVol for unit volume, and TtlTrfVal for turnover in rupees. Keep CM rows in EQ, BE, SM, ST, SZ, BZ, RR, or IV. Exclude rights symbols. Check required columns individually. The 2024-03-04 file has a trailing empty header with no matching data field.

## Keep Total Market and Microcap membership together
Nifty Total Market is the union of Nifty 500 and Nifty Microcap 250. Daily constituent imports must set both date-specific flags from backtest_nse_index_constituents, and refreshes must write both the 750-member and 250-member sets. Do not infer Microcap 250 from all stocks outside Nifty 500.

## Accept only ISO ex-dates in corporate action files
The user requires only the new corporate_actions.csv date format. Match EX_DT (column 6) directly to the requested YYYY-MM-DD date. Do not support or infer the legacy DD/MM/YYYY format, and do not use RECORD_DT to choose actions. Keep current-file precedence and the previous-trading-day fallback; each file must use ISO ex-dates.

## Use original NSE prices for historical trust repairs
For a requested RR or IV repair, use the original NSE security-wise CSV prices. Rebuild adjusted OHLC from those prices on each pass. Apply cash events in ex-date order using the prior adjusted close, including multiple payments between trades. Preserve traded volume and use sourced effective dates for official index membership.

Keep a backup during the repair. Use the existing calculation jobs in date order. Commit all fields for one date before saving its checkpoint. Import and calculation share a lock per symbol. Separate symbols can run concurrently. Leave metrics null until there is enough history.

## Match trust beta to quote intervals
RR/IV trust returns may span gaps between trades. Match Nifty 50 close-to-close returns to the same pair of quote dates. Require the existing 235-observation annual minimum, a prior quote, and all matching benchmark dates. Leave covariance and beta null if benchmark data is missing or benchmark variance is zero. Never pair a multi-day trust return with a single-day benchmark return.

## Calculate breadth from dated members and available metrics
Include Total Market and Microcap 250. Read membership flags on the requested price date and skip indices with no members. Each percentage uses members with a value for that metric, including zero. Save null when no members have a value.

## Rebuild the full market heartbeat history
`backtest:bulk-calculate-market-heartbeat` truncates `market_heartbeats`. Run the daily calculation for every available price date in order and stop on a failed date. A partial date range would leave the history incomplete.

## Include both trust series in custom Allcap
Custom Allcap includes non-ETF EQ, BE, RR, and IV instruments from their actual quote dates, including dates before an official index entry. Daily imports and historical repairs must set the same Allcap flag. Heartbeats use the stored flag and available metrics. Official indices require their own sourced membership dates.

## Import daily F&O membership from an extracted NSE UDiFF CSV
Use the uploaded `fno.csv` for the requested date, including zero-volume stock contracts. Reject ZIP content even when the filename ends in `.csv`. Match dated symbol changes and validate all matches before updating price flags in one transaction. Use `NseIndexEnum::FNO_IGNORED_DATES` for the approved special session. Leave missing dates unavailable. The daily command needs no network access, archive downloads, or saved-source replay.

## Use the maintained constituent table for daily membership
`backtest:import-constituents` reads `backtest_nse_index_constituents`. Downloads and master-table maintenance happen outside the daily process. Update one price date atomically and report members without a matching price. The full-table rebuild command `backtest:refresh-nse-index-constituents` is retired. Do not rebuild the master from price flags.

## Start expanded membership on 16 November 2026
`NseIndexMembership` and `config/nse_index_memberships.php` define display and daily indices. Keep the approved screener and backtest choices in `NseIndexEnum`. New membership flags are non-null booleans with a false default. The initial expansion starts on 2026-11-16. Leave each flag false before its own start date. Add only new master keys and flags when extending coverage. Audit existing memberships without rewriting them. Stock pills, constituent pages, and exports use dated price flags.
