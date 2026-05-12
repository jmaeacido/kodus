# KODUS PIA Scope Audit

Last reviewed: 2026-05-12

This scope audit identifies the current privacy-relevant processing boundaries in the KODUS codebase. It is technical evidence only; final PIA statements must be confirmed by the system owner.

## 1. Scope Position

The PIA should cover the full KODUS application stack because the system processes user accounts, beneficiary masterlist data, implementation-location data, payout/fund records, messaging content, uploads, generated spreadsheets, job result payloads, logs, and external-service metadata.

KODUS should not be scoped as a simple document tracker. The current system supports an integrated operational workflow for RRP-CFTW / Project LAWA and BINHI: baseline targets, MEB import, MEB validation, duplicate/crossmatch checks, implementation status monitoring, fund/payout monitoring, reporting, notifications, and audit trails.

## 2. Included Modules

| Module | Evidence | Main Tables / Stores |
|---|---|---|
| Authentication, account lifecycle, 2FA, SSO | `login.php`, `ajax_login.php`, `register.php`, `auth_helpers.php`, `security.php`, `two_factor_helpers.php`, `sso_helpers.php` | `users`, session/cookies |
| Admin/user management | `admin/users_management.php`, `admin/change_user_type.php`, `admin/deactivate_user.php`, `admin/restore_user.php`, `admin/reset_user_2fa.php`, `admin/password_security.php` | `users`, `audit_logs`, `mail_logs` |
| MEB import/edit/export | `pages/import.php`, `pages/meb_import_helpers.php`, `pages/meb_import_worker.php`, `pages/data-tracking-meb.php`, `pages/update.php`, `pages/export_meb.php` | `meb`, import job/output stores |
| MEB change review | `meb_change_history_helpers.php`, `pages/meb-change-review.php` | `meb_change_history` |
| MEB validation | `pages/data-tracking-meb-validation.php`, `pages/fetch_data_validation_admin.php`, `pages/update_validation_status.php`, `pages/export_meb_validation.php` | `meb`, `project_lawa_binhi_targets`, `audit_logs` |
| RRP-CFTW / LAWA-BINHI targets | `implementation-status/program-targets.php`, `implementation-status/save-project-target.php`, `implementation-status/import-project-targets.php`, `project_targets_helpers.php` | `project_lawa_binhi_targets`, `project_target_entries` |
| Implementation activity/status | `implementation-status/program-activities.php`, `implementation-status/save-imp-status.php`, `implementation-status/activity_metadata.php` | `program_activity_metadata`, `program_activity_actual_projects` |
| Location maps/records | `implementation-status/project-location-maps.php`, `implementation-status/project-location-records.php`, fetch routes | `program_activity_actual_projects` |
| LAWA/BINHI summaries | `implementation-status/lawa-summary.php`, `implementation-status/binhi-summary.php`, `implementation-status/fetch-program-summary.php` | target/activity tables, `project_variable_config` |
| Deduplication | `deduplication/index.php`, `deduplication/upload_handler.php`, `deduplication/worker_v2.php`, `deduplication/export_results.php` | `deduplication_jobs`, `deduplication_results`, uploaded files |
| Crossmatch | `crossmatch/index.php`, `crossmatch/upload_handler.php`, `crossmatch/run_job.php`, `crossmatch/helpers/fuzzy.php`, `crossmatch/export.php` | `crossmatch_jobs`, `crossmatch_results`, uploaded files, `meb` candidates |
| MEBIS utilities | `mebis-consolidator/`, `mebis-lgu-template/` | `mebis_consolidator_outputs`, `mebis_lgu_template_jobs`, `mebis_lgu_template_outputs`, generated files |
| Payout | `pages/payout.php`, `pages/update_payout.php`, `pages/update_payout_group.php`, `pages/payout_export.php` | `breakdown`, `project_variable_config` |
| Fund monitoring | `pages/fund-monitoring.php`, `pages/save_fund_monitoring.php`, `fund_monitoring_helpers.php` | `fund_monitoring_object_codes`, `fund_monitoring_items`, `fund_monitoring_entries` |
| Document/data tracking | `pages/data-tracking-in.php`, `pages/data-tracking-out.php`, `pages/save_document.php`, `pages/forward_document.php` | `incoming`, `outgoing`, `aatracker`, upload folders |
| Messaging/inbox | `contact.php`, `send_contact.php`, `inbox/*.php`, `inbox/mailbox_helpers.php` | `contact_messages`, `contact_message_recipients`, `contact_replies`, `message_reads`, attachments |
| Notifications/live refresh | `app_notification_helpers.php`, `notifications/*.php`, `live_refresh.php`, `dist/js/kodus-live-refresh.js`, `socket_helpers.php` | `app_notifications`, `app_notification_reads`, optional socket metadata |
| Calendar/events | `pages/calendar.php`, `pages/event_schedule_helpers.php`, `pages/sendEventEmails.php`, `pages/fetch_events.php` | `events`, `event_guests`, `event_schedule_days`, `draggable_events` |
| Audit and errors | `audit_helpers.php`, `error_helpers.php`, `admin/audit_logs.php`, `config.php` | `audit_logs`, error logs |

## 3. Data Categories

### User Account Data

Processed data:

- names, username, email, position, assigned area
- role/user type, account status, deactivation state
- password hashes, reset tokens, remember tokens
- 2FA secret/recovery metadata
- SSO identifiers, avatar URL, ID/contact fields where present
- last login, last activity, online/idle/offline presence state

Primary evidence: `users`, `auth_helpers.php`, `security.php`, `two_factor_helpers.php`, `sso_helpers.php`, `admin/users_management.php`.

