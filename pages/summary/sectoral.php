<?php
include('../../header.php');
include('../../sidenav.php');

session_start();

if (!isset($_SESSION['selected_year'])) {
    echo "<p style='color:red;'>Fiscal year not selected. Please go back and select a year.</p>";
    exit;
}

$year = (int) $_SESSION['selected_year'];
$checkMarkHex = 'E29C93';

$query = "
    SELECT
        province,
        lgu,
        SUM(CASE WHEN sex = 'MALE' THEN 1 ELSE 0 END) AS male_count,
        SUM(CASE WHEN sex = 'FEMALE' THEN 1 ELSE 0 END) AS female_count,
        SUM(CASE WHEN HEX(COALESCE(nhts1, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS nhts1_count,
        SUM(CASE WHEN HEX(COALESCE(nhts2, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS nhts2_count,
        SUM(CASE WHEN HEX(COALESCE(fourPs, '')) = '{$checkMarkHex}' OR COALESCE(fourPs, '') = 'M' THEN 1 ELSE 0 END) AS fourPs_member_count,
        SUM(CASE WHEN fourPs = 'G' THEN 1 ELSE 0 END) AS fourPs_graduated_count,
        SUM(CASE WHEN HEX(COALESCE(F, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS farmers_count,
        SUM(CASE WHEN HEX(COALESCE(FF, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS fisherfolks_count,
        SUM(CASE WHEN HEX(COALESCE(`IS`, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS is_count,
        SUM(CASE WHEN HEX(COALESCE(IP, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS ip_count,
        SUM(CASE WHEN HEX(COALESCE(SC, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS sc_count,
        SUM(CASE WHEN HEX(COALESCE(SP, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS sp_count,
        SUM(CASE WHEN HEX(COALESCE(LW, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS lw_count,
        SUM(CASE WHEN HEX(COALESCE(PW, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS pw_count,
        SUM(CASE WHEN PWD REGEXP '^[A-Z]$' THEN 1 ELSE 0 END) AS pwd_count,
        SUM(CASE WHEN HEX(COALESCE(OSY, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS osy_count,
        SUM(CASE WHEN HEX(COALESCE(FR, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS fr_count,
        SUM(CASE WHEN HEX(COALESCE(ybDs, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS ybDs_count,
        SUM(CASE WHEN HEX(COALESCE(lgbtqia, '')) = '{$checkMarkHex}' THEN 1 ELSE 0 END) AS lgbtqia_count,
        COUNT(*) AS beneficiary_count
    FROM meb
    WHERE YEAR(time_stamp) = {$year}
    GROUP BY province, lgu
    ORDER BY province, lgu
";

$queryResult = $conn->query($query);
if (!$queryResult) {
    throw new RuntimeException('Sectoral summary query failed: ' . $conn->error);
}

$result = [];
while ($row = $queryResult->fetch_assoc()) {
    $result[] = $row;
}

$rows = [];
$totals = [
    'beneficiary_count' => 0,
    'male_count' => 0,
    'female_count' => 0,
    'nhts1_count' => 0,
    'nhts2_count' => 0,
    'fourPs_member_count' => 0,
    'fourPs_graduated_count' => 0,
    'farmers_count' => 0,
    'fisherfolks_count' => 0,
    'is_count' => 0,
    'ip_count' => 0,
    'sc_count' => 0,
    'sp_count' => 0,
    'lw_count' => 0,
    'pw_count' => 0,
    'pwd_count' => 0,
    'osy_count' => 0,
    'fr_count' => 0,
    'ybDs_count' => 0,
    'lgbtqia_count' => 0,
];

foreach ($result as $row) {
    $rows[] = $row;
    foreach ($totals as $key => $value) {
        $totals[$key] += (int) ($row[$key] ?? 0);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Summary of Partner-Beneficiaries per Sector</title>
</head>
<body>
<div class="wrapper">
  <div class="content-wrapper">
    <div class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-lg-12">
            <br><br><br>
            <div class="card card-primary card-outline">
              <div class="card-header d-flex align-items-center">
                <h5 class="m-0 flex-grow-1">Summary of Partner-Beneficiaries per Sector</h5>
                <button id="exportBtn" class="btn btn-info btn-sm" style="font-size: 10px; width: auto;">Export to Excel</button>
              </div>
              <div class="card-body">
                <div class="table-container kodus-table-scroll" style="--kodus-table-min-width: 1800px;">
                  <table id="sectoralTable" class="table table-bordered table-striped" style="text-align: center; width: 100%; table-layout: auto;">
                    <thead>
                      <tr>
                        <th>Province</th>
                        <th>City or Municipality</th>
                        <th>No. of Partner-Beneficiaries</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Listahanan 3 (P)</th>
                        <th>LSWDO Assessment (NON)</th>
                        <th>4Ps Member</th>
                        <th>4Ps Graduated</th>
                        <th>Farmers (F)</th>
                        <th>Fisher-folks (FF)</th>
                        <th>Informal Sector (IS)</th>
                        <th>Indigenous People (IP)</th>
                        <th>Senior Citizen (SC)</th>
                        <th>Solo Parent (SP)</th>
                        <th>Lactating Women (LW)</th>
                        <th>Pregnant Women (PW)</th>
                        <th>Person with Disabilities (PWD)</th>
                        <th>Out of School Youth (OSY)</th>
                        <th>Former Rebel (FR)</th>
                        <th>YAKAP Bayan/ PWUD</th>
                        <th>LGBTQIA+</th>
                      </tr>
                      <tr style="font-weight: bold; font-size: 12px;">
                        <td colspan="2">Total</td>
                        <td><?php echo $totals['beneficiary_count']; ?></td>
                        <td><?php echo $totals['male_count']; ?></td>
                        <td><?php echo $totals['female_count']; ?></td>
                        <td><?php echo $totals['nhts1_count']; ?></td>
                        <td><?php echo $totals['nhts2_count']; ?></td>
                        <td><?php echo $totals['fourPs_member_count']; ?></td>
                        <td><?php echo $totals['fourPs_graduated_count']; ?></td>
                        <td><?php echo $totals['farmers_count']; ?></td>
                        <td><?php echo $totals['fisherfolks_count']; ?></td>
                        <td><?php echo $totals['is_count']; ?></td>
                        <td><?php echo $totals['ip_count']; ?></td>
                        <td><?php echo $totals['sc_count']; ?></td>
                        <td><?php echo $totals['sp_count']; ?></td>
                        <td><?php echo $totals['lw_count']; ?></td>
                        <td><?php echo $totals['pw_count']; ?></td>
                        <td><?php echo $totals['pwd_count']; ?></td>
                        <td><?php echo $totals['osy_count']; ?></td>
                        <td><?php echo $totals['fr_count']; ?></td>
                        <td><?php echo $totals['ybDs_count']; ?></td>
                        <td><?php echo $totals['lgbtqia_count']; ?></td>
                      </tr>
                    </thead>
                    <tbody style="font-size: 10px;">
                      <?php foreach ($rows as $row): ?>
                      <tr>
                        <td style="white-space: nowrap;"><?php echo $row['province']; ?></td>
                        <td><?php echo $row['lgu']; ?></td>
                        <td><?php echo $row['beneficiary_count']; ?></td>
                        <td><?php echo $row['male_count']; ?></td>
                        <td><?php echo $row['female_count']; ?></td>
                        <td><?php echo $row['nhts1_count']; ?></td>
                        <td><?php echo $row['nhts2_count']; ?></td>
                        <td><?php echo $row['fourPs_member_count']; ?></td>
                        <td><?php echo $row['fourPs_graduated_count']; ?></td>
                        <td><?php echo $row['farmers_count']; ?></td>
                        <td><?php echo $row['fisherfolks_count']; ?></td>
                        <td><?php echo $row['is_count']; ?></td>
                        <td><?php echo $row['ip_count']; ?></td>
                        <td><?php echo $row['sc_count']; ?></td>
                        <td><?php echo $row['sp_count']; ?></td>
                        <td><?php echo $row['lw_count']; ?></td>
                        <td><?php echo $row['pw_count']; ?></td>
                        <td><?php echo $row['pwd_count']; ?></td>
                        <td><?php echo $row['osy_count']; ?></td>
                        <td><?php echo $row['fr_count']; ?></td>
                        <td><?php echo $row['ybDs_count']; ?></td>
                        <td><?php echo $row['lgbtqia_count']; ?></td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
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
<script src="<?php echo $app_root; ?>plugins/jszip/jszip.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<script src="../../plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>
<script>
$(function () {
  $("#sectoralTable").DataTable({
    responsive: false,
    scrollX: true,
    autoWidth: false,
    lengthChange: true,
    pageLength: 25
  });
  document.getElementById('exportBtn').addEventListener('click', function () {
      window.location.href = 'export';
  });
});
</script>
</body>
</html>
