# Annex F - Deployment and Sustainability Documentation

**Document status:** Draft for host/system-owner validation  
**System:** KliMalasakit Operational Data Unified System (KODUS)  
**Scope:** Deployment setup, hosting environment, backup/restore approach, version control, documentation set, maintenance responsibilities, background jobs, Socket.IO/realtime components, PWA/offline-draft status, and continuity measures  
**Privacy note:** This annex intentionally excludes credentials, tokens, production hostnames, private IPs, secret keys, database passwords, SMTP credentials, SSO secrets, and Socket.IO bearer tokens.

## F.1 Purpose

This annex summarizes the deployment and sustainability arrangements for KODUS as reflected in the repository documentation and source code. It is intended to support GPD/KM submission while leaving production-specific details for the authorized host or system owner.

## F.2 Current Application Profile

| Item | Description |
| --- | --- |
| Application name | KliMalasakit Operational Data Unified System (KODUS) |
| Current repository-documented version | v2.4.6, codename Control Center |
| Release date in metadata | 2026-03-30 |
| Application type | Server-rendered PHP/MySQL internal operations platform |
| Primary operational areas | MEB processing, validation, RRP-CFTW / Project LAWA and BINHI implementation monitoring, deduplication, crossmatch, payout/fund monitoring, messaging, notifications, reports, and audit/admin controls |
| Main technology stack | PHP with `mysqli`, MySQL/MariaDB, AdminLTE 3, Bootstrap, jQuery, DataTables, Chart.js, SweetAlert2, PhpSpreadsheet, PHPMailer, Dotenv, Google2FA, optional Socket.IO bridge |

## F.3 Deployment Setup

The maintained deployment guidance identifies Linux, Nginx, PHP-FPM, MySQL/MariaDB, Composer, PHP CLI for workers, SMTP access, and optional Socket.IO bridge service as the expected stack.

Repository references:

- `docs/LINUX_NGINX_DEPLOYMENT.md`
- `docs/PRODUCTION_PACKAGE_GUIDE.md`
- `deployment/nginx/crg-kodus.conf.example`
- `.env.example`

**Table F-1. Deployment setup summary.**

| Component | Expected arrangement | Manual validation |
| --- | --- | --- |
| Web server | Nginx or Apache with rules denying access to secrets, docs, logs, uploads, and executable files in upload paths | Validate against deployed server configuration before signing |
| PHP runtime | PHP-FPM for web requests and PHP CLI for background workers | Validate installed PHP version/extensions against deployment guide |
| Database | MySQL or MariaDB | Validate engine/version with host owner; do not include credentials |
| Dependencies | Composer install with optimized autoloading | Confirm `composer install --no-dev --optimize-autoloader` or equivalent package process |
| Environment file | `.env` created on server only; not committed or exposed | Confirm server-only storage and restricted permissions |
| Mail service | SMTP configured through server environment | Confirm operational mail relay without exposing SMTP secrets |
| SSO | Optional Caraga Connect SSO configuration | Enable only when approved client configuration is issued |
| Realtime | Optional Socket.IO bridge, configured only through environment values | Confirm enabled bridge or fallback-polling status for the deployment |

## F.4 Hosting Environment

The approved hosting summary should identify only non-secret deployment facts: environment type, operating system family, web server family, PHP runtime, database engine family, and accountable host office/vendor. Passwords, tokens, public attack-surface details, private IPs, and network diagrams must remain outside the annex.

## F.5 Runtime Directories and Data Protection

The deployment documentation identifies writable runtime paths for uploads, outputs, and background jobs. These directories must be writable only where required and blocked from script execution.

| Runtime area | Example paths |
| --- | --- |
| Crossmatch uploads/results | `crossmatch/uploads/`, `crossmatch/` job/result tables |
| Deduplication uploads/results/logs | `deduplication/uploads/`, `deduplication/logs/` |
| Inbox attachments | `inbox/uploads/` |
| Document uploads | `pages/uploads/` |
| MEBIS outputs/jobs | `mebis-consolidator/outputs/`, `mebis-lgu-template/jobs/`, `mebis-lgu-template/outputs/` |
| Profile exports | `pages/profile_exports/` and profile export job/output helpers |

**Control notes:**

- Generated outputs may contain sensitive beneficiary or operational data.
- Upload/output folders must be included in backup scope only after retention and lawful purpose are confirmed.
- Server rules must prevent executable upload abuse.

## F.6 Backup and Restore Approach

The repository states that the actual production backup process must be supplied by the host/system owner.

The official backup process should cover the database, required upload/output directories, deployment package reference, and non-secret configuration inventory. Backup frequency, storage category, encryption approach, retention period, restoration-test schedule, and responsible personnel should be maintained by the host/system owner in the controlled operations record.

**Minimum backup scope for validation:**

- Database tables for users, MEB, targets, activities, payout/fund monitoring, documents, audit logs, notifications, messages, calendar, and job metadata.
- Required upload/output directories after sensitivity and retention review.
- Environment configuration inventory without exposing actual secret values.
- Deployment package/version reference.

## F.7 Version Control and Release Management

KODUS source should be maintained in version control with reviewed deployment packages. The production package guide recommends deploying reviewed application code, bundled assets, Composer dependencies, and required runtime scaffolding while excluding secrets, dumps, keys, local diagnostics, scratch files, test spreadsheets, and operational datasets.

**Table F-2. Release evidence checklist.**

