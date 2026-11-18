---
paths:
  - app/Enums/ScreenSortByOptionEnum.php
  - app/Enums/PlanEnum.php
---

# Enums

## Keep the established twelve-minus return names
User decision: retain the 12 minus 1 and 12 minus 2 option names. Current users understand these names. Do not rename them or change their calculation as part of the sorting audit.

## Keep past order amounts when plan prices change
PlanEnum prices apply to new orders only. The pricing page must use those same prices. Keep each existing orders.amount value unchanged, including pending orders. Payment checkout, callbacks, invoices and reports must use the stored order amount, not the current plan price. Do not backfill or recalculate past order values when prices change.
