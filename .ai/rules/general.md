---
paths:
  - '**'
---

# General

## Use the ChatGPT Chrome extension for browser access

Get the user's agreement before connecting to Chrome. A request to use the Chrome plugin authorizes the ChatGPT Chrome extension for that task. Keep browser control inside the extension, leave existing tabs open, and report a connection failure if it is unavailable. Use read-only HTTP for ordinary data downloads.

In NodeREPL, import `setupBrowserRuntime` from the absolute path to `~/.codex/plugins/cache/openai-bundled/chrome/<installed-version>/scripts/browser-client.mjs` and keep the runtime it returns. Find the entry with `type === 'extension'` and `family === 'chrome'` in `await runtime.browsers.list()`. Select it with `const browser = await runtime.browsers.get(entry.id)`, name the session with `await browser.nameSession('🔎 Task')`, and read `await browser.documentation()`. Pass the matching entry from `await browser.user.openTabs()` to `await browser.user.claimTab(tab)`, or use `await browser.tabs.new()` for a new tab.

## Check the code before connecting to Forge
Check local code, configuration, and tests first when explaining how the application works. Connect to Forge for a requested live check or when the answer depends on server state that local checks cannot establish.
