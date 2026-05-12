const fs = require("fs");
const path = require("path");
const {
  AlignmentType,
  BorderStyle,
  Document,
  Footer,
  Header,
  HeadingLevel,
  ImageRun,
  LevelFormat,
  Packer,
  PageBreak,
  PageNumber,
  Paragraph,
  ShadingType,
  SimpleField,
  Table,
  TableCell,
  TableOfContents,
  TableRow,
  TextRun,
  WidthType,
} = require("../../annexes/.tooling/node_modules/docx");

const repoRoot = path.resolve(__dirname, "../../../..");
const outputDir = path.join(repoRoot, "docs/gpd/appendices/docx");
const screenshotDir = path.join(repoRoot, "docs/gpd/annexes/screenshots");
const docsDir = path.join(repoRoot, "docs");

const BLUE = "1F4E79";
const RED = "C00000";
const GRAY = "666666";
const LIGHT_BLUE = "D9EAF7";
const LIGHT_GRAY = "F2F2F2";

const page = {
  margin: { top: 1080, right: 900, bottom: 900, left: 900 },
  size: { width: 11906, height: 16838 },
};

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

function run(text, options = {}) {
  return new TextRun({
    text,
    font: "Arial",
    size: options.size || 22,
    bold: !!options.bold,
    italics: !!options.italics,
    color: options.color || "000000",
    allCaps: !!options.allCaps,
    break: options.break || 0,
  });
}

function field(instr) {
  return new SimpleField(instr);
}

function para(children, options = {}) {
  return new Paragraph({
    children: Array.isArray(children) ? children : [run(children || "", options)],
    alignment: options.alignment || AlignmentType.JUSTIFIED,
    heading: options.heading,
    spacing: {
      before: options.before ?? 80,
      after: options.after ?? 120,
      line: options.line ?? 276,
    },
    bullet: options.bullet ? { level: 0 } : undefined,
  });
}

function h(text, level = 1) {
  const heading = level === 1 ? HeadingLevel.HEADING_1 : level === 2 ? HeadingLevel.HEADING_2 : HeadingLevel.HEADING_3;
  return para([run(text, { bold: true, color: BLUE, size: level === 1 ? 30 : level === 2 ? 25 : 22 })], {
    heading,
    alignment: AlignmentType.LEFT,
    before: level === 1 ? 300 : 220,
    after: 130,
  });
}

function note(text) {
  return para([run(text, { bold: true, color: RED })], { alignment: AlignmentType.LEFT });
}

function footer() {
  return new Footer({
    children: [
      new Paragraph({
        alignment: AlignmentType.CENTER,
        children: [
          run("FOR OFFICIAL USE ONLY", { bold: true, size: 18, color: GRAY }),
          run("    Page ", { size: 18, color: GRAY }),
          new TextRun({ children: [PageNumber.CURRENT], font: "Arial", size: 18, color: GRAY }),
        ],
      }),
    ],
  });
}

function header(meta) {
  return new Header({
    children: [
      new Paragraph({
        alignment: AlignmentType.CENTER,
        children: [run(`KODUS Good Practice Documentation - ${meta.code}`, { bold: true, size: 18, color: GRAY })],
      }),
    ],
  });
}

function titlePage(meta) {
  return [
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 2200, after: 320 }, children: [run(meta.code, { bold: true, color: BLUE, size: 42 })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 280 }, children: [run(meta.title, { bold: true, size: 30 })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 220 }, children: [run("KliMalasakit Operational Data Unified System (KODUS)", { size: 24 })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 650 }, children: [run("Good Practice Documentation Appendix Package", { size: 22 })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 180 }, children: [run("DSWD Field Office Caraga", { size: 22 })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 180 }, children: [run("Prepared for Knowledge Management documentation review", { italics: true, size: 20 })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, children: [run("[MANUAL INPUT REQUIRED: Insert official document date, version, reviewer, and approving official]", { bold: true, color: RED, size: 20 })] }),
    new Paragraph({ children: [new PageBreak()] }),
  ];
}

function toc() {
  return [
    h("Table of Contents", 1),
    new TableOfContents("", { hyperlink: true, headingStyleRange: "1-3" }),
    new Paragraph({ children: [new PageBreak()] }),
  ];
}

function table(rows, options = {}) {
  const maxCols = Math.max(...rows.map((row) => row.length));
  const widths = options.widths || Array.from({ length: maxCols }, () => Math.floor(100 / maxCols));
  return new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    borders: {
      top: { style: BorderStyle.SINGLE, size: 4, color: "808080" },
      bottom: { style: BorderStyle.SINGLE, size: 4, color: "808080" },
      left: { style: BorderStyle.SINGLE, size: 4, color: "808080" },
      right: { style: BorderStyle.SINGLE, size: 4, color: "808080" },
      insideHorizontal: { style: BorderStyle.SINGLE, size: 4, color: "BFBFBF" },
      insideVertical: { style: BorderStyle.SINGLE, size: 4, color: "BFBFBF" },
    },
    rows: rows.map((row, rowIndex) => new TableRow({
      tableHeader: rowIndex === 0,
      children: Array.from({ length: maxCols }).map((_, cellIndex) => new TableCell({
        width: { size: widths[cellIndex] || Math.floor(100 / maxCols), type: WidthType.PERCENTAGE },
        shading: rowIndex === 0 ? { type: ShadingType.CLEAR, fill: LIGHT_BLUE } : undefined,
        margins: { top: 90, bottom: 90, left: 90, right: 90 },
        children: [
          new Paragraph({
            alignment: AlignmentType.LEFT,
            spacing: { before: 0, after: 0, line: 240 },
            children: [run(String(row[cellIndex] || ""), { size: 18, bold: rowIndex === 0, color: rowIndex === 0 ? BLUE : "000000" })],
          }),
        ],
      })),
    })),
  });
}

