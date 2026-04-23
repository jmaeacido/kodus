# KODUS Application Documentation

Date: 2026-04-08

## 1. Overview

KODUS stands for **KliMalasakit Online Document Updating System**. It is a PHP and MySQL web application used for:

- user registration, login, password reset, and authenticator-based 2FA with recovery codes
- beneficiary masterlist management and validation
- incoming, outgoing, and payout tracking
- calendar scheduling and event reminders
- implementation-status planning and accomplishment tracking
- internal messaging with inbox threads and attachments
- crossmatch and deduplication utilities for dataset quality checks

KODUS is implemented as a classic PHP page-based application with shared helper files rather than a formal MVC framework.

## 2. Core stack

- PHP with procedural page controllers
- MySQL or MariaDB via `mysqli`
- AdminLTE 3 and Bootstrap 4
- jQuery and DataTables
- Chart.js
- PHPMailer
- PhpSpreadsheet
- `vlucas/phpdotenv`
- `pragmarx/google2fa`
- `bacon/bacon-qr-code`

PHP dependencies declared in [`composer.json`](C:\laragon\www\kodus\composer.json):

- `phpoffice/phpspreadsheet`
- `phpmailer/phpmailer`
- `vlucas/phpdotenv`
- `pragmarx/google2fa`
- `bacon/bacon-qr-code`

Node tooling still exists in [`package.json`](C:\laragon\www\kodus\package.json), but it is optional and only needed when rebuilding frontend assets.

## 3. Important files

- [`config.php`](C:\laragon\www\kodus\config.php): environment loading, DB connection, schema helpers, and runtime bootstrap
- [`header.php`](C:\laragon\www\kodus\header.php): authenticated page shell and shared page bootstrap
- [`security.php`](C:\laragon\www\kodus\security.php): CSRF, method guards, cookie/session hardening, same-origin checks, upload MIME detection
- [`auth_helpers.php`](C:\laragon\www\kodus\auth_helpers.php): login/session handling, remember-me, redirects, security headers
- [`notification_helpers.php`](C:\laragon\www\kodus\notification_helpers.php): mail sending, templating, and audit/mail logging
- [`sidenav.php`](C:\laragon\www\kodus\sidenav.php): main navigation and topbar state
- [`mail_config.php`](C:\laragon\www\kodus\mail_config.php): SMTP configuration

## 4. Directory guide

- [`pages/`](C:\laragon\www\kodus\pages): main operational pages and handlers
- [`admin/`](C:\laragon\www\kodus\admin): admin user-management and settings pages
- [`implementation-status/`](C:\laragon\www\kodus\implementation-status): targets and program activity monitoring
- [`inbox/`](C:\laragon\www\kodus\inbox): internal messaging UI and handlers
- [`crossmatch/`](C:\laragon\www\kodus\crossmatch): DB-vs-file and file-vs-file comparison utility
- [`deduplication/`](C:\laragon\www\kodus\deduplication): duplicate detection utility
- [`dist/`](C:\laragon\www\kodus\dist): compiled UI assets and app images
- [`plugins/`](C:\laragon\www\kodus\plugins): vendored JS/CSS libraries
- [`vendor/`](C:\laragon\www\kodus\vendor): Composer dependencies
- [`docs/`](C:\laragon\www\kodus\docs): project documentation
- [`scripts/`](C:\laragon\www\kodus\scripts): maintenance scripts

## 5. Main entry points

- [`index.php`](C:\laragon\www\kodus\index.php): login screen
- [`login.php`](C:\laragon\www\kodus\login.php): sign-in handler
- [`register.php`](C:\laragon\www\kodus\register.php): public registration
- [`verify-2fa.php`](C:\laragon\www\kodus\verify-2fa.php): 2FA screen
- [`home.php`](C:\laragon\www\kodus\home.php): dashboard
- [`settings.php`](C:\laragon\www\kodus\settings.php): profile, password, avatar, theme, and 2FA settings
- [`contact.php`](C:\laragon\www\kodus\contact.php): compose message page
- [`inbox/index.php`](C:\laragon\www\kodus\inbox\index.php): inbox UI

