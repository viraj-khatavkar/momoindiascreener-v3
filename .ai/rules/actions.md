---
paths:
  - app/Actions/ApplyScreenFiltersAction.php
  - app/Actions/BuildHomePageDataAction.php
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
