---
paths:
  - '**'
---

# General

## Do not control or close the user's Chrome from terminal tools
The user reported that terminal Chrome automation closed their open browser, twice. Do not launch, attach to, control, terminate, or close Chrome through terminal commands, Playwright Chrome channels, or browser scripts. Use read-only HTTP downloads for data work. A browser plugin requires an explicitly agreed connection before use. Never interfere with the user's existing browser session.
