# KODUS Data Dictionary / Data Elements

## Title Page

**Document Title:** KODUS Data Dictionary / Data Elements  
**System Name:** KODUS  
**Status:** For Hosting Requirements Submission

---

## Table of Contents

- [1. Introduction](#1-introduction)
- [2. Purpose of the Document](#2-purpose-of-the-document)
- [3. Scope](#3-scope)
- [4. System Overview](#4-system-overview)
- [5. Database Overview](#5-database-overview)
- [6. Naming Conventions](#6-naming-conventions)
- [7. Table Inventory](#7-table-inventory)
- [8. Detailed Data Dictionary](#8-detailed-data-dictionary)
- [9. Key Relationships and Constraints](#9-key-relationships-and-constraints)
- [10. Notes on Data Integrity and Validation](#10-notes-on-data-integrity-and-validation)
- [11. Assumptions and Items for Confirmation](#11-assumptions-and-items-for-confirmation)
- [12. Conclusion](#12-conclusion)

## 1. Introduction

This document presents a formal data dictionary for the current KODUS web application database as observed from the running application configuration, the live MySQL schema, and the PHP files that create, alter, query, and maintain the database objects. It is intended for hosting requirements submission and focuses only on structures and meanings that can be grounded in the present codebase and configured database.

## 2. Purpose of the Document

The purpose of this document is to identify the current database elements used by KODUS, describe the tables and columns that are materially part of the deployed application, summarize keys and relationships, and record schema observations that require project-owner confirmation before production hosting.

## 3. Scope

This document covers the active database named `kodus-dev_db` as reached by `config.php`, including tables, columns, data types, indexes, foreign keys, and application-level usage that can be traced in the PHP codebase. Historical SQL dump files were intentionally excluded from the source basis.

## 4. System Overview

KODUS is a PHP and MySQL web application that supports user management, login and SSO, two-factor authentication, incoming and outgoing document tracking, beneficiary or MEB data management, inbox messaging, calendar scheduling, fund monitoring, crossmatch and deduplication utilities, and implementation-status monitoring for LAWA and BINHI activities.

## 5. Database Overview

- Active database inspected: `kodus-dev_db`
- Database access path: `config.php` using `mysqli` and environment-backed connection settings
- Character set behavior observed in helper-created tables: `utf8mb4`
- Current tables documented from live schema: `40`
- Schema source basis: `information_schema.TABLES`, `information_schema.COLUMNS`, `information_schema.STATISTICS`, and foreign-key metadata queried from the configured KODUS database

## 6. Naming Conventions

- The schema uses a mixed naming style with both `snake_case` and legacy `camelCase` or abbreviated field names.
- Primary keys are commonly stored in an `id` column, but some tables use composite read-state keys and some lookup tables do not declare a primary key.
- Timestamp fields frequently use names such as `created_at`, `updated_at`, `deleted_at`, `read_at`, and `trashed_at`.
- Several modules store lists or structured content in `TEXT` or `LONGTEXT` columns and interpret them in PHP logic rather than through dedicated JSON column types.

## 7. Table Inventory

| Table Name | Category | Columns | Indexes | Foreign Keys | Purpose / Description |
| --- | --- | --- | --- | --- | --- |
| aatracker | Operational | 16 | 1 | 0 | Legacy action and approval tracker for document intake and routing. |
| app_notification_reads | Utility / Audit | 3 | 2 | 1 | Per-user read state for notification feed records. |
| app_notifications | Utility / Audit | 10 | 3 | 0 | Notification feed records shown inside KODUS. |
| app_settings | Utility / Audit | 4 | 1 | 0 | Key-value application settings maintained by helper logic. |
| audit_logs | Operational | 6 | 1 | 0 | Audit trail for security and user actions. |
| barangay | Reference | 3 | 0 | 0 | Reference list of barangays. |
| breakdown | Operational | 8 | 1 | 0 | Payout and beneficiary breakdown data. |
| contact_message_recipients | Operational | 6 | 4 | 2 | Normalized recipient list for contact-message threads. |
| contact_messages | Operational | 11 | 1 | 0 | Inbox or contact-message threads. |
| contact_replies | Operational | 9 | 3 | 2 | Replies attached to inbox or contact-message threads. |
| crossmatch_jobs | Operational | 11 | 1 | 0 | Crossmatch processing jobs. |
| crossmatch_results | Operational | 4 | 1 | 0 | Crossmatch result rows. |
| deduplication_jobs | Operational | 9 | 1 | 0 | Deduplication processing jobs. |
| deduplication_results | Operational | 6 | 2 | 1 | Potential duplicate result rows. |
| deduplication_template_outputs | Utility / Audit | 8 | 3 | 0 | Generated deduplication template history. |
| draggable_events | Operational | 5 | 1 | 0 | Reusable drag-and-drop calendar labels. |
| event_guests | Operational | 6 | 3 | 2 | Normalized guest or invitee list for calendar events. |
| event_schedule_days | Operational | 9 | 4 | 0 | Expanded per-day schedule rows for events. |
| events | Operational | 16 | 1 | 0 | Primary calendar event records. |
| fund_monitoring_entries | Operational | 8 | 2 | 1 | Monthly obligations and disbursement entries. |
| fund_monitoring_items | Operational | 15 | 3 | 0 | Fund monitoring master items. |
| fund_monitoring_object_codes | Operational | 8 | 2 | 0 | Reference object codes for fund monitoring. |
| imp_status | Legacy / Transitional | 11 | 1 | 0 | Legacy implementation-status table retained in the live schema. |
| incoming | Operational | 12 | 1 | 0 | Incoming document tracking records. |
| mail_logs | Utility / Audit | 6 | 1 | 0 | Outbound mail-delivery log entries. |
| meb | Operational | 33 | 2 | 0 | Master beneficiary or MEB records. |
| meb_change_history | Utility / Audit | 7 | 3 | 0 | Before-and-after history for MEB edits. |
| mebis_consolidator_outputs | Utility / Audit | 7 | 2 | 0 | Generated MEBIS consolidator output history. |
| mebis_lgu_template_outputs | Utility / Audit | 8 | 2 | 0 | Generated MEBIS LGU template output history. |
| message_reads | Utility / Audit | 7 | 3 | 2 | Per-user read and trash state for message threads. |
| municipality | Reference | 3 | 0 | 0 | Reference list of municipalities. |
| outgoing | Operational | 12 | 1 | 0 | Outgoing or forwarded document tracking records. |
| pdos | Reference | 3 | 1 | 0 | Reference PDO list used by the application. |
| program_activity_actual_projects | Operational | 25 | 3 | 1 | Normalized actual project/accomplishment rows linked to implementation activity metadata. |
| program_activity_metadata | Operational | 76 | 2 | 0 | Implementation monitoring rows for location-level program activity data. |
| project_lawa_binhi_targets | Operational | 32 | 3 | 0 | Annual LAWA and BINHI target configuration by location. |
| project_target_entries | Operational | 17 | 3 | 1 | Normalized per-project target rows linked to annual LAWA and BINHI target records. |
| project_variable_config | Operational | 12 | 2 | 0 | Fiscal-year project variables, rates, and factor values. |
| provinces | Reference | 3 | 1 | 0 | Reference list of provinces. |
| users | Operational | 42 | 1 | 0 | User accounts, authentication state, profile data, and role or 2FA metadata. |

### Absent or Legacy References Not Included in the Detailed Dictionary

| Table / Object | Current Status | Observed Basis |
| --- | --- | --- |
| holidays | Absent from current configured database | `pages/calendar.php`, `pages/fetch_events.php`, `pages/fetch_holidays.php` |
| coreworkforce | Absent from current configured database | No current PHP usage found |
| trackdata | Absent from current configured database | No current PHP usage found |
| _merge_conflicts | Absent from current configured database | No current PHP usage found |

## 8. Detailed Data Dictionary

### 8.1 `aatracker`

**Purpose / Description:** Legacy action and approval tracker for document intake and routing.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `pages/save_document.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `pages/save_document.php` |
| aaDate | date | N/A | No | None | none | Field used to store aa date; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `pages/save_document.php` |
| tracking_number2 | varchar(255) | 255 | No | None | none | Generated tracking number assigned after insert. | 04-15-26-123 | Live schema via information_schema; PHP usage in `pages/save_document.php` |
| description | varchar(1000) | 1000 | No | None | none | Field used to store description; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/save_document.php` |
| bFocal | date | N/A | No | None | none | BFocal routing date for the action and approval tracker workflow. | 2026-04-15 | Live schema via information_schema; PHP usage in `pages/save_document.php` |
| focal | date | N/A | No | None | none | Field used to store focal; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `pages/save_document.php` |
| remarks2 | varchar(1000) | 1000 | Yes | None | none | Remarks field currently present in the live schema. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/save_document.php` |
| sHead | date | N/A | No | None | none | Field used to store s head; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `pages/save_document.php` |
| file_name | varchar(255) | 255 | No | None | none | Field used to store file name; exact business meaning to be confirmed by project owner where not obvious from code. | document.pdf | Live schema via information_schema; PHP usage in `pages/save_document.php` |
| file_size | int | 10 | No | None | none | Field used to store file size; exact business meaning to be confirmed by project owner where not obvious from code. | 245760 | Live schema via information_schema; PHP usage in `pages/save_document.php` |
| file_type | varchar(255) | 255 | No | None | none | Field used to store file type; exact business meaning to be confirmed by project owner where not obvious from code. | application/pdf | Live schema via information_schema; PHP usage in `pages/save_document.php` |
| upload_time | datetime | N/A | No | None | none | Field used to store upload time; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `pages/save_document.php` |
| aaType | int | 10 | No | None | none | Field used to store aa type; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `pages/save_document.php` |
| outDate | date | N/A | No | None | none | Field used to store out date; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `pages/save_document.php` |
| personnel | varchar(255) | 255 | No | None | none | Field used to store personnel; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/save_document.php` |
| dateReceived | date | N/A | No | None | none | Field used to store date received; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `pages/save_document.php` |

### 8.2 `app_notification_reads`

**Purpose / Description:** Per-user read state for notification feed records.  
**Primary Key:** notification_id, user_id  
**Foreign Keys:** `notification_id` -> `app_notifications(id)`  
**Primary Source Basis:** `app_notification_helpers.php`, `notifications/mark_read.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| notification_id | int unsigned | 10 | No | None | PK, FK | Field used to store notification id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `app_notification_helpers.php`, `notifications/mark_read.php` |
| user_id | int | 10 | No | None | PK, Indexed | Field used to store user id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `app_notification_helpers.php`, `notifications/mark_read.php` |
| read_at | datetime | N/A | No | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `app_notification_helpers.php`, `notifications/mark_read.php` |

### 8.3 `app_notifications`

**Purpose / Description:** Notification feed records shown inside KODUS.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `app_notification_helpers.php`, `notifications/mark_read.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int unsigned | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `app_notification_helpers.php`, `notifications/mark_read.php` |
| category | varchar(80) | 80 | No | system | Indexed | Field used to store category; exact business meaning to be confirmed by project owner where not obvious from code. | system | Live schema via information_schema; PHP usage in `app_notification_helpers.php`, `notifications/mark_read.php` |
| title | varchar(255) | 255 | No | None | none | Field used to store title; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `app_notification_helpers.php`, `notifications/mark_read.php` |
| message | text | 65535 | No | None | none | Field used to store message; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `app_notification_helpers.php`, `notifications/mark_read.php` |
| url | varchar(2048) | 2048 | Yes | None | none | Field used to store url; exact business meaning to be confirmed by project owner where not obvious from code. | /pages/calendar.php | Live schema via information_schema; PHP usage in `app_notification_helpers.php`, `notifications/mark_read.php` |
| icon_class | varchar(80) | 80 | No | fas fa-bell | none | Field used to store icon class; exact business meaning to be confirmed by project owner where not obvious from code. | fas fa-bell | Live schema via information_schema; PHP usage in `app_notification_helpers.php`, `notifications/mark_read.php` |
| color_class | varchar(40) | 40 | No | text-warning | none | Field used to store color class; exact business meaning to be confirmed by project owner where not obvious from code. | text-warning | Live schema via information_schema; PHP usage in `app_notification_helpers.php`, `notifications/mark_read.php` |
| actor_user_id | int | 10 | Yes | None | none | Field used to store actor user id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `app_notification_helpers.php`, `notifications/mark_read.php` |
| actor_name | varchar(255) | 255 | Yes | None | none | Field used to store actor name; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `app_notification_helpers.php`, `notifications/mark_read.php` |
| created_at | datetime | N/A | No | CURRENT_TIMESTAMP | Indexed | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `app_notification_helpers.php`, `notifications/mark_read.php` |

### 8.4 `app_settings`

**Purpose / Description:** Key-value application settings maintained by helper logic.  
**Primary Key:** setting_key  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `error_helpers.php`, `admin/maintenance.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| setting_key | varchar(100) | 100 | No | None | PK | Field used to store setting key; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `error_helpers.php`, `admin/maintenance.php` |
| setting_value | longtext | 4294967295 | Yes | None | none | Field used to store setting value; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `error_helpers.php`, `admin/maintenance.php` |
| updated_at | datetime | N/A | No | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `error_helpers.php`, `admin/maintenance.php` |
| updated_by | int | 10 | Yes | None | none | Field used to store updated by; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `error_helpers.php`, `admin/maintenance.php` |

### 8.5 `audit_logs`

**Purpose / Description:** Audit trail for security and user actions.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `audit_helpers.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `audit_helpers.php` |
| user_id | int | 10 | Yes | None | none | Field used to store user id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `audit_helpers.php` |
| action | varchar(255) | 255 | Yes | None | none | Field used to store action; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `audit_helpers.php` |
| details | text | 65535 | Yes | None | none | Field used to store details; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `audit_helpers.php` |
| ip_address | varchar(45) | 45 | Yes | None | none | Field used to store ip address; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `audit_helpers.php` |
| created_at | datetime | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `audit_helpers.php` |

### 8.6 `barangay`

**Purpose / Description:** Reference list of barangays.  
**Primary Key:** To be confirmed by project owner  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `get_data.php`, `live_refresh.php`, `payout.php`, `project_targets_helpers.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | varchar(255) | 255 | No | None | none | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `get_data.php`, `live_refresh.php`, `payout.php` |
| municipality_id | varchar(255) | 255 | No | None | none | Field used to store municipality id; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `get_data.php`, `live_refresh.php`, `payout.php` |
| brgy_name | varchar(255) | 255 | No | None | none | Field used to store brgy name; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `get_data.php`, `live_refresh.php`, `payout.php` |

### 8.7 `breakdown`

**Purpose / Description:** Payout and beneficiary breakdown data.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `payout.php`, `implementation-status/program-activities.php`, `pages/meb-batch-summary.php`, `pages/payout.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `payout.php`, `implementation-status/program-activities.php`, `pages/meb-batch-summary.php` |
| province | varchar(250) | 250 | No | None | none | Location value stored by the record. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `payout.php`, `implementation-status/program-activities.php`, `pages/meb-batch-summary.php` |
| lgu | varchar(250) | 250 | No | None | none | Field used to store lgu; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `payout.php`, `implementation-status/program-activities.php`, `pages/meb-batch-summary.php` |
| barangay | varchar(250) | 250 | No | None | none | Location value stored by the record. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `payout.php`, `implementation-status/program-activities.php`, `pages/meb-batch-summary.php` |
| benesNumber | int | 10 | No | None | none | Field used to store benes number; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `payout.php`, `implementation-status/program-activities.php`, `pages/meb-batch-summary.php` |
| amount | int | 10 | No | None | none | Field used to store amount; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `payout.php`, `implementation-status/program-activities.php`, `pages/meb-batch-summary.php` |
| paid | int | 10 | No | None | none | Field used to store paid; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `payout.php`, `implementation-status/program-activities.php`, `pages/meb-batch-summary.php` |
| payoutDate | date | N/A | Yes | None | none | Field used to store payout date; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `payout.php`, `implementation-status/program-activities.php`, `pages/meb-batch-summary.php` |

### 8.8 `contact_message_recipients`

**Purpose / Description:** Normalized recipient list for contact-message threads.  
**Primary Key:** id  
**Foreign Keys:** `message_id` -> `contact_messages(id)`; `user_id` -> `users(id)`  
**Primary Source Basis:** `inbox/mailbox_helpers.php`, `send_contact.php`, `inbox/index.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | bigint | 19 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `inbox/mailbox_helpers.php`, `send_contact.php`, `inbox/index.php` |
| message_id | int | 10 | No | None | FK, Indexed | Parent contact message thread identifier. | 1 | Live schema via information_schema; PHP usage in `inbox/mailbox_helpers.php`, `send_contact.php`, `inbox/index.php` |
| user_id | int | 10 | Yes | None | FK, Indexed | Field used to store user id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `inbox/mailbox_helpers.php`, `send_contact.php`, `inbox/index.php` |
| recipient_email | varchar(255) | 255 | No | None | Indexed | Email address of the intended message recipient. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `inbox/mailbox_helpers.php`, `send_contact.php`, `inbox/index.php` |
| recipient_name | varchar(255) | 255 | Yes | None | none | Field used to store recipient name; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `inbox/mailbox_helpers.php`, `send_contact.php`, `inbox/index.php` |
| created_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `inbox/mailbox_helpers.php`, `send_contact.php`, `inbox/index.php` |

### 8.9 `contact_messages`

**Purpose / Description:** Inbox or contact-message threads.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `send_contact.php`, `inbox/index.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `send_contact.php`, `inbox/index.php` |
| user_email | varchar(255) | 255 | Yes | None | none | Field used to store user email; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `send_contact.php`, `inbox/index.php` |
| user_name | varchar(255) | 255 | Yes | None | none | Field used to store user name; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `send_contact.php`, `inbox/index.php` |
| recipient | varchar(255) | 255 | Yes | None | none | Field used to store recipient; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `send_contact.php`, `inbox/index.php` |
| subject | text | 65535 | Yes | None | none | Field used to store subject; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `send_contact.php`, `inbox/index.php` |
| message | text | 65535 | Yes | None | none | Field used to store message; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `send_contact.php`, `inbox/index.php` |
| sent_at | datetime | N/A | Yes | CURRENT_TIMESTAMP | none | Field used to store sent at; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `send_contact.php`, `inbox/index.php` |
| is_read | tinyint | 3 | No | 0 | none | Field used to store is read; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `send_contact.php`, `inbox/index.php` |
| admin_reply | text | 65535 | Yes | None | none | Field used to store admin reply; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `send_contact.php`, `inbox/index.php` |
| replied_at | datetime | N/A | Yes | None | none | Field used to store replied at; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `send_contact.php`, `inbox/index.php` |
| attachment | varchar(5000) | 5000 | Yes | None | none | Field used to store attachment; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `send_contact.php`, `inbox/index.php` |

### 8.10 `contact_replies`

**Purpose / Description:** Replies attached to inbox or contact-message threads.  
**Primary Key:** id  
**Foreign Keys:** `message_id` -> `contact_messages(id)`; `user_id` -> `users(id)`  
**Primary Source Basis:** `inbox/send_reply.php`, `inbox/mailbox_helpers.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `inbox/send_reply.php`, `inbox/mailbox_helpers.php` |
| message_id | int | 10 | No | None | FK, Indexed | Field used to store message id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `inbox/send_reply.php`, `inbox/mailbox_helpers.php` |
| user_id | int | 10 | No | None | FK, Indexed | Field used to store user id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `inbox/send_reply.php`, `inbox/mailbox_helpers.php` |
| reply | text | 65535 | No | None | none | Field used to store reply; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `inbox/send_reply.php`, `inbox/mailbox_helpers.php` |
| sent_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Field used to store sent at; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `inbox/send_reply.php`, `inbox/mailbox_helpers.php` |
| updated_at | datetime | N/A | Yes | None | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `inbox/send_reply.php`, `inbox/mailbox_helpers.php` |
| attachment | varchar(5000) | 5000 | Yes | None | none | Field used to store attachment; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `inbox/send_reply.php`, `inbox/mailbox_helpers.php` |
| deleted_for_everyone_at | datetime | N/A | Yes | None | none | Field used to store deleted for everyone at; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `inbox/send_reply.php`, `inbox/mailbox_helpers.php` |
| deleted_by_user_id | int | 10 | Yes | None | none | Field used to store deleted by user id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `inbox/send_reply.php`, `inbox/mailbox_helpers.php` |

### 8.11 `crossmatch_jobs`

**Purpose / Description:** Crossmatch processing jobs.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `crossmatch/upload_handler.php`, `crossmatch/run.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `crossmatch/upload_handler.php`, `crossmatch/run.php` |
| user_id | int | 10 | Yes | None | none | Field used to store user id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `crossmatch/upload_handler.php`, `crossmatch/run.php` |
| percent | int | 10 | No | 0 | none | Field used to store percent; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `crossmatch/upload_handler.php`, `crossmatch/run.php` |
| done | tinyint(1) | 3 | No | 0 | none | Field used to store done; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `crossmatch/upload_handler.php`, `crossmatch/run.php` |
| created_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `crossmatch/upload_handler.php`, `crossmatch/run.php` |
| updated_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `crossmatch/upload_handler.php`, `crossmatch/run.php` |
| status | varchar(255) | 255 | No | None | none | Field used to store status; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `crossmatch/upload_handler.php`, `crossmatch/run.php` |
| file1_name | varchar(255) | 255 | Yes | None | none | Field used to store file1 name; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `crossmatch/upload_handler.php`, `crossmatch/run.php` |
| file2_name | varchar(255) | 255 | Yes | None | none | Field used to store file2 name; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `crossmatch/upload_handler.php`, `crossmatch/run.php` |
| rule | varchar(20) | 20 | No | strict | none | Field used to store rule; exact business meaning to be confirmed by project owner where not obvious from code. | strict | Live schema via information_schema; PHP usage in `crossmatch/upload_handler.php`, `crossmatch/run.php` |
| threshold | int | 10 | No | 85 | none | Field used to store threshold; exact business meaning to be confirmed by project owner where not obvious from code. | 85 | Live schema via information_schema; PHP usage in `crossmatch/upload_handler.php`, `crossmatch/run.php` |

### 8.12 `crossmatch_results`

**Purpose / Description:** Crossmatch result rows.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `crossmatch/run.php`, `crossmatch/results.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `crossmatch/run.php`, `crossmatch/results.php` |
| job_id | char(32) | 32 | No | None | none | Field used to store job id; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `crossmatch/run.php`, `crossmatch/results.php` |
| record_json | json | N/A | No | None | none | Field used to store record json; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `crossmatch/run.php`, `crossmatch/results.php` |
| candidates_json | json | N/A | No | None | none | Field used to store candidates json; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `crossmatch/run.php`, `crossmatch/results.php` |

### 8.13 `deduplication_jobs`

**Purpose / Description:** Deduplication processing jobs.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `deduplication/upload_handler.php`, `deduplication/worker_v2.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `deduplication/upload_handler.php`, `deduplication/worker_v2.php` |
| user_id | int | 10 | No | None | none | Field used to store user id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `deduplication/upload_handler.php`, `deduplication/worker_v2.php` |
| file_name | varchar(255) | 255 | No | None | none | Field used to store file name; exact business meaning to be confirmed by project owner where not obvious from code. | document.pdf | Live schema via information_schema; PHP usage in `deduplication/upload_handler.php`, `deduplication/worker_v2.php` |
| rule | varchar(25) | 25 | No | None | none | Field used to store rule; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `deduplication/upload_handler.php`, `deduplication/worker_v2.php` |
| threshold | int | 10 | No | None | none | Field used to store threshold; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `deduplication/upload_handler.php`, `deduplication/worker_v2.php` |
| status | varchar(255) | 255 | No | None | none | Field used to store status; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `deduplication/upload_handler.php`, `deduplication/worker_v2.php` |
| progress | int | 10 | No | 0 | none | Field used to store progress; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `deduplication/upload_handler.php`, `deduplication/worker_v2.php` |
| created_at | timestamp | N/A | No | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `deduplication/upload_handler.php`, `deduplication/worker_v2.php` |
| last_activity | timestamp | N/A | No | CURRENT_TIMESTAMP | none | Field used to store last activity; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `deduplication/upload_handler.php`, `deduplication/worker_v2.php` |

### 8.14 `deduplication_results`

**Purpose / Description:** Potential duplicate result rows.  
**Primary Key:** id  
**Foreign Keys:** `job_id` -> `deduplication_jobs(id)`  
**Primary Source Basis:** `deduplication/worker_v2.php`, `deduplication/results.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `deduplication/worker_v2.php`, `deduplication/results.php` |
| job_id | int | 10 | No | None | FK, Indexed | Field used to store job id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `deduplication/worker_v2.php`, `deduplication/results.php` |
| group_id | int | 10 | No | None | none | Field used to store group id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `deduplication/worker_v2.php`, `deduplication/results.php` |
| row_data | longtext | 4294967295 | No | None | none | Field used to store row data; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `deduplication/worker_v2.php`, `deduplication/results.php` |
| created_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `deduplication/worker_v2.php`, `deduplication/results.php` |
| similarity | int | 10 | Yes | None | none | Field used to store similarity; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `deduplication/worker_v2.php`, `deduplication/results.php` |

### 8.15 `deduplication_template_outputs`

**Purpose / Description:** Generated deduplication template history.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `deduplication/helpers/generator_history.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | bigint unsigned | 20 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `deduplication/helpers/generator_history.php` |
| output_token | varchar(32) | 32 | No | None | Indexed | Field used to store output token; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `deduplication/helpers/generator_history.php` |
| filename | varchar(255) | 255 | No | None | none | Field used to store filename; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `deduplication/helpers/generator_history.php` |
| municipality_name | varchar(191) | 191 | No |  | none | Field used to store municipality name; exact business meaning to be confirmed by project owner where not obvious from code. |  | Live schema via information_schema; PHP usage in `deduplication/helpers/generator_history.php` |
| row_count | int unsigned | 10 | No | 0 | none | Field used to store row count; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `deduplication/helpers/generator_history.php` |
| source_file | varchar(255) | 255 | No |  | none | Field used to store source file; exact business meaning to be confirmed by project owner where not obvious from code. |  | Live schema via information_schema; PHP usage in `deduplication/helpers/generator_history.php` |
| created_by | int | 10 | Yes | None | Indexed | Field used to store created by; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `deduplication/helpers/generator_history.php` |
| created_at | datetime | N/A | No | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `deduplication/helpers/generator_history.php` |

### 8.16 `draggable_events`

**Purpose / Description:** Reusable drag-and-drop calendar labels.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `pages/add_draggable.php`, `pages/calendar.php`, `pages/delete_draggable.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `pages/add_draggable.php`, `pages/calendar.php`, `pages/delete_draggable.php` |
| title | varchar(255) | 255 | No | None | none | Field used to store title; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/add_draggable.php`, `pages/calendar.php`, `pages/delete_draggable.php` |
| color | varchar(20) | 20 | No | #3788d8 | none | Field used to store color; exact business meaning to be confirmed by project owner where not obvious from code. | #3788d8 | Live schema via information_schema; PHP usage in `pages/add_draggable.php`, `pages/calendar.php`, `pages/delete_draggable.php` |
| created_by | int | 10 | No | None | none | Field used to store created by; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `pages/add_draggable.php`, `pages/calendar.php`, `pages/delete_draggable.php` |
| created_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `pages/add_draggable.php`, `pages/calendar.php`, `pages/delete_draggable.php` |

### 8.17 `event_guests`

**Purpose / Description:** Normalized guest or invitee list for calendar events.  
**Primary Key:** id  
**Foreign Keys:** `event_id` -> `events(id)`; `user_id` -> `users(id)`  
**Primary Source Basis:** `pages/sendEventEmails.php`, `pages/fetch_events.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | bigint | 19 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `pages/sendEventEmails.php`, `pages/fetch_events.php` |
| event_id | int | 10 | No | None | FK, Indexed | Parent calendar event identifier. | 1 | Live schema via information_schema; PHP usage in `pages/sendEventEmails.php`, `pages/fetch_events.php` |
| user_id | int | 10 | Yes | None | FK, Indexed | Field used to store user id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `pages/sendEventEmails.php`, `pages/fetch_events.php` |
| guest_email | varchar(255) | 255 | No | None | Indexed | Email address of the invited event guest. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/sendEventEmails.php`, `pages/fetch_events.php` |
| guest_name | varchar(255) | 255 | Yes | None | none | Field used to store guest name; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/sendEventEmails.php`, `pages/fetch_events.php` |
| created_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `pages/sendEventEmails.php`, `pages/fetch_events.php` |

### 8.18 `event_schedule_days`

**Purpose / Description:** Expanded per-day schedule rows for events.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `pages/event_schedule_helpers.php`, `pages/update_event.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `pages/event_schedule_helpers.php`, `pages/update_event.php` |
| event_id | int | 10 | No | None | Indexed | Field used to store event id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `pages/event_schedule_helpers.php`, `pages/update_event.php` |
| schedule_date | date | N/A | No | None | none | Field used to store schedule date; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `pages/event_schedule_helpers.php`, `pages/update_event.php` |
| start_time | time | N/A | No | None | none | Field used to store start time; exact business meaning to be confirmed by project owner where not obvious from code. | 08:00:00 | Live schema via information_schema; PHP usage in `pages/event_schedule_helpers.php`, `pages/update_event.php` |
| end_time | time | N/A | No | None | none | Field used to store end time; exact business meaning to be confirmed by project owner where not obvious from code. | 08:00:00 | Live schema via information_schema; PHP usage in `pages/event_schedule_helpers.php`, `pages/update_event.php` |
| start_datetime | datetime | N/A | No | None | Indexed | Field used to store start datetime; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `pages/event_schedule_helpers.php`, `pages/update_event.php` |
| end_datetime | datetime | N/A | No | None | Indexed | Field used to store end datetime; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `pages/event_schedule_helpers.php`, `pages/update_event.php` |
| created_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `pages/event_schedule_helpers.php`, `pages/update_event.php` |
| updated_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `pages/event_schedule_helpers.php`, `pages/update_event.php` |

### 8.19 `events`

**Purpose / Description:** Primary calendar event records.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `header.php`, `page_loader.php`, `select_year.php`, `settings.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `header.php`, `page_loader.php`, `select_year.php` |
| title | varchar(255) | 255 | No | None | none | Field used to store title; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `header.php`, `page_loader.php`, `select_year.php` |
| description | text | 65535 | Yes | None | none | Field used to store description; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `header.php`, `page_loader.php`, `select_year.php` |
| start | datetime | N/A | No | None | none | Field used to store start; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `header.php`, `page_loader.php`, `select_year.php` |
| end | datetime | N/A | Yes | None | none | Field used to store end; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `header.php`, `page_loader.php`, `select_year.php` |
| all_day | tinyint(1) | 3 | Yes | 0 | none | Flag indicating whether the event is all-day. | 0 | Live schema via information_schema; PHP usage in `header.php`, `page_loader.php`, `select_year.php` |
| guests | text | 65535 | Yes | None | none | Field used to store guests; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `header.php`, `page_loader.php`, `select_year.php` |
| location | varchar(255) | 255 | Yes | None | none | Field used to store location; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `header.php`, `page_loader.php`, `select_year.php` |
| color | varchar(20) | 20 | Yes | #3788d8 | none | Field used to store color; exact business meaning to be confirmed by project owner where not obvious from code. | #3788d8 | Live schema via information_schema; PHP usage in `header.php`, `page_loader.php`, `select_year.php` |
| is_private | tinyint(1) | 3 | No | 0 | none | Field used to store is private; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `header.php`, `page_loader.php`, `select_year.php` |
| created_by | int | 10 | No | None | none | Field used to store created by; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `header.php`, `page_loader.php`, `select_year.php` |
| updated_by | int | 10 | Yes | None | none | Field used to store updated by; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `header.php`, `page_loader.php`, `select_year.php` |
| created_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `header.php`, `page_loader.php`, `select_year.php` |
| updated_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `header.php`, `page_loader.php`, `select_year.php` |
| deleted_by | int | 10 | Yes | None | none | Field used to store deleted by; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `header.php`, `page_loader.php`, `select_year.php` |
| deleted_at | datetime | N/A | Yes | None | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `header.php`, `page_loader.php`, `select_year.php` |

### 8.20 `fund_monitoring_entries`

**Purpose / Description:** Monthly obligations and disbursement entries.  
**Primary Key:** id  
**Foreign Keys:** `item_id` -> `fund_monitoring_items(id)`  
**Primary Source Basis:** `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php` |
| item_id | int | 10 | No | None | FK, Indexed | Field used to store item id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php` |
| entry_month | tinyint | 3 | No | None | Indexed | Field used to store entry month; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php` |
| obligations | decimal(16,2) | 16,2 | No | 0.00 | none | Field used to store obligations; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php` |
| disbursement | decimal(16,2) | 16,2 | No | 0.00 | none | Field used to store disbursement; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php` |
| updated_by | int | 10 | Yes | None | none | Field used to store updated by; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php` |
| created_at | datetime | N/A | No | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php` |
| updated_at | datetime | N/A | No | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php` |

### 8.21 `fund_monitoring_items`

**Purpose / Description:** Fund monitoring master items.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `fund_monitoring_helpers.php`, `pages/fund-monitoring.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/fund-monitoring.php` |
| fiscal_year | int | 10 | No | None | Indexed | Field used to store fiscal year; exact business meaning to be confirmed by project owner where not obvious from code. | 2026 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/fund-monitoring.php` |
| saro_number | varchar(120) | 120 | No | None | Indexed | Field used to store saro number; exact business meaning to be confirmed by project owner where not obvious from code. | DRRP-CC-2026-CARAGA-16 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/fund-monitoring.php` |
| pap_name | varchar(255) | 255 | No | None | none | Field used to store pap name; exact business meaning to be confirmed by project owner where not obvious from code. | Shared PAP | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/fund-monitoring.php` |
| object_code_name | varchar(190) | 190 | No | None | none | Field used to store object code name; exact business meaning to be confirmed by project owner where not obvious from code. | Training Expenses | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/fund-monitoring.php` |
| authorized_appropriation | decimal(16,2) | 16,2 | No | 0.00 | none | Field used to store authorized appropriation; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/fund-monitoring.php` |
| realignment | decimal(16,2) | 16,2 | No | 0.00 | none | Field used to store realignment; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/fund-monitoring.php` |
| display_order | int | 10 | No | 0 | none | Field used to store display order; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/fund-monitoring.php` |
| reason_obligation | text | 65535 | Yes | None | none | Field used to store reason obligation; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/fund-monitoring.php` |
| reason_disbursement | text | 65535 | Yes | None | none | Field used to store reason disbursement; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/fund-monitoring.php` |
| is_active | tinyint(1) | 3 | No | 1 | none | Field used to store is active; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/fund-monitoring.php` |
| created_by | int | 10 | Yes | None | none | Field used to store created by; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/fund-monitoring.php` |
| updated_by | int | 10 | Yes | None | none | Field used to store updated by; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/fund-monitoring.php` |
| created_at | datetime | N/A | No | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/fund-monitoring.php` |
| updated_at | datetime | N/A | No | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/fund-monitoring.php` |

### 8.22 `fund_monitoring_object_codes`

**Purpose / Description:** Reference object codes for fund monitoring.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php` |
| fiscal_year | int | 10 | No | None | Indexed | Field used to store fiscal year; exact business meaning to be confirmed by project owner where not obvious from code. | 2026 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php` |
| object_code_name | varchar(190) | 190 | No | None | Indexed | Field used to store object code name; exact business meaning to be confirmed by project owner where not obvious from code. | Training Expenses | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php` |
| is_active | tinyint(1) | 3 | No | 1 | none | Field used to store is active; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php` |
| created_by | int | 10 | Yes | None | none | Field used to store created by; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php` |
| updated_by | int | 10 | Yes | None | none | Field used to store updated by; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php` |
| created_at | datetime | N/A | No | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php` |
| updated_at | datetime | N/A | No | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `fund_monitoring_helpers.php`, `pages/save_fund_monitoring.php` |

### 8.23 `imp_status`

**Purpose / Description:** Legacy implementation-status table retained in the live schema.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `implementation-status/activity_metadata.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php` |
| province | varchar(255) | 255 | No | None | none | Location value stored by the record. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php` |
| municipality | varchar(255) | 255 | No | None | none | Location value stored by the record. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php` |
| barangay | varchar(255) | 255 | No | None | none | Location value stored by the record. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php` |
| beneficiaries | int | 10 | Yes | None | none | Field used to store beneficiaries; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php` |
| plgu_forum | date | N/A | Yes | None | none | Field used to store plgu forum; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php` |
| mlgu_forum | date | N/A | Yes | None | none | Field used to store mlgu forum; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php` |
| blgu_forum | date | N/A | Yes | None | none | Field used to store blgu forum; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php` |
| project_names | varchar(1000) | 1000 | Yes | None | none | Field used to store project names; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php` |
| created_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php` |
| updated_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php` |

### 8.24 `incoming`

**Purpose / Description:** Incoming document tracking records.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `pages/track_incoming.php`, `pages/update_data.php`, `pages/forward_document.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `pages/track_incoming.php`, `pages/update_data.php`, `pages/forward_document.php` |
| date_received | date | N/A | No | None | none | Field used to store date received; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `pages/track_incoming.php`, `pages/update_data.php`, `pages/forward_document.php` |
| tracking_number | varchar(250) | 250 | No | None | none | Field used to store tracking number; exact business meaning to be confirmed by project owner where not obvious from code. | 04-15-26-123 | Live schema via information_schema; PHP usage in `pages/track_incoming.php`, `pages/update_data.php`, `pages/forward_document.php` |
| description | varchar(250) | 250 | No | None | none | Field used to store description; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/track_incoming.php`, `pages/update_data.php`, `pages/forward_document.php` |
| focal | date | N/A | Yes | None | none | Field used to store focal; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `pages/track_incoming.php`, `pages/update_data.php`, `pages/forward_document.php` |
| remarks | varchar(250) | 250 | Yes | None | none | Field used to store remarks; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/track_incoming.php`, `pages/update_data.php`, `pages/forward_document.php` |
| file_name | varchar(250) | 250 | Yes | None | none | Field used to store file name; exact business meaning to be confirmed by project owner where not obvious from code. | document.pdf | Live schema via information_schema; PHP usage in `pages/track_incoming.php`, `pages/update_data.php`, `pages/forward_document.php` |
| file_type | varchar(250) | 250 | Yes | None | none | Field used to store file type; exact business meaning to be confirmed by project owner where not obvious from code. | application/pdf | Live schema via information_schema; PHP usage in `pages/track_incoming.php`, `pages/update_data.php`, `pages/forward_document.php` |
| file_size | varchar(250) | 250 | Yes | None | none | Field used to store file size; exact business meaning to be confirmed by project owner where not obvious from code. | 245760 | Live schema via information_schema; PHP usage in `pages/track_incoming.php`, `pages/update_data.php`, `pages/forward_document.php` |
| upload_time | varchar(250) | 250 | Yes | None | none | Field used to store upload time; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/track_incoming.php`, `pages/update_data.php`, `pages/forward_document.php` |
| user_log | varchar(255) | 255 | No | None | none | Field used to store user log; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/track_incoming.php`, `pages/update_data.php`, `pages/forward_document.php` |
| status | varchar(20) | 20 | Yes | None | none | Field used to store status; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/track_incoming.php`, `pages/update_data.php`, `pages/forward_document.php` |

### 8.25 `mail_logs`

**Purpose / Description:** Outbound mail-delivery log entries.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `notification_helpers.php`, `send_contact.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `notification_helpers.php`, `send_contact.php` |
| recipient | varchar(255) | 255 | Yes | None | none | Field used to store recipient; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `notification_helpers.php`, `send_contact.php` |
| subject | varchar(255) | 255 | Yes | None | none | Field used to store subject; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `notification_helpers.php`, `send_contact.php` |
| status | varchar(50) | 50 | Yes | None | none | Field used to store status; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `notification_helpers.php`, `send_contact.php` |
| message | text | 65535 | Yes | None | none | Field used to store message; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `notification_helpers.php`, `send_contact.php` |
| created_at | datetime | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `notification_helpers.php`, `send_contact.php` |

### 8.26 `meb`

**Purpose / Description:** Master beneficiary or MEB records.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `pages/import.php`, `pages/fetch_data.php`, `pages/update.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| lastName | varchar(250) | 250 | No | None | none | Field used to store last name; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| firstName | varchar(250) | 250 | No | None | none | Field used to store first name; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| middleName | varchar(250) | 250 | Yes | None | none | Field used to store middle name; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| ext | varchar(250) | 250 | Yes | None | none | Field used to store ext; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| purok | varchar(250) | 250 | No | None | none | Field used to store purok; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| barangay | varchar(250) | 250 | No | None | none | Location value stored by the record. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| lgu | varchar(250) | 250 | No | None | none | Field used to store lgu; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| province | varchar(250) | 250 | No | None | none | Location value stored by the record. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| birthDate | date | N/A | Yes | None | none | Field used to store birth date; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| age | int | 10 | No | None | none | Field used to store age; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| sex | varchar(250) | 250 | No | None | none | Field used to store sex; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| civilStatus | varchar(250) | 250 | Yes | None | none | Field used to store civil status; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| nhts1 | varchar(250) | 250 | Yes | None | none | Field used to store nhts1; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| nhts2 | varchar(250) | 250 | Yes | None | none | Field used to store nhts2; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| fourPs | varchar(250) | 250 | Yes | None | none | Field used to store four ps; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| F | varchar(250) | 250 | Yes | None | none | Field used to store f; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| FF | varchar(250) | 250 | Yes | None | none | Field used to store f f; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| IS | varchar(250) | 250 | Yes | None | none | Field used to store i s; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| IP | varchar(250) | 250 | Yes | None | none | Field used to store i p; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| SC | varchar(250) | 250 | Yes | None | none | Field used to store s c; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| SP | varchar(250) | 250 | Yes | None | none | Field used to store s p; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| LW | varchar(250) | 250 | Yes | None | none | Field used to store l w; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| PW | varchar(250) | 250 | Yes | None | none | Field used to store p w; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| PWD | varchar(250) | 250 | Yes | None | none | Field used to store p w d; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| OSY | varchar(250) | 250 | Yes | None | none | Field used to store o s y; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| FR | varchar(250) | 250 | Yes | None | none | Field used to store f r; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| ybDs | varchar(250) | 250 | Yes | None | none | Field used to store yb ds; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| lgbtqia | varchar(250) | 250 | No | None | none | Field used to store lgbtqia; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| editReason | varchar(250) | 250 | Yes | None | none | Field used to store edit reason; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| validation | varchar(250) | 250 | Yes | ? | none | Field used to store validation; exact business meaning to be confirmed by project owner where not obvious from code. | ? | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| batch_id | bigint | 19 | No | None | none | Field used to store batch id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |
| time_stamp | timestamp | N/A | No | CURRENT_TIMESTAMP | Indexed | Field used to store time stamp; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `pages/import.php`, `pages/fetch_data.php`, `pages/update.php` |

### 8.27 `meb_change_history`

**Purpose / Description:** Before-and-after history for MEB edits.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `meb_change_history_helpers.php`, `pages/meb-change-review.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int unsigned | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `meb_change_history_helpers.php`, `pages/meb-change-review.php` |
| meb_id | int | 10 | No | None | Indexed | Field used to store meb id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `meb_change_history_helpers.php`, `pages/meb-change-review.php` |
| user_id | int | 10 | Yes | None | none | Field used to store user id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `meb_change_history_helpers.php`, `pages/meb-change-review.php` |
| edit_reason | text | 65535 | Yes | None | none | Field used to store edit reason; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `meb_change_history_helpers.php`, `pages/meb-change-review.php` |
| before_json | longtext | 4294967295 | No | None | none | Field used to store before json; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `meb_change_history_helpers.php`, `pages/meb-change-review.php` |
| after_json | longtext | 4294967295 | No | None | none | Field used to store after json; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `meb_change_history_helpers.php`, `pages/meb-change-review.php` |
| created_at | datetime | N/A | No | CURRENT_TIMESTAMP | Indexed | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `meb_change_history_helpers.php`, `pages/meb-change-review.php` |

### 8.28 `mebis_consolidator_outputs`

**Purpose / Description:** Generated MEBIS consolidator output history.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `mebis-consolidator/helpers/history.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | bigint unsigned | 20 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `mebis-consolidator/helpers/history.php` |
| output_token | varchar(32) | 32 | No | None | Indexed | Field used to store output token; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `mebis-consolidator/helpers/history.php` |
| filename | varchar(255) | 255 | No | None | none | Field used to store filename; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `mebis-consolidator/helpers/history.php` |
| row_count | int unsigned | 10 | No | 0 | none | Field used to store row count; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `mebis-consolidator/helpers/history.php` |
| source_files_json | longtext | 4294967295 | Yes | None | none | Field used to store source files json; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `mebis-consolidator/helpers/history.php` |
| created_by | int | 10 | Yes | None | none | Field used to store created by; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `mebis-consolidator/helpers/history.php` |
| created_at | datetime | N/A | No | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `mebis-consolidator/helpers/history.php` |

### 8.29 `mebis_lgu_template_outputs`

**Purpose / Description:** Generated MEBIS LGU template output history.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `mebis-lgu-template/helpers/history.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | bigint unsigned | 20 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `mebis-lgu-template/helpers/history.php` |
| output_token | varchar(32) | 32 | No | None | Indexed | Field used to store output token; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `mebis-lgu-template/helpers/history.php` |
| filename | varchar(255) | 255 | No | None | none | Field used to store filename; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `mebis-lgu-template/helpers/history.php` |
| municipality_name | varchar(191) | 191 | No |  | none | Field used to store municipality name; exact business meaning to be confirmed by project owner where not obvious from code. |  | Live schema via information_schema; PHP usage in `mebis-lgu-template/helpers/history.php` |
| row_count | int unsigned | 10 | No | 0 | none | Field used to store row count; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `mebis-lgu-template/helpers/history.php` |
| source_file | varchar(255) | 255 | No |  | none | Field used to store source file; exact business meaning to be confirmed by project owner where not obvious from code. |  | Live schema via information_schema; PHP usage in `mebis-lgu-template/helpers/history.php` |
| created_by | int | 10 | Yes | None | none | Field used to store created by; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `mebis-lgu-template/helpers/history.php` |
| created_at | datetime | N/A | No | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `mebis-lgu-template/helpers/history.php` |

### 8.30 `message_reads`

**Purpose / Description:** Per-user read and trash state for message threads.  
**Primary Key:** id  
**Foreign Keys:** `message_id` -> `contact_messages(id)`; `user_id` -> `users(id)`  
**Primary Source Basis:** `inbox/get_thread.php`, `inbox/mark_read.php`, `inbox/delete_message.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `inbox/get_thread.php`, `inbox/mark_read.php`, `inbox/delete_message.php` |
| message_id | int | 10 | No | None | FK, Indexed | Field used to store message id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `inbox/get_thread.php`, `inbox/mark_read.php`, `inbox/delete_message.php` |
| user_id | int | 10 | No | None | FK, Indexed | Field used to store user id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `inbox/get_thread.php`, `inbox/mark_read.php`, `inbox/delete_message.php` |
| is_read | tinyint(1) | 3 | Yes | 0 | none | Field used to store is read; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `inbox/get_thread.php`, `inbox/mark_read.php`, `inbox/delete_message.php` |
| is_trashed | tinyint(1) | 3 | No | 0 | none | Field used to store is trashed; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `inbox/get_thread.php`, `inbox/mark_read.php`, `inbox/delete_message.php` |
| read_at | timestamp | N/A | Yes | None | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `inbox/get_thread.php`, `inbox/mark_read.php`, `inbox/delete_message.php` |
| trashed_at | datetime | N/A | Yes | None | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `inbox/get_thread.php`, `inbox/mark_read.php`, `inbox/delete_message.php` |

### 8.31 `municipality`

**Purpose / Description:** Reference list of municipalities.  
**Primary Key:** To be confirmed by project owner  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `live_refresh.php`, `payout.php`, `project_targets_helpers.php`, `tmp_schema_check.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | varchar(255) | 255 | No | None | none | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `live_refresh.php`, `payout.php`, `project_targets_helpers.php` |
| province_id | varchar(255) | 255 | No | None | none | Field used to store province id; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `live_refresh.php`, `payout.php`, `project_targets_helpers.php` |
| municipality_name | varchar(255) | 255 | No | None | none | Field used to store municipality name; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `live_refresh.php`, `payout.php`, `project_targets_helpers.php` |

### 8.32 `outgoing`

**Purpose / Description:** Outgoing or forwarded document tracking records.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `pages/track_outgoing.php`, `pages/update_data_out.php`, `pages/forward_document.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `pages/track_outgoing.php`, `pages/update_data_out.php`, `pages/forward_document.php` |
| date_out | date | N/A | No | None | none | Field used to store date out; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `pages/track_outgoing.php`, `pages/update_data_out.php`, `pages/forward_document.php` |
| tracking_number | varchar(250) | 250 | No | None | none | Field used to store tracking number; exact business meaning to be confirmed by project owner where not obvious from code. | 04-15-26-123 | Live schema via information_schema; PHP usage in `pages/track_outgoing.php`, `pages/update_data_out.php`, `pages/forward_document.php` |
| description | varchar(250) | 250 | No | None | none | Field used to store description; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/track_outgoing.php`, `pages/update_data_out.php`, `pages/forward_document.php` |
| remarks | varchar(250) | 250 | Yes | None | none | Field used to store remarks; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/track_outgoing.php`, `pages/update_data_out.php`, `pages/forward_document.php` |
| file_name | varchar(250) | 250 | Yes | None | none | Field used to store file name; exact business meaning to be confirmed by project owner where not obvious from code. | document.pdf | Live schema via information_schema; PHP usage in `pages/track_outgoing.php`, `pages/update_data_out.php`, `pages/forward_document.php` |
| file_type | varchar(250) | 250 | Yes | None | none | Field used to store file type; exact business meaning to be confirmed by project owner where not obvious from code. | application/pdf | Live schema via information_schema; PHP usage in `pages/track_outgoing.php`, `pages/update_data_out.php`, `pages/forward_document.php` |
| file_size | varchar(250) | 250 | Yes | None | none | Field used to store file size; exact business meaning to be confirmed by project owner where not obvious from code. | 245760 | Live schema via information_schema; PHP usage in `pages/track_outgoing.php`, `pages/update_data_out.php`, `pages/forward_document.php` |
| upload_time | datetime | N/A | Yes | None | none | Field used to store upload time; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `pages/track_outgoing.php`, `pages/update_data_out.php`, `pages/forward_document.php` |
| receiving_office | varchar(250) | 250 | No | None | none | Field used to store receiving office; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/track_outgoing.php`, `pages/update_data_out.php`, `pages/forward_document.php` |
| date_forwarded | datetime | N/A | Yes | None | none | Field used to store date forwarded; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `pages/track_outgoing.php`, `pages/update_data_out.php`, `pages/forward_document.php` |
| user_log | varchar(255) | 255 | No | None | none | Field used to store user log; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `pages/track_outgoing.php`, `pages/update_data_out.php`, `pages/forward_document.php` |

### 8.33 `pdos`

**Purpose / Description:** Reference PDO list used by the application.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** To be confirmed by project owner

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in project PHP usage files |
| province_id | varchar(255) | 255 | No | None | none | Field used to store province id; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in project PHP usage files |
| pdo | varchar(255) | 255 | No | None | none | Field used to store pdo; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in project PHP usage files |

### 8.34 `program_activity_actual_projects`

**Purpose / Description:** Normalized actual project/accomplishment rows linked to implementation activity metadata.  
**Primary Key:** id  
**Foreign Keys:** `program_activity_id` -> `program_activity_metadata(id)`  
**Primary Source Basis:** `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | bigint | 19 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| program_activity_id | int | 10 | No | None | FK, Indexed | Field used to store program activity id; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| actual_project_id | varchar(64) | 64 | No | None | Indexed | Stable identifier for the saved actual project/accomplishment row. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| coverage_entry_id | varchar(64) | 64 | Yes | None | none | Identifier linking back to the coverage entry from the activity form. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| target_project_row_id | varchar(64) | 64 | Yes | None | none | Identifier linking the actual accomplishment to a target project row when applicable. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| sort_order | int | 10 | No | 0 | Indexed | Field used to store sort order; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| purok | varchar(255) | 255 | No | None | none | Field used to store purok; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| latitude | decimal(10,7) | 10,7 | Yes | None | none | Field used to store latitude; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| longitude | decimal(10,7) | 10,7 | Yes | None | none | Field used to store longitude; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| project_name | varchar(255) | 255 | No | None | none | Field used to store project name; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| project_classification | varchar(32) | 32 | No | None | none | Field used to store project classification; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| project_type | varchar(255) | 255 | No | None | none | Field used to store project type; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| fertilizer_enabled | tinyint(1) | 3 | No | 0 | none | Field used to store fertilizer enabled; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| fertilizer_ohn_quantity | decimal(14,2) | 14,2 | Yes | None | none | Field used to store fertilizer ohn quantity; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| fertilizer_concoction_quantity | decimal(14,2) | 14,2 | Yes | None | none | Field used to store fertilizer concoction quantity; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| fertilizer_vermicompost_quantity | decimal(14,2) | 14,2 | Yes | None | none | Field used to store fertilizer vermicompost quantity; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| aquatic_resource | varchar(255) | 255 | Yes | None | none | Field used to store aquatic resource; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| aquatic_resource_quantity | int | 10 | Yes | None | none | Field used to store aquatic resource quantity; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| actual_accomplishment | varchar(255) | 255 | Yes | None | none | Field used to store actual accomplishment; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| land_area | varchar(255) | 255 | Yes | None | none | Field used to store land area; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| land_ownership | varchar(255) | 255 | Yes | None | none | Field used to store land ownership; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| drive_link | varchar(2048) | 2048 | Yes | None | none | External documentation or evidence link for the project accomplishment. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| status | varchar(32) | 32 | No | pending | none | Field used to store status; exact business meaning to be confirmed by project owner where not obvious from code. | pending | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| created_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |
| updated_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`, `implementation-status/fetch-project-location-records.php` |

### 8.35 `program_activity_metadata`

**Purpose / Description:** Implementation monitoring rows for location-level program activity data.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| fiscal_year | int | 10 | No | 0 | Indexed | Field used to store fiscal year; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| province | varchar(255) | 255 | No | None | Indexed | Location value stored by the record. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| municipality | varchar(255) | 255 | No | None | Indexed | Location value stored by the record. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| barangay | varchar(255) | 255 | No | None | Indexed | Location value stored by the record. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| plgu_forum | date | N/A | Yes | None | none | Field used to store plgu forum; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| mlgu_forum | date | N/A | Yes | None | none | Field used to store mlgu forum; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| blgu_forum | date | N/A | Yes | None | none | Field used to store blgu forum; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| project_names | varchar(1000) | 1000 | Yes | None | none | Field used to store project names; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| created_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| updated_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| plgu_forum_from | date | N/A | Yes | None | none | Field used to store plgu forum from; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| plgu_forum_to | date | N/A | Yes | None | none | Field used to store plgu forum to; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| mlgu_forum_from | date | N/A | Yes | None | none | Field used to store mlgu forum from; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| mlgu_forum_to | date | N/A | Yes | None | none | Field used to store mlgu forum to; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| blgu_forum_from | date | N/A | Yes | None | none | Field used to store blgu forum from; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| blgu_forum_to | date | N/A | Yes | None | none | Field used to store blgu forum to; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| stage1_start_date | date | N/A | Yes | None | none | Field used to store stage1 start date; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| stage1_end_date | date | N/A | Yes | None | none | Field used to store stage1 end date; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| stage2_start_date | date | N/A | Yes | None | none | Field used to store stage2 start date; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| stage2_end_date | date | N/A | Yes | None | none | Field used to store stage2 end date; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| stage3_start_date | date | N/A | Yes | None | none | Field used to store stage3 start date; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| stage3_end_date | date | N/A | Yes | None | none | Field used to store stage3 end date; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| site_validation | varchar(1000) | 1000 | Yes | None | none | Field used to store site validation; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| drmd_monitoring_from | date | N/A | Yes | None | none | Field used to store drmd monitoring from; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| drmd_monitoring_to | date | N/A | Yes | None | none | Field used to store drmd monitoring to; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| drmd_monitoring_participants | varchar(1000) | 1000 | Yes | None | none | Field used to store drmd monitoring participants; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| joint_post_monitoring_from | date | N/A | Yes | None | none | Field used to store joint post monitoring from; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| joint_post_monitoring_to | date | N/A | Yes | None | none | Field used to store joint post monitoring to; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| joint_post_monitoring_participants | varchar(1000) | 1000 | Yes | None | none | Field used to store joint post monitoring participants; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| payout_schedule_from | date | N/A | Yes | None | none | Field used to store payout schedule from; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| payout_schedule_to | date | N/A | Yes | None | none | Field used to store payout schedule to; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| fund_obligation_partner_beneficiaries | int | 10 | Yes | None | none | Field used to store fund obligation partner beneficiaries; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| fund_disbursement_served_partner_beneficiaries | int | 10 | Yes | None | none | Field used to store fund disbursement served partner beneficiaries; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| liquidation_date | date | N/A | Yes | None | none | Field used to store liquidation date; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| special_disbursing_officer | varchar(255) | 255 | Yes | None | none | Field used to store special disbursing officer; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| last_day_project_implementation | date | N/A | Yes | None | none | Field used to store last day project implementation; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| check_issuance_date | date | N/A | Yes | None | none | Field used to store check issuance date; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| work_accomplishment_report_status | varchar(255) | 255 | Yes | None | none | Field used to store work accomplishment report status; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| performance_rating_remarks | text | 65535 | Yes | None | none | Field used to store performance rating remarks; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| actual_lawa_accomplishment | int | 10 | Yes | None | none | Field used to store actual lawa accomplishment; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| actual_binhi_accomplishment | int | 10 | Yes | None | none | Field used to store actual binhi accomplishment; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| actual_capbuild_accomplishment | int | 10 | Yes | None | none | Field used to store actual capbuild accomplishment; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| actual_community_action_plan_accomplishment | int | 10 | Yes | None | none | Field used to store actual community action plan accomplishment; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_actual_accomplishments | varchar(1000) | 1000 | Yes | None | none | Field used to store coverage actual accomplishments; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_actual_puroks | varchar(1000) | 1000 | Yes | None | none | Field used to store coverage actual puroks; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_actual_project_names | varchar(1000) | 1000 | Yes | None | none | Field used to store coverage actual project names; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_actual_project_classifications | varchar(1000) | 1000 | Yes | None | none | Field used to store coverage actual project classifications; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_actual_land_areas | varchar(1000) | 1000 | Yes | None | none | Field used to store coverage actual land areas; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_actual_land_ownerships | varchar(1000) | 1000 | Yes | None | none | Field used to store coverage actual land ownerships; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_actual_project_types | varchar(1000) | 1000 | Yes | None | none | Field used to store coverage actual project types; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_actual_statuses | varchar(1000) | 1000 | Yes | None | none | Field used to store coverage actual statuses; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_actual_aquatic_resources | varchar(1000) | 1000 | Yes | None | none | Field used to store coverage actual aquatic resources; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_actual_aquatic_resource_quantities | varchar(1000) | 1000 | Yes | None | none | Field used to store coverage actual aquatic resource quantities; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| binhi_sites_established_target | int | 10 | Yes | None | none | Field used to store binhi sites established target; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| binhi_sites_established_actual | int | 10 | Yes | None | none | Field used to store binhi sites established actual; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| binhi_facilities_added_target | int | 10 | Yes | None | none | Field used to store binhi facilities added target; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| binhi_facilities_added_actual | int | 10 | Yes | None | none | Field used to store binhi facilities added actual; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| fertilizer_ohn_target | decimal(14,2) | 14,2 | Yes | None | none | Field used to store fertilizer ohn target; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| fertilizer_ohn_actual | decimal(14,2) | 14,2 | Yes | None | none | Field used to store fertilizer ohn actual; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| fertilizer_concoction_target | decimal(14,2) | 14,2 | Yes | None | none | Field used to store fertilizer concoction target; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| fertilizer_concoction_actual | decimal(14,2) | 14,2 | Yes | None | none | Field used to store fertilizer concoction actual; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| fertilizer_vermicompost_target | decimal(14,2) | 14,2 | Yes | None | none | Field used to store fertilizer vermicompost target; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| fertilizer_vermicompost_actual | decimal(14,2) | 14,2 | Yes | None | none | Field used to store fertilizer vermicompost actual; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| area_land_utilized_target | decimal(14,2) | 14,2 | Yes | None | none | Field used to store area land utilized target; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_fertilizer_enabled_flags | varchar(1000) | 1000 | Yes | None | none | Field used to store coverage fertilizer enabled flags; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_fertilizer_ohn_quantities | text | 65535 | Yes | None | none | Field used to store coverage fertilizer ohn quantities; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_fertilizer_concoction_quantities | text | 65535 | Yes | None | none | Field used to store coverage fertilizer concoction quantities; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_fertilizer_vermicompost_quantities | text | 65535 | Yes | None | none | Field used to store coverage fertilizer vermicompost quantities; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_actual_coordinates | text | 65535 | Yes | None | none | Field used to store coverage actual coordinates; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_actual_latitude | text | 65535 | Yes | None | none | Field used to store coverage actual latitude; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_actual_longitude | text | 65535 | Yes | None | none | Field used to store coverage actual longitude; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_entry_ids | text | 65535 | Yes | None | none | Field used to store coverage entry ids; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| actual_project_ids | text | 65535 | Yes | None | none | Field used to store actual project ids; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| target_project_row_ids | text | 65535 | Yes | None | none | Field used to store target project row ids; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |
| coverage_drive_links | text | 65535 | Yes | None | none | Field used to store coverage drive links; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `implementation-status/activity_metadata.php`, `implementation-status/save-imp-status.php` |

### 8.36 `project_lawa_binhi_targets`

**Purpose / Description:** Annual LAWA and BINHI target configuration by location.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `project_targets_helpers.php`, `implementation-status/save-project-target.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| fiscal_year | int | 10 | No | None | Indexed | Field used to store fiscal year; exact business meaning to be confirmed by project owner where not obvious from code. | 2026 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| province | varchar(255) | 255 | No | None | Indexed | Location value stored by the record. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| municipality | varchar(255) | 255 | No | None | Indexed | Location value stored by the record. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| barangay | varchar(255) | 255 | No | None | Indexed | Location value stored by the record. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| target_partner_beneficiaries | int | 10 | No | 0 | none | Field used to store target partner beneficiaries; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| created_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| updated_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| puroks | text | 65535 | Yes | None | none | Field used to store puroks; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| coordinates | text | 65535 | Yes | None | none | Field used to store coordinates; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| project_row_ids | text | 65535 | Yes | None | none | Field used to store project row ids; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| latitude | text | 65535 | Yes | None | none | Field used to store latitude; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| longitude | text | 65535 | Yes | None | none | Field used to store longitude; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| project_names | text | 65535 | Yes | None | none | Field used to store project names; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| project_classifications | text | 65535 | Yes | None | none | Field used to store project classifications; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| fertilizer_enabled_flags | text | 65535 | Yes | None | none | Field used to store fertilizer enabled flags; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| fertilizer_ohn_targets | text | 65535 | Yes | None | none | Field used to store fertilizer ohn targets; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| fertilizer_concoction_targets | text | 65535 | Yes | None | none | Field used to store fertilizer concoction targets; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| fertilizer_vermicompost_targets | text | 65535 | Yes | None | none | Field used to store fertilizer vermicompost targets; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| binhi_target_quantities | text | 65535 | Yes | None | none | Field used to store binhi target quantities; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| aquatic_resources | text | 65535 | Yes | None | none | Field used to store aquatic resources; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| aquatic_resource_quantities | text | 65535 | Yes | None | none | Field used to store aquatic resource quantities; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| lawa_target | int | 10 | No | 0 | none | Field used to store lawa target; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| binhi_target | int | 10 | No | 0 | none | Field used to store binhi target; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| binhi_vegetable_target | int | 10 | No | 0 | none | Field used to store binhi vegetable target; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| binhi_crops_target | int | 10 | No | 0 | none | Field used to store binhi crops target; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| binhi_disaster_resilient_crops_target | int | 10 | No | 0 | none | Field used to store binhi disaster resilient crops target; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| binhi_fruit_bearing_trees_target | int | 10 | No | 0 | none | Field used to store binhi fruit bearing trees target; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| binhi_tilapia_target | int | 10 | No | 0 | none | Field used to store binhi tilapia target; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| capbuild_target | int | 10 | No | 2 | none | Field used to store capbuild target; exact business meaning to be confirmed by project owner where not obvious from code. | 2 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| community_action_plan_target | int | 10 | No | 1 | none | Field used to store community action plan target; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |
| project_types | text | 65535 | Yes | None | none | Field used to store project types; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php` |

### 8.37 `project_target_entries`

**Purpose / Description:** Normalized per-project target rows linked to annual LAWA and BINHI target records.  
**Primary Key:** id  
**Foreign Keys:** `target_id` -> `project_lawa_binhi_targets(id)`  
**Primary Source Basis:** `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | bigint | 19 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php` |
| target_id | int | 10 | No | None | FK, Indexed | Parent LAWA/BINHI target record identifier. | 1 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php` |
| row_id | varchar(64) | 64 | No | None | Indexed | Stable row identifier for the target project entry. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php` |
| sort_order | int | 10 | No | 0 | Indexed | Field used to store sort order; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php` |
| purok | varchar(255) | 255 | No | None | none | Field used to store purok; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php` |
| project_name | varchar(255) | 255 | No | None | none | Field used to store project name; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php` |
| project_type | varchar(255) | 255 | No | None | none | Field used to store project type; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php` |
| project_classification | varchar(32) | 32 | No | None | none | Field used to store project classification; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php` |
| fertilizer_enabled | tinyint(1) | 3 | No | 0 | none | Field used to store fertilizer enabled; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php` |
| fertilizer_ohn_target | decimal(14,2) | 14,2 | Yes | None | none | Field used to store fertilizer ohn target; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php` |
| fertilizer_concoction_target | decimal(14,2) | 14,2 | Yes | None | none | Field used to store fertilizer concoction target; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php` |
| fertilizer_vermicompost_target | decimal(14,2) | 14,2 | Yes | None | none | Field used to store fertilizer vermicompost target; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php` |
| binhi_target_quantity | int | 10 | Yes | None | none | Field used to store binhi target quantity; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php` |
| aquatic_resource | varchar(255) | 255 | Yes | None | none | Field used to store aquatic resource; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php` |
| aquatic_resource_quantity | int | 10 | Yes | None | none | Field used to store aquatic resource quantity; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php` |
| created_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php` |
| updated_at | timestamp | N/A | Yes | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `project_targets_helpers.php`, `implementation-status/save-project-target.php`, `implementation-status/fetch-project-targets.php` |

### 8.38 `project_variable_config`

**Purpose / Description:** Fiscal-year project variables, rates, and factor values.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `project_variable_helpers.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `project_variable_helpers.php` |
| fiscal_year | int | 10 | No | None | Indexed | Field used to store fiscal year; exact business meaning to be confirmed by project owner where not obvious from code. | 2026 | Live schema via information_schema; PHP usage in `project_variable_helpers.php` |
| variable_key | varchar(100) | 100 | No | None | Indexed | Field used to store variable key; exact business meaning to be confirmed by project owner where not obvious from code. | daily_wage_rate | Live schema via information_schema; PHP usage in `project_variable_helpers.php` |
| variable_label | varchar(150) | 150 | No | None | none | Field used to store variable label; exact business meaning to be confirmed by project owner where not obvious from code. | Daily Wage Rate | Live schema via information_schema; PHP usage in `project_variable_helpers.php` |
| value_type | varchar(20) | 20 | No | number | none | Field used to store value type; exact business meaning to be confirmed by project owner where not obvious from code. | number | Live schema via information_schema; PHP usage in `project_variable_helpers.php` |
| value_number | decimal(14,4) | 14,4 | Yes | None | none | Field used to store value number; exact business meaning to be confirmed by project owner where not obvious from code. | 0.00 | Live schema via information_schema; PHP usage in `project_variable_helpers.php` |
| value_text | text | 65535 | Yes | None | none | Field used to store value text; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_variable_helpers.php` |
| unit | varchar(50) | 50 | Yes | None | none | Field used to store unit; exact business meaning to be confirmed by project owner where not obvious from code. | PHP/day | Live schema via information_schema; PHP usage in `project_variable_helpers.php` |
| notes | varchar(255) | 255 | Yes | None | none | Field used to store notes; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `project_variable_helpers.php` |
| updated_by | int | 10 | Yes | None | none | Field used to store updated by; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `project_variable_helpers.php` |
| created_at | datetime | N/A | No | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `project_variable_helpers.php` |
| updated_at | datetime | N/A | No | CURRENT_TIMESTAMP | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `project_variable_helpers.php` |

### 8.39 `provinces`

**Purpose / Description:** Reference list of provinces.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `home.php`, `implementation-status/program-summary-template.php`, `pages/meb-batch-summary.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | varchar(255) | 255 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `home.php`, `implementation-status/program-summary-template.php`, `pages/meb-batch-summary.php` |
| region_id | varchar(255) | 255 | No | None | none | Field used to store region id; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `home.php`, `implementation-status/program-summary-template.php`, `pages/meb-batch-summary.php` |
| province_name | varchar(255) | 255 | No | None | none | Field used to store province name; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `home.php`, `implementation-status/program-summary-template.php`, `pages/meb-batch-summary.php` |

### 8.40 `users`

**Purpose / Description:** User accounts, authentication state, profile data, and role or 2FA metadata.  
**Primary Key:** id  
**Foreign Keys:** None declared in the current schema.  
**Primary Source Basis:** `config.php`, `login.php`, `register.php`, `auth_helpers.php`, `sso_helpers.php`, `two_factor_helpers.php`

| Column Name | Data Type | Length / Size | Null Allowed | Default Value | Key Type | Description / Business Meaning | Example Value | Source Basis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| id | int | 10 | No | None | PK | Primary identifier for the row. | 1 | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| username | varchar(255) | 255 | No | None | none | Field used to store username; exact business meaning to be confirmed by project owner where not obvious from code. | jdoe | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| password | varchar(255) | 255 | No | None | none | Field used to store password; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| password_policy_version | int | 10 | No | 0 | none | Field used to store password policy version; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| password_changed_at | datetime | N/A | Yes | None | none | Field used to store password changed at; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| must_change_password | tinyint(1) | 3 | No | 0 | none | Field used to store must change password; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| password_strength_notified_at | datetime | N/A | Yes | None | none | Field used to store password strength notified at; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| last_name | varchar(250) | 250 | No | None | none | Field used to store last name; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| first_name | varchar(250) | 250 | No | None | none | Field used to store first name; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| middle_name | varchar(250) | 250 | No | None | none | Field used to store middle name; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| ext | varchar(250) | 250 | No | None | none | Field used to store ext; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| email | varchar(250) | 250 | No | None | none | Field used to store email; exact business meaning to be confirmed by project owner where not obvious from code. | user@example.com | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| sso_subject | varchar(255) | 255 | Yes | None | none | Field used to store sso subject; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| id_number | varchar(100) | 100 | Yes | None | none | Field used to store id number; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| contact_number | varchar(50) | 50 | Yes | None | none | Field used to store contact number; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| sso_avatar_url | varchar(2048) | 2048 | Yes | None | none | Field used to store sso avatar url; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| position | varchar(250) | 250 | No | None | none | Field used to store position; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| positionAbr | varchar(10) | 10 | No | None | none | Field used to store position abr; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| area | varchar(250) | 250 | No | None | none | Field used to store area; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| picture | varchar(255) | 255 | No | default.webp | none | Field used to store picture; exact business meaning to be confirmed by project owner where not obvious from code. | default.webp | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| remember_token | varchar(64) | 64 | Yes | None | none | Field used to store remember token; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| reset_token | varchar(255) | 255 | Yes | None | none | Field used to store reset token; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| reset_token_expiry | datetime | N/A | Yes | None | none | Field used to store reset token expiry; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| two_fa_enabled | tinyint(1) | 3 | Yes | 1 | none | Field used to store two fa enabled; exact business meaning to be confirmed by project owner where not obvious from code. | 1 | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| two_fa_secret | varchar(64) | 64 | Yes | None | none | Authenticator secret used for 2FA when enabled. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| two_fa_confirmed_at | datetime | N/A | Yes | None | none | Field used to store two fa confirmed at; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| two_fa_recovery_codes | text | 65535 | Yes | None | none | Recovery-code payload used for account recovery. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| two_fa_recovery_generated_at | datetime | N/A | Yes | None | none | Field used to store two fa recovery generated at; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| two_fa_code | varchar(6) | 6 | Yes | None | none | Field used to store two fa code; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| two_fa_code_expiry | datetime | N/A | Yes | None | none | Field used to store two fa code expiry; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| userType | varchar(10) | 10 | No | user | none | Authorization role such as user, editor, aa, or admin. | user | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| deleted_at | datetime | N/A | Yes | None | none | Timestamp field used by the workflow. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| date_registered | timestamp | N/A | No | None | none | Field used to store date registered; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| last_login_at | datetime | N/A | Yes | None | none | Field used to store last login at; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| last_activity | datetime | N/A | Yes | None | none | Field used to store last activity; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| is_online | tinyint(1) | 3 | Yes | 0 | none | Field used to store is online; exact business meaning to be confirmed by project owner where not obvious from code. | 0 | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| theme_preference | varchar(10) | 10 | No | light | none | Field used to store theme preference; exact business meaning to be confirmed by project owner where not obvious from code. | light | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| role_change_old_type | varchar(20) | 20 | Yes | None | none | Field used to store role change old type; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| role_change_new_type | varchar(20) | 20 | Yes | None | none | Field used to store role change new type; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| role_change_reason | varchar(30) | 30 | Yes | None | none | Field used to store role change reason; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| role_change_message | varchar(255) | 255 | Yes | None | none | Field used to store role change message; exact business meaning to be confirmed by project owner where not obvious from code. | To be confirmed by project owner | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |
| role_change_force_logout_at | datetime | N/A | Yes | None | none | Field used to store role change force logout at; exact business meaning to be confirmed by project owner where not obvious from code. | 2026-04-15 09:30:00 | Live schema via information_schema; PHP usage in `config.php`, `login.php`, `register.php` |

## 9. Key Relationships and Constraints

| Table | Foreign-Key Column(s) | Referenced Table | Referenced Column(s) | On Delete | On Update |
| --- | --- | --- | --- | --- | --- |
| app_notification_reads | notification_id | app_notifications | id | CASCADE | NO ACTION |
| contact_message_recipients | message_id | contact_messages | id | CASCADE | NO ACTION |
| contact_message_recipients | user_id | users | id | SET NULL | NO ACTION |
| contact_replies | message_id | contact_messages | id | CASCADE | NO ACTION |
| contact_replies | user_id | users | id | CASCADE | NO ACTION |
| deduplication_results | job_id | deduplication_jobs | id | NO ACTION | NO ACTION |
| event_guests | event_id | events | id | CASCADE | NO ACTION |
| event_guests | user_id | users | id | SET NULL | NO ACTION |
| fund_monitoring_entries | item_id | fund_monitoring_items | id | CASCADE | NO ACTION |
| message_reads | message_id | contact_messages | id | CASCADE | NO ACTION |
| message_reads | user_id | users | id | CASCADE | NO ACTION |
| program_activity_actual_projects | program_activity_id | program_activity_metadata | id | CASCADE | NO ACTION |
| project_target_entries | target_id | project_lawa_binhi_targets | id | CASCADE | NO ACTION |

## 10. Notes on Data Integrity and Validation

- The application performs runtime schema assurance in multiple helpers, so the live schema is the authoritative basis for this document.
- File-upload modules validate file type and size before writing metadata to tracking tables.
- The inbox module supplements thread and reply tables with per-user read and trash-state tracking.
- Fund monitoring uses unique keys and parent-child detail tables to reduce duplicate monthly entries.
- Several logical relationships are enforced at the PHP level without declared foreign-key constraints.

## 11. Assumptions and Items for Confirmation

- The schema source of truth for this document is the live KODUS database reached through `config.php`; SQL dump files were intentionally excluded per instruction.
- The `aatracker` table retains several legacy field names for historical document-routing data; field labels should be confirmed with the process owner before production publication.
- The active `events` schema uses `all_day`, but the older `pages/events.php` endpoint still references `allDay`; this appears to be a legacy path and should be confirmed or retired.
- The file `pages/fetch_ph_holidays.php` writes to a `holidays` table, but that table is not present in the current configured database and is therefore excluded from the detailed dictionary.
- Reference tables such as `barangay` and `municipality` do not currently declare full relational constraints even though the application uses them operationally.

## 12. Conclusion

Based on the current configured KODUS database and the present PHP codebase, the system is operating on a live schema composed of user-management, messaging, document-tracking, beneficiary, calendar, fund-monitoring, crossmatch, deduplication, and implementation-monitoring tables. This document is suitable as a hosting-requirements submission artifact, provided the noted inconsistencies and confirmation items are reviewed before final production sign-off.

Document note: This document was derived from the current KODUS codebase and live configured database and may require final schema validation before production hosting.
