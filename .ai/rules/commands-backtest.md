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
The instrument import accepts only NSE UDiFF bhavcopy; do not add support for the older layout. Map by header names. Save FinInstrmNm in the existing daily price name column. TtlTradgVol is share volume and TtlTrfVal is turnover in rupees. Keep CM rows in EQ, BE, SM, ST, SZ, or BZ and exclude rights symbols. The 2024-03-04 upload has a trailing empty header with no corresponding data field; validate required columns without requiring equal row and header lengths.

## Keep Total Market and Microcap membership together
Nifty Total Market is the union of Nifty 500 and Nifty Microcap 250. Daily constituent imports must set both date-specific flags from backtest_nse_index_constituents, and refreshes must write both the 750-member and 250-member sets. Do not infer Microcap 250 from all stocks outside Nifty 500.

## Accept only ISO ex-dates in corporate action files
The user requires only the new corporate_actions.csv date format. Match EX_DT (column 6) directly to the requested YYYY-MM-DD date. Do not support or infer the legacy DD/MM/YYYY format, and do not use RECORD_DT to choose actions. Keep current-file precedence and the previous-trading-day fallback; each file must use ISO ex-dates.

## Accept only UDiFF bhavcopy
The daily instrument import accepts only NSE UDiFF bhavcopy. Map by header names. Save FinInstrmNm as the daily name; TtlTradgVol is unit volume and TtlTrfVal is turnover in rupees. Keep CM series EQ, BE, SM, ST, SZ, BZ, RR and IV; exclude rights symbols. Validate required columns without requiring equal header and row lengths. Historical REIT and InvIT repair uses NSE website price CSV exports through backtest:import-trust-history, because the user rejected the stored bhavcopies for that repair.

## Rebuild trust history from original NSE prices
Import only the NSE website security-wise historical CSVs for RR and IV. Rebuild adjusted OHLC from original CSV prices on every import; do not compound an earlier import or alter traded volume for cash payments. Apply index membership only from a sourced effective date. Reset and recalculate derived fields in chronological order with the existing calculation jobs. Keep backups and per-symbol checkpoints. New or sparse histories retain null metrics when the normal lookback requirements are unmet.

## Rebuild trust history from original NSE prices
Import only NSE website security-wise historical CSVs for RR and IV. Rebuild adjusted OHLC from original prices on every import. Apply cash events in ex-date order, using the prior adjusted close at each step, including multiple payments between trades. Do not alter traded volume. Use sourced effective dates for index membership. Recalculate with the existing jobs in date order; commit all fields for one date together before its checkpoint. Import and calculation must share per-symbol locks; separate symbols can run concurrently. Preserve backups and null metrics for insufficient history.

## Match trust beta to quote intervals
RR/IV trust returns may span gaps between trades. Match Nifty 50 close-to-close returns to the same pair of quote dates. Require the existing 235-observation annual minimum, a prior quote, and all matching benchmark dates. Leave covariance and beta null if benchmark data is missing or benchmark variance is zero. Never pair a multi-day trust return with a single-day benchmark return.

## Calculate breadth from dated members and available metrics
Include Total Market and Microcap 250. Read only the membership flags on the requested price date; skip indices with no members. Each percentage uses only members with a non-null value for that metric, retaining valid zero values. Save null when none qualify. Bulk refresh calls the daily command for indexed price dates, supports inclusive optional from/to bounds, preserves existing history, and stops on a failed date. Never truncate the heartbeat table or impose a fixed history start date.

## Include REITs in the custom Allcap universe
User decision: Allcap includes non-ETF EQ, BE, and RR instruments from their actual quote dates. Keep IV excluded. Official index flags still follow their own dated memberships; the September 2026 REIT index entry date does not limit custom Allcap membership.

## Retain REIT Allcap membership on historical reimport
User decision: set is_nifty_allcap for RR price history from its first quote, including dates before September 2026. Keep IV false. Preserve sourced effective dates for official index memberships.

