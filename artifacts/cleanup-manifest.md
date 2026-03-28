# KODUS Cleanup Manifest

Generated on 2026-03-10 from repo inspection only.

This file does not delete anything. It is a review aid to help separate likely dead code, generated/runtime artifacts, and files that should stay.

## Safe To Delete Now

These appear unused by the live app based on route/includes/navigation scans, or are clearly backup/demo copies.

- `C:\laragon\www\kodus\select_year_backup.php`
  - Unreferenced backup of the fiscal year selector.
- `C:\laragon\www\kodus\send_contact_backup.php`
  - Unreferenced backup copy.
- `C:\laragon\www\kodus\send-reset-link - backup.php`
  - Unreferenced backup copy.
- `C:\laragon\www\kodus\pages\calendar_backup.php`
  - Unreferenced backup copy of the calendar page.
- `C:\laragon\www\kodus\pages\data-tracking-meb_backup.php`
  - Unreferenced backup/demo-like older MEB page.
- `C:\laragon\www\kodus\pages\payout_backup.php`
  - Unreferenced older payout page.
- `C:\laragon\www\kodus\pages\fsdfsadfdsf.php`
  - No references found; looks like a small stray AJAX/session helper.
- `C:\laragon\www\kodus\pages\doc_67b554273e8c91.66334709.pdf`
  - No references found; looks like an accidentally committed generated file.
- `C:\laragon\www\kodus\favicon_backup.ico`
  - No references found; backup asset.
- `C:\laragon\www\kodus\inbox\index2.php`
  - Unreferenced alternate inbox implementation; live app uses `inbox/index.php`.
- `C:\laragon\www\kodus\pages\vendor_backup`
  - Duplicate parked vendor tree; runtime uses `C:\laragon\www\kodus\vendor`.
- `C:\laragon\www\kodus\pages\examples`
  - AdminLTE demo pages; not linked from the real app.
- `C:\laragon\www\kodus\pages\charts`
  - AdminLTE demo pages; not linked from the real app.
- `C:\laragon\www\kodus\pages\forms`
  - AdminLTE demo pages; not linked from the real app.
- `C:\laragon\www\kodus\pages\layout`
  - AdminLTE demo pages; not linked from the real app.
- `C:\laragon\www\kodus\pages\mailbox`
  - AdminLTE demo pages; not linked from the real app.
- `C:\laragon\www\kodus\pages\search`
  - AdminLTE demo pages; not linked from the real app.
- `C:\laragon\www\kodus\pages\tables`
  - AdminLTE demo pages; not linked from the real app.
- `C:\laragon\www\kodus\pages\UI`
  - AdminLTE demo pages; not linked from the real app.
- `C:\laragon\www\kodus\pages\gallery.html`
  - Demo page only referenced by other AdminLTE demo pages.
- `C:\laragon\www\kodus\pages\kanban.html`
  - Demo page only referenced by other AdminLTE demo pages.
- `C:\laragon\www\kodus\pages\widgets.html`
  - Demo page only referenced by other AdminLTE demo pages.

## Archive Outside Repo

These are likely not source code, but they may still have operational or historical value. Prefer moving them to an archive folder or separate storage before deleting.

- `C:\laragon\www\kodus\pages\uploads`
  - User-uploaded and generated document files.
- `C:\laragon\www\kodus\crossmatch\uploads`
  - Uploaded crossmatch input files.
- `C:\laragon\www\kodus\deduplication\uploads`
  - Uploaded deduplication input files.
- `C:\laragon\www\kodus\inbox\uploads`
  - Message and reply attachments.
- `C:\laragon\www\kodus\deduplication\logs`
  - Job logs and launcher logs; useful for audit/troubleshooting but not source.
- `C:\laragon\www\kodus\crossmatch\progress`
  - Runtime progress artifacts for jobs.
- `C:\laragon\www\kodus\pages\debug_log.txt`
  - Debug output file written by `pages/save_document.php`.
- `C:\laragon\www\kodus\.tmp.driveupload`
  - Temporary upload/cache folder, likely runtime-generated.
- `C:\laragon\www\kodus\kodus_db.sql`
  - Database dump; valuable, but not part of active application code.
- `C:\laragon\www\kodus\kodus-key_pair.pem`
  - Sensitive operational key material; do not leave in app repo if not required.
- `C:\laragon\www\kodus\kodus-key_pair.ppk`
  - Sensitive operational key material; do not leave in app repo if not required.
- `C:\laragon\www\kodus\LIBJO, PDI (ECT PAYROLL).xlsm`
  - Appears to be a standalone working document, not app code.
- `C:\laragon\www\kodus\Beneficiaries_Template.xlsx`
  - Root-level copy appears unused; archive if you want to keep the original source file.

