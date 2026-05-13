<?php
// crossmatch/index.php
// Entry UI: choose mode + threshold + upload files
  include('../header.php');

  // if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
      // header("HTTP/1.1 403 Forbidden");
      // echo "Access denied. Admins only.";
      // exit;
  // }
  
  include('../sidenav.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>KODUS | Crossmatch</title>
  <style>
    /* Center all table headers and cells in #recent-crossmatch-table */
    #recent-crossmatch-table th,
    #recent-crossmatch-table td {
        text-align: center;
        vertical-align: middle; /* optional: center vertically */
    }

    #recent-crossmatch-table th {
      font-size: 14px;
    }

    .crossmatch-job-status-panel {
      display: none;
      border-left: 4px solid #17a2b8;
    }

    .crossmatch-job-status-panel.is-visible {
      display: block;
    }

    .crossmatch-job-status-panel.is-completed {
      border-left-color: #28a745;
    }

    .crossmatch-job-status-panel.is-failed {
      border-left-color: #dc3545;
    }

    .crossmatch-job-status-panel .progress {
      height: 0.65rem;
      border-radius: 999px;
      overflow: hidden;
    }
  </style>
</head>
<body>
<div class="wrapper">

  <!-- Preloader -->

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">

    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Beneficiary Crossmatching</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../../home">Home</a></li>
              <li class="breadcrumb-item active">Crossmatching</li>
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
              <div class="card-header d-flex align-items-center">
                <h4 class="m-0 flex-grow-1">Beneficiary Crossmatching</h4>
              </div>
              <!-- /.card-header -->
              <div class="card-body">
                <form id="uploadForm" action="upload_handler.php" method="post" enctype="multipart/form-data" data-no-loader="true">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                  <div class="row g-3">

                    <div class="col-md-4">
                      <label class="form-label">Mode</label><br>
                      <select name="mode" id="mode" class="form-control" style="height:37px;" required>
                        <option value="db_vs_file">KODUS DB vs File</option>
                        <option value="file_vs_file">File vs File</option>
                      </select>
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">Threshold (%)</label><br>
                      <input type="number" name="threshold" class="form-control" min="50" max="100" step="1" value="85" required>
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">Birthdate rule</label><br>
                      <select name="birthdate_rule" class="form-control">
                        <option value="soft">Soft (fuzzy)</option>
                        <option value="strict">Strict (exact)</option>
                      </select>
                    </div>

                    <div class="col-12"><hr></div>

                    <div class="col-md-6">
                      <label class="form-label">Upload File A (.xlsx or .csv) — required</label>
                      <input type="file" name="file1" class="form-control" accept=".xlsx,.csv" required>
                      <div class="form-text" style="color:orange; font-style: italic;">Headers required: lastName, firstName, middleName, ext, birthDate, barangay, lgu, province</div>
                    </div>

                    <div class="col-md-6" id="file2wrap" style="display:none;">
                      <label class="form-label">Upload File B (.xlsx or .csv) — only for File vs File</label>
                      <input type="file" name="file2" class="form-control" accept=".xlsx,.csv">
                    </div><div style="height: 120px; display:block"></div>

                    <div class="col-12">
                      <button class="btn btn-primary" id="crossmatchStartButton">Upload & Start</button>
                      <a class="btn btn-link" href="template_file" download>Download Template</a>
                    </div>

                  </div>
                </form>
              </div>
              <!-- /.card-body -->
            </div>
        <!-- /.row -->
            <div class="card">
              <div class="card-header d-flex align-items-center">
                <h4 class="m-0 flex-grow-1">Crossmatching Template Generator</h4>
              </div>
              <div class="card-body">
                <div class="alert alert-info">
                  Upload one or more final validated MEB workbooks and convert them into crossmatching-ready files with the required beneficiary columns.
                </div>
                <form id="crossmatchTemplateGenerateForm" action="generate_template.php" method="post" enctype="multipart/form-data" data-no-loader="true">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                  <div class="form-group">
                    <label for="crossmatch-template-files">Final Validated MEB Workbooks</label>
                    <input type="file" name="template_files[]" class="form-control" accept=".xlsx,.xlsm" multiple required id="crossmatch-template-files">
                    <small class="form-text text-muted">Accepted files: <code>.xlsx</code> and <code>.xlsm</code>.</small>
                  </div>
                  <button type="submit" class="btn btn-success">
                    <i class="fas fa-file-excel mr-1"></i>
                    Generate Crossmatching Templates
                  </button>
                </form>
              </div>
            </div>

            <div class="card crossmatch-job-status-panel" id="crossmatchJobStatusPanel" aria-live="polite">
              <div class="card-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                  <div>
                    <h5 class="mb-1" id="crossmatchJobStatusTitle">Background Generation</h5>
                    <div class="text-muted" id="crossmatchJobStatusMessage">Checking latest job status...</div>
                  </div>
                  <span class="badge badge-info mt-2 mt-md-0" id="crossmatchJobStatusBadge">Queued</span>
                </div>
                <div class="mt-3">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span id="crossmatchJobStatusStep">Queued</span>
                    <strong id="crossmatchJobStatusProgress">0%</strong>
                  </div>
                  <div class="progress">
                    <div
                      class="progress-bar progress-bar-striped progress-bar-animated bg-info"
                      id="crossmatchJobStatusProgressBar"
                      role="progressbar"
                      style="width: 0%;"
                      aria-valuemin="0"
                      aria-valuemax="100"
                      aria-valuenow="0"
                    ></div>
                  </div>
                </div>
                <div class="mt-3" id="crossmatchJobStatusActions" hidden>
                  <a class="btn btn-sm btn-success" id="crossmatchJobViewResults" href="#" hidden>
                    <i class="fas fa-list mr-1"></i>
                    View Results
                  </a>
                  <button type="button" class="btn btn-sm btn-outline-secondary ml-1" id="crossmatchJobClearButton">
                    <i class="fas fa-eraser mr-1"></i>
                    Clear
                  </button>
                </div>
              </div>
            </div>

            <div class="card">
              <div class="card-header d-flex align-items-center">
                <h4 class="m-0 flex-grow-1">Recent Crossmatchings</h4>
              </div>
              <div class="card-body" id="recent-crossmatch-container" style="height: 46vh; overflow-y: auto;">
                <table class="table table-bordered table-striped" id="recent-crossmatch-table">
                  <thead>
                    <tr>
                      <th>Job</th>
                      <th>Mode</th>
                      <th>Rule</th>
                      <th>Threshold</th>
                      <th>Possible Matches</th>
                      <th>Created at</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <!-- Rows will be loaded via AJAX -->
                  </tbody>
                </table>
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
<script src="../plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../plugins/sweetalert2/sweetalert2.min.js"></script>
<!-- AdminLTE App -->
<script src="../dist/js/adminlte.min.js"></script>
<!-- DataTables CSS & JS -->
<link rel="stylesheet" href="../cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="../cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="../cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
document.getElementById('mode').addEventListener('change', function(){
  document.getElementById('file2wrap').style.display = this.value === 'file_vs_file' ? 'block' : 'none';
});

