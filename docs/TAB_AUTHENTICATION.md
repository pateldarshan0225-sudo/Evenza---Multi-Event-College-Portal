# Per-tab authentication

The PHP session cookie identifies the browser session. The account within that session is selected by an explicit `evenza_tab_id` carried in the `X-Tab-ID` header, form field, or URL query, in that order. The old shared `evenza_tab_id` cookie is ignored.

Previously, focusing or clicking another tab overwrote that cookie. Refreshing an Organizer page could therefore select the Admin context, fail the Organizer guard, and redirect through login to the Admin dashboard.

`assets/js/tab-auth.js` keeps the tab identifier in sessionStorage, prepares internal links and forms, and attaches the header to internal fetch/XHR requests. Programmatic `form.submit()` is covered. External requests do not receive the tab header. All three portal layouts load the helper.

A document navigation without explicit context first receives a small, account-free bootstrap page. It reads sessionStorage and reloads the same URL with the correct identifier before PHP renders any account data. Role guards and login/logout redirects preserve this context. Logging out removes only that tab's authentication entry.

This change does not modify database records or schema. Authentication still requires an entry in the server-side PHP session; a tab identifier by itself does not authenticate a browser.

## Regression check

With WAMP and a dedicated headless Chrome on port 9228, run `node tools/audit-tab-auth.cjs`. It creates a disposable PHP session containing three existing account IDs obtained through read-only queries, uses a separate browser context, and removes both fixtures afterward. It does not change credentials, submit registrations, or create database records.

The 14 checks cover cross-tab cookie interference, repeated refreshes, recovery of old URLs, role mismatch redirects, links, programmatic GET forms, cookie-only rejection, and independent logout. Additional overview, workspace, registration UI, event filter, PHP syntax, and unauthenticated HTTP checks pass after the change.
