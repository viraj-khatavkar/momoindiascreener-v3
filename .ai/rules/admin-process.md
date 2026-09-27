---
paths:
  - 'app/Actions/AdminProcess/**'
---

# Admin Process

## Run valuation imports before ETF marking
From 2024-03-01, put Import market cap and then Import price to earnings immediately after Apply corporate action adjustments and before Mark ETFs. Keep existing step IDs, results, and output when changing the order of unfinished runs.