const crossmatchTemplateForm = document.getElementById('crossmatchTemplateGenerateForm');
if (crossmatchTemplateForm) {
  crossmatchTemplateForm.addEventListener('submit', function(event) {
    event.preventDefault();
    const button = crossmatchTemplateForm.querySelector('button[type="submit"]');
    if (button) {
      button.disabled = true;
    }

    Swal.fire({
      title: 'Generating Templates',
      html: `
        <div class="text-left">
          <p class="mb-2">Uploading workbooks and formatting crossmatching templates...</p>
          <div class="progress" style="height: .85rem; border-radius: 999px; overflow: hidden;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 72%;"></div>
          </div>
        </div>
      `,
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false
    });

    fetch(crossmatchTemplateForm.action, {
      method: 'POST',
      body: new FormData(crossmatchTemplateForm),
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      credentials: 'same-origin'
    })
      .then(response => response.json().then(payload => ({ ok: response.ok, payload })))
      .then(({ ok, payload }) => {
        if (!ok || !payload.success) {
          throw new Error(payload.message || 'Unable to generate templates.');
        }
        const links = (payload.outputs || []).map(output => (
          `<a class="btn btn-sm btn-outline-success m-1" href="${output.url}"><i class="fas fa-download mr-1"></i>${output.filename}</a>`
        )).join('');
        Swal.fire({
          icon: 'success',
          title: 'Templates Ready',
          html: `<p>${payload.message}</p><div>${links}</div>`
        });
      })
      .catch(error => {
        Swal.fire({ icon: 'error', title: 'Generation Failed', text: error.message || 'Unable to generate templates.' });
      })
      .finally(() => {
        if (button) {
          button.disabled = false;
        }
      });
  });
}
</script>
<script>
let recentCrossmatchTable;

