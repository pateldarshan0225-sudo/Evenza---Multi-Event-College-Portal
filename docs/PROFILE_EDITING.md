# Student and organizer profile editing

Student profiles can edit name, login email, phone, enrollment number, college, gender, semester, and profile photo. Organizer profiles can edit college name, login email, phone, URL slug, university, address, and logo. Account status and verification controls remain administrative.

Both pages use the existing database columns. `includes/profile_helper.php` validates input, checks duplicate account emails and enrollment/slug conflicts, validates institution IDs, and updates only allowed fields for the authenticated account. Profile and password forms require a tab-scoped CSRF token. New passwords require the current password and are hashed before storage.

Optional images accept JPEG, PNG, and WEBP up to 2 MB and 8000 pixels per dimension. The server checks image contents and MIME, generates a random image filename, and stores it in the existing uploads/students or uploads/colleges directory. Profile writes use a transaction; failures remove only the newly uploaded file. Existing images are kept when no new image is selected and are not deleted automatically.

Preview and reset run in the browser before saving. Local user and college placeholders cover missing images. Student sidebar and dashboard images use the saved profile photo; organizer sidebar logos use the saved college logo. Updated name/email/image information is reflected in the current authenticated tab.

Validation performed without saving real account changes:

- 26 read-only profile, password, schema-binding, and upload checks: `tools/audit-profile-validation.php`.
- 24 desktop/mobile edit, preview, reset, image-loading, and protected-field checks: `tools/audit-profile-editor.cjs`.
- Existing organizer interaction checks and responsive profile-page checks.

No database migration or successful profile/password update was executed during development checks. The browser checks select existing local image assets for preview and never submit them.
