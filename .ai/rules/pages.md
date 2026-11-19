---
paths:
  - resources/js/Pages/InstrumentView.vue
  - resources/js/Pages/Welcome.vue
  - resources/js/Pages/MarketHealth.vue
  - resources/js/Pages/IndicesDashboard.vue
  - resources/js/Pages/NseIndexView.vue
  - resources/js/Pages/IndustryView.vue
---

# Pages

## Use category colours in the instrument summary
Use subtle purple for adjusted price, blue for market cap and P/E, and teal for median daily traded value. Colours identify categories, not valuation quality. Keep signed returns green for positive values, red for negative values, and neutral for zero or missing values. Keep labels, units and short descriptions visible so users do not depend on colour alone.

## Call guest screens and backtests examples
Use Example screens and Example backtests in user-facing headings, descriptions, empty states and read-only page labels. This is a display naming rule; existing public route names, configuration keys and access rules keep their technical names.

## Separate session breadth from longer market trends
The approved Market Health design starts with a dated session summary, then chart history and metric details. Keep daily advances and declines distinct from moving-average, annual-return and all-time-high participation. Keep all existing measures accessible and retain the selected metric and period when the index changes.

## Keep index comparison compact and retain search context
Put key indices above the sortable comparison rows. Keep every index accessible with pagination and a compact phone layout. Preserve search, sort, and result page on browser Back and the All indices link. Name-based categories were unreliable. Omit category pills and group labels on the dashboard and index detail pages. Discard legacy group values from remembered state and navigation URLs.

## Share the chart period across index measures
The approved detail page uses one chart with price, P/E, P/B and dividend yield tabs. Keep the chosen period across tabs and index changes. Max must include all stored dates even on a narrow phone screen. Set the minimum bar spacing accordingly. Yield uses an automatic percent scale. Market Health participation keeps its 0–100 scale. Show the data date and index points.

## Use the shared white page background
User decision: Market Health, the indices dashboard and index detail pages must use the same white page background as the rest of the site. Keep light shading inside controls, table headers and supporting panels only; do not add a separate gray page canvas.

## Show full monthly history without internal scrolling
Follow the [monthly returns display rule](components.md#show-full-monthly-history-without-internal-scrolling) on pages that show the returns table.

## Keep stock membership pills compact
Show the first six index pills by default on the stock page. When more memberships exist, show an accessible Show more button with the remaining count, and Show less after expansion. Preserve each dated constituent link and the existing index order. Do not expand the screener or backtest choices.

## Show current industry membership with completed-date prices
Link the stock industry label to /industries/{industry:slug}. The industry page lists all instruments assigned to that industry, including members without a quote. Use prices from the latest completed date and show missing metrics as unavailable; never use current industry labels as historical classifications. Retain search, sorting, pagination, and a phone layout. Industry coverage currently comes from the imported Nifty 1000 source, not the full NSE market.
