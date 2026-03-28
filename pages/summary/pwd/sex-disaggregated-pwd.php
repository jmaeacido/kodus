<?php
  include('../../../header.php');
  include('../../../sidenav.php');

  session_start();

  //Ensure fiscal year is selected
  if (!isset($_SESSION['selected_year'])) {
    echo "<p style='color: red;'>Fiscal year not selected. Please go back and select a year.</p>";
    exit;
  }

  $year = (int) $_SESSION['selected_year'];

  //Prepared statement
  $stmt = $conn->prepare("
    SELECT 
        province, 
        lgu,
        SUM(CASE WHEN sex = 'MALE' AND PWD REGEXP '^[A-Z]$' THEN 1 ELSE 0 END) AS male_pwd_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD REGEXP '^[A-Z]$' THEN 1 ELSE 0 END) AS female_pwd_count,
        SUM(CASE WHEN PWD REGEXP '^[A-Z]$' THEN 1 ELSE 0 END) AS pwd_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'A' THEN 1 ELSE 0 END) AS female_A_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'A' THEN 1 ELSE 0 END) AS male_A_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'B' THEN 1 ELSE 0 END) AS female_B_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'B' THEN 1 ELSE 0 END) AS male_B_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'C' THEN 1 ELSE 0 END) AS female_C_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'C' THEN 1 ELSE 0 END) AS male_C_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'D' THEN 1 ELSE 0 END) AS female_D_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'D' THEN 1 ELSE 0 END) AS male_D_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'E' THEN 1 ELSE 0 END) AS female_E_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'E' THEN 1 ELSE 0 END) AS male_E_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'F' THEN 1 ELSE 0 END) AS female_F_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'F' THEN 1 ELSE 0 END) AS male_F_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'G' THEN 1 ELSE 0 END) AS female_G_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'G' THEN 1 ELSE 0 END) AS male_G_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'H' THEN 1 ELSE 0 END) AS female_H_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'H' THEN 1 ELSE 0 END) AS male_H_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'I' THEN 1 ELSE 0 END) AS female_I_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'I' THEN 1 ELSE 0 END) AS male_I_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'J' THEN 1 ELSE 0 END) AS female_J_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'J' THEN 1 ELSE 0 END) AS male_J_count,
        SUM(CASE WHEN sex = 'FEMALE' AND PWD = 'K' THEN 1 ELSE 0 END) AS female_K_count,
        SUM(CASE WHEN sex = 'MALE' AND PWD = 'K' THEN 1 ELSE 0 END) AS male_K_count
    FROM meb
    WHERE YEAR(time_stamp) = ?
    GROUP BY province, lgu
    ORDER BY province, lgu;
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
                <h5 class="m-0 flex-grow-1">Persons with Disability (PWD) Sex Disaggregation</h5>
                <button id="exportBtn" class="btn btn-info btn-sm" style="font-size: 10px; width: auto;">Export to Excel</button>
              </div>
              <div class="card-body">
                <div class="table-container">
                  <table id="sectoralTable" class="table table-bordered table-striped" style="text-align: center; width: 100%; table-layout: auto;">
                    <thead>
                      <tr>
                        <th rowspan="3">Province</th>
                        <th rowspan="3">City or Municipality</th>
                        <th rowspan="3">Persons with Disability</th>
                        <th colspan="22">PWD CLASSIFICATIONS</th>
                      </tr>
                      <tr>
                        <th colspan="2">A.<p>Multiple Disabilities</p></th>
                        <th colspan="2">B.<p>Intellectual Disability</p></th>
                        <th colspan="2">C.<p>Learning Disability</p></th>
                        <th colspan="2">D.<p>Mental Disability</p></th>
                        <th colspan="2">E.<p>Physical Disability (Orthopedic)</p></th>
                        <th colspan="2">F.<p>Psychosocial Disability</p></th>
                        <th colspan="2">G.<p>Non-apparent Visual Disability</p></th>
                        <th colspan="2">H.<p>Non-apparent Speech and Language Impairment</p></th>
                        <th colspan="2">I.<p>Non-apparent Cancer</p></th>
                        <th colspan="2">J.<p>Non-apparent Rare Disease</p></th>
                        <th colspan="2">K.<p>Deaf/Hard of Hearing Disability</p></th>
                      </tr>
                      <tr>
                        <th>Female</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Male</th>
                      </tr>
                        <?php
                        // Initialize totals
                        $total_pwd = $total_female_A = $total_female_B = $total_female_C = 0;
                        $total_female_D = $total_female_E = $total_female_F = $total_female_G = 0;
                        $total_female_H = $total_female_I = $total_female_J = $total_female_K = 0;
                        $total_male_A = $total_male_B = $total_male_C = 0;
                        $total_male_D = $total_male_E = $total_male_F = $total_male_G = 0;
                        $total_male_H = $total_male_I = $total_male_J = $total_male_K = 0;

                        // Store data temporarily to calculate totals first
                        $rows = [];
                        while ($row = mysqli_fetch_assoc($result)) {
                            $rows[] = $row;

                            // Accumulate totals
                            $total_pwd += $row['pwd_count'];
                            $total_female_A += $row['male_A_count'];
                            $total_male_A += $row['female_A_count'];
                            $total_female_B += $row['male_B_count'];
                            $total_male_B += $row['female_B_count'];
                            $total_female_C += $row['male_C_count'];
                            $total_male_C += $row['female_C_count'];
                            $total_female_D += $row['female_D_count'];
                            $total_male_D += $row['male_D_count'];
                            $total_female_E += $row['female_E_count'];
                            $total_male_E += $row['male_E_count'];
                            $total_female_F += $row['female_F_count'];
                            $total_male_F += $row['male_F_count'];
                            $total_female_G += $row['female_G_count'];
                            $total_male_G += $row['male_G_count'];
                            $total_female_H += $row['female_H_count'];
                            $total_male_H += $row['male_H_count'];
                            $total_female_I += $row['female_I_count'];
                            $total_male_I += $row['male_I_count'];
                            $total_female_J += $row['female_J_count'];
                            $total_male_J += $row['male_J_count'];
                            $total_female_K += $row['female_K_count'];
                            $total_male_K += $row['male_K_count'];
                        }
                        ?>

                      <!-- Total Row at the TOP -->
                      <tr style="font-weight: bold; font-size: 12px;">
                          <td colspan="2">Total</td>
                          <td><?php echo $total_pwd; ?></td>
                          <td><?php echo $total_female_A; ?></td>
                          <td><?php echo $total_male_A; ?></td>
                          <td><?php echo $total_female_B; ?></td>
                          <td><?php echo $total_male_B; ?></td>
                          <td><?php echo $total_female_C; ?></td>
                          <td><?php echo $total_male_C; ?></td>
                          <td><?php echo $total_female_D; ?></td>
                          <td><?php echo $total_male_D; ?></td>
                          <td><?php echo $total_female_E; ?></td>
                          <td><?php echo $total_male_E; ?></td>
                          <td><?php echo $total_female_F; ?></td>
                          <td><?php echo $total_male_F; ?></td>
                          <td><?php echo $total_female_G; ?></td>
                          <td><?php echo $total_male_G; ?></td>
                          <td><?php echo $total_female_H; ?></td>
                          <td><?php echo $total_male_H; ?></td>
                          <td><?php echo $total_female_I; ?></td>
                          <td><?php echo $total_male_I; ?></td>
                          <td><?php echo $total_female_J; ?></td>
                          <td><?php echo $total_male_J; ?></td>
                          <td><?php echo $total_female_K; ?></td>
                          <td><?php echo $total_male_K; ?></td>
                      </tr>
                    </thead>
                    <tbody style="font-size: 10px;">

                        <!-- Render all rows below the total -->
                        <?php foreach ($rows as $row) { ?>
                            <tr>
                                <td style="white-space: nowrap;"><?php echo $row['province']; ?></td>
                                <td><?php echo $row['lgu']; ?></td>
                                <td><?php echo $row['pwd_count']; ?></td>
                                <td><?php echo $row['female_A_count']; ?></td>
                                <td><?php echo $row['male_A_count']; ?></td>
                                <td><?php echo $row['female_B_count']; ?></td>
                                <td><?php echo $row['male_B_count']; ?></td>
                                <td><?php echo $row['female_C_count']; ?></td>
                                <td><?php echo $row['male_C_count']; ?></td>
                                <td><?php echo $row['female_D_count']; ?></td>
                                <td><?php echo $row['male_D_count']; ?></td>
                                <td><?php echo $row['female_E_count']; ?></td>
                                <td><?php echo $row['male_E_count']; ?></td>
                                <td><?php echo $row['female_F_count']; ?></td>
                                <td><?php echo $row['male_F_count']; ?></td>
                                <td><?php echo $row['female_G_count']; ?></td>
                                <td><?php echo $row['male_G_count']; ?></td>
                                <td><?php echo $row['female_H_count']; ?></td>
                                <td><?php echo $row['male_H_count']; ?></td>
                                <td><?php echo $row['female_I_count']; ?></td>
                                <td><?php echo $row['male_I_count']; ?></td>
                                <td><?php echo $row['female_J_count']; ?></td>
                                <td><?php echo $row['male_J_count']; ?></td>
                                <td><?php echo $row['female_K_count']; ?></td>
                                <td><?php echo $row['male_K_count']; ?></td>
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
      window.location.href = 'sex-disaggregated-pwd_export';
});
</script>
</body>
</html>
