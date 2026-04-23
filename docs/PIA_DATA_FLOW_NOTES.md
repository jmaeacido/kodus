# KODUS PIA Data Flow Notes

## Core Sensitive Touchpoints
- `users`: account identity, email, password hash, reset/remember tokens, 2FA secret/recovery data, SSO-linked fields, role and activity metadata.
- `meb`: beneficiary names, birth date, sex, granular location, NHTS indicators, and sectoral/program flags including `PWD`, `lgbtqia`, `FR`, and `ybDs`.
- `meb_change_history`: full before/after beneficiary snapshots.
- `audit_logs` and `mail_logs`: secondary stores of IPs, action details, recipients, and email content/status.
- Upload directories: `dist/img/`, `inbox/uploads/`, `pages/uploads/`, `storage/aatracker/`, `crossmatch/uploads/`, `deduplication/uploads/`.

## Likely Data Flows
- Registration/account flow
  - User enters profile + password in `register.php` -> `users` row created -> welcome email sent -> `mail_logs` and `audit_logs` updated.
- Login/security flow
  - User submits username/password in `login.php` -> `users` checked -> optional 2FA verification in `verify-2fa.php` / `verify_2fa_code.php` -> session set -> login alert email logged.
- Password reset flow
  - User enters email in `send-reset-link.php` -> hashed reset token stored in `users` -> reset email sent -> token verified in `reset-password.php`.
- Beneficiary intake flow
  - Admin uploads MEB spreadsheet in `pages/import.php` -> rows inserted into `meb` with batch ID -> MEB list/reporting pages read from `meb`.
- Beneficiary update/validation flow
  - Admin edits in `pages/update.php` -> `meb` updated -> `meb_change_history` snapshot inserted -> `audit_logs` updated.
  - Admin validates rows in `pages/update_validation_status.php` -> `meb.validation` updated -> `audit_logs` updated.
- Reporting/export flow
  - Dashboard and reports aggregate `meb` in `get_data.php` and `pages/summary/*`.
  - Export endpoints stream XLSX/CSV containing beneficiary or utility result data.
- Messaging flow
  - User/admin composes in `send_contact.php` -> thread inserted in `contact_messages` -> recipients synced to `contact_message_recipients` -> attachments saved under `inbox/uploads/contact_attachments/` -> email delivered.
  - Reply in `inbox/send_reply.php` -> reply inserted in `contact_replies` -> attachment saved under `inbox/uploads/reply_attachments/` -> `message_reads` updated -> optional reply email sent.
- Document-routing flow
  - Staff uploads incoming/outgoing/action-tracker files -> metadata stored in `incoming`, `outgoing`, or `aatracker` -> files written to `pages/uploads/` or `storage/aatracker/`.
- Implementation-status flow
  - Editors/admins save targets and activity rows -> location/project/accomplishment data stored in `project_lawa_binhi_targets`, `project_target_entries`, `program_activity_metadata`, `program_activity_actual_projects` -> map and records pages display coordinates and drive links.
- Utility flow
  - Crossmatch/dedup users upload beneficiary datasets -> job metadata stored -> result payloads stored in `crossmatch_results` / `deduplication_results` -> exports/downloads generated.

## Third-Party / External Exposure Points
- SMTP provider for account, event, and inbox/contact email.
- Caraga Connect SSO and optional socket bridge.
- OpenStreetMap and Esri tile services for project maps.
- Nager.Date and Official Gazette holiday lookups.
- Stored Google Drive links in implementation records.

## Immediate PIA Emphasis Areas
- Full MEB beneficiary repository and exports.
- User account security data and admin management.
- Local uploaded-file repositories.
- Utility uploads/results that duplicate or transform beneficiary datasets.
- Hybrid live schema where legacy and normalized stores coexist.
