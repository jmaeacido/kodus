$ErrorActionPreference = 'Stop'

function Set-CellValue {
    param(
        $Sheet,
        $Cell,
        $Value
    )

    $cellText = [string]$Cell
    if ($cellText -notmatch '^([A-Z]+)(\d+)$') {
        throw "Unsupported cell reference: $cellText"
    }

    $columnLetters = $matches[1]
    $rowNumber = [int]$matches[2]
    $columnNumber = 0
    foreach ($char in $columnLetters.ToCharArray()) {
        $columnNumber = ($columnNumber * 26) + ([int][char]$char - [int][char]'A' + 1)
    }

    $targetCell = $Sheet.Cells.Item($rowNumber, $columnNumber)
    if ($null -eq $Value) {
        $targetCell.Value2 = $null
    }
    elseif ($Value -is [DateTime]) {
        $targetCell.Value2 = $Value.ToString('yyyy-MM-dd')
    }
    else {
        $targetCell.Value2 = [string]$Value
    }
}

function Clear-Range {
    param(
        $Sheet,
        $RangeAddress
    )

    $range = $Sheet.Range($RangeAddress)
    foreach ($cell in $range.Cells) {
        if ($cell.MergeCells) {
            $mergeArea = $cell.MergeArea
            $firstCell = $mergeArea.Cells.Item(1, 1)
            if ($cell.Address() -eq $firstCell.Address()) {
                $mergeArea.ClearContents() | Out-Null
            }
        }
        else {
            $cell.ClearContents() | Out-Null
        }
    }
}

$templatePath = 'D:\This PC\Windows (C)\Users\jmaeacido\Downloads\ePIRMA PIA.xlsx'
$outputPath = Join-Path $PSScriptRoot '..\docs\KODUS_PIA_WORKBOOK.xlsx'
$outputPath = [System.IO.Path]::GetFullPath($outputPath)

$today = Get-Date '2026-04-21'
$nextPiaDate = $today.AddYears(1)
$startDate = $today
$targetShort = $today.AddDays(30)
$targetMedium = $today.AddDays(90)
$targetLong = $today.AddDays(180)

$processName = 'KODUS'
$processOwner = 'KODUS System Owner / RRP Process Owner (to confirm)'
$copName = 'Compliance Officer for Privacy (to confirm)'
$regionalDirector = 'Regional Director (to confirm)'

$thresholdDescription = @'
KODUS is a PHP/MySQL web application used for account administration, beneficiary master-list management for the Risk Resiliency Program (RRP), implementation monitoring, document routing, internal messaging, calendar/event coordination, payouts/fund monitoring, and beneficiary-data utilities such as crossmatch, deduplication, and MEBIS output generation. The live system processes user account/security data, beneficiary identity and eligibility data, uploaded files, exports, notifications, logs, coordinates, and operational records across both legacy and normalized tables.
'@

