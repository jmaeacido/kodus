<?php
include('../../header.php');
include('../../sidenav.php');

session_start();

// Ensure fiscal year is selected
if (!isset($_SESSION['selected_year'])) {
    echo "<p style='color:red;'>Fiscal year not selected. Please go back and select a year.</p>";
    exit;
}

$year = (int) $_SESSION['selected_year'];

// Use prepared statement (recommended)
$stmt = $conn->prepare("
    SELECT 
        province, 
        lgu, 
        SUM(CASE WHEN sex = 'MALE' THEN 1 ELSE 0 END) AS male_count,
        SUM(CASE WHEN sex = 'FEMALE' THEN 1 ELSE 0 END) AS female_count,
        SUM(CASE WHEN nhts1 = '✓' THEN 1 ELSE 0 END) AS nhts1_count,
        (SUM(CASE WHEN sex = 'MALE' THEN 1 ELSE 0 END) + SUM(CASE WHEN sex = 'FEMALE' THEN 1 ELSE 0 END)) AS beneficiary_count,
        SUM(CASE WHEN nhts2 = '✓' THEN 1 ELSE 0 END) AS nhts2_count,
        SUM(CASE WHEN F = '✓' THEN 1 ELSE 0 END) AS farmers_count,
        SUM(CASE WHEN FF = '✓' THEN 1 ELSE 0 END) AS fisherfolks_count,
        SUM(CASE WHEN IP = '✓' THEN 1 ELSE 0 END) AS ip_count,
        SUM(CASE WHEN SC = '✓' THEN 1 ELSE 0 END) AS sc_count,
        SUM(CASE WHEN SP = '✓' THEN 1 ELSE 0 END) AS sp_count,
        SUM(CASE WHEN OSY = '✓' THEN 1 ELSE 0 END) AS osy_count,
        SUM(CASE WHEN PWD REGEXP '^[A-Z]$' THEN 1 ELSE 0 END) AS pwd_count,
        SUM(CASE WHEN fourPs = '✓' THEN 1 ELSE 0 END) AS fourPs_count,
        SUM(CASE WHEN lgbtqia = '✓' THEN 1 ELSE 0 END) AS lgbtqia_count,
        (SUM(CASE WHEN FR = '✓' THEN 1 ELSE 0 END) + SUM(CASE WHEN ybDs = '✓' THEN 1 ELSE 0 END)) AS others_count
    FROM meb
    WHERE YEAR(time_stamp) = ?
    GROUP BY province, lgu
    ORDER BY province, lgu
");

$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();
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

  <!-- Preloader -->

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-lg-12">
            <br>
            <div class="card card-primary card-outline">
              <div class="card-header d-flex align-items-center">
                <h5 class="m-0 flex-grow-1">Summary of Partner-Beneficiaries per Sector</h5>
                <button id="exportBtn" class="btn btn-info btn-sm" style="font-size: 10px; width: auto;">Export to Excel</button>
              </div>
              <div class="card-body">
                <div class="table-container">
                  <table id="sectoralTable" class="table table-bordered table-striped" style="text-align: center; width: 100%; table-layout: auto;">
                    <thead>
                      <tr>
                        <th rowspan="2">Province</th>
                        <th rowspan="2">City or Municipality</th>
                        <th rowspan="2">No. of Partner-Beneficiaries</th>
                        <th colspan="2">Sex</th>
                        <th colspan="12">CLASSIFICATIONS</th>
                      </tr>
                      <tr>
                        <th>MALE</th>
                        <th>FEMALE</th>
                        <th>NHTS-PR / LISTAHANAN</th>
                        <th>Identified Poor by LSWDO</th>
                        <th>4Ps</th>
                        <th>Farmers</th>
                        <th>Fisherfolks</th>
                        <th>Indigenous People</th>
                        <th>SC / Elder Persons</th>
                        <th>Solo Parents</th>
                        <th>Youth / OSY</th>
                        <th>Persons with Disability</th>
                        <th>LGBTQIA+</th>
                        <th>Others (i.e. Former Rebels / Yakap Bayan)</th>
                      </tr>
                        <?php
                        // Initialize totals
                        $total_beneficiaries = $total_male = $total_female = $total_nhts1 = $total_nhts2 = 0;
                        $total_farmers = $total_fisherfolks = $total_ip = $total_sc = $total_sp = 0;
                        $total_osy = $total_pwd = $total_fourPs = $total_lgbtqia = $total_others = 0;

                        // Store data temporarily to calculate totals first
                        $rows = [];
                        while ($row = mysqli_fetch_assoc($result)) {
                            $rows[] = $row;

                            // Accumulate totals
                            $total_beneficiaries += $row['beneficiary_count'];
                            $total_male += $row['male_count'];
                            $total_female += $row['female_count'];
                            $total_nhts1 += $row['nhts1_count'];
                            $total_nhts2 += $row['nhts2_count'];
                            $total_fourPs += $row['fourPs_count'];
                            $total_farmers += $row['farmers_count'];
                            $total_fisherfolks += $row['fisherfolks_count'];
                            $total_ip += $row['ip_count'];
                            $total_sc += $row['sc_count'];
                            $total_sp += $row['sp_count'];
                            $total_osy += $row['osy_count'];
                            $total_pwd += $row['pwd_count'];
                            $total_lgbtqia += $row['lgbtqia_count'];
                            $total_others += $row['others_count'];
                        }
                        ?>

                      <!-- Total Row at the TOP -->
                      <tr style="font-weight: bold; font-size: 12px;">
                          <td colspan="2">Total</td>
                          <td><?php echo $total_beneficiaries; ?></td>
                          <td><?php echo $total_male; ?></td>
                          <td><?php echo $total_female; ?></td>
                          <td><?php echo $total_nhts1; ?></td>
                          <td><?php echo $total_nhts2; ?></td>
                          <td><?php echo $total_fourPs; ?></td>
                          <td><?php echo $total_farmers; ?></td>
                          <td><?php echo $total_fisherfolks; ?></td>
                          <td><?php echo $total_ip; ?></td>
                          <td><?php echo $total_sc; ?></td>
                          <td><?php echo $total_sp; ?></td>
                          <td><?php echo $total_osy; ?></td>
                          <td><?php echo $total_pwd; ?></td>
                          <td><?php echo $total_lgbtqia; ?></td>
                          <td><?php echo $total_others; ?></td>
                      </tr>
                    </thead>
                    <tbody style="font-size: 10px;">

                        <!-- Render all rows below the total -->
                        <?php foreach ($rows as $row) { ?>
                            <tr>
                                <td style="white-space: nowrap;"><?php echo $row['province']; ?></td>
                                <td><?php echo $row['lgu']; ?></td>
                                <td><?php echo $row['beneficiary_count']; ?></td>
                                <td><?php echo $row['male_count']; ?></td>
                                <td><?php echo $row['female_count']; ?></td>
                                <td><?php echo $row['nhts1_count']; ?></td>
                                <td><?php echo $row['nhts2_count']; ?></td>
                                <td><?php echo $row['fourPs_count']; ?></td>
                                <td><?php echo $row['farmers_count']; ?></td>
                                <td><?php echo $row['fisherfolks_count']; ?></td>
                                <td><?php echo $row['ip_count']; ?></td>
                                <td><?php echo $row['sc_count']; ?></td>
                                <td><?php echo $row['sp_count']; ?></td>
                                <td><?php echo $row['osy_count']; ?></td>
                                <td><?php echo $row['pwd_count']; ?></td>
                                <td><?php echo $row['lgbtqia_count']; ?></td>
                                <td><?php echo $row['others_count']; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <!-- /.col-md-6 -->
        </div>
        <!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->

  <!-- Control Sidebar -->
  <aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
    <div class="p-3">
      <h5>Title</h5>
      <p>Sidebar content</p>
    </div>
  </aside>
  <!-- /.control-sidebar -->
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->

<!-- jQuery -->
<script src="<?php echo $base_url;?>kodus/plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="<?php echo $base_url;?>kodus/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables  & Plugins -->
<script src="<?php echo $base_url;?>kodus/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/jszip/jszip.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- AdminLTE App -->
<script src="<?php echo $base_url;?>kodus/dist/js/adminlte.min.js"></script>

<script>
  $(document).ready(function () {
      $('#sectoralTable').DataTable({
          responsive: true,
          autoWidth: false,
          ordering: false,
          "lengthMenu": [[10,25,50,100,200,300,-1], [10,25,50,100,200,300,"All"]],
      });
  });
</script>
<script>
  document.getElementById('exportBtn').addEventListener('click', function () {
      window.location.href = 'export';
});
</script>
</body>
</html>
