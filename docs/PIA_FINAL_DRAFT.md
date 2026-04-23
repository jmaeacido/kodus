# KODUS Privacy Impact Assessment Draft

## 1. Purpose and Scope
This Privacy Impact Assessment covers the KODUS application as implemented in the current codebase and reflected in the live configured database. For technical evidence, field-level details, tables, file paths, and control references should be read together with [PIA_SCOPE_AUDIT.md](/c:/laragon/www/kodus/docs/PIA_SCOPE_AUDIT.md), which serves as the technical evidence appendix.

The system scope is grouped into three main data domains:
- User account and admin/security data
- Beneficiary master list and related outputs
- Operational and supporting data such as uploads, messaging, logs, maps, and utilities

The live database and code together indicate that this is a high-impact personal data processing system because it handles direct beneficiary identity data, potentially sensitive classifications, user authentication and 2FA data, exported reports, attachments, logs, and multiple supporting operational repositories.

## 2. System Overview
KODUS is a PHP and MySQL web application used for account management, beneficiary master-list processing, implementation monitoring, internal messaging, calendar/event handling, document tracking, payout/fund monitoring, and data-quality utilities such as crossmatching and deduplication.

The deployed environment currently reflects a hybrid data model. Some modules already use normalized child tables, while the live database still retains several legacy packed columns in the same functional areas. For PIA purposes, the privacy boundary should therefore include both the intended normalized structures and the legacy structures still present in production.

## 3. Data Domain 1: User Account and Admin/Security Data

### 3.1 Data Processed
This domain includes:
- Account identity data such as username, email, first name, middle name, last name, suffix, position, position abbreviation, and area
- Authentication data such as password hash, remember-me token, reset token, reset-token expiry, and password-policy fields
- Two-factor authentication data such as enablement state, TOTP secret, recovery codes, setup dates, and temporary code fields
- SSO-linked identity data such as SSO subject, optional ID number, optional contact number, and SSO avatar URL
- Account administration data such as role, deactivation state, role-change metadata, and forced logout markers
- Activity/security metadata such as last login, last activity, online status, theme preference, audit records, and mail delivery logs

### 3.2 Processing Activities
The Data Flow and Processing Activities statements in this PIA are based on [PIA_DATA_FLOW_NOTES.md](/c:/laragon/www/kodus/docs/PIA_DATA_FLOW_NOTES.md).

For this domain, the main confirmed activities are:
- User registration and account creation
- Username/password authentication
- Remember-me token issuance and restoration
- Password reset issuance and completion
- 2FA setup, verification, recovery, disablement, and admin reset
- Profile and password updates in settings
- Role change, deactivation, restoration, and password-security enforcement by administrators
- Audit logging and mail logging of important security/account actions

### 3.3 Data Flow Summary
- A user may register directly in the application or be provisioned/matched through SSO.
- Credentials are verified during login, with optional 2FA completion before the session is fully established.
- Reset links and security notifications are delivered through SMTP-configured email.
- Admin users can review and change account state, role, 2FA status, and password-security posture.
- Security-relevant actions are reflected in audit and mail logs.

### 3.4 Storage and Access
Primary storage is the `users` table, with additional supporting storage in `audit_logs`, `mail_logs`, PHP sessions, and local avatar files under `dist/img/`.

Access is role-dependent:
- `admin` has the broadest visibility and management rights
- `editor` has no general user-admin powers but does access some operational areas
- `aa` can access some operations modules
- `user` is generally restricted to own-account and participant-level features

### 3.5 Key Privacy Risks
- Concentration of credential, token, and 2FA data in one account repository
- Broad admin visibility into user status, presence, and security posture
- Secondary replication of personal/security data in audit and mail logs
- Possible identity-linkage risk from SSO fields such as `sso_subject`, `id_number`, and `contact_number`

