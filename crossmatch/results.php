<?php
// crossmatch/results.php
include ('../header.php');
include ('../sidenav.php');

$jobId = $_GET['job'] ?? ($_SESSION['kds_cfg']['job_id'] ?? null);
if (!$jobId) {
    header('Location: ./');
    exit;
}

// Fetch results from DB
$stmt = $conn->prepare("SELECT record_json, candidates_json FROM crossmatch_results WHERE job_id=?");
$stmt->bind_param("i", $jobId);
$stmt->execute();
$res = $stmt->get_result();

$results = [];
$matchedCount = 0;
while ($row = $res->fetch_assoc()) {
    $candidates = json_decode($row['candidates_json'], true) ?: [];
    if (!empty($candidates)) {
        $matchedCount++;
    }
    $results[] = [
        'record'     => json_decode($row['record_json'], true),
        'candidates' => $candidates
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
  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
  <style>
    .cand{border-left:3px solid #f1f1f1;padding-left:.6rem;margin-bottom:.5rem}.score{font-weight:700}
  </style>
</head>
<body>
<div class="wrapper">

  <!-- Preloader -->

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0">Beneficiary Crossmatching</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="../../home">Home</a></li>
            <li class="breadcrumb-item active">Crossmatching</li>
          </ol>
        </div>
        </div>
      </div>
    </div>

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

          <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
              <div class="text-muted">
                Total records: <strong><?= count($results) ?></strong> | Possible matches: <strong><?= $matchedCount ?></strong>
              </div>
              <div class="form-inline">
                <label for="matchFilter" class="mr-2 mb-0">Show</label>
                <select id="matchFilter" class="form-control form-control-sm">
                  <option value="all">All records</option>
                  <option value="matched">Possible matches only</option>
                </select>
              </div>
            </div>
            <div class="table-container">
              <form id="reviewForm" method="post" action="export.php">
                <input type="hidden" name="type" id="exportType" value="xlsx">
                <input type="hidden" name="job_id" value="<?= htmlspecialchars($jobId) ?>">

                <div class="table-responsive">
                  <table id="crossmatchTable" class="table table-bordered table-striped">
                    <thead>
                      <tr>
                        <th style="width:45%">Uploaded Record</th>
                        <th style="width:45%">Top 3 Candidates</th>
                        <th style="width:10%" class="text-center">
                          <input type="checkbox" id="selectAll"> Accept All?
                        </th>
                      </tr>
                      <tr>
                        <th><input type="text" class="form-control form-control-sm column-filter" placeholder="Filter Uploaded Record"></th>
                        <th><input type="text" class="form-control form-control-sm column-filter" placeholder="Filter Candidates"></th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($results as $idx => $row): ?>
                      <tr data-has-matches="<?= empty($row['candidates']) ? '0' : '1' ?>">
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
                          <em>No candidate ≥ threshold</em>
                          <?php endif; ?>
                        </td>
                        <td class="text-center">
                          <input type="checkbox" name="accept[]" value="<?= $idx ?>" <?= empty($row['candidates']) ? '' : 'checked' ?>>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </form>
            </div>
          </div><!-- /.card-body -->
        </div>
      </div>
    </div>
  </div><!-- /.content-wrapper -->
</div><!-- ./wrapper -->

<!-- JS -->
<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../dist/js/adminlte.min.js"></script>

<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>

<style>
input[type="checkbox"] {
  width: 18px; height: 18px; cursor: pointer;
  appearance: none; border: 2px solid #666;
  border-radius: 4px; background-color: #fff; position: relative;
}
input[type="checkbox"]:checked {
  background-color: #4caf50; border-color: #388e3c;
}
input[type="checkbox"]:checked::after {
  content: "✔"; font-size: 14px; color: white;
  position: absolute; top: -3px; left: -3px;
}
#selectAll.indeterminate {
  background-color: #ffc107; border: 2px solid #ff9800;
}
#selectAll.indeterminate::after {
  content: "–"; font-size: 16px; color: black;
  position: absolute; top: -6px; left: 3px;
}
</style>

<script>
const matchFilterState = {
  value: 'all'
};
let crossmatchTable;

$.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
  if (settings.nTable.id !== 'crossmatchTable') {
    return true;
  }

  if (matchFilterState.value !== 'matched') {
    return true;
  }

  const rowNode = settings.aoData[dataIndex] && settings.aoData[dataIndex].nTr;
  return rowNode ? rowNode.getAttribute('data-has-matches') === '1' : true;
});

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

  crossmatchTable = $('#crossmatchTable').DataTable({
    pageLength: 10,
    lengthMenu: [
      [10, 25, 50, -1],
      [10, 25, 50, "Show All"]
    ],
    order: [],
    dom: '<"top"f>rt<"bottom"lp><"clear">',
    initComplete: function() {
      const api = this.api();
      api.columns().every(function() {
        const that = this;
        $('input', this.header()).on('keyup change clear', function() {
          if (that.search() !== this.value) {
            that.search(this.value).draw();
          }
        });
      });
    }
  });

  const matchFilter = document.getElementById('matchFilter');
  if (matchFilter) {
    matchFilter.addEventListener('change', function() {
      matchFilterState.value = this.value;
      crossmatchTable.draw();
    });
  }

  const checkboxes = document.querySelectorAll('input[name="accept[]"]');
  const selectAll = document.getElementById('selectAll');

  checkboxes.forEach(cb => cb.addEventListener('change', toggleExportButtons));
  if (selectAll) {
    selectAll.addEventListener('change', (e) => {
      const checked = e.target.checked;
      checkboxes.forEach(cb => cb.checked = checked);
      toggleExportButtons();
    });
  }
});

function submitExport(type) {
  Swal.fire({
    title: 'Confirm Export',
    text: `Are you sure you want to export the selected records as ${type.toUpperCase()}?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, Export',
    cancelButtonText: 'Cancel',
    reverseButtons: true
  }).then((result) => {
    if (!result.isConfirmed) return;

    const form = document.getElementById('reviewForm');
    const formData = new FormData();
    formData.set('type', type);

    form.querySelectorAll('input[type="hidden"]').forEach((input) => {
      formData.set(input.name, input.value);
    });

    if (crossmatchTable) {
      crossmatchTable.$('input[name="accept[]"]:checked').each(function() {
        formData.append(this.name, this.value);
      });

      crossmatchTable.$('input[type="radio"]:checked').each(function() {
        formData.append(this.name, this.value);
      });
    }

    fetch('export.php', {
      method: 'POST',
      body: formData
    })
    .then(async response => {
      if (!response.ok) {
        const msg = await response.text();
        Swal.fire({ icon: 'warning', title: 'Export Failed', text: msg || 'Nothing selected.' });
        return;
      }

      return response.blob().then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
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
    .catch(err => Swal.fire({ icon: 'error', title: 'Error', text: 'An unexpected error occurred: ' + err }));
  });
}
</script>
</body>
</html>
