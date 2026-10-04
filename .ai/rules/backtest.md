---
paths:
  - 'app/Actions/Backtest/**'
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