function loadRecentCrossmatchings() {
    $.ajax({
        url: 'fetch_recent_crossmatch.php',
        method: 'GET',
        dataType: 'html',
        success: function(data) {
            if (!recentCrossmatchTable) {
                $('#recent-crossmatch-table tbody').html(data);
                recentCrossmatchTable = $('#recent-crossmatch-table').DataTable({
                    paging: true,
                    pageLength: 10,
                    lengthChange: false,
                    searching: true,
                    info: true,
                    autoWidth: false,
                    order: [[0, 'desc']]
                });
            } else {
                recentCrossmatchTable.clear().draw();
                $('#recent-crossmatch-table tbody').fadeOut(200, function() {
                    $(this).html(data).fadeIn(400, function() {
                        recentCrossmatchTable.rows.add($('#recent-crossmatch-table tbody tr')).draw(false);
                    });
                });
            }
        },
        error: function() {
            console.error('Failed to load recent crossmatchings.');
        }
    });
}

// Initial load
loadRecentCrossmatchings();

if (window.KODUSLiveRefresh) {
    window.KODUSLiveRefresh.watch({
        channels: ['crossmatch_recent_table'],
        allowPollingFallback: true,
        onChange: loadRecentCrossmatchings
    });
}

window.addEventListener('kodus:partial-refresh', function () {
    loadRecentCrossmatchings();
});

