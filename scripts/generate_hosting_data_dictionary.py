from __future__ import annotations

import html
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCHEMA_PATH = ROOT / "Hosting_Requirements" / ".kodus_live_schema.json"
OUTPUT_MD = ROOT / "Hosting_Requirements" / "KODUS_Data_Dictionary_Data_Elements.md"
OUTPUT_HTML = ROOT / "Hosting_Requirements" / ".KODUS_Data_Dictionary_Data_Elements.html"
DOCS_OUTPUT_MD = ROOT / "docs" / "KODUS_DATA_DICTIONARY.md"
DOCS_OUTPUT_HTML = ROOT / "docs" / "KODUS_DATA_DICTIONARY.html"

TABLE_DESCRIPTIONS = {
    "aatracker": "Legacy action and approval tracker for document intake and routing.",
    "app_notifications": "Notification feed records shown inside KODUS.",
    "app_notification_reads": "Per-user read state for notification feed records.",
    "app_settings": "Key-value application settings maintained by helper logic.",
    "audit_logs": "Audit trail for security and user actions.",
    "barangay": "Reference list of barangays.",
    "breakdown": "Payout and beneficiary breakdown data.",
    "contact_messages": "Inbox or contact-message threads.",
    "contact_message_recipients": "Normalized recipient list for contact-message threads.",
    "contact_replies": "Replies attached to inbox or contact-message threads.",
    "crossmatch_jobs": "Crossmatch processing jobs.",
    "crossmatch_results": "Crossmatch result rows.",
    "deduplication_jobs": "Deduplication processing jobs.",
    "deduplication_results": "Potential duplicate result rows.",
    "deduplication_template_outputs": "Generated deduplication template history.",
    "draggable_events": "Reusable drag-and-drop calendar labels.",
    "event_schedule_days": "Expanded per-day schedule rows for events.",
    "events": "Primary calendar event records.",
    "event_guests": "Normalized guest or invitee list for calendar events.",
    "fund_monitoring_entries": "Monthly obligations and disbursement entries.",
    "fund_monitoring_items": "Fund monitoring master items.",
    "fund_monitoring_object_codes": "Reference object codes for fund monitoring.",
    "imp_status": "Legacy implementation-status table retained in the live schema.",
    "incoming": "Incoming document tracking records.",
    "mail_logs": "Outbound mail-delivery log entries.",
    "meb": "Master beneficiary or MEB records.",
    "meb_change_history": "Before-and-after history for MEB edits.",
    "mebis_consolidator_outputs": "Generated MEBIS consolidator output history.",
    "mebis_lgu_template_outputs": "Generated MEBIS LGU template output history.",
    "message_reads": "Per-user read and trash state for message threads.",
    "municipality": "Reference list of municipalities.",
    "outgoing": "Outgoing or forwarded document tracking records.",
    "pdos": "Reference PDO list used by the application.",
    "program_activity_metadata": "Implementation monitoring rows for location-level program activity data.",
    "program_activity_actual_projects": "Normalized actual project/accomplishment rows linked to implementation activity metadata.",
    "project_lawa_binhi_targets": "Annual LAWA and BINHI target configuration by location.",
    "project_target_entries": "Normalized per-project target rows linked to annual LAWA and BINHI target records.",
    "project_variable_config": "Fiscal-year project variables, rates, and factor values.",
    "provinces": "Reference list of provinces.",
    "users": "User accounts, authentication state, profile data, and role or 2FA metadata.",
}

