---
paths:
  - resources/js/Components/ScreenCard.vue
  - resources/js/Components/ReturnsHeatmap.vue
---

# Components

## Share screen cards between home and the screens index
Both Welcome.vue and Screens/Index.vue use ScreenCard.vue for the same purple card, name, index, ranking factor and order. Personal cards open the edit page; public cards open the public results page. Keep labels, keyboard focus and long-name wrapping consistent on both pages.

## Show full monthly history without internal scrolling
User decision: show every available return year by default, with all 12 months and the annual or YTD value. Remove the recent-five-year selector and fixed-height or horizontal scroll containers. Use a full-width desktop table and year grids on smaller screens, with normal page scrolling only. This replaces the earlier scrolling returns-table design.
