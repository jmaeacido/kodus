# Annex A - KODUS Operational Workflow Screenshots

**Document status:** Draft for screenshot capture and owner validation  
**System:** KliMalasakit Operational Data Unified System (KODUS)  
**Scope:** Major user-facing screens and administrative controls used for DSWD Caraga program operations  
**Privacy note:** Screenshots must be captured using test accounts or sanitized/staging data only. Do not show production beneficiary names, IDs, contact details, credentials, tokens, live upload filenames containing personal data, or confidential operational details.

## A.1 Screenshot Capture Standards

All screenshots shall be clear, current, and consistent with the approved production or staging build. Where real screenshots are not yet available, the placeholder below shall remain until the document owner inserts the approved image.

**Caption format:**  
`Figure A-[number]. [Screen name] - [Short purpose/use note].`

**Placeholder format:**  
<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot for Screen Name. Capture path: module/page. Use sanitized or test data only.]</span>

## A.2 Screenshot Inventory

| Figure | Screen | Repository route or module | Required capture note | Status |
| --- | --- | --- | --- | --- |
| A-1 | Login / username-password access | `login.php`, `ajax_login.php` | Show login form without typed password. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-2 | SSO / Caraga Connect access | `login-sso/index.php`, `login-sso/callback.php` | Show only non-secret SSO entry point. Do not show OAuth client values. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-3 | Dashboard / home | `home.php` | Show role-appropriate home dashboard with sanitized counts. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-4 | Implementation Status - Program Targets | `implementation-status/program-targets.php` | Show baseline target encoding or target list with test values. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-5 | Implementation Status - Program Activities | `implementation-status/program-activities.php` | Show activity encoding/monitoring screen with sanitized activity details. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-6 | Implementation Status - LAWA/BINHI Summary | `implementation-status/lawa-summary.php`, `implementation-status/binhi-summary.php` | Show summary page with aggregate values only. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-7 | MEBIS Consolidator | `mebis-consolidator/index.php` | Show upload/history screen and output summary, not raw records. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-8 | MEBIS LGU Template Generator | `mebis-lgu-template/index.php` | Show final validated MEB upload and generation status with test files. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-9 | MEB Import / Master List | `pages/data-tracking-meb.php` | Show imported MEB list using blurred/sanitized rows or test data. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-10 | MEB Validation | `pages/data-tracking-meb-validation.php` | Show target versus imported actual counts and validation badges. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-11 | Name Matching / MEBIS Output | `mebis-consolidator/`, `mebis-lgu-template/` | Show name-matching output summary without individual identities. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-12 | Deduplication Upload / Recent Jobs | `deduplication/index.php` | Show upload form, rule/threshold fields, and recent jobs table. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-13 | Deduplication Results | `deduplication/results.php` | Show duplicate group layout using synthetic rows. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-14 | Crossmatch Upload / Start | `crossmatch/index.php`, `crossmatch/start.php` | Show upload/start options with test files only. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-15 | Crossmatch Results | `crossmatch/results.php` | Show uploaded record, top candidates, scores, and accept checkbox using synthetic rows. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-16 | Reports / Export Screens | `pages/export_meb.php`, `pages/export_meb_validation.php`, `pages/summary/`, `pages/fund-monitoring-export.php` | Show report/export entry points and aggregate output only. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-17 | Inbox / Messenger | `inbox/index.php`, `messenger/index.php`, `contact.php` | Show message list and thread using test conversations only. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-18 | Notifications | `notifications/index.php`, `app_notification_helpers.php` | Show notification feed with non-sensitive titles. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-19 | Settings / Profile | `settings.php`, profile helpers | Show profile settings without email, phone, recovery codes, or secret QR data. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-20 | Audit Logs | `admin/audit_logs.php` | Show filtered log table using sanitized users/actions. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-21 | Admin User Management | `admin/users_management.php` | Show user list with masked names/emails and role/status controls. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |
| A-22 | Admin Maintenance / Password Security | `admin/maintenance.php`, `admin/password_security.php` | Show administrative control page without sensitive values. | <span style="color:red">[MANUAL INPUT REQUIRED]</span> |

