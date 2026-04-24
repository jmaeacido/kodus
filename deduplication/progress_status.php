<?php
include('../header.php');
include('../sidenav.php');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers/jobs.php';

$jobId = intval($_GET['job'] ?? 0);
$job = deduplication_fetch_accessible_job(
    $conn,
    $jobId,
    (int) ($_SESSION['user_id'] ?? 0),
    (string) ($_SESSION['user_type'] ?? '')
);

if ($jobId > 0 && !$job) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Running Deduplication</title>
  <script src="../plugins/sweetalert2/sweetalert2.min.js"></script>
</head>
<body>
<script>
const deduplicationCsrfToken = <?= json_encode(security_get_csrf_token()) ?>;
const deduplicationJobId = <?= json_encode((string) $jobId) ?>;

Swal.fire({
  title: 'Running Deduplication',
  html: `
    <div class="text-left">
      <p class="mb-2">Please keep this tab open while we compare beneficiary records.</p>
      <div class="d-flex justify-content-between align-items-center mb-2">
        <strong id="dedupJobProgressStatus">Preparing job...</strong>
        <span id="dedupJobProgressValue">0%</span>
      </div>
      <div class="progress" style="height: 0.85rem; border-radius: 999px; overflow: hidden;">
        <div
          id="dedupJobProgressBar"
          class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
          role="progressbar"
          style="width: 0%;"
          aria-valuemin="0"
          aria-valuemax="100"
          aria-valuenow="0"
        ></div>
      </div>
    </div>
  `,
  allowOutsideClick: false,
  showCancelButton: true,
  cancelButtonText: 'Cancel',
  showConfirmButton: false,
  didOpen: () => {
    const progressBar = Swal.getHtmlContainer().querySelector('#dedupJobProgressBar');
    const progressValue = Swal.getHtmlContainer().querySelector('#dedupJobProgressValue');
    const progressStatus = Swal.getHtmlContainer().querySelector('#dedupJobProgressStatus');
    let lastProgress = 0;

    function setProgress(value, statusText) {
      const progress = Math.max(0, Math.min(100, Number(value || 0)));
      progressBar.style.width = progress + '%';
      progressBar.setAttribute('aria-valuenow', String(Math.round(progress)));
      progressValue.textContent = Math.round(progress) + '%';
      if (statusText) {
        progressStatus.textContent = statusText;
      }

      progressBar.classList.remove('bg-primary', 'bg-warning', 'bg-success', 'bg-danger');
      if (progress < 50) {
        progressBar.classList.add('bg-primary');
      } else if (progress < 90) {
        progressBar.classList.add('bg-warning');
      } else {
        progressBar.classList.add('bg-success');
      }
    }

    const interval = setInterval(() => {
      fetch('status_api.php?job=' + encodeURIComponent(deduplicationJobId))
        .then(res => res.json())
        .then(data => {
          let progress = data.progress ?? 0;

          if (data.status === 'pending') {
            setProgress(progress, 'Waiting for the worker to start...');
          }

          if (data.status === 'processing' && progress <= lastProgress) {
            setProgress(progress, 'Comparing beneficiary records...');
          }

          if (progress > lastProgress) {
            setProgress(progress, progress < 100 ? 'Comparing beneficiary records...' : 'Finalizing results...');
            lastProgress = progress;
          }

          if (data.status === 'done') {
            clearInterval(interval);
            setProgress(100, 'Deduplication complete.');
            setTimeout(() => {
              Swal.fire({
                icon: 'success',
                title: 'Deduplication Complete',
                html: `Processed successfully.<br><a href="results.php?job=${encodeURIComponent(deduplicationJobId)}" class="btn btn-success mt-2">View Results</a>`,
                allowOutsideClick: false
              });
            }, 500);
          }

          if (data.status === 'failed') {
            clearInterval(interval);
            progressBar.classList.remove('bg-primary', 'bg-warning', 'bg-success');
            progressBar.classList.add('bg-danger');
            progressStatus.textContent = 'Deduplication failed.';
            Swal.fire({
              icon: 'error',
              title: 'Deduplication Failed',
              text: 'Please check logs.',
              allowOutsideClick: false
            });
          }
        });
    }, 1000);

    Swal.getCancelButton().addEventListener('click', () => {
      const cancelBody = new URLSearchParams({
        job: deduplicationJobId,
        csrf_token: deduplicationCsrfToken
      });

      fetch('cancel_job.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: cancelBody.toString()
      })
        .then(res => res.text())
        .then(msg => {
          clearInterval(interval);
          Swal.fire({
            icon: 'warning',
            title: 'Deduplication Cancelled',
            text: msg
          }).then(() => window.location.href = 'index.php');
        })
        .catch(() => {
          Swal.fire({
            icon: 'error',
            title: 'Error Cancelling',
            text: 'Could not cancel the job.'
          }).then(() => window.location.href = 'index.php');
        });
    });
  }
});
</script>
</body>
</html>
