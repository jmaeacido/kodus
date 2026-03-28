<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/audit_helpers.php';
require_once __DIR__ . '/app_meta.php';
require_once __DIR__ . '/role_change_helpers.php';

include('base_url.php');
include('config.php');

auth_enforce_session_timeout("{$app_root}logout");
auth_handle_page_access($conn);
auth_mark_user_online($conn);
auth_apply_security_headers();
role_change_ensure_schema($conn);
if (function_exists('audit_log_page_visit')) {
    audit_log_page_visit($conn);
}

$roleChangeState = null;
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $roleChangeState = role_change_get_state($conn, (int) $_SESSION['user_id']);
    if ($roleChangeState && !empty($roleChangeState['expired'])) {
        $logoutReason = ($roleChangeState['reason'] ?? '') === 'deactivated' ? 'deactivated' : 'role_changed';
        header('Location: ' . $app_root . 'logout?reason=' . $logoutReason . '&token=' . rawurlencode(security_get_csrf_token()));
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="<?php echo $base_url;?>kodus/favicon.ico" type="image/x-icon">

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="<?php echo $base_url;?>kodus/fonts.googleapis.com/css/fontfamily.css">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="<?php echo $base_url;?>kodus/plugins/fontawesome-free/css/all.min.css">
    <!-- overlayScrollbars -->
    <link rel="stylesheet" href="<?php echo $base_url;?>kodus/plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="<?php echo $base_url;?>kodus/dist/css/adminlte.min.css">
    <!-- Custom style -->
    <link rel="stylesheet" href="<?php echo $base_url;?>kodus/dist/css/custom.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="<?php echo $base_url;?>kodus/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo $base_url;?>kodus/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo $base_url;?>kodus/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
    <?php include __DIR__ . '/page_loader.php'; ?>
    <script>
      window.KODUS_LIVE_REFRESH_URL = <?php echo json_encode($base_url . 'kodus/live_refresh.php'); ?>;
    </script>
    <script src="<?php echo $base_url; ?>kodus/dist/js/kodus-live-refresh.js"></script>
    <script>
      (function() {
        let stickyHeaderRaf = null;
        let stickyHeaderObserver = null;
        let stickyHeaderHooksBound = false;
        let stickyHeaderJqueryBound = false;
        let stickyHeaderJqueryRetries = 0;

        function syncStickyHeaderOffsets() {
          stickyHeaderRaf = null;

          document.querySelectorAll('.table-container table, .dataTables_scrollHead table').forEach(function(table) {
            if (!table.tHead) {
              return;
            }

            table.classList.add('kodus-sticky-header-table');

            const rows = Array.from(table.tHead.rows);
            let offset = 0;
            const totalRows = rows.length;

            rows.forEach(function(row, rowIndex) {
              const rowHeight = row.getBoundingClientRect().height || row.offsetHeight || 0;

              Array.from(row.cells).forEach(function(cell) {
                cell.style.setProperty('--kodus-sticky-top', offset + 'px');
                cell.style.setProperty('--kodus-sticky-z', String(40 + (totalRows - rowIndex)));
              });

              offset += rowHeight;
            });
          });
        }

        function queueStickyHeaderSync() {
          if (stickyHeaderRaf !== null) {
            cancelAnimationFrame(stickyHeaderRaf);
          }

          stickyHeaderRaf = requestAnimationFrame(syncStickyHeaderOffsets);
        }

        function bindStickyHeaderJqueryHooks() {
          if (stickyHeaderJqueryBound) {
            return;
          }

          if (window.jQuery) {
            window.jQuery(document).on('draw.dt column-sizing.dt responsive-display.dt init.dt', queueStickyHeaderSync);
            stickyHeaderJqueryBound = true;
            return;
          }

          if (stickyHeaderJqueryRetries < 10) {
            stickyHeaderJqueryRetries += 1;
            setTimeout(bindStickyHeaderJqueryHooks, 500);
          }
        }

        function bindStickyHeaderHooks() {
          if (stickyHeaderHooksBound) {
            return;
          }

          stickyHeaderHooksBound = true;
          queueStickyHeaderSync();
          window.addEventListener('load', queueStickyHeaderSync, { passive: true });
          window.addEventListener('resize', queueStickyHeaderSync, { passive: true });
          document.addEventListener('shown.bs.tab', queueStickyHeaderSync);
          document.addEventListener('shown.bs.collapse', queueStickyHeaderSync);
          document.addEventListener('sidebarCollapsed', queueStickyHeaderSync);
          document.addEventListener('collapsed.lte.pushmenu', queueStickyHeaderSync);
          document.addEventListener('shown.lte.pushmenu', queueStickyHeaderSync);

          if (document.body && !stickyHeaderObserver) {
            stickyHeaderObserver = new MutationObserver(queueStickyHeaderSync);
            stickyHeaderObserver.observe(document.body, {
              childList: true,
              subtree: true,
              attributes: true,
              attributeFilter: ['class', 'style']
            });
          }

          bindStickyHeaderJqueryHooks();
        }

        document.addEventListener('DOMContentLoaded', bindStickyHeaderHooks);
      })();
    </script>
    <style>
      :root {
        --kodus-detail-text: #e8eef5;
        --kodus-detail-muted: #aebfd1;
        --kodus-detail-panel: #1a2432;
        --kodus-detail-panel-strong: #202c3d;
        --kodus-detail-border: #314154;
        --kodus-detail-hero-start: #2b579a;
        --kodus-detail-hero-end: #101828;
        --kodus-detail-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
        --kodus-detail-badge-bg: #113d48;
        --kodus-detail-badge-text: #9de8f2;
        --kodus-detail-positive-bg: #163a24;
        --kodus-detail-positive-text: #7CFC9B;
        --kodus-detail-warning-bg: #4a3a08;
        --kodus-detail-warning-text: #FFD86B;
        --kodus-detail-link: #7dc4ff;
      }

      body[data-theme="light"] {
        --kodus-detail-text: #1f2d3d;
        --kodus-detail-muted: #5f7488;
        --kodus-detail-panel: #ffffff;
        --kodus-detail-panel-strong: linear-gradient(180deg, #f8fbff, #ffffff);
        --kodus-detail-border: #d7e4f4;
        --kodus-detail-hero-start: #eef6ff;
        --kodus-detail-hero-end: #ffffff;
        --kodus-detail-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
        --kodus-detail-badge-bg: #e2efff;
        --kodus-detail-badge-text: #0d6efd;
        --kodus-detail-positive-bg: #e3f4e8;
        --kodus-detail-positive-text: #1e7e34;
        --kodus-detail-warning-bg: #fff3cf;
        --kodus-detail-warning-text: #a16800;
        --kodus-detail-link: #0d6efd;
      }

      .swal2-popup.kodus-detail-popup {
        width: min(960px, 92vw);
        padding: 1.35rem;
        border-radius: 22px;
        color: var(--kodus-detail-text);
        background: var(--kodus-detail-hero-end);
        box-shadow: var(--kodus-detail-shadow);
      }

      .swal2-popup.kodus-detail-popup .swal2-title {
        color: var(--kodus-detail-text);
        font-size: 1.35rem;
        font-weight: 700;
      }

      .swal2-popup.kodus-detail-popup .swal2-html-container {
        margin-top: 0.75rem;
        color: var(--kodus-detail-text);
      }

      .swal2-popup.kodus-edit-popup {
        width: min(980px, 94vw);
        padding: 1.35rem;
        border-radius: 22px;
        color: var(--kodus-detail-text);
        background: var(--kodus-detail-hero-end);
        box-shadow: var(--kodus-detail-shadow);
      }

      .swal2-popup.kodus-edit-popup .swal2-title,
      .swal2-popup.kodus-edit-popup .swal2-html-container {
        color: var(--kodus-detail-text);
      }

      .swal2-popup.kodus-edit-popup .swal2-html-container {
        margin-top: 0.75rem;
      }

      .swal2-popup.kodus-swal-hero-ready .swal2-title,
      .swal2-popup.kodus-swal-hero-ready .swal2-icon {
        display: none !important;
      }

      .swal2-popup.kodus-swal-hero-ready .swal2-html-container {
        margin-top: 0.8rem;
      }

      .kodus-swal-hero {
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 4.8rem 1rem 1.05rem;
        margin-bottom: 0.85rem;
        border-radius: 18px;
        border: 1px solid var(--kodus-detail-border);
        background: linear-gradient(135deg, var(--kodus-detail-hero-start), var(--kodus-detail-hero-end));
      }

      .kodus-swal-hero::after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0));
        pointer-events: none;
      }

      .kodus-swal-hero-copy,
      .kodus-swal-hero-badge {
        position: relative;
        z-index: 1;
      }

      .kodus-swal-hero-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        margin-bottom: 0.35rem;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--kodus-detail-muted);
      }

      .kodus-swal-hero-title {
        margin: 0;
        font-size: 1.18rem;
        font-weight: 700;
        line-height: 1.3;
        color: var(--kodus-detail-text);
      }

      .kodus-swal-hero-subtitle {
        margin: 0.3rem 0 0;
        color: var(--kodus-detail-muted);
        line-height: 1.45;
        font-size: 0.9rem;
      }

      .kodus-swal-hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        min-height: 2.2rem;
        padding: 0.45rem 0.8rem;
        border-radius: 999px;
        border: 1px solid var(--kodus-detail-border);
        background: var(--kodus-detail-panel);
        color: var(--kodus-detail-text);
        font-size: 0.8rem;
        font-weight: 700;
        white-space: nowrap;
      }

      @media (max-width: 576px) {
        .kodus-swal-hero {
          padding-right: 1.05rem;
          flex-direction: column;
        }
      }

      .swal2-popup .swal2-close {
        color: var(--kodus-detail-text);
        opacity: 0.78;
        text-shadow: none;
        transition: opacity 0.15s ease-in-out, color 0.15s ease-in-out;
      }

      .swal2-popup .swal2-close:hover,
      .swal2-popup .swal2-close:focus {
        color: var(--kodus-detail-text);
        opacity: 1;
      }

      .swal2-popup.kodus-swal-has-top-actions {
        position: relative;
      }

      .swal2-popup .kodus-swal-top-actions {
        position: absolute;
        top: 1rem;
        right: 1rem;
        z-index: 3;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
      }

      .swal2-popup .kodus-swal-top-action-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.4rem;
        height: 2.4rem;
        padding: 0 0.8rem;
        border: 1px solid var(--kodus-detail-border);
        border-radius: 999px;
        background: var(--kodus-detail-panel-strong);
        color: var(--kodus-detail-text);
        box-shadow: none;
      }

      .swal2-popup .kodus-swal-top-action-button:hover,
      .swal2-popup .kodus-swal-top-action-button:focus {
        color: var(--kodus-detail-text);
        opacity: 1;
      }

      .swal2-popup .kodus-swal-top-action-button i {
        pointer-events: none;
      }

      .swal2-popup.kodus-swal-has-top-actions .swal2-close {
        position: static;
        inset: auto;
        width: 2.4rem;
        height: 2.4rem;
        margin: 0;
        border-radius: 999px;
        font-size: 1.5rem;
      }

      .kodus-edit-shell {
        text-align: left;
        color: var(--kodus-detail-text);
      }

      .kodus-edit-header {
        padding: 1rem 1.1rem;
        margin-bottom: 1rem;
        border-radius: 18px;
        border: 1px solid var(--kodus-detail-border);
        background: linear-gradient(135deg, var(--kodus-detail-hero-start), var(--kodus-detail-hero-end));
      }

      .kodus-edit-header-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: var(--kodus-detail-text);
      }

      .kodus-edit-header-note {
        margin: 0.35rem 0 0;
        color: var(--kodus-detail-muted);
        line-height: 1.45;
      }

      .kodus-edit-section {
        padding: 1rem 1.05rem;
        margin-bottom: 0.95rem;
        border-radius: 16px;
        border: 1px solid var(--kodus-detail-border);
        background: var(--kodus-detail-panel);
      }

      .kodus-edit-section:last-child {
        margin-bottom: 0;
      }

      .kodus-edit-section-title {
        margin: 0 0 0.85rem;
        font-size: 0.88rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--kodus-detail-text);
      }

      .kodus-edit-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 0.85rem 0.95rem;
      }

      .kodus-edit-grid--compact {
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
      }

      .kodus-edit-field {
        margin: 0;
      }

      .kodus-edit-field--full {
        grid-column: 1 / -1;
      }

      .kodus-edit-shell label {
        display: block;
        margin-bottom: 0.38rem;
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--kodus-detail-muted);
      }

      .kodus-edit-shell .form-control,
      .kodus-edit-shell .custom-select,
      .kodus-edit-shell textarea {
        border-radius: 12px;
        border: 1px solid var(--kodus-detail-border);
        background: var(--kodus-detail-panel-strong);
        color: var(--kodus-detail-text);
      }

      .kodus-edit-shell .form-control:focus,
      .kodus-edit-shell .custom-select:focus,
      .kodus-edit-shell textarea:focus {
        border-color: var(--kodus-detail-link);
        box-shadow: 0 0 0 0.18rem rgba(13, 110, 253, 0.18);
      }

      .kodus-edit-shell .form-control[readonly],
      .kodus-edit-shell .form-control:disabled {
        background: rgba(255, 255, 255, 0.04);
        color: var(--kodus-detail-muted);
      }

      body[data-theme="light"] .kodus-edit-shell .form-control[readonly],
      body[data-theme="light"] .kodus-edit-shell .form-control:disabled {
        background: #f5f8fc;
      }

      .kodus-edit-help {
        display: block;
        margin-top: 0.4rem;
        color: var(--kodus-detail-muted);
        font-size: 0.78rem;
        line-height: 1.4;
      }

      .kodus-edit-inline-file {
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: wrap;
      }

      .kodus-edit-check {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.78rem;
        color: #f66;
      }

      @media (max-width: 576px) {
        .swal2-popup.kodus-edit-popup {
          padding: 1.05rem;
        }

        .kodus-edit-grid,
        .kodus-edit-grid--compact {
          grid-template-columns: 1fr;
        }
      }

      .kodus-detail-modal {
        text-align: left;
        color: var(--kodus-detail-text);
      }

      .kodus-detail-hero {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        padding: 1.1rem 1.2rem;
        margin-bottom: 1rem;
        border-radius: 18px;
        border: 1px solid var(--kodus-detail-border);
        background: linear-gradient(135deg, var(--kodus-detail-hero-start), var(--kodus-detail-hero-end));
      }

      .kodus-detail-eyebrow {
        display: block;
        margin-bottom: 0.35rem;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--kodus-detail-muted);
      }

      .kodus-detail-title {
        margin: 0;
        font-size: 1.22rem;
        font-weight: 700;
        line-height: 1.3;
        color: var(--kodus-detail-text);
      }

      .kodus-detail-subtitle {
        margin: 0.3rem 0 0;
        color: var(--kodus-detail-muted);
        line-height: 1.45;
      }

      .kodus-detail-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 2.3rem;
        padding: 0.5rem 0.8rem;
        border-radius: 999px;
        border: 1px solid var(--kodus-detail-border);
        background: var(--kodus-detail-panel);
        color: var(--kodus-detail-text);
        font-size: 0.82rem;
        font-weight: 600;
        white-space: nowrap;
      }

      .kodus-detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.85rem;
        margin-bottom: 1rem;
      }

      .kodus-detail-stat {
        padding: 0.95rem 1rem;
        border-radius: 16px;
        border: 1px solid var(--kodus-detail-border);
        background: var(--kodus-detail-panel-strong);
      }

      .kodus-detail-label {
        display: block;
        margin-bottom: 0.4rem;
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--kodus-detail-muted);
      }

      .kodus-detail-value {
        display: block;
        line-height: 1.5;
        word-break: break-word;
        color: var(--kodus-detail-text);
      }

      .kodus-detail-value--strong {
        font-size: 1.08rem;
        font-weight: 700;
        line-height: 1.3;
      }

      .kodus-detail-value--positive {
        color: var(--kodus-detail-positive-text);
      }

      .kodus-detail-value--warning {
        color: var(--kodus-detail-warning-text);
      }

      .kodus-detail-section {
        padding: 0.95rem 1rem;
        margin-bottom: 0.9rem;
        border-radius: 16px;
        border: 1px solid var(--kodus-detail-border);
        background: var(--kodus-detail-panel);
      }

      .kodus-detail-section:last-child {
        margin-bottom: 0;
      }

      .kodus-detail-section-title {
        margin: 0 0 0.85rem;
        font-size: 0.9rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--kodus-detail-text);
      }

      .kodus-detail-section-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.9rem;
      }

      .kodus-detail-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        background: var(--kodus-detail-badge-bg);
        color: var(--kodus-detail-badge-text);
        font-size: 0.86rem;
        font-weight: 700;
        line-height: 1;
      }

      .kodus-detail-list {
        margin: 0;
        padding-left: 1.1rem;
      }

      .kodus-detail-list li {
        margin-bottom: 0.35rem;
        line-height: 1.45;
      }

      .kodus-detail-empty {
        color: var(--kodus-detail-muted);
        font-style: italic;
      }

      .kodus-detail-link {
        color: var(--kodus-detail-link);
        font-weight: 600;
        text-decoration: none;
      }

      .kodus-detail-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        border: 1px solid var(--kodus-detail-border);
        border-radius: 16px;
        overflow: hidden;
        background: var(--kodus-detail-panel-strong);
      }

      .kodus-detail-table th,
      .kodus-detail-table td {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid var(--kodus-detail-border);
        vertical-align: top;
      }

      .kodus-detail-table tr:last-child th,
      .kodus-detail-table tr:last-child td {
        border-bottom: none;
      }

      .kodus-detail-table th {
        width: 42%;
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--kodus-detail-muted);
        background: var(--kodus-detail-panel);
      }

      .kodus-row-actions {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        flex-wrap: nowrap;
        white-space: nowrap;
      }

      .kodus-row-actions .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
      }

      body[data-theme="light"] .kodus-detail-table th {
        background: #f3f8ff;
      }

      @media (max-width: 576px) {
        .swal2-popup.kodus-detail-popup {
          padding: 1.05rem;
        }

        .kodus-detail-hero {
          flex-direction: column;
        }

        .kodus-detail-table th,
        .kodus-detail-table td {
          display: block;
          width: 100%;
        }

        .kodus-detail-table th {
          border-bottom: none;
          padding-bottom: 0.25rem;
        }

        .kodus-detail-table td {
          padding-top: 0;
        }
      }
    </style>
