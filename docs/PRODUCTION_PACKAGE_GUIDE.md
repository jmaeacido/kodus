# KODUS Production Package Guide

Date: 2026-04-07

This guide lists what should go to the company server and what should stay out of the production deployment package.

## Deploy

These are the app areas that should normally be included in the production package:

- `admin/`
- `cdn.datatables.net/`
- `cdn.jsdelivr.net/`
- `cdnjs.cloudflare.com/`
- `code.jquery.com/`
- `crossmatch/`
- `deduplication/`
- `dist/`
- `fonts.googleapis.com/`
- `fonts.gstatic.com/`
- `implementation-status/`
- `inbox/`
- `mebis-consolidator/`
- `pages/`
- `plugins/`
- `storage/`
- `vendor/`
- `.htaccess`
- `ajax_login.php`
- `app_meta.php`
- `audit_helpers.php`
- `auth_helpers.php`
- `base_url.php`
- `composer.json`
- `composer.lock`
- `config.php`
- `contact.php`
- `delete_account.php`
- `disable_2fa.php`
- `env_helpers.php`
- `export_excel.php`
- `export_style_helpers.php`
- `favicon.ico`
- `forgot-password.php`
- `fund_monitoring_helpers.php`
- `get_data.php`
- `header.php`
- `home.php`
- `index.php`
- `live_refresh.php`
- `login.php`
- `logout.php`
- `mail_config.php`
- `notification_helpers.php`
- `page_loader.php`
- `password_policy_helpers.php`
- `payout.php`
- `payout_config_helpers.php`
- `project_targets_helpers.php`
- `project_variable_helpers.php`
- `recover-password.php`
- `register.php`
- `remove_photo.php`
- `reset-password.php`
- `restore_user.php`
- `restore_users.php`
- `role-change-status.php`
- `role_change_helpers.php`
- `save_profile_settings.php`
- `save_theme_preference.php`
- `security.php`
- `select_year.php`
- `send-reset-link.php`
- `begin_2fa_setup.php`
- `regenerate_recovery_codes.php`
- `two_factor_helpers.php`
- `send_contact.php`
- `send_legacy_password_reminders.php`
- `send_login_notification.php`
- `settings.php`
- `sidenav.php`
- `theme_helpers.php`
- `update-password.php`
- `verify-2fa.php`
- `verify_2fa_code.php`

## Do Not Deploy

These should be excluded from the production package:

- `.env`
- `.env.example`
- `.git/`
- `.tmp.driveupload/`
- `artifacts/`
- `docs/`
- `screenshots/`
- `scratch/`
- `sql/`
- `composer-setup.php`
- `composer.phar`
- `info.php`
- `phpinfo.php`
- `__diag_password_policy.php`
- `__https_debug.php`
- `kodus-key_pair.pem`
- `kodus-key_pair.ppk`
- `kodus_db.sql`
- any temporary exports, test spreadsheets, or local working files

Examples from the current workspace that should not be deployed:

- `Beneficiaries_Template.xlsx`
- `Caraga RRP-CFTW Tracker_CY2026 live file.xlsx`
- `LIBJO, PDI (ECT PAYROLL).xlsm`
- `Regional Program Implementation Plan.xlsx`

If any of those files are required for user-facing downloads, move them into a reviewed production assets location first and confirm they do not contain sensitive data.

## Deploy With Caution

These paths may be needed at runtime, but should be reviewed before go-live:

- `storage/`
- `crossmatch/uploads/`
- `deduplication/uploads/`
- `deduplication/logs/`
- `inbox/uploads/`
- `pages/uploads/`

Production rules for these directories:

- keep write access limited to the web server account only where needed
- do not place secrets there
- do not allow script execution there
- clean out old test files before go-live
- move logs outside the web root if possible

## Recommended Packaging Rule

If you are preparing a zip or deployment artifact, build it from an allowlist mindset:

1. Include the application code and runtime assets only.
2. Exclude secrets, dumps, docs, screenshots, scratch work, installers, and diagnostics.
3. Add the production `.env` separately on the server instead of bundling it in the package.

## Pre-Upload Sanity Check

Before copying the package to the company server, verify that the package does not contain:

- any `.env` file
- any `.sql` dump
- any `.pem`, `.ppk`, or `.key` file
- any `phpinfo` or debug endpoint
- any `docs/`, `scratch/`, `screenshots/`, or `artifacts/` directory

## Recommended Server-Side Follow-Up

After deployment:

- create the production `.env` directly on the server
- set secure file permissions
- confirm blocked files return `403` or `404`
- run the smoke tests from [`APACHE_PRODUCTION_CHECKLIST.md`](/C:/laragon/www/kodus/docs/APACHE_PRODUCTION_CHECKLIST.md)