$dpsRows = @(
    [ordered]@{
        Stakeholder = 'Authorized KODUS users (admin, editor, aa, user)'
        Collection = @'
Users register or are matched through SSO and provide names, username, email, password, optional profile photo, and account settings. Login, reset, remember-me, and 2FA flows also create security metadata such as tokens, timestamps, and recovery material.
'@
        Use = @'
Used for authentication, access control, password recovery, 2FA verification, notifications, auditability, and account administration. Security and account metadata should be retained only for operational and security review periods approved by the owner.
'@
        Storage = @'
Stored primarily in the users table, with supporting data in PHP sessions, audit_logs, mail_logs, and local avatar storage (dist/img/). Data is transmitted through authenticated web sessions and SMTP-configured email workflows.
'@
        Sharing = @'
Shared internally with administrators and authorized support personnel as required for account management and security operations. Certain notifications are sent through the configured SMTP provider; SSO fields may also be exchanged with the external identity provider.
'@
        Disposal = @'
Accounts should be deactivated when no longer needed. Logs, tokens, session data, and related security records should follow the approved retention and disposal schedule to be confirmed by the owner.
'@
    }
    [ordered]@{
        Stakeholder = 'Beneficiaries / Master List of Eligible Beneficiaries (MEB) subjects'
        Collection = @'
Authorized staff upload beneficiary spreadsheets and manually correct records. Collected fields include names, birth date, age, sex, civil status, province/municipality/barangay/purok, NHTS indicators, and sector/program flags including potentially sensitive classifications such as PWD and LGBTQIA.
'@
        Use = @'
Used for beneficiary master-list management, validation, reporting, implementation monitoring, sectoral summaries, and program administration under RRP. Full-record edits create before/after history snapshots for accountability.
'@
        Storage = @'
Stored mainly in meb and meb_change_history, and reused in reporting/export endpoints plus utility result tables such as crossmatch_results and deduplication_results. Data moves through browser sessions, spreadsheet imports, exports, and internal dashboards.
'@
        Sharing = @'
Shared internally through reports, validation screens, and exports. External disclosure is not fully defined in the repo and should be limited to approved program, reporting, or supervisory recipients using minimum-necessary data.
'@
        Disposal = @'
MEB records, change-history snapshots, and exported datasets should follow an approved records schedule covering active use, audit support, archiving if required, and secure disposal when no longer needed.
'@
    }
    [ordered]@{
        Stakeholder = 'Operational correspondents, invited guests, and document-routing participants'
        Collection = @'
Users compose inbox/contact messages, add recipients, upload attachments, create events with guest names/emails, and upload routing or action-tracker documents. Records may include message body text, recipient details, filenames, remarks, and timestamps.
'@
        Use = @'
Used for internal coordination, correspondence, event reminders, and document tracking. Some records also trigger email delivery and notification updates.
'@
        Storage = @'
Stored in contact_messages, contact_message_recipients, contact_replies, message_reads, events, event_guests, incoming, outgoing, aatracker, and local upload directories such as inbox/uploads/, pages/uploads/, and storage/aatracker/.
'@
        Sharing = @'
Shared with intended recipients inside the application and, where enabled, through outbound SMTP email. Uploaded files may also be forwarded within operational workflows.
'@
        Disposal = @'
Messages, invitations, and uploaded documents should be retained only for the relevant operational or records-management period, then deleted or archived according to approved policy.
'@
    }
    [ordered]@{
        Stakeholder = 'Implementation, payout, and utility data managers'
        Collection = @'
Editors and admins encode program target/activity details, project locations, coordinates, drive links, beneficiary counts, payout/fund-monitoring values, and uploaded utility datasets for crossmatch, deduplication, and MEBIS generation.
'@
        Use = @'
Used for implementation monitoring, summaries, maps, payout/fund tracking, duplicate detection, dataset comparison, and generation of utility outputs for RRP operations.
'@
        Storage = @'
Stored in project_lawa_binhi_targets, project_target_entries, program_activity_metadata, program_activity_actual_projects, breakdown, fund_monitoring_items, fund_monitoring_entries, crossmatch_jobs/results, deduplication_jobs/results, and output history tables.
'@
        Sharing = @'
Viewed internally through dashboards, maps, and exports. Coordinates and map views also involve external tile providers, while stored drive links may point to external evidence repositories.
'@
        Disposal = @'
Operational tables, uploaded utility files, result tables, and generated outputs should be periodically reviewed and disposed of under an approved retention and backup policy.
'@
    }
)

