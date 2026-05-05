<?php
include('../header.php');
include('../sidenav.php');

$isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
$canEditMebRecords = in_array((string) ($_SESSION['user_type'] ?? ''), ['admin', 'editor'], true);
$selectedFiscalYear = isset($_SESSION['selected_year']) ? (int) $_SESSION['selected_year'] : null;
$importFlash = $_SESSION['meb_import_flash'] ?? null;
unset($_SESSION['meb_import_flash']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | MEB</title>
  <style>
    .kodus-detail-action {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      margin-top: 0.9rem;
      padding: 0.5rem 0.9rem;
      border-radius: 999px;
      border: 1px solid rgba(13, 110, 253, 0.35);
      background: rgba(13, 110, 253, 0.12);
      color: #8fc2ff;
      font-size: 0.82rem;
      font-weight: 700;
      text-decoration: none;
      transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
    }

    .kodus-detail-action:hover,
    .kodus-detail-action:focus {
      color: #ffffff;
      background: rgba(13, 110, 253, 0.24);
      border-color: rgba(13, 110, 253, 0.55);
      text-decoration: none;
      transform: translateY(-1px);
    }

    body[data-theme="light"] .kodus-detail-action {
      color: #0b5ed7;
      background: rgba(13, 110, 253, 0.08);
      border-color: rgba(13, 110, 253, 0.22);
    }

    body[data-theme="light"] .kodus-detail-action:hover,
    body[data-theme="light"] .kodus-detail-action:focus {
      color: #0a58ca;
      background: rgba(13, 110, 253, 0.16);
      border-color: rgba(13, 110, 253, 0.3);
    }

    .meb-batch-actions {
      display: grid;
      gap: 0.75rem;
      max-width: 720px;
      margin: 0.85rem 0 0;
      padding: 0.85rem;
      border: 1px solid rgba(148, 163, 184, 0.24);
      border-radius: 8px;
      background: rgba(15, 23, 42, 0.2);
    }

    .meb-batch-actions__header,
    .meb-batch-actions__tools {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .meb-batch-actions__title {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin: 0;
      font-size: 0.95rem;
      font-weight: 700;
    }

    .meb-batch-actions__meta {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.25rem 0.55rem;
      border-radius: 999px;
      background: rgba(13, 110, 253, 0.12);
      color: #8fc2ff;
      font-size: 0.78rem;
      font-weight: 700;
    }

    .meb-batch-actions__search {
      min-width: 220px;
      max-width: 320px;
      border-radius: 6px;
    }

    .meb-batch-list {
      display: grid;
      gap: 0.45rem;
      max-height: 220px;
      overflow: auto;
      padding-right: 0.25rem;
    }

    .meb-batch-option {
      display: grid;
      grid-template-columns: auto 1fr auto;
      align-items: center;
      gap: 0.65rem;
      margin: 0;
      padding: 0.55rem 0.65rem;
      border: 1px solid rgba(148, 163, 184, 0.18);
      border-radius: 6px;
      background: rgba(255, 255, 255, 0.04);
      cursor: pointer;
    }

    .meb-batch-option:hover {
      border-color: rgba(13, 110, 253, 0.42);
      background: rgba(13, 110, 253, 0.09);
    }

    .meb-batch-option__id {
      font-weight: 700;
    }

    .meb-batch-option__count,
    .meb-batch-empty {
      color: #9ca3af;
      font-size: 0.82rem;
    }

    .meb-batch-actions__footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    body[data-theme="light"] .meb-batch-actions {
      background: #f8fafc;
      border-color: rgba(15, 23, 42, 0.1);
    }

    body[data-theme="light"] .meb-batch-actions__meta {
      color: #0b5ed7;
      background: rgba(13, 110, 253, 0.08);
    }

    body[data-theme="light"] .meb-batch-option {
      background: #ffffff;
      border-color: rgba(15, 23, 42, 0.1);
    }

    body[data-theme="light"] .meb-batch-option__count,
    body[data-theme="light"] .meb-batch-empty {
      color: #64748b;
    }

    @media (max-width: 576px) {
      .meb-batch-actions__search,
      .meb-batch-actions__footer .btn {
        width: 100%;
        max-width: none;
      }
    }
  </style>
</head>
<body>
<div class="wrapper">


  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Masterlist of Eligible Beneficiaries</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../home">Home</a></li>
              <li class="breadcrumb-item active">MEB</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header d-flex align-items-center">
            <h4 class="m-0 flex-grow-1">Master list of Eligible Beneficiaries</h4>
            <?php if ($isAdmin): ?>
            <form action="import" method="POST" enctype="multipart/form-data" class="mb-0" id="mebImportForm" data-no-loader="true">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
              <label for="excelFile" class="btn btn-info btn-sm" style="font-size: 10px; position: relative; top: 4px;">Choose Excel File:</label>
              <input type="file" name="excelFile" id="excelFile" accept=".xlsx, .xls" style="font-size: 10px; display: none;" onchange="displayFileName()">
              <span id="file-name"></span>
              <button type="submit" class="btn btn-success btn-sm" name="import" id="mebImportSubmit" style="font-size: 10px; width: 60px;">Import</button>
            </form>&nbsp;
            <?php endif; ?>
            <button id="generateProfileBtn" class="btn btn-primary btn-sm" style="font-size: 10px; width: auto;">Generate Profile File</button>&nbsp;
            <button id="exportBtn" class="btn btn-info btn-sm" style="font-size: 10px; width: auto;">Export to Excel</button>
          </div>
          <div class="table-container kodus-table-scroll" style="--kodus-table-min-width: 1800px;">
            <?php if ($isAdmin): ?>
            <form id="bulkActionForm" action="bulk_action" method="POST">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <table id="table1" class="table table-bordered table-striped" style="text-align: center; width: 100%; table-layout: auto;">
              <thead>
                <tr>
                  <th rowspan="3">Action</th>
                  <?php if ($isAdmin): ?>
                  <th rowspan="3"></th>
                  <?php endif; ?>
                  <th style="width: 10px;" rowspan="3">NO.</th>
                  <th colspan="4" rowspan="2">NAME</th>
                  <th style="width: 10px;" rowspan="3">PUROK</th>
                  <th style="width: 10px;" rowspan="3">BARANGAY</th>
                  <th style="width: 10px;" rowspan="3">LGU</th>
                  <th style="width: 10px;" rowspan="3">PROVINCE</th>
                  <th style="width: 10px;" rowspan="3">BIRTHDATE</th>
                  <th style="width: 10px;" rowspan="3">AGE</th>
                  <th style="width: 10px;" rowspan="3">SEX</th>
                  <th style="width: 10px;" rowspan="3">CIVIL STATUS</th>
                  <th colspan="16">CLASSIFICATIONS</th>
                </tr>
                <tr class="narrow">
                  <th rowspan="2">POOR BASED ON NATIONAL HOUSEHOLD TARGETING SYSTEM FOR POVERTY REDUCTION (NHTS-PR) Listahanan 3 (P)</th>
                  <th rowspan="2">IDENTIFIED POOR, MARGINALIZED &amp; DISADVANTAGED BASED ON THE ASSESSMENT OF LSWDO (NON)</th>
                  <th colspan="14">SECTORS</th>
                </tr>
                <tr>
                  <th style="white-space: nowrap; width: 10px;">LAST NAME</th>
                  <th style="white-space: nowrap; width: 10px;">FIRST NAME</th>
                  <th style="white-space: nowrap; width: 10px;">MIDDLE NAME</th>
                  <th style="white-space: nowrap; width: 10px;">EXT.</th>
                  <th style="width: 10px;">Pantawid Pamilyang Pilipino Program (4Ps)</th>
                  <th style="width: 10px;">Farmers (F)</th>
                  <th style="width: 10px;">Fisher-folks (FF)</th>
                  <th style="width: 10px;">Informal Sector (IS)</th>
                  <th style="width: 10px;">Indigenous People (IP)</th>
                  <th style="width: 10px;">Senior Citizen (SC)</th>
                  <th style="width: 10px;">Solo Parent (SP)</th>
                  <th style="width: 10px;">Lactating Women (LW)</th>
                  <th style="width: 10px;">Pregnant Women (PW)</th>
                  <th style="width: 10px;">Persons with Disability (PWD)</th>
                  <th style="width: 10px;">Out of School Youth (OSY)</th>
                  <th style="width: 10px;">Former Rebel (FR)</th>
                  <th style="width: 10px;">YAKAP Bayan/ Person Who Used Drugs (YB/PWUD)</th>
                  <th style="width: 10px;">LGBTQIA+</th>
                </tr>
              </thead>
              <tbody style="font-size: 10px; white-space: nowrap;"></tbody>
            </table>
            <?php if ($isAdmin): ?>
            <button class="btn btn-outline-info" type="submit" name="action" value="edit">Edit Selected</button>
            <button class="btn btn-outline-danger" type="submit" name="action" value="delete" id="deleteButton">Delete Selected</button>
            </form>
            <br>
            <form action="delete_batch" method="POST" data-no-loader="true" id="deleteBatchForm">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
              <div class="meb-batch-actions">
                <div class="meb-batch-actions__header">
                  <p class="meb-batch-actions__title">
                    <i class="fas fa-layer-group" aria-hidden="true"></i>
                    Batch deletion
                  </p>
                  <span class="meb-batch-actions__meta">
                    <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                    FY <?= htmlspecialchars((string) ($selectedFiscalYear ?? 'Not selected'), ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </div>
                <div class="meb-batch-actions__tools">
                  <input type="search" class="form-control form-control-sm meb-batch-actions__search" id="batchSearch" placeholder="Search batch ID">
                  <div class="btn-group btn-group-sm" role="group" aria-label="Batch selection tools">
                    <button class="btn btn-outline-info" type="button" id="selectVisibleBatches">Select visible</button>
                    <button class="btn btn-outline-secondary" type="button" id="clearBatchSelection">Clear</button>
                  </div>
                </div>
                <div class="meb-batch-list" id="batchList">
                  <span class="meb-batch-empty">Loading fiscal year batches...</span>
                </div>
                <div class="meb-batch-actions__footer">
                  <span class="meb-batch-empty" id="batchSelectionSummary">0 batches selected</span>
                  <button class="btn btn-outline-danger btn-sm" type="submit" name="deleteBatch" id="deleteBatchButton" disabled>
                    <i class="fas fa-trash-alt" aria-hidden="true"></i>
                    Delete selected batches
                  </button>
                </div>
              </div>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
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
<script src="../dist/js/adminlte.min.js"></script>
<script>
  const isAdminView = <?php echo $isAdmin ? 'true' : 'false'; ?>;
  const canEditMebRecords = <?php echo $canEditMebRecords ? 'true' : 'false'; ?>;
  const selectedFiscalYear = <?php echo json_encode($selectedFiscalYear); ?>;
  const importFlash = <?php echo json_encode($importFlash); ?>;

  function displayFileName() {
    const fileInput = document.getElementById('excelFile');
    const fileName = fileInput && fileInput.files[0] ? fileInput.files[0].name : 'No file chosen';
    const fileNameNode = document.getElementById('file-name');
    if (fileNameNode) {
      fileNameNode.textContent = fileName;
    }
  }
</script>
<script>
  if (importFlash && importFlash.message) {
    Swal.fire({
      icon: importFlash.type === 'success' ? 'success' : 'error',
      title: importFlash.type === 'success' ? 'Success' : 'Error',
      text: String(importFlash.message)
    });
  }
</script>
<script>
  (function () {
    const importForm = document.getElementById('mebImportForm');
    const importInput = document.getElementById('excelFile');
    const importSubmit = document.getElementById('mebImportSubmit');
    let importProgressTimer = null;
    let importProgressValue = 0;

    if (!importForm || !importInput || !importSubmit) {
      return;
    }

    function clearImportProgressTimer() {
      if (importProgressTimer) {
        window.clearInterval(importProgressTimer);
        importProgressTimer = null;
      }
    }

    function updateImportProgress(value, statusText) {
      importProgressValue = Math.max(0, Math.min(100, Number(value || 0)));

      const progressBar = document.getElementById('mebImportProgressBar');
      const progressValueLabel = document.getElementById('mebImportProgressValue');
      const progressStatus = document.getElementById('mebImportProgressStatus');

      if (progressBar) {
        progressBar.style.width = `${importProgressValue}%`;
        progressBar.setAttribute('aria-valuenow', String(Math.round(importProgressValue)));
      }

      if (progressValueLabel) {
        progressValueLabel.textContent = `${Math.round(importProgressValue)}%`;
      }

      if (progressStatus && statusText) {
        progressStatus.textContent = statusText;
      }
    }

    function startImportProgressRamp(targetValue, statusText) {
      clearImportProgressTimer();

      importProgressTimer = window.setInterval(function () {
        if (importProgressValue >= targetValue) {
          clearImportProgressTimer();
          return;
        }

        const remaining = targetValue - importProgressValue;
        const increment = remaining > 14 ? 4 : (remaining > 6 ? 2 : 1);
        updateImportProgress(importProgressValue + increment, statusText);
      }, 180);
    }

    function openImportProgressModal() {
      importProgressValue = 8;

      return Swal.fire({
        title: 'Importing Workbook',
        html: `
          <div class="text-left">
            <p class="mb-2">Please keep this tab open while we upload and import the Excel workbook.</p>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <strong id="mebImportProgressStatus">Preparing upload...</strong>
              <span id="mebImportProgressValue">8%</span>
            </div>
            <div class="progress" style="height: 0.85rem; border-radius: 999px; overflow: hidden;">
              <div
                id="mebImportProgressBar"
                class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                role="progressbar"
                style="width: 8%;"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="8"
              ></div>
            </div>
          </div>
        `,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: function () {
          updateImportProgress(8, 'Preparing upload...');
        }
      });
    }

    function submitImportRequest(formData) {
      return new Promise(function (resolve, reject) {
        const xhr = new XMLHttpRequest();
        let settled = false;

        xhr.open('POST', importForm.action, true);
        xhr.withCredentials = true;
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');

        xhr.upload.addEventListener('progress', function (event) {
          if (!event.lengthComputable) {
            return;
          }

          const percent = Math.round((event.loaded / event.total) * 70);
          updateImportProgress(Math.max(importProgressValue, percent), 'Uploading workbook...');
        });

        xhr.upload.addEventListener('load', function () {
          updateImportProgress(Math.max(importProgressValue, 74), 'Upload complete. Importing rows...');
          startImportProgressRamp(94, 'Validating headers and saving records...');
        });

        xhr.addEventListener('load', function () {
          if (settled) {
            return;
          }

          settled = true;
          clearImportProgressTimer();

          const payload = (() => {
            try {
              return JSON.parse(xhr.responseText || '{}');
            } catch (error) {
              return {
                success: false,
                message: 'Import failed.'
              };
            }
          })();

          if (xhr.status < 200 || xhr.status >= 300 || !payload || payload.success !== true) {
            reject(new Error(payload && payload.message ? payload.message : 'Import failed.'));
            return;
          }

          updateImportProgress(100, 'Import complete.');
          window.setTimeout(function () {
            resolve(payload);
          }, 220);
        });

        xhr.addEventListener('error', function () {
          if (settled) {
            return;
          }

          settled = true;
          clearImportProgressTimer();
          reject(new Error('Unable to import the Excel file right now.'));
        });

        xhr.addEventListener('abort', function () {
          if (settled) {
            return;
          }

          settled = true;
          clearImportProgressTimer();
          reject(new Error('Import was cancelled.'));
        });

        updateImportProgress(12, 'Starting upload...');
        startImportProgressRamp(24, 'Starting upload...');
        xhr.send(formData);
      });
    }

    importForm.addEventListener('submit', async function (event) {
      event.preventDefault();

      if (!importInput.files || importInput.files.length === 0) {
        Swal.fire({
          icon: 'error',
          title: 'No File Selected',
          text: 'Please choose an Excel file to import.'
        });
        return;
      }

      const formData = new FormData(importForm);
      formData.set('import', '1');
      importSubmit.disabled = true;

      try {
        openImportProgressModal();
        const payload = await submitImportRequest(formData);

        window.location.href = payload.redirect || 'data-tracking-meb';
      } catch (error) {
        clearImportProgressTimer();
        importSubmit.disabled = false;
        Swal.close();
        Swal.fire({
          icon: 'error',
          title: 'Import Failed',
          text: error && error.message ? error.message : 'Unable to import the Excel file right now.'
        });
      }
    });
  }());
</script>
<script>
  (function () {
    const generateProfileButton = document.getElementById('generateProfileBtn');
    let profileProgressTimer = null;
    let profileProgressValue = 0;

    if (!generateProfileButton) {
      return;
    }

    function clearProfileProgressTimer() {
      if (profileProgressTimer) {
        window.clearInterval(profileProgressTimer);
        profileProgressTimer = null;
      }
    }

    function updateProfileProgress(value, statusText) {
      profileProgressValue = Math.max(0, Math.min(100, Number(value || 0)));

      const progressBar = document.getElementById('mebProfileProgressBar');
      const progressValueLabel = document.getElementById('mebProfileProgressValue');
      const progressStatus = document.getElementById('mebProfileProgressStatus');

      if (progressBar) {
        progressBar.style.width = `${profileProgressValue}%`;
        progressBar.setAttribute('aria-valuenow', String(Math.round(profileProgressValue)));
      }

      if (progressValueLabel) {
        progressValueLabel.textContent = `${Math.round(profileProgressValue)}%`;
      }

      if (progressStatus && statusText) {
        progressStatus.textContent = statusText;
      }
    }

    function startProfileProgressRamp(targetValue, statusText) {
      clearProfileProgressTimer();

      profileProgressTimer = window.setInterval(function () {
        if (profileProgressValue >= targetValue) {
          clearProfileProgressTimer();
          return;
        }

        const remaining = targetValue - profileProgressValue;
        const increment = remaining > 14 ? 4 : (remaining > 6 ? 2 : 1);
        updateProfileProgress(profileProgressValue + increment, statusText);
      }, 180);
    }

    function openProfileProgressModal() {
      profileProgressValue = 10;

      return Swal.fire({
        title: 'Generating Profile File',
        html: `
          <div class="text-left">
            <p class="mb-2">Please keep this tab open while we build the Partner-Beneficiaries Profile workbook.</p>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <strong id="mebProfileProgressStatus">Preparing export...</strong>
              <span id="mebProfileProgressValue">10%</span>
            </div>
            <div class="progress" style="height: 0.85rem; border-radius: 999px; overflow: hidden;">
              <div
                id="mebProfileProgressBar"
                class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                role="progressbar"
                style="width: 10%;"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="10"
              ></div>
            </div>
          </div>
        `,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: function () {
          updateProfileProgress(10, 'Preparing export...');
        }
      });
    }

    function resolveDownloadFilename(xhr) {
      const disposition = xhr.getResponseHeader('Content-Disposition') || '';
      const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
      if (utf8Match && utf8Match[1]) {
        return decodeURIComponent(utf8Match[1]);
      }

      const plainMatch = disposition.match(/filename="?([^"]+)"?/i);
      if (plainMatch && plainMatch[1]) {
        return plainMatch[1];
      }

      return 'Partner-Beneficiaries_Profile.xlsx';
    }

    function triggerBlobDownload(blob, filename) {
      const downloadUrl = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = downloadUrl;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.setTimeout(function () {
        window.URL.revokeObjectURL(downloadUrl);
      }, 1000);
    }

    function requestProfileFile() {
      return new Promise(function (resolve, reject) {
        const xhr = new XMLHttpRequest();
        let settled = false;

        xhr.open('GET', 'export_profile', true);
        xhr.withCredentials = true;
        xhr.responseType = 'blob';

        xhr.addEventListener('readystatechange', function () {
          if (xhr.readyState >= 2 && profileProgressValue < 28) {
            updateProfileProgress(28, 'Export started. Gathering records...');
            startProfileProgressRamp(92, 'Formatting workbook and preparing download...');
          }
        });

        xhr.addEventListener('progress', function (event) {
          if (!event.lengthComputable) {
            return;
          }

          const percent = 28 + Math.round((event.loaded / event.total) * 64);
          updateProfileProgress(Math.max(profileProgressValue, percent), 'Streaming workbook...');
        });

        xhr.addEventListener('load', function () {
          if (settled) {
            return;
          }

          settled = true;
          clearProfileProgressTimer();

          if (xhr.status < 200 || xhr.status >= 300) {
            reject(new Error('Unable to generate the profile workbook.'));
            return;
          }

          const contentType = xhr.getResponseHeader('Content-Type') || '';
          if (contentType.indexOf('application/json') !== -1 || contentType.indexOf('text/html') !== -1) {
            reject(new Error('The server returned an unexpected response while generating the workbook.'));
            return;
          }

          updateProfileProgress(100, 'Workbook ready.');
          window.setTimeout(function () {
            resolve({
              blob: xhr.response,
              filename: resolveDownloadFilename(xhr)
            });
          }, 220);
        });

        xhr.addEventListener('error', function () {
          if (settled) {
            return;
          }

          settled = true;
          clearProfileProgressTimer();
          reject(new Error('Unable to generate the profile workbook.'));
        });

        xhr.addEventListener('abort', function () {
          if (settled) {
            return;
          }

          settled = true;
          clearProfileProgressTimer();
          reject(new Error('Profile generation was cancelled.'));
        });

        updateProfileProgress(14, 'Starting export...');
        startProfileProgressRamp(24, 'Starting export...');
        xhr.send();
      });
    }

    generateProfileButton.addEventListener('click', async function () {
      generateProfileButton.disabled = true;

      try {
        openProfileProgressModal();
        const result = await requestProfileFile();
        Swal.close();
        triggerBlobDownload(result.blob, result.filename);
      } catch (error) {
        clearProfileProgressTimer();
        Swal.close();
        Swal.fire({
          icon: 'error',
          title: 'Generation Failed',
          text: error && error.message ? error.message : 'Unable to generate the profile workbook right now.'
        });
      } finally {
        generateProfileButton.disabled = false;
      }
    });
  }());
</script>
<script>
  $(document).ready(function() {
      let selectedIds = [];
      const mebFocusId = (() => {
          const params = new URLSearchParams(window.location.search);
          const rawValue = params.get('focus_id');
          const numericValue = Number(rawValue || 0);
          return Number.isInteger(numericValue) && numericValue > 0 ? numericValue : 0;
      })();

      function escapeHtml(value) {
          return $('<div>').text(value ?? '').html();
      }

      function formatFallback(value, fallback = 'Not set') {
          const text = String(value ?? '').trim();
          return text !== '' ? escapeHtml(text) : `<span class="kodus-detail-empty">${escapeHtml(fallback)}</span>`;
      }

      function isMarked(value) {
          const normalized = String(value ?? '').trim().toLowerCase();
          return ['\u2713', 'âœ“', 'Ã¢Å“â€œ', 'yes', 'y', 'true', '1'].includes(normalized);
      }

      function renderClassificationBadge(label) {
          return `
              <div class="kodus-detail-stat">
                  <span class="kodus-detail-value kodus-detail-value--strong kodus-detail-value--positive">${escapeHtml(label)}</span>
              </div>
          `;
      }

      function getFourPsLabel(value) {
          const normalized = String(value ?? '').trim().toUpperCase();
          if (normalized === 'M') {
              return 'Pantawid Pamilyang Pilipino Program (4Ps) - Member';
          }
          if (normalized === 'G') {
              return 'Pantawid Pamilyang Pilipino Program (4Ps) - Graduated';
          }
          return isMarked(value) ? 'Pantawid Pamilyang Pilipino Program (4Ps) - Member' : '';
      }

      function getFourPsDisplayText(value) {
          const normalized = String(value ?? '').trim().toUpperCase();
          if (normalized === 'M') {
              return 'M - Member';
          }
          if (normalized === 'G') {
              return 'G - Graduated';
          }
          return isMarked(value) ? 'M - Member' : '';
      }

      function getPwdCategoryLabel(value) {
          const code = String(value ?? '').trim().toUpperCase();
          const categories = {
              A: 'Multiple Disabilities',
              B: 'Intellectual Disability',
              C: 'Learning Disability',
              D: 'Mental Disability',
              E: 'Physical Disability (Orthopedic)',
              F: 'Psychosocial Disability',
              G: 'Non-apparent Visual Disability',
              H: 'Non-apparent Speech and Language Impairment',
              I: 'Non-apparent Cancer',
              J: 'Non-apparent Rare Disease',
              K: 'Deaf/Hard of Hearing Disability'
          };

          return categories[code] ? `PWD - ${categories[code]}` : '';
      }

      function renderMebDetails(rowData) {
          const lastName = String(rowData.lastName ?? '').trim();
          const givenNames = [
              rowData.firstName,
              rowData.middleName,
              rowData.ext
          ].filter(value => String(value ?? '').trim() !== '').join(' ');
          const fullName = [lastName, givenNames]
              .filter(value => value !== '')
              .join(lastName && givenNames ? ', ' : '');
          const canEditRecord = canEditMebRecords && String(rowData.can_edit ?? '0') === '1';
          const editUrl = `data-tracking-meb-edit?ids=${encodeURIComponent(String(rowData.id ?? ''))}&return_to=${encodeURIComponent('data-tracking-meb')}`;
          const editAction = canEditRecord
              ? `
                  <div class="mt-3">
                      <a href="${editUrl}" class="kodus-detail-action">
                          <i class="fas fa-edit mr-1"></i>
                          Edit Record
                      </a>
                  </div>
              `
              : '';

          const classificationCards = [
              isMarked(rowData.nhts1) ? renderClassificationBadge('Listahanan 3 (P)') : '',
              isMarked(rowData.nhts2) ? renderClassificationBadge('LSWDO Assessment (NON)') : '',
              getFourPsLabel(rowData.fourPs) ? renderClassificationBadge(getFourPsLabel(rowData.fourPs)) : '',
              isMarked(rowData.F) ? renderClassificationBadge('Farmers (F)') : '',
              isMarked(rowData.FF) ? renderClassificationBadge('Fisher-folks (FF)') : '',
              isMarked(rowData.IS) ? renderClassificationBadge('Informal Sector (IS)') : '',
              isMarked(rowData.IP) ? renderClassificationBadge('Indigenous People (IP)') : '',
              isMarked(rowData.SC) ? renderClassificationBadge('Senior Citizen (SC)') : '',
              isMarked(rowData.SP) ? renderClassificationBadge('Solo Parent (SP)') : '',
              isMarked(rowData.LW) ? renderClassificationBadge('Lactating Women (LW)') : '',
              isMarked(rowData.PW) ? renderClassificationBadge('Pregnant Women (PW)') : '',
              getPwdCategoryLabel(rowData.PWD) ? renderClassificationBadge(getPwdCategoryLabel(rowData.PWD)) : '',
              isMarked(rowData.OSY) ? renderClassificationBadge('Out of School Youth (OSY)') : '',
              isMarked(rowData.FR) ? renderClassificationBadge('Former Rebel') : '',
              isMarked(rowData.ybDs) ? renderClassificationBadge('YAKAP Bayan/ Person Who Used Drugs (YB/PWUD)') : '',
              isMarked(rowData.lgbtqia) ? renderClassificationBadge('LGBTQIA+') : ''
          ].filter(Boolean).join('');

          return `
              <div class="kodus-detail-modal">
                  <div class="kodus-detail-hero">
                      <div>
                          <span class="kodus-detail-eyebrow">Eligible Beneficiary</span>
                          <h3 class="kodus-detail-title">${formatFallback(fullName, 'No recorded name')}</h3>
                          <p class="kodus-detail-subtitle">${formatFallback(rowData.barangay, 'No barangay')}, ${formatFallback(rowData.lgu, 'No municipality')}, ${formatFallback(rowData.province, 'No province')}</p>
                          ${editAction}
                      </div>
                      <div class="kodus-detail-pill">Record #${escapeHtml(rowData.id ?? 'N/A')}</div>
                  </div>

                  <div class="kodus-detail-grid">
                      <div class="kodus-detail-stat">
                          <span class="kodus-detail-label">Age</span>
                          <span class="kodus-detail-value kodus-detail-value--strong">${formatFallback(rowData.age)}</span>
                      </div>
                      <div class="kodus-detail-stat">
                          <span class="kodus-detail-label">Sex</span>
                          <span class="kodus-detail-value kodus-detail-value--strong">${formatFallback(rowData.sex)}</span>
                      </div>
                      <div class="kodus-detail-stat">
                          <span class="kodus-detail-label">Civil Status</span>
                          <span class="kodus-detail-value kodus-detail-value--strong">${formatFallback(rowData.civilStatus)}</span>
                      </div>
                      <div class="kodus-detail-stat">
                          <span class="kodus-detail-label">Birthdate</span>
                          <span class="kodus-detail-value kodus-detail-value--strong">${formatFallback(rowData.birthDate)}</span>
                      </div>
                  </div>

                  <div class="kodus-detail-section">
                      <h6 class="kodus-detail-section-title">Identity</h6>
                      <div class="kodus-detail-section-grid">
                          <div>
                              <span class="kodus-detail-label">Last Name</span>
                              <span class="kodus-detail-value">${formatFallback(rowData.lastName)}</span>
                          </div>
                          <div>
                              <span class="kodus-detail-label">First Name</span>
                              <span class="kodus-detail-value">${formatFallback(rowData.firstName)}</span>
                          </div>
                          <div>
                              <span class="kodus-detail-label">Middle Name</span>
                              <span class="kodus-detail-value">${formatFallback(rowData.middleName)}</span>
                          </div>
                          <div>
                              <span class="kodus-detail-label">Extension</span>
                              <span class="kodus-detail-value">${formatFallback(rowData.ext, 'None')}</span>
                          </div>
                      </div>
                  </div>

                  <div class="kodus-detail-section">
                      <h6 class="kodus-detail-section-title">Location</h6>
                      <div class="kodus-detail-section-grid">
                          <div>
                              <span class="kodus-detail-label">Purok</span>
                              <span class="kodus-detail-value">${formatFallback(rowData.purok)}</span>
                          </div>
                          <div>
                              <span class="kodus-detail-label">Barangay</span>
                              <span class="kodus-detail-value">${formatFallback(rowData.barangay)}</span>
                          </div>
                          <div>
                              <span class="kodus-detail-label">LGU</span>
                              <span class="kodus-detail-value">${formatFallback(rowData.lgu)}</span>
                          </div>
                          <div>
                              <span class="kodus-detail-label">Province</span>
                              <span class="kodus-detail-value">${formatFallback(rowData.province)}</span>
                          </div>
                      </div>
                  </div>

                  <div class="kodus-detail-section mb-0">
                      <h6 class="kodus-detail-section-title">Classifications</h6>
                      <div class="kodus-detail-grid">
                          ${classificationCards || '<span class="kodus-detail-empty">No classifications tagged</span>'}
                      </div>
                  </div>
              </div>
          `;
      }

      function renderMebCarousel(position, total) {
          const prevDisabled = position <= 1 ? 'disabled' : '';
          const nextDisabled = position >= total ? 'disabled' : '';

          return `
              <div class="d-flex justify-content-between align-items-center mb-3">
                  <button type="button" class="btn btn-outline-secondary btn-sm meb-carousel-btn" data-direction="prev" ${prevDisabled}>&larr;</button>
                  <span class="kodus-detail-label mb-0">Record ${position} of ${total}</span>
                  <button type="button" class="btn btn-outline-secondary btn-sm meb-carousel-btn" data-direction="next" ${nextDisabled}>&rarr;</button>
              </div>
          `;
      }

      function openMebDetailsModal(startId, searchValue) {
          let activeId = startId;
          let isLoading = false;
          let popupElement = null;

          const revealHtmlContainer = () => {
              const htmlContainer = Swal.getHtmlContainer();
              if (htmlContainer) {
                  htmlContainer.style.display = '';
              }
              return htmlContainer;
          };

          const handleArrowNavigation = (event) => {
              if (!Swal.isVisible() || isLoading) {
                  return;
              }

              if (event.key === 'ArrowLeft') {
                  const prevButton = Swal.getHtmlContainer()?.querySelector('.meb-carousel-btn[data-direction="prev"]');
                  if (prevButton && !prevButton.disabled) {
                      event.preventDefault();
                      prevButton.click();
                  }
              }

              if (event.key === 'ArrowRight') {
                  const nextButton = Swal.getHtmlContainer()?.querySelector('.meb-carousel-btn[data-direction="next"]');
                  if (nextButton && !nextButton.disabled) {
                      event.preventDefault();
                      nextButton.click();
                  }
              }
          };

          const renderLoadingState = () => {
              const htmlContainer = revealHtmlContainer();
              if (!htmlContainer) {
                  return;
              }

              htmlContainer.innerHTML = `
                  <div class="text-center py-4">
                      <div class="spinner-border text-info mb-3" role="status" aria-hidden="true"></div>
                      <div class="kodus-detail-label mb-0">Loading beneficiary details...</div>
                  </div>
              `;
          };

          const loadRecord = (direction = 'current') => {
              isLoading = true;
              renderLoadingState();

              $.getJSON('fetch_meb_detail.php', {
                  id: activeId,
                  direction: direction,
                  search: searchValue
              }).done(function(response) {
                  const htmlContainer = revealHtmlContainer();

                  if (!response || !response.success || !response.row || !htmlContainer) {
                      isLoading = false;
                      if (htmlContainer) {
                          htmlContainer.innerHTML = '<span class="kodus-detail-empty">Unable to load beneficiary details.</span>';
                      }
                      return;
                  }

                  activeId = response.row.id;
                  isLoading = false;
                  htmlContainer.innerHTML = `
                      ${renderMebCarousel(Number(response.position || 1), Number(response.total || 1))}
                      ${renderMebDetails(response.row)}
                  `;
                  if (popupElement) {
                      popupElement.focus();
                  }

                  htmlContainer.querySelectorAll('.meb-carousel-btn').forEach((button) => {
                      button.addEventListener('click', function() {
                          const nextDirection = this.getAttribute('data-direction');
                          if (!this.disabled) {
                              loadRecord(nextDirection);
                          }
                      });
                  });
              }).fail(function() {
                  isLoading = false;
                  const htmlContainer = revealHtmlContainer();
                  if (htmlContainer) {
                      htmlContainer.innerHTML = '<span class="kodus-detail-empty">Unable to load beneficiary details.</span>';
                  }
              });
          };

          Swal.fire({
              title: 'Partner-Beneficiary Details',
              customClass: {
                  popup: 'kodus-detail-popup'
              },
              width: 980,
              html: '',
              confirmButtonText: '<i class="fas fa-times"></i>',
              stopKeydownPropagation: false,
              didOpen: function() {
                  popupElement = Swal.getPopup();
                  if (popupElement) {
                      popupElement.setAttribute('tabindex', '0');
                      popupElement.addEventListener('keydown', handleArrowNavigation);
                      popupElement.focus();
                  }
                  loadRecord('current');
              },
              willClose: function() {
                  if (popupElement) {
                      popupElement.removeEventListener('keydown', handleArrowNavigation);
                  }
                  popupElement = null;
              }
          });
      }

      function updateSelectedIds() {
          selectedIds = Array.from($('input[name="selected[]"]:checked')).map((checkbox) => checkbox.value);
      }

      const columns = [];
      columns.push({
          data: null,
          orderable: false,
          searchable: false,
          render: function() {
              return '<span class="kodus-row-actions"><button type="button" class="btn btn-info btn-sm meb-details-btn" style="font-size:10px;" title="View details" aria-label="View details"><i class="nav-icon fas fa-eye"></i></button></span>';
          }
      });

      if (isAdminView) {
          columns.push({
              data: 'id',
              render: function(data) {
                  return `<input type="checkbox" name="selected[]" value="${data}" class="select-checkbox">`;
              }
          });
      }

      columns.push({
          data: null,
          render: function(data, type, row, meta) {
              return meta.row + 1;
          }
      });

      columns.push(
          { data: 'lastName' },
          { data: 'firstName' },
          { data: 'middleName' },
          { data: 'ext' },
          { data: 'purok' },
          { data: 'barangay' },
          { data: 'lgu' },
          { data: 'province' },
          { data: 'birthDate' },
          { data: 'age' },
          { data: 'sex' },
          { data: 'civilStatus' },
          { data: 'nhts1' },
          { data: 'nhts2' },
          {
              data: 'fourPs',
              render: function(data) {
                  return getFourPsDisplayText(data);
              }
          },
          { data: 'F' },
          { data: 'FF' },
          { data: 'IS' },
          { data: 'IP' },
          { data: 'SC' },
          { data: 'SP' },
          { data: 'LW' },
          { data: 'PW' },
          { data: 'PWD' },
          { data: 'OSY' },
          { data: 'FR' },
          { data: 'ybDs' },
          { data: 'lgbtqia' }
      );

      const table = $("#table1").DataTable({
          processing: false,
          serverSide: true,
          ajax: {
              url: "fetch_data.php",
              type: "GET",
              dataSrc: function(json) {
                  if (isAdminView) {
                      setTimeout(() => {
                          selectedIds.forEach((id) => {
                              $(`input[name="selected[]"][value="${id}"]`).prop('checked', true);
                          });
                      }, 100);
                  }
                  return json.data;
              }
          },
          columns: columns,
          lengthChange: true,
          lengthMenu: [[10,25,50,100,200,300,-1], [10,25,50,100,200,300,"All"]],
          pageLength: 10,
          paging: true,
          responsive: false,
          scrollX: true,
          rowCallback: function(row, data, index) {
              const counterColumnIndex = isAdminView ? 2 : 1;
              $('td:eq(' + counterColumnIndex + ')', row).html(index + 1 + table.page.info().start);
          }
      });

      $('#table1 tbody').on('click', '.meb-details-btn', function() {
          const rowData = table.row($(this).closest('tr')).data();

          if (!rowData || !rowData.id) {
              return;
          }

          openMebDetailsModal(rowData.id, table.search());
      });

      if (isAdminView) {
          $("#table1 tbody").on("change", 'input[name="selected[]"]', function() {
              updateSelectedIds();
          });
      }

      if (window.KODUSLiveRefresh) {
          window.KODUSLiveRefresh.watchDataTable({
              channels: ['meb_table'],
              table: table,
              socket: {
                  key: 'data-tracking-meb',
                  channel: 'kodus.meb',
                  events: ['meb.changed']
              }
          });
      }

      if (mebFocusId > 0) {
          window.setTimeout(function() {
              openMebDetailsModal(mebFocusId, '');
              const currentUrl = new URL(window.location.href);
              currentUrl.searchParams.delete('focus_id');
              window.history.replaceState({}, document.title, currentUrl.toString());
          }, 250);
      }
  });
</script>
<?php if ($isAdmin): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    let fiscalYearBatches = [];
    const selectedBatchIds = new Set();

    function normalizeBatchPayload(payload) {
        if (Array.isArray(payload)) {
            return payload.map((batchId) => ({
                id: String(batchId),
                recordCount: null
            }));
        }

        if (!payload || !Array.isArray(payload.batches)) {
            return [];
        }

        return payload.batches.map((batch) => ({
            id: String(batch.id || ''),
            recordCount: Number.isFinite(Number(batch.recordCount)) ? Number(batch.recordCount) : null
        })).filter((batch) => batch.id !== '');
    }

    function updateBatchSelectionSummary() {
        const deleteButton = document.getElementById("deleteBatchButton");
        const summary = document.getElementById("batchSelectionSummary");
        const selectedCount = selectedBatchIds.size;

        if (deleteButton) {
            deleteButton.disabled = selectedCount === 0;
        }

        if (summary) {
            summary.textContent = `${selectedCount} batch${selectedCount === 1 ? '' : 'es'} selected`;
        }
    }

    function renderBatchOptions() {
        const batchList = document.getElementById("batchList");
        const batchSearch = document.getElementById("batchSearch");
        if (!batchList) {
            return;
        }

        const searchTerm = batchSearch ? batchSearch.value.trim().toLowerCase() : '';
        const visibleBatches = fiscalYearBatches.filter((batch) => batch.id.toLowerCase().includes(searchTerm));

        batchList.innerHTML = '';

        if (visibleBatches.length === 0) {
            const empty = document.createElement("span");
            empty.className = "meb-batch-empty";
            empty.textContent = fiscalYearBatches.length === 0
                ? `No batches found for fiscal year ${selectedFiscalYear || ''}.`
                : "No batches match your search.";
            batchList.appendChild(empty);
            updateBatchSelectionSummary();
            return;
        }

        visibleBatches.forEach((batch) => {
            const label = document.createElement("label");
            label.className = "meb-batch-option";

            const checkbox = document.createElement("input");
            checkbox.type = "checkbox";
            checkbox.name = "batchIds[]";
            checkbox.value = batch.id;
            checkbox.checked = selectedBatchIds.has(batch.id);

            const batchId = document.createElement("span");
            batchId.className = "meb-batch-option__id";
            batchId.textContent = `Batch ID: ${batch.id}`;

            const count = document.createElement("span");
            count.className = "meb-batch-option__count";
            count.textContent = batch.recordCount === null
                ? ""
                : `${batch.recordCount.toLocaleString()} record${batch.recordCount === 1 ? '' : 's'}`;

            label.appendChild(checkbox);
            label.appendChild(batchId);
            label.appendChild(count);
            batchList.appendChild(label);
        });

        updateBatchSelectionSummary();
    }

    function loadBatchOptions() {
        fetch('fetch_batches.php')
            .then(response => response.json())
            .then(payload => {
                if (payload && payload.error) {
                    throw new Error(payload.error);
                }
                fiscalYearBatches = normalizeBatchPayload(payload);
                renderBatchOptions();
            })
            .catch(error => {
                console.error("Error fetching batch IDs:", error);
                const batchList = document.getElementById("batchList");
                if (batchList) {
                    batchList.innerHTML = '<span class="meb-batch-empty">Unable to load fiscal year batches.</span>';
                }
            });
    }

    loadBatchOptions();

    const batchSearch = document.getElementById("batchSearch");
    if (batchSearch) {
        batchSearch.addEventListener("input", renderBatchOptions);
    }

    const batchList = document.getElementById("batchList");
    if (batchList) {
        batchList.addEventListener("change", function(event) {
            if (event.target && event.target.matches('input[name="batchIds[]"]')) {
                if (event.target.checked) {
                    selectedBatchIds.add(event.target.value);
                } else {
                    selectedBatchIds.delete(event.target.value);
                }
                updateBatchSelectionSummary();
            }
        });
    }

    const selectVisibleBatches = document.getElementById("selectVisibleBatches");
    if (selectVisibleBatches) {
        selectVisibleBatches.addEventListener("click", function() {
            document.querySelectorAll('#batchList input[name="batchIds[]"]').forEach((input) => {
                input.checked = true;
                selectedBatchIds.add(input.value);
            });
            updateBatchSelectionSummary();
        });
    }

    const clearBatchSelection = document.getElementById("clearBatchSelection");
    if (clearBatchSelection) {
        clearBatchSelection.addEventListener("click", function() {
            document.querySelectorAll('input[name="batchIds[]"]').forEach((input) => {
                input.checked = false;
            });
            selectedBatchIds.clear();
            updateBatchSelectionSummary();
        });
    }

    const deleteBatchForm = document.getElementById("deleteBatchForm");
    if (!deleteBatchForm) {
        return;
    }

    deleteBatchForm.addEventListener("submit", async function(event) {
      event.preventDefault();

      const selectedBatchIdValues = Array.from(selectedBatchIds).filter(Boolean);

      if (selectedBatchIdValues.length === 0) {
          Swal.fire({
              icon: 'warning',
              title: 'No batch selected',
              text: 'Please select at least one batch to delete.',
          });
          return;
      }

      const confirmResult = await Swal.fire({
          icon: 'warning',
          title: 'Delete selected batches?',
          text: `This will delete ${selectedBatchIdValues.length} batch${selectedBatchIdValues.length === 1 ? '' : 'es'} and all matching MEB records for fiscal year ${selectedFiscalYear}.`,
          showCancelButton: true,
          confirmButtonText: 'Yes, delete',
          cancelButtonText: 'Cancel',
          confirmButtonColor: '#dc3545'
      });

      if (!confirmResult.isConfirmed) {
          return;
      }

      const formData = new FormData(this);
      formData.delete('batchIds[]');
      selectedBatchIdValues.forEach((batchId) => {
          formData.append('batchIds[]', batchId);
      });

      fetch('delete_batch.php', {
          method: 'POST',
          body: formData
      })
      .then(response => response.text())
      .then(text => {
          try {
              return JSON.parse(text);
          } catch (error) {
              console.error("Invalid JSON response:", text);
              throw new Error("Invalid JSON response from server.");
          }
      })
      .then(result => {
          if (result.success) {
              Swal.fire({
                  icon: 'success',
                  title: 'Success',
                  text: `${result.deletedBatches || selectedBatchIdValues.length} batch${(result.deletedBatches || selectedBatchIdValues.length) === 1 ? '' : 'es'} deleted successfully!`,
              }).then(() => {
                  location.reload();
              });
          } else {
              Swal.fire({
                  icon: 'error',
                  title: 'Error',
                  text: result.error || "An unknown error occurred.",
              });
          }
      })
      .catch(error => {
          console.error("Error deleting batch:", error);
          Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'Something went wrong. Please try again.',
          });
      });
    });
});
</script>
<?php endif; ?>
<script>
  document.getElementById('exportBtn').addEventListener('click', function () {
      window.location.href = 'export_meb';
  });
</script>
</body>
</html>
