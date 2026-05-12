# KODUS Data Dictionary

Last reviewed: 2026-05-12

This dictionary is based on the current PHP codebase and documented live-schema evidence. It is intended for PIA, hosting, GPD, and replication review. The exact production schema should still be confirmed against the live database before formal sign-off.

## 1. System Domains

| Domain | Main Tables | Main Code Evidence | Data Sensitivity |
|---|---|---|---|
| Accounts and access control | `users`, `app_settings` | `auth_helpers.php`, `security.php`, `two_factor_helpers.php`, `sso_helpers.php`, `admin/users_management.php` | Personal / Sensitive security metadata |
| Audit and mail logging | `audit_logs`, `mail_logs`, `meb_change_history` | `audit_helpers.php`, `notification_helpers.php`, `meb_change_history_helpers.php`, `admin/audit_logs.php` | Potentially sensitive |
| MEB beneficiary records | `meb`, `meb_change_history` | `pages/import.php`, `pages/meb_import_helpers.php`, `pages/update.php`, `pages/data-tracking-meb.php`, `pages/export_meb.php` | Sensitive personal information |
| MEB validation | `meb`, `project_lawa_binhi_targets` | `pages/data-tracking-meb-validation.php`, `pages/fetch_data_validation_admin.php`, `pages/update_validation_status.php` | Sensitive aggregated and personal information |
| RRP-CFTW / LAWA and BINHI targets | `project_lawa_binhi_targets`, `project_target_entries` | `project_targets_helpers.php`, `implementation-status/save-project-target.php` | Operational / potentially sensitive by location |
| Implementation status | `program_activity_metadata`, `program_activity_actual_projects` | `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/project-location-records.php`, `implementation-status/project-location-maps.php` | Operational / potentially sensitive |
| Project variables | `project_variable_config` | `project_variable_helpers.php`, `admin/project_variables.php` | Operational |
| Payout | `breakdown` | `pages/payout.php`, `pages/update_payout.php`, `pages/update_payout_group.php` | Sensitive financial/beneficiary aggregate data |
| Fund monitoring | `fund_monitoring_object_codes`, `fund_monitoring_items`, `fund_monitoring_entries` | `fund_monitoring_helpers.php`, `pages/fund-monitoring.php`, `pages/save_fund_monitoring.php` | Operational financial data |
| Document tracking | `incoming`, `outgoing`, `aatracker` | `pages/data-tracking-in.php`, `pages/data-tracking-out.php`, `pages/save_document.php`, `pages/forward_document.php` | Operational / may contain sensitive references |
| Messaging | `contact_messages`, `contact_message_recipients`, `contact_replies`, `message_reads` | `send_contact.php`, `inbox/mailbox_helpers.php`, `inbox/*.php` | Personal / operational |
| Notifications | `app_notifications`, `app_notification_reads` | `app_notification_helpers.php`, `notifications/*.php` | Operational metadata |
| Calendar | `events`, `event_guests`, `event_schedule_days`, `draggable_events` | `pages/calendar.php`, `pages/event_schedule_helpers.php`, `pages/sendEventEmails.php` | Personal / operational |
| Crossmatch | `crossmatch_jobs`, `crossmatch_results` | `crossmatch/upload_handler.php`, `crossmatch/run_job.php`, `crossmatch/helpers/fuzzy.php` | Sensitive uploaded dataset/results |
| Deduplication | `deduplication_jobs`, `deduplication_results`, template output/history tables | `deduplication/upload_handler.php`, `deduplication/worker_v2.php`, `deduplication/helpers/*` | Sensitive uploaded dataset/results |
| MEBIS utilities | `mebis_consolidator_outputs`, `mebis_lgu_template_jobs`, `mebis_lgu_template_outputs` | `mebis-consolidator/helpers/history.php`, `mebis-lgu-template/helpers/jobs.php`, `mebis-lgu-template/helpers/history.php` | Sensitive if outputs include beneficiary records |

## 2. Current Database Snapshot

Snapshot taken from the configured database on 2026-05-12:

| Table | Rows |
|---|---:|
| `users` | 23 |
| `meb` | 13,345 |
| `meb_change_history` | 53 |
| `audit_logs` | 15,125 |
| `mail_logs` | 1,096 |
| `project_lawa_binhi_targets` | 109 |
| `project_target_entries` | 7 |
| `program_activity_metadata` | 91 |
| `program_activity_actual_projects` | 1 |
| `deduplication_jobs` | 100 |
| `deduplication_results` | 1,709 |
| `crossmatch_jobs` | 57 |
| `crossmatch_results` | 13,955 |
| `fund_monitoring_items` | 17 |
| `fund_monitoring_entries` | 68 |
| `app_notifications` | 139 |
| `contact_messages` | 20 |
| `events` | 19 |