$pdiRows = @(
    [ordered]@{ Data='User profile data (username, email, first/middle/last name, suffix, position, area)'; Type='PI'; Source='Registration, settings updates, and SSO-linked provisioning'; Purpose='Account identity, access management, and user administration'; Legal='Operational necessity for KODUS administration; exact authority to confirm'; Location='users table; PHP session context'; Internal='Authorized users and administrators; admin has broader visibility'; PIPs='SMTP / hosting arrangements to confirm'; OtherPICs='None confirmed in repo'; Disclosure='Displayed in settings, navigation, inbox, and admin user management'; Protection='Password-protected sessions, RBAC, CSRF, audit logging'; Backup='Included in database backup scope (owner to confirm)' }
    [ordered]@{ Data='Password hash, reset token, remember token, reset expiry'; Type='Priv'; Source='Registration, login, reset, and password update flows'; Purpose='Authentication, session restoration, and password recovery'; Legal='Operational necessity for secure access; exact authority to confirm'; Location='users table'; Internal='Application security logic and authorized administrators only'; PIPs='None confirmed'; OtherPICs='None confirmed'; Disclosure='Not displayed as plaintext; used in auth flows only'; Protection='Hashing, session hardening, rate limiting, reset-token validation'; Backup='Likely included in DB backups; retention policy to confirm' }
    [ordered]@{ Data='2FA state, TOTP secret, recovery codes, temporary 2FA codes'; Type='Priv'; Source='2FA setup, verification, recovery, and admin reset flows'; Purpose='Multi-factor account protection and recovery'; Legal='Operational necessity for secure access; exact authority to confirm'; Location='users table'; Internal='Account owner and limited administrators managing 2FA support'; PIPs='None confirmed'; OtherPICs='None confirmed'; Disclosure='Used only during 2FA setup, verification, recovery, and admin reset'; Protection='Hashed recovery codes, secret storage, access checks, audit logging'; Backup='Likely included in DB backups; handling/retention to confirm' }
    [ordered]@{ Data='Profile photo filename / SSO avatar URL'; Type='PI'; Source='Profile update and SSO synchronization'; Purpose='User profile display and recognition inside the app'; Legal='Operational necessity for user account profile display'; Location='users table; dist/img/'; Internal='Authorized users and administrators'; PIPs='SSO provider to confirm'; OtherPICs='None confirmed'; Disclosure='Shown in UI components such as settings, side navigation, inbox'; Protection='Filename checks, authenticated access, local storage'; Backup='File backup scope to confirm' }
    [ordered]@{ Data='Audit log metadata (IP address, action, details, timestamp)'; Type='PI'; Source='State-changing requests and explicit audit logging'; Purpose='Accountability, security review, and incident reconstruction'; Legal='Security monitoring and legitimate operational need'; Location='audit_logs'; Internal='Administrators / authorized reviewers'; PIPs='Hosting/log infrastructure to confirm'; OtherPICs='None confirmed'; Disclosure='Admin audit log screens and operational review'; Protection='RBAC, app access checks, database controls'; Backup='Included in database backup scope (owner to confirm)' }
    [ordered]@{ Data='Beneficiary direct identifiers (last name, first name, middle name, suffix)'; Type='PI'; Source='MEB spreadsheet import and admin updates'; Purpose='Beneficiary master-list management and reporting'; Legal='RRP program administration; exact citation to confirm'; Location='meb'; Internal='Authorized operational users; admin has widest access'; PIPs='None confirmed'; OtherPICs='External recipients of exports to confirm'; Disclosure='MEB views, reports, exports, utilities'; Protection='Admin restrictions on import/edit/validation, audit logs, CSRF'; Backup='Included in database backup scope (owner to confirm)' }
    [ordered]@{ Data='Beneficiary demographic data (birth date, age, sex, civil status)'; Type='PI'; Source='MEB import and admin update flows'; Purpose='Validation, reporting, profile and sectoral summaries'; Legal='RRP program administration; exact citation to confirm'; Location='meb'; Internal='Authorized operational users'; PIPs='None confirmed'; OtherPICs='Export recipients to confirm'; Disclosure='MEB pages, profile exports, sex-disaggregated reporting'; Protection='Access checks, audit logs, controlled exports'; Backup='Included in database backup scope (owner to confirm)' }
    [ordered]@{ Data='Beneficiary location data (province, municipality/LGU, barangay, purok)'; Type='PI'; Source='MEB import and updates'; Purpose='Eligibility context, reporting, and geographic grouping'; Legal='RRP program administration; exact citation to confirm'; Location='meb'; Internal='Authorized operational users'; PIPs='Map/tile providers apply only when location data is visualized elsewhere'; OtherPICs='Export recipients to confirm'; Disclosure='MEB pages, summaries, exports, payout/implementation grouping'; Protection='Access checks, audit logs, controlled exports'; Backup='Included in database backup scope (owner to confirm)' }
    [ordered]@{ Data='Eligibility and poverty-related fields (nhts1, nhts2)'; Type='SPI'; Source='MEB import and updates'; Purpose='Eligibility-related review, reporting, and program administration'; Legal='RRP program administration; exact citation to confirm'; Location='meb'; Internal='Authorized operational users'; PIPs='None confirmed'; OtherPICs='Export recipients to confirm'; Disclosure='Dashboard, validation, reporting, and export outputs'; Protection='Admin write restrictions, access controls, audit logs'; Backup='Included in database backup scope (owner to confirm)' }
    [ordered]@{ Data='Sector/program flags (fourPs, PWD, LGBTQIA, FR, ybDs, etc.)'; Type='SPI'; Source='MEB import and updates'; Purpose='Sectoral classification, reporting, and program operations'; Legal='RRP program administration; exact citation to confirm'; Location='meb'; Internal='Authorized operational users'; PIPs='None confirmed'; OtherPICs='Export recipients to confirm'; Disclosure='Sectoral reports, profile exports, utility processing'; Protection='Access controls, admin write restrictions, audit logs'; Backup='Included in database backup scope (owner to confirm)' }
    [ordered]@{ Data='Before/after beneficiary snapshots and validation/edit reason'; Type='SPI'; Source='MEB edit and validation workflows'; Purpose='Traceability, correction review, and accountability'; Legal='Legitimate accountability and audit support'; Location='meb_change_history and meb validation fields'; Internal='Administrators / authorized reviewers'; PIPs='None confirmed'; OtherPICs='None confirmed'; Disclosure='Change review and validation management pages'; Protection='Admin-only edit flows, audit trail, restricted review pages'; Backup='Included in database backup scope (owner to confirm)' }
    [ordered]@{ Data='Inbox/contact message content, recipient details, reply content, attachment metadata'; Type='PI'; Source='Contact compose, inbox reply, and recipient synchronization'; Purpose='Internal correspondence, notifications, and coordination'; Legal='Operational necessity for internal communications'; Location='contact_messages, contact_message_recipients, contact_replies, message_reads, inbox/uploads/'; Internal='Thread participants and authorized admins/support where applicable'; PIPs='SMTP provider for email copies/notifications'; OtherPICs='Email recipients to confirm'; Disclosure='Inbox UI, contact threads, outbound email'; Protection='Access checks, attachment validation, audit/mail logs'; Backup='Database and file backup scope to confirm' }
    [ordered]@{ Data='Event guest names/emails and event metadata'; Type='PI'; Source='Calendar event create/update flows'; Purpose='Scheduling, invitation, and reminder sending'; Legal='Operational necessity for coordination'; Location='events, event_guests'; Internal='Event creators and authorized users'; PIPs='SMTP provider'; OtherPICs='Guest recipients'; Disclosure='Calendar UI and reminder email'; Protection='Authenticated access, app-level controls'; Backup='Included in database backup scope (owner to confirm)' }
    [ordered]@{ Data='Uploaded document metadata and stored files (incoming/outgoing/action tracker)'; Type='PI'; Source='Document-routing and tracker upload forms'; Purpose='Routing, reference, action tracking, and document management'; Legal='Operational necessity for program administration'; Location='incoming, outgoing, aatracker, pages/uploads/, storage/aatracker/'; Internal='Authorized operational roles'; PIPs='Hosting/storage arrangements to confirm'; OtherPICs='Recipients in routing workflows to confirm'; Disclosure='Tracking pages, forwarding, popups, operational review'; Protection='Filename/MIME checks, page-level access controls'; Backup='Database/file backup scope to confirm' }
    [ordered]@{ Data='Implementation/project location data, coordinates, land details, drive links'; Type='PI'; Source='Implementation-status target/activity forms'; Purpose='Project monitoring, mapping, and status reporting'; Legal='RRP implementation monitoring; exact citation to confirm'; Location='project_* and program_activity_* tables'; Internal='Editors, admins, and authorized operational users'; PIPs='OpenStreetMap/Esri tile services; external drive hosting where links are used'; OtherPICs='None confirmed'; Disclosure='Maps, records pages, summaries'; Protection='Role checks, authenticated access, audit logging'; Backup='Included in database backup scope (owner to confirm)' }
    [ordered]@{ Data='Crossmatch/dedup utility uploads, row data, candidate matches, exports'; Type='SPI'; Source='Utility upload and processing flows'; Purpose='Dataset comparison, duplicate detection, output generation'; Legal='Operational necessity for data quality management'; Location='crossmatch_* tables, deduplication_* tables, utility upload folders'; Internal='Authorized utility users (production role scope to confirm)'; PIPs='None confirmed'; OtherPICs='Recipients of generated outputs to confirm'; Disclosure='Utility result pages and exports'; Protection='Upload validation, authenticated access, local result storage'; Backup='Database/file backup scope to confirm' }
)

