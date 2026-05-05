<?php
// crossmatch/start.php
include ('../header.php');
require_once __DIR__ . '/helpers/jobs.php';

// if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    // header("HTTP/1.1 403 Forbidden");
    // echo "Access denied. Admins only.";
    // exit;
// }

include ('../sidenav.php');

$jobId = $_GET['job'] ?? ($_SESSION['kds_cfg']['job_id'] ?? null);
if (!$jobId) {
    header('Location: ./');
    exit;
}

$job = crossmatch_fetch_accessible_job(
    $conn,
    (int) $jobId,
    (int) ($_SESSION['user_id'] ?? 0),
    (string) ($_SESSION['user_type'] ?? ''),
    'id, user_id'
);
if (!$job) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Running Crossmatch</title>
  <link href="../plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <script src="../plugins/sweetalert2/sweetalert2.min.js"></script>
</head>
<body class="p-4" style="background-color: #454d55;">

<script>
(function(){
  const jobId = <?= json_encode($jobId) ?>;

  Swal.fire({
    title: 'Running Crossmatch',
    html: `
      <div class="text-left">
        <p class="mb-2">Please keep this tab open while we compare beneficiary records.</p>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <strong id="lbl">Starting...</strong>
          <span id="pbl">0%</span>
        </div>
        <div class="progress" style="height: 0.85rem; border-radius: 999px; overflow: hidden;">
          <div id="pb" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width:0%;" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
        </div>
      </div>
    `,
    allowOutsideClick: false,
    showConfirmButton: false,
    didOpen: () => {
      const pb  = document.getElementById('pb');
      const pbl = document.getElementById('pbl');
      const lbl = document.getElementById('lbl');

      let interval = 800;     // start fast
      let timerId;

      const poll = () => {
        fetch('progress_status.php?job=' + encodeURIComponent(jobId))
          .then(r => r.json())
          .then(j => {
            pb.style.width = j.percent + '%';
            pb.setAttribute('aria-valuenow', String(j.percent));
            pbl.textContent = j.percent + '%';
            lbl.textContent = j.status;

            pb.classList.remove('bg-primary', 'bg-warning', 'bg-success');
            if (j.percent < 50) {
              pb.classList.add('bg-primary');
            } else if (j.percent < 90) {
              pb.classList.add('bg-warning');
            } else {
              pb.classList.add('bg-success');
            }

            if (j.done) {
              clearTimeout(timerId);
              setTimeout(() => {
                Swal.close();
                window.location.href = "results.php?job=" + jobId;
              }, 400);
              return;
            }

            // 📈 Adaptive interval logic
            if (j.percent < 10)       interval = 800;   // startup: fast updates
            else if (j.percent < 80)  interval = 2000;  // middle: relax to 2s
            else                      interval = 1000;  // finish: tighten to 1s

            timerId = setTimeout(poll, interval);
          })
          .catch(() => {
            // On error, retry in 2s
            timerId = setTimeout(poll, 2000);
          });
      };

      poll(); // kick off polling
    }
  });
})();
</script>
</body>
</html>
