<?php
session_start();
include('../header.php');
include('../sidenav.php');

// if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    // header("HTTP/1.1 403 Forbidden");
    // echo "Access denied. Admins only.";
    // exit;
// }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | Deduplication</title>
  <!-- DataTables CSS -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <style>
    /* Center all table headers and cells in #recent-dedupe-table */
    #recent-dedupe-table th,
    #recent-dedupe-table td {
        text-align: center;
        vertical-align: middle; /* optional: center vertically */
    }

    #recent-dedupe-table th {
      font-size: 14px;
    }
  </style>
</head>
<body>
<div class="content-wrapper">

  <!-- Preloader -->

  <section class="content-header">
    <h1>Deduplication Tool</h1>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h4 class="m-0 flex-grow-1">Beneficiary Deduplication</h4>
        </div>
        <div class="card-body">
          <form action="upload_handler.php" method="POST" enctype="multipart/form-data">
            <div class="row g-3">

              <div class="col-md-6">
                <label class="form-label" for="threshold">Threshold (%)</label>
                <input type="number" name="threshold" id="threshold" class="form-control" min="50" max="100" value="85" required>
              </div>

              <div class="col-md-6">
                <label for="rule">Matching Rule</label>
                <select name="rule" id="rule" class="form-control" required>
                  <option value="soft">Soft (fuzzy)</option>
                  <option value="strict">Strict</option>
                </select>
              </div>

              <div class="col-12"><hr></div>

              <div class="col-12">
                <label for="file">Upload Excel/CSV file</label>
                <input type="file" name="file" id="file" class="form-control" accept=".csv, .xlsx" required>
                <div class="form-text" style="color:orange; font-style: italic;">Headers required: rowNumber, lastName, firstName, middleName, ext, birthDate, barangay, lgu, province</div>
              </div> 

              <div class="col-12 mt-3">
                <button type="submit" class="btn btn-primary">Start Deduplication</button>
                <a class="btn btn-link" href="helpers/Deduplication_Template.xlsx" download>Download Template</a>
              </div>

            </div>
          </form>
        </div>
      </div>
      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h4 class="m-0 flex-grow-1">Recent Deduplications</h4>
        </div>
        <div class="card-body" id="recent-dedupe-container" style="height: 46vh; overflow-y: auto;">
          <table class="table table-bordered table-striped" id="recent-dedupe-table">
            <thead>
              <tr>
                <th>Job</th>
                <th>Rule</th>
                <th>Threshold</th>
                <th>Possible Duplicates</th>
                <th>Created at</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <!-- Rows will be loaded here via AJAX -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- REQUIRED SCRIPTS -->
<script src="../plugins/jquery/jquery.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../dist/js/adminlte.min.js"></script>
<script>
  let recentDedupeTable;

  function loadRecentDeduplications() {
      $.ajax({
          url: 'fetch_recent_deduplications.php',
          method: 'GET',
          dataType: 'html',
          success: function(data) {
              if (!recentDedupeTable) {
                  // First load: populate table and initialize DataTables
                  $('#recent-dedupe-table tbody').html(data);

                  recentDedupeTable = $('#recent-dedupe-table').DataTable({
                      paging: true,
                      pageLength: 10,
                      lengthChange: false,
                      searching: true,
                      info: true,
                      autoWidth: false,
                      order: [[0, 'desc']] // Sort by Job column descending
                  });
              } else {
                  // Subsequent loads: update table content with fade effect
                  recentDedupeTable.clear().draw();
                  $('#recent-dedupe-table tbody').fadeOut(200, function() {
                      $(this).html(data).fadeIn(400, function() {
                          recentDedupeTable.rows.add($('#recent-dedupe-table tbody tr')).draw(false);
                      });
                  });
              }
          },
          error: function() {
              console.error('Failed to load recent deduplications.');
          }
      });
  }

  // Load immediately on page load
  loadRecentDeduplications();

  // Refresh every 30 seconds
  setInterval(loadRecentDeduplications, 30000);
</script>

</body>
</html>
