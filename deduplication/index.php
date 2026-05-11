<?php
include('../header.php');
include('../sidenav.php');
require_once __DIR__ . '/helpers/generator_history.php';

$dedupTemplateSuccess = $_SESSION['dedup_template_success'] ?? null;
$dedupTemplateError = $_SESSION['dedup_template_error'] ?? null;
unset($_SESSION['dedup_template_success'], $_SESSION['dedup_template_error']);

$dedupTemplateModal = null;
if ($dedupTemplateError) {
    $dedupTemplateModal = [
        'icon' => 'error',
        'title' => 'Generator failed',
        'text' => (string) $dedupTemplateError,
    ];
} elseif ($dedupTemplateSuccess) {
    $dedupTemplateModal = [
        'icon' => 'success',
        'title' => 'Generator ready',
        'text' => (string) $dedupTemplateSuccess,
    ];
}

dedup_template_history_ensure_schema($conn);
$dedupGeneratedFiles = dedup_template_list_outputs(
    $conn,
    (int) ($_SESSION['user_id'] ?? 0),
    (string) ($_SESSION['user_type'] ?? 'user')
);
?>
<?php if ($dedupTemplateModal !== null): ?>
<script>
  document.documentElement.classList.add('kodus-page-loading');
  window.__kodusPageLoaderHold = true;
  window.__kodusDedupTemplateResultModal = <?= json_encode($dedupTemplateModal) ?>;