$riskRows = @(
    [ordered]@{ Impact='KODUS users and beneficiaries'; Consequence='Unauthorized access to user accounts could expose credentials, tokens, audit history, and linked beneficiary or operational data.'; Threat='Unauthorized access / account compromise'; Severity=4; Likelihood=3 }
    [ordered]@{ Impact='Beneficiaries'; Consequence='Disclosure of MEB exports could expose direct identifiers, demographic data, granular locations, and sensitive sector/eligibility flags outside the application.'; Threat='Uncontrolled export / disclosure'; Severity=4; Likelihood=4 }
    [ordered]@{ Impact='Beneficiaries'; Consequence='Full before/after change-history snapshots may retain sensitive beneficiary data longer than necessary and duplicate disclosure impact.'; Threat='Excessive retention / duplicate storage'; Severity=4; Likelihood=3 }
    [ordered]@{ Impact='Beneficiaries and staff'; Consequence='Utility modules for crossmatch and deduplication can create additional repositories of uploaded beneficiary data and result files that are harder to govern consistently.'; Threat='Secondary dataset proliferation'; Severity=4; Likelihood=3 }
    [ordered]@{ Impact='Users, correspondents, and document subjects'; Consequence='Publicly reachable or weakly protected upload directories could expose attachments, routed documents, or operational files.'; Threat='Improper file storage exposure'; Severity=4; Likelihood=3 }
    [ordered]@{ Impact='Users and invited guests'; Consequence='Email-based notifications, contact messages, and calendar reminders may disclose personal data to the wrong recipient or through insufficiently governed channels.'; Threat='Misdelivery / third-party disclosure'; Severity=3; Likelihood=3 }
    [ordered]@{ Impact='Beneficiaries and operational staff'; Consequence='Hybrid legacy and normalized schemas may cause inconsistent retention, incomplete deletion, or uncertainty about the authoritative source of records.'; Threat='Data governance / integrity gap'; Severity=3; Likelihood=3 }
    [ordered]@{ Impact='Beneficiaries'; Consequence='Project maps, coordinates, and external drive links may reveal location-related context or evidence references beyond the minimum necessary audience.'; Threat='Location privacy / external link exposure'; Severity=3; Likelihood=2 }
    [ordered]@{ Impact='Users'; Consequence='Audit logs and mail logs replicate personal and security-related metadata and may remain accessible beyond the necessary review period if retention is not defined.'; Threat='Log over-retention'; Severity=3; Likelihood=3 }
    [ordered]@{ Impact='Beneficiaries and staff'; Consequence='Broad administrative visibility across user management, MEB, exports, logs, and utilities increases the impact of privilege misuse or weak access review.'; Threat='Excessive privileged access'; Severity=4; Likelihood=3 }
)

