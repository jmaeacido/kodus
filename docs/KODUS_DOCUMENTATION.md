# KODUS Application Documentation

## 1. Overview

KODUS stands for **KliMalasakit Online Document Updating System**. It is a PHP and MySQL web application used to manage beneficiary records, operational tracking, implementation reporting, staff communication, and selected data-cleaning utilities.

The app is built on top of:

- PHP with procedural page controllers and shared helper files
- MySQL via `mysqli`
- AdminLTE 3 and Bootstrap 4 for the UI shell
- DataTables for record browsing and export-oriented tables
- Chart.js for dashboard visuals
- PHPMailer for transactional email
- PhpSpreadsheet for Excel import and export flows
- Dotenv for environment-based configuration

At a high level, KODUS supports:

- user registration, login, remember-me, and optional email-based 2FA
- beneficiary masterlist and validation workflows
- incoming, outgoing, and payout tracking
- calendar and event reminders
- implementation-status planning and accomplishment monitoring
- internal contact/inbox messaging with attachments
- admin user management
- crossmatch and deduplication utilities for beneficiary datasets

## 2. High-Level Architecture

KODUS follows a classic PHP page-based architecture:

1. A request enters a page script such as `home.php`, `pages/data-tracking-meb.php`, or `admin/users_management.php`.
2. Shared bootstrapping is loaded through [`header.php`](C:\laragon\www\kodus\header.php) and [`config.php`](C:\laragon\www\kodus\config.php).
3. Authentication, session checks, security headers, CSRF support, and runtime configuration are applied.
4. The page renders HTML and usually fetches or mutates data through companion endpoints.
5. AJAX endpoints return JSON or HTML fragments that DataTables and UI widgets consume.

Core shared files:

- [`header.php`](C:\laragon\www\kodus\header.php): central bootstrap for authenticated pages, session timeout enforcement, page-visit auditing, and role-change checks
- [`config.php`](C:\laragon\www\kodus\config.php): environment loading, database connection, timezone setup, theme schema enforcement, and audit schema hooks
- [`security.php`](C:\laragon\www\kodus\security.php): CSRF, request method guards, same-origin checks, rate limiting, cookie configuration, password rules, and upload MIME detection
- [`auth_helpers.php`](C:\laragon\www\kodus\auth_helpers.php): public-page detection, session login storage, remember-me restoration, redirect logic, and security headers
- [`notification_helpers.php`](C:\laragon\www\kodus\notification_helpers.php): email rendering, mail logging, and audit logging helpers
- [`sidenav.php`](C:\laragon\www\kodus\sidenav.php): main navigation, unread-message counts, calendar badge, theme toggle, and topbar message feed

## 3. Directory Guide

### Root-level pages

- [`index.php`](C:\laragon\www\kodus\index.php): login screen
- [`login.php`](C:\laragon\www\kodus\login.php): login processing
- [`register.php`](C:\laragon\www\kodus\register.php): user self-registration
- [`verify-2fa.php`](C:\laragon\www\kodus\verify-2fa.php): intermediate 2FA verification screen
- [`settings.php`](C:\laragon\www\kodus\settings.php): profile, password, theme, avatar, account deletion, and 2FA controls
- [`home.php`](C:\laragon\www\kodus\home.php): dashboard and quick links
- [`contact.php`](C:\laragon\www\kodus\contact.php): message composer
- [`inbox/index.php`](C:\laragon\www\kodus\inbox\index.php): threaded inbox UI

### Operational modules

- [`pages/`](C:\laragon\www\kodus\pages): beneficiary, incoming, outgoing, payout, calendar, import/export, and summary reporting screens
- [`implementation-status/`](C:\laragon\www\kodus\implementation-status): baseline targets and program activities tracking
- [`admin/`](C:\laragon\www\kodus\admin): administrative user-management tools
- [`crossmatch/`](C:\laragon\www\kodus\crossmatch): cross-file and DB-vs-file matching
- [`deduplication/`](C:\laragon\www\kodus\deduplication): duplicate detection within uploaded datasets

### Supporting assets and libraries

- [`dist/`](C:\laragon\www\kodus\dist): compiled AdminLTE assets and app images
- [`plugins/`](C:\laragon\www\kodus\plugins): JS/CSS libraries used by the UI
- [`vendor/`](C:\laragon\www\kodus\vendor): Composer dependencies
- [`scripts/`](C:\laragon\www\kodus\scripts): maintenance scripts such as app-version updates
- [`kodus_db.sql`](C:\laragon\www\kodus\kodus_db.sql): database dump with schema and seed data

## 4. Main Functional Areas

