---
paths:
  - 'resources/js/Pages/Backtests/**'
---

# Backtests

## Keep backtest result comparisons and history consistent
Use the selected benchmark for the chart, CAGR comparison and monthly return details. Show CAGR differences in percentage points only when both series cover the same dates; do not compare partial benchmark months with full strategy months. Monthly strategy returns start from NAV 100 so initial charges remain included. Full-history charts need minBarSpacing below the library default, and linked charts must sync dates rather than bar indexes. Result-page changes must preserve saved metric and execution formulas.