</head>
<body>

<!-- SweetAlert2 -->
<script src="<?php echo $base_url;?>kodus/cdn.jsdelivr.net/npm/sweetalert2@11/sweetalert2.min.js"></script>
<script src="<?php echo $base_url;?>kodus/code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
window.KODUS_CSRF_TOKEN = <?= json_encode(security_get_csrf_token()) ?>;
if (window.jQuery) {
  $.ajaxSetup({
    headers: {
      'X-CSRF-Token': window.KODUS_CSRF_TOKEN
    },
    cache: false
  });
}
</script>

<?php if (isset($_SESSION['user_id'])): ?>
<script>
let unreadInterval;
let pollingTimer;  // handles unread polling pause
let logoutTimer;   // handles auto-logout
let roleChangePollInterval;
let roleChangeCountdownInterval;
let roleChangeModalOpen = false;
let currentRoleChangeState = <?php echo json_encode($roleChangeState, JSON_UNESCAPED_SLASHES); ?>;

const POLLING_INACTIVITY_LIMIT = 3 * 60 * 1000; // 3 minutes
const LOGOUT_INACTIVITY_LIMIT  = 60 * 60 * 1000; // 1 hour
const ROLE_CHANGE_STATUS_URL = <?php echo json_encode($app_root . 'role-change-status.php'); ?>;

