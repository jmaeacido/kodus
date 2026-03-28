<?php
// crossmatch/results.php
include ('../header.php');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("HTTP/1.1 403 Forbidden");
    echo "Access denied. Admins only.";
    exit;
}

include ('../sidenav.php');

$jobId = $_GET['job'] ?? ($_SESSION['kds_cfg']['job_id'] ?? null);
if (!$jobId) {
    header('Location: ./');
    exit;
}

// Pagination setup
$perPage = 20; // how many results per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM crossmatch_results WHERE job_id=?");
$countStmt->bind_param("s", $jobId);
$countStmt->execute();
$countRes = $countStmt->get_result();
$totalRows = $countRes->fetch_assoc()['total'] ?? 0;
$countStmt->close();

$totalPages = ceil($totalRows / $perPage);

// fetch results from DB
$stmt = $conn->prepare("SELECT record_json, candidates_json 
                        FROM crossmatch_results 
                        WHERE job_id=? 
                        LIMIT ? OFFSET ?");
$stmt->bind_param("sii", $jobId, $perPage, $offset);
$stmt->execute();
$res = $stmt->get_result();

$results = [];
while ($row = $res->fetch_assoc()) {
    $results[] = [
        'record'     => json_decode($row['record_json'], true),
        'candidates' => json_decode($row['candidates_json'], true)
    ];
}
$stmt->close();

if (empty($results)) {
    echo "<div class='alert alert-warning'>No results found for job $jobId</div>";
    exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>KODUS | Crossmatch — Review</title>
  <style>
    .cand{border-left:3px solid #f1f1f1;padding-left:.6rem;margin-bottom:.5rem}.score{font-weight:700}
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
        <div class="card card-primary card-outline" style="padding: 20px;">
          <div class="card-header d-flex align-items-center">
            <h4 class="m-0 flex-grow-1">Review Matches (<?= count($results) ?>)</h4>
            <div>
              <button type="button" id="exportXlsx" class="btn btn-success me-2" onclick="submitExport('xlsx')" disabled>Export XLSX</button>
              <button type="button" id="exportCsv" class="btn btn-outline-secondary" onclick="submitExport('csv')" disabled>Export CSV</button>
            </div>
          </div>
          <!-- /.card-header -->
          <div class="table-container">

            <form id="reviewForm" method="post" action="export">
              <input type="hidden" name="type" id="exportType" value="xlsx">
              <div class="table-responsive">
                <table class="table table-bordered table-striped">
                  <thead>
                    <tr>
                      <th style="width:45%; font-size: 20px;">Uploaded Record</th>
                      <th style="width:45%; font-size: 20px;">Top 3 Candidates</th>
                      <th style="width:10%; font-size: 20px;">
                        <input type="checkbox" id="selectAll"> Accept All?
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($results as $idx => $row): ?>
                    <tr>
                      <td>
                        <div><strong><?= htmlspecialchars($row['record']['lastName']) ?>, <?= htmlspecialchars($row['record']['firstName']) ?> <?= htmlspecialchars($row['record']['ext']) ?> <?= htmlspecialchars($row['record']['middleName']) ?></strong></div>
                        <div>DOB: <?= htmlspecialchars($row['record']['birthDate']) ?></div>
                        <div><?= htmlspecialchars($row['record']['barangay']) ?>, <?= htmlspecialchars($row['record']['lgu']) ?>, <?= htmlspecialchars($row['record']['province']) ?></div>
                      </td>
                      <td>
                        <?php if (!empty($row['candidates'])): ?>
                        <?php foreach ($row['candidates'] as $cIdx => $c): ?>
                        <div class="cand">
                          <div>
                            <input type="radio" name="choice[<?= $idx ?>]" value="<?= $cIdx ?>" <?= $cIdx === 0 ? 'checked' : '' ?>>
                            <span class="score"><?= htmlspecialchars((string)$c['score']) ?>%</span> — 
                            <strong><?= htmlspecialchars($c['candidate']['lastName']) ?>, <?= htmlspecialchars($c['candidate']['firstName']) ?> <?= htmlspecialchars($c['candidate']['middleName']) ?> <?= htmlspecialchars($c['candidate']['ext']) ?></strong>
                          </div>
                          <div>DOB: <?= htmlspecialchars($c['candidate']['birthDate']) ?></div>
                          <div><?= htmlspecialchars($c['candidate']['barangay']) ?>, <?= htmlspecialchars($c['candidate']['lgu']) ?>, <?= htmlspecialchars($c['candidate']['province']) ?></div>
                          <div><small>Name: <?= $c['nameScore'] ?> | Birth: <?= $c['birthScore'] ?> | Addr: <?= $c['addrScore'] ?></small></div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <em>No candidate &ge; threshold</em>
                        <?php endif; ?>
                      </td>
                      <td class="text-center">
                        <input type="checkbox" name="accept[]" value="<?= $idx ?>" <?= empty($row['candidates']) ? '' : 'checked' ?>>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation">
                  <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                      <a class="page-link" href="?job=<?= urlencode($jobId) ?>&page=<?= $page-1 ?>">Previous</a>
                    </li>

                    <?php for ($p=1; $p <= $totalPages; $p++): ?>
                      <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?job=<?= urlencode($jobId) ?>&page=<?= $p ?>"><?= $p ?></a>
                      </li>
                    <?php endfor; ?>

                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                      <a class="page-link" href="?job=<?= urlencode($jobId) ?>&page=<?= $page+1 ?>">Next</a>
                    </li>
                  </ul>
                </nav>
                <?php endif; ?>
              </div>
            </form>
          </div><!-- /.card-body -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div><!-- /.content -->
  </div><!-- /.content-wrapper -->

  <!-- Main Footer -->
  <footer class="main-footer">
    <!-- To the right -->
    <div class="float-right d-none d-sm-inline">
      Anything you want
    </div>
    <!-- Default to the left -->
    <strong>Copyright &copy; 2014-2021 <a href="https://adminlte.io">AdminLTE.io</a>.</strong> All rights reserved.
  </footer>
</div><!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->

<!-- jQuery -->
<script src="../plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- AdminLTE App -->
<script src="../dist/js/adminlte.min.js"></script>

<style>
/* Base style for all checkboxes */
input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
  appearance: none;
  -webkit-appearance: none;
  border: 2px solid #666;
  border-radius: 4px;
  position: relative;
  background-color: #fff;
}

/* Row checkboxes when checked */
input[type="checkbox"]:checked {
  background-color: #4caf50; /* green */
  border-color: #388e3c;
}
input[type="checkbox"]:checked::after {
  content: "✔";
  font-size: 14px;
  font-weight: bold;
  color: white;
  position: absolute;
  top: -3px;
  left: -3px;
}

/* Select All when indeterminate */
#selectAll.indeterminate {
  background-color: #ffc107; /* amber */
  border: 2px solid #ff9800;
}
#selectAll.indeterminate::after {
  content: "–"; /* dash sign */
  font-size: 16px;
  font-weight: bold;
  color: black;
  position: absolute;
  top: -6px;
  left: 3px;
}
</style>

