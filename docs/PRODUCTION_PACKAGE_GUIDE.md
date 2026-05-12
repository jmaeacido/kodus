# KODUS Production Package Guide

Last reviewed: 2026-05-12

This guide lists what should normally be deployed to production and what should stay out of a production package.

## Deploy

Deploy reviewed application code, bundled assets, Composer dependencies, and required runtime scaffolding:

- `admin/`
- `crossmatch/`
- `deduplication/`
- `dist/`
- `implementation-status/`
- `inbox/`
- `messenger/`
- `mebis-consolidator/`
- `mebis-lgu-template/`
- `notifications/`
- `pages/`
- `plugins/`
- `socket/` if the optional bridge service is being deployed or documented with the package
- bundled local CDN/font asset folders used by the app
- `vendor/` or install it on-server through Composer
- root PHP entry points and helpers, including authentication, security, notification, audit, role, profile, theme, two-factor, SSO, socket, fund monitoring, project target, project variable, payout, and base URL helpers
- `composer.json` and `composer.lock`
- `.htaccess` only when deploying under Apache; Nginx deployments still need server config

Important root/helper files currently include:

- `app_meta.php`
- `config.php`
- `security.php`
- `auth_helpers.php`
- `audit_helpers.php`
- `app_notification_helpers.php`
- `notification_helpers.php`
- `socket_helpers.php`
- `env_helpers.php`
- `db_stmt_helpers.php`
- `base_url.php`
- `header.php`
- `sidenav.php`
- `home.php`
- `index.php`
- `login.php`
- `ajax_login.php`
- `logout.php`
- `register.php`
- `settings.php`
- `select_year.php`
- `password_policy_helpers.php`
- `two_factor_helpers.php`
- `sso_helpers.php`
- `role_change_helpers.php`
- `profile_review_helpers.php`
- `profile_completion_helpers.php`
- `meb_change_history_helpers.php`
- `fund_monitoring_helpers.php`
- `project_targets_helpers.php`
- `project_variable_helpers.php`
- `payout_config_helpers.php`
- `export_style_helpers.php`
- custom error pages `400.php` through `504.php`

## Create on Server, Do Not Bundle

- production `.env`
- database dumps/backups
- private keys
- SMTP credentials
- SSO client secrets
- Socket bearer tokens
- production upload/output data
- production logs

## Do Not Deploy

Exclude:

- `.git/`
- `.env`
- SQL dumps and database backups
- private keys such as `.pem`, `.ppk`, `.key`
- local diagnostics such as `phpinfo.php`, `info.php`, temporary schema checks, debug scripts, and scratch cleanup files
- `docs/` unless the deployment policy explicitly allows internal documentation on the server; if deployed, block it from web access
- `scratch/`, `artifacts/`, `screenshots/`, temporary upload folders, and local working files
- test spreadsheets, live-file copies, payroll files, or other operational data unless formally reviewed, scrubbed, and required as production assets

Examples of files that should be reviewed or excluded if present:

- `Beneficiaries_Template.xlsx`
- `Caraga RRP-CFTW Tracker_CY2026 live file.xlsx`
- `Regional Program Implementation Plan.xlsx`
- payroll or LGU workbook samples
- ad hoc `tmp_*.php` scripts

## Deploy With Caution

These paths may be required at runtime but must be reviewed before go-live:

- `storage/`
- `crossmatch/uploads/`
- `deduplication/uploads/`
- `deduplication/logs/`
- `inbox/uploads/`
- `pages/uploads/`
- `mebis-consolidator/outputs/`
- `mebis-lgu-template/jobs/`
- `mebis-lgu-template/outputs/`
- profile export job/output directories used by `pages/profile_export_*`

Production rules:

- keep write access limited to the web/PHP user
- block script execution
- clear old test files
- move logs outside web root when possible
- treat generated files as sensitive if they contain beneficiary or operational data
- include required runtime directories in backup scope only after confirming retention rules

## Packaging Rule

Use an allowlist mindset:

1. Include reviewed app source, runtime assets, and Composer dependencies.
2. Exclude secrets, dumps, keys, diagnostics, docs, screenshots, scratch work, and operational datasets.
3. Install production `.env` directly on the server.
4. Recreate writable runtime directories with correct permissions.
5. Confirm web-server deny rules before exposing the app.

## Pre-Go-Live Checks

- `composer install --no-dev --optimize-autoloader` completed.
- `.env` exists only on the server and is not browser-accessible.
- DB connection works.
- PHP CLI is available for background workers.
- SMTP works.
- Upload directories are writable and cannot execute scripts.
- MEB import, deduplication, crossmatch, profile export, and MEBIS worker flows start correctly.
- Audit logs record state-changing requests.
- Role checks work for admin/editor/AA/user.
- Maintenance mode and custom error pages work.
- Backup and restore process is documented by the host/system owner.
