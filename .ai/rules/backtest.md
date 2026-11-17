---
paths:
  - 'app/Actions/Backtest/**'
  - app/Actions/Backtest/ImportNseValuationAction.php
---

# Backtest

## Persist daily market cap allocation
Store market cap percentages on the daily NAV snapshots once per backtest run. Results pages must read saved values; do not recalculate the full price and trade history on a page load. Fill old results with backtest:backfill-market-cap-allocation; use --refresh after historical data corrections. Mark dates without sufficient index coverage as calculated, so they are not processed repeatedly. Large cap uses historical Nifty 100 membership, mid cap uses historical Nifty Midcap 150 membership, and other stocks are small cap. Start only when both indices have historical membership data; percentages use total portfolio value with cash and ETFs separate.

## Import daily valuations in the agreed units
From 2024-03-01, daily process runs require marketcap.csv and price_to_earnings.csv. Save marketcap as integer crores: divide raw rupees by 10,000,000 and round to the nearest whole crore. Keep the existing integer column. Save ADJUSTED P/E, not SYMBOL P/E, in price_to_earnings. Match market cap by date, symbol, and series; P/E has no series column. Skip market cap totals and preserve earlier dates.

## Compare moving averages with adjusted prices
The ma_* and ema_* columns use close_adjusted. Later corporate actions rescale these historical columns. Use close_adjusted for MA/EMA filters, exit reasons, and hold-above-DMA checks. Use close_raw for the configured rupee price range. Comparing a raw price with an adjusted average can change historical trades when new dates are imported.

## Use the decision date for stock DMA hold signals
When execute_next_trading_day is enabled, evaluate the hold-above-DMA override on the rebalance decision date, like the stock filters and cash-call signal. Use the execution date only for trade prices and execution restrictions. Reading the execution-day stock DMA can reverse a hold or sale decision after it should have been fixed.

## Include retained holdings in inverse-volatility allocations
A holding can remain protected by the hold-above-DMA rule after it fails entry filters. Include retained holdings in inverse-volatility targets using volatility from the decision date, and use execution-date prices for trades. Absence from the filtered rank list does not mean volatility is missing. Preserve the existing exit rule for actual missing or non-positive volatility.

## Confirm per-stock stops on the next trading close
Fixed stops use the weighted average adjusted buy price, excluding charges; added shares update that average. Trailing stops use the highest adjusted close since entry and never move down. Keep the peak through top-ups and partial sales, and reset it after a full exit. A close strictly below the stop creates a signal. Sell only at the immediately next trading day close if an actual positive quote is lower than the signal close, irrespective of execute_next_trading_day. Equal closes, rebounds, missing quotes, and circuit restrictions require a fresh stop check. Stock stops override hold-above-DMA protection; defensive GOLDBEES holdings follow the cash-call rules.

## Apply the selected stop-loss proceeds rule
In wait_for_rebalance mode, reserve net stop-loss proceeds outside spendable cash until the next scheduled rebalance, including a rebalance on the exit day. Include reserved cash and its configured cash return in daily NAV. Other forced exits must not spend this reserve. In replace_immediately mode, buy the next eligible unheld stock at the exit-day close with the net proceeds, subject to the configured cash-call, gold, ranking, filter, and entry rules. Never rebuy a same-day exited stock. Keep unused replacement proceeds in cash; do not use the no-candidate top-up fallback for stop-loss replacements.

## Block entries on the required pre-demerger exit day
When exit_before_demerger is enabled, exclude every symbol whose demerger exit date is the execution date from new-entry candidates, even if it was not already held. Apply the check before the position limit in scheduled rebalances and forced-exit replacement purchases, so the next eligible rank can fill the slot. Use the execution date for this restriction under both execution timing settings. Do not block these entries when the demerger rule is disabled.

## Use decision-date rank and raw close for weighted rebalances
RankWeighted uses 1 / the actual filtered rank on the decision date; do not renumber the ranks of selected stocks. PriceWeighted uses the unadjusted decision-date close, preserved before execution prices replace quote fields. Normalize positive factors across target holdings and restore target weights at every scheduled rebalance, subject to cash and trade rules. A retained holding without the required rank, weighting price, or execution quote keeps its shares; reserve its current value before allocating the rest. Fetch decision-date raw prices for DMA-protected holdings missing from the filtered list. Do not use an equal-cash top-up after rank or price allocation, because it changes the selected weights.

