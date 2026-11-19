---
paths:
  - app/Actions/ApplyScreenFiltersAction.php
  - app/Actions/BuildHomePageDataAction.php
  - app/Actions/ReadCsvAction.php
  - 'app/Actions/*MarketIndex*Action.php'
  - app/Actions/PrepareLegacyProductionImportAction.php
  - app/Actions/AnalyzeRebalancePortfolioAction.php
  - app/Actions/BuildPortfolioValuationAction.php
  - 'app/Actions/*Trust*Action.php'
  - 'app/Actions/*Fno*Action.php'
  - app/Actions/QueryNseIndexConstituentsAction.php
  - app/Actions/RankInstrumentsAction.php
  - app/Actions/DuplicateScreenAction.php
---

# Actions

## Apply historical valuation filters only when data is available
Imported market cap and P/E coverage starts on 2024-03-01. Use the resolved trading date for historical screens. Before that date, or when a valuation field has no data for that day, skip that filter and show a warning on the screen page. Check coverage across the market, not only the selected index; missing data for an individual stock must still fail an active valuation filter.

## Preserve the approved decile counts and strict filter boundaries
Use `NseIndexEnum::numberOfConstituents` for fixed-size universes. F&O uses dated price flags as described below. Keep strict greater-than comparisons for minimum annual return and negative distance from highs.

## Skip company valuation filters for ETF screens
ETFs never have market cap or P/E data in this application. For NseIndexEnum::NIFTY_ETF, skip both valuation filters for current and historical dates and show a warning for each active filter. Keep the saved limits and all other filters so stock screens still apply their valuation limits when the universe changes.

## Select unique ETFs after the other screen filters
The unique_etf_per_index option is off by default and applies only to ETF screens. After all filters, including decile restrictions and required factor data, keep the ETF with the highest median_volume_one_year for each mapped etf_index. Use volume from the resolved screen date, break ties by symbol ascending, keep unmapped symbols separate, and rank the selected ETFs with the saved factors. Apply the same selection to screen pages and CSV exports.

## Separate personal and public home lists
Signed-in users see only their own screens and backtests, including saved backtests when paid access has expired. Limit personal lists to the six most recently updated items with ID as a stable tie-breaker, and return full owner-scoped counts. Guests see Screen::PUBLIC_SCREENS and only explicitly selected completed public backtests. Home screen cards use saved metadata; do not run screen filters or load result previews on the home page.

## Decode NSE CSV text before Unicode string operations
NSE CSV files can contain Windows-1252 text, including byte 0x96 for an en dash in ETF index labels. Convert fields that are not valid UTF-8 from Windows-1252 to UTF-8 when reading the CSV. Preserve valid UTF-8. Unicode string operations such as Str::squish can return null for these raw bytes, which can also prevent admin process output from being saved.

## Use canonical ETF indexes and symbol-specific ambiguous aliases
Treat TRI, TR, total-return, index suffixes and known fund-name variants as aliases of one canonical market index. Preserve strategy numbers, providers and bond maturities. Match exact canonical keys against active database indexes and approved aliases; never create a new index during import. Generic labels such as INDEX and COMMODITY, and conflicting changes for an existing ETF, require symbol-specific aliases. Preserve ignored/manual decisions and the previous etf_index while a change is pending. Canonical cleanup uses backtest:normalize-etf-indexes, with a preview by default and a backup plus transaction for --apply. Keep all aliases, suggestions and instrument slugs consistent when merging indexes.

## Preserve the meaning of legacy screen settings during import
The legacy `close` was adjusted. Map it to `close_adjusted`. Preserve the `nifty_fno` universe and the `average_sharpe_return_six_three_months` option for any ranking factor. Validate secondary and tertiary factors even when disabled because the result query selects them. The screen and backtest paths check F&O coverage.

Preserve `series_eq` and `series_be` as saved. With both off, the old engine omitted the series filter and the current engine returns no rows. Omit `ignore_top_beta` and `ignore_top_volatility` even when enabled. Report empty series and enabled omitted filters as information. Reserve `--skip-unsupported-screens` for settings that actually lack support.

## Preserve saved screen behavior in the Rebalance Tracker
Check portfolio symbols against the latest prices, but retain the saved screen historical date, combined ranking, and unique ETF selection during analysis. Show the resolved screen date in the results. Keep all entries within the worst-rank boundary. A blank entry limit uses the exit count, including zero. An empty screen result must produce a validation error rather than a full exit list.