<script>
function toggleExportButtons() {
  const checkboxes = document.querySelectorAll('input[name="accept[]"]');
  const checkedBoxes = document.querySelectorAll('input[name="accept[]"]:checked');
  const anyChecked = checkedBoxes.length > 0;

  document.getElementById('exportCsv').disabled = !anyChecked;
  document.getElementById('exportXlsx').disabled = !anyChecked;

  const selectAll = document.getElementById('selectAll');
  if (selectAll) {
    selectAll.classList.remove('indeterminate');

    if (checkedBoxes.length === checkboxes.length) {
      selectAll.checked = true;
    } else if (checkedBoxes.length === 0) {
      selectAll.checked = false;
    } else {
      selectAll.checked = false;
      selectAll.classList.add('indeterminate');
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  toggleExportButtons();

  const checkboxes = document.querySelectorAll('input[name="accept[]"]');
  const selectAll = document.getElementById('selectAll');

  checkboxes.forEach(cb => {
    cb.addEventListener('change', toggleExportButtons);
  });

  if (selectAll) {
    selectAll.addEventListener('change', (e) => {
      const checked = e.target.checked;
      checkboxes.forEach(cb => cb.checked = checked);
      toggleExportButtons();
    });
  }
});
</script>

<script>
function submitExport(type) {
  // ✅ Confirmation prompt before proceeding
  Swal.fire({
    title: 'Confirm Export',
    text: `Are you sure you want to export the selected records as ${type.toUpperCase()}?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, Export',
    cancelButtonText: 'Cancel',
    reverseButtons: true
  }).then((result) => {
    if (!result.isConfirmed) return; // user canceled

    const form = document.getElementById('reviewForm');
    const formData = new FormData(form);
    formData.set('type', type);

    fetch('export.php', {
      method: 'POST',
      body: formData
    })
    .then(async response => {
      if (!response.ok) {
        const msg = await response.text();
        Swal.fire({
          icon: 'warning',
          title: 'Export Failed',
          text: msg || 'Nothing selected.',
        });
        return;
      }

      // ✅ Successful export → trigger download with filename from PHP
      return response.blob().then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;

        // get filename from Content-Disposition header
        const disposition = response.headers.get('Content-Disposition');
        let filename = (type === 'csv' ? 'Crossmatch.csv' : 'Crossmatch.xlsx');
        if (disposition && disposition.includes('filename=')) {
          filename = disposition.split('filename=')[1].trim().replace(/["']/g, '');
        }

        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);

        Swal.fire({
          icon: 'success',
          title: 'Export Started',
          text: 'Download should begin shortly.',
          timer: 1800,
          showConfirmButton: false
        });
      });
    })
    .catch(err => {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'An unexpected error occurred: ' + err
      });
    });
  });
}
</script>
</body>
</html>