## Stock replacements must not add to existing holdings
The user permits added shares for scheduled weight adjustments, but replacement purchases must select the next eligible unheld stock. Apply this to stop-loss, BE-series, and demerger replacements. Remove both no-candidate and spare-cash top-up fallbacks from the replacement flow. Keep unused proceeds as cash if no unheld candidate qualifies. Cash-call allocation to defensive gold remains governed by its existing rules.

## Convert weighted targets to a cost-inclusive order budget once
Weight target differences are gross stock values. executeBuy expects a budget that includes buy charges. Multiply each target difference by (1 + buyCostRate), then apply the common cash scale before calling executeBuy. Do not scale for charges and then pass a gross-only budget, which makes executeBuy allow for the same charges again and distorts the allocation.

## Keep final backtest calculations within the worker memory limit
Read trade history in small lazy batches, ordered by date then ID, when calculating stock performance. Read allocation trades and prices in short date windows and carry holdings between windows. Do not load the full trade history before allocation: Eloquent hydration followed by price queries can exceed the 128 MB PHP worker limit even after metrics were saved.

## Count complete holding periods and save compact position results
A position starts when a stock holding changes from zero to positive and ends only at a full exit. Added shares and partial sales remain in that position; a later entry starts a separate position. Win rate, profit factor, average profit/loss, holding periods, and top lists use completed positions only; count breakeven positions in the denominator. Keep open holdings with separate realised and unrealised P&L, using average cost including charges. Stream trade history and retain only aggregate statistics, the top 20 winners/losers for each sort, and final holdings in stock_performance; never collect or store all completed positions. Value open holdings with the latest quote on a saved simulation date, without using later prices. Percentage P&L uses cumulative purchase cost including buy charges.

## Duplicate saved settings into a fresh pending backtest
Duplication copies every saved strategy setting into a new backtest owned by the same user. Clear run status, progress, timestamps, errors, and loaded result relations; do not copy trades, snapshots, or metrics, and do not queue a run. Use numbered Copy names within the owner scope. The UI opens the new settings with fresh Inertia page state and requires unsaved edits to be saved first.

## Require a current quote for each trade
User rule: if a stock does not trade on the execution date, skip its sale and retain its shares. For a new purchase, skip the unavailable stock before limiting candidates and try the next eligible unheld rank. Last-known prices may value retained holdings but must not create trade fills. Apply this across scheduled, cash-call, stop-loss, BE-series, and demerger exits.

## Apply the agreed delivery charge schedule
Use the user-supplied delivery schedule as NSE defaults: brokerage 0%, STT 0.1% on buys and sells, exchange 0.00307%, SEBI 0.0001% (Rs 10 per crore), GST 18% on brokerage plus exchange plus SEBI, and stamp duty 0.015% on buys only. Include buy STT in order budgets and the form estimate. Rates remain configurable. A default correction must not overwrite saved custom rates or completed results.

## Give pre-demerger exits priority over circuit restrictions
When exit_before_demerger is enabled, the scheduled pre-demerger sale overrides skip_circuit_trades, including a circuit hit on the exit date. This is intended behavior, not an engine defect. It does not override the separate rule requiring a valid positive execution-date quote: if the stock is not trading that day, keep the holding and record no sale or proceeds.

## Restore equal weights for all retained holdings only in rebalanced mode
EqualWeightRebalanced includes every retained holding in its target set, including DMA-protected stocks outside entry filters and the no-candidate cash fallback. EqualWeight keeps its existing non-rebalanced allocation behavior. A holding without a positive execution-date quote retains its shares and reserves its last valid market value and occupied slot before allocations to tradable stocks. Apply the quote reserve to every weighting method.

## Rebalance retained holdings when no new stock qualifies
Under NoCashCall, an empty eligible entry list preserves held stocks but must still apply the selected scheduled weighting method. EqualWeightRebalanced restores equal targets; inverse volatility and price weighting use their decision-date factors even when every holding fails entry filters. Rank weighting leaves holdings without ranks unchanged. Plain EqualWeight uses its existing cash allocation and does not restore target weights.

