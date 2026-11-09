---
paths:
  - app/Actions/ApplyScreenFiltersAction.php
  - app/Actions/BuildHomePageDataAction.php
  - app/Actions/ReadCsvAction.php
  - 'app/Actions/*MarketIndex*Action.php'
  - app/Actions/PrepareLegacyProductionImportAction.php
  - app/Actions/AnalyzeRebalancePortfolioAction.php
  - app/Actions/BuildPortfolioValuationAction.php
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