### 3.6 Existing Controls
Confirmed controls include:
- Password hashing
- 2FA with recovery codes
- CSRF protection
- Same-origin enforcement
- Session hardening and session regeneration
- Rate limiting for login and reset flows
- Role-based access checks
- Audit logging
- Security headers including CSP, HSTS when HTTPS is detected, and framing/content protections

### 3.7 Residual Gaps Requiring Owner Confirmation
- Production session-storage design and retention
- Retention period for audit logs and mail logs
- Whether SSO fields are actively populated in production and for what operational purpose
- Formal access-review and privileged-account review process

## 4. Data Domain 2: Beneficiary Master List and Related Outputs

### 4.1 Data Processed
This domain is centered on the Master List of Eligible Beneficiaries (`meb`) and its downstream outputs. Confirmed fields include:
- Direct identity data such as last name, first name, middle name, and suffix
- Granular location data such as purok, barangay, municipality/LGU, and province
- Demographic data such as birth date, age, sex, and civil status
- Eligibility and poverty-related indicators such as `nhts1` and `nhts2`
- Program/sectoral classification fields such as `fourPs`, `F`, `FF`, `IS`, `IP`, `SC`, `SP`, `LW`, `PW`, `PWD`, `OSY`, `FR`, `ybDs`, and `lgbtqia`
- Edit reason and validation fields
- Full before/after edit snapshots in `meb_change_history`

### 4.2 Processing Activities
Using [PIA_DATA_FLOW_NOTES.md](/c:/laragon/www/kodus/docs/PIA_DATA_FLOW_NOTES.md) as the basis, the confirmed activities for this domain are:
- Spreadsheet-based intake/import of beneficiary master-list records
- Search, retrieval, listing, and filtering of beneficiary rows
- Manual editing and correction of beneficiary records
- Validation marking by administrators
- Aggregation for dashboard and reporting use
- Export to Excel and reporting outputs
- Inclusion of beneficiary data in crossmatch, deduplication, and MEBIS-related utility workflows

### 4.3 Data Flow Summary
- Beneficiary records are uploaded by spreadsheet and inserted into `meb`.
- Users with appropriate access retrieve and review MEB records through tracking and reporting pages.
- Admin users can update and validate MEB records.
- Updates create audit entries and a dedicated before/after history trail.
- Reporting and export endpoints produce outputs that contain beneficiary data, including full-profile extracts and summary reports.
- Utility modules can ingest beneficiary datasets again for matching, duplicate detection, or template-generation purposes.

### 4.4 Storage and Access
Primary storage is the `meb` table, with related storage in:
- `meb_change_history`
- Reporting/export outputs streamed or generated from MEB data
- Utility job/result tables such as `crossmatch_results` and `deduplication_results`
- Utility output history tables such as `mebis_consolidator_outputs` and `mebis_lgu_template_outputs`

Access is broader than one page:
- `admin` can import, edit, validate, export, and review change history
- `editor` and other non-user operational roles can access reporting and some beneficiary-related operational views depending on page-level checks
- Utility modules may expose beneficiary data transformations outside the core MEB screens

### 4.5 Key Privacy Risks
- Direct identity data combined with granular location data
- Sensitive or potentially sensitive category fields such as `PWD`, `lgbtqia`, `FR`, and `ybDs`
- Full-record export functions that can create portable copies outside the app
- MEB change-history snapshots duplicating full personal data states
- Utility workflows creating new copies or transformed versions of beneficiary datasets

### 4.6 Existing Controls
Confirmed controls include:
- Admin-only restriction for core import/edit/validation endpoints
- CSRF and same-origin checks on write paths
- Audit logging of edits and validation actions
- Dedicated change-history capture for beneficiary edits
- Role checks around operations workspace access

### 4.7 Residual Gaps Requiring Owner Confirmation
- Official retention schedule for MEB records and change-history snapshots
- Rules for who may export full beneficiary datasets and under what approval conditions
- Whether exported files are logged or independently controlled outside the application
- Data-sharing arrangements after export or utility processing

