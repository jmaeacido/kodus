# KODUS Privacy Impact Assessment Scope Audit

## 1. Executive Summary
- The PIA scope should cover the full KODUS application stack that processes user accounts, the Master List of Eligible Beneficiaries (`meb`), implementation-status records tied to beneficiary counts and project locations, document-routing uploads, internal messaging, calendar invitations, payout/fund-monitoring records, and data-quality utilities that upload or generate beneficiary datasets.
- Based on the codebase and live database, KODUS indicates a **high privacy impact**. Confirmed reasons include direct beneficiary identifiers (`lastName`, `firstName`, `middleName`, `ext`), birth date, sex, granular location, poverty/eligibility indicators (`nhts1`, `nhts2`), sectoral classifications (`PWD`, `lgbtqia`, `FR`, `ybDs`), user credentials and 2FA artifacts, exports to Excel/CSV, file attachments, email delivery, audit/mail logs, and project-location coordinates / drive links in implementation monitoring.
- This assessment is evidence-based from application code plus the live configured database `kodus_db` scanned through `config.php`. Where the code and live schema diverge, both are called out because the PIA boundary should include the deployed hybrid state, not just the intended refactor target.

## 2. Systems and Modules in Scope
- Module/Page/Feature: User registration, login, password reset, remember-me, 2FA, recovery codes, account settings
  - Why it is in scope: Processes account identity, credentials, email, profile, session/authentication state, and security metadata.
  - Evidence: `register.php`, `login.php`, `send-reset-link.php`, `reset-password.php`, `settings.php`, `save_profile_settings.php`, `auth_helpers.php`, `two_factor_helpers.php`, live DB table `users`
- Module/Page/Feature: User administration and account governance
  - Why it is in scope: Admins view user profiles, roles, activity, 2FA status, deactivation, restore, password-security actions, and audit logs.
  - Evidence: `admin/users_management.php`, `admin/change_user_type.php`, `admin/deactivate_user.php`, `admin/restore_user.php`, `admin/password_security.php`, `admin/password_security_action.php`, `admin/audit_logs.php`, live DB tables `users`, `audit_logs`, `mail_logs`
- Module/Page/Feature: MEB master list intake, browse, edit, validation, delete/export
  - Why it is in scope: Core beneficiary repository with direct personal and potentially sensitive attributes.
  - Evidence: `pages/import.php`, `pages/fetch_data.php`, `pages/data-tracking-meb.php`, `pages/data-tracking-meb-edit.php`, `pages/update.php`, `pages/data-tracking-meb-validation.php`, `pages/update_validation_status.php`, `pages/export_meb.php`, live DB tables `meb`, `meb_change_history`
- Module/Page/Feature: Beneficiary reports and profile exports
  - Why it is in scope: Displays and exports beneficiary data and sensitive segment summaries.
  - Evidence: `home.php`, `get_data.php`, `pages/summary/beneficiary-profile.php`, `pages/summary/fetch_data_profile.php`, `pages/summary/export_profile.php`, `pages/summary/sectoral.php`, `pages/summary/pwd/pwd.php`, `pages/summary/pwd/sex-disaggregated-pwd.php`
- Module/Page/Feature: Implementation Status baseline targets, activities, maps, records, summaries
  - Why it is in scope: Stores municipality/barangay/purok-level project and beneficiary counts, coordinates, land/ownership details, accomplishment status, and drive links.
  - Evidence: `implementation-status/program-targets.php`, `implementation-status/save-project-target.php`, `project_targets_helpers.php`, `implementation-status/program-activities.php`, `implementation-status/save-imp-status.php`, `implementation-status/project-location-maps.php`, `implementation-status/project-location-records.php`, `implementation-status/fetch-program-summary.php`, live DB tables `project_lawa_binhi_targets`, `project_target_entries`, `program_activity_metadata`, `program_activity_actual_projects`
- Module/Page/Feature: Incoming/outgoing/action-and-approval document tracking
  - Why it is in scope: Stores filenames, file metadata, routing details, remarks, receiving office, and uploaded document files.
  - Evidence: `pages/track_incoming.php`, `pages/update_data.php`, `pages/track_outgoing.php`, `pages/update_data_out.php`, `pages/forward_document.php`, `pages/save_document.php`, live DB tables `incoming`, `outgoing`, `aatracker`
- Module/Page/Feature: Payout and fund monitoring
  - Why it is in scope: Stores beneficiary-count and amount data by province/municipality/barangay and admin updates to financial monitoring entries.
  - Evidence: `pages/payout.php`, `pages/update_payout.php`, `pages/update_payout_group.php`, `pages/fund-monitoring.php`, `pages/save_fund_monitoring.php`, live DB tables `breakdown`, `fund_monitoring_items`, `fund_monitoring_entries`
- Module/Page/Feature: Internal messaging / inbox / contact form
  - Why it is in scope: Stores sender identity, recipient identity/email, message content, attachments, replies, read/trash states, and sends email copies/notifications.
  - Evidence: `contact.php`, `send_contact.php`, `inbox/index.php`, `inbox/get_thread.php`, `inbox/send_reply.php`, `inbox/send_reply_mail.php`, `inbox/mailbox_helpers.php`, live DB tables `contact_messages`, `contact_message_recipients`, `contact_replies`, `message_reads`
