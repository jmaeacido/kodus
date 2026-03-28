<?php
// crossmatch/start.php
include ('../header.php');

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
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Running Crossmatch</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="p-4" style="background-color: #454d55;">

<script>
(function(){
  // fire-and-forget run.php
  fetch('run_job.php?job=' + encodeURIComponent(<?= json_encode($jobId) ?>), { method: 'POST' }).catch(()=>{});

  const jobId = <?= json_encode($jobId) ?>;

  Swal.fire({
    title: 'Crossmatching…',
    html: `
      <div class="progress" style="height:24px;">
        <div id="pb" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%">0%</div>
      </div>
      <div id="lbl" class="mt-2">Starting…</div>
    `,
    icon: 'info',
    allowOutsideClick: false,
    showConfirmButton: false,
    didOpen: () => {
      const pb  = document.getElementById('pb');
      const lbl = document.getElementById('lbl');

      let interval = 800;     // start fast
      let timerId;

      const poll = () => {
        fetch('progress_status.php?job=' + encodeURIComponent(jobId))
          .then(r => r.json())
          .then(j => {
            pb.style.width = j.percent + '%';
            pb.textContent = j.percent + '%';
            lbl.textContent = j.status;

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
