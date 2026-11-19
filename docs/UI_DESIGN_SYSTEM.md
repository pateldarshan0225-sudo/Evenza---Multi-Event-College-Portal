# Evenza interface system

The public website and Admin, Organizer, and Student workspaces share `assets/css/evenza-premium.css`. This stylesheet controls presentation; routes, queries, authorization, submission handlers, and database structure remain unchanged.

## Visual direction

- Warm ivory public pages, pale stone workspaces, charcoal text, forest green workspace navigation, and restrained gold accents.
- Locally served variable Inter, weights 100–900, with system font fallback and `font-display: swap`.
- Three token layers: raw palette (`--ev-ink-950`), semantic roles (`--ev-text`), and component roles (`--ev-button-bg`).
- Consistent 20px cards, 12px primary buttons, subtle borders, quiet shadows, and visible keyboard focus.
- Responsive type, wrapping content, horizontally scrollable data tables, and scrollable labelled navigation on small screens.
- Reduced-motion preference disables decorative transitions and animations.
- Native single-select options use `appearance: base-select` inside a feature query, with rounded menus, green selected states, checkmarks, and viewport-limited scrolling. Browsers without customizable-picker support keep their native picker and the shared control typography. Searchable Select2 menus and multi-select lists receive the same visual theme.
- Dropdown styling adds no JavaScript dependencies and preserves native validation and existing change/submit handlers. `tools/audit-select-options.cjs` checks native options and keyboard selection in public registration, all three portals, and an organizer modal without submitting forms.

## Shared integration

Public pages load the system through `Frontend/Header.php`. Portal pages load it in their document head. Shared sidebars contain actual navigation labels and keyboard-operable account menu buttons. Existing active-route logic is preserved.

The homepage photograph is served locally from `assets/images/brand/campus-community.jpg`; its original source is https://images.unsplash.com/photo-1523240795612-9a054b0db644. Homepage avatars use bundled illustrations. Student placeholders and missing organizer logos use local assets, avoiding a dependency on a remote avatar service.

## Validation — 25 October 2026

- 53 PHP files passed syntax validation.
- 150 layout checks: 10 public pages and 19 protected pages at 320, 375, 768, 1024, and 1440px, plus an existing event detail page at all five widths. No remaining viewport expansion, horizontal page overflow, or rendered PHP errors were found in these checks.
- 58 read-only HTTP checks passed.
- 17 event filter interaction checks passed.
- College filter placement at five widths, search, keyboard selection, and empty state checks passed.
- 8 mobile menu and registration form interaction checks passed.
- 18 workspace checks passed for local typography, labelled navigation, keyboard account menus, and create-event dialog placement.
- Desktop and mobile screenshots of the homepage and all three dashboards were visually reviewed.

Protected page fixtures use `START TRANSACTION READ ONLY` and rollback. No successful registration, event creation, payment, destructive action, or database migration was performed. These checks validate the UI paths exercised; they do not establish that every possible workflow or dataset is defect-free.

## Re-running checks

Run a dedicated headless Chrome instance with remote debugging on port 9228. Then use:

```text
node tools/audit-ui.cjs
node tools/audit-ui.cjs --protected
node tools/audit-premium.cjs
node tools/audit-event-filters.cjs
node tools/audit-flows.cjs
node tools/audit-college-filter.cjs
python tools/audit-http.py
```

The college filter audit closes the dedicated browser, so run it last. Individual pages can be checked with `--page=Frontend/event_detail.php?id=EXISTING_ID --widths=320,375,768,1024,1440`. JSON results and review screenshots are stored under `tools/`.