- Module/Page/Feature: Calendar and event invitations
  - Why it is in scope: Stores event titles/descriptions, location, privacy flag, guest emails/names, and email reminders.
  - Evidence: `pages/calendar.php`, `pages/add_event.php`, `pages/update_event.php`, `pages/delete_event.php`, `pages/sendEventEmails.php`, live DB tables `events`, `event_guests`
- Module/Page/Feature: Crossmatch, deduplication, MEBIS utilities
  - Why it is in scope: Uploads and processes beneficiary datasets, stores result rows, and generates downloadable outputs.
  - Evidence: `crossmatch/index.php`, `crossmatch/upload_handler.php`, `crossmatch/run_job.php`, `crossmatch/export.php`, `deduplication/index.php`, `deduplication/upload_handler.php`, `deduplication/export_results.php`, `mebis-consolidator/index.php`, `mebis-consolidator/helpers/history.php`, `mebis-lgu-template/index.php`, `mebis-lgu-template/helpers/history.php`, live DB tables `crossmatch_jobs`, `crossmatch_results`, `deduplication_jobs`, `deduplication_results`, `mebis_consolidator_outputs`, `mebis_lgu_template_outputs`

## 3. Personal Data Inventory

### User Account Data

| Data Category | Specific Field / Data Element | Data Subject | Where Collected | Where Stored | Where Displayed/Used | Sensitivity Level (Personal / Sensitive / Potentially Sensitive) | Evidence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| User Account Data | `username`, `email`, `first_name`, `middle_name`, `last_name`, `ext`, `position`, `positionAbr`, `area` | App user | `register.php`, `save_profile_settings.php`, SSO callback flow in `sso_helpers.php` | Live DB `users` | `settings.php`, `sidenav.php`, `admin/users_management.php`, session in `auth_helpers.php` | Personal | `register.php`, `save_profile_settings.php`, `auth_helpers.php`, live DB `users` columns |
| User Account Data | Password hash | App user | `register.php`, `save_profile_settings.php`, `update-password.php`, `reset-password.php` | Live DB `users.password` | Login verification and password-policy checks | Sensitive | `register.php`, `login.php`, `security.php`, live DB `users.password` |
| User Account Data | Remember-me token | App user | Login remember-me flow | Live DB `users.remember_token`, cookie | Auto-login restoration | Sensitive | `auth_helpers.php`, `security.php`, live DB `users.remember_token` |
| User Account Data | Reset token and expiry | App user | Forgot-password / password-policy reset issuance | Live DB `users.reset_token`, `users.reset_token_expiry` | Reset-link validation | Sensitive | `send-reset-link.php`, `password_policy_helpers.php`, `reset-password.php`, live DB columns |
| User Account Data | 2FA enabled flag, TOTP secret, recovery codes, 2FA confirmation/generated dates, temporary code/expiry | App user | `begin_2fa_setup.php`, `verify_2fa_code.php`, `settings.php` | Live DB `users.two_fa_*` columns | 2FA setup, verification, recovery, admin reset | Sensitive | `two_factor_helpers.php`, `verify_2fa_code.php`, `admin/reset_user_2fa.php`, live DB columns |
| User Account Data | Profile photo filename and optional SSO avatar URL | App user | `save_profile_settings.php`, SSO sync in `sso_helpers.php` | Live DB `users.picture`, `users.sso_avatar_url`; local files under `dist/img/` | `settings.php`, `sidenav.php`, inbox UI | Personal | `save_profile_settings.php`, `avatar_helpers.php`, `sso_helpers.php`, live DB columns |
| User Account Data | SSO subject, ID number, contact number | App user | SSO integration | Live DB `users.sso_subject`, `id_number`, `contact_number` | SSO matching/provisioning | Personal / Potentially Sensitive | `sso_helpers.php`, live DB `users` columns |
| User Account Data | User role/type, deleted/deactivated state, role-change metadata | App user | Admin user management | Live DB `users.userType`, `deleted_at`, `role_change_*` | Access control, forced logout, admin screens | Potentially Sensitive | `admin/change_user_type.php`, `admin/deactivate_user.php`, `role_change_helpers.php`, live DB columns |
| User Account Data | Last login, last activity, online status, theme preference | App user | Auth/session flows and settings | Live DB `users.last_login_at`, `last_activity`, `is_online`, `theme_preference` | Home/admin/user presence/live refresh | Personal | `auth_helpers.php`, `live_refresh.php`, `admin/users_management.php`, live DB columns |

### Beneficiary Data

