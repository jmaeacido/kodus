# Annex E - Audit and Accountability Records

**Document status:** Draft for audit-retention and accountability validation  
**System:** KliMalasakit Operational Data Unified System (KODUS)  
**Scope:** Audit log coverage, session/account actions, data changes, import/export actions, deletion/restoration records, 2FA/email logs where applicable, and administrative accountability controls  
**Privacy note:** Sample records are synthetic and sanitized. Do not include real IP addresses, usernames, emails, user IDs, beneficiary names, or raw change details in submission copies unless formally redacted and approved.

## E.1 Purpose

This annex documents the accountability records available in KODUS and the controls that support traceability of user actions, administrative decisions, imports, validation updates, notifications, and related system events.

## E.2 Audit Log Coverage

KODUS includes an `audit_logs` mechanism and an admin-only audit log screen. The helper records the user, action, details, IP address, and timestamp. The configuration also logs authenticated state-changing requests for POST, PUT, PATCH, and DELETE methods, except selected skipped endpoints.

**Table E-1. Audit and accountability coverage.**

| Area | Coverage | Representative files/modules |
| --- | --- | --- |
| State-changing requests | Records method, path, posted field names, and user agent for authenticated users. | `config.php`, `audit_helpers.php` |
| Explicit business actions | Records formatted details for specific operational actions. | MEB validation, payout group updates, admin actions |
| MEB validation updates | Records rows affected, target status, and before/after validation value. | `pages/update_validation_status.php` |
| MEB change history | Captures before/after changes for MEB edits. | `meb_change_history_helpers.php`, `pages/update.php` |
| Admin audit review | Provides filterable audit log table for administrators. | `admin/audit_logs.php` |
| Account actions | Deactivation, restoration, role changes, 2FA resets, and account deletion actions where implemented. | `admin/`, `delete_account.php`, `disable_2fa.php` |
| Notifications | Stores app notifications and read state. | `app_notification_helpers.php`, `notifications/` |
| Email outcomes | Stores mail delivery outcomes where notification helpers are used. | `notification_helpers.php`, `mail_logs` |
| Import/background jobs | Stores job status, progress, source filename, requester, row count, and timestamps for applicable jobs. | MEB import, profile export, MEBIS, deduplication, crossmatch |

## E.3 Sanitized Audit Log Sample

**Table E-2. Sample audit log records, sanitized.**

| Audit no. | Timestamp | User reference | Role | Action | Sanitized details | Source IP |
| --- | --- | --- | --- | --- | --- | --- |
| AUD-000001 | 2026-05-12 09:15:22 | USER-ADMIN-01 | Admin | Update MEB Validation | Updated MEB validation for 12 row(s). Target status: validated. Row references masked. | 192.0.2.10 |
| AUD-000002 | 2026-05-12 09:28:44 | USER-EDITOR-03 | Editor | Request POST | Method: POST; Path: implementation-status/save-project-target; Fields: fiscal_year, province, municipality, barangay, target | 192.0.2.11 |
| AUD-000003 | 2026-05-12 10:04:31 | USER-ADMIN-01 | Admin | Payout Municipality Update | Updated grouped payout fields. Record references masked. | 192.0.2.10 |
| AUD-000004 | 2026-05-12 10:20:18 | USER-ADMIN-02 | Admin | Account Restoration | Restored masked user account. | 192.0.2.12 |

**Note:** `192.0.2.0/24` addresses are documentation examples and are not production addresses.

## E.4 Login and Session Tracking

KODUS supports username/password login, AJAX login, remember-me token hashing, password reset token flow, optional SSO callback handling, session regeneration at login, role-change safety checks, and account deactivation/restoration behavior. Last-login and last-activity fields are referenced in account/session flows and user status displays.

**Control notes:**

- Do not include session IDs, remember-me tokens, reset tokens, SSO authorization codes, or OAuth parameters in documentation.
- Login/session evidence should be summarized by aggregate counts or sanitized records only.