## Keep performance baselines and saved Sortino consistent
Start drawdown and Ulcer Index from NAV 100 before entry charges. Use the latest date when NAV retouches a peak; match the result-page drawdown episode by both peak and trough dates. First-month and first-year returns also start from NAV 100. Calculate Sortino once per run with the compounded daily cash target in both excess return and downside deviation, and divide downside squares by all daily returns. Save sortino_ratio; never recompute it from edited settings on page load. Old results remain null until rerun.

## Do not move a rebalance into an incomplete data period
Weekly and monthly holiday fallback to the last available trading day requires later-period trading data to prove the period has ended. The last imported date alone must not cause an early rebalance when the configured day has not arrived. Exact configured-day matches and monthly on-or-after matches keep their existing behavior.

## Reject insufficient backtest data before clearing results
A full user run needs at least two distinct selected-universe trading dates from its start date. Validate before deleting saved results or queueing the job; repeat validation inside the job. Metrics must throw for fewer than two snapshots or no elapsed date span, so incomplete runs cannot be marked completed. Show the start_date validation error for both run and retry requests.

## Show partial market cap allocation with unclassified holdings
Start allocation at the first saved date with both Nifty 100 and Midcap 150 membership coverage. Missing membership for one retained holding must not exclude the entire date: include its value as unclassified. Carry valid historical prices in bounded batches, including dates before common coverage. Do not infer a missing group from future quotes. Keep known ETFs separate, and default unclassified to zero when reading older saved allocation JSON. Allocation refresh must preserve trades, NAV and summary metrics.

## Assume delisting after 100 missing market trading days
The user requires a last-quoted-close exit after 100 consecutive market trading dates without a positive adjusted quote. This deliberately uses future data; label sales Assumed delisting and include the confirmation date. Use the simulation calendar and quotes outside index membership. Override DMA and circuits, charge normal costs, and follow forced-exit replacement and cash rules. Block same-day entries before rank limits; permit entry after quotes resume. Shorter gaps do not qualify. Keep price queries bounded and completed results unchanged until rerun.

## Share precomputed trading gaps across backtests
Store shared gaps in backtest_nse_trading_gaps, never per backtest. Keep one open gap per symbol plus confirmed historical gaps; track processed_through and requires_rebuild in the singleton state table. Run backtest:update-assumed-delistings after daily processing: scan once initially, then combine new quotes with saved open gaps. Rebuild after historical price or symbol changes. Publish records and state atomically. Backtests only read saved events and confirm 100 dates against their simulation calendar; reject stale data before clearing results. Preserve completed backtests until rerun.

## Keep daily gap updates incremental after price adjustments
Read new quotes once in small date windows using bnip_date_symbol_unique; carry last quote dates from saved open gaps. Full rebuilds use the same bounded scan. Gap detection depends on positive adjusted quotes, not their amounts: dividend and corporate-action adjustments invalidate gaps only if a processed positive quote rounds to zero. Check the stored decimal precision and save invalidation with the price change in one transaction. Historical date imports, symbol changes, and manual corrections still require rebuilding. Preserve the 100-market-day rule and show command progress.

## Keep cash for unfilled stock slots
Offer cash_call_if_not_enough_stocks for new selections. Plain Equal Weight divides available cash, including net sale proceeds, by empty portfolio slots; buy eligible unheld stocks and retain unused portions without topping up holdings. Rebalanced methods reserve the unfilled-slot portion of allocatable portfolio value and apply selected weights to the invested part; purchase budgets must preserve that reserve after charges and share rounding. For forced replacements between rebalances, divide exit proceeds by replacement slots, not by candidates found. Keep other cash modes, trade restrictions, and stop-loss cash reserves unchanged.

