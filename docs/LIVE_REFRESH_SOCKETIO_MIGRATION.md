# KODUS Live Refresh Socket.IO Migration

Last updated: 2026-04-28

## Socket.IO Pattern

KODUS now centralizes realtime browser wiring in `dist/js/kodus-live-refresh.js`.

- Client config is emitted from `header.php` as `window.KODUS_SOCKET_CONFIG`.
- Socket.IO is enabled only when explicit `KODUS_SOCKET_SERVER_URL` or `KODUS_SOCKET_BROADCAST_URL` values are configured; it no longer infers a socket host from the SSO authorize URL.
- The shared client loads `clientScriptUrl`, connects to `serverUrl`, and emits the configured join event, defaulting to `subscribe`.
- Channels follow the ePIRMA-style room pattern, such as `kodus.mailbox`, `kodus.notifications`, `kodus.incoming`, `kodus.outgoing`, and `kodus.meb`.
- Browser listeners use named events from backend broadcasts, such as `mail.changed`, `notifications.changed`, and `incoming.changed`.
- Socket transport is forced to `websocket` so Socket.IO does not silently fall back to HTTP polling.

## Polling Inventory

| File | Previous interval | Endpoint/action | UI/module | Purpose | Socket.IO replacement |
| --- | ---: | --- | --- | --- | --- |
| `dist/js/kodus-live-refresh.js` | Long poll, 20-25s request timeout, immediate reconnect | `live_refresh.php?channels=...` | Shared DataTable/live refresh | Detect changed table snapshots | Socket watcher first; no long-poll for `watchDataTable()` when `socket.channel` is provided |
| `header.php` | 60s | Reload all visible DataTables and dispatch `kodus:partial-refresh` | Global pages/dashboard/tracking/admin | Broad page refresh sweep | Removed; page-specific Socket.IO handlers now own updates |
| `sidenav.php` | 15s | `messenger/get_notification_feed.php`, `notifications/get_feed.php` | Topbar mail/app notification dropdowns and badges | Update unread counts/feed | `kodus.mailbox` `mail.changed`; `kodus.notifications` `notifications.changed` |
| `inbox/index.php` | 5s | `get_mailbox_state.php`, then `fetch_messages.php`, `get_thread.php`, `get_typing_state.php` | Messenger/inbox | Refresh message list, active thread, typing indicator, unread count | `kodus.mailbox` `mail.changed` |
| `inbox/index2.php` | 30s | `get_unread_count.php`, `fetch_messages.php` | Legacy inbox view | Refresh unread badge and list | `kodus.mailbox` `mail.changed` |
| `pages/data-tracking-in.php` | Shared long poll | `live_refresh.php` channel `incoming_table` | Incoming tracking table | Reload DataTable on incoming changes | `kodus.incoming` `incoming.changed` |
| `pages/data-tracking-out.php` | Shared long poll | `live_refresh.php` channel `outgoing_table` | Outgoing tracking table | Reload DataTable on outgoing changes | `kodus.outgoing` `outgoing.changed` |
| `pages/data-tracking-meb.php` | Shared long poll | `live_refresh.php` channel `meb_table` | MEB table | Reload DataTable on MEB changes | `kodus.meb` `meb.changed` |
| `pages/data-tracking-meb-validation.php` | Shared long poll | `live_refresh.php` channel `meb_validation_table` | MEB validation table | Reload DataTable on validation/target changes | `kodus.meb` `meb.validation.changed` |
| `admin/users_management.php` | Shared long poll fallback | `live_refresh.php` channel `user_status_table` | User online/status table | Reflect online/deactivated/status changes | Recommended `kodus.users` `users.status.changed`; fallback remains |
| `crossmatch/index.php` | Shared long poll fallback | `live_refresh.php` channel `crossmatch_recent_table` | Recent crossmatch jobs | Detect completed/recent jobs | Recommended `kodus.crossmatch` `crossmatch.jobs.changed`; fallback remains |
| `deduplication/index.php` | Shared long poll fallback | `live_refresh.php` channel `deduplication_recent_table` | Recent deduplication jobs | Detect completed/recent jobs | Recommended `kodus.deduplication` `deduplication.jobs.changed`; fallback remains |
| `header.php` | 5s fallback only when Socket.IO is unavailable | `role-change-status`, `get_maintenance_state` | Session safety modals/banners | Enforce role/account/maintenance state | `kodus.session` `role.changed`, `maintenance.changed` |
| `crossmatch/index.php`, `crossmatch/start.php` | Job progress loop | `progress_status.php?job=...` | Active crossmatch run | Show running job progress | Recommended `kodus.crossmatch.job.{id}` `crossmatch.job.progress`; fallback remains |
| `deduplication/index.php`, `deduplication/progress_status.php` | Job progress loop | `status_api.php?job=...` | Active deduplication run | Show running job progress | Recommended `kodus.deduplication.job.{id}` `deduplication.job.progress`; fallback remains |
| `pages/data-tracking-meb.php` | Import/profile progress loops | import/profile progress endpoints | MEB import/profile tools | Show active batch progress | Recommended `kodus.meb.import.{id}` `meb.import.progress`; fallback remains |
| `mebis-lgu-template/index.php`, `mebis-consolidator/index.php` | Job progress loops | local progress endpoints | MEBIS template/consolidator jobs | Show active generation progress | Recommended `kodus.mebis.job.{id}` `mebis.job.progress`; fallback remains |
| `pages/fund-monitoring.php` | 30s | live status endpoint | Fund monitoring | Detect fund monitoring changes | Recommended `kodus.fund-monitoring` `fund-monitoring.changed`; fallback remains |