## Value saved portfolios from entered holdings and raw prices
Saved portfolios contain current holding entries, not a dated trade ledger. Date selection values those same entries with the latest positive raw quote on or before that date; it does not reconstruct past holdings. Use indexed quote lookups in a fixed number of queries, shared by the page and CSV. Keep missing prices null and label partial totals. Compare quote dates with the last available market date to avoid weekend warnings. Store holding symbols without a foreign key to the instrument master, which imports replace. Corporate actions are shown for review and do not change entered holdings.

## Trust cash policy and source evidence
Use an explicitly declared distribution total once. If no total is stated, sum only explicit dividend and interest amounts. Exclude separate principal or debt repayments. Reconcile missing amounts, combined interest and principal, or multiple totals on one ex-date against the source. Historical cash files used EX-DATE. The daily importer uses ISO ex-dates.

## Recognise NSE distribution abbreviations
Daily NSE corporate action files use DISTRIBUTN, DISTRIBTN and DISTRIBTIN as well as DISTRIBUTION. Normalize these observed forms before cash detection and amount extraction so valid RR/IV distributions are not silently skipped. Preserve the full per-unit amount, existing cash policy, ISO ex-date matching and idempotent imports.

## Match F&O symbols by date
NSE `symbolchange.csv` may have a header or no header. Follow dated rename chains, preserve the original source symbols, and reject ambiguous or duplicate price matches. Mergers and reused symbols require separate evidence.

## Historical F&O source exceptions
The completed import used a legacy file with d-M-y dates and a trailing *Updated line for 2012-05-14. Missing bhavcopies for 2013-10-09 and 2021-03-30 were reconciled against the official daily settlement reports. Unexpired FUTSTK contracts established 141 and 156 stocks respectively. OPTSTK spot rows had no expiry. Preserve those dated memberships. An adjacent date cannot establish membership.

## Accept only UDiFF for new F&O imports
Use FinInstrmTp, TckrSymb, TradDt, and XpryDt with ISO dates and STF/STO stock contracts. Reject legacy bhavcopies and settlement CSVs. Preserve imported history, existing audit evidence, dated symbol mappings, and the approved special-session exclusion.

## Read F&O coverage and group sizes from price flags
User decision: the price table is the only source for dated F&O membership. Read is_nifty_fno for coverage and count all flagged price rows for the selected date before metric or screen filters. Do not require a source-import table. Keep fixed constituent counts for other universes.

## Keep the completed trust import separate from daily processing
The one-time REIT and InvIT import is complete. Its commands, historical CSV readers, manifests, downloads, backups, and reports were temporary. Preserve imported values, daily cash parsing and adjustments, valuation imports, analytics, and market heartbeat support. Keep original user-supplied files unless the user requests deletion.

## Keep constituent lists tied to a completed price date
Instrument membership links carry the displayed quote date. Constituent pages and CSV exports use membership flags and basic metrics from the same date in backtest_nse_instrument_prices, including F&O. Resolve the latest price date on or before the requested date and exclude unfinished daily process dates. Do not substitute the current constituent master or an older membership set when the resolved date has no members. Export every matching row with the same search and sort, independently of pagination. Keep adjusted close labels and market cap units in crores.

## Use shared average ranks and fixed tie ordering
Screen and backtest factors give equal stored numeric values the average of their occupied ranks. Sum only enabled factor ranks. Resolve equal totals by symbol ascending; use the same symbol tie order for single-factor queries and primary-factor decile cutoffs. Keep fixed decile counts and eligibility filters. Final backtest ranks remain consecutive integers for entry limits, worst-rank checks and reciprocal weighting. Swapping factors preserves results only when the eligible pool stays the same; primary-factor decile selection still runs first.

## Copy saved screens into a paid users private workspace
Paid users with verified email may duplicate a screen they own or one of Screen::PUBLIC_SCREENS. Check access on the server and save the copy under the requesting user. Copy all saved filters, factor settings, historical settings and result columns, with fresh identity and timestamps and no loaded relations. Generate numbered Copy names within the new owner scope. The editor must require saved changes before duplication and open the new copy with fresh Inertia state.

## Apply screen EMA filters to adjusted closes
Screens support opt-in above/below EMA 20, 50, 100 and 200 filters. Compare close_adjusted strictly against the stored EMA, require every selected condition, and ignore EMA flags when apply_ema is off. Default all EMA flags false, including in-memory models used for older export snapshots. Copy and save all flags; current/historical results, CSV, and queued ZIP exports must reuse the same filtering action.

## Seek to the portfolio valuation date for each symbol
Use the existing symbol and date index to find the last positive raw close for each distinct holding symbol. A correlated lookup can sort years of prices for each holding even when MySQL uses that index. Check the number of rows MySQL reads on a historical date as well as the number of queries.
