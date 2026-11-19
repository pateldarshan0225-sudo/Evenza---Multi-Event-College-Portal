# Website audit — 24 October 2026

Database records and schema were not modified. No registration, payment, upload,
delete, cancellation, profile update or password change was submitted during testing.

## Changes made

- Added an accessible mobile/tablet navigation toggle and links to the public header.
- Fixed horizontal overflow from registration gutters, college/event pagination,
  dashboard headers, filters and flex/grid content.
- Kept long portal navigation bars horizontally scrollable on mobile, with the
  account dropdown outside the scrolling region.
- Added input width constraints, wrapping for long dropdown content, visible
  keyboard focus and mobile footer form wrapping.
- Corrected the unclosed script tag and missing viewport in the legacy Admin header.
- Preserved the authenticated student's name/email in the shared student sidebar.
- Stored existing Bootstrap 5.3.3, Bootstrap Icons 1.11.3, jQuery 3.7.1,
  Select2 4.1.0-rc.0 and Chart.js 4.4.4 dependencies locally. Public and portal
  pages now use those copies. External fonts/images still require connectivity.
- Preserved pre-existing workspace changes.

## Verification

- 145 browser layout cases: 10 public pages and 19 protected portal pages at
  320, 375, 768, 1024 and 1440px. Final cases have no page-level horizontal
  overflow, detected out-of-bounds content outside intentional scroll regions,
  or rendered PHP warnings/fatal errors.
- Protected pages were rendered by a CLI fixture using existing account IDs,
  synthetic session identity, and a MySQL `READ ONLY` transaction. Chrome
  intercepted document requests to display these responses. This verifies
  protected GET rendering and queries, not a real authenticated browser login.
- 47 HTTP checks passed: public/empty-state/detail requests, unauthenticated
  redirects on 19 protected pages, and public local assets.
- Mobile navigation, signup dropdown bounds, password visibility, invalid-email
  feedback and college live search passed browser interaction checks.
- All database tables/columns were inspected inside a read-only transaction.
- PHP syntax was checked across application and audit scripts.

Evidence: `tools/ui-audit-results.json`, `tools/portal-audit-results.json`,
`tools/http-audit-results.json`, `tools/flow-audit-results.json`, and
`tools/register-college-mobile.png`.

## Backend findings requiring follow-up

The backend cannot be described as fully correct or production-ready. The
following existing business/security behavior was reviewed but not changed by
this UI repair. References are relative to the project root.

| Priority | Location | Finding |
| --- | --- | --- |
| Critical | `Frontend/register_university.php`, successful POST handler | Public university signup grants an `admin` session using a university ID. There is no corresponding admin account creation, and university passwords are collected but never stored. University IDs can overlap admin IDs, including password-change targets. Separate university authorization and account provisioning are needed. |
| Critical | `Frontend/event_detail.php`, registration transaction | Paid event registration creates an approved registration, active entry pass and a fabricated paid UPI payment without verifying an actual payment. A verified payment workflow is needed before issuing paid entry passes. |
| High | `assets/json/file-upload.php` | Template upload endpoint has no authentication, file type/size validation or safe filename generation. It should be removed or secured before deployment. No uploads were attempted. |
| High | Registration, Admin account creation and profile password handlers | Several handlers save plaintext passwords. Login also accepts legacy plaintext. New password writes should use `password_hash`; existing account migration needs a deliberate plan. No password records were read or changed. |
| High | `Frontend/login.php` | Login does not enforce inactive admin/college or inactive/unverified student status. Role guards trust the stored session without refreshing account status. |
| High | Admin delete/cancel handlers and mutation forms | Several destructive actions use GET requests, and no CSRF token checks were found. Actions should use protected POST requests with server-side token verification. No such actions were invoked. |
| High | College/university registration logo handling | Upload checks accept SVG based on filename extension, do not enforce the advertised 2MB limit or validate decoded image content, and move files before other form validation completes. |
| Medium | `Frontend/register.php` | New students are automatically marked verified; confirm this matches the intended institution verification process. |
| Medium | `Frontend/event_detail.php` | Team registration initially adds only the leader even when the event minimum team size is larger. Completion and approval must enforce team limits. Duplicate-registration checks also need concurrency protection. |
| Medium | `tab_auth.php`, `assets/js/tab-auth.js` | Normal navigation uses a shared cookie for tab identity; true isolation is not guaranteed during concurrent tab navigation. Browser session-storage failures and cross-origin AJAX header handling also need coverage. |
| Medium | `Frontend/Footer.php` | Newsletter subscription only displays a JavaScript success alert; it does not persist a subscription. |
| Medium | Multiple POST handlers | Raw database exception details are displayed to users. Return a generic error and log technical details privately. |

## Limits and rerunning

Successful writes, payment processing, upload handling and destructive actions
were intentionally not exercised because the database must remain unchanged.
No claim is made that every backend mutation or every possible UI state passes.

Run `python tools/audit-http.py` and
`C:\wamp64\bin\php\php8.3.28\php.exe -d xdebug.mode=off tools/audit-backend.php`
for repeatable read-only checks. Browser scripts require a dedicated local
headless Chrome instance exposing CDP on port 9228. Use a temporary browser
profile, run `node tools/audit-ui.cjs`, then
`node tools/audit-ui.cjs --protected` and `node tools/audit-flows.cjs` sequentially.
Do not run these browser scripts against a browser with personal logged-in tabs.
