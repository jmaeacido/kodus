<?php
session_start();
include('../header.php');
include('../sidenav.php');
require_once __DIR__ . '/../config.php';

$jobId = intval($_GET['job'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM deduplication_jobs WHERE id=?");
$stmt->bind_param("i", $jobId);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Running Deduplication</title>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    #swal-progress {
      width: 100%; background: #e0e0e0;
      border-radius: 8px; overflow: hidden;
      margin-top: 15px; height: 25px;
      box-shadow: inset 0 1px 3px rgba(0,0,0,.2);
    }
    #swal-progress-bar {
      width: 0%; height: 100%; text-align: center;
      color: #fff; line-height: 25px; font-weight: bold;
      border-radius: 8px 0 0 8px;
      transition: width 0.8s ease, background-color 0.5s ease;
      background-color: #3085d6;
    }
  </style>
</head>
<body>
<script>
Swal.fire({
  title: 'Processing Deduplication...',
  html: `<div id="swal-progress"><div id="swal-progress-bar">0%</div></div>`,
  allowOutsideClick: false,
  showCancelButton: true,
  cancelButtonText: 'Cancel',
  showConfirmButton: false,
  didOpen: () => {
    const progressBar = Swal.getHtmlContainer().querySelector('#swal-progress-bar');
    let lastProgress = 0;

    const interval = setInterval(() => {
      fetch('status_api.php?job=<?= $jobId ?>')
        .then(res => res.json())
        .then(data => {
          let progress = data.progress ?? 0;

          if (progress > lastProgress) {
            progressBar.style.width = progress + '%';
            progressBar.textContent = progress + '%';
            lastProgress = progress;
            if (progress < 50) progressBar.style.backgroundColor = '#3085d6';
            else if (progress < 90) progressBar.style.backgroundColor = '#ff9800';
            else progressBar.style.backgroundColor = '#28a745';
          }

          if (data.status === 'done') {
            clearInterval(interval);
            progressBar.style.width = '100%';
            progressBar.textContent = '100%';
            progressBar.style.backgroundColor = '#28a745';
            setTimeout(() => {
              Swal.fire({
                icon: 'success',
                title: 'Deduplication Complete',
                html: `Processed successfully.<br><a href="results.php?job=<?= $jobId ?>" class="btn btn-success mt-2">View Results</a>`,
                allowOutsideClick: false
              });
            }, 500);
          }

          if (data.status === 'failed') {
            clearInterval(interval);
            progressBar.style.backgroundColor = '#dc3545';
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
      fetch('cancel_job.php?job=<?= $jobId ?>')
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