(function () {
    const form = document.getElementById('uploadForm');
    const submitButton = document.getElementById('crossmatchStartButton');

    if (!form) {
        return;
    }

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
        const progressBar = document.getElementById('crossmatchUploadProgressBar');
        const progressValueLabel = document.getElementById('crossmatchUploadProgressValue');
        const progressStatus = document.getElementById('crossmatchUploadProgressStatus');

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

        return Swal.fire({
            title: 'Starting Crossmatch',
            html: `
              <div class="text-left">
                <p class="mb-2">Please keep this tab open while we upload and validate your source files.</p>
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <strong id="crossmatchUploadProgressStatus">Preparing upload...</strong>
                  <span id="crossmatchUploadProgressValue">8%</span>
                </div>
                <div class="progress" style="height: 0.85rem; border-radius: 999px; overflow: hidden;">
                  <div id="crossmatchUploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 8%;" aria-valuemin="0" aria-valuemax="100" aria-valuenow="8"></div>
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

    function submitCrossmatchRequest(formData) {
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
                updateProgress(Math.max(progressValue, percent), 'Uploading source files...');
            });

            xhr.upload.addEventListener('load', function () {
                updateProgress(Math.max(progressValue, 72), 'Upload complete. Creating crossmatch job...');
                startProgressRamp(94, 'Preparing matching engine...');
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
                        const responseText = (xhr.responseText || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                        return { success: false, message: responseText || 'The server returned an invalid response.' };
                    }
                })();

                if (xhr.status < 200 || xhr.status >= 300 || !payload.success) {
                    reject(new Error(payload.message || 'Unable to start crossmatching.'));
                    return;
                }

                updateProgress(100, 'Crossmatch job started.');
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
                reject(new Error('Unable to start crossmatching.'));
            });

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
            openProgressModal();
            const payload = await submitCrossmatchRequest(formData);
            Swal.close();
            form.reset();
            document.getElementById('file2wrap').style.display = 'none';
            window.dispatchEvent(new CustomEvent('crossmatch-job-queued', {
                detail: {
                    jobId: payload.job_id || ''
                }
            }));
            await Swal.fire({
                icon: 'success',
                title: 'Crossmatch Started',
                text: payload.message || 'Crossmatching job started in the background.'
            });
        } catch (error) {
            Swal.close();
            await Swal.fire({
                icon: 'error',
                title: 'Crossmatch failed',
                text: error && error.message ? error.message : 'Unable to start crossmatching.'
            });
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    });
}());

(function () {
    const panel = document.getElementById('crossmatchJobStatusPanel');
    const message = document.getElementById('crossmatchJobStatusMessage');
    const badge = document.getElementById('crossmatchJobStatusBadge');
    const step = document.getElementById('crossmatchJobStatusStep');
    const progressLabel = document.getElementById('crossmatchJobStatusProgress');
    const progressBar = document.getElementById('crossmatchJobStatusProgressBar');
    const actions = document.getElementById('crossmatchJobStatusActions');
    const viewResults = document.getElementById('crossmatchJobViewResults');
    const clearButton = document.getElementById('crossmatchJobClearButton');
    let activeJobId = '';
    let pollTimer = null;

    if (!panel || !message || !badge || !step || !progressLabel || !progressBar || !actions || !viewResults || !clearButton) {
        return;
    }

    function badgeClass(status) {
        if (status === 'completed') {
            return 'badge badge-success';
        }
        if (status === 'failed') {
            return 'badge badge-danger';
        }
        if (status === 'processing') {
            return 'badge badge-primary';
        }
        return 'badge badge-info';
    }

    function progressClass(status) {
        if (status === 'completed') {
            return 'progress-bar bg-success';
        }
        if (status === 'failed') {
            return 'progress-bar bg-danger';
        }
        return 'progress-bar progress-bar-striped progress-bar-animated bg-info';
    }

    function renderStatus(job) {
        if (!job) {
            return;
        }

        const done = Boolean(job.done);
        const percent = Math.max(0, Math.min(100, Number(job.percent || 0)));
        const rawStatus = String(job.status || '');
        const status = done ? 'completed' : (rawStatus.toLowerCase().includes('fail') ? 'failed' : 'processing');

        panel.classList.add('is-visible');
        panel.classList.toggle('is-completed', status === 'completed');
        panel.classList.toggle('is-failed', status === 'failed');
        badge.className = badgeClass(status);
        badge.textContent = status === 'completed' ? 'Completed' : (status === 'failed' ? 'Failed' : 'Processing');
        message.textContent = rawStatus || (done ? 'Crossmatching complete.' : 'Crossmatching is running in the background.');
        step.textContent = done ? 'Completed' : (rawStatus || 'Processing');
        progressLabel.textContent = `${Math.round(percent)}%`;
        progressBar.className = progressClass(status);
        progressBar.style.width = `${percent}%`;
        progressBar.setAttribute('aria-valuenow', String(Math.round(percent)));
        actions.hidden = false;
        viewResults.hidden = !done;
        viewResults.href = activeJobId ? `results.php?job=${encodeURIComponent(activeJobId)}` : '#';

        if (window.refreshAppNotifications && done) {
            window.refreshAppNotifications();
        }
    }

    function refreshStatus(jobId) {
        if (!jobId) {
            return Promise.resolve(null);
        }

        return fetch(`progress_status.php?job=${encodeURIComponent(jobId)}`, {
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
                if (job && job.done) {
                    window.clearInterval(pollTimer);
                    pollTimer = null;
                    loadRecentCrossmatchings();
                }
            });
        }, 1800);
    }

    window.addEventListener('crossmatch-job-queued', function (event) {
        const jobId = event.detail && event.detail.jobId ? String(event.detail.jobId) : '';
        if (!jobId) {
            return;
        }

        renderStatus({
            percent: 0,
            done: false,
            status: 'Waiting for the background generator to start.'
        });
        schedulePolling(jobId);
    });

    clearButton.addEventListener('click', function () {
        if (pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }
        activeJobId = '';
        panel.classList.remove('is-visible', 'is-completed', 'is-failed');
    });
}());
</script>
</body>
</html>
