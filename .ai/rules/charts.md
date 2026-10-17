---
paths:
  - 'resources/js/Components/Charts/Instrument*.vue'
---

# Charts

## Use stored daily metrics and visible-period medians for instrument charts
Instrument history charts share the adjusted price history request and use stored absolute_return_one_year, price_to_earnings, marketcap and rsi_one_year values. Keep nulls missing; zero and negative returns remain valid. Market cap values are already in crores. Calculate medians only from non-null values in the visible dates, including after zoom or pan. Keep one chart tab visible, preserve the selected time period across tabs, and omit P/E and market cap tabs for ETFs.
