#!/usr/bin/env python3
from __future__ import annotations

import argparse
import re
from dataclasses import dataclass, field
from pathlib import Path
from typing import Dict, List, Optional


ROOT = Path(__file__).resolve().parents[1]
OUTPUT_PATH = ROOT / "docs" / "KODUS_DATA_DICTIONARY.md"
HTML_OUTPUT_PATH = ROOT / "docs" / "KODUS_DATA_DICTIONARY.html"


TABLE_DESCRIPTIONS: Dict[str, str] = {
    "aatracker": "Legacy action/approval tracker for document handling and routing timelines.",
    "audit_logs": "Application audit trail for user actions and security-relevant activity.",
    "barangay": "Reference list of barangays linked to municipalities.",
    "breakdown": "Legacy partner-beneficiary breakdown records used in reporting views.",
    "contact_messages": "Internal contact or inbox message threads submitted through the app.",
    "contact_replies": "Replies attached to contact message threads.",
    "coreworkforce": "Reference data for core workforce counts and classifications.",
    "crossmatch_jobs": "Crossmatch processing jobs and progress metadata.",
    "crossmatch_results": "Stored results produced by crossmatch job runs.",
    "deduplication_jobs": "Deduplication processing jobs initiated by users.",
    "deduplication_results": "Potential duplicate matches produced by deduplication jobs.",
    "draggable_events": "Simple calendar drag/drop event records.",
    "events": "Primary calendar event records and invitations.",
    "event_schedule_days": "Expanded schedule rows for multi-day or recurring events.",
    "fund_monitoring_entries": "Per-item fund utilization values keyed by fiscal year and object code.",
    "fund_monitoring_items": "Fund monitoring master items for SARO, PAP, and object-code groupings.",
    "fund_monitoring_object_codes": "Reference list of object codes used by fund monitoring.",
    "imp_status": "Implementation status rows for project targets, schedules, and accomplishments.",
    "incoming": "Incoming document tracking records and attached file metadata.",
    "mail_logs": "Mail delivery log entries for app-generated emails.",
    "meb": "Master list of partner-beneficiary or MEB records used across reports and payout views.",
    "message_reads": "Per-user read state for inbox/contact messages.",
    "municipality": "Reference list of municipalities linked to provinces.",
    "outgoing": "Outgoing document tracking records and attached file metadata.",
    "pdos": "Reference list for provinces, districts, or office groupings used by the app.",
    "program_activity_metadata": "Detailed location-based implementation and activity metadata for program monitoring.",
    "project_lawa_binhi_targets": "Annual target configuration for LAWA and BINHI project metrics.",
    "project_variable_config": "Configurable fiscal-year project variables such as rates, days, and text settings.",
    "provinces": "Reference list of provinces.",
    "trackdata": "Legacy operational tracking dataset with detailed beneficiary and project attributes.",
    "users": "KODUS user accounts, authentication state, security settings, and profile metadata.",
    "_merge_conflicts": "Utility table used to store merge-conflict artifacts during database consolidation.",
}


@dataclass
class Column:
    name: str
    col_type: str
    not_null: bool
    default: Optional[str]
    extras: List[str] = field(default_factory=list)


@dataclass
class IndexDef:
    name: str
    index_type: str
    method: str
    columns: List[str]


@dataclass
class ForeignKeyDef:
    name: str
    columns: List[str]
    referenced_table: str
    referenced_columns: List[str]
    on_delete: str
    on_update: str


@dataclass
class TableDef:
    name: str
    columns: List[Column] = field(default_factory=list)
    indexes: List[IndexDef] = field(default_factory=list)
    foreign_keys: List[ForeignKeyDef] = field(default_factory=list)


