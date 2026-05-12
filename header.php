<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/audit_helpers.php';
require_once __DIR__ . '/app_meta.php';
require_once __DIR__ . '/role_change_helpers.php';
require_once __DIR__ . '/socket_helpers.php';

include('base_url.php');
include('config.php');

auth_enforce_session_timeout("{$app_root}logout");
auth_handle_page_access($conn);
auth_enforce_operations_access($conn);
auth_enforce_admin_page_access($conn);
auth_enforce_admin_generator_access($conn);
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

$maintenanceClientState = null;
if (
    isset($_SESSION['user_id']) &&
    ($_SESSION['user_type'] ?? '') !== 'admin'
) {
    $candidateMaintenanceState = kodus_maintenance_state($conn);
    if (($candidateMaintenanceState['phase'] ?? 'inactive') === 'pending') {
        $maintenanceClientState = $candidateMaintenanceState;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="<?php echo $app_root; ?>favicon.ico" type="image/x-icon">

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="<?php echo $app_root; ?>fonts.googleapis.com/css/fontfamily.css">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/fontawesome-free/css/all.min.css">
    <!-- overlayScrollbars -->
    <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/sweetalert2/sweetalert2.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="<?php echo $app_root; ?>dist/css/adminlte.min.css">
    <!-- Custom style -->
    <link rel="stylesheet" href="<?php echo $app_root; ?>dist/css/custom.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
    <?php include __DIR__ . '/page_loader.php'; ?>
    <script>
      window.KODUS_SOCKET_CONFIG = <?php echo json_encode(kodus_socket_frontend_config(), JSON_UNESCAPED_SLASHES); ?>;
      window.KODUS_LOCATION_CONTEXT = <?php echo json_encode([
          'endpoint' => $app_root . 'save_location_context.php',
          'csrfToken' => security_get_csrf_token(),
          'reloadOnChange' => true,
          'maxAgeSeconds' => 1800,
          'session' => app_location_session_snapshot(),
      ], JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="<?php echo $app_root; ?>dist/js/kodus-live-refresh.js"></script>
    <script src="<?php echo $app_root; ?>dist/js/kodus-location-context.js"></script>
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
        max-height: calc(100vh - 2rem);
        max-height: calc(100dvh - 2rem);
        padding: 1.35rem;
        border-radius: 22px;
        color: var(--kodus-detail-text);
        background: var(--kodus-detail-hero-end);
        box-shadow: var(--kodus-detail-shadow);
        display: flex !important;
        flex-direction: column;
        justify-content: flex-start;
        overflow: hidden;
      }

      .swal2-popup.kodus-detail-popup .swal2-title {
        color: var(--kodus-detail-text);
        font-size: 1.35rem;
        font-weight: 700;
      }

      .swal2-popup.kodus-detail-popup .swal2-html-container {
        margin-top: 0.75rem;
        color: var(--kodus-detail-text);
        flex: 1 1 auto;
        min-height: 0;
        max-height: none;
        overflow-y: auto;
        padding-right: 0.25rem;
      }

      .swal2-popup.kodus-detail-popup .swal2-actions {
        flex: 0 0 auto;
        z-index: 2;
        margin: 0.85rem 0 0;
        padding-top: 0.85rem;
        width: 100%;
        background: linear-gradient(180deg, rgba(22, 32, 52, 0), var(--kodus-detail-hero-end) 34%);
      }

      .swal2-popup.kodus-edit-popup {
        width: min(980px, 94vw);
        max-height: calc(100vh - 2rem);
        max-height: calc(100dvh - 2rem);
        padding: 1.35rem;
        border-radius: 22px;
        color: var(--kodus-detail-text);
        background: var(--kodus-detail-hero-end);
        box-shadow: var(--kodus-detail-shadow);
        display: flex !important;
        flex-direction: column;
        justify-content: flex-start;
        overflow: hidden;
      }

      .swal2-popup.kodus-edit-popup .swal2-title,
      .swal2-popup.kodus-edit-popup .swal2-html-container {
        color: var(--kodus-detail-text);
      }

      .swal2-popup.kodus-edit-popup .swal2-html-container {
        margin-top: 0.75rem;
        flex: 1 1 auto;
        min-height: 0;
        max-height: none;
        overflow-y: auto;
        padding-right: 0.25rem;
      }

      .swal2-popup.kodus-edit-popup .swal2-actions {
        flex: 0 0 auto;
        z-index: 2;
        margin: 0.85rem 0 0;
        padding-top: 0.85rem;
        width: 100%;
        background: linear-gradient(180deg, rgba(22, 32, 52, 0), var(--kodus-detail-hero-end) 34%);
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

      .swal2-container.kodus-swal-container {
        padding: 1rem;
        backdrop-filter: blur(6px);
      }

      .swal2-popup.kodus-swal-popup {
        width: min(32rem, 92vw);
        padding: 1.35rem 1.35rem 1.15rem;
        border-radius: 24px;
        border: 1px solid var(--kodus-swal-border, var(--kodus-detail-border));
        background: linear-gradient(180deg, var(--kodus-swal-panel, var(--kodus-detail-panel)), rgba(24, 34, 48, 0.98));
        color: var(--kodus-swal-text, var(--kodus-detail-text));
        box-shadow: var(--kodus-swal-shadow, var(--kodus-detail-shadow));
        text-align: left;
      }

      body[data-theme="light"] .swal2-popup.kodus-swal-popup {
        background: linear-gradient(180deg, var(--kodus-swal-panel, #ffffff), #f8fbff);
      }

      .swal2-popup.kodus-swal-popup .swal2-title {
        color: var(--kodus-swal-text, var(--kodus-detail-text));
        font-size: 1.28rem;
        font-weight: 700;
        line-height: 1.3;
      }

      .swal2-popup.kodus-swal-popup .swal2-html-container,
      .swal2-popup.kodus-swal-popup .swal2-footer {
        color: var(--kodus-swal-muted, var(--kodus-detail-muted));
      }

      .swal2-popup.kodus-swal-popup .swal2-html-container {
        margin: 0.85rem 0 0;
        font-size: 0.96rem;
        line-height: 1.6;
      }

      .swal2-popup.kodus-swal-popup .swal2-icon {
        margin: 0.15rem auto 0;
      }

      .swal2-popup.kodus-swal-popup .swal2-actions {
        gap: 0.75rem;
        justify-content: flex-end;
        margin: 1.15rem 0 0;
        padding: 0.2rem 0 0;
      }

      .swal2-popup.kodus-swal-popup .swal2-styled {
        margin: 0;
        min-width: 8.75rem;
        padding: 0.8rem 1.2rem;
        border: 1px solid transparent;
        border-radius: 14px;
        font-size: 0.95rem;
        font-weight: 700;
        line-height: 1.2;
        transition: transform 0.16s ease, box-shadow 0.16s ease, filter 0.16s ease, border-color 0.16s ease;
      }

      .swal2-popup.kodus-swal-popup .swal2-styled:hover {
        transform: translateY(-1px);
      }

      .swal2-popup.kodus-swal-popup .swal2-confirm {
        background: linear-gradient(135deg, var(--kodus-swal-confirm-start, #2563eb), var(--kodus-swal-confirm-end, #0b57d0)) !important;
        color: #fff !important;
        box-shadow: var(--kodus-swal-confirm-shadow, 0 14px 28px rgba(37, 99, 235, 0.28));
      }

      .swal2-popup.kodus-swal-popup .swal2-cancel {
        background: var(--kodus-swal-cancel-bg, rgba(255, 255, 255, 0.06)) !important;
        border-color: var(--kodus-swal-border, var(--kodus-detail-border)) !important;
        color: var(--kodus-swal-text, var(--kodus-detail-text)) !important;
        box-shadow: none !important;
      }

      .swal2-popup.kodus-swal-popup .swal2-deny {
        background: linear-gradient(135deg, #f59e0b, #d97706) !important;
        color: #fff !important;
        box-shadow: 0 12px 24px rgba(217, 119, 6, 0.24);
      }

      .swal2-popup.kodus-swal-popup .swal2-input,
      .swal2-popup.kodus-swal-popup .swal2-select,
      .swal2-popup.kodus-swal-popup .swal2-textarea,
      .swal2-popup.kodus-swal-popup .swal2-file {
        margin-top: 1rem;
        border: 1px solid var(--kodus-swal-border, var(--kodus-detail-border));
        border-radius: 14px;
        background: var(--kodus-swal-input-bg, rgba(9, 16, 28, 0.42));
        color: var(--kodus-swal-text, var(--kodus-detail-text));
        box-shadow: none;
      }

      .swal2-popup.kodus-swal-popup .swal2-validation-message {
        margin-top: 0.9rem;
        border-radius: 14px;
      }

      .swal2-popup.kodus-swal-popup .swal2-footer {
        margin-top: 1rem;
        padding-top: 0.85rem;
        border-top: 1px solid var(--kodus-swal-border, var(--kodus-detail-border));
      }

      .swal2-popup.kodus-swal-popup .swal2-timer-progress-bar {
        height: 0.3rem;
        background: linear-gradient(90deg, var(--kodus-swal-confirm-start, #2563eb), #20c997);
      }

      .swal2-popup.kodus-swal-toast {
        width: min(25rem, 92vw);
        padding: 0.95rem 1rem;
        border-radius: 18px;
        border: 1px solid var(--kodus-swal-border, var(--kodus-detail-border));
        background: var(--kodus-swal-toast-bg, rgba(12, 20, 33, 0.94));
        color: var(--kodus-swal-text, var(--kodus-detail-text));
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
      }

      .swal2-popup.kodus-swal-toast .swal2-title {
        color: var(--kodus-swal-text, var(--kodus-detail-text));
        font-size: 0.98rem;
        font-weight: 700;
        text-align: left;
      }

      .swal2-popup.kodus-swal-toast .swal2-html-container {
        margin: 0.35rem 0 0;
        color: var(--kodus-swal-muted, var(--kodus-detail-muted));
        font-size: 0.88rem;
        text-align: left;
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

      body[data-theme="light"] .swal2-popup.kodus-edit-popup .swal2-actions {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0), #ffffff 34%);
      }

      body[data-theme="light"] .swal2-popup.kodus-detail-popup .swal2-actions {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0), #ffffff 34%);
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
<script src="<?php echo $app_root; ?>plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="<?php echo $app_root; ?>code.jquery.com/jquery-3.6.0.min.js"></script>
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
<?php
$kodusPopup = $_SESSION['kodus_popup'] ?? null;
unset($_SESSION['kodus_popup']);
?>
<?php if (is_array($kodusPopup)): ?>
<script>
(function () {
  const popup = <?= json_encode($kodusPopup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

  function openPopup() {
    if (!window.Swal || !popup) {
      return false;
    }

    const popupClass = 'kodus-swal-popup';
    const containerClass = 'kodus-swal-container';

    Swal.fire({
      icon: popup.icon || 'info',
      title: popup.title || 'KODUS Alert',
      text: popup.text || '',
      timer: Number.isFinite(popup.timer) ? popup.timer : undefined,
      timerProgressBar: !!popup.timer,
      confirmButtonText: 'OK',
      customClass: {
        container: containerClass,
        popup: popupClass
      },
      buttonsStyling: true
    });

    return true;
  }

  if (!openPopup()) {
    let attempts = 0;
    const retryPopup = function () {
      attempts += 1;
      if (!openPopup() && attempts < 20) {
        window.setTimeout(retryPopup, 250);
      }
    };
    window.setTimeout(retryPopup, 250);
  }
})();
</script>
<?php endif; ?>

<?php if (isset($_SESSION['user_id'])): ?>
<style>
.kodus-maintenance-banner {
  position: fixed;
  right: 1rem;
  bottom: 1rem;
  width: min(420px, calc(100vw - 2rem));
  z-index: 1060;
  border-radius: 1.15rem;
  border: 2px solid rgba(191, 56, 26, 0.42);
  background:
    radial-gradient(circle at top right, rgba(255, 209, 102, 0.35), transparent 36%),
    linear-gradient(145deg, rgba(255, 244, 214, 0.99), rgba(255, 225, 168, 0.98));
  color: #5b180c;
  box-shadow: 0 24px 54px rgba(112, 39, 14, 0.22), 0 0 0 1px rgba(255, 255, 255, 0.35) inset;
  display: none;
  overflow: hidden;
  animation: kodusMaintenanceBannerPulse 1.8s ease-in-out infinite;
}

body.dark-mode .kodus-maintenance-banner,
body[data-theme="dark"] .kodus-maintenance-banner {
  background:
    radial-gradient(circle at top right, rgba(255, 196, 0, 0.18), transparent 34%),
    linear-gradient(145deg, rgba(71, 10, 10, 0.98), rgba(36, 5, 5, 0.98));
  color: #fff1d6;
  border-color: rgba(255, 99, 71, 0.42);
  box-shadow: 0 28px 60px rgba(0, 0, 0, 0.34), 0 0 0 1px rgba(255, 140, 0, 0.08) inset;
}

.kodus-maintenance-banner__body {
  padding: 1.1rem 1.1rem 1rem;
  position: relative;
  padding-left: 5.4rem;
}

.kodus-maintenance-banner__body::before {
  content: "";
  position: absolute;
  inset: 0 auto 0 0;
  width: 8px;
  background: linear-gradient(180deg, #ffcf5a, #ff7a18 48%, #ff3b30 100%);
}

.kodus-maintenance-banner__koda {
  position: absolute;
  left: 0.85rem;
  top: 0.7rem;
  width: 4.4rem;
  height: 5rem;
  pointer-events: none;
}

.kodus-maintenance-banner__koda-svg {
  width: 100%;
  height: 100%;
  overflow: visible;
  filter: drop-shadow(0 10px 18px rgba(18, 136, 203, 0.18));
}

.kodus-maintenance-banner__koda .koda-leaf.front {
  fill: #7ecf2f;
}

.kodus-maintenance-banner__koda .koda-leaf.back {
  fill: #59b61d;
}

.kodus-maintenance-banner__koda .koda-body {
  fill: url(#maintenanceKodaBodyGradient);
}

.kodus-maintenance-banner__koda .koda-core {
  fill: #0f70b8;
  opacity: 0.92;
}

.kodus-maintenance-banner__koda .koda-highlight {
  fill: rgba(255, 255, 255, 0.76);
}

.kodus-maintenance-banner__koda .koda-droplet {
  fill: #8fe3ff;
}

.kodus-maintenance-banner__koda .koda-droplet.small {
  fill: #d7f6ff;
}

.kodus-maintenance-banner__koda .koda-arm,
.kodus-maintenance-banner__koda .koda-finger,
.kodus-maintenance-banner__koda .koda-leg,
.kodus-maintenance-banner__koda .koda-foot {
  fill: none;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.kodus-maintenance-banner__koda .koda-arm,
.kodus-maintenance-banner__koda .koda-finger {
  stroke: #795548;
}

.kodus-maintenance-banner__koda .koda-arm {
  stroke-width: 5;
}

.kodus-maintenance-banner__koda .koda-finger {
  stroke-width: 3.5;
}

.kodus-maintenance-banner__koda .koda-leg,
.kodus-maintenance-banner__koda .koda-foot {
  stroke: #4caf50;
}

.kodus-maintenance-banner__koda .koda-leg {
  stroke-width: 6;
}

.kodus-maintenance-banner__koda .koda-foot {
  stroke-width: 5;
}

.kodus-maintenance-banner__koda .koda-mouth {
  fill: none;
  stroke: #e1f5fe;
  stroke-width: 4;
  stroke-linecap: round;
}

.kodus-maintenance-banner__koda .koda-eye {
  fill: #e1f5fe;
  stroke: none;
}

.kodus-maintenance-banner__koda-aura {
  position: absolute;
  inset: 0.15rem 0.25rem auto;
  width: 2.8rem;
  height: 2.8rem;
  border-radius: 50%;
  border: 2px dashed rgba(80, 170, 39, 0.34);
  animation: kodusKodaOrbit 10s linear infinite;
}

.kodus-maintenance-banner__koda-crown {
  position: absolute;
  top: -0.1rem;
  left: 0.58rem;
  width: 2.15rem;
  height: 1.2rem;
}

.kodus-maintenance-banner__koda-crown span {
  position: absolute;
  display: block;
  background: linear-gradient(180deg, #9ad81b, #58b319);
  border-radius: 70% 10% 70% 10%;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.16);
}

.kodus-maintenance-banner__koda-crown span:nth-child(1) {
  left: 0;
  top: 0.35rem;
  width: 0.8rem;
  height: 0.7rem;
  transform: rotate(-38deg);
}

.kodus-maintenance-banner__koda-crown span:nth-child(2) {
  left: 0.67rem;
  top: -0.02rem;
  width: 0.84rem;
  height: 0.95rem;
  transform: rotate(-4deg);
}

.kodus-maintenance-banner__koda-crown span:nth-child(3) {
  right: 0;
  top: 0.35rem;
  width: 0.8rem;
  height: 0.7rem;
  transform: scaleX(-1) rotate(-38deg);
}

.kodus-maintenance-banner__koda-shell {
  position: absolute;
  left: 0.52rem;
  top: 0.62rem;
  width: 2.3rem;
  height: 3.2rem;
  border-radius: 58% 58% 52% 52% / 42% 42% 58% 58%;
  background: linear-gradient(180deg, #82d9cb 0%, #4ab9d6 58%, #1188cb 100%);
  box-shadow: 0 12px 22px rgba(18, 136, 203, 0.18);
}

.kodus-maintenance-banner__koda-shell::before {
  content: "";
  position: absolute;
  top: 0.28rem;
  left: 0.22rem;
  width: 0.74rem;
  height: 1rem;
  border-radius: 60% 40% 58% 42%;
  background: rgba(255,255,255,0.9);
  transform: rotate(28deg);
}

.kodus-maintenance-banner__koda-face {
  position: absolute;
  left: 0.35rem;
  top: 1.05rem;
  width: 1.62rem;
  height: 1.55rem;
  border-radius: 50%;
  background: radial-gradient(circle at 50% 30%, #2a98d3, #0f70b8 76%);
}

.kodus-maintenance-banner__koda-face::before,
.kodus-maintenance-banner__koda-face::after {
  content: "";
  position: absolute;
  top: 0.58rem;
  width: 0.18rem;
  height: 0.18rem;
  border-radius: 50%;
  background: #e8f5ff;
  transition: transform 180ms ease, width 180ms ease, height 180ms ease, border-radius 180ms ease, top 180ms ease;
}

.kodus-maintenance-banner__koda-face::before {
  left: 0.4rem;
}

.kodus-maintenance-banner__koda-face::after {
  right: 0.4rem;
}

.kodus-maintenance-banner__koda-smile {
  position: absolute;
  left: 0.58rem;
  top: 0.84rem;
  width: 0.46rem;
  height: 0.22rem;
  border-bottom: 2px solid #e8f5ff;
  border-radius: 0 0 0.55rem 0.55rem;
  transition: all 180ms ease;
}

.kodus-maintenance-banner__koda-brow {
  position: absolute;
  top: 0.44rem;
  width: 0.34rem;
  height: 0.08rem;
  border-radius: 999px;
  background: rgba(232, 245, 255, 0.95);
  opacity: 0;
  transition: opacity 180ms ease, transform 180ms ease, top 180ms ease;
}

.kodus-maintenance-banner__koda-brow--left {
  left: 0.3rem;
}

.kodus-maintenance-banner__koda-brow--right {
  right: 0.3rem;
}

.kodus-maintenance-banner__koda.is-warning .kodus-maintenance-banner__koda-arm--left {
  top: 1.82rem;
  transform: rotate(-46deg);
}

.kodus-maintenance-banner__koda.is-warning .kodus-maintenance-banner__koda-arm--right {
  top: 2.02rem;
  transform: scaleX(-1) rotate(-12deg);
}

.kodus-maintenance-banner__koda.is-warning .kodus-maintenance-banner__koda-leg--left {
  transform: rotate(6deg);
}

.kodus-maintenance-banner__koda.is-warning .kodus-maintenance-banner__koda-leg--right {
  transform: rotate(-6deg);
}

.kodus-maintenance-banner__koda.is-urgent .kodus-maintenance-banner__koda-aura {
  animation-duration: 4s;
}

.kodus-maintenance-banner__koda.is-urgent .kodus-maintenance-banner__koda-face::before,
.kodus-maintenance-banner__koda.is-urgent .kodus-maintenance-banner__koda-face::after {
  top: 0.6rem;
  width: 0.26rem;
  height: 0.08rem;
  border-radius: 999px;
}

.kodus-maintenance-banner__koda.is-urgent .kodus-maintenance-banner__koda-brow {
  opacity: 1;
}

.kodus-maintenance-banner__koda.is-urgent .kodus-maintenance-banner__koda-brow--left {
  transform: rotate(24deg);
}

.kodus-maintenance-banner__koda.is-urgent .kodus-maintenance-banner__koda-brow--right {
  transform: rotate(-24deg);
}

.kodus-maintenance-banner__koda.is-urgent .kodus-maintenance-banner__koda-smile {
  left: 0.63rem;
  top: 0.9rem;
  width: 0.32rem;
  height: 0.12rem;
  border-bottom-width: 2px;
  border-radius: 0 0 0.25rem 0.25rem;
}

.kodus-maintenance-banner__koda.is-urgent .kodus-maintenance-banner__koda-arm--left {
  top: 1.76rem;
  left: 0.22rem;
  transform: rotate(-62deg);
}

.kodus-maintenance-banner__koda.is-urgent .kodus-maintenance-banner__koda-arm--right {
  top: 1.78rem;
  right: 0.18rem;
  transform: scaleX(-1) rotate(-42deg);
}

.kodus-maintenance-banner__koda.is-urgent .kodus-maintenance-banner__koda-leg--left {
  left: 0.9rem;
  height: 1.08rem;
  transform: rotate(1deg);
}

.kodus-maintenance-banner__koda.is-urgent .kodus-maintenance-banner__koda-leg--right {
  right: 0.9rem;
  height: 1.08rem;
  transform: rotate(-1deg);
}

.kodus-maintenance-banner__koda-stem {
  position: absolute;
  left: 1.08rem;
  top: 2.42rem;
  width: 0.16rem;
  height: 0.78rem;
  border-radius: 999px;
  background: linear-gradient(180deg, #8fdc24, #4cae1b);
}

.kodus-maintenance-banner__koda-arm {
  position: absolute;
  top: 1.95rem;
  width: 0.52rem;
  height: 0.3rem;
  background: linear-gradient(180deg, #8fdc24, #5eb81a);
  border-radius: 60% 20% 60% 20%;
}

.kodus-maintenance-banner__koda-arm--left {
  left: 0.28rem;
  transform: rotate(-28deg);
}

.kodus-maintenance-banner__koda-arm--right {
  right: 0.24rem;
  transform: scaleX(-1) rotate(-28deg);
}

.kodus-maintenance-banner__koda-leg {
  position: absolute;
  bottom: 0.1rem;
  width: 0.36rem;
  height: 1rem;
  background: linear-gradient(180deg, #68ccdd, #45b5da);
  border-radius: 999px;
}

.kodus-maintenance-banner__koda-leg--left {
  left: 1.02rem;
  transform: rotate(10deg);
}

.kodus-maintenance-banner__koda-leg--right {
  right: 1rem;
  transform: rotate(-10deg);
}

.kodus-maintenance-banner__koda-ground {
  position: absolute;
  left: 0.6rem;
  bottom: -0.04rem;
  width: 2rem;
  height: 0.28rem;
  border-radius: 999px;
  background: rgba(180, 54, 35, 0.14);
}

.kodus-maintenance-banner__eyebrow {
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #9f2f12;
  margin-bottom: 0.45rem;
}

.kodus-maintenance-banner__title {
  font-size: 1.28rem;
  font-weight: 800;
  line-height: 1.15;
  margin-bottom: 0.55rem;
  color: #5b180c;
  text-transform: uppercase;
}

.kodus-maintenance-banner__message {
  font-size: 1rem;
  line-height: 1.5;
  margin-bottom: 1rem;
  color: rgba(91, 24, 12, 0.92);
}

.kodus-maintenance-banner__timer {
  display: inline-flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.7rem 0.95rem;
  border-radius: 999px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.38), rgba(255, 140, 0, 0.18));
  border: 1px solid rgba(191, 56, 26, 0.16);
  font-weight: 800;
  color: #6c1a0f;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.28);
}

.kodus-maintenance-banner__timer i {
  color: #c0392b;
  font-size: 1rem;
}

#kodus-maintenance-banner-countdown {
  font-size: 1.15rem;
  color: #8f1d12;
}

.kodus-maintenance-banner.is-critical {
  border-color: rgba(220, 53, 69, 0.68);
  background:
    radial-gradient(circle at top right, rgba(255, 120, 120, 0.28), transparent 36%),
    linear-gradient(145deg, rgba(255, 230, 230, 0.99), rgba(255, 193, 193, 0.98));
  color: #5c0c12;
  animation-duration: 1s;
}

.kodus-maintenance-banner.is-critical .kodus-maintenance-banner__eyebrow {
  color: #b02a37;
}

.kodus-maintenance-banner.is-critical .kodus-maintenance-banner__title {
  color: #7a1018;
}

.kodus-maintenance-banner.is-critical .kodus-maintenance-banner__message {
  color: rgba(92, 12, 18, 0.92);
}

.kodus-maintenance-banner.is-critical .kodus-maintenance-banner__timer {
  background: linear-gradient(135deg, rgba(255,255,255,0.45), rgba(220, 53, 69, 0.18));
  border-color: rgba(176, 42, 55, 0.24);
  color: #7a1018;
}

.kodus-maintenance-banner.is-critical .kodus-maintenance-banner__timer i,
.kodus-maintenance-banner.is-critical #kodus-maintenance-banner-countdown {
  color: #c1121f;
}

.kodus-maintenance-banner.is-critical .kodus-maintenance-banner__koda-aura {
  border-color: rgba(193, 18, 31, 0.38);
}

.kodus-maintenance-banner.is-critical .kodus-maintenance-banner__koda-crown span {
  background: linear-gradient(180deg, #b5e61d, #6ec41d);
}

.kodus-maintenance-banner.is-critical .kodus-maintenance-banner__koda-smile {
  width: 0.38rem;
  left: 0.62rem;
  border-bottom-color: #fff0f0;
}

.kodus-maintenance-banner.is-critical .kodus-maintenance-banner__koda-ground {
  background: rgba(193, 18, 31, 0.18);
}

body.dark-mode .kodus-maintenance-banner__eyebrow,
body[data-theme="dark"] .kodus-maintenance-banner__eyebrow {
  color: #ffcf75;
}

body.dark-mode .kodus-maintenance-banner__title,
body[data-theme="dark"] .kodus-maintenance-banner__title {
  color: #ffffff;
}

body.dark-mode .kodus-maintenance-banner__message,
body[data-theme="dark"] .kodus-maintenance-banner__message {
  color: rgba(255, 241, 214, 0.96);
}

body.dark-mode .kodus-maintenance-banner__timer,
body[data-theme="dark"] .kodus-maintenance-banner__timer {
  background: linear-gradient(135deg, rgba(255, 224, 138, 0.2), rgba(255, 120, 0, 0.28));
  border-color: rgba(255, 207, 117, 0.24);
  color: #fff5dc;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.08);
}

body.dark-mode .kodus-maintenance-banner__timer i,
body[data-theme="dark"] .kodus-maintenance-banner__timer i {
  color: #ffd166;
}

body.dark-mode #kodus-maintenance-banner-countdown,
body[data-theme="dark"] #kodus-maintenance-banner-countdown {
  color: #ffffff;
}

body.dark-mode .kodus-maintenance-banner__koda-aura,
body[data-theme="dark"] .kodus-maintenance-banner__koda-aura {
  border-color: rgba(144, 214, 43, 0.28);
}

body.dark-mode .kodus-maintenance-banner__koda-ground,
body[data-theme="dark"] .kodus-maintenance-banner__koda-ground {
  background: rgba(255, 193, 7, 0.14);
}

body.dark-mode .kodus-maintenance-banner.is-critical,
body[data-theme="dark"] .kodus-maintenance-banner.is-critical {
  border-color: rgba(255, 99, 132, 0.75);
  background:
    radial-gradient(circle at top right, rgba(255, 120, 120, 0.22), transparent 36%),
    linear-gradient(145deg, rgba(86, 8, 18, 0.99), rgba(51, 5, 12, 0.98));
  color: #ffe4e6;
}

body.dark-mode .kodus-maintenance-banner.is-critical .kodus-maintenance-banner__eyebrow,
body[data-theme="dark"] .kodus-maintenance-banner.is-critical .kodus-maintenance-banner__eyebrow {
  color: #ff9aa2;
}

body.dark-mode .kodus-maintenance-banner.is-critical .kodus-maintenance-banner__title,
body[data-theme="dark"] .kodus-maintenance-banner.is-critical .kodus-maintenance-banner__title {
  color: #ffffff;
}

body.dark-mode .kodus-maintenance-banner.is-critical .kodus-maintenance-banner__message,
body[data-theme="dark"] .kodus-maintenance-banner.is-critical .kodus-maintenance-banner__message {
  color: rgba(255, 228, 230, 0.95);
}

body.dark-mode .kodus-maintenance-banner.is-critical .kodus-maintenance-banner__timer,
body[data-theme="dark"] .kodus-maintenance-banner.is-critical .kodus-maintenance-banner__timer {
  background: linear-gradient(135deg, rgba(255, 160, 160, 0.18), rgba(255, 59, 48, 0.3));
  border-color: rgba(255, 154, 162, 0.28);
  color: #fff1f2;
}

body.dark-mode .kodus-maintenance-banner.is-critical .kodus-maintenance-banner__timer i,
body.dark-mode .kodus-maintenance-banner.is-critical #kodus-maintenance-banner-countdown,
body[data-theme="dark"] .kodus-maintenance-banner.is-critical .kodus-maintenance-banner__timer i,
body[data-theme="dark"] .kodus-maintenance-banner.is-critical #kodus-maintenance-banner-countdown {
  color: #ffffff;
}

@keyframes kodusMaintenanceBannerPulse {
  0%, 100% {
    transform: translateY(0);
    box-shadow: 0 28px 60px rgba(0, 0, 0, 0.34), 0 0 0 0 rgba(255, 59, 48, 0.14);
  }
  50% {
    transform: translateY(-2px);
    box-shadow: 0 32px 70px rgba(0, 0, 0, 0.4), 0 0 0 6px rgba(255, 59, 48, 0.08);
  }
}

@keyframes kodusKodaOrbit {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
<script>
let logoutTimer;   // handles auto-logout
let roleChangeCountdownInterval;
let maintenanceCountdownInterval;
let roleChangeModalOpen = false;
let currentRoleChangeState = <?php echo json_encode($roleChangeState, JSON_UNESCAPED_SLASHES); ?>;
let currentMaintenanceState = <?php echo json_encode($maintenanceClientState, JSON_UNESCAPED_SLASHES); ?>;
let maintenanceToastShown = false;

const LOGOUT_INACTIVITY_LIMIT  = 60 * 60 * 1000; // 1 hour
const CURRENT_SESSION_USER_ID = <?php echo json_encode(isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null); ?>;

function showInactivityTimeoutAlert() {
  Swal.fire({
    icon: 'warning',
    title: 'Session Expired',
    text: 'You have been logged out due to inactivity.',
    confirmButtonText: 'OK',
    showConfirmButton: true,
    showCloseButton: false,
    allowOutsideClick: false,
    allowEscapeKey: false,
    willClose: () => {
      submitLogout('timeout');
    }
  });
}
const ROLE_CHANGE_STATUS_URL = <?php echo json_encode($app_root . 'role-change-status'); ?>;
const MAINTENANCE_STATUS_URL = <?php echo json_encode($app_root . 'get_maintenance_state'); ?>;

function formatRoleLabel(value) {
  const text = String(value || '').trim();
  return text ? text.charAt(0).toUpperCase() + text.slice(1) : 'User';
}

function formatMaintenanceCountdown(seconds) {
  const totalSeconds = Math.max(0, Number(seconds || 0));
  const minutes = Math.floor(totalSeconds / 60);
  const remainingSeconds = totalSeconds % 60;
  if (minutes <= 0) {
    return `${remainingSeconds}s`;
  }

  return `${minutes}m ${String(remainingSeconds).padStart(2, '0')}s`;
}

function ensureMaintenanceBanner() {
  let banner = document.getElementById('kodus-maintenance-banner');
  if (banner) {
    return banner;
  }

  banner = document.createElement('div');
  banner.id = 'kodus-maintenance-banner';
  banner.className = 'kodus-maintenance-banner';
  banner.innerHTML = `
    <div class="kodus-maintenance-banner__body">
      <div class="kodus-maintenance-banner__koda is-warning" aria-hidden="true" title="KODA - Sprout Guardian">
        <svg class="kodus-maintenance-banner__koda-svg" viewBox="0 0 200 220" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="KODA mascot">
          <defs>
            <linearGradient id="maintenanceKodaBodyGradient" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#82d9cb"></stop>
              <stop offset="58%" stop-color="#4ab9d6"></stop>
              <stop offset="100%" stop-color="#1188cb"></stop>
            </linearGradient>
          </defs>
          <path class="koda-leaf front" d="M104 22 C118 2, 138 10, 132 30 C128 42, 115 46, 104 40 C110 34, 112 28, 104 22 Z"></path>
          <path class="koda-leaf back" d="M96 24 C84 8, 68 18, 74 34 C79 42, 90 43, 98 37 C93 33, 92 28, 96 24 Z"></path>
          <path class="koda-body" d="M100 14 C140 52, 170 100, 155 150 C140 198, 60 198, 45 150 C30 100, 60 52, 100 14 Z"></path>
          <ellipse class="koda-core" cx="100" cy="138" rx="55" ry="50"></ellipse>
          <ellipse class="koda-highlight" cx="75" cy="72" rx="15" ry="25" transform="rotate(-30 75 72)"></ellipse>
          <circle class="koda-droplet" cx="150" cy="42" r="8"></circle>
          <circle class="koda-droplet small" cx="164" cy="27" r="5"></circle>
          <path class="koda-arm left" d="M56 138 Q40 132 30 146 Q25 154 34 160"></path>
          <path class="koda-arm right" d="M144 138 Q160 132 170 146 Q175 154 166 160"></path>
          <path class="koda-finger left-top" d="M30 146 L24 142"></path>
          <path class="koda-finger left-bottom" d="M31 152 L24 154"></path>
          <path class="koda-finger right-top" d="M170 146 L176 142"></path>
          <path class="koda-finger right-bottom" d="M169 152 L176 154"></path>
          <path class="koda-leg left" d="M90 178 Q84 194 90 206"></path>
          <path class="koda-leg right" d="M110 178 Q116 194 110 206"></path>
          <path class="koda-foot left" d="M84 206 Q90 212 96 206"></path>
          <path class="koda-foot right" d="M104 206 Q110 212 116 206"></path>
          <circle class="koda-eye left" cx="80" cy="138" r="6"></circle>
          <circle class="koda-eye right" cx="120" cy="138" r="6"></circle>
          <path class="koda-mouth" d="M85 158 Q100 173 115 158"></path>
        </svg>
      </div>
      <div class="kodus-maintenance-banner__eyebrow">Urgent Maintenance Warning</div>
      <div class="kodus-maintenance-banner__title">Save Your Work Now</div>
      <div class="kodus-maintenance-banner__message" id="kodus-maintenance-banner-message"></div>
      <div class="kodus-maintenance-banner__timer">
        <i class="fas fa-exclamation-triangle"></i>
        <span>Time left: <strong id="kodus-maintenance-banner-countdown">0s</strong></span>
      </div>
    </div>
  `;
  document.body.appendChild(banner);
  return banner;
}

function hideMaintenanceBanner() {
  const banner = document.getElementById('kodus-maintenance-banner');
  if (banner) {
    banner.style.display = 'none';
  }
}

function renderMaintenanceBanner(state) {
  const banner = ensureMaintenanceBanner();
  const messageNode = document.getElementById('kodus-maintenance-banner-message');
  const countdownNode = document.getElementById('kodus-maintenance-banner-countdown');
  const kodaNode = banner.querySelector('.kodus-maintenance-banner__koda');
  const secondsRemaining = Math.max(0, Number(state?.seconds_remaining || 0));
  const isCritical = secondsRemaining > 0 && secondsRemaining <= 30;

  if (messageNode) {
    messageNode.textContent = String(state?.message || 'Scheduled maintenance will begin soon. Please save or complete your current work before the countdown ends.');
  }

  if (countdownNode) {
    countdownNode.textContent = formatMaintenanceCountdown(secondsRemaining);
  }

  if (kodaNode) {
    kodaNode.classList.toggle('is-warning', !isCritical);
    kodaNode.classList.toggle('is-urgent', isCritical);
  }

  banner.classList.toggle('is-critical', isCritical);
  banner.style.display = 'block';
}

function clearMaintenanceTimers() {
  clearTimeout(maintenanceCountdownInterval);
  maintenanceCountdownInterval = null;
}

function forceMaintenanceLogout() {
  clearMaintenanceTimers();
  hideMaintenanceBanner();
  submitLogout('maintenance');
}

function startMaintenanceCountdown() {
  clearMaintenanceTimers();

  const tickMaintenanceCountdown = () => {
    if (!currentMaintenanceState) {
      clearMaintenanceTimers();
      return;
    }

    currentMaintenanceState.seconds_remaining = Math.max(0, Number(currentMaintenanceState.seconds_remaining || 0) - 1);
    renderMaintenanceBanner(currentMaintenanceState);

    if (currentMaintenanceState.seconds_remaining <= 0) {
      forceMaintenanceLogout();
      return;
    }

    maintenanceCountdownInterval = setTimeout(tickMaintenanceCountdown, 1000);
  };

  maintenanceCountdownInterval = setTimeout(tickMaintenanceCountdown, 1000);
}

function announceMaintenanceCountdown(state) {
  if (maintenanceToastShown) {
    return;
  }

  maintenanceToastShown = true;
  renderMaintenanceBanner(state);
}

function handleMaintenanceState(state) {
  if (!state || !state.active) {
    currentMaintenanceState = null;
    clearMaintenanceTimers();
    hideMaintenanceBanner();
    return;
  }

  currentMaintenanceState = state;
  renderMaintenanceBanner(state);
  announceMaintenanceCountdown(state);

  if (Number(state.seconds_remaining || 0) <= 0) {
    forceMaintenanceLogout();
    return;
  }

  startMaintenanceCountdown();
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
  clearTimeout(roleChangeCountdownInterval);
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

  const tickRoleChangeCountdown = () => {
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
      return;
    }

    roleChangeCountdownInterval = setTimeout(tickRoleChangeCountdown, 1000);
  };

  roleChangeCountdownInterval = setTimeout(tickRoleChangeCountdown, 1000);
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

function bindSessionSafetySocket() {
  if (!window.KODUSLiveRefresh || typeof window.KODUSLiveRefresh.watchSocket !== 'function' || !window.KODUSLiveRefresh.isSocketEnabled()) {
    return;
  }

  window.KODUSLiveRefresh.watchSocket({
    key: 'session-safety',
    channel: 'kodus.session',
    events: ['role.changed', 'maintenance.changed'],
    onMessage: function(payload) {
      const eventName = String(payload?.event || '');
      const data = payload?.data && typeof payload.data === 'object' ? payload.data : {};

      if (eventName === 'role.changed') {
        const targetUserId = Number(data.user_id || 0);
        if (!CURRENT_SESSION_USER_ID || targetUserId !== Number(CURRENT_SESSION_USER_ID)) {
          return;
        }

        if (data.state && typeof data.state === 'object') {
          handleRoleChangeState(Object.assign({ active: true }, data.state));
          return;
        }

        return;
      }

      if (eventName === 'maintenance.changed') {
        if (data.state && typeof data.state === 'object') {
          if (data.state.active || data.state.phase === 'pending') {
            handleMaintenanceState(Object.assign({ active: true }, data.state));
          } else {
            handleMaintenanceState(null);
          }
          return;
        }

      }
    }
  });

  window.KODUSLiveRefresh.connectSocket().then(function(socket) {
    return socket;
  });
}

// ----------------------
// UNREAD COUNT REFRESH
// ----------------------
function updateUnreadCount() {
  $.getJSON("<?php echo $app_root; ?>messenger/get_unread_count.php", function(data) {
    let badge = $('#sidebarMailUnreadBadge');
    const mailNavLabel = $('.nav-item a[href$="messenger/"] p');

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
    if (typeof window.updateKodusChatBubbleUnreadBadges === 'function') {
      window.updateKodusChatBubbleUnreadBadges(data.count || 0);
    }
  });
}

function startUnreadPolling() {
  if (!unreadInterval) {
    updateUnreadCount();
  }
}

function stopUnreadPolling() {
  unreadInterval = null;
}

// ----------------------
// INACTIVITY HANDLING
// ----------------------
function resetTimers() {
  // Reset logout inactivity timer
  clearTimeout(logoutTimer);
  logoutTimer = setTimeout(() => {
    showInactivityTimeoutAlert();
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
  if (currentMaintenanceState) {
    handleMaintenanceState(Object.assign({ active: true, message: currentMaintenanceState.warning_message || currentMaintenanceState.message || '' }, currentMaintenanceState));
  }
  bindSessionSafetySocket();
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
    <strong>KliMalasakit Operational Data Unified System</a>.</strong>
  </footer>
</body>
</html>