$controlRows = @(
    [ordered]@{ Consequence='Unauthorized account compromise affecting user/admin and beneficiary data'; Measures='Keep password hashing, session regeneration, CSRF, rate limiting, and 2FA in place; add periodic privileged-account review and formal admin recertification.'; Type='Technical and organizational'; Standard='Least privilege and secure authentication practice'; Start=$today; End=$targetMedium; Severity=4; Likelihood=2 }
    [ordered]@{ Consequence='Full MEB exports can create portable copies of sensitive beneficiary data'; Measures='Restrict full-export rights to approved roles, add explicit export logging/review, and document approval/handling rules for exported files.'; Type='Technical and organizational'; Standard='Need-to-know / minimum necessary data sharing'; Start=$today; End=$targetMedium; Severity=4; Likelihood=2 }
    [ordered]@{ Consequence='Change-history and utility tables may over-retain sensitive beneficiary records'; Measures='Approve and implement a retention schedule covering meb_change_history, audit_logs, mail_logs, utility uploads/results, and generated outputs.'; Type='Organizational'; Standard='Records retention and disposal governance'; Start=$today; End=$targetLong; Severity=4; Likelihood=2 }
    [ordered]@{ Consequence='Upload directories may expose attachments or routed documents if directly reachable'; Measures='Confirm production web-server protection for upload folders, disable public listing/direct access where possible, and add malware scanning if available.'; Type='Technical and organizational'; Standard='Secure file storage and upload handling'; Start=$today; End=$targetMedium; Severity=4; Likelihood=2 }
    [ordered]@{ Consequence='Utility uploads and result sets duplicate beneficiary data outside the core MEB repository'; Measures='Limit crossmatch/dedup tools to admin or designated data stewards, define retention, and review generated files regularly.'; Type='Technical and organizational'; Standard='Access minimization and controlled secondary processing'; Start=$today; End=$targetMedium; Severity=4; Likelihood=2 }
    [ordered]@{ Consequence='Email, map, and drive integrations create third-party exposure points'; Measures='Document the exact data shared with SMTP, SSO, map providers, and external drive repositories, and ensure only minimum necessary data is transmitted.'; Type='Organizational'; Standard='Third-party disclosure control'; Start=$today; End=$targetShort; Severity=3; Likelihood=2 }
    [ordered]@{ Consequence='Hybrid legacy/normalized schema can weaken retention and source-of-truth governance'; Measures='Formally identify the production source of truth for inbox/events/implementation modules and retire legacy fields when migration is complete.'; Type='Organizational and technical'; Standard='Data governance and system design control'; Start=$today; End=$targetLong; Severity=3; Likelihood=2 }
    [ordered]@{ Consequence='Logs may retain personal/security metadata longer than necessary'; Measures='Define access restrictions, review cadence, and retention periods for audit_logs and mail_logs, including archived copies and backups.'; Type='Organizational'; Standard='Security monitoring with retention limits'; Start=$today; End=$targetMedium; Severity=3; Likelihood=2 }
    [ordered]@{ Consequence='Project map and drive-link records may reveal location/evidence details beyond intended audiences'; Measures='Review who can access implementation map/record pages, minimize displayed data where feasible, and set rules for storing external drive links.'; Type='Technical and organizational'; Standard='Need-to-know and data minimization'; Start=$today; End=$targetMedium; Severity=3; Likelihood=2 }
    [ordered]@{ Consequence='Privilege misuse could expose user, beneficiary, export, and log data'; Measures='Use periodic access review, prompt deactivation of unnecessary accounts, and separate review of admin/editor/aa privileges from ordinary users.'; Type='Organizational'; Standard='Access governance and segregation of duties'; Start=$today; End=$targetMedium; Severity=4; Likelihood=2 }
)