function tableCaption(prefix, title) {
  return para([run(`Table ${prefix}-`), field(`SEQ Table${prefix} \\* ARABIC`), run(`. ${title}`, { bold: true })], {
    alignment: AlignmentType.CENTER,
    before: 120,
    after: 80,
  });
}

function figCaption(prefix, title) {
  return para([run(`Figure ${prefix}-`), field(`SEQ Figure${prefix} \\* ARABIC`), run(`. ${title}`, { bold: true })], {
    alignment: AlignmentType.CENTER,
    before: 80,
    after: 60,
  });
}

function purpose(text) {
  return para([run("Purpose/use: ", { bold: true }), run(text)], { alignment: AlignmentType.JUSTIFIED, before: 20, after: 150 });
}

function imageFigure(prefix, title, imagePath, purposeText) {
  const children = [];
  if (imagePath && fs.existsSync(imagePath)) {
    const data = fs.readFileSync(imagePath);
    children.push(new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { before: 120, after: 80 },
      children: [new ImageRun({ data, transformation: { width: 560, height: 369 }, type: "png" })],
    }));
  } else {
    children.push(placeholderBox(title));
  }
  children.push(figCaption(prefix, title));
  children.push(purpose(purposeText));
  return children;
}

function placeholderBox(label) {
  return new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    borders: {
      top: { style: BorderStyle.SINGLE, size: 8, color: "999999" },
      bottom: { style: BorderStyle.SINGLE, size: 8, color: "999999" },
      left: { style: BorderStyle.SINGLE, size: 8, color: "999999" },
      right: { style: BorderStyle.SINGLE, size: 8, color: "999999" },
    },
    rows: [
      new TableRow({
        children: [
          new TableCell({
            shading: { type: ShadingType.CLEAR, fill: LIGHT_GRAY },
            margins: { top: 850, bottom: 850, left: 120, right: 120 },
            children: [
              new Paragraph({
                alignment: AlignmentType.CENTER,
                children: [run("[MANUAL SCREENSHOT INSERT REQUIRED]", { bold: true, color: RED, size: 22 })],
              }),
              new Paragraph({
                alignment: AlignmentType.CENTER,
                children: [run(label, { bold: true, color: GRAY, size: 18 })],
              }),
              new Paragraph({
                alignment: AlignmentType.CENTER,
                children: [run("Use sanitized or synthetic data only.", { italics: true, color: GRAY, size: 18 })],
              }),
            ],
          }),
        ],
      }),
    ],
  });
}

function processMap(prefix, title, steps, purposeText) {
  const rows = [["Step", "Responsible office/user", "Control or output"]];
  for (const step of steps) rows.push(step);
  return [
    tableCaption(prefix, title),
    table(rows, { widths: [32, 28, 40] }),
    purpose(purposeText),
  ];
}

function diagramLane(prefix, title, lanes, purposeText) {
  const rows = [["Process stage", ...lanes.map((lane) => lane.name)]];
  const max = Math.max(...lanes.map((lane) => lane.items.length));
  for (let i = 0; i < max; i++) rows.push([`Stage ${i + 1}`, ...lanes.map((lane) => lane.items[i] || "")]);
  return [
    figCaption(prefix, title),
    table(rows),
    purpose(purposeText),
  ];
}

function intro(meta, scope) {
  return [
    h(`${meta.code} - ${meta.title}`, 1),
    para(`This appendix forms part of the KODUS Good Practice Documentation package. It is prepared in formal DSWD Knowledge Management style to provide organized evidence, process notes, and documentary references for review, replication, and institutional learning.`),
    para(`Scope: ${scope}`),
    para(`Confidentiality notice: All examples in this appendix use sanitized, aggregate, or synthetic data. Screenshots pending owner validation are marked with manual insertion notices. Passwords, tokens, OAuth secrets, beneficiary data, contact details, private host identifiers, and other sensitive values are excluded.`),
  ];
}

function commonChecklist(prefix) {
  return [
    h(`${prefix}. Owner Validation Checklist`, 2),
    tableCaption(prefix, "Owner validation checklist"),
    table([
      ["Validation item", "Required action", "Status"],
      ["Sanitization", "Confirm no beneficiary-level personal information, account secret, token, or password is visible.", "[MANUAL INPUT REQUIRED]"],
      ["Currency", "Confirm screenshots, tables, and process notes reflect the approved KODUS build.", "[MANUAL INPUT REQUIRED]"],
      ["Sign-off", "Record reviewer, date reviewed, and approving official in the title page.", "[MANUAL INPUT REQUIRED]"],
      ["Retention", "File the validated appendix with the controlled GPD/KM documentation set.", "[MANUAL INPUT REQUIRED]"],
    ], { widths: [28, 52, 20] }),
  ];
}

