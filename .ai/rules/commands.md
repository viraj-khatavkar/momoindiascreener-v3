---
paths:
  - app/Console/Commands/ImportNseIndicesCommand.php
---

# Commands

## Allow zero closing values for Nifty50 Dividend Points
Nifty50 Dividend Points resets to zero each year after the March derivatives settlement. Accept a finite, non-negative close for the normalized slug nifty50-dividend-points. All other index closes must remain strictly positive. Missing, non-numeric, negative and out-of-range closes must still fail before any records are saved. Source: https://archives.nseindia.com/content/indices/Nifty50_Dividend_Points.pdf