if (-not (Test-Path -LiteralPath $templatePath)) {
    throw "Template workbook not found: $templatePath"
}

Copy-Item -LiteralPath $templatePath -Destination $outputPath -Force

$excel = $null
$workbook = $null
$threshold = $null
$dps = $null
$pdi = $null
$risk = $null
$controls = $null
$signoff = $null

try {
    $excel = New-Object -ComObject Excel.Application
    $excel.Visible = $false
    $excel.DisplayAlerts = $false

    $workbook = $excel.Workbooks.Open($outputPath)
    $threshold = $workbook.Worksheets.Item('Threshold Analysis')
    $dps = $workbook.Worksheets.Item('DPS (1)')
    $pdi = $workbook.Worksheets.Item('PDI (2)')
    $risk = $workbook.Worksheets.Item('Risk Identification (3)')
    $controls = $workbook.Worksheets.Item('Security Controls (4)')
    $signoff = $workbook.Worksheets.Item('SIGN OFF SHEET (6)')

    Set-CellValue $threshold 'B2' $processName
    Set-CellValue $threshold 'B3' $processOwner
    Set-CellValue $threshold 'B4' 'Authorized DSWD / KODUS personnel with role-based access (admin, editor, aa, user)'
    Set-CellValue $threshold 'B5' 'No direct public user role confirmed; external recipients may receive emails/exports as approved operationally'
    Set-CellValue $threshold 'A7' $thresholdDescription.Trim()
    Set-CellValue $threshold 'C12' 'Yes'
    Set-CellValue $threshold 'D12' 1
    Set-CellValue $threshold 'C13' 'Yes'
    Set-CellValue $threshold 'D13' 1
    Set-CellValue $threshold 'C14' 'No'
    Set-CellValue $threshold 'D14' 0
    Set-CellValue $threshold 'C15' 'No'
    Set-CellValue $threshold 'D15' 0
    Set-CellValue $threshold 'C16' 'No'
    Set-CellValue $threshold 'D16' 0
    Set-CellValue $threshold 'C17' 'No'
    Set-CellValue $threshold 'D17' 0
    Set-CellValue $threshold 'C18' 'Yes'
    Set-CellValue $threshold 'D18' 1
    Set-CellValue $threshold 'C19' 'No'
    Set-CellValue $threshold 'D19' 0
    Set-CellValue $threshold 'D11' 3
    Set-CellValue $threshold 'B24' 'Proceed with PIA (required)'
    Set-CellValue $threshold 'B27' 'Prepared as draft from code and live DB evidence'
    Set-CellValue $threshold 'B28' 'System owner / process owner to confirm'
    Set-CellValue $threshold 'B29' $today
    Set-CellValue $threshold 'B31' $copName
    Set-CellValue $threshold 'B32' 'Compliance Officer for Privacy'
    Set-CellValue $threshold 'B33' ''
    Set-CellValue $threshold 'B35' $regionalDirector
    Set-CellValue $threshold 'B36' 'Noted by (to confirm)'
    Set-CellValue $threshold 'B37' ''

    Set-CellValue $dps 'B3' "Process Name: $processName"
    Clear-Range $dps 'B7:M18'
    $dpsRow = 7
    foreach ($row in $dpsRows) {
        Set-CellValue $dps "B$dpsRow" $row.Stakeholder
        Set-CellValue $dps "D$dpsRow" $row.Collection.Trim()
        Set-CellValue $dps "F$dpsRow" $row.Use.Trim()
        Set-CellValue $dps "I$dpsRow" $row.Storage.Trim()
        Set-CellValue $dps "K$dpsRow" $row.Sharing.Trim()
        Set-CellValue $dps "M$dpsRow" $row.Disposal.Trim()
        $dpsRow++
    }

    Clear-Range $pdi 'B5:M40'
    $pdiRow = 5
    foreach ($row in $pdiRows) {
        Set-CellValue $pdi "B$pdiRow" $row.Data
        Set-CellValue $pdi "C$pdiRow" $row.Type
        Set-CellValue $pdi "D$pdiRow" $row.Source
        Set-CellValue $pdi "E$pdiRow" $row.Purpose
        Set-CellValue $pdi "F$pdiRow" $row.Legal
        Set-CellValue $pdi "G$pdiRow" $row.Location
        Set-CellValue $pdi "H$pdiRow" $row.Internal
        Set-CellValue $pdi "I$pdiRow" $row.PIPs
        Set-CellValue $pdi "J$pdiRow" $row.OtherPICs
        Set-CellValue $pdi "K$pdiRow" $row.Disclosure
        Set-CellValue $pdi "L$pdiRow" $row.Protection
        Set-CellValue $pdi "M$pdiRow" $row.Backup
        $pdiRow++
    }

    Clear-Range $risk 'B18:G40'
    $riskRow = 18
    foreach ($row in $riskRows) {
        Set-CellValue $risk "B$riskRow" $row.Impact
        Set-CellValue $risk "C$riskRow" $row.Consequence
        Set-CellValue $risk "D$riskRow" $row.Threat
        Set-CellValue $risk "E$riskRow" $row.Severity
        Set-CellValue $risk "F$riskRow" $row.Likelihood
        Set-CellValue $risk "G$riskRow" ($row.Severity * $row.Likelihood)
        $riskRow++
    }

    Clear-Range $controls 'B11:J35'
    $controlRow = 11
    foreach ($row in $controlRows) {
        Set-CellValue $controls "A$controlRow" ($controlRow - 10)
        Set-CellValue $controls "B$controlRow" $row.Consequence
        Set-CellValue $controls "C$controlRow" $row.Measures
        Set-CellValue $controls "D$controlRow" $row.Type
        Set-CellValue $controls "E$controlRow" $row.Standard
        Set-CellValue $controls "F$controlRow" $row.Start
        Set-CellValue $controls "G$controlRow" $row.End
        Set-CellValue $controls "H$controlRow" $row.Severity
        Set-CellValue $controls "I$controlRow" $row.Likelihood
        Set-CellValue $controls "J$controlRow" ($row.Severity * $row.Likelihood)
        $controlRow++
    }

    Set-CellValue $signoff 'D7' $processName
    Set-CellValue $signoff 'D10' $processOwner
    Set-CellValue $signoff 'D11' 'Process owner / system owner (to confirm)'
    Set-CellValue $signoff 'D13' $startDate
    Set-CellValue $signoff 'D14' $today
    Set-CellValue $signoff 'D16' $copName
    Set-CellValue $signoff 'D17' 'Compliance Officer for Privacy'
    Set-CellValue $signoff 'D19' ''
    Set-CellValue $signoff 'D20' $nextPiaDate
    Set-CellValue $signoff 'D22' $regionalDirector
    Set-CellValue $signoff 'D24' ''
    Set-CellValue $signoff 'K12' 'PIA_FINAL_DRAFT.md'
    Set-CellValue $signoff 'K13' 'PIA_SCOPE_AUDIT.md'
    Set-CellValue $signoff 'K14' 'PIA_DATA_FLOW_NOTES.md'
    Set-CellValue $signoff 'K15' 'PIA_OWNER_ANSWERS_DRAFT.md'
    Set-CellValue $signoff 'K16' 'Annex C - RRP through CFT and Work for FY 2024'

    foreach ($sheet in @($threshold, $dps, $pdi, $risk, $controls, $signoff)) {
        $sheet.Columns.AutoFit() | Out-Null
        $sheet.Rows.AutoFit() | Out-Null
    }

    $workbook.Save()
    Write-Output $outputPath
}
finally {
    if ($workbook) {
        $workbook.Close($true)
        [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($workbook)
    }
    if ($excel) {
        $excel.Quit()
        [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($excel)
    }
    foreach ($obj in @($signoff, $controls, $risk, $pdi, $dps, $threshold)) {
        if ($obj) {
            [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($obj)
        }
    }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}
