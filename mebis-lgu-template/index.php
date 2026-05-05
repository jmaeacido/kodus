<?php
include('../header.php');
include('../sidenav.php');
require_once __DIR__ . '/helpers/history.php';

$successMessage = $_SESSION['mebis_template_success'] ?? null;
$errorMessage = $_SESSION['mebis_template_error'] ?? null;
unset($_SESSION['mebis_template_success'], $_SESSION['mebis_template_error']);

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

mebis_template_history_ensure_schema($conn);
$savedOutputs = mebis_template_list_outputs($conn);
?>
<?php if ($resultModal !== null): ?>
<script>
  document.documentElement.classList.add('kodus-page-loading');
  window.__kodusPageLoaderHold = true;
  window.__kodusMebisTemplateResultModal = <?= json_encode($resultModal) ?>;
</script>
<?php endif; ?>
<style>
    .mebis-template-card {
      border: 1px solid rgba(25, 135, 84, 0.16);
      border-radius: 1rem;
      background: linear-gradient(135deg, rgba(25, 135, 84, 0.08), rgba(13, 110, 253, 0.05));
      padding: 1rem 1rem 0.9rem;
      margin-bottom: 1rem;
    }

    .mebis-template-card h5 {
      margin-bottom: 0.35rem;
      font-weight: 700;
    }

    .mebis-template-card p {
      margin-bottom: 0;
      color: #6c757d;
    }

    body[data-theme="dark"] .mebis-template-card p {
      color: #b8c7d9;
    }

    .mebis-file-preview {
      margin-top: 1rem;
      padding: 0.9rem 1rem;
      border: 1px dashed rgba(25, 135, 84, 0.35);
      border-radius: 0.9rem;
      background: rgba(25, 135, 84, 0.04);
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

    .mebis-file-preview li:last-child {
      margin-bottom: 0;
    }

    .mebis-generated-files {
      overflow-x: auto;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      max-height: min(28rem, calc(100vh - 18rem));
    }

    .mebis-generated-files table {
      min-width: 860px;
      white-space: nowrap;
    }

    .mebis-job-status-panel {
      display: none;
      border-left: 4px solid #17a2b8;
    }

    .mebis-job-status-panel.is-visible {
      display: block;
    }

    .mebis-job-status-panel.is-completed {
      border-left-color: #28a745;
    }

    .mebis-job-status-panel.is-failed {
      border-left-color: #dc3545;
    }

    .mebis-job-status-panel .progress {
      height: 0.65rem;
      border-radius: 999px;
      overflow: hidden;
    }

    .mebis-job-output-list {
      margin: 0.75rem 0 0;
      padding-left: 1rem;
    }

    .mebis-job-output-list li {
      margin-bottom: 0.4rem;
      word-break: break-word;
    }
</style>
<div class="wrapper">
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">MEB Import Template</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../home">Home</a></li>
              <li class="breadcrumb-item active">MEB Import Template</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header">
            <h4 class="m-0">Upload Final Validated MEB</h4>
          </div>
          <div class="card-body">
            <div class="mebis-template-card">
              <h5>What this tool does</h5>
              <p>Uploads one or more final validated MEB workbooks and converts each one into a simple import-ready template for the app. The output keeps the beneficiary fields needed for import and removes the <strong>PUROK</strong> prefix so values stay uniform and neat.</p>
            </div>

            <form id="mebisTemplateGenerateForm" action="download" method="post" enctype="multipart/form-data" data-loader-text="Uploading MEB workbooks..." data-no-loader="true">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

              <div class="form-group">
                <label>Final Validated MEB Workbooks</label>
                <input type="file" name="mebis_files[]" class="form-control" accept=".xlsx,.xlsm" multiple required id="mebis-template-files">
                <small class="form-text text-muted">Accepted files: <code>.xlsx</code> and <code>.xlsm</code>. Each output is an import-ready workbook named like <code>001_MUNICIPALITY batch 01.xlsx</code>.</small>
              </div>

              <div class="mebis-file-preview" id="mebis-template-preview" hidden>
                <h6>Selected Files</h6>
                <ul id="mebis-template-preview-list"></ul>
              </div>

              <button type="submit" class="btn btn-success mt-3" id="mebisTemplateGenerateButton">
                <i class="fas fa-file-excel mr-1"></i>
                Generate Import Templates
              </button>
            </form>
          </div>
        </div>

        <div class="card mebis-job-status-panel" id="mebisTemplateJobStatusPanel" aria-live="polite">
          <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
              <div>
                <h5 class="mb-1" id="mebisTemplateJobStatusTitle">Background Generation</h5>
                <div class="text-muted" id="mebisTemplateJobStatusMessage">Checking latest job status...</div>
              </div>
              <span class="badge badge-info mt-2 mt-md-0" id="mebisTemplateJobStatusBadge">Queued</span>
            </div>
            <div class="mt-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span id="mebisTemplateJobStatusStep">Queued</span>
                <strong id="mebisTemplateJobStatusProgress">0%</strong>
              </div>
              <div class="progress">
                <div
                  class="progress-bar progress-bar-striped progress-bar-animated bg-info"
                  id="mebisTemplateJobStatusProgressBar"
                  role="progressbar"
                  style="width: 0%;"
                  aria-valuemin="0"
                  aria-valuemax="100"
                  aria-valuenow="0"
                ></div>
              </div>
            </div>
            <div class="mt-3" id="mebisTemplateJobStatusActions" hidden>
              <button type="button" class="btn btn-sm btn-outline-danger" id="mebisTemplateJobCancelButton">
                <i class="fas fa-times mr-1"></i>
                Cancel
              </button>
              <button type="button" class="btn btn-sm btn-outline-secondary ml-1" id="mebisTemplateJobClearButton">
                <i class="fas fa-eraser mr-1"></i>
                Clear
              </button>
            </div>
            <div id="mebisTemplateJobStatusOutputs"></div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h4 class="m-0">Generated Files</h4>
          </div>
          <div class="card-body p-0">
            <div class="mebis-generated-files">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Filename</th>
                  <th>Municipality</th>
                  <th>Rows</th>
                  <th>Source Workbook</th>
                      <th>Created</th>
                      <th class="text-right pr-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($savedOutputs === []): ?>
                  <tr>
                    <td colspan="6" class="text-center text-muted py-4">No generated files yet.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($savedOutputs as $entry): ?>
                    <tr>
                      <td><?= htmlspecialchars((string) $entry['filename'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars((string) $entry['municipality_name'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= number_format((int) ($entry['rows'] ?? 0)) ?></td>
                      <td><?= htmlspecialchars((string) $entry['source_file'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars((string) $entry['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td class="text-right pr-3">
                        <a href="file_csv?id=<?= urlencode((string) $entry['token']) ?>" class="btn btn-sm btn-outline-success" download>
                          <i class="fas fa-download mr-1"></i>
                          CSV
                        </a>
                        <a href="file?id=<?= urlencode((string) $entry['token']) ?>" class="btn btn-sm btn-outline-secondary ml-1" download>
                          <i class="fas fa-file-excel mr-1"></i>
                          XLSX
                        </a>
                        <?php if (!empty($entry['is_imported'])): ?>
                          <span class="badge badge-success ml-1">
                            <i class="fas fa-check mr-1"></i>
                            Imported<?= !empty($entry['imported_batch_id']) ? ' #' . htmlspecialchars((string) $entry['imported_batch_id'], ENT_QUOTES, 'UTF-8') : '' ?>
                          </span>
                        <?php else: ?>
                          <form action="import_generated" method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="token" value="<?= htmlspecialchars((string) $entry['token'], ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-sm btn-success ml-1">
                              <i class="fas fa-file-import mr-1"></i>
                              Import
                            </button>
                          </form>
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

<script>
  (function () {
    const form = document.getElementById('mebisTemplateGenerateForm');
    const input = document.getElementById('mebis-template-files');
    const preview = document.getElementById('mebis-template-preview');
    const list = document.getElementById('mebis-template-preview-list');
    const submitButton = document.getElementById('mebisTemplateGenerateButton');
    const generatorLoader = window.KodusPageLoader && typeof window.KodusPageLoader.createStandaloneLoader === 'function'
      ? window.KodusPageLoader.createStandaloneLoader({
          text: form && form.dataset.loaderText ? form.dataset.loaderText : 'Uploading LGU template workbooks...'
        })
      : null;

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

      const progressBar = document.getElementById('mebisTemplateProgressBar');
      const progressValueLabel = document.getElementById('mebisTemplateProgressValue');
      const progressStatus = document.getElementById('mebisTemplateProgressStatus');

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
        title: 'Uploading Templates',
        html: `
          <div class="text-left">
            <p class="mb-2">Please keep this tab open only while the files upload. Generation will continue in the background.</p>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <strong id="mebisTemplateProgressStatus">Preparing upload...</strong>
              <span id="mebisTemplateProgressValue">8%</span>
            </div>
            <div class="progress" style="height: 0.85rem; border-radius: 999px; overflow: hidden;">
              <div
                id="mebisTemplateProgressBar"
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
        const csrfTokenInput = form.querySelector('input[name="csrf_token"]');
        if (csrfTokenInput && csrfTokenInput.value) {
          xhr.setRequestHeader('X-CSRF-Token', csrfTokenInput.value);
        }

        xhr.upload.addEventListener('progress', function (event) {
          if (!event.lengthComputable) {
            return;
          }

          const percent = Math.round((event.loaded / event.total) * 68);
          updateGeneratorProgress(Math.max(progressValue, percent), 'Uploading workbooks...');
        });

        xhr.upload.addEventListener('load', function () {
          updateGeneratorProgress(Math.max(progressValue, 72), 'Upload complete. Queueing background generation...');
          startGeneratorProgressRamp(94, 'Starting background generator...');
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
              const responseText = (xhr.responseText || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
              const fallbackMessage = xhr.status === 413
                ? 'The uploaded files are larger than the web server allows.'
                : (responseText || 'The server returned an invalid response.');

              return {
                success: false,
                message: fallbackMessage
              };
            }
          })();

          if (xhr.status < 200 || xhr.status >= 300 || !payload.success) {
            reject(new Error(payload.message || `Unable to generate LGU template files. HTTP ${xhr.status}.`));
            return;
          }

          updateGeneratorProgress(100, 'Generation queued.');
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
          reject(new Error('Unable to generate LGU template files.'));
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
        didOpen: function (popup) {
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

    form.addEventListener('submit', async function (event) {
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      event.preventDefault();

      const formData = new FormData(form);

      if (submitButton) {
        submitButton.disabled = true;
      }

      try {
        openGeneratorProgressModal();
        const payload = await submitGeneratorRequest(formData);
        Swal.close();
        window.dispatchEvent(new CustomEvent('mebis-template-job-queued', {
          detail: {
            jobToken: payload.job_token || ''
          }
        }));

        await showGeneratorAlert({
          icon: 'success',
          title: 'Generation Started',
          text: payload.message || 'LGU template generation started in the background.'
        });

        form.reset();
        list.innerHTML = '';
        preview.hidden = true;
      } catch (error) {
        Swal.close();
        await showGeneratorAlert({
          icon: 'error',
          title: 'Template generation failed',
          text: error && error.message ? error.message : 'Unable to generate LGU template files.'
        });
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
        }
      }
    });
  }());

  (function () {
    const panel = document.getElementById('mebisTemplateJobStatusPanel');
    const title = document.getElementById('mebisTemplateJobStatusTitle');
    const message = document.getElementById('mebisTemplateJobStatusMessage');
    const badge = document.getElementById('mebisTemplateJobStatusBadge');
    const step = document.getElementById('mebisTemplateJobStatusStep');
    const progressLabel = document.getElementById('mebisTemplateJobStatusProgress');
    const progressBar = document.getElementById('mebisTemplateJobStatusProgressBar');
    const outputs = document.getElementById('mebisTemplateJobStatusOutputs');
    const actions = document.getElementById('mebisTemplateJobStatusActions');
    const cancelButton = document.getElementById('mebisTemplateJobCancelButton');
    const clearButton = document.getElementById('mebisTemplateJobClearButton');
    let activeJobToken = '';
    let pollTimer = null;
    let importAllRunning = false;
    const alertableJobTokens = new Set();
    const kickedJobTokens = new Set();

    if (!panel || !message || !badge || !step || !progressLabel || !progressBar || !outputs || !actions || !cancelButton || !clearButton) {
      return;
    }

    function escapeTemplateHtml(value) {
      const div = document.createElement('div');
      div.textContent = value == null ? '' : String(value);
      return div.innerHTML;
    }

    function statusLabel(status) {
      const labels = {
        queued: 'Queued',
        processing: 'Processing',
        completed: 'Completed',
        failed: 'Failed',
        canceled: 'Canceled'
      };

      return labels[status] || 'Queued';
    }

    function statusBadgeClass(status) {
      if (status === 'completed') {
        return 'badge badge-success';
      }
      if (status === 'failed') {
        return 'badge badge-danger';
      }
      if (status === 'canceled') {
        return 'badge badge-secondary';
      }
      if (status === 'processing') {
        return 'badge badge-primary';
      }
      return 'badge badge-info';
    }

    function progressBarClass(status) {
      if (status === 'completed') {
        return 'progress-bar bg-success';
      }
      if (status === 'failed') {
        return 'progress-bar bg-danger';
      }
      if (status === 'canceled') {
        return 'progress-bar bg-secondary';
      }
      return 'progress-bar progress-bar-striped progress-bar-animated bg-info';
    }

    function renderOutputs(job) {
      const items = Array.isArray(job.outputs) ? job.outputs : [];
      if (job.status !== 'completed' || items.length === 0) {
        outputs.innerHTML = '';
        return;
      }

      const pendingItems = items.filter(function (item) {
        return item && !item.is_imported && item.token;
      });
      const pendingTokensJson = JSON.stringify(pendingItems.map(function (item) {
        return item.token;
      }));

      outputs.innerHTML = `
        ${pendingItems.length > 0
          ? `<form action="import_generated_all" method="post" class="mt-3 mb-0">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="tokens_json" value="${escapeTemplateHtml(pendingTokensJson)}">
              ${pendingItems.map(function (item) {
                return `<input type="hidden" name="tokens[]" value="${escapeTemplateHtml(item.token || '')}">`;
              }).join('')}
              <button
                type="submit"
                class="btn btn-sm btn-success"
              >
                <i class="fas fa-file-import mr-1"></i>
                Import All
              </button>
            </form>`
          : ''
        }
        <ul class="mebis-job-output-list">
          ${items.map(function (item) {
            return `
              <li>
                <span>${escapeTemplateHtml(item.filename || 'Generated template')}</span>
                <a class="btn btn-sm btn-outline-success ml-2" href="${escapeTemplateHtml(item.csv_url || '#')}" download>CSV</a>
                <a class="btn btn-sm btn-outline-secondary ml-1" href="${escapeTemplateHtml(item.xlsx_url || '#')}" download>XLSX</a>
                ${item.is_imported
                  ? `<span class="badge badge-success ml-2">Imported${item.imported_batch_id ? ` #${escapeTemplateHtml(item.imported_batch_id)}` : ''}</span>`
                  : `<form action="import_generated" method="post" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                      <input type="hidden" name="token" value="${escapeTemplateHtml(item.token || '')}">
                      <button type="submit" class="btn btn-sm btn-success ml-1">Import</button>
                    </form>`
                }
              </li>
            `;
          }).join('')}
        </ul>
      `;
    }

    async function importGeneratedOutput(token) {
      const body = new URLSearchParams();
      body.append('csrf_token', '<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>');
      body.append('token', token);

      const response = await fetch('import_generated', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: body.toString()
      });

      const responseText = await response.text();
      let payload = null;
      try {
        payload = JSON.parse(responseText || '{}');
      } catch (error) {
        const fallbackMessage = responseText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        throw new Error(fallbackMessage || 'The import endpoint returned an invalid response.');
      }

      if (!response.ok || !payload || !payload.success) {
        throw new Error((payload && payload.message) || 'Import failed.');
      }

      return payload;
    }

    function setImportAllState(button, status, isBusy) {
      if (button) {
        button.disabled = isBusy;
      }

      const statusElement = document.getElementById('mebisTemplateImportAllStatus');
      if (statusElement) {
        statusElement.textContent = status || '';
      }
    }

    async function runImportAll(button) {
      if (!button || importAllRunning) {
        return;
      }

      let tokens = [];
      try {
        tokens = JSON.parse(button.dataset.tokens || '[]');
      } catch (error) {
        tokens = [];
      }

      tokens = tokens.filter(function (token) {
        return typeof token === 'string' && token !== '';
      });

      if (tokens.length === 0) {
        return;
      }

      const originalText = button.innerHTML;
      const imported = [];
      importAllRunning = true;
      setImportAllState(button, `Importing 1 of ${tokens.length}...`, true);

      try {
        for (let index = 0; index < tokens.length; index += 1) {
          button.innerHTML = `<i class="fas fa-spinner fa-spin mr-1"></i> Importing ${index + 1}/${tokens.length}`;
          setImportAllState(button, `Importing ${index + 1} of ${tokens.length}...`, true);
          const payload = await importGeneratedOutput(tokens[index]);
          imported.push(payload);
          await refreshJobStatus(activeJobToken);
        }

        importAllRunning = false;
        button.innerHTML = originalText;
        setImportAllState(button, '', false);

        if (typeof Swal !== 'undefined') {
          await Swal.fire({
            icon: 'success',
            title: 'Import Complete',
            text: `${imported.length} generated file${imported.length === 1 ? '' : 's'} imported. Each file was imported as a separate batch.`
          });
        }

        await refreshJobStatus(activeJobToken);
      } catch (error) {
        importAllRunning = false;
        button.innerHTML = originalText;
        setImportAllState(button, error && error.message ? error.message : 'Import failed.', false);

        if (typeof Swal !== 'undefined') {
          await Swal.fire({
            icon: 'error',
            title: 'Import Failed',
            text: error && error.message ? error.message : 'One of the generated files could not be imported.'
          });
        }

        await refreshJobStatus(activeJobToken);
      }
    }

    window.mebisTemplateImportAll = runImportAll;

    function maybeShowTerminalAlert(job) {
      if (!job || !job.job_token || !['completed', 'failed'].includes(job.status)) {
        return;
      }

      if (!alertableJobTokens.has(job.job_token)) {
        return;
      }

      const storageKey = `mebisTemplateJobAlerted:${job.job_token}:${job.status}`;
      if (window.localStorage && window.localStorage.getItem(storageKey) === '1') {
        return;
      }

      if (window.localStorage) {
        window.localStorage.setItem(storageKey, '1');
      }

      if (typeof Swal === 'undefined') {
        return;
      }

      Swal.fire({
        icon: job.status === 'completed' ? 'success' : 'error',
        title: job.status === 'completed' ? 'Templates Ready' : 'Template Generation Failed',
        text: job.message || (job.status === 'completed'
          ? 'LGU template files are ready to download.'
          : 'Unable to generate LGU template files.')
      });
    }

    function renderJob(job) {
      if (!job) {
        panel.classList.remove('is-visible', 'is-completed', 'is-failed');
        return;
      }

      const status = String(job.status || 'queued');
      const progress = Math.max(0, Math.min(100, Number(job.progress || 0)));
      activeJobToken = job.job_token || activeJobToken;

      panel.classList.add('is-visible');
      panel.classList.toggle('is-completed', status === 'completed');
      panel.classList.toggle('is-failed', status === 'failed');

      if (title) {
        title.textContent = 'Background Generation';
      }
      badge.className = statusBadgeClass(status);
      badge.textContent = statusLabel(status);
      message.textContent = job.message || 'Checking latest job status...';
      step.textContent = job.current_step || statusLabel(status);
      progressLabel.textContent = `${Math.round(progress)}%`;
      progressBar.className = progressBarClass(status);
      progressBar.style.width = `${progress}%`;
      progressBar.setAttribute('aria-valuenow', String(Math.round(progress)));
      actions.hidden = false;
      cancelButton.disabled = !['queued', 'processing'].includes(status);
      cancelButton.hidden = !['queued', 'processing'].includes(status);
      clearButton.hidden = ['queued', 'processing'].includes(status);

      renderOutputs(job);
      maybeShowTerminalAlert(job);

      if (status === 'queued') {
        kickJobRunner(activeJobToken);
      }

      if (window.refreshAppNotifications && ['completed', 'failed'].includes(status)) {
        window.refreshAppNotifications();
      }
    }

    function kickJobRunner(jobToken) {
      if (!jobToken || kickedJobTokens.has(jobToken)) {
        return;
      }

      kickedJobTokens.add(jobToken);
      const body = new URLSearchParams();
      body.append('job', jobToken);
      body.append('csrf_token', window.KODUS_CSRF_TOKEN || '');

      fetch('run_job', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: body.toString()
      }).catch(function () {
        kickedJobTokens.delete(jobToken);
      });
    }

    function refreshJobStatus(jobToken) {
      const query = jobToken ? `?job=${encodeURIComponent(jobToken)}` : '';
      return fetch(`job_status${query}`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (payload) {
          if (!payload || !payload.success) {
            return null;
          }

          renderJob(payload.job || null);
          return payload.job || null;
        })
        .catch(function () {
          return null;
        });
    }

    function cancelActiveJob() {
      if (!activeJobToken || cancelButton.disabled) {
        return;
      }

      cancelButton.disabled = true;
      const body = new URLSearchParams();
      body.append('job', activeJobToken);
      body.append('csrf_token', window.KODUS_CSRF_TOKEN || '');

      fetch('cancel_job', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: body.toString()
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (payload) {
          if (payload && payload.job) {
            renderJob(payload.job);
          }
          if (!payload || !payload.success) {
            throw new Error(payload && payload.message ? payload.message : 'Unable to cancel the background job.');
          }
        })
        .catch(function (error) {
          cancelButton.disabled = false;
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'error',
              title: 'Cancel Failed',
              text: error && error.message ? error.message : 'Unable to cancel the background job.'
            });
          }
        });
    }

    function schedulePolling(jobToken) {
      if (pollTimer) {
        window.clearInterval(pollTimer);
      }

      pollTimer = window.setInterval(function () {
        refreshJobStatus(activeJobToken || jobToken).then(function (latestJob) {
          const latestStatus = latestJob && latestJob.status ? String(latestJob.status) : '';
          if (latestStatus === 'completed' || latestStatus === 'failed' || latestStatus === 'canceled') {
            window.clearInterval(pollTimer);
            pollTimer = null;
          }
        });
      }, 1800);

      refreshJobStatus(jobToken).then(function (job) {
        const status = job && job.status ? String(job.status) : '';
        if (status === 'completed' || status === 'failed' || status === 'canceled') {
          window.clearInterval(pollTimer);
          pollTimer = null;
          return;
        }
      });
    }

    function renderQueuedPlaceholder(jobToken) {
      renderJob({
        job_token: jobToken,
        status: 'queued',
        progress: 5,
        current_step: 'Queued',
        message: 'Waiting for the background generator to start.',
        outputs: []
      });
    }

    window.addEventListener('mebis-template-job-queued', function (event) {
      activeJobToken = event.detail && event.detail.jobToken ? String(event.detail.jobToken) : '';
      if (activeJobToken) {
        alertableJobTokens.add(activeJobToken);
        renderQueuedPlaceholder(activeJobToken);
        kickJobRunner(activeJobToken);
      }
      schedulePolling(activeJobToken);
    });

    cancelButton.addEventListener('click', cancelActiveJob);
    clearButton.addEventListener('click', function () {
      if (pollTimer) {
        window.clearInterval(pollTimer);
        pollTimer = null;
      }
      activeJobToken = '';
      panel.classList.remove('is-visible', 'is-completed', 'is-failed');
      outputs.innerHTML = '';
    });
    outputs.addEventListener('click', function (event) {
      const button = event.target && typeof event.target.closest === 'function'
        ? event.target.closest('#mebisTemplateImportAllButton')
        : null;
      if (!button || importAllRunning) {
        return;
      }

      event.preventDefault();
      runImportAll(button);
    });

    schedulePolling('');
  }());

  (function () {
    const resultModal = window.__kodusMebisTemplateResultModal;

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
<script src="<?php echo $app_root; ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>
