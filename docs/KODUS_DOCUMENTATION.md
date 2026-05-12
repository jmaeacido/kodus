# KODUS Application Documentation

Last reviewed: 2026-05-12

## 1. Overview

KODUS is a PHP/MySQL internal web application for DSWD Caraga program operations. Its current codebase supports document/data tracking, the Master List of Eligible Beneficiaries (MEB), RRP-CFTW / Project LAWA and BINHI implementation monitoring, beneficiary validation, deduplication, crossmatching, payout/fund monitoring, staff messaging, notifications, and administrative accountability.

The application is a classic server-rendered PHP system. It uses shared helper files and page controllers rather than a formal MVC framework.

## 2. Core Stack

- PHP with `mysqli`
- MySQL or MariaDB
- AdminLTE 3, Bootstrap 4, jQuery, DataTables, Chart.js, SweetAlert2, and bundled plugins
- PHPMailer for outbound email
- PhpSpreadsheet for Excel import/export/generation
- Dotenv for `.env` configuration
- Google2FA and QR-code libraries for TOTP-based two-factor setup
- Optional Socket.IO bridge for realtime broadcasts

`package.json` remains AdminLTE-oriented and is relevant only when frontend assets are rebuilt.

## 3. Application Bootstrap

- `config.php`: loads environment values, connects to MySQL, applies timezone/charset, ensures core schemas, logs state-changing requests, and enforces maintenance mode.
- `security.php`: response headers, CSP, HTTPS handling, session cookie options, CSRF/method guards, same-origin checks, upload MIME detection, JSON responses, and error helpers.
- `auth_helpers.php`: public page detection, session storage, role/area checks, remember-me handling, login completion, online status, and role-aware access helpers.
- `header.php`: authenticated page shell, shared assets, current-user context, notification/live-refresh setup, and navigation state.
- `sidenav.php`: role-aware module navigation.
- `base_url.php` and `env_helpers.php`: URL and environment helpers.

## 4. Roles and Access

Implemented user types are `admin`, `editor`, `aa`, and `user`.

Important access-control helpers:

- `auth_can_edit_meb_province()` limits editor MEB editing to assigned province.
- `auth_can_edit_implementation_province()` limits implementation activity editing by assigned province, with Field Office override.
- `auth_can_manage_program_targets()`, `auth_can_manage_program_activities()`, and `auth_can_manage_project_variables()` allow admin/editor management.
- Admin-only generator directories include `mebis-consolidator` and `mebis-lgu-template`.

Administrative screens include `admin/users_management.php`, `admin/change_user_type.php`, `admin/deactivate_user.php`, `admin/restore_user.php`, `admin/reset_user_2fa.php`, `admin/password_security.php`, `admin/project_variables.php`, `admin/maintenance.php`, and `admin/audit_logs.php`.

## 5. Authentication and Security

Implemented flows:

- username/password login and AJAX login
- remember-me token hashing
- password reset token flow
- password policy checks and admin password security settings
- TOTP-based 2FA, recovery codes, disable/reset flows
- optional Caraga Connect SSO callback flow
- session regeneration at login
- forced logout behavior after role changes
- soft deactivation/restoration of accounts
- security headers and CSP
- CSRF, request method, same-origin, and upload MIME checks on sensitive handlers

Important files:

- `login.php`, `ajax_login.php`, `auth_helpers.php`
- `password_policy_helpers.php`, `send-reset-link.php`, `reset-password.php`
- `two_factor_helpers.php`, `verify-2fa.php`, `verify_2fa_code.php`
- `sso_helpers.php`, `login-sso/callback.php`
- `security.php`, `error_helpers.php`

## 6. MEB Module

The MEB module supports import, background processing, viewing, editing, validation, change review, export, and profile export jobs.

Important files:

