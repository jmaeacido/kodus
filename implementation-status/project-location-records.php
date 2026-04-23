<?php
include('../header.php');
include('../sidenav.php');

$selectedYear = (int) ($_SESSION['selected_year'] ?? date('Y'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | Project Location Records</title>
  <style>
    .project-location-records-page .content-wrapper {
      background:
        radial-gradient(circle at top left, rgba(13, 110, 253, 0.08), transparent 30%),
        radial-gradient(circle at bottom right, rgba(40, 167, 69, 0.07), transparent 28%);
    }
    .project-location-records-card {
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 16px 32px rgba(15, 23, 42, 0.08);
    }
    .project-location-records-pillbar {
      display: flex;
      gap: .75rem;
      flex-wrap: wrap;
      align-items: center;
    }
    .project-location-records-pill {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      padding: .45rem .8rem;
      border-radius: 999px;
      background: rgba(13, 110, 253, 0.12);
      font-weight: 600;
    }
    .project-location-records-table td,
    .project-location-records-table th {
      vertical-align: middle;
    }
    .project-location-records-table .project-id-cell {
      font-family: "Courier New", monospace;
      font-size: .84rem;
    }
    .project-location-records-table .coordinate-cell {
      white-space: nowrap;
      font-variant-numeric: tabular-nums;
    }
    .project-location-records-table .drive-link-anchor {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      font-weight: 600;
    }
    @media (max-width: 767.98px) {
      .project-location-records-pillbar {
        align-items: flex-start;
      }
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed project-location-records-page">
<div class="wrapper">
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">Project Location Records</h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../home">Home</a></li>
              <li class="breadcrumb-item active">Project Location Records</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="card project-location-records-card">
          <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:.75rem;">
              <div>
                <h3 class="card-title mb-0">Actual Project Accomplishment Locations</h3><br>
                <p class="text-muted small mb-0 mt-1">Program Activities only, sorted by province, municipality, barangay, and purok.</p>
              </div>
              <div class="project-location-records-pillbar">
                <span class="project-location-records-pill"><i class="fas fa-calendar-alt"></i> Fiscal Year <?php echo htmlspecialchars((string) $selectedYear, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="project-location-records-pill"><i class="fas fa-map-marker-alt"></i> Actual accomplishment records only</span>
              </div>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="projectLocationRecordsTable" class="table table-bordered table-striped table-hover project-location-records-table">
                <thead>
                  <tr>
                    <th>Project ID</th>
                    <th>Project Name</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Purok</th>
                    <th>Barangay</th>
                    <th>Municipality</th>
                    <th>Province</th>
                    <th>Drive Link</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?php echo $app_root; ?>plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>
<script>
$(function() {
    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    function renderCoordinate(value) {
        if (value === null || value === '' || Number.isNaN(Number(value))) {
            return '<span class="text-muted">Not set</span>';
        }

        return `<span class="coordinate-cell">${escapeHtml(Number(value).toFixed(6))}</span>`;
    }

    $('#projectLocationRecordsTable').DataTable({
        processing: true,
        ajax: {
            url: 'fetch-project-location-records.php',
            dataSrc: function(response) {
                if (!response || !response.success) {
                    return [];
                }
                return response.data || [];
            }
        },
        order: [[7, 'asc'], [6, 'asc'], [5, 'asc'], [4, 'asc'], [1, 'asc']],
        responsive: true,
        pageLength: 25,
        autoWidth: false,
        columns: [
            {
                data: 'project_code',
                render: function(data) {
                    return `<span class="project-id-cell">${escapeHtml(data || '')}</span>`;
                }
            },
            { data: 'project_name', defaultContent: '' },
            {
                data: 'latitude',
                render: function(data) {
                    return renderCoordinate(data);
                }
            },
            {
                data: 'longitude',
                render: function(data) {
                    return renderCoordinate(data);
                }
            },
            { data: 'purok', defaultContent: '' },
            { data: 'barangay', defaultContent: '' },
            { data: 'municipality', defaultContent: '' },
            { data: 'province', defaultContent: '' },
            {
                data: 'drive_link',
                orderable: false,
                searchable: true,
                render: function(data) {
                    const url = String(data || '').trim();
                    if (url === '') {
                        return '<span class="text-muted">Not set</span>';
                    }

                    return `<a class="drive-link-anchor" href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer"><i class="fas fa-external-link-alt"></i> Open Link</a>`;
                }
            }
        ]
    });
});
</script>
</body>
</html>