def parse_create_tables(sql: str) -> Dict[str, TableDef]:
    tables: Dict[str, TableDef] = {}
    pattern = re.compile(r"CREATE TABLE `(?P<name>[^`]+)` \((?P<body>.*?)\) ENGINE=", re.S)

    for match in pattern.finditer(sql):
        name = match.group("name")
        body = match.group("body")
        table = TableDef(name=name)

        for raw_line in body.splitlines():
            line = raw_line.strip().rstrip(",")
            if not line.startswith("`"):
                continue

            col_match = re.match(r"`(?P<name>[^`]+)`\s+(?P<rest>.+)$", line)
            if not col_match:
                continue

            col_name = col_match.group("name")
            rest = col_match.group("rest")

            if " DEFAULT " in rest:
                type_part, default_part = rest.split(" DEFAULT ", 1)
                default = default_part.strip()
            else:
                type_part = rest
                default = None

            not_null = " NOT NULL" in type_part or rest.endswith(" NOT NULL")
            type_clean = (
                type_part.replace(" NOT NULL", "")
                .replace(" NULL", "")
                .replace(" CHARACTER SET utf8mb4", "")
                .replace(" COLLATE utf8mb4_general_ci", "")
                .strip()
            )

            if "DEFAULT_GENERATED" in type_clean:
                type_clean = type_clean.replace("DEFAULT_GENERATED", "").strip()

            table.columns.append(
                Column(
                    name=col_name,
                    col_type=type_clean,
                    not_null=not_null,
                    default=default,
                )
            )

        tables[name] = table

    return tables


def split_top_level_csv(value: str) -> List[str]:
    parts: List[str] = []
    current: List[str] = []
    depth = 0
    for char in value:
        if char == "(":
            depth += 1
        elif char == ")":
            depth = max(0, depth - 1)
        elif char == "," and depth == 0:
            part = "".join(current).strip()
            if part:
                parts.append(part)
            current = []
            continue
        current.append(char)
    tail = "".join(current).strip()
    if tail:
        parts.append(tail)
    return parts


def parse_alter_tables(sql: str, tables: Dict[str, TableDef]) -> None:
    alter_pattern = re.compile(r"ALTER TABLE `(?P<name>[^`]+)`\s+(?P<body>.*?);", re.S)
    for match in alter_pattern.finditer(sql):
        table_name = match.group("name")
        if table_name not in tables:
            continue

        body = match.group("body").strip()
        statements = split_top_level_csv(body)
        table = tables[table_name]

        for statement in statements:
            normalized = " ".join(statement.split())

            pk_match = re.match(r"ADD PRIMARY KEY \((?P<cols>.+)\)", normalized)
            if pk_match:
                columns = re.findall(r"`([^`]+)`", pk_match.group("cols"))
                table.indexes.append(IndexDef("PRIMARY", "PRIMARY", "BTREE", columns))
                for column in table.columns:
                    if column.name in columns and "Primary Key" not in column.extras:
                        column.extras.append("Primary Key")
                continue

            idx_match = re.match(
                r"ADD (?P<kind>UNIQUE KEY|KEY) `(?P<name>[^`]+)` \((?P<cols>.+)\)",
                normalized,
            )
            if idx_match:
                columns = re.findall(r"`([^`]+)`", idx_match.group("cols"))
                idx_type = "UNIQUE" if idx_match.group("kind") == "UNIQUE KEY" else "NORMAL"
                idx_name = idx_match.group("name")
                table.indexes.append(IndexDef(idx_name, idx_type, "BTREE", columns))
                for column in table.columns:
                    if column.name in columns:
                        column.extras.append(f"Index: {idx_name}")
                continue

            fk_match = re.match(
                r"ADD CONSTRAINT `(?P<name>[^`]+)` FOREIGN KEY \((?P<cols>.+?)\) "
                r"REFERENCES `(?P<ref_table>[^`]+)` \((?P<ref_cols>.+?)\)"
                r"(?: ON DELETE (?P<on_delete>[A-Z ]+))?"
                r"(?: ON UPDATE (?P<on_update>[A-Z ]+))?$",
                normalized,
            )
            if fk_match:
                columns = re.findall(r"`([^`]+)`", fk_match.group("cols"))
                ref_columns = re.findall(r"`([^`]+)`", fk_match.group("ref_cols"))
                on_delete = (fk_match.group("on_delete") or "RESTRICT").strip()
                on_update = (fk_match.group("on_update") or "RESTRICT").strip()
                table.foreign_keys.append(
                    ForeignKeyDef(
                        name=fk_match.group("name"),
                        columns=columns,
                        referenced_table=fk_match.group("ref_table"),
                        referenced_columns=ref_columns,
                        on_delete=on_delete,
                        on_update=on_update,
                    )
                )
                for column in table.columns:
                    if column.name in columns:
                        column.extras.append(
                            f"FK -> {fk_match.group('ref_table')}({', '.join(ref_columns)})"
                        )
                continue

            modify_match = re.match(r"MODIFY `(?P<col>[^`]+)` (?P<rest>.+)", normalized)
            if modify_match and "AUTO_INCREMENT" in modify_match.group("rest"):
                col_name = modify_match.group("col")
                for column in table.columns:
                    if column.name == col_name and "Auto Increment" not in column.extras:
                        column.extras.append("Auto Increment")