### 4.1 Authentication and account lifecycle

KODUS supports a full account flow:

- year selection before login through [`select_year.php`](C:\laragon\www\kodus\select_year.php)
- username/password login
- remember-me token login restoration
- optional email-based two-factor authentication
- password reset and recovery
- user self-registration
- session timeout logout after one hour of inactivity
- forced logout after admin role changes or deactivation

Important related files:

- [`index.php`](C:\laragon\www\kodus\index.php)
- [`login.php`](C:\laragon\www\kodus\login.php)
- [`verify_2fa_code.php`](C:\laragon\www\kodus\verify_2fa_code.php)
- [`send_2fa_code.php`](C:\laragon\www\kodus\send_2fa_code.php)
- [`forgot-password.php`](C:\laragon\www\kodus\forgot-password.php)
- [`send-reset-link.php`](C:\laragon\www\kodus\send-reset-link.php)
- [`reset-password.php`](C:\laragon\www\kodus\reset-password.php)
- [`recover-password.php`](C:\laragon\www\kodus\recover-password.php)
- [`update-password.php`](C:\laragon\www\kodus\update-password.php)

### 4.2 Dashboard

The dashboard in [`home.php`](C:\laragon\www\kodus\home.php) provides:

- beneficiary totals and geographic counts
- sex distribution and NHTS-PR classification charts
- sectoral disaggregation
- quick links into daily workflows
- fiscal-year context taken from the selected year in session

Its data is loaded from [`get_data.php`](C:\laragon\www\kodus\get_data.php).

### 4.3 Partner-Beneficiaries / MEB

The Masterlist of Eligible Beneficiaries is one of the core datasets in KODUS.

Primary functions:

- browse beneficiary records with server-side DataTables
- import Excel data
- export data
- bulk edit or delete selected records
- validate records on a separate admin-oriented validation page

Key files:

- [`pages/data-tracking-meb.php`](C:\laragon\www\kodus\pages\data-tracking-meb.php)
- [`pages/data-tracking-meb-edit.php`](C:\laragon\www\kodus\pages\data-tracking-meb-edit.php)
- [`pages/data-tracking-meb-validation.php`](C:\laragon\www\kodus\pages\data-tracking-meb-validation.php)
- [`pages/fetch_data.php`](C:\laragon\www\kodus\pages\fetch_data.php)
- [`pages/fetch_data_validation.php`](C:\laragon\www\kodus\pages\fetch_data_validation.php)
- [`pages/import.php`](C:\laragon\www\kodus\pages\import.php)
- [`pages/export_meb.php`](C:\laragon\www\kodus\pages\export_meb.php)

The structure of this screen and the SQL dump indicate that the `meb` table stores personal, geographic, and sectoral-classification fields used throughout the dashboard and reports.

### 4.4 Incoming, outgoing, and payout tracking

KODUS also tracks document or transaction movement beyond the beneficiary masterlist.

Available screens include:

- [`pages/data-tracking-in.php`](C:\laragon\www\kodus\pages\data-tracking-in.php)
- [`pages/data-tracking-out.php`](C:\laragon\www\kodus\pages\data-tracking-out.php)
- [`pages/payout.php`](C:\laragon\www\kodus\pages\payout.php)

These are supported by fetch, update, and export endpoints such as:

- [`pages/fetch_data_in.php`](C:\laragon\www\kodus\pages\fetch_data_in.php)
- [`pages/fetch_data_out.php`](C:\laragon\www\kodus\pages\fetch_data_out.php)
- [`pages/update_data_out.php`](C:\laragon\www\kodus\pages\update_data_out.php)
- [`pages/payout_export.php`](C:\laragon\www\kodus\pages\payout_export.php)

Based on the SQL schema, these workflows map to tables such as `incoming`, `outgoing`, and possibly `trackdata`.

### 4.5 Calendar and events

The calendar module lets teams manage schedules and reminders.

Key capabilities:

- view calendar events
- add, update, and delete events
- fetch holiday data
- send event-related emails

Relevant files:

- [`pages/calendar.php`](C:\laragon\www\kodus\pages\calendar.php)
- [`pages/add_event.php`](C:\laragon\www\kodus\pages\add_event.php)
- [`pages/update_event.php`](C:\laragon\www\kodus\pages\update_event.php)
- [`pages/delete_event.php`](C:\laragon\www\kodus\pages\delete_event.php)
- [`pages/sendEventEmails.php`](C:\laragon\www\kodus\pages\sendEventEmails.php)

The navigation badge in [`sidenav.php`](C:\laragon\www\kodus\sidenav.php) counts active events for the current day.

