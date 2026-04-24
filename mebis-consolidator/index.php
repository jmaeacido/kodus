<?php
include('../header.php');
include('../sidenav.php');
require_once __DIR__ . '/helpers/history.php';

function mebis_read_output_municipality_summary(string $filename): array
{
    $path = mebis_outputs_dir() . '/' . $filename;
    if (!is_file($path)) {
        return [];
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return [];
    }

    try {
        $headerRow = fgetcsv($handle);
        if (!is_array($headerRow)) {
            return [];
        }

        if (isset($headerRow[0])) {
            $headerRow[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headerRow[0]);
        }

        $headers = array_map(static fn($value): string => trim((string) $value), $headerRow);
        $provinceIndex = array_search('province_name', $headers, true);
        $municipalityIndex = array_search('city_name', $headers, true);

        if ($provinceIndex === false || $municipalityIndex === false) {
            return [];
        }

        $summary = [];

        while (($row = fgetcsv($handle)) !== false) {
            $province = trim((string) ($row[$provinceIndex] ?? ''));
            $municipality = trim((string) ($row[$municipalityIndex] ?? ''));

            if ($municipality === '' && $province === '') {
                continue;
            }

            $key = strtoupper($province . '|' . $municipality);
            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'province_name' => $province,
                    'city_name' => $municipality,
                    'row_count' => 0,
                ];
            }

            $summary[$key]['row_count']++;
        }

        $items = array_values($summary);
        usort($items, static function (array $a, array $b): int {
            $provinceComparison = strcasecmp((string) ($a['province_name'] ?? ''), (string) ($b['province_name'] ?? ''));
            if ($provinceComparison !== 0) {
                return $provinceComparison;
            }

            return strcasecmp((string) ($a['city_name'] ?? ''), (string) ($b['city_name'] ?? ''));
        });

        return $items;
    } finally {
        fclose($handle);
    }
}

$successMessage = $_SESSION['mebis_consolidator_success'] ?? null;
$errorMessage = $_SESSION['mebis_consolidator_error'] ?? null;
unset($_SESSION['mebis_consolidator_success'], $_SESSION['mebis_consolidator_error']);

$resultModal = null;
if ($errorMessage) {
    $resultModal = [
        'icon' => 'error',
        'title' => 'Template generation failed',
        'text' => (string) $errorMessage,
    ];
} elseif ($successMessage) {
    $resultModal = [
        'icon' => 'success',
        'title' => 'Ready',
        'text' => (string) $successMessage,
    ];
}

mebis_history_ensure_schema($conn);
$savedOutputs = mebis_list_outputs($conn);
$outputSummaries = [];

