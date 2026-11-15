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
---

# Actions

## Apply historical valuation filters only when data is available
Imported market cap and P/E coverage starts on 2024-03-01. Use the resolved trading date for historical screens. Before that date, or when a valuation field has no data for that day, skip that filter and show a warning on the screen page. Check coverage across the market, not only the selected index; missing data for an individual stock must still fail an active valuation filter.

## Preserve the approved decile counts and strict filter boundaries
The user approved decile sizes based on NseIndexEnum::numberOfConstituents, rather than the actual row count. The user also approved strict greater-than comparisons for minimum annual return and negative distance from highs. Do not change these behaviors as audit fixes.

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
The old production close field is adjusted; translate it to close_adjusted. In the old screen engine, both series flags off omitted the series filter; the current engine returns no rows. Validate secondary and tertiary ranking fields even when disabled because the result query selects them. Missing F&O membership, the six/three-month Sharpe field, and active legacy top-beta/top-volatility filters have no equivalent in the current engine: report affected screens and require an explicit skip, rather than silently changing them.

## Omit both legacy top filters from the production import
User decision: do not migrate ignore_top_beta or ignore_top_volatility. Omit both fields for every screen, including enabled values. These fields must not block import or require --skip-unsupported-screens. Report enabled values as information. This replaces the earlier rule that required a screen to be skipped for either active filter. Keep the other screen compatibility checks.

## Distinguish the six and three month Sharpe column from its sorting option
The current backtest_nse_instrument_prices table has average_sharpe_return_six_three_months as decimal(20,2). CalculateMomentumCommand already calculates it from the six and three month Sharpe returns. The value is absent from ScreenSortByOptionEnum, which causes the import compatibility check to reject it. The earlier statement that this field has no equivalent is incorrect. Check the sorting option separately from database column availability; no new database column is needed.

## Import saved series flags and the six and three month Sharpe option
User decision: preserve series_eq and series_be as saved, including when both are false. Empty series is information and must not block import. The current results behavior remains: both flags off returns no rows. ScreenSortByOptionEnum now supports average_sharpe_return_six_three_months for all three ranking factors in screen and backtest settings. Import this value unchanged; no new database column is needed. This replaces the earlier restrictions on empty series and the earlier note that this sorting option was absent. Keep the other compatibility checks.

## Preserve saved screen behavior in the Rebalance Tracker
Check portfolio symbols against the latest prices, but retain the saved screen historical date, combined ranking, and unique ETF selection during analysis. Show the resolved screen date in the results. Keep all entries within the worst-rank boundary. A blank entry limit uses the exit count, including zero. An empty screen result must produce a validation error rather than a full exit list.

## Value saved portfolios from entered holdings and raw prices
Saved portfolios contain current holding entries, not a dated trade ledger. Date selection values those same entries with the latest positive raw quote on or before that date; it does not reconstruct past holdings. Use indexed quote lookups in a fixed number of queries, shared by the page and CSV. Keep missing prices null and label partial totals. Compare quote dates with the last available market date to avoid weekend warnings. Store holding symbols without a foreign key to the instrument master, which imports replace. Corporate actions are shown for review and do not change entered holdings.

## Trust cash policy and source evidence
Use an explicitly declared distribution total once. If no total is stated, sum only explicit dividend and interest amounts. Ignore separate principal or debt repayments; never add components to a distribution total. Require source reconciliation for missing amounts, combined interest/principal amounts, and multiple totals on one ex-date. Historical cash files use EX-DATE; keep their parser separate from the daily ISO-only importer. Retain source files, SHA-256 checksums, symbol aliases, and verified corrections in the import manifest.

## Recognise NSE distribution abbreviations
Daily NSE corporate action files use DISTRIBUTN, DISTRIBTN and DISTRIBTIN as well as DISTRIBUTION. Normalize these observed forms before cash detection and amount extraction so valid RR/IV distributions are not silently skipped. Preserve the full per-unit amount, existing cash policy, ISO ex-date matching and idempotent imports.

## Keep historical F&O formats and symbol identities explicit
Support both legacy INSTRUMENT/SYMBOL/TIMESTAMP/EXPIRY_DT and UDiFF FinInstrmTp/TckrSymb/TradDt/XpryDt. The official 2012-05-14 file uses d-M-y dates and a trailing *Updated line. Validate source dates and exclude index contracts. NSE symbolchange.csv may have a header or no header. Follow dated rename chains, reject ambiguous or duplicate price matches, and preserve original source symbols. Do not treat mergers or reused symbols as automatic renames.

## Support saved legacy F&O screens
nifty_fno is now a supported NseIndexEnum value. Preserve it when importing legacy screens. This supersedes the earlier rule that classified all F&O screens as unsupported; data coverage is checked by the screen and backtest paths.

## Use dated settlement futures when a bhavcopy is missing
Two official bhavcopy archives return 404: 2013-10-09 and 2021-03-30. The official F&O - Daily Settlement Prices reports cover both dates (141 and 156 stocks). Accept DATE/INSTRUMENT/UNDERLYING/EXPIRY DATE, requiring valid unexpired FUTSTK contracts. Ignore OPTSTK settlement spot rows because they have no contract expiry. Keep source dates, checksums and symbol mappings; never copy adjacent membership as a substitute.

## Accept only UDiFF for new F&O imports after history completion
User correction after the complete 2011-2026 history import: accept only FinInstrmTp/TckrSymb/TradDt/XpryDt with ISO dates and STF/STO stock contracts. Reject legacy bhavcopies and settlement CSVs. This supersedes the earlier multi-format parsing and settlement-fallback rules. Retain all imported history, source audits, dated symbol mappings and the special-session exclusion.

## Read F&O coverage and group sizes from price flags
User decision: the price table is the only source for dated F&O membership. Read is_nifty_fno for coverage and count all flagged price rows for the selected date before metric or screen filters. Do not require a source-import table. Keep fixed constituent counts for other universes.

## Remove completed trust history tools and temporary sources
User authorized cleanup after the REIT and InvIT import. Remove the one-time trust history commands, historical CSV readers, manifests, downloads, backups and reports. This replaces the earlier instructions to retain those temporary files. Keep imported database values, daily trust cash parsing and adjustments, valuation imports, analytics and market heartbeat support. Keep original user-supplied files unless deletion is requested.

## Keep constituent lists tied to a completed price date
Instrument membership links carry the displayed quote date. Constituent pages and CSV exports use membership flags and basic metrics from the same date in backtest_nse_instrument_prices, including F&O. Resolve the latest price date on or before the requested date and exclude unfinished daily process dates. Do not substitute the current constituent master or an older membership set when the resolved date has no members. Export every matching row with the same search and sort, independently of pagination. Keep adjusted close labels and market cap units in crores.