function formatRoleLabel(value) {
  const text = String(value || '').trim();
  return text ? text.charAt(0).toUpperCase() + text.slice(1) : 'User';
}

function renderRoleChangeHtml(state) {
  const seconds = Math.max(0, Number(state?.seconds_remaining || 0));
  if ((state?.reason || 'role_change') === 'deactivated') {
    const customMessage = String(state?.message || 'Your account has been deactivated by an administrator.');
    return `
      <div style="text-align:left;">
        <p style="margin-bottom:1rem;">${customMessage}</p>
        <div style="padding:.85rem 1rem;border-radius:12px;background:rgba(220,53,69,.12);border:1px solid rgba(220,53,69,.28);">
          You will be signed out in <strong id="role-change-countdown">${seconds}</strong> second${seconds === 1 ? '' : 's'}.
        </div>
      </div>
    `;
  }

  return `
    <div style="text-align:left;">
      <p style="margin-bottom:.6rem;">Your account role was updated by an administrator.</p>
      <p style="margin-bottom:.6rem;"><strong>Previous role:</strong> ${formatRoleLabel(state?.old_type)}</p>
      <p style="margin-bottom:1rem;"><strong>New role:</strong> ${formatRoleLabel(state?.new_type)}</p>
      <div style="padding:.85rem 1rem;border-radius:12px;background:rgba(255,193,7,.12);border:1px solid rgba(255,193,7,.28);">
        You will be signed out in <strong id="role-change-countdown">${seconds}</strong> second${seconds === 1 ? '' : 's'} so your new role can take effect.
      </div>
    </div>
  `;
}

