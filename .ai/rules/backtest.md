---
paths:
  - 'app/Actions/Backtest/**'
---

# Backtest

## Persist daily market cap allocation
Store market cap percentages on the daily NAV snapshots once per backtest run. Results pages must read saved values; do not recalculate the full price and trade history on a page load. Fill old results with backtest:backfill-market-cap-allocation; use --refresh after historical data corrections. Mark dates without sufficient index coverage as calculated, so they are not processed repeatedly. Large cap uses historical Nifty 100 membership, mid cap uses historical Nifty Midcap 150 membership, and other stocks are small cap. Start only when both indices have historical membership data; percentages use total portfolio value with cash and ETFs separate.

## Import daily valuations in the agreed units
From 2024-03-01, daily process runs require marketcap.csv and price_to_earnings.csv. Save marketcap as integer crores: divide raw rupees by 10,000,000 and round to the nearest whole crore. Keep the existing integer column. Save ADJUSTED P/E, not SYMBOL P/E, in price_to_earnings. Match market cap by date, symbol, and series; P/E has no series column. Skip market cap totals and preserve earlier dates.