## Replaced Code

- Removed global page refresh polling from `header.php`.
- Removed topbar notification/message interval polling from `sidenav.php`.
- Removed mailbox background interval polling from `inbox/index.php`.
- Removed legacy inbox interval polling from `inbox/index2.php`.
- Changed `dist/js/kodus-live-refresh.js` so `watchDataTable()` does not start long-polling when a Socket.IO channel is supplied.
- Mirrored the shared realtime changes into the duplicated `kodus/` tree.

## Remaining Fallbacks

These are intentionally left because they either protect session safety or track active background job progress that does not yet broadcast complete Socket.IO events.

- `header.php`: role-change and maintenance safety checks every 5s only when the Socket.IO bridge is unavailable or cannot initialize.
- `admin/users_management.php`: user status table fallback through `live_refresh.php` until user status broadcasts exist.
- `crossmatch/*` and `deduplication/*`: active job progress polling until job progress broadcasts exist.
- `pages/data-tracking-meb.php`, `mebis-lgu-template/*`, and `mebis-consolidator/*`: active import/generation progress polling until job progress broadcasts exist.
- `pages/fund-monitoring.php`: 30s fund monitoring fallback until `fund-monitoring.changed` is emitted.
- UI-only timers such as toast dismissal, long press detection, typing heartbeat, countdowns, page-loader fallbacks, and AdminLTE internals are not live-refresh polling and were left intact.

## Event Map

| Channel | Event | Affected UI |
| --- | --- | --- |
| `kodus.mailbox` | `mail.changed` | Topbar unread feed, mailbox list, active thread, typing indicator |
| `kodus.notifications` | `notifications.changed` | Topbar app notification feed and badge |
| `kodus.incoming` | `incoming.changed` | Incoming DataTable |
| `kodus.outgoing` | `outgoing.changed` | Outgoing DataTable |
| `kodus.meb` | `meb.changed` | MEB DataTable |
| `kodus.meb` | `meb.validation.changed` | MEB validation DataTable |
| `kodus.users` | `users.status.changed` | Recommended future user status event |
| `kodus.crossmatch` | `crossmatch.jobs.changed` | Recommended future recent crossmatch event |
| `kodus.deduplication` | `deduplication.jobs.changed` | Recommended future recent deduplication event |
| `kodus.session` | `role.changed`, `maintenance.changed` | Session safety modals/banners |
| `kodus.fund-monitoring` | `fund-monitoring.changed` | Recommended future fund monitoring event |

## Backend Emit Coverage

Confirmed backend events for the Socket.IO migration:

| Event | PHP files/actions that emit it |
| --- | --- |
| `kodus.mailbox` / `mail.changed` | `send_contact.php` creates a new contact message; `password_policy_helpers.php` creates password-policy mailbox notices; `inbox/send_reply.php` creates replies; `inbox/edit_reply.php` edits replies; `inbox/delete_reply.php` deletes replies for everyone; `inbox/delete_message.php` archives/restores/deletes conversations; `inbox/bulk_actions.php` bulk marks/archives/restores/deletes; `inbox/mark_read.php` marks a conversation read; `inbox/toggle_reaction.php` changes reactions; `inbox/update_typing_status.php` updates typing state. |
| `kodus.notifications` / `notifications.changed` | `app_notification_helpers.php` creates notifications; `notifications/mark_read.php` marks selected/all notifications read. |
| `kodus.incoming` / `incoming.changed` | `pages/track_incoming.php` creates incoming records; `pages/update_data.php` updates incoming records; `pages/forward_document.php` marks incoming records forwarded. |
| `kodus.outgoing` / `outgoing.changed` | `pages/track_outgoing.php` creates outgoing records; `pages/update_data_out.php` updates outgoing records; `pages/forward_document.php` creates outgoing records from forwarded incoming documents. |
| `kodus.meb` / `meb.changed` | `pages/import.php` imports MEB rows; `pages/update.php` edits MEB rows; `pages/bulk_action.php` deletes selected MEB rows; `pages/delete_batch.php` deletes imported MEB batches. |
| `kodus.meb` / `meb.validation.changed` | `pages/import.php` imports MEB rows; `pages/update.php` edits MEB rows; `pages/update_validation_status.php` updates validation status; `pages/bulk_action.php` deletes selected MEB rows; `pages/delete_batch.php` deletes imported MEB batches; `implementation-status/save-project-target.php` saves baseline targets; `implementation-status/delete-project-target.php` deletes baseline targets; `implementation-status/import-project-targets.php` imports baseline targets. |