## Run a selected backtest NAV source before its dependent
DMA cash calls may use an owned independent NoCashCall backtest. Run the full source job to successful completion before dispatching the dependent job, using one captured market-data end date. Validate source ownership, no self/dependency chains, full DMA warmup and date coverage before clearing results. Source failures stop the dependent; protect in-use source settings and results from concurrent edits or reruns. Reuse the existing cash, gold, execution and assumed-delisting rules. The user accepts the retrospective assumed-delisting assumption. Save only the source selector and ID with the dependent settings: do not save copies of source settings, source run dates, or source NAV. Completed dependent results change only on rerun; show the agreed notice.

## Distinguish defensive gold from ranked ETF holdings
Track defensive gold as an in-memory holding purpose. GOLDBEES selected through ranking follows normal rank, filter, stop-loss, BE and weight rules. Cash-call gold purchases, and existing gold retained by a full gold rotation, make the holding defensive; its full exit clears that purpose. Defensive gold follows cash-call exits and reserves its value if a recovery sale is blocked. Never buy back a same-day forced exit through the gold allocation path.

## Keep Sharpe and K-ratio stable and within storage limits
Portfolio Sharpe uses the same compounded daily cash rate as simulation interest. Return null for volatility or log-NAV residuals at the six-decimal NAV rounding scale, with a floating-point floor. Round Sharpe and K-ratio to four decimals and return null for non-finite or out-of-range DECIMAL(10,4) results; an optional ratio must not fail a valid cash-only run or its dependent. Preserve measurable variation and ordinary ratio values. Saved metrics change only on rerun.

## Fill inverse-volatility exit slots before selecting entries
On a scheduled weighting rebalance, execute exits for actual missing or non-positive decision-date volatility before counting free slots and selecting entries. Preserve execution-quote and circuit restrictions, defensive gold and cash-call rules. Reserve the value and occupied slot of holdings whose sale cannot execute.

## Require complete index DMA data before running
For index-based DMA cash calls, require positive finite index closes, period minus one prior observations, and coverage of simulation dates and known warmup market dates. Reject missing data before clearing results and check again in the worker. Bound reads by the run end date; do not treat an unavailable signal as above DMA. NoCashCall and shortage cash mode need no index data.

## Handle independent queue dispatch failures
Catch queue dispatch failures for independent runs as well as source chains. Use the job failure handler to set Failed with an error so the user can retry; keep the existing run timestamp guards.

## Preserve large metrics and ignore Sortino rounding noise
Store CAGR and profit factor as nullable DOUBLE values, retaining four-decimal calculation rounding and finite large values. Non-finite CAGR is unavailable; show unavailable CAGR and derived metrics as missing, never zero. Keep money columns decimal. Apply the NAV rounding tolerance to Sortino downside deviation and its own DECIMAL(20,4) storage bound.

## Use standalone P/E when adjusted P/E cannot be stored
User decision: use ADJUSTED P/E first. If it is missing, non-numeric, non-finite, or outside DECIMAL(8,2) after rounding, use SYMBOL P/E. Save null if neither value can be stored. Keep valid zero and negative values. This replaces the earlier rule to use only ADJUSTED P/E. Keep market cap validation and CSV structure checks strict.

## Queue market-data validation and retain results until it passes
User decision: this replaces the earlier requirement to validate market data before queueing. Save and Run checks settings, ownership and run conflicts, then queues without reading price/index/gap history or deleting results. RunBacktestJob validates dates, shared gaps, index DMA and source NAV history before clearing that run’s trades, snapshots and metrics. A validation error marks the run Failed and keeps the saved result rows. The source worker captures the common end date and chains the dependent; the dependent starts only after the full source succeeds.

## Read long trading calendars without scanning all price rows
Use BacktestTradingCalendarAction for calendar reads. Full ranges select distinct dates through bnip_date_symbol_unique, then use a limited scalar membership lookup per date. Preserve selected-universe membership, zero-quote calendar dates and inclusive bounds. Use the limited backward scan for recent dates and DMA warmup. Do not restore a full DISTINCT membership-filtered price scan or a persistent cache that can become stale after imports.

## Allow one active backtest sequence per user
Lock the owner user row before checking active runs or changing saved backtest settings. Reject Save and Run before saving settings when that user has a Running backtest. Admit a source and its dependent together as one sequence; queue the source first and let its successful full job chain the dependent. Block changes and deletion for every Running backtest, and keep a completed source locked until its dependent finishes. Idle backtests may still be saved without running; different users may run concurrently. Keep market-data checks in the worker.