SOURCE_MAP = {
    "aatracker": ["pages/save_document.php"],
    "app_notifications": ["app_notification_helpers.php", "notifications/mark_read.php"],
    "app_notification_reads": ["app_notification_helpers.php", "notifications/mark_read.php"],
    "app_settings": ["error_helpers.php", "admin/maintenance.php"],
    "audit_logs": ["audit_helpers.php"],
    "contact_messages": ["send_contact.php", "inbox/index.php"],
    "contact_message_recipients": ["inbox/mailbox_helpers.php", "send_contact.php", "inbox/index.php"],
    "contact_replies": ["inbox/send_reply.php", "inbox/mailbox_helpers.php"],
    "crossmatch_jobs": ["crossmatch/upload_handler.php", "crossmatch/run.php"],
    "crossmatch_results": ["crossmatch/run.php", "crossmatch/results.php"],
    "deduplication_jobs": ["deduplication/upload_handler.php", "deduplication/worker_v2.php"],
    "deduplication_results": ["deduplication/worker_v2.php", "deduplication/results.php"],
    "deduplication_template_outputs": ["deduplication/helpers/generator_history.php"],
    "event_schedule_days": ["pages/event_schedule_helpers.php", "pages/update_event.php"],
    "event_guests": ["pages/sendEventEmails.php", "pages/fetch_events.php"],
    "fund_monitoring_entries": ["fund_monitoring_helpers.php", "pages/save_fund_monitoring.php"],
    "fund_monitoring_items": ["fund_monitoring_helpers.php", "pages/fund-monitoring.php"],
    "fund_monitoring_object_codes": ["fund_monitoring_helpers.php", "pages/save_fund_monitoring.php"],
    "incoming": ["pages/track_incoming.php", "pages/update_data.php", "pages/forward_document.php"],
    "mail_logs": ["notification_helpers.php", "send_contact.php"],
    "meb": ["pages/import.php", "pages/fetch_data.php", "pages/update.php"],
    "meb_change_history": ["meb_change_history_helpers.php", "pages/meb-change-review.php"],
    "message_reads": ["inbox/get_thread.php", "inbox/mark_read.php", "inbox/delete_message.php"],
    "outgoing": ["pages/track_outgoing.php", "pages/update_data_out.php", "pages/forward_document.php"],
    "program_activity_metadata": ["implementation-status/activity_metadata.php", "implementation-status/save-imp-status.php"],
    "program_activity_actual_projects": ["implementation-status/activity_metadata.php", "implementation-status/save-imp-status.php", "implementation-status/fetch-project-location-records.php"],
    "project_lawa_binhi_targets": ["project_targets_helpers.php", "implementation-status/save-project-target.php"],
    "project_target_entries": ["project_targets_helpers.php", "implementation-status/save-project-target.php", "implementation-status/fetch-project-targets.php"],
    "project_variable_config": ["project_variable_helpers.php"],
    "users": ["config.php", "login.php", "register.php", "auth_helpers.php", "sso_helpers.php", "two_factor_helpers.php"],
}


def md_table(headers: list[str], rows: list[list[object]]) -> str:
    lines = [
        "| " + " | ".join(headers) + " |",
        "| " + " | ".join(["---"] * len(headers)) + " |",
    ]
    for row in rows:
        safe = [str(v).replace("|", r"\|").replace("\n", "<br>") for v in row]
        lines.append("| " + " | ".join(safe) + " |")
    return "\n".join(lines)


def html_table(headers: list[str], rows: list[list[object]]) -> str:
    out = ["<table><thead><tr>"]
    for head in headers:
        out.append(f"<th>{html.escape(str(head))}</th>")
    out.append("</tr></thead><tbody>")
    for row in rows:
        out.append("<tr>")
        for value in row:
            out.append(f"<td>{html.escape(str(value)).replace(chr(10), '<br>')}</td>")
        out.append("</tr>")
    out.append("</tbody></table>")
    return "".join(out)


def table_category(table: str) -> str:
    if table in {"barangay", "municipality", "pdos", "provinces"}:
        return "Reference"
    if table in {
        "app_settings",
        "app_notifications",
        "app_notification_reads",
        "message_reads",
        "mail_logs",
        "meb_change_history",
        "deduplication_template_outputs",
        "mebis_consolidator_outputs",
        "mebis_lgu_template_outputs",
    }:
        return "Utility / Audit"
    if table == "imp_status":
        return "Legacy / Transitional"
    return "Operational"