function appendixA() {
  const meta = { code: "Appendix A", letter: "A", title: "KODUS Operational Workflow Screenshots", file: "APPENDIX_A_OPERATIONAL_WORKFLOW_SCREENSHOTS.docx" };
  const body = [
    ...intro(meta, "Major KODUS operational screens, including login, dashboard, implementation monitoring, MEB processing, reports, communications, and administrative controls."),
    h("A.1 Screenshot Capture Standards", 2),
    para("Screenshots shall be captured from staging, test accounts, or sanitized production views only. Images must be inserted in line with text, retain readable resolution, and show only information required to demonstrate the workflow."),
    tableCaption("A", "Operational screenshot inventory"),
    table([
      ["Figure", "Screen or module", "Route/reference", "Required capture control"],
      ["A-1", "Login", "login.php", "Show the login form before credentials are entered."],
      ["A-2", "Dashboard", "home.php", "Use sanitized counts and role-appropriate widgets."],
      ["A-3", "Program Targets", "implementation-status/program-targets.php", "Show target setup using test location values."],
      ["A-4", "Program Activities", "implementation-status/program-activities.php", "Show activity monitoring with synthetic activity details."],
      ["A-5", "LAWA/BINHI Summary", "implementation-status summary pages", "Show aggregate outputs only."],
      ["A-6", "MEB Import", "pages/data-tracking-meb.php", "Blur or replace row-level beneficiary records."],
      ["A-7", "MEB Validation", "pages/data-tracking-meb-validation.php", "Show target-versus-actual counts by location."],
      ["A-8", "MEBIS Consolidator", "mebis-consolidator/index.php", "Use test upload filenames only."],
      ["A-9", "Deduplication", "deduplication/index.php", "Use synthetic duplicate job names and rows."],
      ["A-10", "Crossmatch", "crossmatch/index.php", "Use synthetic records and candidate scores."],
      ["A-11", "Reports", "pages/export and summary modules", "Show aggregate reporting entry points."],
      ["A-12", "Inbox/Messenger", "inbox/ and messenger/", "Use test conversation content only."],
      ["A-13", "Notifications", "notifications/", "Use non-sensitive notification entries."],
      ["A-14", "Settings/Profile", "settings.php", "Mask personal information and security artifacts."],
      ["A-15", "Audit Logs", "admin/audit_logs.php", "Show sanitized actions and users."],
      ["A-16", "User Management", "admin/users_management.php", "Mask user identities and contact details."],
      ["A-17", "Maintenance/Password Security", "admin/maintenance.php; admin/password_security.php", "Do not show secrets or sensitive configuration values."],
    ], { widths: [12, 24, 30, 34] }),
    h("A.2 Screen Evidence", 2),
  ];

  const figs = [
    ["Login / Username-Password Access", "ANNEX_A_FIGURE_A-01_LOGIN.png", "Shows authenticated entry to KODUS without exposing credentials."],
    ["Dashboard / Home", "ANNEX_A_FIGURE_A-02_DASHBOARD.png", "Provides the role-based landing page for operational monitoring and navigation."],
    ["Implementation Status - Program Targets", "ANNEX_A_FIGURE_A-03_PROGRAM_TARGETS.png", "Documents target setup and monitoring reference points for implementation tracking."],
    ["Implementation Status - Program Activities", "ANNEX_A_FIGURE_A-04_PROGRAM_ACTIVITIES.png", "Shows the activity monitoring surface for timeline and accomplishment updates."],
    ["Implementation Status - LAWA/BINHI Summary", "ANNEX_A_FIGURE_A-05_LAWA_BINHI_SUMMARY.png", "Presents aggregate target-versus-actual summaries for management review."],
    ["MEB Import / Master List", "ANNEX_A_FIGURE_A-06_MEB_IMPORT.png", "Shows the MEB import/list workflow using sanitized or test records."],
    ["MEB Validation", "ANNEX_A_FIGURE_A-07_MEB_VALIDATION.png", "Documents validation status review and count comparison by location."],
    ["MEBIS Consolidator", "ANNEX_A_FIGURE_A-08_MEBIS_CONSOLIDATOR.png", "Shows consolidated workbook processing without exposing raw personal records."],
    ["Deduplication Upload / Recent Jobs", "ANNEX_A_FIGURE_A-09_DEDUPLICATION.png", "Documents the duplicate-detection entry point and job review surface."],
    ["Crossmatch Upload / Start", "ANNEX_A_FIGURE_A-10_CROSSMATCH.png", "Shows the crossmatch workflow using test files or synthetic records only."],
    ["Reports / Export Screens", "ANNEX_A_FIGURE_A-11_REPORTS.png", "Documents report and export entry points for aggregate or approved outputs."],
    ["Inbox / Messenger", "ANNEX_A_FIGURE_A-12_INBOX_MESSENGER.png", "Shows staff coordination features using test conversation content only."],
    ["Notifications", "ANNEX_A_FIGURE_A-13_NOTIFICATIONS.png", "Documents operational notification delivery with non-sensitive sample entries."],
    ["Settings / Profile", "ANNEX_A_FIGURE_A-14_SETTINGS_PROFILE.png", "Shows account settings while excluding personal contact details and security secrets."],
    ["Audit Logs", "ANNEX_A_FIGURE_A-15_AUDIT_LOGS.png", "Shows accountability monitoring through sanitized action logs."],
    ["Admin User Management", "ANNEX_A_FIGURE_A-16_USER_MANAGEMENT.png", "Documents user administration controls with masked or test identities."],
    ["Admin Maintenance / Password Security", null, "Records maintenance and password security controls once an approved sanitized screenshot is available."],
  ];
  figs.forEach((fig) => body.push(...imageFigure("A", fig[0], fig[1] ? path.join(screenshotDir, fig[1]) : null, fig[2])));
  body.push(...commonChecklist("A"));
  return { meta, body };
}