### Beneficiary / MEB Data

Processed data:

- name, extension, birthdate, age, sex, civil status
- purok/barangay/LGU/province
- NHTS/Listahanan and LSWDO indicators
- 4Ps and sectoral markers
- validation status, edit reason, batch/import metadata
- before/after edit snapshots

Primary evidence: `meb`, `meb_change_history`, `pages/import.php`, `pages/update.php`, `pages/export_meb.php`.

### Implementation and Location Data

Processed data:

- fiscal year, province, municipality, barangay, purok
- LAWA/BINHI target projects, classification, type, quantities
- stage dates, forums, site validation, monitoring dates/participants
- actual project accomplishments, coordinates, land area, ownership, status, evidence links
- water capacity/yield/fertilizer/aquatic resource indicators

Primary evidence: `project_lawa_binhi_targets`, `project_target_entries`, `program_activity_metadata`, `program_activity_actual_projects`.

### Financial / Payout Data

Processed data:

- beneficiary counts, paid/unpaid counts, payout dates
- calculated payout amounts based on configured wage rate/working days
- SARO/PAP/object-code budget lines
- obligations, disbursements, variances, utilization percentages

Primary evidence: `breakdown`, `fund_monitoring_*`, `pages/payout.php`, `pages/fund-monitoring.php`.

### Operational Communications

Processed data:

- message subjects/bodies
- sender/recipient metadata
- attachments
- read/trash state
- typing/presence metadata
- calendar guest email/name and event details

Primary evidence: `contact_messages`, `contact_message_recipients`, `contact_replies`, `message_reads`, `events`, `event_guests`.

### System Metadata

Processed data:

- audit actions/details, IP address, user agent/path summaries
- mail recipients, subjects, status, body/message
- app notifications and read state
- job metadata, upload filenames, progress, result payloads
- optional socket bridge event metadata

Primary evidence: `audit_logs`, `mail_logs`, `app_notifications`, job/result tables.

## 4. Processing Flows

### MEB Import and Validation

1. Admin uploads workbook through `pages/import.php`.
2. Import helper/job code parses and stores rows in `meb`.
3. Validation screen compares imported counts with `project_lawa_binhi_targets`.
4. Admin can edit rows and update validation status.
5. Edits create `meb_change_history`, `audit_logs`, notifications, and live refresh broadcasts.
6. Exports generate Excel outputs.

### LAWA/BINHI Implementation Monitoring

1. Admin/editor encodes baseline targets by fiscal year/location.
2. Target project rows are normalized in `project_target_entries`.
3. Admin/editor encodes activities and actual project rows.
4. Save flow validates date ranges, project classifications, coordinates, quantities, accomplishments, and URLs.
5. Maps, records, and summaries read from normalized activity tables.

### Deduplication and Crossmatch

1. User uploads dataset.
2. File validation and parsing occur.
3. Job metadata is stored.
4. Background worker computes similarity groups or candidate matches.
5. Results are stored and exported.
6. Notifications indicate completion/failure.

### Payout and Fund Monitoring

1. Payout pages read grouped records from `breakdown`.
2. Payout values use configured wage rate and working days.
3. Fund monitoring seeds object codes/budget items and stores monthly entries.
4. Pages compute obligations/disbursements, variances, and utilization rates.

## 5. Existing Controls

Implemented controls visible in code:

- role-based page access
- area/province-based edit controls for editors
- CSRF checks on state-changing routes
- method checks and same-origin enforcement
- session hardening and secure cookie options
- CSP and security headers
- password policy and reset workflow
- optional 2FA and recovery codes
- optional SSO integration
- upload extension/MIME checks in sensitive upload utilities
- audit logging for state-changing requests and explicit actions
- MEB before/after change history
- app notifications and per-user read state
- soft delete/restore for users
- maintenance mode
- Nginx deployment guidance for blocking secrets/uploads/scripts

## 6. External Touchpoints

- SMTP server for email notifications.
- Optional Caraga Connect SSO/OAuth.
- Optional Socket.IO bridge.
- Map/geolocation usage in implementation maps.
- Public holiday APIs in calendar helpers.
- User-entered external drive links for implementation evidence.

## 7. High-Risk Stores

- `meb`
- `meb_change_history`
- deduplication/crossmatch uploads and results
- MEBIS generated outputs
- profile export outputs
- inbox attachments
- `mail_logs` containing message/body content
- `audit_logs` with IP/path/user-agent/action details
- implementation rows containing coordinates and external evidence links

## 8. Gaps Requiring Owner Confirmation

- formal retention schedule for all data classes
- backup frequency, encryption, scope, retention, and restore testing
- authorized roles for full exports, deduplication, crossmatch, and MEBIS utilities
- operational SOP for duplicate/crossmatch results
- operational SOP for MEB validation categories such as `Over Target` and `Unplanned Import`
- whether uploaded documents routinely contain IDs, proofs, or beneficiary supporting records
- external sharing rules for exported reports and generated spreadsheets
- audit log review frequency and assigned reviewer
- incident response and breach reporting workflow
- production server hardening evidence
- data disposal/cleanup process for uploaded/generated files

## 9. Offline/PWA Status

No first-class PWA/offline support is evident in the current source tree. There is no manifest or service worker. “Offline” references are user-presence labels in messaging screens.

## 10. Recommended PIA Boundary Statement

The PIA should cover KODUS end to end, including the web application, database, upload/output folders, background workers, generated spreadsheets, email delivery, SSO, optional socket bridge, map/holiday integrations, deployment environment, backups, and administrative review processes.
