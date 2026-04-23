# KODUS ERD Summary

## Main changes

- Normalized `project_lawa_binhi_targets` by moving repeated project rows out of delimited text columns into `project_target_entries`.
- Normalized `program_activity_metadata` by moving actual coverage/accomplishment rows out of `'||'`-joined columns into `program_activity_actual_projects`.
- Normalized mailbox recipients by replacing comma-separated `contact_messages.recipient` usage with `contact_message_recipients`.
- Normalized calendar guests by replacing comma-separated `events.guests` usage with `event_guests`.

## Core relationships

- `project_lawa_binhi_targets` 1:N `project_target_entries`
  - One fiscal-year/location target record can have many target project rows.
- `program_activity_metadata` 1:N `program_activity_actual_projects`
  - One fiscal-year/location activity record can have many actual project/accomplishment rows.
- `contact_messages` 1:N `contact_message_recipients`
  - One message can target many recipients.
- `contact_messages` 1:N `contact_replies`
  - One conversation thread can have many replies.
- `contact_messages` N:M `users` through `message_reads`
  - Each user has independent read/trash state per message.
- `events` 1:N `event_guests`
  - One calendar event can invite many guests.
- `users` 1:N `events` via `created_by` and optional `updated_by`
  - Users create and update events.

## Why this is ERD-ready

- Repeating groups are now separate child entities instead of string-packed attributes.
- Relationship intent is explicit through foreign keys instead of implicit through parsing rules.
- Junction tables represent real many-to-many business rules for recipients, message state, and invited guests.
- Parent tables now store record-level facts; child tables store row-level facts.

## App impact

- Program target CRUD now treats `project_target_entries` as the row-level source of truth for planned projects.
- Program activity coverage uses `program_activity_actual_projects` for row-level actual implementation data.
- Inbox visibility, unread counts, message access, and reply access resolve recipients through `contact_message_recipients`.
- Calendar fetch/add/update resolves invited guests through `event_guests` instead of an event-level delimited field.

## Migration note

- The migration script copies legacy packed values into normalized child tables first.
- Legacy columns can then be dropped using the optional cleanup statements in the migration once deployment validation is complete.