function appendixB() {
  const meta = { code: "Appendix B", letter: "B", title: "MEB Validation Screenshots", file: "APPENDIX_B_MEB_VALIDATION_SCREENSHOTS.docx" };
  const body = [
    ...intro(meta, "MEB import, validation status review, exception handling through the MEB validation table, and validation-report surfaces used to compare imported actuals with approved target partner-beneficiary counts."),
    h("B.1 Validation Evidence Requirements", 2),
    para("MEB validation screenshots shall show only aggregate counts, status labels, batch references, and sanitized workbook names. Row-level beneficiary names, addresses, IDs, contact information, and birthdates shall be excluded or replaced with synthetic values."),
    ...imageFigure("B", "MEB Import Queue / Batch Processing", path.join(screenshotDir, "ANNEX_A_FIGURE_A-06_MEB_IMPORT.png"), "Shows the controlled entry point for uploaded MEB workbooks and import status review."),
    ...imageFigure("B", "MEB Validation Count Comparison", path.join(screenshotDir, "ANNEX_A_FIGURE_A-07_MEB_VALIDATION.png"), "Shows location-level target and actual counts used for validation status review."),
    ...imageFigure("B", "MEBIS Consolidator Support Screen", path.join(screenshotDir, "ANNEX_A_FIGURE_A-08_MEBIS_CONSOLIDATOR.png"), "Documents related workbook consolidation support for MEB validation preparation."),
    h("B.2 Validation Status and Exception Handling", 2),
    para("KODUS does not have a separate screen named Validation Exception Review. Exception handling is performed through the MEB validation screen, its status labels, the DataTables search/filter function, the Edit Rows action for imported records, and the validation Excel export."),
    h("B.3 Validation Status Reference", 2),
    tableCaption("B", "MEB validation status reference"),
    table([
      ["Status", "Meaning", "Expected reviewer action"],
      ["No Target", "Imported or reviewed location has no approved target record.", "Confirm target setup or mark as out of scope."],
      ["No Import", "Target exists but no corresponding MEB import has been recorded.", "Follow up with responsible operating unit."],
      ["Partial", "Imported actual count is below approved target count.", "Review missing entries and reconcile with field office source."],
      ["Validated", "Imported actual count matches approved target count.", "Record validation and proceed to approved reporting output."],
      ["Over Target", "Imported actual count exceeds approved target count.", "Review excess entries and correct source workbook or target reference."],
      ["Unplanned Import", "Import exists for a location outside the approved target plan.", "Confirm whether the record should be reclassified, corrected, or excluded."],
    ], { widths: [20, 40, 40] }),
    h("B.4 Validation Trail Notes", 2),
    para("The validation reviewer should retain the sanitized screenshot, source-batch reference, date reviewed, and reviewer name in the controlled evidence file. Sensitive source workbooks and raw beneficiary-level exports shall remain in authorized operational storage only."),
    ...commonChecklist("B"),
  ];
  return { meta, body };
}