| Data Category | Specific Field / Data Element | Data Subject | Where Collected | Where Stored | Where Displayed/Used | Sensitivity Level (Personal / Sensitive / Potentially Sensitive) | Evidence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Beneficiary Data | `lastName`, `firstName`, `middleName`, `ext` | Beneficiary | Excel import in `pages/import.php`; admin edits in `pages/update.php` | Live DB `meb` | MEB pages, profile/report exports, crossmatch/dedup datasets | Personal | `pages/import.php`, `pages/fetch_data.php`, `pages/export_meb.php`, live DB `meb` |
| Beneficiary Data | `purok`, `barangay`, `lgu`, `province` | Beneficiary | Import/edit flows | Live DB `meb` | MEB pages, dashboard/reporting, implementation summaries, payout grouping | Personal | `pages/import.php`, `get_data.php`, `pages/summary/fetch_data_profile.php`, live DB `meb` |
| Beneficiary Data | `birthDate`, `age`, `sex`, `civilStatus` | Beneficiary | Import/edit flows | Live DB `meb` | MEB pages, beneficiary profile export, sex-disaggregated reports | Personal / Potentially Sensitive | `pages/import.php`, `pages/update.php`, `pages/export_meb.php`, `pages/summary/pwd/sex-disaggregated-pwd.php`, live DB `meb` |
| Beneficiary Data | Eligibility / poverty indicators: `nhts1`, `nhts2` | Beneficiary | Import/edit flows | Live DB `meb` | Dashboard, export, validation, summaries | Potentially Sensitive | `pages/import.php`, `get_data.php`, `pages/export_meb.php`, live DB `meb` |
| Beneficiary Data | Program/sector tags: `fourPs`, `F`, `FF`, `IS`, `IP`, `SC`, `SP`, `LW`, `PW`, `PWD`, `OSY`, `FR`, `ybDs`, `lgbtqia` | Beneficiary | Import/edit flows | Live DB `meb` | Dashboard sector charts, profile exports, PWD reports, crossmatch/dedup source files | Sensitive / Potentially Sensitive | `pages/import.php`, `pages/update.php`, `home.php`, `pages/summary/pwd/*.php`, live DB `meb` |
| Beneficiary Data | Validation state and edit reason | Beneficiary | Validation page and edit page | Live DB `meb.validation`, `meb.editReason`; `meb_change_history` before/after JSON | MEB validation review and audit trail | Potentially Sensitive | `pages/update_validation_status.php`, `pages/update.php`, `meb_change_history_helpers.php`, live DB tables |
| Beneficiary Data | Full before/after snapshots of beneficiary records | Beneficiary | On MEB update | Live DB `meb_change_history.before_json`, `after_json` | Change review page | Sensitive | `meb_change_history_helpers.php`, `pages/meb-change-review.php`, live DB `meb_change_history` |
| Beneficiary Data | Beneficiary-count by province/municipality/barangay and payout amounts | Beneficiary cohort | Payout management and implementation reporting | Live DB `breakdown` | `pages/payout.php`, implementation summaries | Potentially Sensitive | `pages/payout.php`, `pages/update_payout.php`, live DB `breakdown` |
| Beneficiary Data | Project/activity linkage and location coverage: purok, coordinates, project name/type/classification, land area/ownership, status, drive link, accomplishment values | Beneficiary cohort / project participants | Program activity save flow | Live DB `program_activity_metadata`, `program_activity_actual_projects` and legacy packed coverage columns | Implementation pages, project maps/records, summaries | Potentially Sensitive | `implementation-status/save-imp-status.php`, `implementation-status/project-location-maps.php`, `implementation-status/project-location-records.php`, live DB tables |

### System/Operational Metadata

| Data Category | Specific Field / Data Element | Data Subject | Where Collected | Where Stored | Where Displayed/Used | Sensitivity Level (Personal / Sensitive / Potentially Sensitive) | Evidence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| System/Operational Metadata | IP address, action, details, timestamp | App user / actor | Security and state-changing requests | Live DB `audit_logs` | `admin/audit_logs.php` | Potentially Sensitive | `audit_helpers.php`, `config.php`, live DB `audit_logs` |
| System/Operational Metadata | Mail recipient, subject, status, message | App user / recipient | Email sends for login, reset, registration, contact, 2FA, admin actions | Live DB `mail_logs` | Operational review only | Potentially Sensitive | `notification_helpers.php`, `register.php`, `login.php`, live DB `mail_logs` |
| System/Operational Metadata | Session user identity and auth state in `$_SESSION` | App user | Login / SSO / 2FA flows | PHP session storage | Access control, settings, live refresh, notifications | Potentially Sensitive | `auth_helpers.php`, `security.php`, `verify-2fa.php` |
| System/Operational Metadata | Inbox sender/recipient email, message body, attachment filenames, reply body, read/trash state | App user / staff | Contact and inbox forms | Live DB `contact_messages`, `contact_message_recipients`, `contact_replies`, `message_reads`; files under `inbox/uploads/` | Inbox UI, email notifications | Personal / Potentially Sensitive | `send_contact.php`, `inbox/send_reply.php`, `inbox/get_thread.php`, live DB tables |
| System/Operational Metadata | Calendar guest email/name and event metadata | App user / invited guest | Calendar event forms | Live DB `events`, `event_guests` | Calendar UI, event reminder emails | Personal | `pages/add_event.php`, `pages/sendEventEmails.php`, live DB tables |
| System/Operational Metadata | Uploaded document filenames, MIME type, size, timestamps, user log | Staff / possibly third-party names inside documents | Incoming/outgoing/action tracker forms | Live DB `incoming`, `outgoing`, `aatracker`; files in `pages/uploads/`, `storage/aatracker/` | Tracking pages, forwarding, popups | Potentially Sensitive | `pages/track_incoming.php`, `pages/track_outgoing.php`, `pages/save_document.php`, live DB tables |
| System/Operational Metadata | Crossmatch/dedup job metadata plus result payloads (`record_json`, `candidates_json`, `row_data`) | Beneficiaries / uploaded dataset subjects | Upload utilities | Live DB `crossmatch_jobs`, `crossmatch_results`, `deduplication_jobs`, `deduplication_results`; uploaded files in utility folders | Results pages and exports | Sensitive | `crossmatch/upload_handler.php`, `crossmatch/run_job.php`, `deduplication/upload_handler.php`, `deduplication/export_results.php`, live DB tables |

