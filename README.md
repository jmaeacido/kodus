<<<<<<< HEAD
# KODUS

KliMalasakit Online Document Updating System.

## Full documentation

Detailed application documentation is available at [`docs/KODUS_DOCUMENTATION.md`](C:\laragon\www\kodus\docs\KODUS_DOCUMENTATION.md).

## Beginner-friendly structure

- `config.php` loads environment variables, applies the shared security check, and opens the database connection.
- `security.php` keeps small security-focused helpers such as CSRF validation, rate limiting, cookie settings, and upload MIME detection.
- `auth_helpers.php` contains session, remember-me, redirect, and shared access-control logic.
- `notification_helpers.php` contains reusable email and audit-log helpers for login and 2FA notifications.
- `header.php` now acts as a thin bootstrap layer for pages: it starts the session flow, applies shared auth rules, and outputs the common page shell.

## Main entry points

- `index.php` shows the login screen.
- `login.php` handles standard sign-in.
- `verify_2fa_code.php` finishes two-factor sign-in.
- `settings.php` manages user profile and security settings.
- `pages/`, `admin/`, `inbox/`, `crossmatch/`, and `deduplication/` contain feature pages and feature-specific handlers.

## Maintenance tips

- Put reusable business logic in helper files instead of copying it into page scripts.
- Keep page files focused on request handling and view markup.
- Prefer the shared helpers before adding new session, redirect, email, or security code.
- When adding a new POST endpoint, reuse `security_require_method()` and `security_require_csrf_token()`.

## Versioning

- App version metadata lives in `app_meta.php`.
- Run `php scripts/update_app_version.php` at the end of the day to bump the patch version only when relevant source changes were detected since the last release date.
=======
# kodus
KliMalasakit Online Document Updating System
>>>>>>> d1ed1b3fe0bb98033676b293b4e0f261964306f6