function appendixC() {
  const meta = { code: "Appendix C", letter: "C", title: "Sample Implementation Summaries", file: "APPENDIX_C_SAMPLE_IMPLEMENTATION_SUMMARIES.docx" };
  const body = [
    ...intro(meta, "Synthetic implementation summaries for demonstrating how KODUS supports management review of Project LAWA and BINHI targets, activities, and accomplishments."),
    h("C.1 Summary Preparation Note", 2),
    para("The sample summaries below are illustrative only. They are designed to show the documentary form and review fields expected in the GPD package without disclosing actual beneficiary-level or personally identifiable records."),
    tableCaption("C", "Sample provincial implementation summary"),
    table([
      ["Province", "Municipalities covered", "Approved target PBs", "Imported/validated PBs", "Validation status", "Management note"],
      ["Agusan del Norte", "3", "120", "118", "Partial", "Two pending entries subject to source workbook reconciliation."],
      ["Agusan del Sur", "4", "180", "180", "Validated", "Counts aligned with approved target records."],
      ["Surigao del Norte", "2", "90", "94", "Over Target", "Four excess entries under validation review."],
      ["Surigao del Sur", "3", "150", "150", "Validated", "Ready for consolidated reporting."],
      ["Dinagat Islands", "1", "45", "42", "Partial", "Three pending entries awaiting field confirmation."],
    ], { widths: [19, 16, 16, 16, 16, 17] }),
    tableCaption("C", "Sample activity accomplishment summary"),
    table([
      ["Activity area", "Synthetic reporting period", "Planned activities", "Completed activities", "Variance", "Action point"],
      ["Climate-resilient livelihood support", "Q1 2026", "12", "11", "-1", "Confirm rescheduled activity date."],
      ["Cash-for-training/work implementation", "Q1 2026", "18", "18", "0", "Maintain routine monitoring."],
      ["MEB validation and reporting", "Q1 2026", "5", "4", "-1", "Complete pending workbook validation."],
      ["Monitoring and technical assistance", "Q1 2026", "8", "8", "0", "Attach approved monitoring evidence."],
    ], { widths: [30, 18, 14, 14, 10, 14] }),
    h("C.2 Sample Narrative Summary", 2),
    para("For the synthetic reporting period, KODUS supported consolidation of target and accomplishment references across covered localities. The dashboard and validation modules enabled management to identify locations with complete validation, partial completion, or records requiring further reconciliation. The generated summaries are intended to support operational decision-making, management review, and preparation of formal reports using controlled and validated source data."),
    h("C.3 Recommended Summary Attachments", 2),
    para("The final owner-validated package may attach approved aggregate exports, sanitized screenshots, and signed review sheets. Raw operational workbooks, unmasked beneficiary records, and source files containing personal data shall not be included in the public or KM-facing appendix package."),
    ...commonChecklist("C"),
  ];
  return { meta, body };
}

function appendixD() {
  const meta = { code: "Appendix D", letter: "D", title: "Audit-Log Screenshots", file: "APPENDIX_D_AUDIT_LOG_SCREENSHOTS.docx" };
  const body = [
    ...intro(meta, "Audit log views, accountability controls, and sanitized sample audit fields used to document traceability of KODUS actions."),
    h("D.1 Audit-Log Evidence Standards", 2),
    para("Audit-log evidence shall demonstrate accountability controls without exposing private user details, IP addresses, session identifiers, tokens, or beneficiary-level data. Where screenshots contain sensitive values, the document owner shall redact or replace them before insertion."),
    ...imageFigure("D", "Audit Logs Administrative View", path.join(screenshotDir, "ANNEX_A_FIGURE_A-15_AUDIT_LOGS.png"), "Shows administrative review of user actions, timestamps, and system event details using sanitized values."),
    ...imageFigure("D", "User Management Accountability Context", path.join(screenshotDir, "ANNEX_A_FIGURE_A-16_USER_MANAGEMENT.png"), "Shows the administrative context for role and account actions that may be reflected in the audit trail."),
    ...imageFigure("D", "Filtered Audit Search Result", null, "Reserved for a sanitized screenshot showing filtered audit results for a selected action category and date range."),
    h("D.2 Synthetic Audit-Log Sample", 2),
    tableCaption("D", "Synthetic audit-log sample"),
    table([
      ["Timestamp", "Role", "Action category", "Object reference", "Sanitized details"],
      ["2026-05-01 09:15", "System Administrator", "User status update", "USER-TEST-014", "Test account status changed from Active to Inactive."],
      ["2026-05-01 10:42", "Editor", "MEB import", "BATCH-SYN-2026-004", "Synthetic workbook import completed with 120 rows."],
      ["2026-05-02 14:05", "Authorized AA", "Validation review", "VAL-SYN-2026-011", "Location status updated to Validated after count reconciliation."],
      ["2026-05-03 08:55", "System", "Background job", "DEDUP-SYN-2026-002", "Deduplication job completed; output stored in controlled area."],
    ], { widths: [20, 20, 22, 18, 20] }),
    h("D.3 Audit Review Note", 2),
    para("Audit-log extracts included in documentation shall be limited to the minimum fields required to demonstrate the control. Full audit-log exports remain operational records and shall be handled under applicable information security, records management, and privacy requirements."),
    ...commonChecklist("D"),
  ];
  return { meta, body };
}

