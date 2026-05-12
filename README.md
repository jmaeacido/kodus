# KODUS

Last reviewed: 2026-05-12

KODUS means **KliMalasakit Operational Data Unified System**. In the current codebase it is a PHP/MySQL internal operations platform for DSWD Caraga climate-response program monitoring, document/data tracking, MEB processing, RRP-CFTW / Project LAWA and BINHI implementation monitoring, fund/payout monitoring, staff coordination, and audit-ready reporting.

Application metadata from `app_meta.php`:

- Version: `2.4.6`
- Codename: `Control Center`
- Release date: `2026-03-30`

## Current Scope

KODUS is not only a document tracker. The implemented modules support an institutional workflow that connects planning targets, beneficiary records, validation, implementation status, payouts, funds, reporting, notifications, and accountability logs.

Core implemented areas:

- Authentication, registration, password reset, remember-me, 2FA, SSO hooks, profile review, and account lifecycle controls
- Role and area-based access for `admin`, `editor`, `aa`, and `user` accounts
- MEB import, background import jobs, MEB edit screens, MEB validation, change review, profile export, and Excel export
- RRP-CFTW / Project LAWA and BINHI baseline targets, project target rows, implementation activity rows, project location records, maps, and summary reports
- Deduplication and crossmatch utilities for uploaded beneficiary datasets
- Payout tracking and fund monitoring, including monthly obligations/disbursements and export routes
- Incoming/outgoing document tracking and document upload/forwarding helpers
- Inbox/messenger, group chat, attachments, reactions, typing state, read/trash state, and notification feed
- Calendar events, guest lists, schedule helpers, holidays, and reminders
- App notifications, live refresh polling, and optional Socket.IO bridge
- Admin user management, maintenance mode, password security settings, project variables, audit logs, and restore/deactivation actions

## Stack

- PHP using procedural page controllers and shared helper modules
- MySQL or MariaDB via `mysqli`
- AdminLTE 3, Bootstrap 4, jQuery, DataTables, Chart.js, SweetAlert2, and bundled frontend plugins
- Composer dependencies in `composer.json`:
  - `phpoffice/phpspreadsheet`
  - `phpmailer/phpmailer`
  - `vlucas/phpdotenv`
  - `pragmarx/google2fa`
  - `bacon/bacon-qr-code`
- Optional Node tooling remains from AdminLTE and is only needed when rebuilding frontend assets

## Key Entry Points

- `index.php`, `login.php`, `ajax_login.php`, `logout.php`
- `register.php`, `forgot-password.php`, `send-reset-link.php`, `reset-password.php`
- `verify-2fa.php`, `verify_2fa_code.php`, `begin_2fa_setup.php`, `disable_2fa.php`
- `home.php`, `select_year.php`, `settings.php`
- `pages/data-tracking-meb.php`, `pages/import.php`, `pages/meb_import_worker.php`
- `pages/data-tracking-meb-validation.php`, `pages/update_validation_status.php`
- `implementation-status/program-targets.php`, `implementation-status/program-activities.php`
- `deduplication/index.php`, `deduplication/upload_handler.php`, `deduplication/worker_v2.php`
- `crossmatch/index.php`, `crossmatch/upload_handler.php`, `crossmatch/run_job.php`
- `pages/payout.php`, `pages/fund-monitoring.php`
- `inbox/index.php`, `contact.php`, `send_contact.php`
- `notifications/index.php`, `live_refresh.php`
- `admin/users_management.php`, `admin/audit_logs.php`, `admin/maintenance.php`

## Important Directories

- `admin/`: user, role, password security, project variable, maintenance, restore, and audit screens
- `pages/`: main operational pages for MEB, document tracking, payout, fund monitoring, exports, calendar, and profile jobs
- `implementation-status/`: target setting, activity monitoring, maps, records, and LAWA/BINHI summaries
- `deduplication/`: uploaded-file duplicate detection jobs and results
- `crossmatch/`: DB-vs-file and file-vs-file matching jobs and results
- `mebis-consolidator/`: MEBIS workbook consolidation and output history
- `mebis-lgu-template/`: LGU import-template generation and import helpers
- `inbox/` and `messenger/`: staff messaging handlers
- `notifications/`: notification history/feed actions
- `socket/`: optional Socket.IO bridge service documentation
- `docs/`: ERD, PIA, user manuals, test cases, screenshots, deployment guides, and evidence notes
- `dist/`, `plugins/`, `cdn.*`, `fonts.*`: bundled frontend/static assets

## Data and Accountability

The main operational tables include `users`, `meb`, `meb_change_history`, `audit_logs`, `mail_logs`, `incoming`, `outgoing`, `breakdown`, `project_lawa_binhi_targets`, `project_target_entries`, `program_activity_metadata`, `program_activity_actual_projects`, `crossmatch_jobs`, `crossmatch_results`, `deduplication_jobs`, `deduplication_results`, `fund_monitoring_items`, `fund_monitoring_entries`, `contact_messages`, `contact_message_recipients`, `contact_replies`, `message_reads`, `events`, `event_guests`, `app_notifications`, and `app_notification_reads`.

KODUS currently has live refresh and optional Socket.IO support, but no first-class PWA/offline mode was found. There is no app manifest or service-worker registration in the current source tree; “offline” references are presence labels in chat/inbox flows.

## Configuration

Copy `.env.example` to `.env` and configure:

- database connection
- app URL and public root/directory
- timezone
- SMTP mail delivery
- Caraga Connect SSO endpoints and credentials, if used
- optional Socket.IO bridge settings
- optional KODA scene settings

Never commit `.env`, SQL dumps, private keys, runtime logs, or production uploads.

## Deployment

The maintained deployment target is Linux + Nginx + PHP-FPM + MySQL/MariaDB. See:

- `docs/LINUX_NGINX_DEPLOYMENT.md`
- `docs/PRODUCTION_PACKAGE_GUIDE.md`

Runtime upload/output directories must be writable only where needed, and server rules must block secrets, SQL dumps, logs, private keys, documentation, and executable uploads.