| Evidence | Status |
| --- | --- |
| Reviewed source version/tag/commit | KODUS v2.4.6, codename Control Center; repository review date 2026-05-12 |
| Deployment date | To be recorded in deployment log for the controlled release |
| Deployment approver | <span style="color:red">[MANUAL INPUT REQUIRED: official deployment approver/signatory]</span> |
| Smoke test result | Required checks: login, dashboard load, MEB import/validation access, exports, notifications, audit page, background worker status |
| Rollback plan | Retain previous reviewed package, database backup, and restore procedure before release activation |

## F.8 Documentation Set

Repository documentation available for sustainability and turnover includes:

| Document | Purpose |
| --- | --- |
| `README.md` | Current system overview, modules, scope, and configuration reminders |
| `docs/KODUS_DOCUMENTATION.md` | Full codebase-aligned documentation |
| `docs/LINUX_NGINX_DEPLOYMENT.md` | Linux/Nginx deployment guidance |
| `docs/PRODUCTION_PACKAGE_GUIDE.md` | Production package inclusion/exclusion rules |
| `docs/KODUS_DATA_DICTIONARY.md` | Data dictionary reference |
| `docs/ERD_SCHEMA_SUMMARY.md`, `docs/KODUS_ERD.*` | Database/entity relationship references |
| `docs/PIA_*` | Privacy impact/data-flow evidence |
| `docs/LIVE_REFRESH_SOCKETIO_MIGRATION.md` | Realtime/live refresh behavior and Socket.IO migration notes |
| `docs/KODUS_User_Manual*`, `docs/manual_screens/` | User documentation and screenshot evidence |

## F.9 Maintenance Responsibilities

**Table F-3. Suggested maintenance responsibility matrix.**

| Area | Responsible party | Frequency | Evidence |
| --- | --- | --- | --- |
| User and role review | KODUS system owner / system administrator | At least per reporting cycle and after personnel changes | User list review record |
| Audit log review | System administrator with audit/KM/GPD reviewer | At least per reporting cycle or incident review | Audit review sheet |
| Backup verification | Host/system owner | Per approved backup and restoration-test schedule | Backup and restore test record |
| Dependency/security review | Technical maintainer / system owner | Per release or security advisory | Patch/release notes |
| Worker/job monitoring | System administrator / technical maintainer | Routine operations review and after failed jobs | Job status and error review |
| Documentation update | KODUS documentation owner / KM reviewer | Per release or policy cycle | Updated manuals/annexes |

## F.10 Background Jobs and Workers

KODUS uses PHP CLI/background workers for long-running or generated-output tasks.

| Worker area | Representative files | Purpose |
| --- | --- | --- |
| MEB import | `pages/meb_import_worker.php`, `pages/meb_import_helpers.php` | Background import of MEB workbooks with progress/status updates |
| Profile export | `pages/profile_export_worker.php`, `pages/profile_export_job_helpers.php` | Background generation of profile exports |
| Deduplication | `deduplication/worker.php`, `deduplication/worker_v2.php` | Duplicate detection and result storage |
| Crossmatch | `crossmatch/run_job.php` | Match scoring and result storage |
| MEBIS LGU template | `mebis-lgu-template/worker.php` | Background generation of import-ready templates |

**Control note:** PHP CLI path and worker permissions must be verified during deployment. Worker folders and output locations must not expose executable files or sensitive output publicly.

## F.11 Socket.IO and Realtime Components

KODUS centralizes realtime browser wiring in `dist/js/kodus-live-refresh.js` and supports an optional Socket.IO bridge. Channels include mailbox, notifications, incoming, outgoing, MEB, and MEB validation events, with fallback polling retained for selected job-progress and safety checks.

Repository references:

- `docs/LIVE_REFRESH_SOCKETIO_MIGRATION.md`
- `socket/README.md`
- `socket/SSO_CONFIG.md`
- `socket_helpers.php`

**Security note:** Socket.IO server URLs, broadcast URLs, client script URLs, and bearer tokens must be configured through environment values and must not appear in annexes.

## F.12 PWA / Offline-Draft Sustainability Status

Repository review found no first-class PWA/offline implementation in the current source tree. No app manifest or service-worker registration was identified. The word “offline” appears mainly in inbox/messenger presence labels and Socket.IO client internals. The inbox UI preserves draft text during thread refresh in the current browser session, but this is not equivalent to a formal offline-draft PWA capability.

Formal PWA/offline-draft capability is not implemented in the reviewed source tree. If the system owner later requires offline operation, it should be handled as a separate approved implementation plan covering service worker behavior, manifest, offline queueing, conflict handling, storage encryption, and privacy review.

## F.13 Continuity Measures

Minimum continuity measures for KODUS should include:

- Documented backup and restore process with periodic restoration testing.
- Named technical and program owners.
- Release and rollback procedure.
- Worker/job monitoring and failure notification review.
- Access review for admin/editor/AA/user roles.
- Audit log review and retention schedule.
- Server hardening for upload/output directories and secret files.
- Updated user manuals, screenshots, and annexes after material workflow changes.

## F.14 Owner Validation Notes

- Approved host environment details should be summarized using non-secret facts only and filed with the accountable office/vendor record.
- Backup and restoration evidence should be attached to the controlled operations record or referenced by evidence number.
- Release/version evidence should cite the reviewed source version, package, tag, commit, or deployment log entry used for the deployed build.
- Maintenance roster and review schedules should follow Table F-3 unless superseded by a signed operations plan.
- Socket.IO deployment status should be recorded as enabled with environment-based configuration or as fallback-polling only.
- Formal PWA/offline-draft capability is documented as not implemented in the reviewed codebase unless a separate approved implementation plan is issued.