## 4. Processing Activities
- Collection
  - User self-registration collects names, position, area, username, email, password. Evidence: `register.php`.
  - User settings updates collect profile data, optional password, and avatar image. Evidence: `save_profile_settings.php`.
  - MEB import collects beneficiary rows from `.xls/.xlsx`. Evidence: `pages/import.php`.
  - Contact/inbox compose and reply forms collect message text, recipients, and attachments. Evidence: `send_contact.php`, `inbox/send_reply.php`.
  - Incoming/outgoing/action-tracker pages collect routing metadata and uploaded documents. Evidence: `pages/track_incoming.php`, `pages/track_outgoing.php`, `pages/save_document.php`.
  - Implementation-status forms collect project and accomplishment details, coordinates, land/ownership, and drive links. Evidence: `implementation-status/save-project-target.php`, `implementation-status/save-imp-status.php`.
- Encoding / normalization
  - Imported beneficiary spreadsheets are mapped column-by-column into `meb`. Evidence: `pages/import.php`.
  - Program target rows and actual project rows are normalized into child tables in helper/save code, while live DB still retains legacy packed columns. Evidence: `project_targets_helpers.php`, `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, live DB `project_lawa_binhi_targets`, `program_activity_metadata`.
  - Inbox recipients are synced into normalized recipient rows, but live DB also still contains legacy `contact_messages.recipient`. Evidence: `inbox/mailbox_helpers.php`, `send_contact.php`, live DB `contact_messages`.
- Authentication
  - Username/password verification with rate limiting, session regeneration, remember-me issuance, and deactivated-account checks. Evidence: `login.php`, `auth_helpers.php`, `security.php`.
  - SSO account matching/provisioning and token-based socket client participation. Evidence: `sso_helpers.php`, `socket_helpers.php`.
  - 2FA TOTP verification and recovery-code fallback. Evidence: `verify_2fa_code.php`, `two_factor_helpers.php`.
- Verification / validation
  - Admin-only beneficiary validation writes `meb.validation`. Evidence: `pages/data-tracking-meb-validation.php`, `pages/update_validation_status.php`.
  - Password policy enforcement forces reset for older/weak-password accounts. Evidence: `password_policy_helpers.php`, `login.php`.
- Storage
  - User, beneficiary, inbox, event, project, utility, and operational records are stored in MySQL tables. Evidence: live DB scan via `config.php`; `docs/KODUS_DATA_DICTIONARY.md`.
  - Uploaded files are stored on disk in app folders. Evidence: `save_profile_settings.php`, `send_contact.php`, `inbox/send_reply.php`, `pages/track_incoming.php`, `pages/save_document.php`, utility upload handlers.
- Retrieval / search
  - MEB list supports search by name and location. Evidence: `pages/fetch_data.php`.
  - Beneficiary profile reporting supports table filtering and export. Evidence: `pages/summary/fetch_data_profile.php`, `pages/summary/export_profile.php`.
  - Inbox queries retrieve message metadata, avatars, read states, and thread contents. Evidence: `inbox/fetch_messages.php`, `inbox/get_thread.php`.
  - Live refresh fingerprints poll for MEB, incoming/outgoing, user-status, and utility changes. Evidence: `live_refresh.php`.
- Editing / update
  - Admin edits MEB rows and writes change history. Evidence: `pages/update.php`, `meb_change_history_helpers.php`.
  - Admin/settings pages update user account metadata, role, activation state, 2FA state, and password reset requirements. Evidence: `save_profile_settings.php`, `admin/change_user_type.php`, `admin/deactivate_user.php`, `admin/reset_user_2fa.php`, `admin/password_security_action.php`.
  - Incoming/outgoing, payout, fund monitoring, project targets, and program activities are editable. Evidence: `pages/update_data.php`, `pages/update_data_out.php`, `pages/update_payout*.php`, `pages/save_fund_monitoring.php`, `implementation-status/save-project-target.php`, `implementation-status/save-imp-status.php`.
- Export / download / print
  - Full MEB export to XLSX. Evidence: `pages/export_meb.php`.
  - Beneficiary profile and summary exports. Evidence: `pages/summary/export_profile.php`, `pages/summary/export.php`, `pages/summary/pwd/*_export.php`.
  - Crossmatch and dedup results export. Evidence: `crossmatch/export.php`, `deduplication/export_results.php`.
  - Recovery-code print page. Evidence: `recovery_codes_print.php`.
  - Attachment zip download in inbox. Evidence: `inbox/download_all.php`.
- Email sending / notification
  - Registration, password reset, login alerts, 2FA alerts/reset alerts, event emails, and contact/inbox email delivery. Evidence: `notification_helpers.php`, `register.php`, `send-reset-link.php`, `login.php`, `verify_2fa_code.php`, `pages/sendEventEmails.php`, `send_contact.php`, `inbox/send_reply_mail.php`.
- Logging / auditing
  - State-changing requests and explicit actions write `audit_logs`. Evidence: `config.php`, `audit_helpers.php`.
  - Outbound email outcomes write `mail_logs`. Evidence: `notification_helpers.php`, `register.php`, `login.php`.
  - Beneficiary edits write before/after JSON snapshots. Evidence: `meb_change_history_helpers.php`.
- Deletion / soft delete / restore
  - Users are soft-deleted with `deleted_at` and can be restored. Evidence: `delete_account.php`, `admin/deactivate_user.php`, `admin/restore_user.php`, live DB `users.deleted_at`.
  - Inbox includes delete-for-me, trash state, and delete-for-everyone behaviors. Evidence: `inbox/delete_message.php`, `inbox/delete_reply.php`, `inbox/bulk_actions.php`, live DB `message_reads`, `contact_replies`.
  - MEB batch deletion exists. Evidence: `pages/delete_batch.php`.
- File upload
  - Avatar upload, contact/reply attachments, incoming/outgoing files, action-tracker files, crossmatch/dedup uploads. Evidence: `save_profile_settings.php`, `send_contact.php`, `inbox/send_reply.php`, `pages/track_incoming.php`, `pages/track_outgoing.php`, `pages/save_document.php`, utility upload handlers.
- Geolocation / location context
  - Project-location maps use stored latitude/longitude and display map markers. Evidence: `implementation-status/project-location-maps.php`, `implementation-status/fetch-project-location-maps.php`, live DB `program_activity_actual_projects.latitude/longitude`.
  - Session location context is stored for UI context. Evidence: `save_location_context.php`.

## 5. Data Flow Clues from Implementation
- User registration/login/account management flow
  - User submits registration form in `register.php` -> app validates required fields/email/password strength -> password is hashed -> row inserted into `users` -> audit record written -> welcome email sent and logged in `mail_logs`.
  - User submits credentials in `login.php` -> rate limit check in session -> password verified against `users.password` -> password-policy enforcement may issue reset token -> if 2FA enabled, session stores `2fa_user_id` and redirects to `verify-2fa.php` -> successful login updates `last_login_at`, regenerates session, may issue `remember_token`, and sends/logs login alert.
  - User updates settings in `save_profile_settings.php` -> profile fields and optional password/avatar written to `users` -> avatar file moved to `dist/img/`.
- Beneficiary master list intake/update/use flow
  - Admin uploads Excel in `pages/import.php` -> expected MEB columns are validated -> each row inserted into `meb` with batch ID.
  - MEB pages fetch/search rows from `meb` through `pages/fetch_data.php` and related reporting endpoints.
  - Admin edits rows in `pages/update.php` -> `meb` row updated -> `meb_change_history` stores before/after JSON -> `audit_logs` captures the change -> socket/live refresh events notify open tables.
  - Admin validates rows in `pages/update_validation_status.php` -> `meb.validation` updated -> `audit_logs` and app notifications updated.
  - Dashboard/reports aggregate `meb` by sex, NHTS, sectoral categories, and location in `get_data.php` and summary pages.
- Export/reporting flow
  - MEB export page queries `meb` for selected year -> writes XLSX with names, birth date, sex, NHTS, and sectoral fields -> streams file to browser. Evidence: `pages/export_meb.php`.
  - Profile/sectoral/PWD reports query `meb` -> render browser tables and export spreadsheets. Evidence: `pages/summary/*`.
  - Crossmatch/dedup export flows serialize uploaded/live beneficiary comparison results to CSV/XLSX. Evidence: `crossmatch/export.php`, `deduplication/export_results.php`.
- Email/2FA/notification flow
  - SMTP config is loaded from environment in `mail_config.php`.
  - Password reset generates token -> stores hashed token in `users` -> sends reset link by PHPMailer. Evidence: `send-reset-link.php`.
  - Login/2FA/admin security actions send notification emails and write `mail_logs`. Evidence: `login.php`, `verify_2fa_code.php`, `admin/reset_user_2fa.php`, `notification_helpers.php`.
  - Contact/inbox messages are stored in DB, recipients normalized, attachments saved on disk, and outgoing email sent to recipients. Evidence: `send_contact.php`, `inbox/send_reply_mail.php`.
- File upload/storage flow
  - Avatars -> `save_profile_settings.php` -> `dist/img/`.
  - Contact attachments -> `send_contact.php` -> `inbox/uploads/contact_attachments/`.
  - Reply attachments -> `inbox/send_reply.php` -> `inbox/uploads/reply_attachments/`.
  - Incoming/outgoing files -> `pages/track_incoming.php` / `pages/track_outgoing.php` -> `pages/uploads/`.
  - Action tracker files -> `pages/save_document.php` -> `storage/aatracker/`.
  - Crossmatch/dedup source files -> utility upload handlers -> `crossmatch/uploads/`, `deduplication/uploads/`.

## 6. Roles and Access Scope
- Role name: `admin`
  - Data it can access: Full user-account data, MEB records, validation, reports, inbox/admin-visible conversations, audit logs, password-security screens, implementation targets/activities, utilities, document tracking, payouts, fund monitoring.
  - Data it can modify: User roles/activation/2FA reset/password reset actions, MEB rows/validation/import/delete, project variables, incoming/outgoing, payouts, fund monitoring, events, implementation target/activity data.
  - Sensitive pages/features reachable: `admin/users_management.php`, `admin/audit_logs.php`, `admin/password_security.php`, `pages/data-tracking-meb-validation.php`, generators under `mebis-consolidator/` and `mebis-lgu-template/`.
  - Evidence: `auth_helpers.php`, `sidenav.php`, `admin/*.php`, `pages/update*.php`
- Role name: `editor`
  - Data it can access: Operations workspace, reports, implementation status, inbox, beneficiary profile reporting.
  - Data it can modify: Program targets, program activities, project variables; can view operations data.
  - Sensitive pages/features reachable: `implementation-status/program-targets.php`, `implementation-status/program-activities.php`, `admin/project_variables.php`.
  - Evidence: `auth_helpers.php` (`auth_can_manage_program_targets`, `auth_can_manage_program_activities`, `auth_can_manage_project_variables`), `sidenav.php`, `implementation-status/save-*.php`
- Role name: `aa`
  - Data it can access: Operations pages and payout/incoming/outgoing tracking; inbox and reports available through non-user routing.
  - Data it can modify: Incoming/outgoing entries and payout updates where pages explicitly allow `admin` or `aa`.
  - Sensitive pages/features reachable: `pages/data-tracking-in.php`, `pages/data-tracking-out.php`, `pages/payout.php`.
  - Evidence: `auth_helpers.php` (`auth_can_view_operations`), `pages/payout.php`, `pages/update_payout.php`, JS/page checks in `pages/data-tracking-in.php`, `pages/data-tracking-out.php`
- Role name: `user`
  - Data it can access: Own account, calendar, inbox/contact, general reporting pages; cannot access operations workspace.
  - Data it can modify: Own settings, inbox/contact activity, possibly self-created calendar events.
  - Sensitive pages/features reachable: Inbox threads they participate in, account settings, recovery code print, possibly some reports not hidden from `user`.
  - Evidence: `auth_helpers.php` (`auth_can_view_operations`), `sidenav.php`, `settings.php`, `inbox/*.php`

## 7. Storage Locations and Repositories of Data
- Database tables
  - `users`, `meb`, `meb_change_history`, `audit_logs`, `mail_logs`, `contact_messages`, `contact_message_recipients`, `contact_replies`, `message_reads`, `incoming`, `outgoing`, `aatracker`, `breakdown`, `project_lawa_binhi_targets`, `project_target_entries`, `program_activity_metadata`, `program_activity_actual_projects`, `crossmatch_jobs`, `crossmatch_results`, `deduplication_jobs`, `deduplication_results`, `mebis_consolidator_outputs`, `mebis_lgu_template_outputs`, `events`, `event_guests`, `app_notifications`, `app_notification_reads`, `fund_monitoring_items`, `fund_monitoring_entries`, `project_variable_config`.
  - Evidence: live DB scan via `config.php`; `docs/KODUS_DATA_DICTIONARY.md`
- Uploaded files/directories
  - `dist/img/` for user avatars.
  - `inbox/uploads/contact_attachments/` and `inbox/uploads/reply_attachments/` for inbox files.
  - `pages/uploads/` for incoming/outgoing document files.
  - `storage/aatracker/` for action-tracker files.
  - `crossmatch/uploads/` and `deduplication/uploads/` for uploaded utility datasets.
  - Evidence: `save_profile_settings.php`, `send_contact.php`, `inbox/send_reply.php`, `pages/track_incoming.php`, `pages/track_outgoing.php`, `pages/save_document.php`, `crossmatch/upload_handler.php`, `deduplication/upload_handler.php`
- Generated exports/outputs
  - Browser-streamed XLSX/CSV exports from MEB/report pages.
  - MEBIS generator outputs recorded in DB history tables and stored in generator output directories.
  - Dedup template history and utility result exports.
  - Evidence: `pages/export_meb.php`, `pages/summary/export_profile.php`, `crossmatch/export.php`, `deduplication/export_results.php`, `mebis-consolidator/helpers/history.php`, `mebis-lgu-template/helpers/history.php`
- Logs
  - Application audit entries in `audit_logs`.
  - Email delivery logs in `mail_logs`.
  - PHP/server error logging enabled by `security_configure_runtime_for_web()`; destination not defined in repo.
  - Evidence: `audit_helpers.php`, `notification_helpers.php`, `security.php`
- Session data
  - PHP sessions store `user_id`, `username`, `email`, user type, 2FA temporary state, CSRF token, selected year, and notices.
  - Evidence: `auth_helpers.php`, `security.php`, `verify-2fa.php`, `select_year.php`
- Audit logs
  - `audit_logs` plus `meb_change_history` and `message_reads` provide operational/accountability traces.
  - Evidence: `audit_helpers.php`, `meb_change_history_helpers.php`, `inbox/*.php`
- Email logs
  - `mail_logs` stores recipient/subject/status/message.
  - Evidence: `notification_helpers.php`, live DB `mail_logs`

## 8. External Services and Third-Party Exposure
| Service | Purpose | Data possibly exposed | Evidence |
| --- | --- | --- | --- |
| SMTP provider configured by env vars (`SMTP_HOST`, `SMTP_USERNAME`, etc.) | Sends registration, password reset, login, 2FA, event, and inbox/contact emails | User email address, event recipient email, message subject/body, security-notification contents, attachments when emailing contact/reply messages | `mail_config.php`, `notification_helpers.php`, `.env.example`, `send_contact.php`, `pages/sendEventEmails.php` |
| Caraga Connect SSO / OAuth | External identity provider and optional logout/token endpoints | User identity attributes from IdP, access tokens, possible avatar URL, contact number, ID number | `sso_helpers.php`, `.env.example`, `socket_helpers.php`, `security.php` CSP allowlist |
| Socket bridge / realtime server | Broadcasts mailbox, MEB, incoming/outgoing, and other live updates | Event metadata such as message IDs, actor IDs, record IDs, counts; bearer token to socket service | `socket_helpers.php`, `header.php`, `.env.example`, calling sites in `pages/*`, `inbox/*` |
| OpenStreetMap tile servers | Map tiles for project location maps | Client IP/user agent to tile host; location context from viewed project area | `implementation-status/project-location-maps.php` |
| Esri ArcGIS tile services | Satellite / hybrid tile layers for project maps | Client IP/user agent to tile host; location context from viewed project area | `implementation-status/project-location-maps.php` |
| Nager.Date public holiday API | Calendar holiday retrieval | Server-side request metadata; queried year | `pages/fetch_events.php`, `pages/fetch_holidays.php` |
| Official Gazette holiday page | Holiday scraping fallback | Server-side request metadata; queried year | `pages/fetch_ph_holidays.php` |
| Google Drive links entered by users | External evidence/reference link for implementation records | Potential exposure through stored drive URLs associated with project/activity records | `implementation-status/program-activities.php`, `implementation-status/save-imp-status.php`, live DB `program_activity_actual_projects.drive_link` |

## 9. Existing Privacy and Security Controls Found
| Control | What it protects | Evidence | Gaps or limitations observed |
| --- | --- | --- | --- |
| Password hashing | User passwords at rest | `register.php`, `save_profile_settings.php`, `security.php` (`password_hash`, `password_verify`) | No repo evidence of separate secret pepper or breach-password screening |
| 2FA with TOTP and recovery codes | Account takeover risk | `two_factor_helpers.php`, `verify_2fa_code.php`, `settings.php`, live DB `users.two_fa_*` | Recovery codes are stored hashed, but 2FA is still user-account scoped only; no repo evidence of step-up auth for sensitive admin actions beyond admin role checks |
| CSRF tokens | Cross-site state-changing requests | `security.php`, forms in `register.php`, `settings.php`, `send_contact.php`, `pages/*` | Some legacy endpoints rely on direct `security_validate_csrf_token` patterns; consistency should be verified operationally |
| Same-origin enforcement | Blocks cross-site POST/PUT/PATCH/DELETE requests | `config.php`, `security.php` | Depends on header presence and host matching; reverse proxy/header handling needs deployment confirmation |
| Session hardening and regeneration | Session hijack/fixation risk | `security.php`, `auth_helpers.php` | Session storage backend and production retention not confirmed from repo |
| Rate limiting | Brute-force / reset abuse | `login.php`, `send-reset-link.php`, `security.php` | Uses PHP session-backed counters, so limits may be weak across browsers/devices and likely non-distributed |
| Role-based access control | Restricts admin/operations/implementation actions | `auth_helpers.php`, `header.php`, `sidenav.php`, admin and page-level checks | Some utility pages show commented-out admin checks, e.g. `crossmatch/index.php`, so utility exposure should be reviewed carefully |
| Audit logging | Accountability for state changes and page visits | `audit_helpers.php`, `config.php`, `admin/audit_logs.php` | Audit log content may include detailed field changes and user agent/IP; retention and review process unknown |
| MEB change history | Traceability of beneficiary edits | `meb_change_history_helpers.php`, `pages/meb-change-review.php` | Stores full before/after JSON snapshots, which increases sensitivity and retention burden |
| Upload MIME/type checks and filename sanitization | Malicious file upload risk | `security.php`, `save_profile_settings.php`, `send_contact.php`, `inbox/send_reply.php`, `pages/track_incoming.php`, `pages/save_document.php` | Upload directories appear web-accessible in several modules; no repo evidence of antivirus scanning or at-rest encryption |
| Soft delete / restore for users | Safer account deactivation workflow | `delete_account.php`, `admin/deactivate_user.php`, `admin/restore_user.php` | Soft-deleted user data remains in DB; retention/purge policy not visible |
| Security headers / CSP / HSTS / frame protection | Browser-side hardening | `security.php` | CSP still allows `'unsafe-inline'` and `'unsafe-eval'`; HTTPS enforcement depends on deployment config |
| Maintenance mode / restricted access | Limits exposure during maintenance | `config.php`, `error_helpers.php`, `admin/maintenance.php` | Operational use and notification timing need owner confirmation |

## 10. Likely PIA Risk Areas

### User account risks
- Credential and account-security data is concentrated in `users`, including remember/reset tokens and 2FA artifacts.
- Admin pages expose user email, role, presence, and 2FA state in one place.
- Audit and mail logs create secondary repositories of personal/operational data.
- SSO fields (`sso_subject`, `id_number`, `contact_number`) increase identity-linkage risk.

### Beneficiary data risks
- `meb` contains direct identifiers plus potentially sensitive sector/eligibility tags including `PWD`, `lgbtqia`, `FR`, and `ybDs`.
- Beneficiary exports are first-class features, increasing downstream copy/spread risk.
- `meb_change_history` stores full before/after snapshots, multiplying storage and disclosure impact.
- Implementation-status tables can associate location, project status, land details, and drive links with beneficiary coverage contexts.

### Export/reporting risks
- Full XLSX exports of beneficiary data exist and include names, birth date, sex, and eligibility/sectoral fields.
- Crossmatch and dedup utilities upload and generate large beneficiary datasets and result payloads.
- Generator/history modules create additional output repositories not limited to the core MEB table.

### Operational/admin risks
- Multiple local upload directories hold attachments and document files.
- Live DB shows hybrid legacy and normalized schemas, increasing the chance of duplicate repositories and inconsistent control application.
- Utility access control needs review because `crossmatch/index.php` contains commented admin-only gating.
- Realtime socket broadcasting, map tiles, SMTP delivery, and holiday APIs create third-party exposure points.

## 11. Gaps and Unknowns
- Retention schedule for `meb`, `audit_logs`, `mail_logs`, uploads, and generated utility outputs cannot be confirmed from the repo alone.
- Backup handling, backup encryption, and backup access controls are not visible in code.
- Production encryption at rest for database/files is not confirmed.
- Real deployment topology, reverse proxy behavior, and exact session-storage backend are not confirmed.
- Whether uploads are publicly web-accessible in production, behind auth, or protected by web-server rules needs operational confirmation.
- Whether exports are logged, approved, or restricted by policy is not evident.
- Data sharing outside the app, especially after export/download/email, is not visible from the repo.
- The live DB still retains legacy columns alongside normalized tables; the intended source of truth for each module should be confirmed before final PIA submission.
- The repo does not confirm formal access review, least-privilege review cadence, or incident-response procedures.
- The repo does not confirm whether actual beneficiary supporting documents are uploaded anywhere besides routing/inbox files.

## 12. Recommended PIA Coverage Boundary
- Include the entire KODUS web application and live database as one privacy processing system, not just the MEB screens.
- Treat the following as mandatory PIA scope areas:
  - User identity and account-security lifecycle
  - MEB beneficiary master list and related reports/exports
  - Beneficiary validation and edit-history workflows
  - Implementation-status targets/activities/maps/records
  - Incoming/outgoing/action-tracker uploads and routing metadata
  - Payout and fund-monitoring records where beneficiary counts/amounts are handled
  - Inbox/contact/calendar communications with email delivery and attachments
  - Crossmatch, deduplication, and MEBIS utility uploads/results/outputs
  - Audit logs, mail logs, app notifications, and session/auth metadata
  - Third-party transmission points: SMTP, SSO, socket bridge, map tiles, holiday APIs, and stored external drive links
- For submission use, the formal PIA should explicitly distinguish:
  - Confirmed implemented controls
  - Live-schema legacy artifacts still in operation
  - Operational questions requiring owner confirmation before sign-off

## 13. Evidence Appendix
- `config.php`: runtime bootstrap, DB connection, same-origin enforcement, schema helper invocation, audit logging on state changes.
- `security.php`: CSRF, session/cookie hardening, password strength, same-origin checks, MIME detection, CSP/HSTS/headers.
- `auth_helpers.php`: session storage, remember-me, role gates, last-login/activity tracking, operations/admin access logic.
- `register.php`: user registration fields, password hashing, welcome email, audit logging.
- `login.php`: rate limiting, password verification, 2FA redirect, remember-me, login alert.
- `two_factor_helpers.php`: 2FA schema, TOTP secret/recovery code handling.
- `admin/users_management.php`: active/deactivated user data surfaced to admins, role and 2FA visibility.
- `pages/import.php`: beneficiary spreadsheet intake and mapped MEB fields.
- `pages/update.php`: beneficiary editing, change history, audit logging.
- `pages/export_meb.php`: full beneficiary export payload.
- `pages/update_validation_status.php`: admin validation workflow for MEB rows.
- `send_contact.php`, `inbox/send_reply.php`, `inbox/get_thread.php`: messaging content, recipients, attachments, read/trash state.
- `pages/track_incoming.php`, `pages/track_outgoing.php`, `pages/save_document.php`: document uploads and tracking metadata.
- `implementation-status/save-project-target.php`, `implementation-status/save-imp-status.php`, `implementation-status/project-location-maps.php`: implementation monitoring, coordinates, project details, drive links.
- `crossmatch/*`, `deduplication/*`: uploaded beneficiary dataset processing and result storage.
- `docs/KODUS_DATA_DICTIONARY.md`: live-schema data dictionary basis for active configured database.
- Live DB scan via `config.php`: confirmed active database `kodus_db`, populated tables including `users` (18), `meb` (11,831), `audit_logs` (8,241), `mail_logs` (971), `crossmatch_results` (13,955), `deduplication_results` (1,691), and hybrid legacy/normalized structures.