These counts support system-use indicators but are not impact statistics by themselves.

## 3. Core Tables

### `users`

Purpose: user accounts, authentication state, role/area authorization, profile data, SSO links, 2FA state, activity status, and soft-delete state.

Important fields:

- identity: `username`, `email`, `first_name`, `middle_name`, `last_name`, `ext`, `position`, `positionAbr`, `area`
- access: `userType`, `deleted_at`, role-change metadata
- security: `password`, `remember_token`, `reset_token`, `reset_token_expiry`, `two_fa_*`
- SSO: `sso_subject`, `sso_avatar_url`, `id_number`, `contact_number`
- activity/profile: `last_login_at`, `last_activity`, `is_online`, `theme_preference`, profile review fields

Evidence: `auth_helpers.php`, `security.php`, `register.php`, `login.php`, `save_profile_settings.php`, `admin/users_management.php`, `admin/change_user_type.php`.

### `meb`

Purpose: Master List of Eligible Beneficiaries.

Important fields:

- identity/name: `lastName`, `firstName`, `middleName`, `ext`
- location: `purok`, `barangay`, `lgu`, `province`
- demographic: `birthDate`, `age`, `sex`, `civilStatus`
- eligibility/sector markers: `nhts1`, `nhts2`, `fourPs`, `F`, `FF`, `IS`, `IP`, `SC`, `SP`, `LW`, `PW`, `PWD`, `OSY`, `FR`, `ybDs`, `lgbtqia`
- workflow: `batch_id`, `validation`, `editReason`, `time_stamp`

Evidence: `pages/import.php`, `pages/meb_import_helpers.php`, `pages/data-tracking-meb.php`, `pages/update.php`, `pages/export_meb.php`.

### `meb_change_history`

Purpose: before/after traceability for MEB edits.

Fields: `id`, `meb_id`, `user_id`, `edit_reason`, `before_json`, `after_json`, `created_at`.

Evidence: `meb_change_history_helpers.php`, `pages/update.php`, `pages/meb-change-review.php`.

Privacy note: stores full before/after beneficiary snapshots, increasing retention and disclosure risk.

### `project_lawa_binhi_targets`

Purpose: fiscal-year baseline target per province/municipality/barangay.

Important fields:

- location/year: `fiscal_year`, `province`, `municipality`, `barangay`
- project counts: `lawa_target`, `binhi_target`
- BINHI type targets: `binhi_vegetable_target`, `binhi_crops_target`, `binhi_disaster_resilient_crops_target`, `binhi_fruit_bearing_trees_target`, `binhi_tilapia_target`
- related targets: `capbuild_target`, `community_action_plan_target`, `target_partner_beneficiaries`
- timestamps: `created_at`, `updated_at`

Keys: unique location per fiscal year.

Evidence: `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php`.

### `project_target_entries`

Purpose: normalized per-project target rows linked to `project_lawa_binhi_targets`.

Important fields:

- `target_id`, `row_id`, `sort_order`
- `purok`, `project_name`, `project_type`, `project_classification`
- `fertilizer_enabled`, `fertilizer_ohn_target`, `fertilizer_concoction_target`, `fertilizer_vermicompost_target`
- `binhi_target_quantity`
- `aquatic_resource`, `aquatic_resource_quantity`

Relationship: `project_target_entries.target_id` -> `project_lawa_binhi_targets.id` with cascade delete.

Evidence: `project_targets_helpers.php`, `implementation-status/save-project-target.php`.

### `program_activity_metadata`

Purpose: fiscal-year implementation activity metadata per province/municipality/barangay.

Important fields:

- location/year: `fiscal_year`, `province`, `municipality`, `barangay`
- forums: `plgu_forum_from/to`, `mlgu_forum_from/to`, `blgu_forum_from/to`
- implementation stages: `stage1_start_date/end_date`, `stage2_start_date/end_date`, `stage3_start_date/end_date`
- monitoring: `site_validation`, `drmd_monitoring_from/to`, `joint_post_monitoring_from/to`, participants fields
- payout/fund milestones: `payout_schedule_from/to`, `fund_obligation_partner_beneficiaries`, `fund_disbursement_served_partner_beneficiaries`, `liquidation_date`, `last_day_project_implementation`, `check_issuance_date`
- accomplishment rollups: `actual_lawa_accomplishment`, `actual_binhi_accomplishment`, `actual_capbuild_accomplishment`, `actual_community_action_plan_accomplishment`
- BINHI/fertilizer/land fields: `binhi_sites_established_*`, `binhi_facilities_added_*`, `fertilizer_*`, `area_land_utilized_target`