Missing backend events not added in this pass:

| Area | Missing event | Exact place to add |
| --- | --- | --- |
| User status table | `kodus.users` / `users.status.changed` | Emit from login/logout/session activity and admin user activation/deactivation/role-change endpoints before removing the `admin/users_management.php` fallback. |
| Crossmatch recent jobs | `kodus.crossmatch` / `crossmatch.jobs.changed` and job-specific `crossmatch.job.progress` | Emit from crossmatch job start/progress/completion code before removing crossmatch progress/recent-job fallbacks. |
| Deduplication recent jobs | `kodus.deduplication` / `deduplication.jobs.changed` and job-specific `deduplication.job.progress` | Emit from deduplication job start/progress/completion code before removing deduplication progress/recent-job fallbacks. |
| Session safety | None for role/deactivation and maintenance settings | `admin/change_user_type.php`, `admin/deactivate_user.php`, and `admin/save_maintenance_settings.php` now emit `kodus.session` events; keep endpoint fallback for socket outages. |
| Fund monitoring | `kodus.fund-monitoring` / `fund-monitoring.changed` | Emit from fund monitoring create/update/delete endpoints before removing `pages/fund-monitoring.php` fallback. |

## Messenger Behavior Notes

- Background 5s mailbox polling was removed.
- Typing updates now refresh only the typing indicator when the event applies to the open thread.
- Active conversation refresh preserves scroll position unless the user is already near the bottom.
- Sending a reply scrolls the selected thread to the bottom after the server-rendered thread reloads.
- Reaction picker state is preserved across socket-triggered conversation refreshes.

## Testing Checklist

- Open DevTools Network and confirm no repeating `live_refresh.php` requests on pages with Socket.IO table channels.
- Confirm no repeating `get_mailbox_state.php`, `fetch_messages.php`, `messenger/get_notification_feed.php`, or `notifications/get_feed.php` calls without a user action or socket event.
- Confirm Socket.IO connects to `KODUS_SOCKET_CONFIG.serverUrl` with no CSP or mixed-content errors.
- Update/send an inbox message and confirm topbar badge, list preview, and selected thread update.
- While reading older messages, receive a reply and confirm the thread does not jump unless it was already near the bottom.
- Open a reaction picker, receive a mailbox event, and confirm the picker remains open.
- Change incoming/outgoing/MEB data and confirm only the relevant DataTable reloads.
- Verify documented fallbacks still work for maintenance/role-change, active crossmatch/deduplication jobs, user status, and fund monitoring.

## Deployment Verification

On the live server, verify these before considering polling fully retired:

- Configure `KODUS_SOCKET_ENABLED=true`, `KODUS_SOCKET_SERVER_URL`, `KODUS_SOCKET_BROADCAST_URL`, `KODUS_SOCKET_CLIENT_SCRIPT_URL`, and `KODUS_SOCKET_BEARER_TOKEN` for the target live or staging Socket.IO service.
- In the page source, confirm `window.KODUS_SOCKET_CONFIG.enabled` is `true`, `serverUrl` points to the live Socket.IO origin, `clientScriptUrl` points to the matching `/socket.io/socket.io.js`, and `joinEvent` is `subscribe`.
- In DevTools Security/Console, confirm the CSP allows only the configured Socket.IO script origin and exact `ws://` or `wss://` Socket.IO origin; there should be no CSP errors for `socket.io.js` or the WebSocket upgrade.
- In DevTools Network, filter by `socket.io` and confirm one WebSocket connection is open per browser tab, with no repeating HTTP polling transport requests.
- Trigger one action for each production event: send/reply/react/read a mailbox message, create/read an app notification, create/update/forward incoming/outgoing records, import/edit/delete MEB records, and save/delete/import program targets.
- For each trigger, confirm only the relevant UI refreshes: mailbox/topbar, notifications, incoming table, outgoing table, MEB table, or MEB validation table.
- Keep an inbox thread open with a reaction picker visible, receive a message/reaction event, and confirm the picker stays open and scroll position is preserved unless the thread was already near the bottom.
- Watch the console during reconnect/offline tests; socket errors may log a warning, but pages must remain usable and manual actions must still work.
- Confirm remaining network timers match the documented fallbacks only: role/maintenance safety when Socket.IO is unavailable, user status fallback, job progress fallbacks, MEBIS progress, and fund monitoring.
