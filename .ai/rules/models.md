---
paths:
  - app/Models/BacktestNseInstrument.php
  - app/Models/NseIndex.php
---

# Models

## Exclude sector and industry classification features
User decision: do not display, import, export, or schedule updates for sector or industry classifications. The proposed local NSE lookup and CSV sync workflow was cancelled. Keep these features out unless the user explicitly changes this decision.

## Verify local index audits without the MySQL MRR read bug
On local MySQL 8.0.33 (macOS arm64), verify nse_indices date-range reads with FORCE INDEX (PRIMARY) or NO_MRR(nse_indices). A reproduced MRR read bug returns repeated IDs despite unique stored rows. Do not delete apparent duplicates based only on an MRR result. Confirm stored rows with a primary-key read. Related verified MySQL bug: https://bugs.mysql.com/bug.php?id=112642