## Bulk heartbeat is a full rebuild
Explicit user correction: backtest:bulk-calculate-market-heartbeat must truncate market_heartbeats and rebuild from scratch. Do not turn it into a preserving or partial-range refresh. Use the shared daily calculation for every available price date in order, skip each index on dates without stored members, and stop on a failed date. This supersedes the earlier rule that prohibited truncation or added from/to bounds. Daily calculations still use only available metric values and save null when none qualify.

## Include both trust series in custom Allcap
User extension: custom Allcap includes non-ETF EQ, BE, RR and IV instruments. This supersedes the earlier REIT-only rules that excluded IV, including the historical-reimport rule. Daily constituent imports and historical trust imports must set the same Allcap flag from actual quote dates. Heartbeats use that stored flag and available metrics. Official index membership still requires its own sourced effective date.

## Import dated F&O membership from official NSE files
User-approved universe: nifty_fno / is_nifty_fno. Historical membership comes from each date's official NSE F&O archive, including zero-volume stock contracts; never copy the current constituent snapshot backward. Retain source symbols, checksums, mappings, and unmatched symbols. NSE Files accepts fno.csv for the daily checklist, so server imports do not need NSE network access. The bulk importer is resumable; missing source dates remain unavailable.

## Retain the official F&O source and accept ZIP uploads
With explicit --download, fall back from a 404 bhavcopy to the official dated NSE daily settlement report; record the actual URL and checksum. Retain its CSV as YYYY-MM-DD-settlement.csv for offline reimports. NSE Files accepts an original F&O ZIP or CSV, validates its contents/date, and stores fno.csv for the daily checklist. Exclude the user-approved 2024-03-02 special session through BacktestNseFnoImport::IGNORED_DATES.

## Keep future F&O input UDiFF and admin uploads CSV only
User correction: NSE Files accepts only CSV uploads for F&O, matching other file types; reject ZIP uploads, including ZIP contents renamed as CSV. Extract the NSE ZIP before upload. The command downloads only UDiFF archives and has no legacy or settlement fallback. Existing historical dates reuse saved source records for reapplication/remapping, without parsing retained legacy archives. This supersedes the earlier ZIP-upload and settlement-fallback rule.

## Keep daily F&O CSV imports after history cleanup
User requested removal of completed history tools and temporary data. Keep the daily UDiFF CSV import, symbol-change matching, date validation and atomic price-flag updates. Remove archive downloads, ZIP parsing, bulk history and saved-source replay options. The source-import table and model are removed. Historical flags remain in the price table. This replaces the earlier archive-retention and source-record rules.

## Keep display memberships separate from filter choices
User decision: extra index memberships shown as stock pills must not expand the screener or backtest options or add price flags. Keep the existing approved filter indices. The user removed backtest:refresh-nse-index-constituents as unused; do not restore its full-table rebuild from price flags. Keep backtest:import-constituents for the daily membership step.

## Use false defaults for new membership flags
User decision: any newly added index membership flags must match the existing Total Market convention: non-null booleans with default false. Record new memberships from the tracking start date; do not backfill earlier dates or introduce NULL flags. Keep the existing screener and backtest choices restricted.

## Use the maintained constituent table for daily membership
User decision: constituent downloads and table maintenance happen asynchronously outside the daily process. The daily membership command reads backtest_nse_index_constituents as its source of truth; it must not download files or depend on source URLs. Extra indices may use new non-null, false-default price flags from the tracking start date, superseding the earlier no-new-flags constraint. Audit existing stored membership flags without rewriting them during this expansion. Keep the existing screener and backtest choices.

## Start expanded membership on 16 November 2026
User approved marking the latest stored price date, 2026-11-16, for the new stock index memberships. NseIndexMembership and config/nse_index_memberships.php define display/daily indices separately from NseIndexEnum filter choices. Keep added flags false before each start_date. The initial load adds only new master keys and price flags; preserve existing memberships and all other fields. Daily imports use only the maintained master table, update one date atomically, and report members without a price. Stock pills and constituent pages/exports read dated price flags. No download or source-URL requirement belongs in the daily process.