</script>
<?php endif; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | Deduplication</title>
  <link rel="stylesheet" href="../cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <style>
    #recent-dedupe-table th,
    #recent-dedupe-table td {
      text-align: center;
      vertical-align: middle;
    }

    #recent-dedupe-table th {
      font-size: 14px;
    }

    .dedup-generator-card {
      border: 1px solid rgba(13, 110, 253, 0.14);
      border-radius: 1rem;
      background: linear-gradient(135deg, rgba(13, 110, 253, 0.08), rgba(32, 201, 151, 0.08));
      padding: 1rem 1rem 0.9rem;
      margin-bottom: 1rem;
    }

    .dedup-generator-card h5 {
      margin-bottom: 0.35rem;
      font-weight: 700;
    }

    .dedup-generator-card p {
      margin-bottom: 0;
      color: #6c757d;
    }

    body[data-theme="dark"] .dedup-generator-card p {
      color: #b8c7d9;
    }

    .dedup-file-preview {
      margin-top: 1rem;
      padding: 0.9rem 1rem;
      border: 1px dashed rgba(13, 110, 253, 0.35);
      border-radius: 0.9rem;
      background: rgba(13, 110, 253, 0.04);
    }

    .dedup-file-preview h6 {
      margin-bottom: 0.65rem;
      font-weight: 700;
    }

    .dedup-file-preview ul {
      margin: 0;
      padding-left: 1.15rem;
    }

    .dedup-file-preview li {
      margin-bottom: 0.25rem;
      word-break: break-word;
    }

    .dedup-file-preview li:last-child {
      margin-bottom: 0;
    }

    .dedup-generated-files {
      overflow-x: auto;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      max-height: min(26rem, calc(100vh - 18rem));
    }

    .dedup-generated-files table {
      min-width: 760px;
      white-space: nowrap;
    }

    .dedup-job-status-panel {
      display: none;
      border-left: 4px solid #17a2b8;
    }

    .dedup-job-status-panel.is-visible {
      display: block;
    }

    .dedup-job-status-panel.is-completed {
      border-left-color: #28a745;
    }

    .dedup-job-status-panel.is-failed {
      border-left-color: #dc3545;
    }

    .dedup-job-status-panel .progress {
      height: 0.65rem;
      border-radius: 999px;
      overflow: hidden;
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
            <h1 class="m-0">Deduplication Tool</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../home">Home</a></li>
              <li class="breadcrumb-item active">Deduplication</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h4 class="m-0 flex-grow-1">Beneficiary Deduplication</h4>
        </div>
        <div class="card-body">
          <form id="deduplicationRunForm" action="upload_handler.php" method="POST" enctype="multipart/form-data" data-loader-text="Starting beneficiary deduplication..." data-no-loader="true">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="threshold">Threshold (%)</label>
                <input type="number" name="threshold" id="threshold" class="form-control" min="50" max="100" value="85" required>
              </div>

              <div class="col-md-6">
                <label for="rule">Matching Rule</label>
                <select name="rule" id="rule" class="form-control" required>
                  <option value="soft">Soft (fuzzy)</option>
                  <option value="strict">Strict</option>
                </select>
              </div>

              <div class="col-12"><hr></div>

              <div class="col-12">
                <label for="file">Upload Excel/CSV file</label>
                <input type="file" name="file" id="file" class="form-control" accept=".csv, .xlsx" required>
                <div class="form-text" style="color:orange; font-style: italic;">Headers required: rowNumber, lastName, firstName, middleName, ext, birthDate, barangay, lgu, province</div>
              </div>

              <div class="col-12 mt-3">
                <button type="submit" class="btn btn-primary" id="deduplicationRunButton">Start Deduplication</button>
                <a class="btn btn-link" href="helpers/Deduplication_Template.xlsx" download>Download Template</a>
              </div>
            </div>
          </form>
        </div>
      </div>

      <div class="card dedup-job-status-panel" id="dedupJobStatusPanel" aria-live="polite">
        <div class="card-body">
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div>
              <h5 class="mb-1" id="dedupJobStatusTitle">Background Generation</h5>
              <div class="text-muted" id="dedupJobStatusMessage">Checking latest job status...</div>
            </div>
            <span class="badge badge-info mt-2 mt-md-0" id="dedupJobStatusBadge">Queued</span>
          </div>
          <div class="mt-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span id="dedupJobStatusStep">Queued</span>
              <strong id="dedupJobStatusProgress">0%</strong>
            </div>
            <div class="progress">
              <div
                class="progress-bar progress-bar-striped progress-bar-animated bg-info"
                id="dedupJobStatusProgressBar"
                role="progressbar"
                style="width: 0%;"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="0"
              ></div>
            </div>
          </div>
          <div class="mt-3" id="dedupJobStatusActions" hidden>
            <a class="btn btn-sm btn-success" id="dedupJobViewResults" href="#" hidden>
              <i class="fas fa-list mr-1"></i>
              View Results
            </a>
            <button type="button" class="btn btn-sm btn-outline-danger ml-1" id="dedupJobCancelButton">
              <i class="fas fa-times mr-1"></i>
              Cancel
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary ml-1" id="dedupJobClearButton">
              <i class="fas fa-eraser mr-1"></i>
              Clear
            </button>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h4 class="m-0 flex-grow-1">Deduplication Template Generator</h4>
        </div>
        <div class="card-body">
          <div class="dedup-generator-card">
            <h5>What this generator does</h5>
            <p>Uploads one or more final validated MEB workbooks and converts each one into a deduplication-ready workbook that matches the exact <strong>Deduplication_Template.xlsx</strong> structure: <code>rowNumber</code>, <code>lastName</code>, <code>firstName</code>, <code>middleName</code>, <code>ext</code>, <code>birthDate</code>, <code>barangay</code>, <code>lgu</code>, and <code>province</code>.</p>
          </div>

          <form id="dedupTemplateGenerateForm" action="generate_template.php" method="post" enctype="multipart/form-data" data-loader-text="Building deduplication-ready templates..." data-no-loader="true">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="template_action" value="generate" id="dedupTemplateAction">

            <div class="form-group">
              <label for="dedup-template-files">Final Validated MEB Workbooks</label>
              <input type="file" name="template_files[]" class="form-control" accept=".xlsx,.xlsm" multiple required id="dedup-template-files">
              <small class="form-text text-muted">Accepted files: <code>.xlsx</code> and <code>.xlsm</code>. Each generated workbook uses the same beneficiary sheet and columns required by the Deduplication tool.</small>
            </div>

            <div class="dedup-file-preview" id="dedup-template-preview" hidden>
              <h6>Selected Files</h6>
              <ul id="dedup-template-preview-list"></ul>
            </div>

            <div class="btn-group mt-3">
              <button type="submit" class="btn btn-success" id="dedupTemplateGenerateButton" data-template-action="generate">
                <i class="fas fa-file-excel mr-1"></i>
                Generate Deduplication Templates
              </button>
              <button type="button" class="btn btn-success dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <span class="sr-only">Toggle template generator actions</span>
              </button>
              <div class="dropdown-menu">
                <button type="submit" class="dropdown-item" data-template-action="generate_and_deduplicate">
                  Generate Deduplication Templates and Deduplicate
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h4 class="m-0 flex-grow-1">Recent Deduplications</h4>
        </div>
        <div class="card-body" id="recent-dedupe-container" style="height: 46vh; overflow-y: auto;">
          <table class="table table-bordered table-striped" id="recent-dedupe-table">
            <thead>
              <tr>
                <th>Job</th>
                <th>Rule</th>
                <th>Threshold</th>
                <th>Possible Duplicates</th>
                <th>Created at</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h4 class="m-0 flex-grow-1">Generated Template Files</h4>
        </div>
        <div class="card-body p-0">
          <div class="dedup-generated-files">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Filename</th>
                  <th>Municipality</th>
                  <th>Rows</th>
                  <th>Source Workbook</th>
                  <th>Created</th>
                  <th class="text-right pr-3">Download</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($dedupGeneratedFiles === []): ?>
                  <tr>
                    <td colspan="6" class="text-center text-muted py-4">No generated template files yet.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($dedupGeneratedFiles as $entry): ?>
                    <tr>
                      <td><?= htmlspecialchars((string) $entry['filename'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars((string) $entry['municipality_name'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= number_format((int) ($entry['rows'] ?? 0)) ?></td>
                      <td><?= htmlspecialchars((string) $entry['source_file'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars((string) ($entry['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      <td class="text-right pr-3">
                        <a href="template_file.php?id=<?= urlencode((string) $entry['token']) ?>" class="btn btn-sm btn-outline-success">
                          <i class="fas fa-download mr-1"></i>
                          Download
                        </a>
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
<script src="../cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="../cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="../dist/js/adminlte.min.js"></script>
<script>
  let recentDedupeTable;

  function loadRecentDeduplications() {
      $.ajax({
          url: 'fetch_recent_deduplications.php',
          method: 'GET',
          dataType: 'html',
          success: function(data) {
              if (!recentDedupeTable) {
                  $('#recent-dedupe-table tbody').html(data);
                  recentDedupeTable = $('#recent-dedupe-table').DataTable({
                      paging: true,
                      pageLength: 10,
                      lengthChange: false,
                      searching: true,
                      info: true,
                      autoWidth: false,
                      order: [[0, 'desc']]
                  });
              } else {
                  recentDedupeTable.clear().draw();
                  $('#recent-dedupe-table tbody').fadeOut(200, function() {
                      $(this).html(data).fadeIn(400, function() {
                          recentDedupeTable.rows.add($('#recent-dedupe-table tbody tr')).draw(false);
                      });
                  });
              }
          },
          error: function() {
              console.error('Failed to load recent deduplications.');
          }
      });
  }

  loadRecentDeduplications();

  if (window.KODUSLiveRefresh) {
      window.KODUSLiveRefresh.watch({
          channels: ['deduplication_recent_table'],
          allowPollingFallback: true,
          onChange: loadRecentDeduplications
      });
  }

  window.addEventListener('kodus:partial-refresh', function () {
      loadRecentDeduplications();
  });

  (function () {
      const form = document.getElementById('deduplicationRunForm');
      const submitButton = document.getElementById('deduplicationRunButton');

      if (!form) {
          return;
      }

      let progressTimer = null;
      let progressValue = 0;

      function clearRunProgressTimer() {
          if (progressTimer) {
              window.clearInterval(progressTimer);
              progressTimer = null;
          }
      }

      function updateRunProgress(value, statusText) {
          progressValue = Math.max(0, Math.min(100, Number(value || 0)));
          const progressBar = document.getElementById('dedupRunProgressBar');
          const progressValueLabel = document.getElementById('dedupRunProgressValue');
          const progressStatus = document.getElementById('dedupRunProgressStatus');

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

      function startRunProgressRamp(targetValue, statusText) {
          clearRunProgressTimer();
          progressTimer = window.setInterval(function () {
              if (progressValue >= targetValue) {
                  clearRunProgressTimer();
                  return;
              }

              const remaining = targetValue - progressValue;
              const increment = remaining > 14 ? 4 : (remaining > 6 ? 2 : 1);
              updateRunProgress(progressValue + increment, statusText);
          }, 180);
      }

      function openRunProgressModal() {
          progressValue = 8;

          return Swal.fire({
              title: 'Starting Deduplication',
              html: `
                <div class="text-left">
                  <p class="mb-2">Please keep this tab open while we upload and validate your beneficiary file.</p>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong id="dedupRunProgressStatus">Preparing upload...</strong>
                    <span id="dedupRunProgressValue">8%</span>
                  </div>
                  <div class="progress" style="height: 0.85rem; border-radius: 999px; overflow: hidden;">
                    <div id="dedupRunProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 8%;" aria-valuemin="0" aria-valuemax="100" aria-valuenow="8"></div>
                  </div>
                </div>
              `,
              allowOutsideClick: false,
              allowEscapeKey: false,
              showConfirmButton: false,
              didOpen: function () {
                  updateRunProgress(8, 'Preparing upload...');
              }
          });
      }

      function submitDeduplicationRequest(formData) {
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
                  updateRunProgress(Math.max(progressValue, percent), 'Uploading beneficiary file...');
              });

              xhr.upload.addEventListener('load', function () {
                  updateRunProgress(Math.max(progressValue, 72), 'Upload complete. Validating template...');
                  startRunProgressRamp(94, 'Creating deduplication job...');
              });

              xhr.addEventListener('load', function () {
                  if (settled) {
                      return;
                  }

                  settled = true;
                  clearRunProgressTimer();

                  const payload = (() => {
                      try {
                          return JSON.parse(xhr.responseText || '{}');
                      } catch (error) {
                          const responseText = (xhr.responseText || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                          return { success: false, message: responseText || 'The server returned an invalid response.' };
                      }
                  })();

                  if (xhr.status < 200 || xhr.status >= 300 || !payload.success) {
                      reject(new Error(payload.message || 'Unable to start deduplication.'));
                      return;
                  }

                  updateRunProgress(100, 'Deduplication job started.');
                  window.setTimeout(function () {
                      resolve(payload);
                  }, 220);
              });

              xhr.addEventListener('error', function () {
                  if (settled) {
                      return;
                  }

                  settled = true;
                  clearRunProgressTimer();
                  reject(new Error('Unable to start deduplication.'));
              });

              xhr.addEventListener('abort', function () {
                  if (settled) {
                      return;
                  }

                  settled = true;
                  clearRunProgressTimer();
                  reject(new Error('Deduplication upload was cancelled.'));
              });

              updateRunProgress(12, 'Starting upload...');
              startRunProgressRamp(24, 'Starting upload...');
              xhr.send(formData);
          });
      }

      form.addEventListener('submit', async function (event) {
          event.preventDefault();

          if (!form.checkValidity()) {
              form.reportValidity();
              return;
          }

          const formData = new FormData(form);

          if (submitButton) {
              submitButton.disabled = true;
          }

          try {
              openRunProgressModal();
              const payload = await submitDeduplicationRequest(formData);
              Swal.close();
              form.reset();
              window.dispatchEvent(new CustomEvent('deduplication-job-queued', {
                  detail: {
                      jobId: payload.job_id || ''
                  }
              }));
              await Swal.fire({
                  icon: 'success',
                  title: 'Deduplication Started',
                  text: payload.message || 'Deduplication job started in the background.'
              });
          } catch (error) {
              Swal.close();
              await Swal.fire({
                  icon: 'error',
                  title: 'Deduplication failed',
                  text: error && error.message ? error.message : 'Unable to start deduplication.'
              });
          } finally {
              if (submitButton) {
                  submitButton.disabled = false;
              }
          }
      });
  }());

  (function () {
      const panel = document.getElementById('dedupJobStatusPanel');
      const message = document.getElementById('dedupJobStatusMessage');
      const badge = document.getElementById('dedupJobStatusBadge');
      const step = document.getElementById('dedupJobStatusStep');
      const progressLabel = document.getElementById('dedupJobStatusProgress');
      const progressBar = document.getElementById('dedupJobStatusProgressBar');
      const actions = document.getElementById('dedupJobStatusActions');
      const viewResults = document.getElementById('dedupJobViewResults');
      const cancelButton = document.getElementById('dedupJobCancelButton');
      const clearButton = document.getElementById('dedupJobClearButton');
      let activeJobId = '';
      let pollTimer = null;

      if (!panel || !message || !badge || !step || !progressLabel || !progressBar || !actions || !viewResults || !cancelButton || !clearButton) {
          return;
      }

      function statusLabel(status) {
          const labels = {
              pending: 'Queued',
              processing: 'Processing',
              done: 'Completed',
              failed: 'Failed',
              cancelled: 'Canceled',
              canceled: 'Canceled'
          };

          return labels[status] || 'Queued';
      }

      function badgeClass(status) {
          if (status === 'done') {
              return 'badge badge-success';
          }
          if (status === 'failed') {
              return 'badge badge-danger';
          }
          if (status === 'cancelled' || status === 'canceled') {
              return 'badge badge-secondary';
          }
          if (status === 'processing') {
              return 'badge badge-primary';
          }
          return 'badge badge-info';
      }

      function progressClass(status) {
          if (status === 'done') {
              return 'progress-bar bg-success';
          }
          if (status === 'failed') {
              return 'progress-bar bg-danger';
          }
          if (status === 'cancelled' || status === 'canceled') {
              return 'progress-bar bg-secondary';
          }
          return 'progress-bar progress-bar-striped progress-bar-animated bg-info';
      }

      function statusMessage(status, progress) {
          if (status === 'done') {
              return 'Deduplication complete.';
          }
          if (status === 'failed') {
              return 'Deduplication failed. Please check logs.';
          }
          if (status === 'cancelled' || status === 'canceled') {
              return 'Deduplication was canceled.';
          }
          if (status === 'pending') {
              return 'Waiting for the background generator to start.';
          }
          return progress >= 100 ? 'Finalizing results...' : 'Comparing beneficiary records in the background.';
      }

      function renderStatus(job) {
          if (!job) {
              return;
          }

          const status = String(job.status || 'pending').toLowerCase();
          const progress = Math.max(0, Math.min(100, Number(job.progress || 0)));
          const isTerminal = ['done', 'failed', 'cancelled', 'canceled'].includes(status);

          panel.classList.add('is-visible');
          panel.classList.toggle('is-completed', status === 'done');
          panel.classList.toggle('is-failed', status === 'failed');
          badge.className = badgeClass(status);
          badge.textContent = statusLabel(status);
          message.textContent = statusMessage(status, progress);
          step.textContent = statusLabel(status);
          progressLabel.textContent = `${Math.round(progress)}%`;
          progressBar.className = progressClass(status);
          progressBar.style.width = `${progress}%`;
          progressBar.setAttribute('aria-valuenow', String(Math.round(progress)));
          actions.hidden = false;
          viewResults.hidden = status !== 'done';
          viewResults.href = activeJobId ? `results.php?job=${encodeURIComponent(activeJobId)}` : '#';
          cancelButton.disabled = isTerminal;
          cancelButton.hidden = isTerminal;
          clearButton.hidden = !isTerminal;

          if (window.refreshAppNotifications && isTerminal) {
              window.refreshAppNotifications();
          }
      }

      function refreshStatus(jobId) {
          if (!jobId) {
              return Promise.resolve(null);
          }

          return fetch(`status_api.php?job=${encodeURIComponent(jobId)}`, {
              headers: {
                  'Accept': 'application/json',
                  'X-Requested-With': 'XMLHttpRequest'
              },
              credentials: 'same-origin'
          })
              .then(function (response) {
                  return response.json();
              })
              .then(function (job) {
                  renderStatus(job);
                  return job;
              })
              .catch(function () {
                  return null;
              });
      }

      function schedulePolling(jobId) {
          activeJobId = jobId || activeJobId;
          if (!activeJobId) {
              return;
          }

          if (pollTimer) {
              window.clearInterval(pollTimer);
          }

          refreshStatus(activeJobId);
          pollTimer = window.setInterval(function () {
              refreshStatus(activeJobId).then(function (job) {
                  const status = job && job.status ? String(job.status).toLowerCase() : '';
                  if (['done', 'failed', 'cancelled', 'canceled'].includes(status)) {
                      window.clearInterval(pollTimer);
                      pollTimer = null;
                      loadRecentDeduplications();
                  }
              });
          }, 1800);
      }

      function cancelActiveJob() {
          if (!activeJobId || cancelButton.disabled) {
              return;
          }

          cancelButton.disabled = true;
          const body = new URLSearchParams();
          body.append('job', activeJobId);
          body.append('csrf_token', <?= json_encode(security_get_csrf_token()) ?>);

          fetch('cancel_job.php', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                  'Accept': 'text/plain, application/json',
                  'X-Requested-With': 'XMLHttpRequest'
              },
              credentials: 'same-origin',
              body: body.toString()
          })
              .then(function () {
                  return refreshStatus(activeJobId);
              })
              .catch(function () {
                  cancelButton.disabled = false;
                  if (typeof Swal !== 'undefined') {
                      Swal.fire({
                          icon: 'error',
                          title: 'Cancel Failed',
                          text: 'Unable to cancel the background job.'
                      });
                  }
              });
      }

      window.addEventListener('deduplication-job-queued', function (event) {
          const jobId = event.detail && event.detail.jobId ? String(event.detail.jobId) : '';
          if (!jobId) {
              return;
          }

          activeJobId = jobId;
          renderStatus({
              status: 'pending',
              progress: 0
          });
          schedulePolling(jobId);
      });

      cancelButton.addEventListener('click', cancelActiveJob);
      clearButton.addEventListener('click', function () {
          if (pollTimer) {
              window.clearInterval(pollTimer);
              pollTimer = null;
          }
          activeJobId = '';
          panel.classList.remove('is-visible', 'is-completed', 'is-failed');
      });
  }());

  (function () {
      const form = document.getElementById('dedupTemplateGenerateForm');
      const input = document.getElementById('dedup-template-files');
      const preview = document.getElementById('dedup-template-preview');
      const list = document.getElementById('dedup-template-preview-list');
      const submitButton = document.getElementById('dedupTemplateGenerateButton');
      const actionInput = document.getElementById('dedupTemplateAction');

      if (!input || !preview || !list) {
          return;
      }

      input.addEventListener('change', function () {
          const files = Array.from(input.files || []);
          list.innerHTML = '';

          if (files.length === 0) {
              preview.hidden = true;
              return;
          }

          files.forEach(function (file) {
              const item = document.createElement('li');
              item.textContent = file.name;
              list.appendChild(item);
          });

          preview.hidden = false;
      });

      if (!form) {
          return;
      }

      form.addEventListener('click', function (event) {
          const actionButton = event.target && event.target.closest('[data-template-action]');
          if (actionButton && actionInput) {
              actionInput.value = actionButton.getAttribute('data-template-action') || 'generate';
          }
      });

      let progressTimer = null;
      let progressValue = 0;

      function clearProgressTimer() {
          if (progressTimer) {
              window.clearInterval(progressTimer);
              progressTimer = null;
          }
      }

      function updateProgress(value, statusText) {
          progressValue = Math.max(0, Math.min(100, Number(value || 0)));
          const progressBar = document.getElementById('dedupTemplateProgressBar');
          const progressValueLabel = document.getElementById('dedupTemplateProgressValue');
          const progressStatus = document.getElementById('dedupTemplateProgressStatus');

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

      function startProgressRamp(targetValue, statusText) {
          clearProgressTimer();
          progressTimer = window.setInterval(function () {
              if (progressValue >= targetValue) {
                  clearProgressTimer();
                  return;
              }

              const remaining = targetValue - progressValue;
              const increment = remaining > 14 ? 4 : (remaining > 6 ? 2 : 1);
              updateProgress(progressValue + increment, statusText);
          }, 180);
      }

      function openProgressModal() {
          progressValue = 8;
          const isCombinedAction = actionInput && actionInput.value === 'generate_and_deduplicate';

          return Swal.fire({
              title: 'Generating Templates',
              html: `
                <div class="text-left">
                  <p class="mb-2">${isCombinedAction ? 'Please keep this tab open while we generate templates and queue deduplication.' : 'Please keep this tab open while we upload and build your deduplication-ready files.'}</p>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong id="dedupTemplateProgressStatus">Preparing upload...</strong>
                    <span id="dedupTemplateProgressValue">8%</span>
                  </div>
                  <div class="progress" style="height: 0.85rem; border-radius: 999px; overflow: hidden;">
                    <div id="dedupTemplateProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 8%;" aria-valuemin="0" aria-valuemax="100" aria-valuenow="8"></div>
                  </div>
                </div>
              `,
              allowOutsideClick: false,
              allowEscapeKey: false,
              showConfirmButton: false,
              didOpen: function () {
                  updateProgress(8, 'Preparing upload...');
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
                  updateProgress(Math.max(progressValue, percent), 'Uploading workbooks...');
              });

              xhr.upload.addEventListener('load', function () {
                  const isCombinedAction = actionInput && actionInput.value === 'generate_and_deduplicate';
                  updateProgress(Math.max(progressValue, 72), 'Upload complete. Generating deduplication templates...');
                  startProgressRamp(94, isCombinedAction ? 'Formatting rows and queueing deduplication...' : 'Formatting rows and preparing downloads...');
              });

              xhr.addEventListener('load', function () {
                  if (settled) {
                      return;
                  }

                  settled = true;
                  clearProgressTimer();

                  const payload = (() => {
                      try {
                          return JSON.parse(xhr.responseText || '{}');
                      } catch (error) {
                          return { success: false, message: 'The server returned an invalid response.' };
                      }
                  })();

                  if (xhr.status < 200 || xhr.status >= 300 || !payload.success) {
                      reject(new Error(payload.message || 'Unable to generate deduplication template files.'));
                      return;
                  }

                  updateProgress(100, payload.redirect ? 'Deduplication job started.' : 'Templates ready.');
                  window.setTimeout(function () {
                      resolve(payload);
                  }, 220);
              });

              xhr.addEventListener('error', function () {
                  if (settled) {
                      return;
                  }

                  settled = true;
                  clearProgressTimer();
                  reject(new Error('Unable to generate deduplication template files.'));
              });

              xhr.addEventListener('abort', function () {
                  if (settled) {
                      return;
                  }

                  settled = true;
                  clearProgressTimer();
                  reject(new Error('Template generation was cancelled.'));
              });

              updateProgress(12, 'Starting upload...');
              startProgressRamp(24, 'Starting upload...');
              xhr.send(formData);
          });
      }

      form.addEventListener('submit', async function (event) {
          event.preventDefault();
          const formData = new FormData(form);

          if (submitButton) {
              submitButton.disabled = true;
          }

          try {
              openProgressModal();
              const payload = await submitGeneratorRequest(formData);

              if (payload.redirect) {
                  window.location.href = payload.redirect;
                  return;
              }

              Swal.close();
              await Swal.fire({
                  icon: 'success',
                  title: 'Generator ready',
                  text: payload.message || 'Deduplication template files generated successfully.'
              });
              window.location.reload();
          } catch (error) {
              Swal.close();
              await Swal.fire({
                  icon: 'error',
                  title: 'Generator failed',
                  text: error && error.message ? error.message : 'Unable to generate deduplication template files.'
              });
          } finally {
              if (submitButton) {
                  submitButton.disabled = false;
              }
          }
      });
  }());

  (function () {
      const resultModal = window.__kodusDedupTemplateResultModal;
      if (!resultModal) {
          return;
      }

      function openResultModal() {
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

</body>
</html>
