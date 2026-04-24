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

            <form id="mebisTemplateGenerateForm" action="download" method="post" enctype="multipart/form-data" data-loader-text="Building import-ready MEB templates..." data-no-loader="true">
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
                  <th class="text-right pr-3">Download</th>
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
          text: form && form.dataset.loaderText ? form.dataset.loaderText : 'Building LGU template workbooks...'
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
        title: 'Generating Templates',
        html: `
          <div class="text-left">
            <p class="mb-2">Please keep this tab open while we upload and build your import-ready MEB files.</p>
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
          updateGeneratorProgress(Math.max(progressValue, 72), 'Upload complete. Generating workbook templates...');
          startGeneratorProgressRamp(94, 'Formatting rows and preparing downloads...');
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

          updateGeneratorProgress(100, 'Templates ready.');
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

        await showGeneratorAlert({
          icon: 'success',
          title: 'Ready',
          text: payload.message || 'LGU template files generated successfully.'
        });

        window.location.reload();
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
<script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>