## 6. Major functional areas

### Authentication and account lifecycle

Supported flows:

- username/password login
- remember-me restoration
- authenticator-based 2FA enabled by default
- recovery codes for account fallback
- registration
- password reset and password-policy enforcement
- forced logout after deactivation or role change

Important files:

- [`login.php`](C:\laragon\www\kodus\login.php)
- [`begin_2fa_setup.php`](C:\laragon\www\kodus\begin_2fa_setup.php)
- [`verify_2fa_code.php`](C:\laragon\www\kodus\verify_2fa_code.php)
- [`two_factor_helpers.php`](C:\laragon\www\kodus\two_factor_helpers.php)
- [`regenerate_recovery_codes.php`](C:\laragon\www\kodus\regenerate_recovery_codes.php)
- [`forgot-password.php`](C:\laragon\www\kodus\forgot-password.php)
- [`send-reset-link.php`](C:\laragon\www\kodus\send-reset-link.php)
- [`reset-password.php`](C:\laragon\www\kodus\reset-password.php)
- [`password_policy_helpers.php`](C:\laragon\www\kodus\password_policy_helpers.php)

### Dashboard and reporting

The dashboard in [`home.php`](C:\laragon\www\kodus\home.php) summarizes beneficiary and geographic counts, sex disaggregation, and selected sectoral statistics using data from [`get_data.php`](C:\laragon\www\kodus\get_data.php).

Summary reports are under [`pages/summary/`](C:\laragon\www\kodus\pages\summary).

### Beneficiary / MEB module

Main files:

- [`pages/data-tracking-meb.php`](C:\laragon\www\kodus\pages\data-tracking-meb.php)
- [`pages/data-tracking-meb-edit.php`](C:\laragon\www\kodus\pages\data-tracking-meb-edit.php)
- [`pages/data-tracking-meb-validation.php`](C:\laragon\www\kodus\pages\data-tracking-meb-validation.php)
- [`pages/import.php`](C:\laragon\www\kodus\pages\import.php)
- [`pages/export_meb.php`](C:\laragon\www\kodus\pages\export_meb.php)

### Incoming, outgoing, and payout tracking

Main files:

- [`pages/data-tracking-in.php`](C:\laragon\www\kodus\pages\data-tracking-in.php)
- [`pages/data-tracking-out.php`](C:\laragon\www\kodus\pages\data-tracking-out.php)
- [`pages/payout.php`](C:\laragon\www\kodus\pages\payout.php)
- [`pages/update_data.php`](C:\laragon\www\kodus\pages\update_data.php)
- [`pages/update_data_out.php`](C:\laragon\www\kodus\pages\update_data_out.php)

### Calendar and events

Main files:

- [`pages/calendar.php`](C:\laragon\www\kodus\pages\calendar.php)
- [`pages/add_event.php`](C:\laragon\www\kodus\pages\add_event.php)
- [`pages/update_event.php`](C:\laragon\www\kodus\pages\update_event.php)
- [`pages/delete_event.php`](C:\laragon\www\kodus\pages\delete_event.php)

### Implementation status

Main files:

- [`implementation-status/program-targets.php`](C:\laragon\www\kodus\implementation-status\program-targets.php)
- [`implementation-status/program-activities.php`](C:\laragon\www\kodus\implementation-status\program-activities.php)
- [`implementation-status/save-project-target.php`](C:\laragon\www\kodus\implementation-status\save-project-target.php)
- [`implementation-status/save-imp-status.php`](C:\laragon\www\kodus\implementation-status\save-imp-status.php)

### Messaging and inbox

Main files:

- [`contact.php`](C:\laragon\www\kodus\contact.php)
- [`send_contact.php`](C:\laragon\www\kodus\send_contact.php)
- [`inbox/index.php`](C:\laragon\www\kodus\inbox\index.php)
- [`inbox/get_thread.php`](C:\laragon\www\kodus\inbox\get_thread.php)
- [`inbox/send_reply.php`](C:\laragon\www\kodus\inbox\send_reply.php)