def length_size(col: dict) -> str:
    if col.get("CHARACTER_MAXIMUM_LENGTH"):
        return str(col["CHARACTER_MAXIMUM_LENGTH"])
    if col.get("NUMERIC_PRECISION"):
        scale = col.get("NUMERIC_SCALE")
        return f"{col['NUMERIC_PRECISION']},{scale}" if scale not in (None, "", "0", 0) else str(col["NUMERIC_PRECISION"])
    match = re.search(r"\(([^)]+)\)", col["COLUMN_TYPE"])
    return match.group(1) if match else "N/A"


def describe_column(table: str, name: str) -> str:
    special = {
        ("aatracker", "tracking_number2"): "Generated tracking number assigned after insert.",
        ("aatracker", "bFocal"): "BFocal routing date for the action and approval tracker workflow.",
        ("aatracker", "remarks2"): "Remarks field currently present in the live schema.",
        ("events", "all_day"): "Flag indicating whether the event is all-day.",
        ("contact_message_recipients", "message_id"): "Parent contact message thread identifier.",
        ("contact_message_recipients", "recipient_email"): "Email address of the intended message recipient.",
        ("event_guests", "event_id"): "Parent calendar event identifier.",
        ("event_guests", "guest_email"): "Email address of the invited event guest.",
        ("program_activity_actual_projects", "actual_project_id"): "Stable identifier for the saved actual project/accomplishment row.",
        ("program_activity_actual_projects", "coverage_entry_id"): "Identifier linking back to the coverage entry from the activity form.",
        ("program_activity_actual_projects", "target_project_row_id"): "Identifier linking the actual accomplishment to a target project row when applicable.",
        ("program_activity_actual_projects", "drive_link"): "External documentation or evidence link for the project accomplishment.",
        ("project_target_entries", "row_id"): "Stable row identifier for the target project entry.",
        ("project_target_entries", "target_id"): "Parent LAWA/BINHI target record identifier.",
        ("users", "userType"): "Authorization role such as user, editor, aa, or admin.",
        ("users", "two_fa_secret"): "Authenticator secret used for 2FA when enabled.",
        ("users", "two_fa_recovery_codes"): "Recovery-code payload used for account recovery.",
    }
    if (table, name) in special:
        return special[(table, name)]
    if name == "id":
        return "Primary identifier for the row."
    if name in {"created_at", "updated_at", "deleted_at", "read_at", "trashed_at"}:
        return "Timestamp field used by the workflow."
    if name in {"province", "municipality", "barangay"}:
        return "Location value stored by the record."
    readable = re.sub(r"(?<!^)(?=[A-Z])", " ", name).replace("_", " ").strip().lower()
    return f"Field used to store {readable}; exact business meaning to be confirmed by project owner where not obvious from code."


def example_value(col: dict) -> str:
    name = col["COLUMN_NAME"]
    default = col["COLUMN_DEFAULT"]
    data_type = str(col["DATA_TYPE"]).lower()
    example_map = {
        "id": "1",
        "fiscal_year": "2026",
        "username": "jdoe",
        "email": "user@example.com",
        "theme_preference": "light",
        "userType": "admin",
        "tracking_number": "04-15-26-123",
        "tracking_number2": "04-15-26-123",
        "file_name": "document.pdf",
        "file_type": "application/pdf",
        "file_size": "245760",
        "object_code_name": "Training Expenses",
        "saro_number": "DRRP-CC-2026-CARAGA-16",
        "pap_name": "Shared PAP",
        "variable_key": "daily_wage_rate",
        "variable_label": "Daily Wage Rate",
        "value_type": "number",
        "unit": "PHP/day",
        "category": "system",
        "url": "/pages/calendar.php",
        "icon_class": "fas fa-bell",
        "color_class": "text-warning",
    }
    if default not in (None, "NULL") and "CURRENT_TIMESTAMP" not in str(default):
        return str(default)
    if name in example_map:
        return example_map[name]
    if data_type == "date":
        return "2026-04-15"
    if data_type in {"datetime", "timestamp"}:
        return "2026-04-15 09:30:00"
    if data_type == "time":
        return "08:00:00"
    if data_type in {"int", "tinyint", "bigint", "smallint"}:
        return "1"
    if data_type in {"decimal", "float", "double"}:
        return "0.00"
    return "To be confirmed by project owner"


