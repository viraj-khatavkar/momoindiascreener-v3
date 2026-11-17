---
paths:
  - 'tests/**'
---

# Tests

## Run separate test commands sequentially with the shared MySQL test database
The default test environment uses momoindiascreener-v3-test on MySQL. RefreshDatabase resets that shared schema, so concurrent independent php artisan test commands can delete each other's migrations and fixtures. Run test commands in sequence, pass all paths to one command, or use the supported parallel runner with separate databases.

## Check partial backtest price models with missing-column checks
Keep missing-attribute checks enabled in simulation and ranking-column tests, restoring the previous setting afterward. Cover every ScreenSortByOptionEnum field as the primary, second and third active factor. SQL filter fields need not be returned, but ranking and execution reads must have the selected fields; preserve eligibility, rank order and trade behavior when reducing columns.