Keys: unique location per fiscal year.

Evidence: `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`.

### `program_activity_actual_projects`

Purpose: normalized actual project/accomplishment rows linked to `program_activity_metadata`.

Important fields:

- `program_activity_id`, `actual_project_id`, `project_code`, `sort_order`
- target linkage: `target_project_row_id`
- location: `purok`, `latitude`, `longitude`
- project: `project_name`, `project_type`, `project_classification`
- quantities: fertilizer quantities, aquatic resource quantity, `actual_accomplishment`, `land_area`
- context/evidence: `land_ownership`, `drive_link`, `status`

Relationship: `program_activity_actual_projects.program_activity_id` -> `program_activity_metadata.id` with cascade delete.

Evidence: `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, map/records fetch routes.

### `project_variable_config`

Purpose: fiscal-year configurable project constants.

Examples: daily wage rate, working days, LAWA capacity factors, BINHI yield factors, and other program variables used by targets, payouts, and summaries.

Evidence: `project_variable_helpers.php`, `admin/project_variables.php`, `pages/payout.php`, `implementation-status/fetch-program-summary.php`.

### `breakdown`

Purpose: payout grouping by province/LGU/barangay.

Important fields include location, beneficiary count, amount, paid count, and payout date.

Evidence: `pages/payout.php`, `pages/update_payout.php`, `pages/update_payout_group.php`, `pages/payout_export.php`.

### `fund_monitoring_object_codes`

Purpose: fiscal-year active object-code list.

Fields include `fiscal_year`, `object_code_name`, `is_active`, `created_by`, `updated_by`, timestamps.

Evidence: `fund_monitoring_helpers.php`.

### `fund_monitoring_items`

Purpose: SARO/PAP/object-code budget lines.

Important fields: `fiscal_year`, `saro_number`, `pap_name`, `object_code_name`, `authorized_appropriation`, `realignment`, `display_order`, obligation/disbursement reasons, active flag, created/updated metadata.

Evidence: `fund_monitoring_helpers.php`, `pages/fund-monitoring.php`.

### `fund_monitoring_entries`

Purpose: monthly obligations and disbursement values for fund-monitoring items.

Fields: `item_id`, `entry_month`, `obligations`, `disbursement`, `updated_by`, timestamps.

Relationship: `fund_monitoring_entries.item_id` -> `fund_monitoring_items.id` with cascade delete.

Evidence: `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php`.

## 4. Utility and Accountability Tables

### `audit_logs`

Purpose: application audit trail.

Fields: `id`, `user_id`, `action`, `details`, `ip_address`, `created_at`.

Evidence: `audit_helpers.php`, `config.php`, `admin/audit_logs.php`.

### `mail_logs`

Purpose: outbound email delivery record.

Fields: recipient, subject, status, message/body, created timestamp.

Evidence: `notification_helpers.php`, `send_contact.php`, account/security/admin routes.

### `app_notifications`

Purpose: in-app notification records.

Fields: `category`, `title`, `message`, `url`, icon/color classes, actor user/name, target user, `created_at`.

Evidence: `app_notification_helpers.php`, `notifications/index.php`, `notifications/get_feed.php`.

### `app_notification_reads`

Purpose: per-user notification read state.

Relationship: `notification_id` -> `app_notifications.id`.

Evidence: `app_notification_helpers.php`, `notifications/mark_read.php`.

## 5. Messaging and Calendar Tables

### `contact_messages`

Purpose: message thread root.

Likely fields include sender/user metadata, subject/body, attachment filename/path, sent/replied timestamps, and legacy recipient field.

Evidence: `send_contact.php`, `inbox/mailbox_helpers.php`, `inbox/index.php`.

### `contact_message_recipients`

Purpose: normalized message recipients.

Relationship: `message_id` -> `contact_messages.id`; optional `user_id` -> `users.id`.

Evidence: `send_contact.php`, `inbox/mailbox_helpers.php`.

### `contact_replies`

Purpose: replies in message threads.

Relationship: `message_id` -> `contact_messages.id`; `user_id` -> `users.id`.

Evidence: `inbox/send_reply.php`, `inbox/get_thread.php`, `inbox/mailbox_helpers.php`.

### `message_reads`

Purpose: per-user read/trash state for message threads.

Relationship: `message_id` -> `contact_messages.id`; `user_id` -> `users.id`.

Evidence: `inbox/mark_read.php`, `inbox/mailbox_helpers.php`.

### `events`, `event_guests`, `event_schedule_days`, `draggable_events`

Purpose: calendar events, guests, expanded per-day schedules, and reusable draggable labels.

Evidence: `pages/calendar.php`, `pages/event_schedule_helpers.php`, `pages/sendEventEmails.php`, `pages/fetch_events.php`.

## 6. Data-Quality Tables

### `deduplication_jobs`

Purpose: deduplication job metadata.

Important fields: `user_id`, `file_name`, `rule`, `threshold`, `status`, `progress`, timestamps/activity.

Evidence: `deduplication/upload_handler.php`, `deduplication/worker_v2.php`.

### `deduplication_results`

Purpose: duplicate-group rows.

Important fields: `job_id`, `group_id`, `row_data`, `similarity`, `created_at`.

Evidence: `deduplication/worker_v2.php`, `deduplication/results.php`, `deduplication/export_results.php`.

### `crossmatch_jobs`

Purpose: crossmatch job metadata.

Important fields include user, uploaded filenames, rule, threshold, percent/status/done, timestamps.

Evidence: `crossmatch/upload_handler.php`, `crossmatch/run_job.php`, `crossmatch/helpers/jobs.php`.

### `crossmatch_results`

Purpose: source record and candidate match payloads.

Fields: `job_id`, `record_json`, `candidates_json`.

Evidence: `crossmatch/run_job.php`, `crossmatch/results.php`, `crossmatch/export.php`.

## 7. MEBIS Tables

### `mebis_consolidator_outputs`

Purpose: generated consolidated MEBIS workbook history.

Fields: `output_token`, `filename`, `row_count`, `source_files_json`, `created_by`, `created_at`.

Evidence: `mebis-consolidator/helpers/history.php`.

### `mebis_lgu_template_jobs`

Purpose: background LGU template generation jobs.

Fields: `job_token`, `status`, `progress`, `current_step`, manifests, file/generated counts, requested/started/finished/failed/canceled timestamps.

Evidence: `mebis-lgu-template/helpers/jobs.php`.

### `mebis_lgu_template_outputs`

Purpose: generated LGU template output history.

Evidence: `mebis-lgu-template/helpers/history.php`, `mebis-lgu-template/index.php`.

## 8. Document Tracking Tables

### `incoming`

Purpose: incoming document/data tracking.

Common fields include received date, tracking number, description, focal/action fields, remarks, file name, status, and user log.

Evidence: `pages/data-tracking-in.php`, `pages/track_incoming.php`, `pages/update_data.php`, `pages/forward_document.php`, `live_refresh.php`.

### `outgoing`

Purpose: outgoing document/data tracking.

Common fields include outgoing date, tracking number, description, receiving office, forwarded date, remarks, file name, and user log.

Evidence: `pages/data-tracking-out.php`, `pages/track_outgoing.php`, `pages/update_data_out.php`, `live_refresh.php`.

### `aatracker`

Purpose: action/approval document tracking workflow.

Evidence: `pages/save_document.php`, data-tracking pages, and legacy documentation.

## 9. Key Relationships

- `project_lawa_binhi_targets` 1:N `project_target_entries`
- `program_activity_metadata` 1:N `program_activity_actual_projects`
- `users` 1:N `audit_logs`
- `meb` 1:N `meb_change_history`
- `app_notifications` 1:N `app_notification_reads`
- `contact_messages` 1:N `contact_message_recipients`
- `contact_messages` 1:N `contact_replies`
- `contact_messages` N:M `users` through `message_reads`
- `events` 1:N `event_guests`
- `events` 1:N `event_schedule_days`
- `fund_monitoring_items` 1:N `fund_monitoring_entries`
- `deduplication_jobs` 1:N `deduplication_results`
- `crossmatch_jobs` 1:N `crossmatch_results`

## 10. Privacy and Retention Notes

High-risk stores:

- `meb`
- `meb_change_history`
- crossmatch/deduplication uploads and results
- MEBIS outputs
- profile exports
- inbox attachments and message bodies
- `mail_logs` where email body content is stored
- `audit_logs` where detailed changes, IPs, paths, and user agents may appear

Items requiring policy confirmation:

- retention period for MEB, audit, mail, upload, generated output, and job-result data
- backup frequency, encryption, retention, and restoration testing
- export authorization and review process
- cleanup of uploaded/generated files
- audit log review process
- whether uploaded documents routinely contain beneficiary supporting records or IDs

## 11. Offline/PWA Note

No app manifest, service worker registration, or offline queue/cache strategy was found in the current source tree. “Offline” strings in the code refer to user presence/status labels, not PWA capability.
