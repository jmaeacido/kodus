<?php
include('../../../header.php');
include('../../../sidenav.php');

session_start();

// Ensure fiscal year is selected
if (!isset($_SESSION['selected_year'])) {
    echo "<p style='color:red;'>Fiscal year not selected. Please go back and select a year.</p>";
    exit;
}

$year = (int) $_SESSION['selected_year'];

// Use prepared statement
$stmt = $conn->prepare("
    SELECT 
        province, 
        lgu,
        SUM(CASE WHEN sex = 'MALE' AND PWD REGEXP '^[A-Z]$' THEN 1 ELSE 0 END) AS male_pwd_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD REGEXP '^[A-Z]$' THEN 1 ELSE 0 END) AS female_pwd_count,
        SUM(CASE WHEN PWD REGEXP '^[A-Z]$' THEN 1 ELSE 0 END) AS pwd_count,
        SUM(CASE WHEN PWD = 'A' THEN 1 ELSE 0 END) AS A_count,
        SUM(CASE WHEN PWD = 'B' THEN 1 ELSE 0 END) AS B_count,
        SUM(CASE WHEN PWD = 'C' THEN 1 ELSE 0 END) AS C_count,
        SUM(CASE WHEN PWD = 'D' THEN 1 ELSE 0 END) AS D_count,
        SUM(CASE WHEN PWD = 'E' THEN 1 ELSE 0 END) AS E_count,
        SUM(CASE WHEN PWD = 'F' THEN 1 ELSE 0 END) AS F_count,
        SUM(CASE WHEN PWD = 'G' THEN 1 ELSE 0 END) AS G_count,
        SUM(CASE WHEN PWD = 'H' THEN 1 ELSE 0 END) AS H_count,
        SUM(CASE WHEN PWD = 'I' THEN 1 ELSE 0 END) AS I_count,
        SUM(CASE WHEN PWD = 'J' THEN 1 ELSE 0 END) AS J_count,
        SUM(CASE WHEN PWD = 'K' THEN 1 ELSE 0 END) AS K_count
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
          <div class="col-sm-6">
            <h1 class="m-0">Starter Page</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Starter Page</li>
            </ol>
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-lg-12">

            <div class="card card-primary card-outline">
              <div class="card-header d-flex align-items-center">
                <h5 class="m-0 flex-grow-1">Persons with Disability (PWD) Classification</h5>
                <button id="exportBtn" class="btn btn-info btn-sm" style="font-size: 10px; width: auto;">Export to Excel</button>
              </div>
              <div class="card-body">
                <div class="table-container">
                  <table id="sectoralTable" class="table table-bordered table-striped" style="text-align: center; width: 100%; table-layout: auto;">
                    <thead>
                      <tr>
                        <th rowspan="2">Province</th>
                        <th rowspan="2">City or Municipality</th>
                        <th rowspan="2">Persons with Disability</th>
                        <th colspan="2">Sex</th>
                        <th colspan="11">PWD CLASSIFICATIONS</th>
                      </tr>
                      <tr>
                        <th>MALE</th>
                        <th>FEMALE</th>
                        <th>A.<p>Multiple Disabilities</p></th>
                        <th>B.<p>Intellectual Disability</p></th>
                        <th>C.<p>Learning Disability</p></th>
                        <th>D.<p>Mental Disability</p></th>
                        <th>E.<p>Physical Disability (Orthopedic)</p></th>
                        <th>F.<p>Psychosocial Disability</p></th>
                        <th>G.<p>Non-apparent Visual Disability</p></th>
                        <th>H.<p>Non-apparent Speech and Language Impairment</p></th>
                        <th>I.<p>Non-apparent Cancer</p></th>
                        <th>J.<p>Non-apparent Rare Disease</p></th>
                        <th>K.<p>Deaf/Hard of Hearing Disability</p></th>
                      </tr>
                        <?php
                        // Initialize totals
                        $total_pwd = $total_male = $total_female = $total_A = $total_B = 0;
                        $total_C = $total_D = $total_E = $total_F = $total_G = 0;
                        $total_H = $total_I = $total_J = $total_K = 0;

                        // Store data temporarily to calculate totals first
                        $rows = [];
                        while ($row = mysqli_fetch_assoc($result)) {
                            $rows[] = $row;

                            // Accumulate totals
                            $total_pwd += $row['pwd_count'];
                            $total_male += $row['male_pwd_count'];
                            $total_female += $row['female_pwd_count'];
                            $total_A += $row['A_count'];
                            $total_B += $row['B_count'];
                            $total_C += $row['C_count'];
                            $total_D += $row['D_count'];
                            $total_E += $row['E_count'];
                            $total_F += $row['F_count'];
                            $total_G += $row['G_count'];
                            $total_H += $row['H_count'];
                            $total_I += $row['I_count'];
                            $total_J += $row['J_count'];
                            $total_K += $row['K_count'];
                        }
                        ?>

                      <!-- Total Row at the TOP -->
                      <tr style="font-weight: bold; font-size: 12px;">
                          <td colspan="2">Total</td>
                          <td><?php echo $total_pwd; ?></td>
                          <td><?php echo $total_male; ?></td>
                          <td><?php echo $total_female; ?></td>
                          <td><?php echo $total_A; ?></td>
                          <td><?php echo $total_B; ?></td>
                          <td><?php echo $total_C; ?></td>
                          <td><?php echo $total_D; ?></td>
                          <td><?php echo $total_E; ?></td>
                          <td><?php echo $total_F; ?></td>
                          <td><?php echo $total_G; ?></td>
                          <td><?php echo $total_H; ?></td>
                          <td><?php echo $total_I; ?></td>
                          <td><?php echo $total_J; ?></td>
                          <td><?php echo $total_K; ?></td>
                      </tr>
                    </thead>
                    <tbody style="font-size: 10px;">

                        <!-- Render all rows below the total -->
                        <?php foreach ($rows as $row) { ?>
                            <tr>
                                <td style="white-space: nowrap;"><?php echo $row['province']; ?></td>
                                <td><?php echo $row['lgu']; ?></td>
                                <td><?php echo $row['pwd_count']; ?></td>
                                <td><?php echo $row['male_pwd_count']; ?></td>
                                <td><?php echo $row['female_pwd_count']; ?></td>
                                <td><?php echo $row['A_count']; ?></td>
                                <td><?php echo $row['B_count']; ?></td>
                                <td><?php echo $row['C_count']; ?></td>
                                <td><?php echo $row['D_count']; ?></td>
                                <td><?php echo $row['E_count']; ?></td>
                                <td><?php echo $row['F_count']; ?></td>
                                <td><?php echo $row['G_count']; ?></td>
                                <td><?php echo $row['H_count']; ?></td>
                                <td><?php echo $row['I_count']; ?></td>
                                <td><?php echo $row['J_count']; ?></td>
                                <td><?php echo $row['K_count']; ?></td>
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
      window.location.href = 'pwd_export';
});
</script>
</body>
</html>