### Admin module

Main files:

- [`admin/users_management.php`](C:\laragon\www\kodus\admin\users_management.php)
- [`admin/change_user_type.php`](C:\laragon\www\kodus\admin\change_user_type.php)
- [`admin/deactivate_user.php`](C:\laragon\www\kodus\admin\deactivate_user.php)
- [`admin/restore_user.php`](C:\laragon\www\kodus\admin\restore_user.php)
- [`admin/password_security.php`](C:\laragon\www\kodus\admin\password_security.php)
- [`admin/project_variables.php`](C:\laragon\www\kodus\admin\project_variables.php)

### Data-quality utilities

Crossmatch:

- [`crossmatch/index.php`](C:\laragon\www\kodus\crossmatch\index.php)
- [`crossmatch/upload_handler.php`](C:\laragon\www\kodus\crossmatch\upload_handler.php)
- [`crossmatch/results.php`](C:\laragon\www\kodus\crossmatch\results.php)
- [`crossmatch/export.php`](C:\laragon\www\kodus\crossmatch\export.php)

Deduplication:

- [`deduplication/index.php`](C:\laragon\www\kodus\deduplication\index.php)
- [`deduplication/upload_handler.php`](C:\laragon\www\kodus\deduplication\upload_handler.php)
- [`deduplication/results.php`](C:\laragon\www\kodus\deduplication\results.php)
- [`deduplication/export_results.php`](C:\laragon\www\kodus\deduplication\export_results.php)

## 7. Environment and setup

Copy [`.env.example`](C:\laragon\www\kodus\.env.example) to `.env` and configure:

```env
DB_HOST=127.0.0.1
DB_USERNAME=root
DB_PASSWORD=
DB_NAME=kodus_db
APP_BASE_PATH=/
SMTP_HOST=smtp.gmail.com
SMTP_PORT=465
SMTP_USERNAME=your-email@example.com
SMTP_PASSWORD=your-app-password
SMTP_FROM_NAME="KODUS Admin"
SMTP_FROM_ADDRESS=your-email@example.com
```

Install dependencies:

```powershell
composer install
```

Optional frontend tooling only if you rebuild assets:

```powershell
npm install
npm run production
```

## 8. Repository and sync workflow

This repository is intended to track source code and reviewed runtime assets.

Do not treat Git as the primary transport for:

- production `.env` files
- database dumps
- private keys
- generated outputs or temporary files
- DB-linked uploads

If you need a fully working copy on another machine, sync these separately:

- the database
- `pages/uploads/`
- `inbox/uploads/`
- `crossmatch/uploads/`
- `deduplication/uploads/`

## 9. Security highlights

Implemented controls include:

- CSRF token validation
- request-method guards
- same-origin enforcement
- secure session and cookie settings
- login rate limiting
- password-strength validation
- authenticator-based 2FA with recovery codes
- server-side MIME inspection for uploads
- audit logging of state-changing requests

Shared security helpers live primarily in [`security.php`](C:\laragon\www\kodus\security.php) and [`auth_helpers.php`](C:\laragon\www\kodus\auth_helpers.php).

## 10. Operational notes

- KODUS is still largely procedural PHP, so shared helpers should be preferred over duplicated page logic.
- Runtime schema helpers exist for some features, so database state can evolve through application code as well as SQL migrations.
- Crossmatch and deduplication access rules should still be reviewed carefully before production hardening.
- Deployment should keep writable upload/log folders outside normal source control workflows.

## 11. Related docs

- [`APACHE_PRODUCTION_CHECKLIST.md`](C:\laragon\www\kodus\docs\APACHE_PRODUCTION_CHECKLIST.md)
- [`PRODUCTION_PACKAGE_GUIDE.md`](C:\laragon\www\kodus\docs\PRODUCTION_PACKAGE_GUIDE.md)
- [`LINUX_NGINX_DEPLOYMENT.md`](C:\laragon\www\kodus\docs\LINUX_NGINX_DEPLOYMENT.md)
