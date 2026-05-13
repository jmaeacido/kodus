<?php
  include('../header.php');
  include('../sidenav.php');
  require_once __DIR__ . '/tracking_recipient_helpers.php';

  $userType = $_SESSION['user_type'] ?? 'user';
  $trackingRecipientOptions = isset($conn) && $conn instanceof mysqli ? tracking_fetch_recipient_options($conn) : [];
?>
<script>
    const userType = '<?php echo $userType; ?>';
</script>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | Outgoing</title>
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/select2/css/select2.min.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
  <style>
    .document-modal {
      text-align: left;
      color: inherit;
    }
    .document-modal-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
      margin-bottom: 16px;
    }
    .document-stat {
      border: 1px solid rgba(108, 117, 125, 0.35);
      border-radius: 12px;
      padding: 12px 14px;
      background: rgba(108, 117, 125, 0.08);
    }
    .document-stat-label {
      display: block;
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      opacity: 0.75;
      margin-bottom: 4px;
    }
    .document-stat-value {
      font-size: 1rem;
      font-weight: 700;
      line-height: 1.35;
      word-break: break-word;
    }
    .document-section {
      border: 1px solid rgba(108, 117, 125, 0.28);
      border-radius: 14px;
      padding: 14px 16px;
      margin-bottom: 14px;
      background: rgba(108, 117, 125, 0.05);
    }
    .document-section h6 {
      margin: 0 0 10px;
      font-size: 0.92rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .document-section-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 10px;
    }
    .document-field-label {
      display: block;
      font-size: 0.76rem;
      font-weight: 600;
      opacity: 0.72;
      margin-bottom: 4px;
    }
    .document-field-value {
      display: block;
      line-height: 1.5;
      word-break: break-word;
    }
    .document-empty {
      opacity: 0.72;
      font-style: italic;
    }
    .document-status-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.35rem 0.75rem;
      border-radius: 999px;
      font-size: 0.86rem;
      font-weight: 700;
      line-height: 1;
      background: rgba(23, 162, 184, 0.18);
      color: #9de8f2;
    }
    .document-file-link {
      font-weight: 600;
      text-decoration: none;
    }
    .kodus-track-btn {
      color: #9ec5fe;
      border-color: rgba(13, 110, 253, 0.55);
      background: rgba(13, 110, 253, 0.12);
      transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .kodus-track-btn:hover,
    .kodus-track-btn:focus {
      color: #ffffff;
      border-color: #2f80ff;
      background: rgba(13, 110, 253, 0.28);
      box-shadow: 0 0 0 0.16rem rgba(13, 110, 253, 0.18);
    }
    body[data-theme="light"] .document-stat,
    body[data-theme="light"] .document-section {
      background: #ffffff;
      border-color: rgba(13, 110, 253, 0.14);
      box-shadow: 0 0.4rem 1rem rgba(13, 110, 253, 0.06);
    }
    body[data-theme="light"] .document-status-badge {
      background: rgba(13, 110, 253, 0.12);
      color: #0d6efd;
    }
    body[data-theme="light"] .document-file-link {
      color: #0d6efd;
    }
    body[data-theme="light"] .kodus-track-btn {
      color: #0d6efd;
      border-color: rgba(13, 110, 253, 0.38);
      background: rgba(13, 110, 253, 0.04);
    }
    body[data-theme="light"] .kodus-track-btn:hover,
    body[data-theme="light"] .kodus-track-btn:focus {
      color: #ffffff;
      background: #0d6efd;
      border-color: #0d6efd;
    }
    #Outgoing-table th.tracking-file-column,
    #Outgoing-table td.tracking-file-column {
      width: 14rem;
      min-width: 10rem;
      max-width: 14rem;
      white-space: normal !important;
    }
    .tracking-file-list {
      display: flex;
      flex-direction: column;
      align-items: stretch;
      gap: 0.22rem;
      max-width: 100%;
      max-height: 4.85rem;
      overflow-y: auto;
      text-align: left;
    }
    .tracking-file-link {
      display: block;
      max-width: 100%;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      line-height: 1.25;
    }
    .tracking-file-more {
      display: inline-block;
      margin-top: 0.08rem;
      color: #6c757d;
      font-size: 0.72rem;
      font-weight: 700;
    }
    .swal2-popup.kodus-form-popup {
      width: min(780px, 94vw);
      height: auto;
      max-height: calc(100vh - 2rem);
      max-height: calc(100dvh - 2rem);
      padding: 1.35rem;
      border-radius: 22px;
      color: var(--kodus-detail-text, #f8f9fa);
      background: var(--kodus-detail-hero-end, #162034);
      box-shadow: var(--kodus-detail-shadow, 0 18px 40px rgba(15, 23, 42, 0.12));
      display: flex !important;
      flex-direction: column;
      justify-content: flex-start;
      overflow: hidden;
    }
    .swal2-container.kodus-scrollable-swal {
      align-items: flex-start !important;
      overflow-x: hidden !important;
      overflow-y: auto !important;
      padding: 1.75rem 0.75rem !important;
    }
    .swal2-container.kodus-scrollable-swal .swal2-popup {
      max-height: none !important;
      margin: 0 auto !important;
      overflow: visible !important;
    }
    .swal2-container.kodus-scrollable-swal .swal2-html-container {
      flex: none !important;
      max-height: none !important;
      overflow: visible !important;
    }
    .swal2-popup.kodus-form-popup .swal2-title,
    .swal2-popup.kodus-form-popup .swal2-html-container {
      color: var(--kodus-detail-text, #f8f9fa);
    }
    .swal2-popup.kodus-form-popup .swal2-html-container {
      margin-top: 0.75rem;
      flex: 1 1 auto;
      min-height: 0;
      max-height: none;
      overflow-y: auto;
      padding-right: 0.25rem;
    }
    .swal2-popup.kodus-form-popup .swal2-loader {
      border-color: #ffffff transparent #ffffff transparent;
    }
    .swal2-popup.kodus-form-popup .swal2-actions {
      flex: 0 0 auto;
      z-index: 2;
      margin: 0.85rem 0 0;
      padding-top: 0.85rem;
      width: 100%;
      background: linear-gradient(180deg, rgba(22, 32, 52, 0), var(--kodus-detail-hero-end, #162034) 34%);
    }
    .swal2-popup.kodus-detail-popup,
    .swal2-popup.kodus-edit-popup {
      width: min(920px, 94vw);
      padding: 1.35rem;
      border-radius: 22px;
      color: var(--kodus-detail-text, #f8f9fa);
      background: var(--kodus-detail-hero-end, #162034);
      box-shadow: var(--kodus-detail-shadow, 0 18px 40px rgba(15, 23, 42, 0.12));
    }
    .swal2-popup.kodus-edit-popup {
      width: min(860px, 94vw);
    }
    .swal2-popup.kodus-detail-popup .swal2-html-container,
    .swal2-popup.kodus-edit-popup .swal2-html-container {
      margin: 0.75rem 0 0;
      color: var(--kodus-detail-text, #f8f9fa);
      overflow: visible;
    }
    .swal2-popup.kodus-detail-popup .swal2-actions,
    .swal2-popup.kodus-edit-popup .swal2-actions {
      margin: 0.85rem 0 0;
      padding-top: 0.85rem;
      width: 100%;
      background: linear-gradient(180deg, rgba(22, 32, 52, 0), var(--kodus-detail-hero-end, #162034) 34%);
    }
    .kodus-form-shell {
      text-align: left;
      color: var(--kodus-detail-text, #f8f9fa);
    }
    .kodus-edit-shell {
      text-align: left;
      color: var(--kodus-detail-text, #f8f9fa);
    }
    .kodus-edit-header {
      padding: 1rem 1.05rem;
      margin-bottom: 1rem;
      border-radius: 18px;
      border: 1px solid var(--kodus-detail-border, rgba(255, 255, 255, 0.12));
      background: linear-gradient(135deg, var(--kodus-detail-hero-start, #1a2840), var(--kodus-detail-hero-end, #162034));
    }
    .kodus-edit-header-title {
      margin: 0;
      font-size: 1.08rem;
      font-weight: 700;
      line-height: 1.35;
      color: var(--kodus-detail-text, #f8f9fa);
    }
    .kodus-edit-header-note {
      margin: 0.38rem 0 0;
      line-height: 1.5;
      color: var(--kodus-detail-muted, rgba(255, 255, 255, 0.7));
    }
    .kodus-edit-section {
      padding: 1rem 1.05rem;
      margin-bottom: 0.95rem;
      border-radius: 16px;
      border: 1px solid var(--kodus-detail-border, rgba(255, 255, 255, 0.12));
      background: var(--kodus-detail-panel, rgba(255, 255, 255, 0.05));
    }
    .kodus-edit-section:last-child {
      margin-bottom: 0;
    }
    .kodus-edit-section-title {
      margin: 0 0 0.85rem;
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--kodus-detail-text, #f8f9fa);
    }
    .kodus-edit-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 0.9rem 1rem;
    }
    .kodus-edit-field {
      min-width: 0;
    }
    .kodus-edit-field--full {
      grid-column: 1 / -1;
    }
    .kodus-edit-shell label {
      display: block;
      margin-bottom: 0.42rem;
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--kodus-detail-muted, rgba(255, 255, 255, 0.7));
    }
    .kodus-edit-shell .form-control,
    .kodus-edit-shell textarea {
      width: 100%;
      border-radius: 12px;
      border: 1px solid var(--kodus-detail-border, rgba(255, 255, 255, 0.12));
      background: var(--kodus-detail-panel-strong, rgba(255, 255, 255, 0.08));
      color: var(--kodus-detail-text, #f8f9fa);
      min-height: 46px;
    }
    .kodus-edit-shell textarea.form-control {
      min-height: 110px;
      resize: vertical;
    }
    .kodus-edit-help {
      display: block;
      margin-top: 0.55rem;
      font-size: 0.78rem;
      line-height: 1.45;
      color: var(--kodus-detail-muted, rgba(255, 255, 255, 0.7));
    }
    .kodus-edit-inline-file {
      display: block;
      margin-top: 0.4rem;
    }
    .kodus-detail-modal {
      text-align: left;
      color: var(--kodus-detail-text, #f8f9fa);
    }
    .kodus-detail-hero {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      padding: 1rem 1.05rem;
      margin-bottom: 1rem;
      border-radius: 18px;
      border: 1px solid var(--kodus-detail-border, rgba(255, 255, 255, 0.12));
      background: linear-gradient(135deg, var(--kodus-detail-hero-start, #1a2840), var(--kodus-detail-hero-end, #162034));
    }
    .kodus-detail-eyebrow,
    .kodus-detail-label {
      display: block;
      margin-bottom: 0.32rem;
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      color: var(--kodus-detail-muted, rgba(255, 255, 255, 0.7));
    }
    .kodus-detail-title {
      margin: 0;
      font-size: 1.15rem;
      font-weight: 800;
      color: var(--kodus-detail-text, #f8f9fa);
    }
    .kodus-detail-subtitle {
      margin: 0.38rem 0 0;
      line-height: 1.5;
      color: var(--kodus-detail-muted, rgba(255, 255, 255, 0.7));
    }
    .kodus-detail-pill,
    .kodus-detail-badge {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      padding: 0.35rem 0.65rem;
      background: rgba(13, 110, 253, 0.18);
      color: var(--kodus-detail-text, #f8f9fa);
      font-size: 0.82rem;
      font-weight: 700;
      white-space: nowrap;
    }
    .kodus-detail-grid,
    .kodus-detail-section-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 0.85rem;
    }
    .kodus-detail-stat,
    .kodus-detail-section {
      padding: 0.9rem 1rem;
      border-radius: 16px;
      border: 1px solid var(--kodus-detail-border, rgba(255, 255, 255, 0.12));
      background: var(--kodus-detail-panel, rgba(255, 255, 255, 0.05));
    }
    .kodus-detail-grid,
    .kodus-detail-section {
      margin-bottom: 0.95rem;
    }
    .kodus-detail-section-title {
      margin: 0 0 0.85rem;
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--kodus-detail-text, #f8f9fa);
    }
    .kodus-detail-value {
      display: block;
      color: var(--kodus-detail-text, #f8f9fa);
      line-height: 1.45;
      word-break: break-word;
    }
    .kodus-detail-value--strong {
      font-weight: 800;
    }
    .kodus-detail-empty {
      color: var(--kodus-detail-muted, rgba(255, 255, 255, 0.65));
      font-style: italic;
    }
    .kodus-detail-link {
      color: var(--kodus-detail-link, #7ab7ff);
      font-weight: 700;
    }
    .kodus-form-hero {
      padding: 1rem 1.05rem;
      margin-bottom: 1rem;
      border-radius: 18px;
      border: 1px solid var(--kodus-detail-border, rgba(255, 255, 255, 0.12));
      background: linear-gradient(135deg, var(--kodus-detail-hero-start, #1a2840), var(--kodus-detail-hero-end, #162034));
    }
    .kodus-form-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      margin-bottom: 0.5rem;
      font-size: 0.74rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--kodus-detail-muted, rgba(255, 255, 255, 0.7));
    }
    .kodus-form-eyebrow i {
      font-size: 0.88rem;
    }
    .kodus-form-title {
      margin: 0;
      font-size: 1.08rem;
      font-weight: 700;
      line-height: 1.35;
      color: var(--kodus-detail-text, #f8f9fa);
    }
    .kodus-form-subtitle {
      margin: 0.38rem 0 0;
      line-height: 1.5;
      color: var(--kodus-detail-muted, rgba(255, 255, 255, 0.7));
    }
    .kodus-form-section {
      padding: 1rem 1.05rem;
      margin-bottom: 0.95rem;
      border-radius: 16px;
      border: 1px solid var(--kodus-detail-border, rgba(255, 255, 255, 0.12));
      background: var(--kodus-detail-panel, rgba(255, 255, 255, 0.05));
    }
    .kodus-form-section:last-child {
      margin-bottom: 0;
    }
    .kodus-form-section-title {
      margin: 0 0 0.25rem;
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--kodus-detail-text, #f8f9fa);
    }
    .kodus-form-section-note {
      margin: 0 0 0.85rem;
      font-size: 0.82rem;
      line-height: 1.45;
      color: var(--kodus-detail-muted, rgba(255, 255, 255, 0.7));
    }
    .kodus-form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 0.9rem 1rem;
    }
    .kodus-form-field {
      margin: 0;
    }
    .kodus-form-field--full {
      grid-column: 1 / -1;
    }
    .kodus-form-shell label {
      display: block;
      margin-bottom: 0.42rem;
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--kodus-detail-muted, rgba(255, 255, 255, 0.7));
    }
    .kodus-form-shell .form-control,
    .kodus-form-shell textarea {
      border-radius: 12px;
      border: 1px solid var(--kodus-detail-border, rgba(255, 255, 255, 0.12));
      background: var(--kodus-detail-panel-strong, rgba(255, 255, 255, 0.08));
      color: var(--kodus-detail-text, #f8f9fa);
      min-height: 46px;
    }
    .kodus-form-shell textarea.form-control {
      min-height: 110px;
      resize: vertical;
    }
    .kodus-form-shell .form-control:focus,
    .kodus-form-shell textarea:focus {
      border-color: var(--kodus-detail-link, #0d6efd);
      box-shadow: 0 0 0 0.18rem rgba(13, 110, 253, 0.18);
    }
    .kodus-form-help {
      display: block;
      margin-top: 0.42rem;
      font-size: 0.78rem;
      line-height: 1.45;
      color: var(--kodus-detail-muted, rgba(255, 255, 255, 0.7));
    }
    .kodus-form-shell .select2-container,
    .kodus-edit-shell .select2-container {
      width: 100% !important;
    }
    .kodus-form-shell .select2-container--bootstrap4 .select2-selection--multiple,
    .kodus-edit-shell .select2-container--bootstrap4 .select2-selection--multiple {
      min-height: 52px;
      padding: 0.32rem 0.44rem;
      border-radius: 12px;
      border: 1px solid var(--kodus-detail-border, rgba(255, 255, 255, 0.12));
      background: linear-gradient(180deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.06));
      color: var(--kodus-detail-text, #f8f9fa);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
      transition: border-color 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
    }
    .kodus-form-shell .select2-container--bootstrap4.select2-container--focus .select2-selection--multiple,
    .kodus-form-shell .select2-container--bootstrap4.select2-container--open .select2-selection--multiple,
    .kodus-edit-shell .select2-container--bootstrap4.select2-container--focus .select2-selection--multiple,
    .kodus-edit-shell .select2-container--bootstrap4.select2-container--open .select2-selection--multiple {
      border-color: var(--kodus-detail-link, #0d6efd);
      box-shadow: 0 0 0 0.18rem rgba(13, 110, 253, 0.18);
    }
    .kodus-form-shell .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__rendered,
    .kodus-edit-shell .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__rendered {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.35rem;
      width: 100%;
      min-height: 36px;
      padding: 0;
      box-sizing: border-box;
      overflow: hidden;
    }
    .kodus-form-shell .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice,
    .kodus-edit-shell .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice {
      display: inline-flex;
      align-items: center;
      flex: 0 1 auto;
      min-width: 0;
      min-height: 30px;
      margin: 0;
      padding: 0.22rem 0.5rem;
      border: 1px solid rgba(96, 165, 250, 0.34);
      border-radius: 999px;
      background: rgba(37, 99, 235, 0.2);
      color: var(--kodus-detail-text, #f8f9fa);
      font-size: 0.82rem;
      line-height: 1.25;
      max-width: 100%;
      overflow: hidden;
      white-space: normal;
    }
    .kodus-form-shell .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__remove,
    .kodus-edit-shell .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__remove {
      flex: 0 0 auto;
      margin-right: 0.32rem;
      color: rgba(255, 255, 255, 0.78);
      text-shadow: none;
    }
    .kodus-form-shell .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__display,
    .kodus-edit-shell .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__display {
      display: inline-flex;
      min-width: 0;
      max-width: 100%;
      overflow: hidden;
    }
    .kodus-form-shell .select2-container--bootstrap4 .select2-selection--multiple .select2-search--inline,
    .kodus-edit-shell .select2-container--bootstrap4 .select2-selection--multiple .select2-search--inline {
      flex: 1 1 8rem;
      min-width: min(8rem, 100%);
      max-width: 100%;
    }
    .kodus-form-shell .select2-container--bootstrap4 .select2-search__field,
    .kodus-edit-shell .select2-container--bootstrap4 .select2-search__field {
      width: 100% !important;
      min-width: min(8rem, 100%);
      max-width: 100%;
      margin-top: 0;
      color: var(--kodus-detail-text, #f8f9fa) !important;
    }
    .swal2-container .select2-dropdown {
      z-index: 1070;
      overflow: hidden;
      border: 1px solid var(--kodus-detail-border, rgba(255, 255, 255, 0.14));
      border-radius: 12px;
      background: var(--kodus-detail-hero-end, #162034);
      color: var(--kodus-detail-text, #f8f9fa);
      box-shadow: 0 18px 38px rgba(2, 6, 23, 0.34);
    }
    .swal2-container .select2-search--dropdown {
      padding: 0.55rem;
      background: rgba(255, 255, 255, 0.04);
    }
    .swal2-container .select2-search--dropdown .select2-search__field {
      border-radius: 10px;
      border: 1px solid var(--kodus-detail-border, rgba(255, 255, 255, 0.14));
      background: rgba(15, 23, 42, 0.42);
      color: var(--kodus-detail-text, #f8f9fa);
    }
    .swal2-container .select2-results__option {
      padding: 0.58rem 0.72rem;
      color: var(--kodus-detail-text, #f8f9fa);
    }
    .swal2-container .select2-results__option[aria-selected="true"] {
      background: rgba(96, 165, 250, 0.16);
    }
    .swal2-container .select2-results__option--highlighted[aria-selected] {
      background: rgba(13, 110, 253, 0.32);
      color: #ffffff;
    }
    .kodus-recipient-option,
    .kodus-recipient-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.55rem;
      min-width: 0;
      max-width: 100%;
    }
    .kodus-recipient-chip {
      flex: 1 1 auto;
    }
    .kodus-recipient-avatar {
      width: 1.85rem;
      height: 1.85rem;
      flex: 0 0 auto;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #0d6efd, #20c997);
      color: #ffffff;
      font-size: 0.72rem;
      font-weight: 800;
      letter-spacing: 0.02em;
    }
    .kodus-recipient-copy {
      min-width: 0;
      max-width: 100%;
      flex: 1 1 auto;
      display: inline-flex;
      flex-direction: column;
      line-height: 1.22;
    }
    .kodus-recipient-name,
    .kodus-recipient-email {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .kodus-recipient-name {
      font-weight: 700;
    }
    .kodus-recipient-email {
      font-size: 0.76rem;
      color: var(--kodus-detail-muted, rgba(255, 255, 255, 0.68));
    }
    .kodus-recipient-chip .kodus-recipient-avatar {
      width: 1.35rem;
      height: 1.35rem;
      font-size: 0.62rem;
    }
    .kodus-recipient-chip .kodus-recipient-email {
      display: none;
    }
    .kodus-form-inline-note {
      display: none;
      margin-top: 0.5rem;
      font-size: 0.8rem;
      color: #9de8f2;
      cursor: pointer;
    }
    .kodus-form-suggestions {
      max-height: 150px;
      overflow-y: auto;
      display: none;
      position: absolute;
      z-index: 999;
      width: 100%;
      margin-top: 0.45rem;
      border-radius: 14px;
      border: 1px solid var(--kodus-detail-border, rgba(255, 255, 255, 0.12));
      background: var(--kodus-detail-panel-strong, rgba(17, 24, 39, 0.96));
      box-shadow: 0 18px 35px rgba(15, 23, 42, 0.22);
    }
    .kodus-form-suggestions .list-group-item {
      border: 0;
      background: transparent;
      color: var(--kodus-detail-text, #f8f9fa);
      font-size: 0.88rem;
    }
    .kodus-form-suggestions .list-group-item:hover,
    .kodus-form-suggestions .list-group-item.active {
      background: rgba(13, 110, 253, 0.18);
      color: var(--kodus-detail-text, #f8f9fa);
    }
    .kodus-form-confirm,
    .kodus-form-cancel {
      border: 0;
      border-radius: 999px;
      padding: 0.72rem 1.15rem;
      font-weight: 700;
      box-shadow: none;
    }
    .kodus-form-confirm {
      background: linear-gradient(135deg, #0d6efd, #2f80ff);
      color: #fff;
    }
    .kodus-form-cancel {
      background: rgba(108, 117, 125, 0.18);
      color: var(--kodus-detail-text, #f8f9fa);
    }
    body[data-theme="light"] .kodus-form-section,
    body[data-theme="light"] .kodus-edit-section,
    body[data-theme="light"] .kodus-edit-header,
    body[data-theme="light"] .kodus-detail-section,
    body[data-theme="light"] .kodus-detail-stat,
    body[data-theme="light"] .kodus-detail-hero {
      background: #ffffff;
      border-color: rgba(13, 110, 253, 0.14);
      box-shadow: 0 0.4rem 1rem rgba(13, 110, 253, 0.06);
    }
    body[data-theme="light"] .swal2-popup.kodus-detail-popup,
    body[data-theme="light"] .swal2-popup.kodus-edit-popup {
      background: #ffffff;
      color: #212529;
    }
    body[data-theme="light"] .kodus-detail-modal,
    body[data-theme="light"] .kodus-detail-title,
    body[data-theme="light"] .kodus-detail-value,
    body[data-theme="light"] .kodus-detail-section-title,
    body[data-theme="light"] .kodus-edit-header-title,
    body[data-theme="light"] .kodus-edit-section-title {
      color: #212529;
    }
    body[data-theme="light"] .kodus-detail-subtitle,
    body[data-theme="light"] .kodus-detail-label,
    body[data-theme="light"] .kodus-detail-eyebrow,
    body[data-theme="light"] .kodus-edit-header-note,
    body[data-theme="light"] .kodus-edit-shell label,
    body[data-theme="light"] .kodus-edit-help {
      color: #6c757d;
    }
    body[data-theme="light"] .kodus-detail-pill,
    body[data-theme="light"] .kodus-detail-badge {
      background: rgba(13, 110, 253, 0.12);
      color: #0d6efd;
    }
    body[data-theme="light"] .kodus-detail-empty {
      color: #6c757d;
    }
    body[data-theme="light"] .swal2-popup.kodus-detail-popup .swal2-actions,
    body[data-theme="light"] .swal2-popup.kodus-edit-popup .swal2-actions {
      background: linear-gradient(180deg, rgba(255, 255, 255, 0), #ffffff 34%);
    }
    body[data-theme="light"] .kodus-form-shell .form-control,
    body[data-theme="light"] .kodus-form-shell textarea,
    body[data-theme="light"] .kodus-form-shell .select2-container--bootstrap4 .select2-selection--multiple,
    body[data-theme="light"] .kodus-edit-shell .form-control,
    body[data-theme="light"] .kodus-edit-shell textarea,
    body[data-theme="light"] .kodus-edit-shell .select2-container--bootstrap4 .select2-selection--multiple {
      background: #ffffff;
      border-color: rgba(13, 110, 253, 0.14);
      color: #212529;
    }
    body[data-theme="light"] .kodus-form-shell .select2-container--bootstrap4 .select2-selection--multiple,
    body[data-theme="light"] .kodus-edit-shell .select2-container--bootstrap4 .select2-selection--multiple {
      background: linear-gradient(180deg, #ffffff, #f8fbff);
      box-shadow: 0 0.4rem 1rem rgba(13, 110, 253, 0.06);
    }
    body[data-theme="light"] .kodus-form-shell .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice,
    body[data-theme="light"] .kodus-edit-shell .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice {
      border-color: rgba(13, 110, 253, 0.22);
      background: rgba(13, 110, 253, 0.1);
      color: #1f2937;
    }
    body[data-theme="light"] .kodus-form-shell .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__remove,
    body[data-theme="light"] .kodus-edit-shell .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__remove {
      color: #495057;
    }
    body[data-theme="light"] .kodus-form-shell .select2-container--bootstrap4 .select2-search__field,
    body[data-theme="light"] .kodus-edit-shell .select2-container--bootstrap4 .select2-search__field {
      color: #212529 !important;
    }
    body[data-theme="light"] .swal2-container .select2-dropdown {
      background: #ffffff;
      color: #212529;
      border-color: rgba(13, 110, 253, 0.14);
      box-shadow: 0 18px 38px rgba(15, 23, 42, 0.16);
    }
    body[data-theme="light"] .swal2-container .select2-search--dropdown {
      background: #f8fbff;
    }
    body[data-theme="light"] .swal2-container .select2-search--dropdown .select2-search__field {
      background: #ffffff;
      color: #212529;
      border-color: rgba(13, 110, 253, 0.16);
    }
    body[data-theme="light"] .swal2-container .select2-results__option {
      color: #212529;
    }
    body[data-theme="light"] .swal2-container .select2-results__option[aria-selected="true"] {
      background: rgba(13, 110, 253, 0.08);
    }
    body[data-theme="light"] .swal2-container .select2-results__option--highlighted[aria-selected] {
      background: rgba(13, 110, 253, 0.15);
      color: #0f172a;
    }
    body[data-theme="light"] .kodus-recipient-email {
      color: #6c757d;
    }
    body[data-theme="light"] .swal2-popup.kodus-form-popup .swal2-actions {
      background: linear-gradient(180deg, rgba(255, 255, 255, 0), #ffffff 34%);
    }
    body[data-theme="light"] .kodus-form-suggestions {
      background: #ffffff;
      border-color: rgba(13, 110, 253, 0.14);
    }
    body[data-theme="light"] .kodus-form-suggestions .list-group-item {
      color: #212529;
    }
    body[data-theme="light"] .kodus-form-cancel {
      background: rgba(108, 117, 125, 0.12);
      color: #495057;
    }
    .kodus-upload-progress {
      display: none;
      margin-top: 0.85rem;
      padding: 0.8rem 0.9rem;
      border-radius: 14px;
      border: 1px solid var(--kodus-detail-border, rgba(255, 255, 255, 0.12));
      background: var(--kodus-detail-panel-strong, rgba(255, 255, 255, 0.08));
    }
    .kodus-upload-progress.is-visible {
      display: block;
    }
    .kodus-upload-progress-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      margin-bottom: 0.55rem;
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--kodus-detail-text, #f8f9fa);
    }
    .kodus-upload-progress-track {
      height: 0.65rem;
      overflow: hidden;
      border-radius: 999px;
      background: rgba(108, 117, 125, 0.24);
    }
    .kodus-upload-progress-bar {
      width: 0%;
      height: 100%;
      border-radius: inherit;
      background: linear-gradient(90deg, #0d6efd, #20c997);
      transition: width 0.18s ease;
    }
    body[data-theme="light"] .kodus-upload-progress {
      background: #ffffff;
      border-color: rgba(13, 110, 253, 0.14);
      box-shadow: 0 0.4rem 1rem rgba(13, 110, 253, 0.06);
    }
    body[data-theme="light"] .kodus-upload-progress-row {
      color: #212529;
    }
    .kodus-selected-files {
      display: none;
      margin-top: 0.65rem;
      border: 1px solid var(--kodus-detail-border, rgba(255, 255, 255, 0.12));
      border-radius: 10px;
      overflow: hidden;
      background: rgba(255, 255, 255, 0.05);
    }
    .kodus-selected-files.is-visible {
      display: block;
    }
    .kodus-selected-file {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      padding: 0.55rem 0.65rem;
      border-top: 1px solid var(--kodus-detail-border, rgba(255, 255, 255, 0.1));
      color: var(--kodus-detail-text, #f8f9fa);
      font-size: 0.82rem;
    }
    .kodus-selected-file:first-child {
      border-top: 0;
    }
    .kodus-selected-file-name {
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .kodus-selected-file-size {
      color: var(--kodus-detail-muted, #adb5bd);
      font-size: 0.76rem;
      white-space: nowrap;
    }
    .kodus-selected-file-remove {
      flex: 0 0 auto;
      width: 1.85rem;
      height: 1.85rem;
      border: 0;
      border-radius: 6px;
      background: rgba(220, 53, 69, 0.12);
      color: #ff8a9a;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    body[data-theme="light"] .kodus-selected-files {
      background: #ffffff;
      border-color: rgba(13, 110, 253, 0.14);
    }
    body[data-theme="light"] .kodus-selected-file {
      color: #212529;
      border-top-color: rgba(13, 110, 253, 0.1);
    }
    body[data-theme="light"] .kodus-selected-file-size {
      color: #6c757d;
    }
    body[data-theme="light"] .kodus-selected-file-remove {
      background: rgba(220, 53, 69, 0.1);
      color: #dc3545;
    }
    @media (max-width: 576px) {
      .swal2-popup.kodus-form-popup {
        padding: 1.05rem;
      }
      .kodus-form-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">

  <!-- Preloader -->

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Outgoing Documents</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../home">Home</a></li>
              <li class="breadcrumb-item active">Outgoing</li>
            </ol>
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="content">
      <div class="container-fluid">
        <div class="card">
            <!-- card-header -->
            <div class="card-header">
                <h3 class="card-title">Outgoing Documents</h3>
            </div>
            <!-- /.card-header -->
          <div class="card-body">
            <input type="hidden" id="user-type" value="<?= htmlspecialchars($userType ?? '') ?>">
            <div id="track-documents-container" style="display: none;">
              <button id="track-documents" class="btn btn-outline-primary btn-xs kodus-track-btn">Track Outgoing Documents</button>
            </div><br>
            <div class="table-container kodus-table-scroll" style="--kodus-table-min-width: 1200px;">
              <table id="Outgoing-table" class="table table-bordered table-striped" style="text-align: center; width: 100%; table-layout: auto;">
                <thead style="font-size: 10px;">
                  <tr>
                    <th style="max-width:5%;">Action</th>
                    <th style="max-width:5%;">Date</th>
                    <th style="max-width:7%;">DTN / DRN</th>
                    <th style="max-width:19%;">Description</th>
                    <th style="max-width:18%;">Remarks</th>
                    <th class="tracking-file-column" style="max-width:13%;">File</th>
                    <th style="max-width:13%;">Receiving Office / Personnel</th>
                    <th style="max-width:13%;">Date & Time Forwarded</th>
                    <th style="max-width:6%;">User Log</th>
                  </tr>
                </thead>
                <tbody style="font-size: 10px;">
                  <!-- Table data here. -->
                </tbody>
              </table>
            </div>
            <!-- <a href="#" class="card-link">Card link</a>
            <a href="#" class="card-link">Another link</a> -->
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->

<!-- jQuery -->
<script src="<?php echo $app_root; ?>plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="<?php echo $app_root; ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables  & Plugins -->
<script src="<?php echo $app_root; ?>plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/jszip/jszip.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<script src="../plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/select2/js/select2.full.min.js"></script>
<!-- AdminLTE App -->
<script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>

<script>
const trackingRecipientOptions = <?php echo json_encode($trackingRecipientOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

function escapeHtml(value) {
    return $('<div>').text(value ?? '').html();
}

function formatFallback(value, fallback = 'Not set') {
    const text = String(value ?? '').trim();
    return text !== '' ? escapeHtml(text) : `<span class="kodus-detail-empty">${escapeHtml(fallback)}</span>`;
}

function renderFileLink(fileName) {
    const normalized = String(fileName ?? '').trim();
    if (normalized === '') {
        return '<span class="kodus-detail-empty">No file attached</span>';
    }

    const files = splitTrackingFileNames(normalized);
    return files.map(file => {
        const safeName = escapeHtml(file);
        const safeUrl = encodeURIComponent(file).replace(/%2F/g, '/');
        const popupUrl = `uploads/${safeUrl}`;
        return `<a class="kodus-detail-link d-inline-block mr-2" data-url="${escapeAttribute(popupUrl)}" onclick="openPopup(this.dataset.url)" href="javascript:void(0)">${safeName}</a>`;
    }).join('');
}

function splitTrackingFileNames(fileName) {
    return String(fileName ?? '').split(',').map(file => file.trim()).filter(Boolean);
}

function renderExistingFileRemovalList(fileName) {
    const files = splitTrackingFileNames(fileName);
    if (files.length === 0) {
        return '<span class="kodus-detail-empty">No file attached</span>';
    }

    const rows = files.map(file => `
        <div class="kodus-selected-file" data-existing-file="${escapeAttribute(file)}">
            <span class="kodus-selected-file-name">${escapeHtml(file)}</span>
            <button type="button" class="kodus-selected-file-remove" title="Remove ${escapeAttribute(file)}" aria-label="Remove ${escapeAttribute(file)}">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `).join('');

    return `<div id="existingFileList" class="kodus-selected-files is-visible" aria-live="polite">${rows}</div>`;
}

function bindExistingFileRemovalList(listId = 'existingFileList') {
    const list = document.getElementById(listId);
    if (!list) {
        return;
    }

    list.addEventListener("click", event => {
        const removeButton = event.target.closest(".kodus-selected-file-remove");
        if (!removeButton) {
            return;
        }

        removeButton.closest(".kodus-selected-file")?.remove();
        if (!list.querySelector(".kodus-selected-file")) {
            list.classList.remove("is-visible");
            list.innerHTML = '<span class="kodus-detail-empty d-block p-2">No file attached</span>';
        }
    });
}

function appendKeptExistingFiles(formData, listId = 'existingFileList') {
    formData.append("keep_existing_files_submitted", "1");

    const list = document.getElementById(listId);
    if (!list) {
        return;
    }

    list.querySelectorAll("[data-existing-file]").forEach(row => {
        const fileName = String(row.dataset.existingFile || "").trim();
        if (fileName !== "") {
            formData.append("keep_existing_files[]", fileName);
        }
    });
}

function renderTrackingFileCell(fileName) {
    const normalized = String(fileName ?? '').trim();
    if (normalized === '') {
        return '<span class="kodus-detail-empty">No file</span>';
    }

    const files = normalized.split(',').map(file => file.trim()).filter(Boolean);
    const visibleFiles = files.slice(0, 4);
    const links = visibleFiles.map(file => {
        const safeName = escapeHtml(file);
        const safeUrl = encodeURIComponent(file).replace(/%2F/g, '/');
        const popupUrl = `uploads/${safeUrl}`;
        return `<a class="tracking-file-link" title="${escapeAttribute(file)}" data-url="${escapeAttribute(popupUrl)}" onclick="openPopup(this.dataset.url)" href="javascript:void(0)">${safeName}</a>`;
    }).join('');
    const more = files.length > visibleFiles.length
        ? `<span class="tracking-file-more">+${files.length - visibleFiles.length} more</span>`
        : '';

    return `<div class="tracking-file-list">${links}${more}</div>`;
}

function escapeAttribute(value) {
    return escapeHtml(value).replace(/"/g, '&quot;');
}

function renderFriendlyFormShell(options) {
    const eyebrow = escapeHtml(options.eyebrow || 'Document Form');
    const icon = escapeHtml(options.icon || 'fa-file-alt');
    const title = options.title || '';
    const subtitle = options.subtitle || '';
    const sections = options.sections || '';

    return `
        <form id="${escapeAttribute(options.formId || 'documentForm')}" class="kodus-form-shell">
            <div class="kodus-form-hero">
                <div class="kodus-form-eyebrow"><i class="fas ${icon}"></i>${eyebrow}</div>
                <h3 class="kodus-form-title">${title}</h3>
                <p class="kodus-form-subtitle">${subtitle}</p>
            </div>
            ${sections}
        </form>
    `;
}

function splitRecipientValue(value) {
    return String(value || '').split(/[;,]/).map(item => item.trim()).filter(Boolean);
}

function renderReceivingOfficeField(options = {}) {
    const required = options.required ? 'required' : '';
    const help = options.help || 'Select KODUS users or type external emails, offices, or personnel. Only valid emails receive email notices.';
    const optionHtml = trackingRecipientOptions.map(recipient => {
        const label = escapeHtml(recipient.label || recipient.email || '');
        return `<option value="${escapeAttribute(recipient.label || recipient.email || '')}" data-email="${escapeAttribute(recipient.email || '')}" data-name="${escapeAttribute(recipient.name || '')}">${label}</option>`;
    }).join('');

    return `
        <label for="receiving_office_recipients">Receiving Office / Personnel</label>
        <select id="receiving_office_recipients" name="receiving_office_recipients[]" class="form-control kodus-recipient-picker" multiple ${required}>
            ${optionHtml}
        </select>
        <input type="hidden" id="receiving_office" name="receiving_office" ${required}>
        <span class="kodus-form-help">${escapeHtml(help)}</span>
    `;
}

function recipientInitials(name, email) {
    const source = String(name || email || '').trim();
    const parts = source.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return source.slice(0, 2).toUpperCase() || 'R';
}

function renderRecipientVisual(option, compact = false) {
    if (!option.id) {
        return option.text;
    }

    const dataset = option.element ? option.element.dataset : {};
    const label = dataset.name || option.text || '';
    const email = dataset.email || (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(option.text || '') ? option.text : '');
    const initials = recipientInitials(label, email);
    const className = compact ? 'kodus-recipient-chip' : 'kodus-recipient-option';

    return $(`
        <span class="${className}">
            <span class="kodus-recipient-avatar">${escapeHtml(initials)}</span>
            <span class="kodus-recipient-copy">
                <span class="kodus-recipient-name">${escapeHtml(label || option.text || '')}</span>
                ${email ? `<span class="kodus-recipient-email">${escapeHtml(email)}</span>` : ''}
            </span>
        </span>
    `);
}

function syncReceivingOfficeField() {
    const picker = document.getElementById('receiving_office_recipients');
    const hidden = document.getElementById('receiving_office');
    if (!picker || !hidden) {
        return '';
    }

    const values = Array.from(picker.selectedOptions || []).map(option => String(option.value || option.text || '').trim()).filter(Boolean);
    hidden.value = values.join(', ');
    return hidden.value;
}

function bindReceivingOfficePicker(initialValue = '') {
    const picker = $('#receiving_office_recipients');
    if (!picker.length) {
        return;
    }

    splitRecipientValue(initialValue).forEach(value => {
        if (!picker.find('option').filter(function() { return this.value === value; }).length) {
            picker.append(new Option(value, value, true, true));
        } else {
            picker.find('option').filter(function() { return this.value === value; }).prop('selected', true);
        }
    });

    if ($.fn.select2) {
        picker.select2({
            theme: 'bootstrap4',
            width: '100%',
            tags: true,
            tokenSeparators: [',', ';'],
            dropdownParent: picker.closest('.swal2-popup'),
            placeholder: 'Select users or type recipients',
            templateResult: option => renderRecipientVisual(option, false),
            templateSelection: option => renderRecipientVisual(option, true),
            escapeMarkup: markup => markup,
            createTag: function(params) {
                const term = $.trim(params.term || '');
                return term ? { id: term, text: term, newTag: true } : null;
            }
        });
    }

    picker.on('select2:select', function() {
        $('.select2-search__field').val('');
    });
    picker.on('change select2:select select2:unselect', syncReceivingOfficeField);
    syncReceivingOfficeField();
}

function renderOutgoingDetails(rowData) {
    return `
        <div class="kodus-detail-modal">
            <div class="kodus-detail-hero">
                <div>
                    <span class="kodus-detail-eyebrow">Outgoing Document</span>
                    <h3 class="kodus-detail-title">${formatFallback(rowData.tracking_number, 'No tracking number')}</h3>
                    <p class="kodus-detail-subtitle">${formatFallback(rowData.description, 'No description provided')}</p>
                </div>
                <div class="kodus-detail-pill">${formatFallback(rowData.date_out, 'No outgoing date')}</div>
            </div>

            <div class="kodus-detail-grid">
                <div class="kodus-detail-stat">
                    <span class="kodus-detail-label">Outgoing Date</span>
                    <span class="kodus-detail-value kodus-detail-value--strong">${formatFallback(rowData.date_out)}</span>
                </div>
                <div class="kodus-detail-stat">
                    <span class="kodus-detail-label">DTN / DRN</span>
                    <span class="kodus-detail-value kodus-detail-value--strong">${formatFallback(rowData.tracking_number)}</span>
                </div>
                <div class="kodus-detail-stat">
                    <span class="kodus-detail-label">Forwarded At</span>
                    <span class="kodus-detail-value kodus-detail-value--strong">${formatFallback(rowData.date_forwarded, 'Not forwarded yet')}</span>
                </div>
            </div>

            <div class="kodus-detail-section">
                <h6 class="kodus-detail-section-title">Document Summary</h6>
                <div class="kodus-detail-section-grid">
                    <div>
                        <span class="kodus-detail-label">Description</span>
                        <span class="kodus-detail-value">${formatFallback(rowData.description, 'No description provided')}</span>
                    </div>
                    <div>
                        <span class="kodus-detail-label">Remarks</span>
                        <span class="kodus-detail-value">${formatFallback(rowData.remarks, 'No remarks recorded')}</span>
                    </div>
                </div>
            </div>

            <div class="kodus-detail-section">
                <h6 class="kodus-detail-section-title">Routing and Accountability</h6>
                <div class="kodus-detail-section-grid">
                    <div>
                        <span class="kodus-detail-label">Receiving Office / Personnel</span>
                        <span class="kodus-detail-value">${formatFallback(rowData.receiving_office, 'No receiving office recorded')}</span>
                    </div>
                    <div>
                        <span class="kodus-detail-label">User Log</span>
                        <span class="kodus-detail-value">${formatFallback(rowData.user_log, 'No user activity recorded')}</span>
                    </div>
                </div>
            </div>

            <div class="kodus-detail-section mb-0">
                <h6 class="kodus-detail-section-title">Attachment</h6>
                <span class="kodus-detail-label">Attached File</span>
                <span class="kodus-detail-value">${renderFileLink(rowData.file_name)}</span>
            </div>
        </div>
    `;
}

function getOutgoingRowData(triggerElement) {
    const tableRow = $(triggerElement).closest("tr");
    return table.row(tableRow).data() || table.row(tableRow.prev()).data();
}

function openOutgoingDetails(rowData) {
    if (!rowData) {
        return;
    }

    const date_out = rowData.date_out;
    const date_forwarded = rowData.date_forwarded;

    Swal.fire({
        title: "Document Details",
        width: 920,
        customClass: {
            popup: 'kodus-detail-popup',
            confirmButton: 'kodus-form-confirm',
            cancelButton: 'kodus-form-cancel'
        },
        html: renderOutgoingDetails(rowData),
        buttonsStyling: false,
        showCancelButton: true,
        showConfirmButton: userType === 'admin' || userType === 'aa',
        confirmButtonText: '<i class="fas fa-pen mr-1"></i> Edit',
        cancelButtonText: 'Close',
        preConfirm: () => {
            showEditForm(rowData, date_out, date_forwarded);
        }
    });
}

      let table = $("#Outgoing-table").DataTable({
          "responsive": false,
          "autoWidth": false,
          "processing": false, // Show the processing indicator
          "serverSide": true, // Enable server-side processing
          "ajax": {
              "url": "fetch_data_out.php", // The PHP file to fetch data
              "type": "GET",
          },
          "columns": [
              {
                  "data": null, // First column for actions
                  "render": function(data, type, row) {
                      return `
                          <span class="kodus-row-actions">
                              <button class="btn btn-info btn-sm edit-btn" data-id="${row.id}" style="font-size:10px;" title="View details" aria-label="View details">
                                  <i class="nav-icon fas fa-eye"></i>
                              </button>
                          </span>
                      `;
                  },
                  "orderable": false, // Prevent sorting on actions column
                  "searchable": false
              },
              { "data": "date_out" },
              { "data": "tracking_number" },
              { "data": "description" },
              { "data": "remarks" },
              {
                  "data": "file_name",
                  "className": "tracking-file-column",
                  "render": function(data, type) {
                      return type === "display" ? renderTrackingFileCell(data) : data;
                  }
              },
              { "data": "receiving_office" },
              { "data": "date_forwarded" },
              { "data": "user_log" }
          ],
          "lengthChange": true,
          "pageLength": 10, // Default rows per page
          "paging": true,
          "scrollX": true,
          "columnDefs": [
              { "targets": 0, "width": "5rem" },
              { "targets": 1, "width": "7rem" },
              { "targets": 2, "width": "8rem" },
              { "targets": 3, "width": "16rem" },
              { "targets": 4, "width": "12rem" },
              { "targets": 5, "width": "14rem", "className": "tracking-file-column" },
              { "targets": 6, "width": "11rem" },
              { "targets": 7, "width": "11rem" },
              { "targets": 8, "width": "8rem" }
          ],
          "initComplete": function() {
              this.api().columns.adjust();
          },
          "drawCallback": function() {
              this.api().columns.adjust();
          },
          //"dom": 'Bfrtip',
          //"buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
      });

      if (window.KODUSLiveRefresh) {
          window.KODUSLiveRefresh.watchDataTable({
              channels: ['outgoing_table'],
              table: table,
              socket: {
                  key: 'data-tracking-out',
                  channel: 'kodus.outgoing',
                  events: ['outgoing.changed']
              }
          });
      }
</script>
<script>
$(document).on("click", ".edit-btn", function (event) {
    event.preventDefault();
    event.stopPropagation();
    openOutgoingDetails(getOutgoingRowData(this));
});

$('#Outgoing-table tbody').on('click', 'tr td:not(:first-child)', function (event) {
    if ($(event.target).closest('button, a, input, textarea, select, label').length) {
        return;
    }

    openOutgoingDetails(getOutgoingRowData(this));
});

// Function to open the file in a popup window
function openPopup(url) {
    // Get screen width and height
    let screenWidth = window.screen.width;
    let screenHeight = window.screen.height;

    // Define popup dimensions
    let popupWidth = 800;
    let popupHeight = window.screen.height;

    // Calculate center position
    let left = (screenWidth - popupWidth) / 2;
    let top = (screenHeight - popupHeight) / 2;

    // Open popup window without address bar, centered on screen
    var popupWindow = window.open(url, "_blank", `width=${popupWidth},height=${popupHeight},top=${top},left=${left},scrollbars=yes,resizable=yes,toolbar=no,menubar=no,location=no,status=no,directories=no`);
    popupWindow.document.write('<html><head><title>File Preview</title></head><body><iframe src="' + url + '" width="100%" height="100%"></iframe></body></html>');
}

function appendCsrfToken(formData) {
    if (formData instanceof FormData && !formData.has("csrf_token")) {
        formData.append("csrf_token", window.KODUS_CSRF_TOKEN || "");
    }

    return formData;
}

function parseJsonResponse(response) {
    return response.text().then(text => {
        try {
            const data = text ? JSON.parse(text) : {};

            if (!response.ok) {
                throw new Error(data.message || data.error || `HTTP ${response.status}`);
            }

            return data;
        } catch (error) {
            throw new Error(text || `HTTP ${response.status}`);
        }
    });
}

function renderUploadProgress(progressId) {
    return `
        <div id="${escapeAttribute(progressId)}" class="kodus-upload-progress" aria-live="polite">
            <div class="kodus-upload-progress-row">
                <span class="kodus-upload-progress-label">Preparing upload</span>
                <span class="kodus-upload-progress-value">0%</span>
            </div>
            <div class="kodus-upload-progress-track">
                <div class="kodus-upload-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
            </div>
        </div>
    `;
}

function updateUploadProgress(progressId, percent, label) {
    const progress = document.getElementById(progressId);
    if (!progress) {
        return;
    }

    const normalized = Math.max(0, Math.min(100, Math.round(percent)));
    const progressBar = progress.querySelector(".kodus-upload-progress-bar");
    const progressValue = progress.querySelector(".kodus-upload-progress-value");
    const progressLabel = progress.querySelector(".kodus-upload-progress-label");

    progress.classList.add("is-visible");
    if (progressBar) {
        progressBar.style.width = `${normalized}%`;
        progressBar.setAttribute("aria-valuenow", String(normalized));
    }
    if (progressValue) {
        progressValue.textContent = `${normalized}%`;
    }
    if (progressLabel && label) {
        progressLabel.textContent = label;
    }
}

function setUploadReadyProgress(progressId, label) {
    const progress = document.getElementById(progressId);
    if (!progress) {
        return;
    }

    const progressBar = progress.querySelector(".kodus-upload-progress-bar");
    const progressValue = progress.querySelector(".kodus-upload-progress-value");
    const progressLabel = progress.querySelector(".kodus-upload-progress-label");

    progress.classList.add("is-visible");
    if (progressBar) {
        progressBar.style.width = "0%";
        progressBar.setAttribute("aria-valuenow", "0");
    }
    if (progressValue) {
        progressValue.textContent = "Ready";
    }
    if (progressLabel) {
        progressLabel.textContent = label;
    }
}

function formatUploadBytes(bytes) {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1).replace(/\.0$/, "")} MB`;
    }
    if (bytes >= 1024) {
        return `${(bytes / 1024).toFixed(1).replace(/\.0$/, "")} KB`;
    }
    return `${bytes} bytes`;
}

function fileSelectionKey(file) {
    return [file.name, file.size, file.lastModified].join("::");
}

function syncAccumulatedFileSelection(fileInput, files) {
    if (!window.DataTransfer) {
        return false;
    }

    const transfer = new DataTransfer();
    files.forEach(file => transfer.items.add(file));
    fileInput.files = transfer.files;
    return true;
}

function selectedFileListId(fileInputId, progressId) {
    return `${fileInputId}-${progressId}-selected-files`;
}

function ensureSelectedFileList(fileInput, progressId) {
    const listId = selectedFileListId(fileInput.id, progressId);
    let list = document.getElementById(listId);
    if (!list) {
        fileInput.insertAdjacentHTML("afterend", `<div id="${escapeAttribute(listId)}" class="kodus-selected-files" aria-live="polite"></div>`);
        list = document.getElementById(listId);
    }
    return list;
}

function refreshSelectedFileList(fileInput, progressId) {
    const list = ensureSelectedFileList(fileInput, progressId);
    const files = Array.from(fileInput.files || []);
    list.innerHTML = "";

    if (files.length === 0) {
        list.classList.remove("is-visible");
        const progress = document.getElementById(progressId);
        progress?.classList.remove("is-visible");
        return;
    }

    list.classList.add("is-visible");
    files.forEach((file, index) => {
        const row = document.createElement("div");
        row.className = "kodus-selected-file";

        const name = document.createElement("span");
        name.className = "kodus-selected-file-name";
        name.textContent = file.name;

        const size = document.createElement("span");
        size.className = "kodus-selected-file-size";
        size.textContent = formatUploadBytes(file.size || 0);

        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "kodus-selected-file-remove";
        remove.title = `Remove ${file.name}`;
        remove.setAttribute("aria-label", `Remove ${file.name}`);
        remove.innerHTML = '<i class="fas fa-times"></i>';
        remove.addEventListener("click", () => {
            const selectedFiles = Array.isArray(fileInput.kodusSelectedFiles)
                ? fileInput.kodusSelectedFiles.slice()
                : Array.from(fileInput.files || []);
            selectedFiles.splice(index, 1);
            fileInput.kodusSelectedFiles = selectedFiles;
            syncAccumulatedFileSelection(fileInput, selectedFiles);
            refreshSelectedFileList(fileInput, progressId);

            if (selectedFiles.length > 0) {
                const totalBytes = selectedFiles.reduce((sum, selectedFile) => sum + (selectedFile.size || 0), 0);
                setUploadReadyProgress(progressId, `Selected: ${selectedFiles.length} file${selectedFiles.length === 1 ? "" : "s"} (${formatUploadBytes(totalBytes)})`);
            }
        });

        row.append(name, size, remove);
        list.appendChild(row);
    });
}

function clearAccumulatedFileSelection(fileInputId, progressId) {
    const fileInput = document.getElementById(fileInputId);
    if (!fileInput) {
        return;
    }

    fileInput.kodusSelectedFiles = [];
    if (window.DataTransfer) {
        syncAccumulatedFileSelection(fileInput, []);
    } else {
        fileInput.value = "";
    }

    const progress = document.getElementById(progressId);
    progress?.classList.remove("is-visible");
    refreshSelectedFileList(fileInput, progressId);
}

function bindUploadProgressFilePreview(fileInputId, progressId) {
    const fileInput = document.getElementById(fileInputId);
    if (!fileInput) {
        return;
    }

    ensureSelectedFileList(fileInput, progressId);
    fileInput.addEventListener("change", () => {
        const pickedFiles = Array.from(fileInput.files || []);
        if (window.DataTransfer) {
            const selectedFiles = Array.isArray(fileInput.kodusSelectedFiles) ? fileInput.kodusSelectedFiles : [];
            const selectedKeys = new Set(selectedFiles.map(fileSelectionKey));

            pickedFiles.forEach(file => {
                const key = fileSelectionKey(file);
                if (!selectedKeys.has(key)) {
                    selectedFiles.push(file);
                    selectedKeys.add(key);
                }
            });

            fileInput.kodusSelectedFiles = selectedFiles;
            syncAccumulatedFileSelection(fileInput, selectedFiles);
        }

        const files = Array.from(fileInput.files || []);
        if (files.length === 0) {
            const progress = document.getElementById(progressId);
            progress?.classList.remove("is-visible");
            refreshSelectedFileList(fileInput, progressId);
            return;
        }

        const totalBytes = files.reduce((sum, file) => sum + (file.size || 0), 0);
        const label = files.length === 1
            ? `Selected: ${files[0].name} (${formatUploadBytes(totalBytes)})`
            : `Selected: ${files.length} files (${formatUploadBytes(totalBytes)})`;
        refreshSelectedFileList(fileInput, progressId);
        setUploadReadyProgress(progressId, label);
    });
}

function validateTrackingFormBeforeUpload(formId, progressId) {
    const form = document.getElementById(formId);
    if (!form) {
        return false;
    }

    const invalidField = Array.from(form.querySelectorAll("[required]")).find(field => {
        return !String(field.value || "").trim();
    });

    if (!invalidField) {
        return true;
    }

    const progress = document.getElementById(progressId);
    progress?.classList.remove("is-visible");
    invalidField.focus();
    Swal.showValidationMessage("Please complete the required fields before uploading.");
    return false;
}

function submitFormDataWithProgress(url, formData, progressId, labels = {}) {
    const progressLabels = {
        preparing: labels.preparing || "Preparing upload",
        transferring: labels.transferring || "Uploading file",
        processing: labels.processing || "Processing upload"
    };
    updateUploadProgress(progressId, 3, progressLabels.preparing);

    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        let progressTimer = null;
        let simulatedPercent = 3;

        const stopProgressTimer = () => {
            if (progressTimer) {
                window.clearInterval(progressTimer);
                progressTimer = null;
            }
        };

        const startProgressTimer = () => {
            stopProgressTimer();
            progressTimer = window.setInterval(() => {
                simulatedPercent = Math.min(88, simulatedPercent + (simulatedPercent < 35 ? 7 : 3));
                updateUploadProgress(progressId, simulatedPercent, progressLabels.transferring);
                if (simulatedPercent >= 88) {
                    stopProgressTimer();
                }
            }, 350);
        };

        xhr.open("POST", url, true);
        xhr.withCredentials = true;
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

        xhr.upload.addEventListener("loadstart", () => {
            simulatedPercent = Math.max(simulatedPercent, 8);
            updateUploadProgress(progressId, simulatedPercent, progressLabels.transferring);
            startProgressTimer();
        });

        xhr.upload.addEventListener("progress", event => {
            if (event.lengthComputable && event.total > 0) {
                simulatedPercent = Math.min(90, Math.max(simulatedPercent, (event.loaded / event.total) * 90));
                updateUploadProgress(progressId, simulatedPercent, progressLabels.transferring);
            } else {
                simulatedPercent = Math.max(simulatedPercent, 35);
                updateUploadProgress(progressId, simulatedPercent, progressLabels.transferring);
            }
        });

        xhr.upload.addEventListener("load", () => {
            simulatedPercent = Math.max(simulatedPercent, 92);
            updateUploadProgress(progressId, simulatedPercent, progressLabels.processing);
            stopProgressTimer();
        });

        xhr.addEventListener("load", () => {
            stopProgressTimer();
            updateUploadProgress(progressId, 100, progressLabels.processing);
            const response = {
                ok: xhr.status >= 200 && xhr.status < 300,
                status: xhr.status,
                text: () => Promise.resolve(xhr.responseText || "")
            };

            parseJsonResponse(response).then(resolve).catch(reject);
        });

        xhr.addEventListener("error", () => {
            stopProgressTimer();
            reject(new Error("Network error while uploading."));
        });
        xhr.addEventListener("abort", () => {
            stopProgressTimer();
            reject(new Error("Upload cancelled."));
        });
        xhr.send(formData);
        startProgressTimer();
    });
}

// Function to show the edit form in a SweetAlert2 modal
function showEditForm(rowData, date_out, date_forwarded) {
    Swal.fire({
        title: "Edit Document",
        customClass: {
            container: 'kodus-scrollable-swal',
            popup: 'kodus-edit-popup',
            confirmButton: 'kodus-form-confirm',
            cancelButton: 'kodus-form-cancel'
        },
        html: `
            <form id="editForm" class="kodus-edit-shell">
                <div class="kodus-edit-header">
                    <h3 class="kodus-edit-header-title">${escapeHtml(rowData.tracking_number || 'Document')}</h3>
                    <p class="kodus-edit-header-note">Update the outgoing document details, attachment, and receiving information in one place.</p>
                </div>

                <div class="kodus-edit-section">
                    <h6 class="kodus-edit-section-title">Document Info</h6>
                    <div class="kodus-edit-grid">
                        <div class="kodus-edit-field">
                            <label>Outgoing Date</label>
                            <input type="date" id="date_out" name="date_out" class="form-control" value="${date_out}" required>
                        </div>
                        <div class="kodus-edit-field">
                            <label>DTN / DRN</label>
                            <input type="text" id="tracking_number" name="tracking_number" class="form-control" value="${rowData.tracking_number}" disabled>
                        </div>
                        <div class="kodus-edit-field kodus-edit-field--full">
                            <label>Description</label>
                            <textarea id="description" name="description" class="form-control" rows="4" required>${rowData.description}</textarea>
                        </div>
                        <div class="kodus-edit-field kodus-edit-field--full">
                            <label>Remarks</label>
                            <textarea id="remarks" name="remarks" class="form-control" rows="3">${rowData.remarks}</textarea>
                        </div>
                    </div>
                </div>

                <div class="kodus-edit-section">
                    <h6 class="kodus-edit-section-title">Routing and Attachment</h6>
                    <div class="kodus-edit-grid">
                        <div class="kodus-edit-field kodus-edit-field--full">
                            <label>Attachment</label>
                            <input type="file" id="file" name="file[]" class="form-control" multiple>
                            <span class="kodus-edit-help">
                                Current file(s):
                                <span class="kodus-edit-inline-file">
                                    ${renderExistingFileRemovalList(rowData.file_name)}
                                </span>
                            </span>
                            ${renderUploadProgress('editUploadProgress')}
                        </div>
                        <div class="kodus-edit-field">
                            ${renderReceivingOfficeField({ required: true })}
                        </div>
                        <div class="kodus-edit-field">
                            <label>Date & Time Forwarded</label>
                            <input type="datetime-local" id="date_forwarded" name="date_forwarded" class="form-control" value="${date_forwarded}" required>
                        </div>
                    </div>
                </div>
            </form>
        `,
        buttonsStyling: false,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save mr-1"></i> Save Changes',
        cancelButtonText: 'Cancel',
        didOpen: () => {
            const desc = document.getElementById("description");
            desc.focus();
            // Move cursor to the end of the text
            desc.selectionStart = desc.selectionEnd = desc.value.length;
            bindExistingFileRemovalList();
            bindUploadProgressFilePreview("file", "editUploadProgress");
            bindReceivingOfficePicker(rowData.receiving_office || '');
        },
        preConfirm: () => {
            syncReceivingOfficeField();
            if (!String(document.getElementById("receiving_office")?.value || '').trim()) {
                Swal.showValidationMessage("Please enter or select at least one receiving office/personnel.");
                return false;
            }
            let formData = new FormData(document.getElementById("editForm"));
            appendKeptExistingFiles(formData);
            appendCsrfToken(formData);
            formData.append("id", rowData.id); // Append the row ID for the update query

            return submitFormDataWithProgress("update_data_out.php", formData, "editUploadProgress")
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message);
                }
                return data;
            })
            .then(() => {
                Swal.fire("Success!", "Document updated successfully.", "success").then(() => {
                    table.ajax.reload(null, false); // Refresh table without resetting pagination
                });
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        }
    });
}
</script>
<script>
document.getElementById("track-documents").addEventListener("click", function () {
    const now = new Date();
    const today = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    const date_forwarded = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    
    let incomingData = [];
    let selectedIndex = -1;

    Swal.fire({
        customClass: {
            container: 'kodus-scrollable-swal',
            popup: 'kodus-form-popup',
            confirmButton: 'kodus-form-confirm',
            cancelButton: 'kodus-form-cancel'
        },
        buttonsStyling: false,
        html: renderFriendlyFormShell({
            formId: 'trackForm',
            eyebrow: 'Outgoing Document Log',
            icon: 'fa-paper-plane',
            title: 'Create a clearer outgoing document entry',
            subtitle: 'Capture the routing, attachment, and handoff details in one clean form so the outgoing trail is easier to follow.',
            sections: `
                <div class="kodus-form-section">
                    <h6 class="kodus-form-section-title">Document Details</h6>
                    <p class="kodus-form-section-note">Use a clear description. Matching an incoming document can also help prefill related file details.</p>
                    <div class="kodus-form-grid">
                        <div class="kodus-form-field">
                            <label for="date_out">Outgoing Date</label>
                            <input type="date" id="date_out" name="date_out" class="form-control" required value="${today}">
                        </div>
                        <div class="kodus-form-field kodus-form-field--full" style="position:relative;">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3" required placeholder="Describe the document being sent out."></textarea>
                            <div id="descSuggestions" class="list-group kodus-form-suggestions"></div>
                            <span class="kodus-form-help">Start typing to reuse wording from related incoming documents.</span>
                        </div>
                        <div class="kodus-form-field">
                            <label for="file">Upload File</label>
                            <input type="file" id="file" name="file[]" class="form-control" multiple>
                            <small id="incomingFileInfo" class="kodus-form-inline-note"></small>
                            ${renderUploadProgress('trackUploadProgress')}
                        </div>
                        <div class="kodus-form-field kodus-form-field--full">
                            <label for="remarks">Remarks</label>
                            <textarea id="remarks" name="remarks" class="form-control" placeholder="Add notes, context, or reminders if needed."></textarea>
                        </div>
                    </div>
                </div>
                <div class="kodus-form-section">
                    <h6 class="kodus-form-section-title">Routing Details</h6>
                    <p class="kodus-form-section-note">These fields make the outgoing handoff easier to trace later.</p>
                    <div class="kodus-form-grid">
                        <div class="kodus-form-field">
                            ${renderReceivingOfficeField()}
                        </div>
                        <div class="kodus-form-field">
                            <label for="date_forwarded">Date & Time Forwarded</label>
                            <input type="datetime-local" id="date_forwarded" name="date_forwarded" class="form-control" value="${date_forwarded}" required>
                        </div>
                    </div>
                </div>
            `
        }),
        showCancelButton: true,
        showLoaderOnConfirm: true,
        allowOutsideClick: false,
        confirmButtonText: '<i class="fas fa-paper-plane mr-1"></i> Save Document',
        cancelButtonText: 'Cancel',
        focusConfirm: false,

        didOpen: () => {
            const $desc = $("#description");
            $desc.trigger("focus"); // Auto-focus description field
            const $suggestions = $("#descSuggestions");
            bindUploadProgressFilePreview("file", "trackUploadProgress");
            bindReceivingOfficePicker();

            // Fetch incoming descriptions
            fetch("fetch_incoming_descriptions.php")
                .then(res => res.json())
                .then(data => {
                    incomingData = data;
                });

            // Real-time suggestions
            $desc.on("input", function () {
                const query = $(this).val().toLowerCase();
                const matches = incomingData.filter(item =>
                    item.description.toLowerCase().includes(query) && query.length > 0
                );

                $suggestions.empty();
                selectedIndex = -1;

                if (matches.length > 0) {
                    matches.forEach(item => {
                        const option = $(`<a href="#" class="list-group-item list-group-item-action">${item.description}</a>`);
                        option.on("click", function (e) {
                            e.preventDefault();
                            selectSuggestion(item);
                        });
                        $suggestions.append(option);
                    });
                    $suggestions.show();
                } else {
                    $suggestions.hide();
                    $("#incomingFileInfo").hide();
                    $("#file").prop("disabled", false);
                }
            });

            // Keyboard navigation + Tab autocomplete + auto-scroll
            $desc.on("keydown", function (e) {
                const items = $suggestions.find("a");
                if (items.length === 0) return;

                if (e.key === "ArrowDown") {
                    e.preventDefault();
                    selectedIndex = (selectedIndex + 1) % items.length;
                    highlightItem(items);
                }
                else if (e.key === "ArrowUp") {
                    e.preventDefault();
                    selectedIndex = (selectedIndex - 1 + items.length) % items.length;
                    highlightItem(items);
                }
                else if (e.key === "Enter" || e.key === "Tab") {
                    if (selectedIndex >= 0) {
                        e.preventDefault();
                        const item = incomingData.find(d => d.description === $(items[selectedIndex]).text());
                        if (item) selectSuggestion(item);
                    }
                }
            });

            // Helper to highlight and auto-scroll
            function highlightItem(items) {
                items.removeClass("active");
                const current = $(items[selectedIndex]).addClass("active");

                // Scroll into view if necessary
                const container = $suggestions[0];
                const containerTop = container.scrollTop;
                const containerBottom = containerTop + container.offsetHeight;
                const itemTop = current[0].offsetTop;
                const itemBottom = itemTop + current[0].offsetHeight;

                if (itemBottom > containerBottom) {
                    container.scrollTop += (itemBottom - containerBottom);
                } else if (itemTop < containerTop) {
                    container.scrollTop -= (containerTop - itemTop);
                }
            }

            // Helper to select a suggestion
            function selectSuggestion(item) {
                $desc.val(item.description);
                $suggestions.hide();

                clearAccumulatedFileSelection("file", "trackUploadProgress");
                $("#incomingFileInfo")
                    .html(`Incoming file(s): ${renderFileLink(item.file_name)}<br><span style="color:gray;">No need to upload a file, the incoming one will be used.</span>`)
                    .show();

                $("#file").prop("disabled", true);
            }

            // Hide suggestions when clicking outside
            $(document).on("click", function (e) {
                if (!$(e.target).closest("#description, #descSuggestions").length) {
                    $suggestions.hide();
                }
            });
        },

        preConfirm: () => {
            syncReceivingOfficeField();
            if (!validateTrackingFormBeforeUpload("trackForm", "trackUploadProgress")) {
                return false;
            }

            let formData = new FormData(document.getElementById("trackForm"));
            appendCsrfToken(formData);
            return submitFormDataWithProgress("track_outgoing.php", formData, "trackUploadProgress", {
                preparing: "Preparing outgoing document",
                transferring: "Saving outgoing document",
                processing: "Finalizing save"
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || "Tracking failed");
                }
                return Swal.fire(
                    "Success!",
                    `Document has been tracked.<br>Tracking Number: <b>${data.tracking_number}</b>`,
                    "success"
                ).then(() => {
                    $('#Outgoing-table').DataTable().ajax.reload();
                });
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        }
    });
});
</script>
<script>
  const usersType = document.getElementById('user-type')?.value;
  if (['admin', 'aa'].includes(userType)) {
    document.getElementById('track-documents-container').style.display = 'block';
  }
</script>
</body>
</html>