Login and session evidence should be retained and reviewed according to the approved DSWD records-management, privacy, and information-security schedule. Annex copies should summarize login/session activity only through aggregate or sanitized records and should not expose session identifiers, tokens, or OAuth parameters.

## E.5 Account Actions

Administrative accountability controls include user management, role changes, deactivation/restoration, password security settings, 2FA reset, and maintenance mode.

**Table E-3. Sample account action register, sanitized.**

| Action type | Minimum documentation | Responsible role | Evidence location |
| --- | --- | --- | --- |
| Role change | User reference, previous role, new role, approver, date/time | Admin | Audit log and user management record |
| Deactivation | User reference, reason, effective date, approving official | Admin | Admin action record and audit log |
| Restoration | User reference, reason, restoration date, approving official | Admin | Admin action record and audit log |
| 2FA reset | User reference, reason, requesting official, reset date | Admin | Admin 2FA reset record and notification/email log where applicable |
| Password policy change | Policy field changed, previous/new setting, approving official | Admin | Password security page and audit log |

## E.6 Data Change, Import, Export, and Deletion Records

KODUS supports accountability for operational data changes through request logging, explicit audit entries, change history, and job records.

**Table E-4. Sample data accountability register.**

| Activity | Record to retain | Sensitive details to exclude from annex |
| --- | --- | --- |
| MEB import | Batch ID, uploader reference, row count, source filename if sanitized, start/finish timestamps, job status | Raw workbook, beneficiary names, exact birthdates, full address details |
| MEB validation update | Fiscal year, affected location or masked row references, previous/new validation status, reviewer | Full beneficiary row details |
| MEB edit | Before/after field summary, editor, timestamp, reason | Full personal data unless redacted and required |
| Export | Export type, requesting user, date/time, purpose | Downloaded raw file contents |
| Deletion/restoration | Record reference, action, reason, approving user, timestamp | Full deleted/restored record payload unless authorized |
| Deduplication/crossmatch | Job ID, requester, threshold/rule, aggregate result counts, reviewer status | Raw uploaded dataset and candidate personal records |

## E.7 2FA and Email Logs

KODUS includes TOTP-based 2FA setup/disable/reset flows and PHPMailer-backed notifications. Mail outcomes are logged through mail logging helpers where applicable.

**Table E-5. Sample 2FA/email accountability fields.**

| Event | Sanitized evidence field | Note |
| --- | --- | --- |
| 2FA enabled | User reference, date/time, confirmation notice | Do not show QR code secret or recovery codes. |
| 2FA disabled | User reference, date/time, action source | Confirm whether user-initiated or admin-initiated. |
| 2FA reset | User reference, admin reference, reason, date/time | Requires admin accountability. |
| Password reset email | User reference, delivery status, timestamp | Do not show reset token or reset URL. |
| Operational notification email | Recipient reference, subject category, delivery status | Do not show confidential body content in annexes. |

## E.8 Retention and Accountability Purpose

Audit and accountability records support:

- Verification of authorized access and action history.
- Investigation of data-quality changes and validation outcomes.
- Review of import/export and background job activity.
- Accountability for administrative changes affecting users and security.
- Compliance with internal monitoring, privacy, and records management requirements.

Retention for audit logs, mail logs, notifications, uploads, generated outputs, and job result payloads should follow the approved DSWD records-management and privacy schedule. Until a stricter disposition is issued by the records or data-protection owner, these records should be treated as controlled operational records with access limited to authorized system, audit, and program personnel.

## E.9 Owner Validation Notes

- Audit log retention, archival, and disposal should follow the approved records-management and privacy schedule for controlled operational records.
- Periodic audit review should be assigned to the KODUS system owner, system administrator, and designated audit/KM/GPD reviewer for the reporting period.
- <span style="color:red">[MANUAL INPUT REQUIRED]</span> Insert approved screenshots from Annex A showing audit and admin pages.
- Export actions should be reviewed module by module; where explicit export audit entries are not present, the enhancement should be tracked as an accountability improvement.
- Login attempts, failed logins, and SSO events should be included in formal audit summaries where supported by available logs and privacy-approved reporting rules.