- `pages/data-tracking-meb.php`
- `pages/import.php`
- `pages/meb_import_helpers.php`
- `pages/meb_import_worker.php`
- `pages/meb_import_status.php`
- `pages/data-tracking-meb-edit.php`
- `pages/update.php`
- `pages/meb-change-review.php`
- `meb_change_history_helpers.php`
- `pages/data-tracking-meb-validation.php`
- `pages/fetch_data_validation_admin.php`
- `pages/update_validation_status.php`
- `pages/export_meb.php`
- `pages/export_meb_validation.php`
- `pages/profile_export_*`

Key tables:

- `meb`
- `meb_change_history`
- MEB import job/output tables created by helper code where applicable

Validation compares barangay-level imported MEB counts against `project_lawa_binhi_targets.target_partner_beneficiaries`. Status labels include `No Target`, `No Import`, `Partial`, `Validated`, `Over Target`, and `Unplanned Import`.

## 7. RRP-CFTW / Project LAWA and BINHI

The implementation-status module captures fiscal-year baseline targets, project entries, activity timelines, project accomplishments, location data, geotags, evidence links, and summary outputs.

Important files:

- `implementation-status/program-targets.php`
- `implementation-status/save-project-target.php`
- `implementation-status/import-project-targets.php`
- `implementation-status/fetch-project-targets.php`
- `project_targets_helpers.php`
- `implementation-status/program-activities.php`
- `implementation-status/save-imp-status.php`
- `implementation-status/activity_metadata.php`
- `implementation-status/project-location-records.php`
- `implementation-status/project-location-maps.php`
- `implementation-status/fetch-project-location-records.php`
- `implementation-status/fetch-project-location-maps.php`
- `implementation-status/lawa-summary.php`
- `implementation-status/binhi-summary.php`
- `implementation-status/fetch-program-summary.php`
- `implementation-status/program-summary-template.php`

Key tables:

- `project_lawa_binhi_targets`
- `project_target_entries`
- `program_activity_metadata`
- `program_activity_actual_projects`
- `project_variable_config`

The code normalizes target rows into `project_target_entries` and actual project/accomplishment rows into `program_activity_actual_projects`, while maintaining compatibility with legacy packed fields during transition.

## 8. Deduplication and Crossmatch

Deduplication:

- Upload page and handlers: `deduplication/index.php`, `deduplication/upload_handler.php`
- Parser/template validation: `deduplication/helpers/validator.php`
- Background worker: `deduplication/worker_v2.php`
- Results/export/status: `deduplication/results.php`, `deduplication/export_results.php`, `deduplication/progress_status.php`, `deduplication/status_api.php`
- Key tables: `deduplication_jobs`, `deduplication_results`

Crossmatch:

- Upload/start pages: `crossmatch/index.php`, `crossmatch/upload_handler.php`, `crossmatch/start.php`
- Job runner: `crossmatch/run_job.php`
- Parser and fuzzy scoring: `crossmatch/helpers/file_parser.php`, `crossmatch/helpers/fuzzy.php`
- Results/export/status: `crossmatch/results.php`, `crossmatch/export.php`, `crossmatch/progress_status.php`, `crossmatch/paginated_results.php`
- Key tables: `crossmatch_jobs`, `crossmatch_results`

Both utilities process sensitive beneficiary datasets and should be limited to authorized users in production policy.

## 9. Payout and Fund Monitoring

Payout:

- `pages/payout.php`
- `pages/update_payout.php`
- `pages/update_payout_group.php`
- `pages/payout_export.php`
- `payout_config_helpers.php`
- `project_variable_helpers.php`
- Key table: `breakdown`

Fund monitoring:

- `pages/fund-monitoring.php`
- `pages/save_fund_monitoring.php`
- `pages/fund-monitoring-status.php`
- `pages/fund-monitoring-transactions.php`
- `pages/fund-monitoring-export.php`
- `fund_monitoring_helpers.php`
- Key tables: `fund_monitoring_object_codes`, `fund_monitoring_items`, `fund_monitoring_entries`

Fund monitoring calculates monthly/quarterly obligations and disbursements, variances, and utilization percentages.

## 10. Document and Data Tracking

Incoming/outgoing tracking and document upload/forwarding live mainly under `pages/`.