function appendixE() {
  const meta = { code: "Appendix E", letter: "E", title: "Deduplication and Crossmatch Result Samples", file: "APPENDIX_E_DEDUPLICATION_AND_CROSSMATCH_RESULTS.docx" };
  const body = [
    ...intro(meta, "Synthetic duplicate-detection and crossmatch result formats for demonstrating KODUS matching outputs while preserving confidentiality."),
    h("E.1 Result Interpretation Note", 2),
    para("The following samples use synthetic record codes, synthetic names, and non-production scores. They demonstrate how reviewers may interpret possible duplicate groups and crossmatch candidates. No real beneficiary identifiers are included."),
    ...imageFigure("E", "Deduplication Upload / Recent Jobs", path.join(screenshotDir, "ANNEX_A_FIGURE_A-09_DEDUPLICATION.png"), "Shows the entry point for deduplication processing using synthetic or test upload references."),
    ...imageFigure("E", "Crossmatch Upload / Candidate Review", path.join(screenshotDir, "ANNEX_A_FIGURE_A-10_CROSSMATCH.png"), "Shows crossmatch setup and candidate review controls using synthetic references."),
    tableCaption("E", "Synthetic deduplication group sample"),
    table([
      ["Group ID", "Synthetic record code", "Synthetic display name", "Location hint", "Similarity score", "Reviewer disposition"],
      ["DG-001", "REC-SYN-00041", "Person Alpha", "Municipality A", "96%", "Probable duplicate; confirm source workbook."],
      ["DG-001", "REC-SYN-00077", "Person Alfa", "Municipality A", "96%", "Probable duplicate; same group as REC-SYN-00041."],
      ["DG-002", "REC-SYN-00118", "Person Beta", "Municipality B", "88%", "Possible duplicate; review supporting fields."],
      ["DG-002", "REC-SYN-00204", "Person B.", "Municipality B", "88%", "Possible duplicate; review supporting fields."],
    ], { widths: [13, 20, 20, 17, 13, 17] }),
    tableCaption("E", "Synthetic crossmatch candidate sample"),
    table([
      ["Uploaded record", "Top candidate", "Name score", "Location score", "Overall score", "Recommended action"],
      ["UP-SYN-0031", "MEB-SYN-0881", "92%", "100%", "95%", "Review as likely match."],
      ["UP-SYN-0032", "MEB-SYN-1024", "76%", "100%", "83%", "Review before acceptance."],
      ["UP-SYN-0033", "No candidate above threshold", "N/A", "N/A", "N/A", "Treat as no automated match."],
    ], { widths: [18, 22, 15, 15, 15, 15] }),
    h("E.2 Reviewer Handling Guidance", 2),
    para("Automated scores are decision-support outputs and do not by themselves establish identity or eligibility. The accountable reviewer shall examine source-authorized evidence, document the basis for acceptance or rejection, and retain only the minimum necessary evidence in the controlled operational record."),
    ...commonChecklist("E"),
  ];
  return { meta, body };
}

function appendixF() {
  const meta = { code: "Appendix F", letter: "F", title: "Workflow Diagrams and Process Maps", file: "APPENDIX_F_WORKFLOW_DIAGRAMS_AND_PROCESS_MAPS.docx" };
  const body = [
    ...intro(meta, "Clean process maps and workflow diagrams for KODUS operational modules, prepared as editable Word tables for stable Microsoft Word compatibility."),
    h("F.1 KODUS Operational Workflow", 2),
    ...diagramLane("F", "High-level KODUS operational workflow", [
      { name: "Program/field user", items: ["Prepare approved source data", "Encode or upload operational records", "Review module outputs", "Submit corrected/validated data"] },
      { name: "KODUS module", items: ["Authenticate and apply role/area controls", "Process MEB, target, activity, and job records", "Generate summaries, exports, and notifications", "Record audit trail and validation status"] },
      { name: "Reviewer/administrator", items: ["Confirm authorization and data quality", "Review exceptions and validation status", "Approve aggregate outputs", "Maintain users, audit logs, and deployment controls"] },
    ], "Provides an editable process-map overview of how authorized users, KODUS modules, and reviewers interact during routine operations."),
    h("F.2 MEB Validation Process Map", 2),
    ...processMap("F", "MEB validation process map", [
      ["1. Prepare approved MEB workbook", "Program/field focal", "Workbook prepared using authorized source and sanitized documentation copy."],
      ["2. Upload/import workbook", "Authorized KODUS user", "Import job created and logged."],
      ["3. Validate headers and counts", "KODUS MEB module", "Batch status, row count, and target comparison generated."],
      ["4. Review exceptions", "Reviewer/AA/editor", "No Target, Partial, Over Target, or Unplanned Import items reviewed."],
      ["5. Correct or confirm", "Program owner/reviewer", "Source workbook or target reference corrected where needed."],
      ["6. Export approved output", "Authorized user", "Aggregate or approved report generated and filed."],
    ], "Shows the review path from source workbook preparation to approved MEB validation output."),
    h("F.3 Deduplication and Crossmatch Process Map", 2),
    ...processMap("F", "Deduplication and crossmatch process map", [
      ["1. Upload synthetic/test-ready dataset", "Authorized user", "Dataset accepted only through controlled upload path."],
      ["2. Run matching job", "KODUS worker", "Matching scores and candidate groups generated."],
      ["3. Review candidates", "Reviewer", "Possible duplicate or crossmatch findings assessed."],
      ["4. Record disposition", "Reviewer/program owner", "Accepted, rejected, or pending status documented."],
      ["5. Retain evidence", "System owner", "Only minimum necessary evidence retained in controlled records."],
    ], "Documents how matching outputs are used as review support while maintaining human validation and confidentiality controls."),
    h("F.4 Deployment and Operations Process Map", 2),
    ...processMap("F", "Deployment and operations process map", [
      ["1. Prepare reviewed package", "Technical maintainer", "Reviewed application code and dependencies identified."],
      ["2. Configure server environment", "Host/system owner", "Environment file, database, PHP runtime, and web-server rules configured without exposing secrets."],
      ["3. Run smoke tests", "Technical maintainer/system owner", "Login, modules, workers, exports, notifications, and audit logs verified."],
      ["4. Activate release", "Approving official/system owner", "Deployment date, version, and approver recorded."],
      ["5. Monitor and maintain", "Administrator/technical maintainer", "Backups, audit review, worker monitoring, and access review performed."],
    ], "Provides a non-secret operational map for controlled deployment and sustainment."),
    h("F.5 Reference Diagrams", 2),
    ...imageFigure("F", "KODUS Business Process Reference Architecture", path.join(docsDir, "KODUS_BPRA.png"), "Provides a repository-generated process reference diagram for architecture and workflow orientation."),
    ...imageFigure("F", "KODUS API/Data Exchange Reference", path.join(docsDir, "KODUS_API_DIAGRAM.png"), "Provides a repository-generated reference for API/data exchange orientation where applicable."),
    ...commonChecklist("F"),
  ];
  return { meta, body };
}

