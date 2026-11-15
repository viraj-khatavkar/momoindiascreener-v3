---
paths:
  - 'tests/**'
---

# Tests

## Run separate test commands sequentially with the shared MySQL test database
The default test environment uses momoindiascreener-v3-test on MySQL. RefreshDatabase resets that shared schema, so concurrent independent php artisan test commands can delete each other's migrations and fixtures. Run test commands in sequence, pass all paths to one command, or use the supported parallel runner with separate databases.
