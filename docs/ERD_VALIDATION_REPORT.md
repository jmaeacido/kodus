# ERD Validation Report

Validation date: `2026-04-15`

Validated against a real copied database:
- source: `kodus-dev_db`
- copy used for migration check: `kodus_dev_db_erdcheck`

## Migration results

Source profile before migration:
- `project_lawa_binhi_targets`: `107` parent rows
- `project_target_entries`: `3` existing normalized child rows
- legacy target rows with packed `project_names`: `1` parent row
- expected legacy target child rows from packed data: `5`
- `program_activity_metadata`: `87` parent rows
- `program_activity_actual_projects`: `0` existing normalized child rows
- legacy activity rows with packed `coverage_actual_project_names`: `1` parent row
- expected legacy activity child rows from packed data: `3`
- `contact_messages`: `42` rows
- expected legacy mailbox recipient rows from packed data: `42`
- `events`: `17` rows
- expected legacy event guest rows from packed data: `23`

Copy after migration:
- `project_target_entries`: `8` rows
  - `3` pre-existing normalized rows preserved
  - `5` legacy packed rows migrated
- `program_activity_actual_projects`: `3` rows
- `contact_message_recipients`: `42` rows
- `event_guests`: `23` rows

## Integrity checks

All checked child tables had zero parent-orphan violations:
- `project_target_entries -> project_lawa_binhi_targets`
- `program_activity_actual_projects -> program_activity_metadata`
- `contact_message_recipients -> contact_messages`
- `event_guests -> events`

Foreign keys present and resolved:
- `fk_project_target_entry_target`
- `fk_program_activity_actual_project_parent`
- `fk_contact_message_recipient_message`
- `fk_contact_message_recipient_user`
- `fk_event_guest_event`
- `fk_event_guest_user`

## Defensibility assessment

Schema:
- Clean and relational for the refactored areas.
- Parent-child and recipient/guest relationships are explicit and enforceable.
- Junction-style recipient/guest tables are appropriate.

Source data caveat:
- The source DB already contained `3` normalized `project_target_entries` rows before migration.
- One of those rows has a malformed `row_id` value of `0`, which indicates historical bad application binding before the fix.
- This is a data-quality issue in existing rows, not an ERD defect.

Conclusion:
- The ERD is clean, complete for the normalized scope, and technically defensible.
- Production rollout is reasonable after:
  - backing up the live DB,
  - running the updated migration script,
  - optionally cleaning malformed pre-existing child rows such as `project_target_entries.row_id = '0'`.
