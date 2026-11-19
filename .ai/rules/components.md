---
paths:
  - resources/js/Components/ScreenCard.vue
  - resources/js/Components/ReturnsHeatmap.vue
---

# Components

## Share screen cards between home and the screens index
Both Welcome.vue and Screens/Index.vue use ScreenCard.vue for the same purple card, name, index, ranking factor and order. Personal cards open the edit page; public cards open the public results page. Keep labels, keyboard focus and long-name wrapping consistent on both pages.

## Show full monthly history without internal scrolling
Show every available return year by default, with all 12 months and the annual or YTD value. Use a full-width table on desktop and year grids on smaller screens. Use normal page scrolling only.
