<?php
session_start();
include('../header.php');
include('../sidenav.php');
require_once __DIR__ . '/../config.php';

$jobId = intval($_GET['job'] ?? 0);

// Fetch job details
$stmt = $conn->prepare("SELECT * FROM deduplication_jobs WHERE id=?");
$stmt->bind_param("i", $jobId);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    echo "<div class='alert alert-danger'>Job not found.</div>";
    exit;
}

// Fetch grouped results
$query = "
    SELECT group_id, row_data, similarity
    FROM deduplication_results
    WHERE job_id=?
    ORDER BY group_id ASC, similarity DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jobId);
$stmt->execute();
$result = $stmt->get_result();

// Organize results by group_id
$groups = [];
while ($row = $result->fetch_assoc()) {
    $groups[$row['group_id']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | Deduplication Results</title>
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <div class="content-wrapper">
  <!-- Preloader -->
    <section class="content" style="position: relative; top: 50px;">
      <div class="content-header">
        <div class="container-fluid">
          <h1 class="m-0">Deduplication Results</h1>
          <p><strong>Job:</strong> <?= htmlspecialchars($job['file_name']) ?> | 
             <strong>Status:</strong> <?= htmlspecialchars($job['status']) ?> | 
             <strong>Rule:</strong> <?= htmlspecialchars($job['rule']) ?> | 
             <strong>Threshold:</strong> <?= htmlspecialchars($job['threshold']) ?>%</p>
          <a href="export_results.php?job=<?= $jobId ?>" class="btn btn-success mb-3">
            <i class="fas fa-file-excel"></i> Export Full Results
          </a>
        </div>
      </div>
      <div class="container-fluid">
        <?php if (empty($groups)): ?>
            <div class="alert alert-info">No duplicates found.</div>
        <?php else: ?>
            <?php foreach ($groups as $groupId => $rows): ?>
                <div class="card card-outline card-danger mb-4">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">Duplicate Group #<?= $groupId ?></h3>
                    <a href="export_results.php?job=<?= $jobId ?>&group=<?= $groupId ?>" class="btn btn-sm btn-primary">
                      <i class="fas fa-file-export"></i> Export Group
                    </a>
                  </div>
                  <div class="card-body">
                    <table class="table table-bordered table-striped">
                      <thead>
                        <tr>
                          <th>Row Data</th>
                          <th>Similarity</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($rows as $r): 
                            $data = json_decode($r['row_data'], true);
                            if (is_array($data)) {
                                $display = implode(" | ", $data);
                            } else {
                                $display = htmlspecialchars($r['row_data']);
                            }
                        ?>
                        <tr>
                          <td><?= htmlspecialchars($display) ?></td>
                          <td><?= htmlspecialchars($r['similarity']) ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>

<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="../plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="../plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="../dist/js/adminlte.min.js"></script>
<script>
$(function () {
  $('table').DataTable({
    responsive: true,
    autoWidth: false,
    ordering: false
  });
});
</script>
</body>
</html>