function clearRoleChangeTimers() {
  clearInterval(roleChangeCountdownInterval);
  roleChangeCountdownInterval = null;
}

function forceRoleChangeLogout() {
  clearRoleChangeTimers();
  const reason = (currentRoleChangeState?.reason || 'role_change') === 'deactivated' ? 'deactivated' : 'role_changed';
  submitLogout(reason);
}

function openRoleChangeModal(state) {
  currentRoleChangeState = state;

  if (!roleChangeModalOpen) {
    roleChangeModalOpen = true;
    Swal.fire({
      icon: 'warning',
      title: (state?.reason || 'role_change') === 'deactivated' ? 'Account Disabled' : 'Role Updated',
      html: renderRoleChangeHtml(state),
      allowOutsideClick: false,
      allowEscapeKey: false,
      confirmButtonText: 'Log Out Now',
      showCancelButton: false,
      didOpen: () => {
        startRoleChangeCountdown();
      },
      willClose: () => {
        roleChangeModalOpen = false;
      }
    }).then(() => {
      forceRoleChangeLogout();
    });
    return;
  }

  const container = Swal.getHtmlContainer();
  if (container) {
    container.innerHTML = renderRoleChangeHtml(state);
  }
}

function startRoleChangeCountdown() {
  clearRoleChangeTimers();

  roleChangeCountdownInterval = setInterval(() => {
    if (!currentRoleChangeState) {
      clearRoleChangeTimers();
      return;
    }

    currentRoleChangeState.seconds_remaining = Math.max(0, Number(currentRoleChangeState.seconds_remaining || 0) - 1);
    const counter = document.getElementById('role-change-countdown');
    if (counter) {
      counter.textContent = currentRoleChangeState.seconds_remaining;
    }

    if (currentRoleChangeState.seconds_remaining <= 0) {
      forceRoleChangeLogout();
    }
  }, 1000);
}