function appendixG() {
  const meta = { code: "Appendix G", letter: "G", title: "Deployment Documentation", file: "APPENDIX_G_DEPLOYMENT_DOCUMENTATION.docx" };
  const body = [
    ...intro(meta, "Deployment setup, hosting environment, backup/restore expectations, release management, background workers, realtime components, and sustainability controls for KODUS."),
    h("G.1 Deployment Profile", 2),
    tableCaption("G", "Deployment profile summary"),
    table([
      ["Item", "Description"],
      ["Application name", "KliMalasakit Operational Data Unified System (KODUS)"],
      ["Application type", "Server-rendered PHP/MySQL internal operations platform"],
      ["Primary operational areas", "MEB processing, validation, implementation monitoring, deduplication, crossmatch, reports, messaging, notifications, audit logs, and administrative controls"],
      ["Expected stack", "Linux server; Nginx or Apache; PHP-FPM/PHP CLI; MySQL or MariaDB; Composer; SMTP access; optional Socket.IO bridge"],
      ["Documentation basis", "Repository deployment notes reviewed on 2026-05-12, including Linux/Nginx deployment guidance and production package guide."],
    ], { widths: [28, 72] }),
    h("G.2 Deployment Setup Checklist", 2),
    tableCaption("G", "Deployment setup checklist"),
    table([
      ["Component", "Expected arrangement", "Owner validation"],
      ["Web server", "Nginx or Apache configured to route PHP requests and block secrets, logs, docs, dumps, private keys, and executable uploads.", "[MANUAL INPUT REQUIRED]"],
      ["PHP runtime", "PHP-FPM for web requests and PHP CLI for background workers.", "[MANUAL INPUT REQUIRED]"],
      ["Database", "MySQL or MariaDB with production credentials stored only in server environment.", "[MANUAL INPUT REQUIRED]"],
      ["Dependencies", "Composer dependencies installed using reviewed package process.", "[MANUAL INPUT REQUIRED]"],
      ["Environment file", ".env created directly on server and excluded from packages and documentation.", "[MANUAL INPUT REQUIRED]"],
      ["SMTP", "Mail relay configured through environment values without exposing credentials.", "[MANUAL INPUT REQUIRED]"],
      ["SSO", "Optional Caraga Connect SSO configured only when approved client details are issued.", "[MANUAL INPUT REQUIRED]"],
      ["Realtime", "Optional Socket.IO bridge enabled through environment values or recorded as fallback-polling only.", "[MANUAL INPUT REQUIRED]"],
    ], { widths: [20, 55, 25] }),
    h("G.3 Runtime Directories and Protection", 2),
    tableCaption("G", "Runtime directory protection summary"),
    table([
      ["Runtime area", "Representative paths", "Control note"],
      ["Crossmatch", "crossmatch/uploads/ and related job/result records", "Treat uploaded and generated files as sensitive."],
      ["Deduplication", "deduplication/uploads/; deduplication/logs/", "Block direct script execution and protect logs."],
      ["Inbox attachments", "inbox/uploads/", "Apply upload restrictions and retention controls."],
      ["Operational uploads", "pages/uploads/", "Store only authorized files with restricted access."],
      ["MEBIS outputs/jobs", "mebis-consolidator/outputs/; mebis-lgu-template/jobs/; mebis-lgu-template/outputs/", "Protect generated outputs from public access."],
      ["Profile exports", "pages/profile_exports/ and related job/output helpers", "Treat generated files as sensitive and review retention."],
    ], { widths: [22, 38, 40] }),
    h("G.4 Backup and Restore Approach", 2),
    para("The production backup process must be supplied and validated by the host or system owner. At minimum, the controlled operations record should cover database backups, required upload/output directories, deployment package references, non-secret configuration inventory, encryption approach, retention period, restoration-test schedule, and responsible personnel."),
    tableCaption("G", "Backup validation checklist"),
    table([
      ["Backup item", "Expected coverage", "Validation status"],
      ["Database", "Users, roles, MEB, targets, activities, reports, notifications, messages, audit logs, and job metadata.", "[MANUAL INPUT REQUIRED]"],
      ["Runtime files", "Required upload/output directories after sensitivity and retention review.", "[MANUAL INPUT REQUIRED]"],
      ["Configuration inventory", "Non-secret environment summary and deployment package/version reference.", "[MANUAL INPUT REQUIRED]"],
      ["Restore test", "Scheduled restoration test with date, responsible personnel, and result.", "[MANUAL INPUT REQUIRED]"],
    ], { widths: [22, 55, 23] }),
    h("G.5 Release and Maintenance Responsibilities", 2),
    tableCaption("G", "Maintenance responsibility matrix"),
    table([
      ["Area", "Responsible party", "Frequency", "Evidence"],
      ["User and role review", "System owner / administrator", "Per reporting cycle and personnel changes", "User review record"],
      ["Audit-log review", "Administrator / reviewer", "Per reporting cycle or incident review", "Audit review sheet"],
      ["Backup verification", "Host/system owner", "Per approved backup schedule", "Backup and restore test record"],
      ["Dependency/security review", "Technical maintainer", "Per release or advisory", "Patch/release notes"],
      ["Worker/job monitoring", "Administrator / maintainer", "Routine operations review", "Job status review"],
      ["Documentation update", "Documentation owner / KM reviewer", "Per release or policy cycle", "Updated manuals and appendices"],
    ], { widths: [22, 26, 24, 28] }),
    h("G.6 Background Jobs and Realtime Components", 2),
    tableCaption("G", "Worker and realtime component summary"),
    table([
      ["Area", "Representative files", "Purpose"],
      ["MEB import", "pages/meb_import_worker.php; pages/meb_import_helpers.php", "Background import of MEB workbooks with progress/status updates."],
      ["Profile export", "pages/profile_export_worker.php; pages/profile_export_job_helpers.php", "Background generation of profile exports."],
      ["Deduplication", "deduplication/worker.php; deduplication/worker_v2.php", "Duplicate detection and result storage."],
      ["Crossmatch", "crossmatch/run_job.php", "Match scoring and result storage."],
      ["MEBIS LGU template", "mebis-lgu-template/worker.php", "Background generation of import-ready templates."],
      ["Realtime bridge", "dist/js/kodus-live-refresh.js; socket helpers", "Optional live refresh and notification channels using environment-based configuration."],
    ], { widths: [22, 38, 40] }),
    h("G.7 Deployment Evidence Figure", 2),
    ...imageFigure("G", "Deployment Architecture Placeholder", null, "Reserved for approved non-secret deployment architecture screenshot or diagram showing environment family, web tier, application tier, database tier, and backup responsibility without hostnames, IP addresses, credentials, or network-sensitive details."),
    ...commonChecklist("G"),
  ];
  return { meta, body };
}

