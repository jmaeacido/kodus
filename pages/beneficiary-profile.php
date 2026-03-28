<?php
  include('../header.php');
  include('../sidenav.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | Partner-Beneficiaries Profile</title>
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
            <h1 class="m-0">Partner-Beneficiaries Profile</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../home">Home</a></li>
              <li class="breadcrumb-item active">Partner-Beneficiaries Profile</li>
            </ol>
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="content">
      <div class="container-fluid">
            <div class="card">
              <div class="card-header d-flex align-items-center">
                <h4 class="m-0 flex-grow-1">Partner-Beneficiaries Profile</h4>
                <!--<form action="import" method="POST" enctype="multipart/form-data">
                    <label for="excelFile" class="btn btn-info btn-sm" style="font-size: 10px; position: relative; top: 4px;">Choose Excel File:</label>
                    <input type="file" name="excelFile" id="excelFile" accept=".xlsx, .xls" style="font-size: 10px; display: none;" onchange="displayFileName()">
                    <span id="file-name"></span>
                    <button type="submit" class="btn btn-success btn-sm" name="import" style="font-size: 10px; width: 60px;">Import</button>
                </form>-->&nbsp;
                <button id="exportBtn" class="btn btn-info btn-sm" style="font-size: 10px; width: auto;">Export to Excel</button>
              </div>
              <!-- /.card-header -->
              <div class="table-container">
                <table id="table1" class="table table-bordered table-striped" style="text-align: center; width: 100%; table-layout: auto;">
                  <thead>
                    <tr>
                      <th colspan="4">Partner-Beneficiary</th>
                      <th colspan="3">Eligibility Criteria</th>
                      <th colspan="3">Primary Sectors</th>
                      <th colspan="4">Sub Sectors</th>
                      <th colspan="6">Others</th>
                    </tr>
                    <tr>
                      <th>ID</th>
                      <th>Name</th>
                      <th>Age</th>
                      <th>Sex</th>
                      <th>Listahan Poor 3</th>
                      <th>4Ps Beneficiary</th>
                      <th>Not Enlisted but with MSWDO Certification</th>
                      <th>Farmer</th>
                      <th>Fisherfolk</th>
                      <th>Informal Sector</th>
                      <th>Women</th>
                      <th>PWD</th>
                      <th>Elderly</th>
                      <th>Indigenous</th>
                      <th>Solo Parent</th>
                      <th>Youth (18-30)</th>
                      <th>Out-of-School Youth</th>
                      <th>Former Rebel</th>
                      <th>PWUD</th>
                      <th>LGBTQIA+</th>
                    </tr>
                  </thead>
                  <tbody style="font-size: 10px; white-space: nowrap;">
                    <!-- Table data here -->
                  </tbody>
                </table>
              </div>
              <!-- /.card-body -->
            </div>
        <!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->

  <!-- Main Footer -->
  <footer class="main-footer">
    <!-- To the right -->
    <div class="float-right d-none d-sm-inline">
      Anything you want
    </div>
    <!-- Default to the left -->
    <strong>Copyright &copy; 2014-2021 <a href="https://adminlte.io">AdminLTE.io</a>.</strong> All rights reserved.
  </footer>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->

<!-- jQuery -->
<script src="../plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
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
<script src="../dist/js/adminlte.min.js"></script>
<!-- Page specific script -->
<script>
  function displayFileName() {
  var fileInput = document.getElementById('excelFile');
  var fileName = fileInput.files[0] ? fileInput.files[0].name : 'No file chosen';
  document.getElementById('file-name').textContent = fileName;
}
</script>
<script>
  $(document).ready(function() {

      let table = $("#table1").DataTable({
          "processing": false, // Show the processing indicator
          "serverSide": true, // Enable server-side processing
          "ajax": {
              "url": "fetch_data_profile.php", // The PHP file to fetch data
              "type": "GET",
              "dataSrc": function(json) {
                  return json.data;
              }
          },
          "columns": [
              { "data": "id", defaultContent: "" },
              { "data": "Name", defaultContent: "" },
              { "data": "age", defaultContent: "" },
              { "data": "sex", defaultContent: "" },
              { "data": "nhts1", defaultContent: "" },
              { "data": "fourPs", defaultContent: "" },
              { "data": "nhts2", defaultContent: "" },
              { "data": "F", defaultContent: "" },
              { "data": "FF", defaultContent: "" },
              { "data": null, defaultContent: ""},
              {
                "data": "sex",
                "defaultContent": "",
                "render": function (data, type, row) {
                  return data && data.toLowerCase() === "female" ? "✓" : "";
                }
              },
              {
                "data": "PWD",
                "defaultContent": "",
                "render": function (data, type, row) {
                  const pwdMap = {
                    "A": "Multiple Disabilities",
                    "B": "Intellectual Disability",
                    "C": "Learning Disability",
                    "D": "Mental Disability",
                    "E": "Physical Disability (Orthopedic)",
                    "F": "Psychosocial Disability",
                    "G": "Non-apparent Visual Disability",
                    "H": "Non-apparent Speech and Language Impairment",
                    "I": "Non-apparent Cancer",
                    "J": "Non-apparent Rare Disease",
                    "K": "Deaf/Hard of Hearing Disability"
                  };
                  return pwdMap[data] || "";
                }
              },
              { "data": "SC", defaultContent: "" },
              { "data": "IP", defaultContent: "" },
              { "data": "SP", defaultContent: "" },
              {
                "data": "age",
                "defaultContent": "",
                "render": function (data, type, row) {
                  return data >= 18 && data <= 30 ? "✓" : "";
                }
              },
              { "data": "OSY", defaultContent: "" },
              { "data": "FR", defaultContent: "" },
              { "data": "ybDs", defaultContent: "" },
              { "data": "lgbtqia", defaultContent: "" }
          ],
          "lengthChange": true,
          "lengthMenu": [[10,25,50,100,200,300,-1], [10,25,50,100,200,300,"All"]],
          "pageLength": 10, // Default rows per page
          "paging": true,
          //"dom": 'Bfrtip',
          "responsive": false,
          //"buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"],
          //"rowCallback": function(row, data, index) {
              // Ensure the counter updates correctly per page
              //$('td:eq(0)', row).html(index + 1 + table.page.info().start);
          //}
      });
  });
</script>
<script>
  document.getElementById('exportBtn').addEventListener('click', function () {
      window.location.href = 'export_profile';
});
</script>
</body>
</html>