def render_html(markdown_text: str) -> str:
    lines = markdown_text.splitlines()
    pieces: list[str] = []
    in_list = False
    i = 0

    def fmt(text: str) -> str:
        text = html.escape(text)
        text = re.sub(r"`([^`]+)`", r"<code>\1</code>", text)
        text = re.sub(r"\*\*([^*]+)\*\*", r"<strong>\1</strong>", text)
        return text.replace("  ", "<br>")

    while i < len(lines):
        line = lines[i]
        if line.startswith("| "):
            block = [line]
            i += 1
            while i < len(lines) and lines[i].startswith("| "):
                block.append(lines[i])
                i += 1
            headers = [part.strip() for part in block[0].strip("|").split("|")]
            rows = [[part.strip() for part in row.strip("|").split("|")] for row in block[2:]]
            if in_list:
                pieces.append("</ul>")
                in_list = False
            pieces.append(html_table(headers, rows))
            continue
        if not line.strip():
            if in_list:
                pieces.append("</ul>")
                in_list = False
            pieces.append('<div class="spacer"></div>')
            i += 1
            continue
        if line == "---":
            if in_list:
                pieces.append("</ul>")
                in_list = False
            pieces.append("<hr>")
            i += 1
            continue
        if line.startswith("# "):
            pieces.append(f"<h1>{fmt(line[2:])}</h1>")
        elif line.startswith("## "):
            pieces.append(f"<h2>{fmt(line[3:])}</h2>")
        elif line.startswith("### "):
            pieces.append(f"<h3 class='table-section'>{fmt(line[4:])}</h3>")
        elif line.startswith("- "):
            if not in_list:
                pieces.append("<ul>")
                in_list = True
            pieces.append(f"<li>{fmt(line[2:])}</li>")
        else:
            if in_list:
                pieces.append("</ul>")
                in_list = False
            pieces.append(f"<p>{fmt(line)}</p>")
        i += 1

    if in_list:
        pieces.append("</ul>")

    return f"""<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>KODUS Data Dictionary / Data Elements</title>
<style>
@page {{ size: A4; margin: 18mm 14mm 18mm 14mm; }}
body {{ font-family: Arial, Helvetica, sans-serif; color:#17212b; line-height:1.35; font-size:11px; }}
h1, h2, h3 {{ color:#0f2740; }}
h1 {{ font-size:24px; margin:0 0 12px; }}
h2 {{ font-size:18px; margin:24px 0 8px; page-break-before: always; }}
h2:first-of-type {{ page-break-before: auto; }}
h3 {{ font-size:14px; margin:20px 0 6px; }}
h3.table-section {{ page-break-before: always; }}
ul {{ margin:0 0 0 18px; padding:0; }}
p, li {{ margin:6px 0; }}
hr {{ border:0; border-top:1px solid #9aa7b3; margin:16px 0; }}
table {{ border-collapse:collapse; width:100%; margin:10px 0 18px; table-layout:fixed; }}
th, td {{ border:1px solid #b8c4cf; padding:6px 7px; vertical-align:top; word-wrap:break-word; font-size:10px; }}
th {{ background:#eaf1f7; }}
code {{ font-family: Consolas, monospace; font-size:10px; }}
.spacer {{ height:2px; }}
.footer-note {{ margin-top:24px; font-size:10px; color:#51606f; border-top:1px solid #ccd6df; padding-top:8px; }}
</style>
</head>
<body>
{''.join(pieces)}
<div class="footer-note">Derived from the current KODUS codebase and live configured database; final schema validation is recommended before production hosting.</div>
</body>
</html>"""


