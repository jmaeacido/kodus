# KODUS PIA Owner Answers Draft

This document provides draft written answers for the main non-code questions identified during the technical audit. These answers are intentionally written in a practical, formal-PIA style so the system owner can review, edit, and adopt them as needed.

Important note:
- The technical appendix confirms what exists in code and in the live database.
- The statements below are operational-position drafts and should be reviewed by the actual system owner before use in a final submission.

## 1. What is the retention period for `meb`, `meb_change_history`, `audit_logs`, `mail_logs`, uploads, and generated utility outputs?
Draft answer:
KODUS operational data should be retained only for as long as needed to support program implementation, validation, reporting, audit, and security monitoring requirements. The recommended operational position is:
- Beneficiary master-list records should be retained for the active program cycle and any required post-implementation validation/reporting period.
- Beneficiary change-history records should be retained only as long as needed for accountability, correction review, and audit support.
- Audit logs and mail logs should be retained for a defined security and administrative review window, then archived or disposed of according to agency policy.
- Uploads and generated outputs should not be retained indefinitely. They should be reviewed periodically and removed once no longer needed for operational, audit, or reporting purposes.

Owner action:
- Replace this with the exact approved retention schedule or records-management rule used by the organization.

## 2. Are `pages/uploads/`, `inbox/uploads/`, and `storage/aatracker/` protected from direct public access in production?
Draft answer:
In production, uploaded files are intended to be accessible only to authorized users and administrators based on operational need. The production environment should ensure that upload directories are not openly browsable and are protected by application-level access control and server configuration.

Owner action:
- Confirm the actual production web-server rule or hosting control used to block public directory listing and direct unauthenticated access.
- If any directory remains directly web-accessible, document compensating controls and remediation plans.

## 3. What backup process exists for the database and upload directories, and are backups encrypted?
Draft answer:
The KODUS production environment should include regular backups of the application database and all required upload/output directories needed for continuity of service. Backups should be restricted to authorized personnel and protected in transit and at rest in accordance with agency infrastructure policy.

Owner action:
- State the real backup frequency, storage location, retention period, and encryption practice.
- Confirm whether uploads, generated utility outputs, and logs are included in backup scope.

## 4. Which live schema is the intended source of truth for inbox, events, and implementation status: legacy packed columns, normalized tables, or both during transition?
Draft answer:
The intended direction of KODUS is toward normalized data structures for maintainability and clearer relationship handling. However, the live system currently contains both legacy and normalized structures in some modules. For PIA purposes, both must be treated as in-scope until the transition is formally completed and legacy storage is retired.

Owner action:
- Confirm whether the organization recognizes the normalized tables as the official source of truth already, or whether the application is still in a controlled transition period.

## 5. Are crossmatch and deduplication supposed to be admin-only in production?
Draft answer:
Crossmatch and deduplication utilities handle uploaded beneficiary datasets and derived matching results. Because they create additional data repositories and expose high-risk processing of beneficiary data, the recommended operational position is that these utilities should be limited to authorized administrative or specifically designated data-management personnel only.

Owner action:
- Confirm the intended authorized role set for these utilities in production.
- If access extends beyond admins, document why that access is necessary and what supervision or approval controls apply.

## 6. Who is authorized to export full MEB data, and is export activity reviewed or logged outside the app?
Draft answer:
Full beneficiary exports should be restricted to personnel with a legitimate operational, validation, reporting, or supervisory need. Export use should follow internal approval and accountability rules, and exported data should be handled as controlled information outside the application environment.

Owner action:
- Identify which roles are actually authorized to export full beneficiary data.
- State whether export actions are reviewed, approved, logged, or monitored outside the application.

## 7. Are uploaded documents ever expected to contain beneficiary supporting records or IDs?
Draft answer:
Operational uploads may contain program, routing, or supporting documents connected with implementation activities. If any upload repository contains documents that include beneficiary names, IDs, or other supporting records, those repositories should be treated as part of the beneficiary-data processing boundary and handled with the same level of protection as core records.

Owner action:
- Confirm whether uploaded files routinely contain beneficiary supporting documents, IDs, proofs, certifications, or similar materials.

## 8. What external parties receive exported files, emailed attachments, or Google Drive-linked evidence?
Draft answer:
Any external sharing of KODUS-derived data should occur only where necessary for official program administration, coordination, reporting, or validation, and only through approved channels. External recipients should receive the minimum data necessary for the specific purpose.

Owner action:
- Identify actual recipient organizations, units, or partner entities.
- Clarify whether sharing occurs by email, file transfer, shared drives, or printed reports.

## 9. Is `id_number` / `contact_number` from SSO actively populated in production, and what is their business purpose?
Draft answer:
SSO-linked identity fields should only be populated if they are required to support identity matching, user-account administration, or directory synchronization. If these fields are not used operationally, they should be reviewed for minimization and possible deprecation.

Owner action:
- Confirm whether these fields are populated in production.
- State the actual business purpose and whether their collection remains necessary.

## 10. What formal access review and deactivation cadence is followed for `admin`, `editor`, `aa`, and `user` accounts?
Draft answer:
KODUS access should be granted according to role and operational need, with privileged roles subject to periodic review. Accounts that no longer require access should be deactivated promptly, and access changes should be documented through administrative procedures.

Owner action:
- State the actual review cadence, approving authority, and deactivation timeline used by the organization.
- Confirm whether privileged accounts are reviewed separately from ordinary user accounts.

## 11. Recommended Owner Review Statement
Draft answer:
The KODUS system owner has reviewed the technical evidence appendix and confirms that the formal PIA will use that appendix as the technical basis for describing data elements, modules, storage locations, and implemented controls. The owner will supplement the PIA with approved operational statements on retention, access governance, backup handling, export control, and external sharing practices.
