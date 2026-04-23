<?php
include('../header.php');
include('../sidenav.php');

$summaryClassification = strtoupper((string) ($summaryClassification ?? ''));
$summaryLabel = $summaryClassification === 'BINHI' ? 'BINHI' : 'LAWA';
$pageTitle = $summaryLabel . ' Details';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | <?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <style>
    .summary-card .small-box {
      margin-bottom: 0;
    }
    .table-container {
      overflow-x: auto;
      overflow-y: visible;
      -webkit-overflow-scrolling: touch;
      width: 100%;
      max-width: 100%;
    }
    .table-container .dataTables_wrapper {
      min-width: 0;
      width: 100%;
      max-width: 100%;
    }
    #program-summary-table {
      min-width: 2000px;
    }
    #program-summary-table td {
      vertical-align: middle;
    }
    .summary-context {
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 16px;
      padding: 1rem 1.1rem;
      margin-bottom: 1rem;
      background:
        radial-gradient(circle at top right, rgba(255, 193, 7, 0.12), transparent 30%),
        linear-gradient(135deg, rgba(13, 110, 253, 0.14), rgba(32, 201, 151, 0.08));
      box-shadow: 0 14px 28px rgba(0, 0, 0, 0.16);
    }
    .summary-context p:last-child {
      margin-bottom: 0;
    }
    .summary-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.45rem 0.8rem;
      border-radius: 999px;
      font-weight: 700;
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.12);
      margin-bottom: 0.75rem;
    }
    #program-summary-table th,
    #program-summary-table td {
      white-space: normal;
      min-width: 140px;
      word-break: break-word;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../home">Home</a></li>
              <li class="breadcrumb-item active"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
          </div>
          <div class="card-body">
            <div class="summary-context">
              <span class="summary-pill"><i class="fas fa-layer-group"></i> Implementation Status Details</span>
              <p class="mb-2">This page shows the <strong><?php echo htmlspecialchars($summaryLabel, ENT_QUOTES, 'UTF-8'); ?></strong> details in a barangay-level matrix based on Baseline Targets and Program Activities records for the selected fiscal year.</p>
              <p class="mb-0 text-muted">
                <?php if ($summaryLabel === 'BINHI') { ?>
                  For BINHI, produce, individuals fed, and families fed are computed from the actual planted/introduced quantities using the fiscal year's Project Variables coefficients.
                <?php } else { ?>
                  For LAWA, Total Water Capacity and Potential Area Covered are computed from the Area of Land Utilized using the fiscal year's Project Variables coefficients.
                <?php } ?>
              </p>
            </div>

            <div class="row mb-3 summary-card">
              <div class="col-md-3 col-6">
                <div class="small-box bg-info">
                  <div class="inner">
                    <h3 id="summary-rows">0</h3>
                    <p>Barangays</p>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="small-box bg-success">
                  <div class="inner">
                    <h3 id="summary-provinces">0</h3>
                    <p>Provinces</p>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="small-box bg-warning">
                  <div class="inner">
                    <h3 id="summary-municipalities">0</h3>
                    <p>Municipalities</p>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="small-box bg-secondary">
                  <div class="inner">
                    <h3 id="summary-beneficiaries">0</h3>
                    <p>Partner-beneficiaries</p>
                  </div>
                </div>
              </div>
            </div>

            <div class="table-container kodus-table-scroll" style="--kodus-table-min-width: 2000px;">
              <table id="program-summary-table" class="table table-bordered table-striped" style="text-align: center; width: 100%; table-layout: auto;">
                <thead style="font-size: 10px;"></thead>
                <tbody style="font-size: 10px;"></tbody>
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
    const summaryClassification = <?= json_encode($summaryLabel) ?>;
    const summaryConfig = summaryClassification === 'BINHI'
        ? {
            headerHtml: `
                <tr>
                    <th rowspan="3">Province</th>
                    <th rowspan="3">Municipality</th>
                    <th rowspan="3">Barangay</th>
                    <th colspan="2">No. of Partner-beneficiaries</th>
                    <th colspan="11">TYPE OF BINHI PLANTED/INTRODUCED</th>
                    <th colspan="7">PRODUCTS/PRODUCE OF BINHI (Potential Volume/Yield of produced products of introduced/planted BINHI based on Actual Number of Planted/Introduced BINHI (kg.))</th>
                    <th colspan="6">No. of Individuals to be Feed</th>
                    <th colspan="6">No. of Families to be Feed (estimated at 5 member per family)</th>
                    <th colspan="6">TYPE OF ORGANIC FERTILIZER PRODUCED</th>
                    <th colspan="2">No. of BINHI Sites Established</th>
                    <th colspan="2">No. of BINHI Facilities Added</th>
                    <th colspan="2">Area of Land Utilize (sqm)</th>
                </tr>
                <tr>
                    <th rowspan="2">Target</th>
                    <th rowspan="2">Actual</th>
                    <th colspan="2">Vegetable</th>
                    <th colspan="2">Crops (Banana, Corn, Rice)</th>
                    <th colspan="2">Disaster Resilient Crops (Taro, Sweet Potato)</th>
                    <th colspan="2">Fruit-Bearing Trees</th>
                    <th rowspan="2">TOTAL PLANTED</th>
                    <th colspan="2">Tilapia</th>
                    <th rowspan="2">Vegetable</th>
                    <th rowspan="2">Crops (Banana, Corn, Rice)</th>
                    <th rowspan="2">Disaster Resilient Crops (Taro, Sweet Potato)</th>
                    <th rowspan="2">Fruit-Bearing Trees</th>
                    <th rowspan="2">Tilapia</th>
                    <th rowspan="2">Total for Greens</th>
                    <th rowspan="2">Total</th>
                    <th rowspan="2">Vegetable</th>
                    <th rowspan="2">Crops (Banana, Corn, Rice)</th>
                    <th rowspan="2">Disaster Resilient Crops (Taro, Sweet Potato)</th>
                    <th rowspan="2">Fruit-Bearing Trees</th>
                    <th rowspan="2">Tilapia</th>
                    <th rowspan="2">Total</th>
                    <th rowspan="2">Vegetable</th>
                    <th rowspan="2">Crops (Banana, Corn, Rice)</th>
                    <th rowspan="2">Disaster Resilient Crops (Taro, Sweet Potato)</th>
                    <th rowspan="2">Fruit-Bearing Trees</th>
                    <th rowspan="2">Tilapia</th>
                    <th rowspan="2">Total</th>
                    <th colspan="2">Oriental Herbal Nutrients (liters)</th>
                    <th colspan="2">Concoction/Vermitea (liters)</th>
                    <th colspan="2">Vermicompost/Vermicast (kg)</th>
                    <th rowspan="2">Target</th>
                    <th rowspan="2">Actual</th>
                    <th rowspan="2">Target</th>
                    <th rowspan="2">Actual</th>
                    <th rowspan="2">Target</th>
                    <th rowspan="2">Actual</th>
                </tr>
                <tr>
                    <th>Target</th>
                    <th>Actual</th>
                    <th>Target</th>
                    <th>Actual</th>
                    <th>Target</th>
                    <th>Actual</th>
                    <th>Target</th>
                    <th>Actual</th>
                    <th>Target</th>
                    <th>Actual</th>
                    <th>Target</th>
                    <th>Actual</th>
                    <th>Target</th>
                    <th>Actual</th>
                    <th>Target</th>
                    <th>Actual</th>
                </tr>
            `,
            columns: [
                { data: 'province' },
                { data: 'municipality' },
                { data: 'barangay' },
                { data: 'partner_beneficiaries_target' },
                { data: 'partner_beneficiaries_actual' },
                { data: 'binhi_vegetable_target' },
                { data: 'binhi_vegetable_actual' },
                { data: 'binhi_crops_target' },
                { data: 'binhi_crops_actual' },
                { data: 'binhi_disaster_resilient_crops_target' },
                { data: 'binhi_disaster_resilient_crops_actual' },
                { data: 'binhi_fruit_bearing_trees_target' },
                { data: 'binhi_fruit_bearing_trees_actual' },
                { data: 'binhi_total_planted' },
                { data: 'binhi_tilapia_target' },
                { data: 'binhi_tilapia_actual' },
                { data: 'produce_vegetable' },
                { data: 'produce_crops' },
                { data: 'produce_disaster_resilient_crops' },
                { data: 'produce_fruit_bearing_trees' },
                { data: 'produce_tilapia' },
                { data: 'produce_total_greens' },
                { data: 'produce_total' },
                { data: 'individuals_vegetable' },
                { data: 'individuals_crops' },
                { data: 'individuals_disaster_resilient_crops' },
                { data: 'individuals_fruit_bearing_trees' },
                { data: 'individuals_tilapia' },
                { data: 'individuals_total' },
                { data: 'families_vegetable' },
                { data: 'families_crops' },
                { data: 'families_disaster_resilient_crops' },
                { data: 'families_fruit_bearing_trees' },
                { data: 'families_tilapia' },
                { data: 'families_total' },
                { data: 'fertilizer_ohn_target' },
                { data: 'fertilizer_ohn_actual' },
                { data: 'fertilizer_concoction_target' },
                { data: 'fertilizer_concoction_actual' },
                { data: 'fertilizer_vermicompost_target' },
                { data: 'fertilizer_vermicompost_actual' },
                { data: 'binhi_sites_established_target' },
                { data: 'binhi_sites_established_actual' },
                { data: 'binhi_facilities_added_target' },
                { data: 'binhi_facilities_added_actual' },
                { data: 'area_land_utilized_target' },
                { data: 'area_land_utilized_actual' }
            ]
        }
        : {
            headerHtml: `
                <tr>
                    <th rowspan="2">Province</th>
                    <th rowspan="2">Municipality</th>
                    <th rowspan="2">Barangay</th>
                    <th colspan="2">No. of Partner-beneficiaries</th>
                    <th rowspan="2">Type of LAWA (Name of Sub-Project/Activity)</th>
                    <th colspan="2">No. of LAWA</th>
                    <th colspan="2">No. of aquatic resources (fish, crabs, etc)</th>
                    <th colspan="2">No. of Facilities Established</th>
                    <th colspan="2">No. of Facilities Repaired</th>
                    <th colspan="2">Area of Land Utilized (sqm)</th>
                    <th rowspan="2">Total Water Capacity (cum)</th>
                    <th rowspan="2">Potential Area of Agricultural Land to be Catered/ Covered (sqm)</th>
                </tr>
                <tr>
                    <th>Target</th>
                    <th>Actual</th>
                    <th>Target</th>
                    <th>Actual</th>
                    <th>Target</th>
                    <th>Actual</th>
                    <th>Target</th>
                    <th>Actual</th>
                    <th>Target</th>
                    <th>Actual</th>
                    <th>Target</th>
                    <th>Actual</th>
                </tr>
            `,
            columns: [
                { data: 'province' },
                { data: 'municipality' },
                { data: 'barangay' },
                { data: 'partner_beneficiaries_target' },
                { data: 'partner_beneficiaries_actual' },
                { data: 'type_of_lawa' },
                { data: 'no_of_lawa_target' },
                { data: 'no_of_lawa_actual' },
                { data: 'aquatic_resources_target' },
                { data: 'aquatic_resources_actual' },
                { data: 'facilities_established_target' },
                { data: 'facilities_established_actual' },
                { data: 'facilities_repaired_target' },
                { data: 'facilities_repaired_actual' },
                { data: 'area_land_utilized_target' },
                { data: 'area_land_utilized_actual' },
                { data: 'total_water_capacity' },
                { data: 'potential_area_agri_land' }
            ]
        };

    function formatNumber(value) {
        return new Intl.NumberFormat('en-US').format(Number(value || 0));
    }

    function updateSummary(rows) {
        const provinceCount = new Set(rows.map((row) => String(row.province || '').trim()).filter(Boolean)).size;
        const municipalityCount = new Set(rows.map((row) => `${String(row.province || '').trim()}||${String(row.municipality || '').trim()}`).filter(Boolean)).size;
        const beneficiaryCount = rows.reduce((sum, row) => {
            const parsed = Number(row.partner_beneficiaries_actual || 0);
            return sum + (Number.isFinite(parsed) ? parsed : 0);
        }, 0);

        $('#summary-rows').text(formatNumber(rows.length));
        $('#summary-provinces').text(formatNumber(provinceCount));
        $('#summary-municipalities').text(formatNumber(municipalityCount));
        $('#summary-beneficiaries').text(formatNumber(beneficiaryCount));
    }

    $('#program-summary-table thead').html(summaryConfig.headerHtml);

    const table = $('#program-summary-table').DataTable({
        ajax: {
            url: 'fetch-program-summary.php',
            data: {
                classification: summaryClassification
            },
            dataSrc: function(json) {
                const rows = json.data || [];
                updateSummary(rows);
                return rows;
            }
        },
        columns: summaryConfig.columns,
        responsive: false,
        scrollX: true,
        lengthChange: true,
        autoWidth: false,
        orderCellsTop: true,
        order: [[0, 'asc'], [1, 'asc'], [2, 'asc']]
    });
});
</script>
</body>
</html>
