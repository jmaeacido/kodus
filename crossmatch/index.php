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
            window.location.href = payload.redirect || `start.php?job=${encodeURIComponent(payload.job_id || '')}`;
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
</script>
</body>
</html>