def extract_database_name(sql: str) -> str:
    match = re.search(r"--\s*Database:\s*`([^`]+)`", sql)
    return match.group(1) if match else "kodus_db"


def markdown_table(headers: List[str], rows: List[List[str]]) -> str:
    lines = ["| " + " | ".join(headers) + " |", "| " + " | ".join(["---"] * len(headers)) + " |"]
    for row in rows:
        safe = [value.replace("\n", "<br>").replace("|", "\\|") for value in row]
        lines.append("| " + " | ".join(safe) + " |")
    return "\n".join(lines)


def build_document(database_name: str, tables: Dict[str, TableDef], source_sql: Path) -> str:
    ordered_tables = list(tables.values())
    lines: List[str] = []

    lines.append(f"# {database_name}")
    lines.append("")
    lines.append("Data Dictionary")
    lines.append("")
    lines.append("- Server: localhost")
    lines.append("- Database engine: MySQL")
    lines.append(f"- Source schema: `{source_sql}`")
    lines.append("- Generated by: `scripts/generate_data_dictionary.py`")
    lines.append("")
    lines.append("## 1. Introduction")
    lines.append("")
    lines.append(
        "This data dictionary documents the current KODUS database schema and follows the same practical structure "
        "as the provided sample: an overview of the database, followed by per-table definitions of fields, indexes, "
        "and foreign keys."
    )
    lines.append("")
    lines.append(
        "KODUS stands for KliMalasakit Operational Data Unified System. Based on the application code and existing "
        "project documentation, the database supports user management, MEB beneficiary records, incoming and outgoing "
        "document tracking, internal messaging, project implementation monitoring, fund monitoring, crossmatch, and "
        "deduplication utilities."
    )
    lines.append("")
    lines.append("## 2. Database Summary")
    lines.append("")
    lines.append(f"- Database: `{database_name}`")
    lines.append(f"- Tables documented: `{len(ordered_tables)}`")
    lines.append("")
    lines.append("### 2.1 Table Inventory")
    lines.append("")

    inventory_rows: List[List[str]] = []
    for pos, table in enumerate(ordered_tables, start=1):
        inventory_rows.append(
            [
                str(pos),
                table.name,
                str(len(table.columns)),
                str(len(table.indexes)),
                str(len(table.foreign_keys)),
                TABLE_DESCRIPTIONS.get(table.name, "Application table."),
            ]
        )
    lines.append(markdown_table(["No.", "Table", "Fields", "Indexes", "Foreign Keys", "Purpose"], inventory_rows))

    for pos, table in enumerate(ordered_tables, start=1):
        lines.append("")
        lines.append(f"## 3.{pos} Table: `{table.name}`")
        lines.append("")
        lines.append(TABLE_DESCRIPTIONS.get(table.name, "Application table."))
        lines.append("")
        lines.append("### Fields")
        lines.append("")

        field_rows: List[List[str]] = []
        for column_pos, column in enumerate(table.columns, start=1):
            extras = ", ".join(dict.fromkeys(column.extras))
            field_rows.append(
                [
                    str(column_pos),
                    column.name,
                    column.col_type,
                    "Yes" if column.not_null else "No",
                    column.default or "",
                    extras,
                ]
            )
        lines.append(markdown_table(["Pos", "Name", "Type", "Not Null", "Default", "Others"], field_rows))

        lines.append("")
        lines.append("### Indexes")
        lines.append("")
        if table.indexes:
            index_rows = [
                [index.name, index.index_type, index.method, ", ".join(index.columns)]
                for index in table.indexes
            ]
            lines.append(markdown_table(["Name", "Type", "Method", "Fields"], index_rows))
        else:
            lines.append("_No secondary or primary indexes parsed for this table._")

        lines.append("")
        lines.append("### Foreign Keys")
        lines.append("")
        if table.foreign_keys:
            fk_rows = [
                [
                    fk.name,
                    ", ".join(fk.columns),
                    fk.referenced_table,
                    ", ".join(fk.referenced_columns),
                    fk.on_delete,
                    fk.on_update,
                ]
                for fk in table.foreign_keys
            ]
            lines.append(
                markdown_table(
                    ["Name", "Fields", "Referenced Table", "Referenced Fields", "On Delete", "On Update"],
                    fk_rows,
                )
            )
        else:
            lines.append("_No foreign keys defined._")

    lines.append("")
    lines.append("## 4. Notes")
    lines.append("")
    lines.append("- The dictionary is generated from the SQL dump currently stored in the repository.")
    lines.append("- Some tables appear to be legacy or utility tables but are included for completeness.")
    lines.append("- Field business meanings are inferred only at the table level unless directly obvious from the schema.")
    lines.append("")

    return "\n".join(lines)