if __name__ == "__main__":
    raw = SCHEMA_PATH.read_bytes()
    if raw.startswith((b"\xff\xfe", b"\xfe\xff")):
        schema = json.loads(raw.decode("utf-16"))
    else:
        schema = json.loads(raw.decode("utf-8-sig"))
    tables = list(schema["tables"].keys())

    index_map: dict[str, dict[str, list[dict]]] = {}
    for row in schema["indexes"]:
        index_map.setdefault(row["TABLE_NAME"], {}).setdefault(row["INDEX_NAME"], []).append(row)

    fk_map: dict[str, list[dict]] = {}
    for row in schema["foreign_keys"]:
        fk_map.setdefault(row["TABLE_NAME"], []).append(row)

    text_map = {}
    for path in ROOT.rglob("*.php"):
        parts = set(path.parts)
        if "vendor" in parts or "plugins" in parts or "sql" in parts:
            continue
        text_map[path.relative_to(ROOT).as_posix()] = path.read_text(encoding="utf-8", errors="ignore")

    usage_map = dict(SOURCE_MAP)
    for table in tables:
        if table in usage_map:
            continue
        pattern = re.compile(r"\b" + re.escape(table) + r"\b", re.I)
        usage_map[table] = [rel for rel, text in text_map.items() if pattern.search(text)][:4]

    inventory_rows = []
    for table in tables:
        inventory_rows.append(
            [
                table,
                table_category(table),
                len(schema["tables"][table]["columns"]),
                len(index_map.get(table, {})),
                len(fk_map.get(table, [])),
                TABLE_DESCRIPTIONS.get(table, "To be confirmed by project owner"),
            ]
        )

    excluded_rows = []
    for name in ["holidays", "coreworkforce", "trackdata", "_merge_conflicts"]:
        pattern = re.compile(r"\b" + re.escape(name) + r"\b", re.I)
        hits = [rel for rel, text in text_map.items() if pattern.search(text)]
        excluded_rows.append(
            [name, "Absent from current configured database", ", ".join(f"`{hit}`" for hit in hits[:3]) if hits else "No current PHP usage found"]
        )

    relationship_rows = []
    for table, rows in fk_map.items():
        grouped: dict[str, list[dict]] = {}
        for row in rows:
            grouped.setdefault(row["CONSTRAINT_NAME"], []).append(row)
        for items in grouped.values():
            relationship_rows.append(
                [
                    table,
                    ", ".join(item["COLUMN_NAME"] for item in items),
                    items[0]["REFERENCED_TABLE_NAME"],
                    ", ".join(item["REFERENCED_COLUMN_NAME"] for item in items),
                    items[0]["DELETE_RULE"],
                    items[0]["UPDATE_RULE"],
                ]
            )

    md: list[str] = []
    md += [
        "# KODUS Data Dictionary / Data Elements",
        "",
        "## Title Page",
        "",
        "**Document Title:** KODUS Data Dictionary / Data Elements  ",
        "**System Name:** KODUS  ",
        "**Status:** For Hosting Requirements Submission",
        "",
        "---",
        "",
        "## Table of Contents",
        "",
    ]
    for section in [
        "1. Introduction",
        "2. Purpose of the Document",
        "3. Scope",
        "4. System Overview",
        "5. Database Overview",
        "6. Naming Conventions",
        "7. Table Inventory",
        "8. Detailed Data Dictionary",
        "9. Key Relationships and Constraints",
        "10. Notes on Data Integrity and Validation",
        "11. Assumptions and Items for Confirmation",
        "12. Conclusion",
    ]:
        anchor = section.lower().replace(".", "").replace("/", "").replace(" ", "-")
        md.append(f"- [{section}](#{anchor})")

    md += [
        "",
        "## 1. Introduction",
        "",
        "This document presents a formal data dictionary for the current KODUS web application database as observed from the running application configuration, the live MySQL schema, and the PHP files that create, alter, query, and maintain the database objects. It is intended for hosting requirements submission and focuses only on structures and meanings that can be grounded in the present codebase and configured database.",
        "",
        "## 2. Purpose of the Document",
        "",
        "The purpose of this document is to identify the current database elements used by KODUS, describe the tables and columns that are materially part of the deployed application, summarize keys and relationships, and record schema observations that require project-owner confirmation before production hosting.",
        "",
        "## 3. Scope",
        "",
        f"This document covers the active database named `{schema['database']}` as reached by `config.php`, including tables, columns, data types, indexes, foreign keys, and application-level usage that can be traced in the PHP codebase. Historical SQL dump files were intentionally excluded from the source basis.",
        "",
        "## 4. System Overview",
        "",
        "KODUS is a PHP and MySQL web application that supports user management, login and SSO, two-factor authentication, incoming and outgoing document tracking, beneficiary or MEB data management, inbox messaging, calendar scheduling, fund monitoring, crossmatch and deduplication utilities, and implementation-status monitoring for LAWA and BINHI activities.",
        "",
        "## 5. Database Overview",
        "",
        f"- Active database inspected: `{schema['database']}`",
        "- Database access path: `config.php` using `mysqli` and environment-backed connection settings",
        "- Character set behavior observed in helper-created tables: `utf8mb4`",
        f"- Current tables documented from live schema: `{len(tables)}`",
        "- Schema source basis: `information_schema.TABLES`, `information_schema.COLUMNS`, `information_schema.STATISTICS`, and foreign-key metadata queried from the configured KODUS database",
        "",
        "## 6. Naming Conventions",
        "",
        "- The schema uses a mixed naming style with both `snake_case` and legacy `camelCase` or abbreviated field names.",
        "- Primary keys are commonly stored in an `id` column, but some tables use composite read-state keys and some lookup tables do not declare a primary key.",
        "- Timestamp fields frequently use names such as `created_at`, `updated_at`, `deleted_at`, `read_at`, and `trashed_at`.",
        "- Several modules store lists or structured content in `TEXT` or `LONGTEXT` columns and interpret them in PHP logic rather than through dedicated JSON column types.",
        "",
        "## 7. Table Inventory",
        "",
        md_table(["Table Name", "Category", "Columns", "Indexes", "Foreign Keys", "Purpose / Description"], inventory_rows),
        "",
        "### Absent or Legacy References Not Included in the Detailed Dictionary",
        "",
        md_table(["Table / Object", "Current Status", "Observed Basis"], excluded_rows),
        "",
        "## 8. Detailed Data Dictionary",
        "",
    ]

    for idx, table in enumerate(tables, start=1):
        columns = schema["tables"][table]["columns"]
        indexes = index_map.get(table, {})
        fks = fk_map.get(table, [])
        pk = ", ".join(row["COLUMN_NAME"] for row in indexes.get("PRIMARY", [])) or "To be confirmed by project owner"
        if fks:
            grouped: dict[str, list[dict]] = {}
            for row in fks:
                grouped.setdefault(row["CONSTRAINT_NAME"], []).append(row)
            fk_text = "; ".join(
                f"`{', '.join(item['COLUMN_NAME'] for item in items)}` -> `{items[0]['REFERENCED_TABLE_NAME']}({', '.join(item['REFERENCED_COLUMN_NAME'] for item in items)})`"
                for items in grouped.values()
            )
        else:
            fk_text = "None declared in the current schema."
        md += [
            f"### 8.{idx} `{table}`",
            "",
            f"**Purpose / Description:** {TABLE_DESCRIPTIONS.get(table, 'To be confirmed by project owner')}  ",
            f"**Primary Key:** {pk}  ",
            f"**Foreign Keys:** {fk_text}  ",
            "**Primary Source Basis:** " + (", ".join(f"`{file}`" for file in usage_map.get(table, [])) if usage_map.get(table) else "To be confirmed by project owner"),
            "",
        ]
        rows = []
        for col in columns:
            labels = []
            if col["COLUMN_KEY"] == "PRI":
                labels.append("PK")
            if any(fk["COLUMN_NAME"] == col["COLUMN_NAME"] for fk in fks):
                labels.append("FK")
            for idx_name, idx_rows in indexes.items():
                if idx_name == "PRIMARY":
                    continue
                cols = [row["COLUMN_NAME"] for row in idx_rows]
                if col["COLUMN_NAME"] in cols:
                    labels.append("Unique" if all(row["NON_UNIQUE"] == "0" for row in idx_rows) else "Indexed")
                    break
            rows.append(
                [
                    col["COLUMN_NAME"],
                    col["COLUMN_TYPE"],
                    length_size(col),
                    "Yes" if col["IS_NULLABLE"] == "YES" else "No",
                    col["COLUMN_DEFAULT"] if col["COLUMN_DEFAULT"] is not None else "None",
                    ", ".join(labels) if labels else "none",
                    describe_column(table, col["COLUMN_NAME"]),
                    example_value(col),
                    "Live schema via information_schema; PHP usage in "
                    + (", ".join(f"`{file}`" for file in usage_map.get(table, [])[:3]) if usage_map.get(table) else "project PHP usage files"),
                ]
            )
        md.append(
            md_table(
                [
                    "Column Name",
                    "Data Type",
                    "Length / Size",
                    "Null Allowed",
                    "Default Value",
                    "Key Type",
                    "Description / Business Meaning",
                    "Example Value",
                    "Source Basis",
                ],
                rows,
            )
        )
        md.append("")

    md += [
        "## 9. Key Relationships and Constraints",
        "",
        md_table(["Table", "Foreign-Key Column(s)", "Referenced Table", "Referenced Column(s)", "On Delete", "On Update"], relationship_rows) if relationship_rows else "No declared foreign keys were found in the current schema.",
        "",
        "## 10. Notes on Data Integrity and Validation",
        "",
        "- The application performs runtime schema assurance in multiple helpers, so the live schema is the authoritative basis for this document.",
        "- File-upload modules validate file type and size before writing metadata to tracking tables.",
        "- The inbox module supplements thread and reply tables with per-user read and trash-state tracking.",
        "- Fund monitoring uses unique keys and parent-child detail tables to reduce duplicate monthly entries.",
        "- Several logical relationships are enforced at the PHP level without declared foreign-key constraints.",
        "",
        "## 11. Assumptions and Items for Confirmation",
        "",
        "- The schema source of truth for this document is the live KODUS database reached through `config.php`; SQL dump files were intentionally excluded per instruction.",
        "- The `aatracker` table retains several legacy field names for historical document-routing data; field labels should be confirmed with the process owner before production publication.",
        "- The active `events` schema uses `all_day`, but the older `pages/events.php` endpoint still references `allDay`; this appears to be a legacy path and should be confirmed or retired.",
        "- The file `pages/fetch_ph_holidays.php` writes to a `holidays` table, but that table is not present in the current configured database and is therefore excluded from the detailed dictionary.",
        "- Reference tables such as `barangay` and `municipality` do not currently declare full relational constraints even though the application uses them operationally.",
        "",
        "## 12. Conclusion",
        "",
        "Based on the current configured KODUS database and the present PHP codebase, the system is operating on a live schema composed of user-management, messaging, document-tracking, beneficiary, calendar, fund-monitoring, crossmatch, deduplication, and implementation-monitoring tables. This document is suitable as a hosting-requirements submission artifact, provided the noted inconsistencies and confirmation items are reviewed before final production sign-off.",
        "",
        "Document note: This document was derived from the current KODUS codebase and live configured database and may require final schema validation before production hosting.",
        "",
    ]

    markdown = "\n".join(md)
    OUTPUT_MD.write_text(markdown, encoding="utf-8")
    OUTPUT_HTML.write_text(render_html(markdown), encoding="utf-8")
    DOCS_OUTPUT_MD.write_text(markdown, encoding="utf-8")
    DOCS_OUTPUT_HTML.write_text(render_html(markdown), encoding="utf-8")
    print(OUTPUT_MD)
    print(OUTPUT_HTML)
    print(DOCS_OUTPUT_MD)
    print(DOCS_OUTPUT_HTML)