## 5. Data Domain 3: Operational and Supporting Data

### 5.1 Data Processed
This domain includes operational repositories and supporting services that still carry personal data or data linked to users/beneficiaries:
- Inbox/contact messages, recipients, replies, attachments, and read/trash state
- Calendar events and guest email/name lists
- Incoming/outgoing/action-tracker records and uploaded document files
- Payout and fund-monitoring records with beneficiary-count and financial data
- Implementation-status records, including coordinates, land/ownership details, project status, and external drive links
- Audit logs, mail logs, app notifications, and notification read-state
- Utility jobs/results/outputs for crossmatch, deduplication, and MEBIS templates
- Session data and live-refresh / socket event metadata

### 5.2 Processing Activities
Again using [PIA_DATA_FLOW_NOTES.md](/c:/laragon/www/kodus/docs/PIA_DATA_FLOW_NOTES.md) as the basis, the main activities in this domain are:
- Message composition, delivery, reply, and attachment handling
- Event creation, update, invitation, and reminder email sending
- Upload, storage, viewing, forwarding, and update of routed documents
- Beneficiary-count and payout/fund monitoring updates
- Project/activity monitoring with location and accomplishment tracking
- Utility upload, matching, duplicate analysis, result generation, and export
- Audit logging, mail logging, realtime updates, and notifications

### 5.3 Data Flow Summary
- Operational users upload files and routing metadata into incoming/outgoing/action-tracker modules.
- Internal messaging stores message content and attachments locally while also using email delivery for notification or correspondence.
- Calendar modules store guest details and may send reminder emails.
- Implementation modules store location-based project/accomplishment data and expose map views using external tile providers.
- Utility modules accept uploaded beneficiary datasets, process them into result tables, and generate downloadable outputs.
- Logs and notifications create secondary operational stores of user and activity data.

### 5.4 Storage and Access
This data is distributed across MySQL tables and local filesystem directories.

Confirmed local storage areas include:
- `inbox/uploads/contact_attachments/`
- `inbox/uploads/reply_attachments/`
- `pages/uploads/`
- `storage/aatracker/`
- `crossmatch/uploads/`
- `deduplication/uploads/`

Confirmed database stores include:
- `contact_messages`, `contact_message_recipients`, `contact_replies`, `message_reads`
- `events`, `event_guests`
- `incoming`, `outgoing`, `aatracker`
- `breakdown`, `fund_monitoring_items`, `fund_monitoring_entries`
- `project_lawa_binhi_targets`, `project_target_entries`, `program_activity_metadata`, `program_activity_actual_projects`
- `audit_logs`, `mail_logs`, `app_notifications`, `app_notification_reads`
- `crossmatch_jobs`, `crossmatch_results`, `deduplication_jobs`, `deduplication_results`

### 5.5 Key Privacy Risks
- Multiple upload directories increase the attack surface and storage footprint for personal/operational files
- Internal messaging and event invitation features process email addresses, message content, and attachments
- Logs may replicate personal or operational details beyond the main transaction tables
- Map and external tile services create third-party exposure points tied to location processing
- Utility tools can create large secondary data stores of beneficiary matching or duplicate results
- Hybrid legacy/normalized data structures may complicate consistent retention and source-of-truth decisions

### 5.6 Existing Controls
Confirmed controls include:
- MIME/type validation and filename sanitization on multiple upload paths
- Role and page-level access checks for most operational modules
- CSRF and same-origin protections on write endpoints
- Audit and mail logging for key state changes and communications
- Socket broadcasting behind environment-configured token logic

### 5.7 Residual Gaps Requiring Owner Confirmation
- Whether all upload directories are protected from direct public access in production
- Whether production backups include uploads and whether those backups are encrypted
- Whether antivirus/malware scanning exists on uploaded files
- Whether utility modules are intentionally open to non-admin operational users in production
- Whether drive links and exported files are subject to separate organizational handling rules

