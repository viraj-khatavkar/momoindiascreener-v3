---
paths:
  - 'resources/js/Pages/Screens/**'
---

# Screens

## Separate saved screen results from draft rules
Guests see only Screen::PUBLIC_SCREENS. Signed-in users see their own screens before examples. Open existing screens on Results; open newly created or copied screens on Rules. Summaries and results use saved props. Warn about unapplied edits, keep drafts on history navigation, and block duplication and exports until edits are saved or discarded. Keep display sorting separate from screen ranks. Use Inertia client-side replace for tab URLs so remembered state cannot overwrite the URL.

## Keep the original filter order inside the separate Rules tab
User keeps the separate Results and Rules tabs but requests the old filter order, using backtest settings as the reference. Put the main universe and sort controls first, then General, MA, EMA, Away from High, Positive Days, Circuits, Market Cap, P/E, Series, Beta, Price Range, Multi-Factor Combined Ranking, Historical Ranks, and Custom Filters. Apply the same order to read-only saved rules. Combined factors, series and historical settings belong in their prior accordion positions; preserve all saved settings and dirty-state behavior.