Important files:

- `pages/data-tracking-in.php`
- `pages/data-tracking-out.php`
- `pages/track_incoming.php`
- `pages/track_outgoing.php`
- `pages/update_data.php`
- `pages/update_data_out.php`
- `pages/save_document.php`
- `pages/forward_document.php`
- `pages/document_upload_helpers.php`
- `pages/tracking_recipient_helpers.php`

Key tables include `incoming`, `outgoing`, and `aatracker`.

## 11. Messaging, Notifications, and Realtime

Messaging:

- `contact.php`, `send_contact.php`
- `inbox/` handlers
- `messenger/` mirror handlers
- `inbox/mailbox_helpers.php`
- Key tables: `contact_messages`, `contact_message_recipients`, `contact_replies`, `message_reads`

Notifications:

- `app_notification_helpers.php`
- `notifications/index.php`
- `notifications/get_feed.php`
- `notifications/mark_read.php`
- Key tables: `app_notifications`, `app_notification_reads`

Realtime/live refresh:

- `live_refresh.php`
- `dist/js/kodus-live-refresh.js`
- `socket_helpers.php`
- Optional bridge configuration in `.env.example`

Live-refresh channels include incoming, outgoing, MEB, MEB validation, user status, crossmatch recent jobs, and deduplication recent jobs.

## 12. Calendar and Events

Important files:

- `pages/calendar.php`
- `pages/add_event.php`
- `pages/update_event.php`
- `pages/delete_event.php`
- `pages/event_schedule_helpers.php`
- `pages/sendEventEmails.php`
- `pages/send_event_reminders.php`
- `pages/fetch_events.php`
- `pages/fetch_holidays.php`
- `pages/fetch_ph_holidays.php`

Key tables include `events`, `event_guests`, `event_schedule_days`, and `draggable_events`.

## 13. MEBIS Utilities

MEBIS consolidator:

- `mebis-consolidator/index.php`
- `mebis-consolidator/helpers/parser.php`
- `mebis-consolidator/helpers/history.php`
- `mebis-consolidator/request_letter.php`
- Key table: `mebis_consolidator_outputs`

MEBIS LGU template generator:

- `mebis-lgu-template/index.php`
- `mebis-lgu-template/helpers/template.php`
- `mebis-lgu-template/helpers/jobs.php`
- `mebis-lgu-template/worker.php`
- `mebis-lgu-template/import_generated.php`
- `mebis-lgu-template/import_generated_all.php`
- Key tables: `mebis_lgu_template_jobs`, `mebis_lgu_template_outputs`

## 14. Reporting and Evidence Assets

Excel export is powered by PhpSpreadsheet and common style helpers.

Useful evidence/documentation files:

- `docs/KODUS_ERD.png`, `docs/KODUS_ERD.pdf`, `docs/KODUS_ERD.svg`
- `docs/KODUS_BPRA.png`, `docs/KODUS_BPRA.pdf`
- `docs/KODUS_API_DIAGRAM.png`, `docs/KODUS_API_DIAGRAM.svg`
- `docs/KODUS_User_Manual*.pdf`
- `docs/KODUS_Test_Cases*.xlsx` and `*.pdf`
- `docs/manual_screens/*.png`
- `docs/PIA_FINAL_DRAFT.md`
- `docs/PIA_SCOPE_AUDIT.md`
- `docs/PIA_DATA_FLOW_NOTES.md`

## 15. Offline/PWA Status

No first-class PWA/offline implementation was found in the current source tree. There is no app manifest or service-worker registration. References to “offline” in the code are presence/status labels in chat/inbox flows.

## 16. Deployment

Current deployment guidance is maintained in:

- `docs/LINUX_NGINX_DEPLOYMENT.md`
- `docs/PRODUCTION_PACKAGE_GUIDE.md`

The expected production stack is Linux, Nginx, PHP-FPM, MySQL/MariaDB, Composer dependencies installed with optimized autoloading, configured `.env`, SMTP access, and locked-down upload/runtime directories.