function handleRoleChangeState(state) {
  if (!state || !state.active) {
    return;
  }

  if (state.expired || Number(state.seconds_remaining || 0) <= 0) {
    forceRoleChangeLogout();
    return;
  }

  openRoleChangeModal(state);
}

function pollRoleChangeState() {
  $.getJSON(ROLE_CHANGE_STATUS_URL, function(data) {
    if (data && data.active) {
      handleRoleChangeState(data);
    }
  });
}

// ----------------------
// UNREAD COUNT POLLING
// ----------------------
function updateUnreadCount() {
  $.getJSON("<?php echo $base_url; ?>kodus/inbox/get_unread_count.php", function(data) {
    let badge = $('#sidebarMailUnreadBadge');
    const mailNavLabel = $('.nav-item a[href$="kodus/inbox/"] p');

    if (data.count > 0) {
      if (badge.length) {
        badge.text(data.count);
      } else {
        mailNavLabel.append(
          `<span class="right badge badge-danger" id="sidebarMailUnreadBadge">${data.count}</span>`
        );
      }
    } else {
      badge.remove();
    }
  });
}

function startUnreadPolling() {
  if (!unreadInterval) {
    updateUnreadCount();
    unreadInterval = setInterval(updateUnreadCount, 30000);
  }
}

function stopUnreadPolling() {
  clearInterval(unreadInterval);
  unreadInterval = null;
}

