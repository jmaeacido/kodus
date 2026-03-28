<?php
  include('../header.php');

  if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
      header("HTTP/1.1 403 Forbidden");
      echo "Access denied. Admins only.";
      exit;
  }
  
  include('../sidenav.php');

// Sanitize and validate input IDs
$ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
$ids = array_filter($ids, 'is_numeric'); // Ensure only numeric values
$placeholders = implode(',', array_fill(0, count($ids), '?'));

if (empty($ids)) {
    die("No valid IDs provided.");
}

// Prepared statement to prevent SQL injection
$sql = "SELECT * FROM meb WHERE id IN ($placeholders)";
$stmt = $conn->prepare($sql);
$stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
$stmt->execute();
$result = $stmt->get_result();

$_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // CSRF Token
$returnTo = $_GET['return_to'] ?? 'data-tracking-meb-validation';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Records</title>
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
            <h1 class="m-0">Masterlist of Eligible Beneficiaries</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../home">Home</a></li>
              <li class="breadcrumb-item active">MEB</li>
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
              <div class="card-header">
                <div style="margin-left: 10px;">
                  <h4>Edit Record</h4>
                </div>
              </div>
              <!-- <div class="card-header">
                <h3 class="card-title">DataTable with default features</h3>
              </div> -->
              <!-- /.card-header -->
              <div class="table-container">
                <form action="update" method="POST">
                  <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                  <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>">

                    <?php while ($row = $result->fetch_assoc()): ?>
                      <input type="hidden" name="ids[]" value="<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="card card-info">
                            <div class="card-header">
                                <h3 class="card-title">
                                  <?= htmlspecialchars($row['lastName'], ENT_QUOTES, 'UTF-8') ?>, 
                                  <?= htmlspecialchars($row['firstName'], ENT_QUOTES, 'UTF-8') ?> 
                                  <?= htmlspecialchars($row['middleName'], ENT_QUOTES, 'UTF-8') ?> 
                                  <?= htmlspecialchars($row['ext'], ENT_QUOTES, 'UTF-8') ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label>Last Name</label>
                                            <input class="form-control form-control-sm" type="text" name="lastName[<?= $row['id'] ?>]" value="<?= htmlspecialchars($row['lastName'], ENT_QUOTES, 'UTF-8') ?>">
                                      </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label>First Name</label>
                                            <input class="form-control form-control-sm" type="text" name="firstName[<?= $row['id'] ?>]" value="<?= htmlspecialchars($row['firstName'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label>Middle Name</label>
                                            <input class="form-control form-control-sm" type="text" name="middleName[<?= $row['id'] ?>]" value="<?= htmlspecialchars($row['middleName'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label>Ext.</label>
                                            <input class="form-control form-control-sm" type="text" name="ext[<?= $row['id'] ?>]" value="<?= htmlspecialchars($row['ext'], ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label>Purok</label>
                                            <input class="form-control form-control-sm" type="text" name="purok[<?= $row['id'] ?>]" value="<?= $row['purok'] ?>">
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label>Barangay</label>
                                            <input class="form-control form-control-sm" type="text" name="barangay[<?= $row['id'] ?>]" value="<?= $row['barangay'] ?>">
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label>Birthdate</label>
                                            <input class="form-control form-control-sm" type="date" name="birthDate[<?= $row['id'] ?>]" value="<?= $row['birthDate'] ?>">
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label>Age</label>
                                            <input class="form-control form-control-sm" type="number" name="age[<?= $row['id'] ?>]" value="<?= $row['age'] ?>">
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label>Sex</label>
                                            <select class="form-control form-control-sm" name="sex[<?= $row['id'] ?>]">
                                                <option value="" <?= empty($row['sex']) ? 'selected' : '' ?> disabled><?= $row['sex'] ?></option>
                                                <option value="FEMALE" <?= ($row['sex'] == 'FEMALE') ? 'selected' : '' ?>>FEMALE</option>
                                                <option value="MALE" <?= ($row['sex'] == 'MALE') ? 'selected' : '' ?>>MALE</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label>Civil Status</label>
                                            <select class="form-control form-control-sm" name="civilStatus[<?= $row['id'] ?>]">
                                                <option value="" <?= empty($row['civilStatus']) ? 'selected' : '' ?> disabled><?= $row['civilStatus'] ?></option>
                                                <option value="SINGLE" <?= ($row['civilStatus'] == 'SINGLE') ? 'selected' : '' ?>>SINGLE</option>
                                                <option value="MARRIED" <?= ($row['civilStatus'] == 'MARRIED') ? 'selected' : '' ?>>MARRIED</option>
                                                <option value="LIVED-IN" <?= ($row['civilStatus'] == 'LIVED-IN') ? 'selected' : '' ?>>LIVED-IN</option>
                                                <option value="WIDOWED" <?= ($row['civilStatus'] == 'WIDOWED') ? 'selected' : '' ?>>WIDOWED</option>
                                                <option value="SEPARATED" <?= ($row['civilStatus'] == 'SEPARATED') ? 'selected' : '' ?>>SEPARATED</option>
                                                <option value="DIVORCED" <?= ($row['civilStatus'] == 'DIVORCED') ? 'selected' : '' ?>>DIVORCED</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <br>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="radioGroup[<?= $row['id'] ?>]" value="poor" 
                                                    onclick="updateFields(<?= $row['id'] ?>, 'poor')" 
                                                    <?= ($row['nhts1'] === '✓') ? 'checked' : '' ?>>
                                                <label class="form-check-label">NHTS-PR Poor</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="radioGroup[<?= $row['id'] ?>]" value="non-poor" 
                                                    onclick="updateFields(<?= $row['id'] ?>, 'non-poor')" 
                                                    <?= ($row['nhts2'] === '✓') ? 'checked' : '' ?>>
                                                <label class="form-check-label">NHTS-PR Non-Poor</label>
                                            </div>

                                            <!-- Hidden inputs to store values -->
                                            <input type="hidden" name="nhts1[<?= $row['id'] ?>]" id="nhts1_<?= $row['id'] ?>" value="<?= ($row['nhts1'] === '✓') ? '✓' : '' ?>">
                                            <input type="hidden" name="nhts2[<?= $row['id'] ?>]" id="nhts2_<?= $row['id'] ?>" value="<?= ($row['nhts2'] === '✓') ? '✓' : '' ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="fourPs[<?= $row['id'] ?>]" value="✓" <?= ($row['fourPs'] === '✓') ? 'checked' : '' ?>>
                                                <label class="form-check-label">4Ps</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="F[<?= $row['id'] ?>]" value="✓" <?= ($row['F'] === '✓') ? 'checked' : '' ?>>
                                                <label class="form-check-label">Farmer</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="FF[<?= $row['id'] ?>]" value="✓" <?= ($row['FF'] === '✓') ? 'checked' : '' ?>>
                                                <label class="form-check-label">Fisherfolk</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="IP[<?= $row['id'] ?>]" value="✓" <?= ($row['IP'] === '✓') ? 'checked' : '' ?>>
                                                <label class="form-check-label">Indigenous Person</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="SC[<?= $row['id'] ?>]" value="✓" <?= ($row['SC'] === '✓') ? 'checked' : '' ?>>
                                                <label class="form-check-label">Senior Citizen</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="SP[<?= $row['id'] ?>]" value="✓" <?= ($row['SP'] === '✓') ? 'checked' : '' ?>>
                                                <label class="form-check-label">Solo Parent</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="PW[<?= $row['id'] ?>]" value="✓" <?= ($row['PW'] === '✓') ? 'checked' : '' ?>>
                                                <label class="form-check-label">Pregnant Woman</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input pwd-checkbox" type="checkbox" name="PWD[<?= $row['id'] ?>]" value="✓" <?= (!empty($row['PWD'])) ? 'checked' : 'disabled' ?>>
                                                <label class="form-check-label">PWD</label>
                                                <select class="pwd-select" name="PWD[<?= $row['id'] ?>]">
                                                    <option value="" <?= empty($row['PWD']) ? 'selected' : '' ?>></option>
                                                    <option value="A" <?= ($row['PWD'] === 'A') ? 'selected' : '' ?>>A</option>
                                                    <option value="B" <?= ($row['PWD'] === 'B') ? 'selected' : '' ?>>B</option>
                                                    <option value="C" <?= ($row['PWD'] === 'C') ? 'selected' : '' ?>>C</option>
                                                    <option value="D" <?= ($row['PWD'] === 'D') ? 'selected' : '' ?>>D</option>
                                                    <option value="E" <?= ($row['PWD'] === 'E') ? 'selected' : '' ?>>E</option>
                                                    <option value="F" <?= ($row['PWD'] === 'F') ? 'selected' : '' ?>>F</option>
                                                    <option value="G" <?= ($row['PWD'] === 'G') ? 'selected' : '' ?>>G</option>
                                                    <option value="H" <?= ($row['PWD'] === 'H') ? 'selected' : '' ?>>H</option>
                                                    <option value="I" <?= ($row['PWD'] === 'I') ? 'selected' : '' ?>>I</option>
                                                    <option value="J" <?= ($row['PWD'] === 'J') ? 'selected' : '' ?>>J</option>
                                                    <option value="K" <?= ($row['PWD'] === 'K') ? 'selected' : '' ?>>K</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="OSY[<?= $row['id'] ?>]" value="✓" <?= ($row['OSY'] === '✓') ? 'checked' : '' ?>>
                                                <label class="form-check-label">Out-of-School Youth</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="FR[<?= $row['id'] ?>]" value="✓" <?= ($row['FR'] === '✓') ? 'checked' : '' ?>>
                                                <label class="form-check-label">Former Rebel</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="ybDs[<?= $row['id'] ?>]" value="✓" <?= ($row['ybDs'] === '✓') ? 'checked' : '' ?>>
                                                <label class="form-check-label">YAKAP Bayan / Drug Surenderee</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="lgbtqia[<?= $row['id'] ?>]" value="✓" <?= ($row['lgbtqia'] === '✓') ? 'checked' : '' ?>>
                                                <label class="form-check-label">LGBTQIA+</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label>Reason for Edit</label>
                                        <input class="form-control form-control-sm" type="text" name="editReason[<?= $row['id'] ?>]" value="<?= $row['editReason'] ?>">
                                    </div>
                                </div>
                            </div>  
                        </div>
                    <?php endwhile; ?>
                    <button type="submit" class="btn bg-gradient-primary">Save Changes</button>
                    <button type="button" onclick="window.history.back()" class="btn bg-gradient-warning">Cancel</button>
                </form>
              </div>
              <!-- /.card-body -->
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

<!-- Page specific script -->
<script>
  document.querySelector('form').addEventListener('submit', function (event) {
      event.preventDefault();
      Swal.fire({
          title: 'Are you sure?',
          text: 'Do you really want to save the changes?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: '<i class="fas fa-save"></i>',
          cancelButtonText: '<i class="fas fa-times"></i>'
      }).then((result) => {
          if (result.isConfirmed) {
              event.target.submit();
          }
      });
  });
</script>
<script>
    function updateFields(id, value) {
        document.getElementById('nhts1_' + id).value = (value === 'poor') ? '✓' : '';
        document.getElementById('nhts2_' + id).value = (value === 'non-poor') ? '✓' : '';
}
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".pwd-checkbox").forEach(function (checkbox) {
        let select = checkbox.nextElementSibling.nextElementSibling; // Get the corresponding <select>

        // Disable checkbox initially if no value is selected
        checkbox.disabled = (select.value === "");

        // Handle dropdown selection
        select.addEventListener("change", function () {
            if (select.value !== "") {
                checkbox.disabled = false;
                checkbox.checked = true;
            } else {
                checkbox.checked = false;
                checkbox.disabled = true;
            }
        });
    });
});
</script>

</body>
</html>