foreach ($savedOutputs as $entry) {
    $token = (string) ($entry['token'] ?? '');
    $filename = (string) ($entry['filename'] ?? '');
    $fileExists = !empty($entry['file_exists']);

    if ($token === '' || $filename === '' || !$fileExists) {
        continue;
    }

    $outputSummaries[$token] = [
        'filename' => $filename,
        'summary' => mebis_read_output_municipality_summary($filename),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>KODUS | MEBIS Name-Matching Template</title>
  <?php if ($resultModal !== null): ?>
  <script>
    document.documentElement.classList.add('kodus-page-loading');
    window.__kodusPageLoaderHold = true;
    window.__kodusMebisConsolidatorResultModal = <?= json_encode($resultModal) ?>;
  </script>
  <?php endif; ?>
  <style>
    .mebis-upload-card {
      border: 1px solid rgba(13, 110, 253, 0.12);
      border-radius: 1rem;
      background: linear-gradient(135deg, rgba(13, 110, 253, 0.08), rgba(32, 201, 151, 0.06));
      padding: 1rem 1rem 0.9rem;
      margin-bottom: 1rem;
    }

    .mebis-upload-card h5 {
      margin-bottom: 0.35rem;
      font-weight: 700;
    }

    .mebis-upload-card p {
      margin-bottom: 0;
      color: #6c757d;
    }

    body[data-theme="dark"] .mebis-upload-card p {
      color: #b8c7d9;
    }

    .mebis-columns {
      column-count: 2;
      column-gap: 1.5rem;
      margin-bottom: 0;
      padding-left: 1.15rem;
    }

    @media (max-width: 767.98px) {
      .mebis-columns {
        column-count: 1;
      }
    }

    .mebis-file-preview {
      margin-top: 1rem;
      padding: 0.9rem 1rem;
      border: 1px dashed rgba(13, 110, 253, 0.35);
      border-radius: 0.9rem;
      background: rgba(13, 110, 253, 0.04);
    }

    .mebis-file-preview h6 {
      margin-bottom: 0.65rem;
      font-weight: 700;
    }

    .mebis-file-preview ul {
      margin: 0;
      padding-left: 1.15rem;
    }

    .mebis-file-preview li {
      margin-bottom: 0.25rem;
      word-break: break-word;
    }

    .mebis-picker-row + .mebis-picker-row {
      margin-top: 0.75rem;
    }

    .mebis-summary-meta {
      color: #6c757d;
      font-size: 0.95rem;
    }

    body[data-theme="dark"] .mebis-summary-meta {
      color: #b8c7d9;
    }

    .mebis-summary-trigger {
      padding: 0;
      border: 0;
      background: none;
      color: #0d6efd;
      font-weight: 600;
      text-decoration: underline;
      cursor: pointer;
    }

    .mebis-summary-trigger:hover {
      color: #0a58ca;
    }

    .mebis-missing-file {
      color: #b45309;
      font-weight: 600;
    }

    body[data-theme="dark"] .mebis-missing-file {
      color: #fbbf24;
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
            <h1 class="m-0">MEBIS Name-Matching Template</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../home">Home</a></li>
              <li class="breadcrumb-item active">MEBIS Name-Matching Template</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header">
            <h4 class="m-0">Upload Initial MEB</h4>
          </div>
          <div class="card-body">
            <div class="mebis-upload-card">
              <h5>What this tool does</h5>
              <p>Uploads multiple MEBIS workbooks, uses the bundled CARAGA PSGC helper file, and converts the needed fields into one CSV template for 4Ps and NHTS-PR name matching.</p>
            </div>

            <form id="mebisConsolidatorGenerateForm" action="download.php" method="post" enctype="multipart/form-data" data-loader-text="Building your name-matching CSV template..." data-no-loader="true">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

              <div class="row">
                <div class="col-md-8">
                  <div class="form-group">
                    <label>MEBIS Workbooks</label>
                    <div id="mebis-picker-list">
                      <div class="mebis-picker-row">
                        <input type="file" name="mebis_files[]" class="form-control mebis-file-input" accept=".xlsx,.xlsm" multiple required>
                      </div>
                    </div>
                    <small class="form-text text-muted">Select files from one folder, then click “Add Another File Picker” to choose more files from a different folder if needed.</small>
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="add-file-picker">
                      <i class="fas fa-plus mr-1"></i>
                      Add Another File Picker
                    </button>
                    <div class="mebis-file-preview" id="mebis-file-preview" hidden>
                      <h6>Selected Files</h6>
                      <ul id="mebis-file-preview-list"></ul>
                    </div>
                  </div>
                </div>
              </div>

              <div class="alert alert-info mb-4">
                <strong>Output columns:</strong>
                <ul class="mebis-columns mt-2">
                  <li>last_name</li>
                  <li>first_name</li>
                  <li>middle_name</li>
                  <li>extName</li>
                  <li>birthdate</li>
                  <li>region_code, province_code, city_code, barangay_code</li>
                  <li>region_name, province_name, city_name, barangay_name</li>
                  <li>File_number</li>
                </ul>
              </div>

              <button type="submit" class="btn btn-primary" id="mebisConsolidatorGenerateButton">
                <i class="fas fa-file-csv mr-1"></i>
                Generate Name-Matching CSV
              </button>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-header d-flex align-items-center">
            <h4 class="m-0 flex-grow-1">Municipality Summary</h4>
          </div>
          <div class="card-body">
            <?php if ($savedOutputs === []): ?>
              <p class="text-muted mb-0">Generate a consolidated file first to view municipality totals here.</p>
            <?php else: ?>
              <p class="mebis-summary-meta mb-3" id="municipality-summary-meta">
                Click a value in the Rows column below to view that file's municipality totals here.
              </p>

              <div class="table-responsive">
                <table class="table table-bordered table-striped" id="municipality-summary-table">
                  <thead>
                    <tr>
                      <th>Province</th>
                      <th>Municipality</th>
                      <th>Rows</th>
                    </tr>
                  </thead>
                  <tbody id="municipality-summary-body">
                    <tr>
                      <td class="text-center text-muted">No file selected yet.</td>
                      <td></td>
                      <td></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-header d-flex align-items-center">
            <h4 class="m-0 flex-grow-1">Saved Name-Matching Files</h4>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-bordered table-striped" id="saved-outputs-table">
                <thead>
                  <tr>
                    <th>Created At</th>
                    <th>Filename</th>
                    <th>Status</th>
                    <th>Rows</th>
                    <th>Source Files</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($savedOutputs === []): ?>
                    <tr>
                      <td class="text-center text-muted">No saved name-matching files yet.</td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($savedOutputs as $entry): ?>
                      <?php $fileExists = !empty($entry['file_exists']); ?>
                      <tr>
                        <td><?= htmlspecialchars((string) ($entry['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($entry['filename'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                          <?php if ($fileExists): ?>
                            <span class="badge badge-success">Available</span>
                          <?php else: ?>
                            <span class="badge badge-warning">Missing file</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if ($fileExists): ?>
                            <button
                              type="button"
                              class="mebis-summary-trigger"
                              data-summary-token="<?= htmlspecialchars((string) ($entry['token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            >
                              <?= (int) ($entry['rows'] ?? 0) ?>
                            </button>
                          <?php else: ?>
                            <span class="mebis-missing-file"><?= (int) ($entry['rows'] ?? 0) ?></span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php
                          $sourceFiles = $entry['source_files'] ?? [];
                          if (!is_array($sourceFiles) || $sourceFiles === []) {
                              echo '<span class="text-muted">-</span>';
                          } else {
                              echo htmlspecialchars(implode(', ', array_map('strval', $sourceFiles)), ENT_QUOTES, 'UTF-8');
                          }
                          ?>
                        </td>
                        <td>
                          <?php if ($fileExists): ?>
                            <a href="file?id=<?= urlencode((string) ($entry['token'] ?? '')) ?>" class="btn btn-sm btn-primary" download>
                              <i class="fas fa-download mr-1"></i>
                              Download
                            </a>
                          <?php else: ?>
                            <span class="text-muted">Output file is no longer on disk.</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../dist/js/adminlte.min.js"></script>
<link rel="stylesheet" href="../cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="../cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="../cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function() {
  const summaryTable = $('#municipality-summary-table').DataTable({
    paging: true,
    pageLength: 10,
    lengthChange: false,
    searching: true,
    info: true,
    autoWidth: false,
    order: [[0, 'asc'], [1, 'asc']]
  });

  $('#saved-outputs-table').DataTable({
    paging: true,
    pageLength: 10,
    lengthChange: false,
    searching: true,
    info: true,
    autoWidth: false,
    order: [[0, 'desc']]
  });

  const summaries = <?= json_encode($outputSummaries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const summaryMeta = document.getElementById('municipality-summary-meta');
  const summaryBody = document.getElementById('municipality-summary-body');

  function renderSummaryRows(token) {
    if (!summaryBody || !summaryMeta) {
      return;
    }

    const payload = summaries[token] || null;
    const rows = payload && Array.isArray(payload.summary) ? payload.summary : [];

    summaryTable.clear();

    if (!payload) {
      summaryMeta.textContent = 'No municipality summary is available for the selected file.';
      summaryTable.row.add([
        '<span class="text-muted">No municipality summary available for this file.</span>',
        '',
        ''
      ]).draw();
      return;
    }

    summaryMeta.innerHTML = 'Showing row counts from <strong>' + $('<div>').text(payload.filename || '').html() + '</strong>';

    if (rows.length === 0) {
      summaryTable.row.add([
        '<span class="text-muted">No municipality summary available for this file.</span>',
        '',
        ''
      ]).draw();
      return;
    }

    rows.forEach(function(row) {
      summaryTable.row.add([
        $('<div>').text(row.province_name || '').html(),
        $('<div>').text(row.city_name || '').html(),
        Number(row.row_count || 0)
      ]);
    });

    summaryTable.draw();
  }

  document.querySelectorAll('[data-summary-token]').forEach(function(button) {
    button.addEventListener('click', function() {
      renderSummaryRows(button.getAttribute('data-summary-token') || '');
    });
  });
});

document.addEventListener('DOMContentLoaded', function() {
  const pickerList = document.getElementById('mebis-picker-list');
  const addPickerButton = document.getElementById('add-file-picker');
  const preview = document.getElementById('mebis-file-preview');
  const previewList = document.getElementById('mebis-file-preview-list');
  const form = document.getElementById('mebisConsolidatorGenerateForm');
  const submitButton = document.getElementById('mebisConsolidatorGenerateButton');
  const generatorLoader = window.KodusPageLoader && typeof window.KodusPageLoader.createStandaloneLoader === 'function'
    ? window.KodusPageLoader.createStandaloneLoader({
        text: form && form.dataset.loaderText ? form.dataset.loaderText : 'Building your name-matching CSV template...'
      })
    : null;

  if (!pickerList || !addPickerButton || !preview || !previewList) {
    return;
  }

  function refreshPreview() {
    previewList.innerHTML = '';

    const files = Array.from(document.querySelectorAll('.mebis-file-input'))
      .flatMap(function(input) {
        return Array.from(input.files || []);
      });

    if (files.length === 0) {
      preview.hidden = true;
      return;
    }

    files.forEach(function(file) {
      const item = document.createElement('li');
      item.textContent = file.name;
      previewList.appendChild(item);
    });

    preview.hidden = false;
  }

  pickerList.addEventListener('change', function(event) {
    if (event.target && event.target.classList.contains('mebis-file-input')) {
      refreshPreview();
    }
  });

  addPickerButton.addEventListener('click', function() {
    const row = document.createElement('div');
    row.className = 'mebis-picker-row';
    row.innerHTML = '<input type="file" name="mebis_files[]" class="form-control mebis-file-input" accept=".xlsx,.xlsm" multiple>';
    pickerList.appendChild(row);
  });

  if (!form) {
    return;
  }

  let progressTimer = null;
  let progressValue = 0;

  function clearGeneratorProgressTimer() {
    if (progressTimer) {
      window.clearInterval(progressTimer);
      progressTimer = null;
    }
  }

  function updateGeneratorProgress(value, statusText) {
    progressValue = Math.max(0, Math.min(100, Number(value || 0)));

    const progressBar = document.getElementById('mebisGeneratorProgressBar');
    const progressValueLabel = document.getElementById('mebisGeneratorProgressValue');
    const progressStatus = document.getElementById('mebisGeneratorProgressStatus');

    if (progressBar) {
      progressBar.style.width = `${progressValue}%`;
      progressBar.setAttribute('aria-valuenow', String(Math.round(progressValue)));
    }

    if (progressValueLabel) {
      progressValueLabel.textContent = `${Math.round(progressValue)}%`;
    }

    if (progressStatus && statusText) {
      progressStatus.textContent = statusText;
    }
  }

  function startGeneratorProgressRamp(targetValue, statusText) {
    clearGeneratorProgressTimer();

    progressTimer = window.setInterval(function () {
      if (progressValue >= targetValue) {
        clearGeneratorProgressTimer();
        return;
      }

      const remaining = targetValue - progressValue;
      const increment = remaining > 14 ? 4 : (remaining > 6 ? 2 : 1);
      updateGeneratorProgress(progressValue + increment, statusText);
    }, 180);
  }

  function openGeneratorProgressModal() {
    progressValue = 8;

    return Swal.fire({
      title: 'Generating Template',
      html: `
        <div class="text-left">
          <p class="mb-2">Please keep this tab open while we upload and build your name-matching CSV.</p>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <strong id="mebisGeneratorProgressStatus">Preparing upload...</strong>
            <span id="mebisGeneratorProgressValue">8%</span>
          </div>
          <div class="progress" style="height: 0.85rem; border-radius: 999px; overflow: hidden;">
            <div
              id="mebisGeneratorProgressBar"
              class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
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
        updateGeneratorProgress(8, 'Preparing upload...');
      }
    });
  }

  function submitGeneratorRequest(formData) {
    return new Promise(function (resolve, reject) {
      const xhr = new XMLHttpRequest();
      let settled = false;

      xhr.open('POST', form.action, true);
      xhr.withCredentials = true;
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.setRequestHeader('Accept', 'application/json');

      xhr.upload.addEventListener('progress', function (event) {
        if (!event.lengthComputable) {
          return;
        }

        const percent = Math.round((event.loaded / event.total) * 68);
        updateGeneratorProgress(Math.max(progressValue, percent), 'Uploading workbooks...');
      });

      xhr.upload.addEventListener('load', function () {
        updateGeneratorProgress(Math.max(progressValue, 72), 'Upload complete. Building your CSV...');
        startGeneratorProgressRamp(94, 'Matching records and preparing the file...');
      });

      xhr.addEventListener('load', function () {
        if (settled) {
          return;
        }

        settled = true;
        clearGeneratorProgressTimer();

        const payload = (() => {
          try {
            return JSON.parse(xhr.responseText || '{}');
          } catch (error) {
            return {
              success: false,
              message: 'The server returned an invalid response.'
            };
          }
        })();

        if (xhr.status < 200 || xhr.status >= 300 || !payload.success) {
          reject(new Error(payload.message || 'Unable to generate the name-matching CSV.'));
          return;
        }

        updateGeneratorProgress(100, 'Template ready.');
        window.setTimeout(function () {
          resolve(payload);
        }, 220);
      });

      xhr.addEventListener('error', function () {
        if (settled) {
          return;
        }

        settled = true;
        clearGeneratorProgressTimer();
        reject(new Error('Unable to generate the name-matching CSV.'));
      });

      xhr.addEventListener('abort', function () {
        if (settled) {
          return;
        }

        settled = true;
        clearGeneratorProgressTimer();
        reject(new Error('Template generation was cancelled.'));
      });

      updateGeneratorProgress(12, 'Starting upload...');
      startGeneratorProgressRamp(24, 'Starting upload...');
      xhr.send(formData);
    });
  }

  async function showGeneratorAlert(config) {
    const alertConfig = Object.assign({}, config, {
      didOpen: function(popup) {
        if (generatorLoader) {
          generatorLoader.hide();
        }

        if (typeof config.didOpen === 'function') {
          config.didOpen(popup);
        }
      }
    });

    try {
      await Swal.fire(alertConfig);
    } finally {
      if (generatorLoader) {
        generatorLoader.hide();
      }
    }
  }

  form.addEventListener('submit', async function(event) {
    event.preventDefault();

    const formData = new FormData(form);

    if (submitButton) {
      submitButton.disabled = true;
    }

    try {
      openGeneratorProgressModal();
      const payload = await submitGeneratorRequest(formData);
      Swal.close();

      await showGeneratorAlert({
        icon: 'success',
        title: 'Ready',
        text: payload.message || 'Name-matching CSV generated successfully.'
      });

      window.location.reload();
    } catch (error) {
      Swal.close();
      await showGeneratorAlert({
        icon: 'error',
        title: 'Template generation failed',
        text: error && error.message ? error.message : 'Unable to generate the name-matching CSV.'
      });
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
      }
    }
  });
});
</script>

<?php if ($resultModal): ?>
<script>
  (function () {
    const resultModal = window.__kodusMebisConsolidatorResultModal;

    function openResultModal() {
      if (!resultModal) {
        return;
      }

      if (typeof Swal === 'undefined') {
        window.setTimeout(openResultModal, 60);
        return;
      }

      if (window.KodusPageLoader) {
        window.KodusPageLoader.hold('Preparing the result...');
      }

      Swal.fire({
        icon: resultModal.icon,
        title: resultModal.title,
        text: resultModal.text,
        didOpen: function () {
          if (window.KodusPageLoader) {
            window.KodusPageLoader.release();
          }
          window.__kodusPageLoaderHold = false;
        },
        willClose: function () {
          if (window.KodusPageLoader) {
            window.KodusPageLoader.release();
          }
          window.__kodusPageLoaderHold = false;
        }
      });
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', openResultModal, { once: true });
      return;
    }

    openResultModal();
  }());
</script>
<?php endif; ?>
</body>
</html>
