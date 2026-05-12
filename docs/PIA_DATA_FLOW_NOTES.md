# KODUS PIA Data Flow Notes

Last reviewed: 2026-05-12

This note summarizes current code-supported data flows for privacy, security, and GPD evidence review. It should be validated by the system owner before formal submission.

## 1. Account and Authentication Flow

- User registers or is provisioned through standard registration/SSO-related flows.
- Account data is stored in `users`, including identity fields, email, role, area, password hash, reset/remember token hashes, 2FA metadata, SSO subject fields, profile review flags, last login, last activity, and soft-delete state.
- Login uses `login.php` / `ajax_login.php`, `auth_helpers.php`, `password_policy_helpers.php`, and optional `two_factor_helpers.php`.
- Successful login regenerates the session, updates login/activity metadata, and may trigger email or app notifications.
- Password reset stores a hashed reset token and expiry in `users`, sends email via PHPMailer, and records outcomes in `mail_logs`.

## 2. MEB Flow

- Admin uploads an MEB workbook through `pages/import.php`.
- `pages/meb_import_helpers.php` creates or processes import jobs; `pages/meb_import_worker.php` performs background import work where used.
- Imported beneficiary rows are stored in `meb`.
- Admin edits use `pages/update.php`; updates write to `meb`, create before/after snapshots in `meb_change_history`, write `audit_logs`, create app notifications, and broadcast live refresh events.
- Validation uses `pages/data-tracking-meb-validation.php`, `pages/fetch_data_validation_admin.php`, and `pages/update_validation_status.php`.
- Validation compares actual MEB rows by fiscal year/location against `project_lawa_binhi_targets.target_partner_beneficiaries`.
- MEB exports use `pages/export_meb.php`, `pages/export_meb_validation.php`, and profile export job helpers.

## 3. RRP-CFTW / LAWA and BINHI Flow

- Editors/admins encode fiscal-year baseline targets in `implementation-status/program-targets.php`.
- `implementation-status/save-project-target.php` validates required location, target, LAWA/BINHI project type, quantity, fertilizer, and aquatic-resource fields.
- Parent target rows are stored in `project_lawa_binhi_targets`; per-project rows are stored in `project_target_entries`.
- Editors/admins encode activity and implementation status in `implementation-status/program-activities.php`.
- `implementation-status/save-imp-status.php` validates date ranges, location/province access, project classification/type, coordinates, accomplishments, fertilizer quantities, aquatic resources, land area/ownership, status, and evidence links.
- Parent activity rows are stored in `program_activity_metadata`; actual project/accomplishment rows are stored in `program_activity_actual_projects`.
- Map and records pages read latitude/longitude and activity details through `implementation-status/fetch-project-location-maps.php` and `implementation-status/fetch-project-location-records.php`.
- LAWA/BINHI summaries are generated through `implementation-status/fetch-program-summary.php`, `lawa-summary.php`, `binhi-summary.php`, and `program-summary-template.php`.

## 4. Deduplication and Crossmatch Flow

- Users upload beneficiary datasets to `deduplication/upload_handler.php` or `crossmatch/upload_handler.php`.
- Upload handlers validate file extensions/MIME where implemented, store uploads in module upload directories, and create job rows.
- Deduplication parses required columns, computes strict or fuzzy duplicate groups, stores job metadata in `deduplication_jobs`, and stores grouped results in `deduplication_results`.
- Crossmatch parses uploaded files, optionally loads candidates from `meb`, scores candidate similarity, stores job metadata in `crossmatch_jobs`, and stores candidate matches in `crossmatch_results`.
- Completion/failure can create app notifications targeted to the requesting user.

## 5. Payout and Fund Monitoring Flow

- Payout records read and update `breakdown` through `pages/payout.php`, `pages/update_payout.php`, and `pages/update_payout_group.php`.
- Payout calculations use fiscal-year project variables such as daily wage rate and working days from `project_variable_config`.
- Fund monitoring seeds default object codes and budget items, then stores monthly obligations/disbursements in `fund_monitoring_items`, `fund_monitoring_entries`, and `fund_monitoring_object_codes`.
- Fund monitoring outputs include totals, variances, utilization percentages, transaction/status routes, and Excel export.

## 6. Document Tracking Flow

- Incoming and outgoing routes under `pages/` manage document dates, tracking numbers, descriptions, offices, focal/action fields, remarks, uploaded files, forwarding, and recipient helpers.
- Main tables include `incoming`, `outgoing`, and `aatracker`.
- Uploads and forwards may trigger mail/app notifications and audit entries depending on route.

## 7. Messaging, Calendar, and Notifications Flow

- Contact/inbox messages are created through `contact.php` and `send_contact.php`.
- Messages are stored in `contact_messages`; recipients in `contact_message_recipients`; replies in `contact_replies`; read/trash state in `message_reads`.
- Attachments are stored under inbox upload directories.
- Calendar events are stored in `events`; guests in `event_guests`; expanded schedules in `event_schedule_days`.
- App notifications are stored in `app_notifications`; read state is stored in `app_notification_reads`.
- Email outcomes are stored in `mail_logs`.

## 8. Audit and Live Refresh Flow

- `config.php` calls `audit_log_state_change_request()` for state-changing requests.
- Specific business actions write explicit audit entries through `audit_log()`.
- Admins view and filter audit logs in `admin/audit_logs.php`.
- `live_refresh.php` produces snapshot hashes for incoming, outgoing, MEB, MEB validation, user status, crossmatch, and deduplication channels.
- `dist/js/kodus-live-refresh.js` watches tables and can use long polling or optional Socket.IO bridge events.

## 9. External / Third-Party Touchpoints

- SMTP through PHPMailer for account, contact, event, and operational email.
- Optional Caraga Connect SSO / OAuth.
- Optional Socket.IO bridge configured through `.env`.
- Map tiles/geolocation where maps are used.
- Holiday APIs used by calendar helpers.
- User-entered external drive links in implementation activity records.

## 10. Current Gaps for PIA/GPD Confirmation

- Formal retention schedule for beneficiary data, audit logs, mail logs, uploads, generated outputs, and job result payloads.
- Backup frequency, storage location, encryption, retention, and restoration testing.
- Authorized role list for full exports, deduplication, crossmatch, and MEBIS utilities.
- SOP for acting on duplicate, crossmatch, over-target, and unplanned-import findings.
- Confirmation that uploaded documents do or do not include beneficiary supporting records.
- Evidence of audit-log review practices.
- No true PWA/offline mode is present in the source tree.