## 6. Data Flow and Processing Activities
This section is intentionally derived from [PIA_DATA_FLOW_NOTES.md](/c:/laragon/www/kodus/docs/PIA_DATA_FLOW_NOTES.md).

### 6.1 Processing Activities
- Collection of account data during registration, settings updates, login, reset, and SSO flows
- Collection of beneficiary master-list data through spreadsheet import and manual edits
- Collection of operational data through uploads, inbox messages, event invitations, and implementation forms
- Authentication and account security processing including remember-me, reset, 2FA, and role/security administration
- Retrieval, search, filtering, and dashboard/report aggregation
- Editing, validation, and change-history recording
- Export, download, print, and generated output creation
- Email delivery and notification handling
- Logging, audit, notification, and live-refresh processing

### 6.2 Consolidated Data Flows
- Registration/account flow
  - User enters profile and password -> `users` row created -> email notification and logs generated.
- Login/security flow
  - User authenticates -> optional 2FA completes -> session established -> login alert may be sent and logged.
- Password reset flow
  - Email submitted -> reset token stored -> reset link sent -> new password completed through reset flow.
- Beneficiary intake/update/validation flow
  - Admin imports MEB spreadsheet -> records inserted into `meb` -> updates and validations modify `meb` -> audit and change-history entries created.
- Reporting/export flow
  - MEB and summary pages query beneficiary data -> outputs are exported as XLSX/CSV or rendered as reports.
- Messaging flow
  - Compose/reply actions store threads, recipients, replies, and attachments -> email may also be sent -> read states update.
- Document-routing flow
  - Files and routing metadata are uploaded -> stored in DB and local directories -> records may be forwarded or updated.
- Implementation-status flow
  - Program target/activity data is saved -> location and accomplishment data stored -> maps and records pages display outputs.
- Utility flow
  - Crossmatch/dedup users upload datasets -> jobs and results are stored -> exports/downloads generated.

## 7. Third-Party Exposure
Confirmed external exposure points include:
- SMTP provider for account, event, and inbox/contact email
- Caraga Connect SSO / OAuth
- Optional socket bridge / realtime server
- OpenStreetMap and Esri tile services
- Nager.Date and Official Gazette holiday lookups
- Externally stored drive links referenced in implementation records

The formal PIA should describe these as third-party or external transfer points and confirm what data is shared, under what authority, and with what safeguards.

## 8. Existing Controls and Residual Risk Position
KODUS has multiple implemented technical controls, especially around authentication, request protection, uploads, and auditability. However, the privacy impact remains high because of the nature of the beneficiary data, the presence of export and utility workflows, and the number of secondary repositories where personal data can appear.

The system therefore appears suitable for a formal PIA only if the operational owner also confirms retention, access governance, backup handling, upload protection, and export/data-sharing rules.

## 9. Open Operational Questions to be Completed by the Owner
The following should be finalized using [PIA_OWNER_ANSWERS_DRAFT.md](/c:/laragon/www/kodus/docs/PIA_OWNER_ANSWERS_DRAFT.md):
- Retention period for primary records, logs, uploads, and generated outputs
- Production protection of upload directories
- Backup handling and encryption
- Production source-of-truth decision for hybrid legacy/normalized modules
- Utility access restrictions
- Export authorization and review
- Presence of supporting beneficiary documents in uploads
- External sharing practices after export/email/drive-link use
- Actual use of SSO-linked ID/contact fields
- Formal access review process for privileged roles

## 10. Conclusion
The current KODUS implementation should be assessed as a high-impact privacy processing system with three major domains of concern:
- User account and admin/security data
- Beneficiary master list and related outputs
- Operational and supporting data such as uploads, messaging, logs, maps, and utilities

The technical basis for this conclusion is already documented in the evidence appendix. The remaining work for a submission-ready PIA is primarily operational clarification and final policy statements from the system owner.