// ----------------------
// INACTIVITY HANDLING
// ----------------------
function resetTimers() {
  // Reset polling inactivity timer
  clearTimeout(pollingTimer);
  startUnreadPolling();
  pollingTimer = setTimeout(stopUnreadPolling, POLLING_INACTIVITY_LIMIT);

  // Reset logout inactivity timer
  clearTimeout(logoutTimer);
  logoutTimer = setTimeout(() => {
    Swal.fire({
      icon: 'warning',
      title: 'Session Expired',
      text: 'You have been logged out due to inactivity.',
      showConfirmButton: true,
      willClose: () => {
        submitLogout('timeout');
      }
    });
  }, LOGOUT_INACTIVITY_LIMIT);
}

function submitLogout(reason = 'manual') {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = <?php echo json_encode($app_root . 'logout'); ?>;

  const csrfInput = document.createElement('input');
  csrfInput.type = 'hidden';
  csrfInput.name = 'csrf_token';
  csrfInput.value = window.KODUS_CSRF_TOKEN || '';
  form.appendChild(csrfInput);

  const reasonInput = document.createElement('input');
  reasonInput.type = 'hidden';
  reasonInput.name = 'reason';
  reasonInput.value = reason;
  form.appendChild(reasonInput);

  document.body.appendChild(form);
  form.submit();
}

// Track user activity
$(document).on("mousemove keydown click scroll", resetTimers);

// Init on page load
$(document).ready(function() {
  resetTimers();
  if (currentRoleChangeState) {
    handleRoleChangeState(Object.assign({ active: true }, currentRoleChangeState));
  }
  roleChangePollInterval = setInterval(pollRoleChangeState, 5000);
});
</script>
<?php endif; ?>

<!-- Disable Register Button Until Terms Are Checked -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    let checkbox = document.getElementById("agreeTerms");
    let registerBtn = document.querySelector("button[type='submit']");

    if (checkbox && registerBtn) {
        registerBtn.disabled = true; // Initially disable the button

        checkbox.addEventListener("change", function() {
            registerBtn.disabled = !checkbox.checked; // Enable if checked, disable if unchecked
        });
    }
});
</script>

  <!-- Main Footer -->
  <footer class="main-footer fixed-bottom one">
    <!-- To the right -->
    <div class="float-right d-none d-sm-inline">
      Version <?= htmlspecialchars(app_version_label(), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(app_release_label(), ENT_QUOTES, 'UTF-8') ?>
    </div>
    <!-- Default to the left -->
    <strong>KliMalasakit Online Document Updating System</a>.</strong>
  </footer>
</body>
</html>