## A.3 Screen Notes and Captions

### Figure A-1. Login / Username-Password Access

![Figure A-01](screenshots/ANNEX_A_FIGURE_A-01_LOGIN.png)

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot. Capture the login screen before entering credentials.]</span>

**Purpose/use:** Provides authenticated entry to KODUS using standard username/password access. The login flow is supported by session regeneration, password policy controls, remember-me token handling, and optional two-factor verification.

### Figure A-2. SSO / Caraga Connect Access

![Figure A-02](screenshots/ANNEX_A_FIGURE_A-02_DASHBOARD.png)

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot. Capture only the public SSO entry or redirect prompt; do not show client secrets, authorization codes, tokens, or callback parameters.]</span>

**Purpose/use:** Supports optional Caraga Connect SSO integration for authorized users where configured by the system owner.

### Figure A-3. Dashboard / Home

![Figure A-03](screenshots/ANNEX_A_FIGURE_A-03_PROGRAM_TARGETS.png)

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot. Use a test account and sanitized counts.]</span>

**Purpose/use:** Serves as the role-based landing page for users after login and provides navigation to operational modules such as MEB, implementation monitoring, reports, inbox, and administrative functions.

### Figure A-4. Implementation Status - Program Targets

![Figure A-04](screenshots/ANNEX_A_FIGURE_A-04_PROGRAM_ACTIVITIES.png)

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot of target setup or target list with test values.]</span>

**Purpose/use:** Records fiscal-year baseline targets for Project LAWA and BINHI, including province, municipality, barangay, target partner-beneficiaries, project classification, and project target entries.

### Figure A-5. Implementation Status - Program Activities

![Figure A-05](screenshots/ANNEX_A_FIGURE_A-05_LAWA_BINHI_SUMMARY.png)

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot using sanitized program/activity entries.]</span>

**Purpose/use:** Captures activity timelines, actual project/accomplishment entries, project classifications, location information, and evidence links for implementation monitoring.

### Figure A-6. Implementation Status - LAWA/BINHI Summary

![Figure A-06](screenshots/ANNEX_A_FIGURE_A-06_MEB_IMPORT.png)

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot showing aggregate summary output only.]</span>

**Purpose/use:** Presents target-versus-actual summaries for LAWA and BINHI implementation, supporting management review, planning, and reporting.

### Figure A-7. MEBIS Consolidator

![Figure A-07](screenshots/ANNEX_A_FIGURE_A-07_MEB_VALIDATION.png)

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot of the MEBIS consolidator upload/history screen. Use test files or blurred filenames.]</span>

**Purpose/use:** Consolidates MEBIS workbooks and summarizes outputs by province and municipality/city. The module is intended to support preparation of name-matching and validation materials without exposing raw personal data in documentation.

### Figure A-8. MEBIS LGU Template Generator

![Figure A-08](screenshots/ANNEX_A_FIGURE_A-08_MEBIS_CONSOLIDATOR.png)

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot of final validated MEB upload and background generation status.]</span>

**Purpose/use:** Converts approved MEBIS/MEB workbooks into import-ready templates and records background generation status for user follow-up.

### Figure A-9. MEB Import / Master List

![Figure A-09](screenshots/ANNEX_A_FIGURE_A-09_DEDUPLICATION.png)

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot with all beneficiary names and granular identifiers blurred or replaced with test records.]</span>

**Purpose/use:** Displays imported Master List of Eligible Beneficiaries records, batch information, and export functions. Access and editing are role- and area-controlled.

### Figure A-10. MEB Validation

![Figure A-10](screenshots/ANNEX_A_FIGURE_A-10_CROSSMATCH.png)

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot of target-versus-actual counts by location.]</span>

**Purpose/use:** Compares imported MEB actual counts with baseline target partner-beneficiaries for the selected fiscal year. Statuses include No Target, No Import, Partial, Validated, Over Target, and Unplanned Import.