### 4.6 Reports and summaries

Reporting is exposed through the summary pages under [`pages/summary`](C:\laragon\www\kodus\pages\summary).

Current report areas exposed from the sidebar:

- Partner-Beneficiaries Profile
- Sectoral Data Summary
- PWD summary
- PWD sex disaggregation

These screens consume beneficiary records and dashboard aggregates to give program-level visibility.

### 4.7 Implementation Status

The implementation-status module is split into two major parts:

- baseline targets
- program activities

#### Baseline Targets

[`implementation-status/program-targets.php`](C:\laragon\www\kodus\implementation-status\program-targets.php) lets administrators:

- manually add project targets
- import targets from Excel
- organize targets by province, municipality, barangay, and purok
- associate project names and classifications such as LAWA and BINHI
- set target partner-beneficiary counts

Non-admin users appear to have read-only access.

#### Program Activities

[`implementation-status/program-activities.php`](C:\laragon\www\kodus\implementation-status\program-activities.php) manages richer accomplishment tracking with structured activity data and coverage sections. The implementation suggests detailed reporting around program phases, social preparation, and site validation.

Supporting endpoints include:

- [`implementation-status/fetch-project-targets.php`](C:\laragon\www\kodus\implementation-status\fetch-project-targets.php)
- [`implementation-status/save-project-target.php`](C:\laragon\www\kodus\implementation-status\save-project-target.php)
- [`implementation-status/import-project-targets.php`](C:\laragon\www\kodus\implementation-status\import-project-targets.php)
- [`implementation-status/fetch-program-activities.php`](C:\laragon\www\kodus\implementation-status\fetch-program-activities.php)
- [`implementation-status/get-program-activity.php`](C:\laragon\www\kodus\implementation-status\get-program-activity.php)
- [`implementation-status/save-imp-status.php`](C:\laragon\www\kodus\implementation-status\save-imp-status.php)

The main table for this module is `imp_status`.

### 4.8 Messaging, contact, and inbox

KODUS includes an internal communication feature set:

- [`contact.php`](C:\laragon\www\kodus\contact.php): compose messages with optional attachments
- [`send_contact.php`](C:\laragon\www\kodus\send_contact.php): deliver and store outbound messages
- [`inbox/index.php`](C:\laragon\www\kodus\inbox\index.php): mailbox UI
- [`inbox/get_thread.php`](C:\laragon\www\kodus\inbox\get_thread.php): load thread details
- [`inbox/send_reply.php`](C:\laragon\www\kodus\inbox\send_reply.php): reply within message threads
- [`inbox/get_notification_feed.php`](C:\laragon\www\kodus\inbox\get_notification_feed.php): topbar notification feed
- [`inbox/get_unread_count.php`](C:\laragon\www\kodus\inbox\get_unread_count.php): unread badge counts

Messaging behavior includes:

- recipients can be admins, all users, all users plus admins, or a specific user
- non-admin messages default to the admin mailbox if no explicit user target is chosen
- messages are stored in `contact_messages`
- read state is stored in `message_reads`
- replies are supported through dedicated inbox endpoints
- attachments are stored under `inbox/uploads`
- outgoing emails are also sent through SMTP

### 4.9 Admin user management

Administrators can manage users through [`admin/users_management.php`](C:\laragon\www\kodus\admin\users_management.php).

Implemented/admin-visible functions include:

- list active and deactivated users
- inspect online, idle, and offline states
- change user type or classification
- deactivate users
- restore deactivated users

Related files:

- [`admin/change_user_type.php`](C:\laragon\www\kodus\admin\change_user_type.php)
- [`admin/deactivate_user.php`](C:\laragon\www\kodus\admin\deactivate_user.php)
- [`admin/restore_user.php`](C:\laragon\www\kodus\admin\restore_user.php)
- [`role_change_helpers.php`](C:\laragon\www\kodus\role_change_helpers.php)
- [`role-change-status.php`](C:\laragon\www\kodus\role-change-status.php)

The role-change mechanism is wired into [`header.php`](C:\laragon\www\kodus\header.php), so affected users are notified and signed out when their access changes.

### 4.10 Crossmatching

The crossmatch utility compares beneficiary datasets to identify possible matches.

Supported modes from [`crossmatch/index.php`](C:\laragon\www\kodus\crossmatch\index.php):

- KODUS DB vs file
- file vs file

Configuration options:

- similarity threshold
- soft or strict birthdate rule
- uploaded Excel or CSV files

Related files:

- [`crossmatch/upload_handler.php`](C:\laragon\www\kodus\crossmatch\upload_handler.php)
- [`crossmatch/start.php`](C:\laragon\www\kodus\crossmatch\start.php)
- [`crossmatch/run.php`](C:\laragon\www\kodus\crossmatch\run.php)
- [`crossmatch/run_job.php`](C:\laragon\www\kodus\crossmatch\run_job.php)
- [`crossmatch/results.php`](C:\laragon\www\kodus\crossmatch\results.php)
- [`crossmatch/export.php`](C:\laragon\www\kodus\crossmatch\export.php)

The SQL dump shows `crossmatch_jobs` and `crossmatch_results` tables to persist runs and findings.

### 4.11 Deduplication

The deduplication utility detects likely duplicate rows within an uploaded file.

Configured through [`deduplication/index.php`](C:\laragon\www\kodus\deduplication\index.php):

- threshold percentage
- soft or strict matching rule
- one uploaded Excel or CSV file

Related files:

- [`deduplication/upload_handler.php`](C:\laragon\www\kodus\deduplication\upload_handler.php)
- [`deduplication/worker.php`](C:\laragon\www\kodus\deduplication\worker.php)
- [`deduplication/worker_v2.php`](C:\laragon\www\kodus\deduplication\worker_v2.php)
- [`deduplication/results.php`](C:\laragon\www\kodus\deduplication\results.php)
- [`deduplication/export_results.php`](C:\laragon\www\kodus\deduplication\export_results.php)

The database persists activity in `deduplication_jobs` and `deduplication_results`.

## 5. Database Overview

The application uses MySQL and connects through credentials in the environment file.

Tables visible in [`kodus_db.sql`](C:\laragon\www\kodus\kodus_db.sql) include:

- `users`
- `audit_logs`
- `mail_logs`
- `contact_messages`
- `contact_replies`
- `message_reads`
- `events`
- `draggable_events`
- `meb`
- `incoming`
- `outgoing`
- `trackdata`
- `imp_status`
- `crossmatch_jobs`
- `crossmatch_results`
- `deduplication_jobs`
- `deduplication_results`
- `barangay`
- `municipality`
- `provinces`

From the codebase, the most important data domains are:

- identity and access: `users`, role-change state, remember tokens, reset tokens
- messaging and mail history: `contact_messages`, `message_reads`, `mail_logs`, `audit_logs`
- program records: `meb`, `incoming`, `outgoing`, `imp_status`
- utilities: crossmatch and deduplication job/result tables
- scheduling: `events`

## 6. Setup and Installation

### Prerequisites

- PHP compatible with the installed Composer dependencies
- MySQL or MariaDB
- Composer
- optional Node.js and npm if frontend asset rebuilding is needed
- SMTP credentials for notification features

### Environment configuration

Copy [`.env.example`](C:\laragon\www\kodus\.env.example) to `.env` and configure:

```env
DB_HOST=127.0.0.1
DB_USERNAME=root
DB_PASSWORD=
DB_NAME=kodus_db

SMTP_HOST=smtp.gmail.com
SMTP_PORT=465
SMTP_USERNAME=your-email@example.com
SMTP_PASSWORD=your-app-password
SMTP_FROM_NAME="KODUS Admin"
SMTP_FROM_ADDRESS=your-email@example.com
```

### Composer dependencies

Install PHP packages:

```powershell
composer install
```

Required PHP libraries from [`composer.json`](C:\laragon\www\kodus\composer.json):

- `phpoffice/phpspreadsheet`
- `phpmailer/phpmailer`
- `vlucas/phpdotenv`

### Database import

Create the target database and import the SQL dump:

```powershell
mysql -u root -p kodus_db < kodus_db.sql
```

### Local web serving

In a Laragon setup, point the web root at the project and browse to the app through the local host configured for the workspace.

If the app is served from a subdirectory, confirm [`base_url.php`](C:\laragon\www\kodus\base_url.php) matches the environment.

### Optional frontend tooling

The repository still contains AdminLTE build tooling in [`package.json`](C:\laragon\www\kodus\package.json). This is only needed if you want to rebuild the theme assets.

Typical commands:

```powershell
npm install
npm run production
```

## 7. Security Features

Security controls implemented in code include:

- CSRF token generation and validation for POST and AJAX requests
- same-origin enforcement for state-changing requests
- secure cookie settings with `HttpOnly` and `SameSite=Lax`
- strict session mode and cookie-only sessions
- login rate limiting in session storage
- strong password validation requiring 12+ characters with mixed character classes
- optional email-based 2FA
- remember-me token hashing
- security headers such as `X-Frame-Options`, `X-Content-Type-Options`, and `Referrer-Policy`
- HSTS when the app is served over HTTPS
- server-side MIME inspection for uploads
- audit logging for security-relevant actions