const appendices = [appendixA, appendixB, appendixC, appendixD, appendixE, appendixF, appendixG].map((make) => make());

function makeDocument(meta, children) {
  return new Document({
    creator: "KODUS GPD Appendix Tooling",
    title: `${meta.code} - ${meta.title}`,
    description: "KODUS Good Practice Documentation appendix package",
    styles: {
      default: {
        document: { run: { font: "Arial", size: 22 }, paragraph: { spacing: { line: 276, after: 120 } } },
      },
      paragraphStyles: [
        { id: "Heading1", name: "Heading 1", basedOn: "Normal", next: "Normal", quickFormat: true, run: { font: "Arial", size: 30, bold: true, color: BLUE }, paragraph: { spacing: { before: 300, after: 140 } } },
        { id: "Heading2", name: "Heading 2", basedOn: "Normal", next: "Normal", quickFormat: true, run: { font: "Arial", size: 25, bold: true, color: BLUE }, paragraph: { spacing: { before: 240, after: 130 } } },
        { id: "Heading3", name: "Heading 3", basedOn: "Normal", next: "Normal", quickFormat: true, run: { font: "Arial", size: 22, bold: true, color: BLUE }, paragraph: { spacing: { before: 200, after: 110 } } },
      ],
    },
    numbering: {
      config: [
        {
          reference: "default-bullets",
          levels: [{ level: 0, format: LevelFormat.BULLET, text: "•", alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 720, hanging: 360 } } } }],
        },
      ],
    },
    sections: [
      {
        properties: { page },
        headers: { default: header(meta) },
        footers: { default: footer() },
        children,
      },
    ],
  });
}

function docChildren(appendix, includeToc = true) {
  return [...titlePage(appendix.meta), ...(includeToc ? toc() : []), ...appendix.body];
}

async function writeDocx(file, doc) {
  const out = path.join(outputDir, file);
  fs.writeFileSync(out, await Packer.toBuffer(doc));
  return out;
}

async function main() {
  ensureDir(outputDir);
  const generated = [];

  for (const appendix of appendices) {
    const doc = makeDocument(appendix.meta, docChildren(appendix, true));
    generated.push(await writeDocx(appendix.meta.file, doc));
  }

  const masterChildren = [
    ...titlePage({ code: "Master Appendices", title: "KODUS GPD Appendix Package" }),
    ...toc(),
  ];
  appendices.forEach((appendix, index) => {
    if (index > 0) masterChildren.push(new Paragraph({ children: [new PageBreak()] }));
    masterChildren.push(...appendix.body);
  });
  const masterDoc = makeDocument({ code: "Master Appendices", title: "KODUS GPD Appendix Package" }, masterChildren);
  generated.push(await writeDocx("KODUS_GPD_APPENDICES_MASTER.docx", masterDoc));

  console.log(`Generated ${generated.length} DOCX files in ${outputDir}`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