### Figure A-11. Name Matching / MEBIS Output

![Figure A-11](screenshots/ANNEX_A_FIGURE_A-11_REPORTS.png)

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot of sanitized output summary or generated template history.]</span>

**Purpose/use:** Documents the system-supported preparation of MEBIS/name-matching outputs while preserving confidentiality of individual beneficiary identities.

### Figure A-12. Deduplication Upload / Recent Jobs

![Figure A-12](screenshots/ANNEX_A_FIGURE_A-12_INBOX_MESSENGER.png)

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot using test upload filename and synthetic data.]</span>

**Purpose/use:** Allows authorized users to upload a beneficiary dataset, select duplicate-detection settings, and monitor recently processed jobs.

### Figure A-13. Deduplication Results

![Figure A-13](screenshots/ANNEX_A_FIGURE_A-13_NOTIFICATIONS.png)

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot using synthetic duplicate groups only.]</span>

**Purpose/use:** Displays grouped possible duplicates and similarity percentages for review, export, and exception handling.

### Figure A-14. Crossmatch Upload / Start

![Figure A-14](screenshots/ANNEX_A_FIGURE_A-14_SETTINGS_PROFILE.png)

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot using test files and no production dataset names.]</span>

**Purpose/use:** Starts crossmatching of uploaded beneficiary records against candidate records or the MEB dataset, subject to configured thresholds and authorized access.

### Figure A-15. Crossmatch Results

![Figure A-15](screenshots/ANNEX_A_FIGURE_A-15_AUDIT_LOGS.png)

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot using synthetic uploaded records and candidate matches only.]</span>

**Purpose/use:** Shows top candidate matches, score components, and review/accept controls for possible matches.

### Figure A-16. Reports / Export Screens

![Figure A-16](screenshots/ANNEX_A_FIGURE_A-16_USER_MANAGEMENT.png)

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot of report/export menus or aggregate report pages.]</span>

**Purpose/use:** Supports production of MEB exports, validation reports, beneficiary profiles, implementation summaries, payout exports, and fund monitoring reports.

### Figure A-17. Inbox / Messenger

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot from a test conversation only.]</span>

**Purpose/use:** Supports staff coordination through inbox/messenger threads, replies, attachments, read state, reactions, group chats, and real-time notification updates.

### Figure A-18. Notifications

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot of notification list with synthetic entries.]</span>

**Purpose/use:** Displays application notifications generated by operational actions such as MEB imports, validation updates, messages, and background job results.

### Figure A-19. Settings / Profile

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot with all personal details masked. Do not show 2FA QR secrets or recovery codes.]</span>

**Purpose/use:** Provides account settings, profile information, and security controls such as two-factor setup/disablement where applicable.

### Figure A-20. Audit Logs

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot of sanitized audit log table.]</span>

**Purpose/use:** Provides admin-only review of recorded system actions, including user, action, details, IP address, and timestamp fields.

### Figure A-21. Admin User Management

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot with masked user identities.]</span>

**Purpose/use:** Enables authorized administrators to manage user roles, deactivation/restoration, password security, and 2FA reset controls.

### Figure A-22. Admin Maintenance / Password Security

<span style="color:red">[MANUAL INPUT REQUIRED: Insert screenshot of maintenance/password policy controls without sensitive values.]</span>

**Purpose/use:** Documents administrative safeguards for maintenance mode, password rules, and production readiness controls.

## A.4 Screenshot Quality Checklist

- Screenshot uses staging/test or sanitized production data.
- No password, token, secret, OAuth code, 2FA QR secret, recovery code, or SMTP/SSO configuration value is visible.
- No raw beneficiary names, IDs, birthdates, addresses, contact information, or unredacted uploaded document names are visible.
- The active role and fiscal year context are clear where relevant.
- The caption and purpose/use note are present below each figure.
- The document owner has validated that each screenshot reflects the current approved KODUS workflow.

