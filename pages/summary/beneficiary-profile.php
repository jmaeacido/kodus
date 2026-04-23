<?php
  include('../../header.php');
  include('../../sidenav.php');
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
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Partner-Beneficiaries Profile</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../../home">Home</a></li>
              <li class="breadcrumb-item active">Partner-Beneficiaries Profile</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header d-flex align-items-center">
            <h4 class="m-0 flex-grow-1">Partner-Beneficiaries Profile</h4>
            <button id="exportBtn" class="btn btn-info btn-sm" style="font-size: 10px; width: auto;">Export to Excel</button>
          </div>
          <div class="table-container kodus-table-scroll" style="--kodus-table-min-width: 1600px;">
            <table id="table1" class="table table-bordered table-striped" style="text-align: center; width: 100%; table-layout: auto;">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Age</th>
                  <th>Sex</th>
                  <th>Listahanan 3 (P)</th>
                  <th>LSWDO Assessment (NON)</th>
                  <th>Pantawid Pamilyang Pilipino Program (4Ps)</th>
                  <th>Farmers (F)</th>
                  <th>Fisher-folks (FF)</th>
                  <th>Informal Sector (IS)</th>
                  <th>Indigenous People (IP)</th>
                  <th>Senior Citizen (SC)</th>
                  <th>Solo Parent (SP)</th>
                  <th>Lactating Women (LW)</th>
                  <th>Pregnant Women (PW)</th>
                  <th>PWD</th>
                  <th>Out of School Youth (OSY)</th>
                  <th>Former Rebel (FR)</th>
                  <th>YAKAP Bayan/ Person Who Used Drugs (YB/PWUD)</th>
                  <th>LGBTQIA+</th>
                </tr>
              </thead>
              <tbody style="font-size: 10px; white-space: nowrap;"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../../plugins/jquery/jquery.min.js"></script>
<script src="../../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
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
<script src="../../dist/js/adminlte.min.js"></script>
<script>
  function formatFourPsStatus(value) {
      const normalized = String(value ?? '').trim().toUpperCase();
      if (normalized === 'M') {
          return 'M - Member';
      }
      if (normalized === 'G') {
          return 'G - Graduated';
      }
      return ['✓', 'âœ“', 'Ã¢Å“â€œ', 'YES', 'Y', 'TRUE', '1'].includes(normalized) ? 'M - Member' : '';
  }

  $(document).ready(function() {
      $("#table1").DataTable({
          "processing": false,
          "serverSide": true,
          "ajax": {
              "url": "fetch_data_profile.php",
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
              { "data": "nhts2", defaultContent: "" },
              {
                "data": "fourPs",
                "defaultContent": "",
                "render": function (data) {
                  return formatFourPsStatus(data);
                }
              },
              { "data": "F", defaultContent: "" },
              { "data": "FF", defaultContent: "" },
              { "data": "IS", defaultContent: "" },
              { "data": "IP", defaultContent: "" },
              { "data": "SC", defaultContent: "" },
              { "data": "SP", defaultContent: "" },
              { "data": "LW", defaultContent: "" },
              { "data": "PW", defaultContent: "" },
              {
                "data": "PWD",
                "defaultContent": "",
                "render": function (data) {
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
              { "data": "OSY", defaultContent: "" },
              { "data": "FR", defaultContent: "" },
              { "data": "ybDs", defaultContent: "" },
              { "data": "lgbtqia", defaultContent: "" }
          ],
          "lengthChange": true,
          "lengthMenu": [[10,25,50,100,200,300,-1], [10,25,50,100,200,300,"All"]],
          "pageLength": 10,
          "paging": true,
          "responsive": false,
          "scrollX": true
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