## Keep

These are part of the live app based on includes, navigation, route rewrites, or direct feature usage.

- `C:\laragon\www\kodus\index.php`
- `C:\laragon\www\kodus\select_year.php`
- `C:\laragon\www\kodus\login.php`
- `C:\laragon\www\kodus\logout.php`
- `C:\laragon\www\kodus\header.php`
- `C:\laragon\www\kodus\sidenav.php`
- `C:\laragon\www\kodus\config.php`
- `C:\laragon\www\kodus\base_url.php`
- `C:\laragon\www\kodus\home.php`
- `C:\laragon\www\kodus\get_data.php`
- `C:\laragon\www\kodus\contact.php`
- `C:\laragon\www\kodus\send_contact.php`
- `C:\laragon\www\kodus\settings.php`
- `C:\laragon\www\kodus\save_profile_settings.php`
- `C:\laragon\www\kodus\remove_photo.php`
- `C:\laragon\www\kodus\delete_account.php`
- `C:\laragon\www\kodus\disable_2fa.php`
- `C:\laragon\www\kodus\send_2fa_code.php`
- `C:\laragon\www\kodus\verify_2fa_code.php`
- `C:\laragon\www\kodus\verify-2fa.php`
- `C:\laragon\www\kodus\forgot-password.php`
- `C:\laragon\www\kodus\send-reset-link.php`
- `C:\laragon\www\kodus\reset-password.php`
- `C:\laragon\www\kodus\register.php`
- `C:\laragon\www\kodus\update-password.php`
- `C:\laragon\www\kodus\admin`
  - Active user management area referenced by the sidebar.
- `C:\laragon\www\kodus\pages`
  - Keep the live business pages and APIs under this folder.
- `C:\laragon\www\kodus\pages\summary`
  - Active reporting area referenced by the sidebar.
- `C:\laragon\www\kodus\crossmatch`
  - Active tool area referenced by the sidebar.
- `C:\laragon\www\kodus\deduplication`
  - Active tool area referenced by the sidebar.
- `C:\laragon\www\kodus\inbox`
  - Active inbox area referenced by the sidebar.
- `C:\laragon\www\kodus\implementation-status`
  - Active implementation-status area referenced by the sidebar.
- `C:\laragon\www\kodus\vendor`
  - Composer runtime dependencies.
- `C:\laragon\www\kodus\dist`
  - Theme and static runtime assets.
- `C:\laragon\www\kodus\plugins`
  - Frontend runtime dependencies used by the app pages.
- `C:\laragon\www\kodus\.htaccess`
- `C:\laragon\www\kodus\pages\.htaccess`
- `C:\laragon\www\kodus\implementation-status\.htaccess`
- `C:\laragon\www\kodus\crossmatch\.htaccess`
- `C:\laragon\www\kodus\deduplication\.htaccess`

## Review Manually Before Deciding

These are likely dead, broken, or dangerous to expose, but they deserve a quick human decision before removal.

- `C:\laragon\www\kodus\ajax_login.php`
  - No references found. Also appears internally inconsistent because it requires `send_login_notification.php`, which is a full script, not a reusable helper.
- `C:\laragon\www\kodus\send_login_notification.php`
  - No active route references found; appears to be an older standalone login notification flow.
- `C:\laragon\www\kodus\recover-password.php`
  - No references found; likely an AdminLTE-derived leftover superseded by the current forgot/reset flow.
- `C:\laragon\www\kodus\script.php`
  - No references found; contains older login-check logic now replaced by `header.php`.
- `C:\laragon\www\kodus\payout.php`
  - Appears to be an obsolete root-level duplicate; active implementation is `C:\laragon\www\kodus\pages\payout.php`.
- `C:\laragon\www\kodus\restore_users.php`
  - Likely obsolete root-level duplicate; sidebar points to `C:\laragon\www\kodus\admin\restore_users.php`.
- `C:\laragon\www\kodus\restore_user.php`
  - Likely obsolete root-level duplicate of admin functionality.
- `C:\laragon\www\kodus\info.php`
  - Public `phpinfo()` endpoint; almost never appropriate to keep in production.
- `C:\laragon\www\kodus\phpinfo.php`
  - Public `phpinfo()` endpoint; almost never appropriate to keep in production.

## Suggested Cleanup Order

1. Move `Archive Outside Repo` items to a safe backup location.
2. Remove `Safe To Delete Now` items.
3. Disable or remove `Review Manually Before Deciding` items, especially `info.php` and `phpinfo.php`.
4. Re-test the live app areas:
   - Login and year selection
   - Home dashboard
   - MEB pages
   - Reports
   - Calendar
   - Inbox/contact
   - Crossmatch
   - Deduplication
   - Implementation Status