Operational note:

`config.php` and multiple page scripts set the timezone to `Asia/Manila`, while the execution environment may differ. If the app is deployed elsewhere, keep the server, PHP, and MySQL timezone assumptions aligned.

## 8. Email and Notifications

KODUS uses SMTP through PHPMailer.

Email-triggering flows include:

- welcome email on registration
- login notifications
- 2FA code delivery and 2FA state-change notifications
- password reset
- contact/inbox delivery and auto-replies
- event emails

Mail configuration is centralized in:

- [`mail_config.php`](C:\laragon\www\kodus\mail_config.php)
- [`notification_helpers.php`](C:\laragon\www\kodus\notification_helpers.php)

All mail attempts can be logged into `mail_logs`.

## 9. User Roles and Access Patterns

The codebase clearly distinguishes at least these roles:

- `admin`
- `user`
- `aa` in some recipient filtering logic

Observed access behavior:

- admins can access user management, target imports, validation views, and broad messaging options
- non-admin users still have access to most operational pages but are restricted on admin-only actions
- some utility pages currently have commented-out admin guards, so access control there should be reviewed before production hardening

## 10. Important Workflows

### New user onboarding

1. User selects a fiscal year.
2. User registers through the public registration page.
3. The app writes a new `users` record and sends a welcome email.
4. The user logs in and may be prompted for 2FA depending on account settings.

### Daily operations

1. User opens the dashboard.
2. User navigates to MEB, incoming, outgoing, payout, calendar, or reports.
3. Data is listed through AJAX-backed DataTables.
4. Admin users can import, validate, bulk edit, or export records.

### Messaging flow

1. User composes a message from the contact page.
2. The message is stored in `contact_messages`.
3. Recipients are resolved from user role and selection.
4. Email is sent through SMTP.
5. Inbox badges and message-read state update through polling.

### Role change flow

1. Admin changes a user role or deactivates the user.
2. Role-change state is stored and checked during page loads and polling.
3. The user sees a warning modal.
4. The app signs the user out so the new role takes effect cleanly.

## 11. Development Notes

- The app is mostly procedural rather than MVC.
- Business logic is gradually being extracted into helper files.
- Shared request protection is already standardized, so new POST endpoints should reuse:
  - `security_require_method()`
  - `security_require_csrf_token()`
- UI dependencies are a mix of local vendored assets and CDN-style mirrored folders checked into the repo.
- The repository currently has active feature work in progress, so documentation and changes should be added carefully without reverting unrelated work.

## 12. Maintenance Recommendations

If you plan to continue developing KODUS, these areas are worth keeping in mind:

- centralize remaining direct SQL and duplicated page logic into helpers or service-style modules
- review commented-out authorization checks in utility modules
- standardize footer/version rendering so all public pages use the same app metadata
- replace any remaining hard-coded year options in [`select_year.php`](C:\laragon\www\kodus\select_year.php) with a dynamic strategy
- document the expected Excel templates for MEB, targets, crossmatch, and deduplication in separate operator guides if end users need them
- add automated tests around authentication, messaging, and import endpoints if the app will continue to grow

## 13. Quick Reference

### Main entry URLs

- `/kodus/` -> login
- `/kodus/home` -> dashboard
- `/kodus/pages/data-tracking-meb` -> beneficiary masterlist
- `/kodus/pages/data-tracking-in` -> incoming tracking
- `/kodus/pages/data-tracking-out` -> outgoing tracking
- `/kodus/pages/payout` -> payout tracking
- `/kodus/pages/calendar` -> calendar
- `/kodus/implementation-status/program-targets` -> baseline targets
- `/kodus/implementation-status/program-activities` -> program activities
- `/kodus/admin/users_management` -> admin users management
- `/kodus/inbox/` -> inbox
- `/kodus/contact` -> message composer
- `/kodus/crossmatch/` -> crossmatch tool
- `/kodus/deduplication/` -> deduplication tool

### Most important shared files

- [`header.php`](C:\laragon\www\kodus\header.php)
- [`config.php`](C:\laragon\www\kodus\config.php)
- [`security.php`](C:\laragon\www\kodus\security.php)
- [`auth_helpers.php`](C:\laragon\www\kodus\auth_helpers.php)
- [`notification_helpers.php`](C:\laragon\www\kodus\notification_helpers.php)
- [`sidenav.php`](C:\laragon\www\kodus\sidenav.php)

---

This document reflects the current repository state inspected on **March 25, 2026** and is based on the implemented code paths present in the workspace.
