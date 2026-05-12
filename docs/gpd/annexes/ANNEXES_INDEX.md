# KODUS GPD Annexes Index

**System:** KliMalasakit Operational Data Unified System (KODUS)  
**Office/Program:** DSWD Field Office Caraga  
**Prepared for:** Governance, Privacy, Documentation, and Knowledge Management submission  
**Document status:** Draft for document-owner validation  
**Last repository review:** 2026-05-12

## Document Control

| Field | Entry |
| --- | --- |
| Prepared by | <span style="color:red">[MANUAL INPUT REQUIRED: Name/office of preparer]</span> |
| Reviewed by | <span style="color:red">[MANUAL INPUT REQUIRED: KM/GPD reviewer]</span> |
| Approved by | <span style="color:red">[MANUAL INPUT REQUIRED: Approving official/signatory]</span> |
| Coverage period | Repository and documentation review through 2026-05-12; operational reporting period to be confirmed in the final signed transmittal |
| Source system version | KODUS v2.4.6, codename Control Center, release date 2026-03-30 |
| Data protection note | All examples in these annexes are sanitized. No production beneficiary names, IDs, credentials, tokens, secret keys, or raw beneficiary records are included. |

## Annex Register

| Annex | File | Purpose | Status | Required manual inputs |
| --- | --- | --- | --- | --- |
| Annex A | `ANNEX_A_OPERATIONAL_WORKFLOW_SCREENSHOTS.md` | Provides screenshot inventory, capture instructions, captions, and purpose notes for major KODUS operational screens. | Draft; screenshot placeholders inserted | Login/SSO, dashboard, MEB, MEBIS, validation, name matching, deduplication, reports, inbox/messenger, notifications, settings/profile, audit/admin screenshots |
| Annex B | `ANNEX_B_MEB_VALIDATION_WORKFLOW.md` | Documents the end-to-end MEB validation workflow, controls, background job behavior, roles, and Mermaid diagram source. | Draft; workflow based on repository review | Document-owner confirmation of SOP, validation sign-off rules, external 4Ps/NHTS-PR handling, and final approval date |
| Annex C | `ANNEX_C_DEDUPLICATION_CROSSMATCH_OUTPUTS.md` | Provides sanitized sample structures and interpretation notes for deduplication, name matching, and crossmatch outputs. | Draft; samples are synthetic | Owner validation of thresholds, acceptance/rejection SOP, and preferred export sample format |
| Annex D | `ANNEX_D_SAMPLE_DISAGGREGATED_REPORTS.md` | Provides sanitized examples of disaggregated reports by sex, age, disability, location, program/activity, and implementation status. | Draft; synthetic examples only | Actual approved report extracts, reporting period, official totals, and signatory block |
| Annex E | `ANNEX_E_AUDIT_ACCOUNTABILITY_RECORDS.md` | Documents audit log coverage, session/account actions, data changes, import/export actions, deletion/restoration records, 2FA/email logs, and accountability controls. | Draft; samples are sanitized | Audit-retention policy, formal review schedule, accountable office, and sample redacted export if required |
| Annex F | `ANNEX_F_DEPLOYMENT_SUSTAINABILITY_DOCUMENTATION.md` | Summarizes deployment, hosting, backups, version control, documentation, background jobs, Socket.IO, PWA/offline status, and continuity measures. | Draft; based on repository docs | Host environment details, backup/restore evidence, continuity plan, maintenance roster, and PWA/offline decision |

## Repository Sources Reviewed

The annexes are aligned with the current KODUS source tree and documentation, including:

| Area | Repository references |
| --- | --- |
| System overview | `README.md`, `docs/KODUS_DOCUMENTATION.md` |
| Deployment | `docs/LINUX_NGINX_DEPLOYMENT.md`, `docs/PRODUCTION_PACKAGE_GUIDE.md`, `deployment/nginx/crg-kodus.conf.example` |
| Privacy/data flow | `docs/PIA_DATA_FLOW_NOTES.md`, `docs/PIA_FINAL_DRAFT.md`, `docs/PIA_SCOPE_AUDIT.md` |
| MEB import and validation | `pages/data-tracking-meb.php`, `pages/meb_import_helpers.php`, `pages/meb_import_worker.php`, `pages/data-tracking-meb-validation.php`, `pages/fetch_data_validation_admin.php`, `pages/update_validation_status.php` |
| MEBIS utilities | `mebis-consolidator/`, `mebis-lgu-template/` |
| Deduplication/crossmatch | `deduplication/`, `crossmatch/` |
| Reports and exports | `pages/export_meb.php`, `pages/export_meb_validation.php`, `implementation-status/fetch-program-summary.php`, `pages/summary/`, `pages/fund-monitoring-export.php`, `pages/payout_export.php` |
| Audit/accountability | `audit_helpers.php`, `admin/audit_logs.php`, `meb_change_history_helpers.php`, `mail_config.php`, `app_notification_helpers.php` |
| Realtime and workers | `docs/LIVE_REFRESH_SOCKETIO_MIGRATION.md`, `dist/js/kodus-live-refresh.js`, `socket/README.md`, `socket_helpers.php` |
| Existing screenshots | `docs/manual_screens/` |

## Final Owner-Validation Checklist

- Annex A screenshot placeholders were replaced with repository screenshot references or explicit owner-validation notes; final submission should use only approved sanitized captures.
- Sanitized sample tables should be compared with the official report formats used for submission before signing.
- MEB validation statuses and escalation rules should be confirmed by the KODUS data owner for the reporting period.
- Authorized roles for MEB import/export, validation, MEBIS utilities, deduplication, crossmatch, and full exports should be confirmed through the user-management record.
- Official dates, names, office titles, and signatures should be applied on the final controlled copy.
- Retention periods for beneficiary data, generated outputs, upload folders, audit logs, mail logs, and job results should follow the approved DSWD records-management and privacy schedule.
- Backup/restore evidence and continuity procedures should be attached or filed by the hosting/system owner.
- PWA/offline-draft capability is documented as not implemented in the reviewed codebase unless the system owner issues a separate implementation requirement.
