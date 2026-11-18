---
paths:
  - 'resources/js/**'
---

# Js

## Require deliberate wheel input for every chart
Create Lightweight Charts through utils/createInteractiveChart.ts, including indicator panes. Normal mouse-wheel or trackpad scrolling must remain page scrolling; only Ctrl or Command plus wheel may zoom or pan the chart. Keep vertical touch dragging available to the page and preserve pinch zoom, range controls, chart synchronization and reset actions. Use the shared ChartInteractionHint. Attach wheel handling to the chart-owned element so removing or rebuilding a chart cannot leave listeners on its parent or the document.
