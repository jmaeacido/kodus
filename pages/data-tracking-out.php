<?php
  include('../header.php');
  include('../sidenav.php');

  $userType = $_SESSION['user_type'] ?? 'user';
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
    .swal2-popup.kodus-form-popup {
      width: min(780px, 94vw);
      padding: 1.35rem;
      border-radius: 22px;
      color: var(--kodus-detail-text, #f8f9fa);
      background: var(--kodus-detail-hero-end, #162034);
      box-shadow: var(--kodus-detail-shadow, 0 18px 40px rgba(15, 23, 42, 0.12));
    }
    .swal2-popup.kodus-form-popup .swal2-title,
    .swal2-popup.kodus-form-popup .swal2-html-container {
      color: var(--kodus-detail-text, #f8f9fa);
    }
    .swal2-popup.kodus-form-popup .swal2-html-container {
      margin-top: 0.75rem;
    }
    .kodus-form-shell {
      text-align: left;
      color: var(--kodus-detail-text, #f8f9fa);
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
    body[data-theme="light"] .kodus-form-section {
      background: #ffffff;
      border-color: rgba(13, 110, 253, 0.14);
      box-shadow: 0 0.4rem 1rem rgba(13, 110, 253, 0.06);
    }
    body[data-theme="light"] .kodus-form-shell .form-control,
    body[data-theme="light"] .kodus-form-shell textarea {
      background: #ffffff;
      border-color: rgba(13, 110, 253, 0.14);
      color: #212529;
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
<body class="hold-transition dark-mode sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
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
            </div>
            <div class="table-container">
              <table id="Outgoing-table" class="table table-bordered table-striped" style="text-align: center; width: 100%; table-layout: auto;">
                <thead style="font-size: 10px;">
                  <tr>
                    <th style="max-width:5%;">Action</th>
                    <th style="max-width:5%;">Date</th>
                    <th style="max-width:7%;">DTN / DRN</th>
                    <th style="max-width:19%;">Description</th>
                    <th style="max-width:18%;">Remarks</th>
                    <th style="max-width:13%;">File</th>
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
<script src="<?php echo $base_url;?>kodus/plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="<?php echo $base_url;?>kodus/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables  & Plugins -->
<script src="<?php echo $base_url;?>kodus/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/jszip/jszip.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- AdminLTE App -->
<script src="<?php echo $base_url;?>kodus/dist/js/adminlte.min.js"></script>

<script>
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

    const safeName = escapeHtml(normalized);
    const safeUrl = encodeURIComponent(normalized).replace(/%2F/g, '/');
    return `<a class="kodus-detail-link" onclick="openPopup('uploads/${safeUrl}')" href="javascript:void(0)">${safeName}</a>`;
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

      let table = $("#Outgoing-table").DataTable({
          "responsive": true,
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
              { "data": "file_name" },
              { "data": "receiving_office" },
              { "data": "date_forwarded" },
              { "data": "user_log" }
          ],
          "lengthChange": true,
          "pageLength": 10, // Default rows per page
          "paging": true,
          //"dom": 'Bfrtip',
          "responsive": false,
          //"buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
      });

      // Periodically refresh data while keeping selections
      setInterval(() => {
          table.ajax.reload(null, false); // false ensures pagination doesn't reset
      }, 60000);
</script>
<script>
$(document).on("click", ".edit-btn", function () {
    var rowData = table.row($(this).parents("tr")).data(); // Get row data

    // Get the dates directly from rowData without formatting
    let date_out = rowData.date_out;
    let date_forwarded = rowData.date_forwarded;

    // Show SweetAlert2 modal with details
    Swal.fire({
        title: "Document Details",
        width: 920,
        customClass: {
            popup: 'kodus-detail-popup'
        },
        html: renderOutgoingDetails(rowData),
        icon: "info",
        showCancelButton: true,
        showConfirmButton: userType === 'admin' || userType === 'aa',
        confirmButtonText: '<i class="fas fa-pen"></i>',
        cancelButtonText: '<i class="fas fa-times"></i>',
        preConfirm: () => {
            // Open another modal for editing the row details
            showEditForm(rowData, date_out, date_forwarded);
        }
    });
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

// Function to show the edit form in a SweetAlert2 modal
function showEditForm(rowData, date_out, date_forwarded) {
    Swal.fire({
        title: "Edit Document",
        customClass: {
            popup: 'kodus-edit-popup'
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
                            <input type="file" id="file" name="file" class="form-control">
                            <span class="kodus-edit-help">
                                Current file:
                                <span class="kodus-edit-inline-file">
                                    ${rowData.file_name 
                                        ? `<a class="kodus-detail-link" onclick="openPopup('uploads/${rowData.file_name}')" href="javascript:void(0)">${rowData.file_name}</a>
                                           <label class="kodus-edit-check">
                                               <input type="checkbox" id="remove_file" name="remove_file" value="1"> Remove file
                                           </label>`
                                        : '<span class="kodus-detail-empty">No file attached</span>'}
                                </span>
                            </span>
                        </div>
                        <div class="kodus-edit-field">
                            <label>Receiving Office / Personnel</label>
                            <input type="text" id="receiving_office" name="receiving_office" class="form-control" value="${rowData.receiving_office}" required>
                        </div>
                        <div class="kodus-edit-field">
                            <label>Date & Time Forwarded</label>
                            <input type="datetime-local" id="date_forwarded" name="date_forwarded" class="form-control" value="${date_forwarded}" required>
                        </div>
                    </div>
                </div>
            </form>
        `,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save"></i>',
        cancelButtonText: '<i class="fas fa-times"></i>',
        didOpen: () => {
            const desc = document.getElementById("description");
            desc.focus();
            // Move cursor to the end of the text
            desc.selectionStart = desc.selectionEnd = desc.value.length;
        },
        preConfirm: () => {
            let formData = new FormData(document.getElementById("editForm"));
            formData.append("id", rowData.id); // Append the row ID for the update query

            // Append remove_file flag if checked
            if (document.getElementById("remove_file")?.checked) {
                formData.append("remove_file", "1");
            }

            return fetch("update_data_out.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
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
        title: "Track Outgoing Document",
        customClass: {
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
                            <input type="file" id="file" name="file" class="form-control">
                            <small id="incomingFileInfo" class="kodus-form-inline-note"></small>
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
                            <label for="receiving_office">Receiving Office / Personnel</label>
                            <input id="receiving_office" name="receiving_office" class="form-control" placeholder="Enter the destination office or recipient">
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
        allowOutsideClick: false,
        confirmButtonText: '<i class="fas fa-paper-plane mr-1"></i> Save Document',
        cancelButtonText: 'Cancel',
        focusConfirm: false,

        didOpen: () => {
            const $desc = $("#description");
            $desc.trigger("focus"); // Auto-focus description field
            const $suggestions = $("#descSuggestions");

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

                $("#incomingFileInfo")
                    .html(`Incoming file: <u>${item.file_name}</u> (click to preview)<br><span style="color:gray;">No need to upload a file, the incoming one will be used.</span>`)
                    .off("click")
                    .on("click", function () {
                        openPopup("uploads/" + item.file_name);
                    })
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
            let formData = new FormData(document.getElementById("trackForm"));
            return fetch("track_Outgoing.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
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