## Read final-day holdings quotes before searching history
For open-position valuation, query positive adjusted closes on the final saved simulation date first. Query historical last prices only for holdings missing a final-day quote. Both paths must restrict prices to saved simulation dates and never read a later date. Preserve all position and metric formulas.

## Check stock calendar data before importing a missing holiday index price
A backtest with index DMA needs pre-start history (period minus one earlier index closes). Calendar validation derives expected dates from stock price rows. Synthetic stock fixtures on a market holiday can therefore cause a missing-index error even when the index history is complete. Inspect the price rows for that date and repair confirmed bad source data with a backup and approval; do not fabricate index prices or disable the completeness check. Rebuild shared trading gaps after historical price or calendar corrections. Keep test fixtures in the isolated test database.

## Import REIT and InvIT market caps
The daily market cap import must accept RR and IV alongside the existing equity series. Match date, symbol and series, and save whole crores using the existing conversion. Historical trust market cap repairs use dated MCAP files from official NSE PR archives and verified symbol aliases; update only existing price rows. P/E remains source-driven and null when the NSE file has no value.

## Use date-specific F&O coverage and decile counts
F&O membership is stored in is_nifty_fno with daily source records in backtest_nse_fno_imports. Require source coverage for every market trading date in an F&O backtest before clearing saved results. F&O deciles use the source member_count for the selected date. Keep the approved fixed constituent counts for other universes. Price metrics continue to use the existing equity price calculations.

## Exclude the approved F&O special session
The user explicitly excludes 2024-03-02 from F&O history because that special session has trust quotes but no stock quotes in this database. Use BacktestNseFnoImport::IGNORED_DATES for the F&O import and backtest coverage check. Do not change the other price rows or ignore other missing trading dates automatically.

## Use price flags as the only F&O membership source
User decision: use backtest_nse_instrument_prices.is_nifty_fno for dated membership, coverage checks and decile counts. Count all flagged rows before metric filters. Use the indexed trading calendar for coverage. The backtest_nse_fno_imports table is removed; do not add a second membership source. Reject a daily import before changing flags if any source symbol lacks a price match. NseIndexEnum::FNO_IGNORED_DATES keeps the approved 2024-03-02 exclusion. This replaces the earlier source-record and source-count rules.

## Limit valuation-sort backtest start dates
User-approved limit: marketcap and price_to_earnings ranking require start_date >= 2024-03-01, confirmed as the first stored valuation date. Apply it to the main factor and enabled second/third factors only. Share dates through BacktestSortDateAction, show the minimum in settings, and validate both the request and worker before clearing saved results, including source/dependent runs. Do not change the saved date automatically or scan price history during Save and Run.

## Set the initial date when valuation sorting is selected
User correction: selecting marketcap or price_to_earnings in the main factor, or enabling/selecting either in a second or third factor, sets the form date to 2024-03-01. The user may then choose any date on or after that day. Keep a valid later saved date when reopening the form, and keep server/worker validation. The earlier instruction against automatic date changes applies to stored records, not this requested editor behavior; save only through the normal settings action.

## Load only required ranking and execution columns
Backtest candidate queries must select the stock identity, adjusted/raw prices, series, volatility and active ranking-factor columns rather than all price-table columns. Keep all eligibility filters in SQL and include each enabled factor before combined ranking. Execution-date price lookups need plain query-builder rows, not a second Eloquent model pool. A full read-only replay of backtest 28 reduced peak simulation memory from 192 MB to 86 MB with identical trades and NAV; keep the 128 MB worker limit and cover large candidate pools with a regression test.

## Load only required ranking and execution columns
Backtest candidate queries must select stock identity, adjusted/raw prices, series, volatility and active ranking-factor columns rather than all price-table columns. Keep eligibility filters in SQL and include each enabled factor before combined ranking. Execution-date quote lookups need plain query-builder rows, not a second Eloquent model pool. Keep bounded reads and regression tests even though the user has now raised worker memory to 512 MB; this supersedes the earlier 128 MB limit requirement.
