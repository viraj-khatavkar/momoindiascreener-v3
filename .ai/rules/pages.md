---
paths:
  - resources/js/Pages/InstrumentView.vue
  - resources/js/Pages/Welcome.vue
  - resources/js/Pages/MarketHealth.vue
  - resources/js/Pages/IndicesDashboard.vue
  - resources/js/Pages/NseIndexView.vue
---

# Pages

## Use category colours in the instrument summary
Use subtle purple for adjusted price, blue for market cap and P/E, and teal for median daily traded value. Colours identify categories, not valuation quality. Keep signed returns green for positive values, red for negative values, and neutral for zero or missing values. Keep labels, units and short descriptions visible so users do not depend on colour alone.

## Call guest screens and backtests examples
Use Example screens and Example backtests in user-facing headings, descriptions, empty states and read-only page labels. This is a display naming rule; existing public route names, configuration keys and access rules keep their technical names.

## Separate session breadth from longer market trends
The approved Market Health design starts with a dated session summary, then chart history and metric details. Keep daily advances and declines distinct from moving-average, annual-return and all-time-high participation. Keep all existing measures accessible and retain the selected metric and period when the index changes.

## Keep index comparison compact and retain search context
The approved index dashboard puts key indices above grouped, sortable comparison rows. Keep every index accessible with pagination and a compact phone layout. Preserve search, group, sort and result page on browser Back and the All indices link.

## Share the chart period across index measures
The approved detail page uses one chart with price, P/E, P/B and dividend yield tabs. Keep the chosen period across tabs and index changes. Max must include all stored dates even on a narrow phone screen; the chart minimum bar spacing must allow this. Yield uses an automatic percent scale, while Market Health participation keeps its 0–100 scale. Show the data date and index points, and keep return-year labels visible during horizontal scroll.

## Use the shared white page background
User decision: Market Health, the indices dashboard and index detail pages must use the same white page background as the rest of the site. Keep light shading inside controls, table headers and supporting panels only; do not add a separate gray page canvas.

## Show full monthly history without internal scrolling
User decision: show every available return year by default, with all 12 months and the annual or YTD value. Remove the recent-five-year selector and fixed-height or horizontal scroll containers. Use a full-width desktop table and year grids on smaller screens, with normal page scrolling only. This replaces the earlier scrolling returns-table design.

## Do not classify indices with category pills
User decision: remove the dashboard category pills and the related group labels from dashboard rows and index details. The name-based classification was not reliable. Keep search, sorting and pagination; discard legacy group values from remembered state and navigation URLs. This replaces the earlier grouped-comparison design.