def html_escape(value: str) -> str:
    return (
        value.replace("&", "&amp;")
        .replace("<", "&lt;")
        .replace(">", "&gt;")
        .replace('"', "&quot;")
    )


def build_html_document(database_name: str, tables: Dict[str, TableDef], source_sql: Path) -> str:
    ordered_tables = list(tables.values())
    parts: List[str] = []

    def add(text: str) -> None:
        parts.append(text)

    add("<!doctype html>")
    add('<html lang="en">')
    add("<head>")
    add('<meta charset="utf-8">')
    add(f"<title>{html_escape(database_name)} Data Dictionary</title>")
    add(
        """
<style>
body {
  font-family: "Segoe UI", Tahoma, sans-serif;
  margin: 32px;
  color: #1f2937;
  line-height: 1.45;
}
h1, h2, h3, h4 { color: #111827; margin-top: 1.4em; }
h1 { margin-top: 0; }
.meta { margin: 0 0 18px 0; padding: 0; list-style: none; }
.meta li { margin: 4px 0; }
table {
  width: 100%;
  border-collapse: collapse;
  margin: 12px 0 24px 0;
  font-size: 13px;
}
th, td {
  border: 1px solid #d1d5db;
  padding: 8px 10px;
  vertical-align: top;
  text-align: left;
}
th {
  background: #f3f4f6;
}
.muted {
  color: #6b7280;
  font-style: italic;
}
@media print {
  body { margin: 18px; }
  h2 { page-break-before: always; }
  h2:first-of-type { page-break-before: auto; }
}
</style>
        """.strip()
    )
    add("</head>")
    add("<body>")
    add(f"<h1>{html_escape(database_name)}</h1>")
    add("<p><strong>Data Dictionary</strong></p>")
    add('<ul class="meta">')
    add("<li><strong>Server:</strong> localhost</li>")
    add("<li><strong>Database engine:</strong> MySQL</li>")
    add(f"<li><strong>Source schema:</strong> <code>{html_escape(str(source_sql))}</code></li>")
    add("<li><strong>Generated by:</strong> <code>scripts/generate_data_dictionary.py</code></li>")
    add("</ul>")
    add("<h2>1. Introduction</h2>")
    add(
        "<p>This data dictionary documents the current KODUS database schema and follows the same practical structure "
        "as the provided sample: an overview of the database, followed by per-table definitions of fields, indexes, "
        "and foreign keys.</p>"
    )
    add(
        "<p>KODUS stands for KliMalasakit Operational Data Unified System. Based on the application code and existing "
        "project documentation, the database supports user management, MEB beneficiary records, incoming and outgoing "
        "document tracking, internal messaging, project implementation monitoring, fund monitoring, crossmatch, and "
        "deduplication utilities.</p>"
    )
    add("<h2>2. Database Summary</h2>")
    add(f"<p><strong>Database:</strong> <code>{html_escape(database_name)}</code><br>")
    add(f"<strong>Tables documented:</strong> <code>{len(ordered_tables)}</code></p>")
    add("<h3>2.1 Table Inventory</h3>")
    add("<table>")
    add("<thead><tr><th>No.</th><th>Table</th><th>Fields</th><th>Indexes</th><th>Foreign Keys</th><th>Purpose</th></tr></thead>")
    add("<tbody>")
    for pos, table in enumerate(ordered_tables, start=1):
        add(
            "<tr>"
            f"<td>{pos}</td>"
            f"<td><code>{html_escape(table.name)}</code></td>"
            f"<td>{len(table.columns)}</td>"
            f"<td>{len(table.indexes)}</td>"
            f"<td>{len(table.foreign_keys)}</td>"
            f"<td>{html_escape(TABLE_DESCRIPTIONS.get(table.name, 'Application table.'))}</td>"
            "</tr>"
        )
    add("</tbody></table>")

    for pos, table in enumerate(ordered_tables, start=1):
        add(f"<h2>3.{pos} Table: <code>{html_escape(table.name)}</code></h2>")
        add(f"<p>{html_escape(TABLE_DESCRIPTIONS.get(table.name, 'Application table.'))}</p>")
        add("<h3>Fields</h3>")
        add("<table>")
        add("<thead><tr><th>Pos</th><th>Name</th><th>Type</th><th>Not Null</th><th>Default</th><th>Others</th></tr></thead><tbody>")
        for column_pos, column in enumerate(table.columns, start=1):
            extras = ", ".join(dict.fromkeys(column.extras))
            add(
                "<tr>"
                f"<td>{column_pos}</td>"
                f"<td><code>{html_escape(column.name)}</code></td>"
                f"<td><code>{html_escape(column.col_type)}</code></td>"
                f"<td>{'Yes' if column.not_null else 'No'}</td>"
                f"<td><code>{html_escape(column.default or '')}</code></td>"
                f"<td>{html_escape(extras)}</td>"
                "</tr>"
            )
        add("</tbody></table>")

        add("<h3>Indexes</h3>")
        if table.indexes:
            add("<table>")
            add("<thead><tr><th>Name</th><th>Type</th><th>Method</th><th>Fields</th></tr></thead><tbody>")
            for index in table.indexes:
                add(
                    "<tr>"
                    f"<td><code>{html_escape(index.name)}</code></td>"
                    f"<td>{html_escape(index.index_type)}</td>"
                    f"<td>{html_escape(index.method)}</td>"
                    f"<td>{html_escape(', '.join(index.columns))}</td>"
                    "</tr>"
                )
            add("</tbody></table>")
        else:
            add('<p class="muted">No secondary or primary indexes parsed for this table.</p>')

        add("<h3>Foreign Keys</h3>")
        if table.foreign_keys:
            add("<table>")
            add(
                "<thead><tr><th>Name</th><th>Fields</th><th>Referenced Table</th><th>Referenced Fields</th><th>On Delete</th><th>On Update</th></tr></thead><tbody>"
            )
            for fk in table.foreign_keys:
                add(
                    "<tr>"
                    f"<td><code>{html_escape(fk.name)}</code></td>"
                    f"<td>{html_escape(', '.join(fk.columns))}</td>"
                    f"<td><code>{html_escape(fk.referenced_table)}</code></td>"
                    f"<td>{html_escape(', '.join(fk.referenced_columns))}</td>"
                    f"<td>{html_escape(fk.on_delete)}</td>"
                    f"<td>{html_escape(fk.on_update)}</td>"
                    "</tr>"
                )
            add("</tbody></table>")
        else:
            add('<p class="muted">No foreign keys defined.</p>')

    add("<h2>4. Notes</h2>")
    add("<ul>")
    add("<li>The dictionary is generated from the SQL dump currently stored in the repository.</li>")
    add("<li>Some tables appear to be legacy or utility tables but are included for completeness.</li>")
    add("<li>Field business meanings are inferred only at the table level unless directly obvious from the schema.</li>")
    add("</ul>")
    add("</body></html>")
    return "\n".join(parts)


def main() -> None:
    parser = argparse.ArgumentParser(description="Generate a KODUS data dictionary from a SQL dump.")
    parser.add_argument(
        "sql_path",
        nargs="?",
        default=str(ROOT / "sql" / "kodus_db_april_05_2026.sql"),
        help="Path to the SQL dump file to parse.",
    )
    args = parser.parse_args()

    sql_path = Path(args.sql_path).expanduser().resolve()
    sql = sql_path.read_text(encoding="utf-8", errors="ignore")
    database_name = extract_database_name(sql)
    tables = parse_create_tables(sql)
    parse_alter_tables(sql, tables)
    document = build_document(database_name, tables, sql_path)
    html_document = build_html_document(database_name, tables, sql_path)
    OUTPUT_PATH.write_text(document, encoding="utf-8")
    HTML_OUTPUT_PATH.write_text(html_document, encoding="utf-8")
    print(f"Wrote {OUTPUT_PATH}")
    print(f"Wrote {HTML_OUTPUT_PATH}")


if __name__ == "__main__":
    main()
