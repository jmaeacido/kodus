<?php
  include('../header.php');

  $currentUserType = (string) ($_SESSION['user_type'] ?? '');
  if (!in_array($currentUserType, ['admin', 'editor'], true)) {
      header("HTTP/1.1 403 Forbidden");
      echo "Access denied.";
      exit;
  }
  
  include('../sidenav.php');

const MEB_EDIT_CHECKMARK = "\u{2713}";

function meb_edit_is_marked($value): bool
{
    $normalized = strtoupper(trim((string) $value));
    return in_array($normalized, [MEB_EDIT_CHECKMARK, 'âœ“', 'Ã¢Å“â€œ', 'ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“', 'YES', 'Y', 'TRUE', '1'], true);
}

function meb_edit_normalize_fourps($value): string
{
    $normalized = strtoupper(trim((string) $value));

    if ($normalized === 'G') {
        return 'G';
    }

    if ($normalized === 'M' || meb_edit_is_marked($value)) {
        return 'M';
    }

    return '';
}

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
$result = db_stmt_fetch_all_assoc($stmt);

if (count($result) !== count($ids)) {
    header("HTTP/1.1 404 Not Found");
    echo "One or more selected records could not be found.";
    exit;
}

foreach ($result as $row) {
    if (!auth_can_edit_meb_province($conn, $row['province'] ?? '')) {
        header("HTTP/1.1 403 Forbidden");
        echo "Editors can only edit records in their assigned province.";
        exit;
    }
}

$csrfToken = security_get_csrf_token();
$returnTo = $_GET['return_to'] ?? 'data-tracking-meb';
$updateFlash = $_SESSION['meb_update_flash'] ?? null;
unset($_SESSION['meb_update_flash']);
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
                <form action="update" method="POST" id="mebUpdateForm" data-no-loader="true">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>">

                    <?php foreach ($result as $row): ?>
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
                                            <input
                                                class="form-control form-control-sm meb-birthdate-input"
                                                type="date"
                                                name="birthDate[<?= $row['id'] ?>]"
                                                value="<?= htmlspecialchars($row['birthDate'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-row-id="<?= (int) $row['id'] ?>">
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label>Age</label>
                                            <input
                                                class="form-control form-control-sm meb-age-input"
                                                type="number"
                                                name="age[<?= $row['id'] ?>]"
                                                value="<?= htmlspecialchars((string) $row['age'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-row-id="<?= (int) $row['id'] ?>"
                                                readonly>
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
                                                    <?= meb_edit_is_marked($row['nhts1']) ? 'checked' : '' ?>>
                                                <label class="form-check-label">Listahanan 3 (P)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="radioGroup[<?= $row['id'] ?>]" value="non-poor" 
                                                    onclick="updateFields(<?= $row['id'] ?>, 'non-poor')" 
                                                    <?= meb_edit_is_marked($row['nhts2']) ? 'checked' : '' ?>>
                                                <label class="form-check-label">LSWDO Assessment (NON)</label>
                                            </div>

                                            <!-- Hidden inputs to store values -->
                                            <input type="hidden" name="nhts1[<?= $row['id'] ?>]" id="nhts1_<?= $row['id'] ?>" value="<?= meb_edit_is_marked($row['nhts1']) ? MEB_EDIT_CHECKMARK : '' ?>">
                                            <input type="hidden" name="nhts2[<?= $row['id'] ?>]" id="nhts2_<?= $row['id'] ?>" value="<?= meb_edit_is_marked($row['nhts2']) ? MEB_EDIT_CHECKMARK : '' ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <div class="form-check">
                                                <select class="form-control form-control-sm mb-2" name="fourPs[<?= $row['id'] ?>]"><option value="" <?= empty($row['fourPs']) ? 'selected' : '' ?>></option><option value="M" <?= (meb_edit_normalize_fourps($row['fourPs']) === 'M') ? 'selected' : '' ?>>M - Member</option><option value="G" <?= ($row['fourPs'] === 'G') ? 'selected' : '' ?>>G - Graduated</option><option value="<?= htmlspecialchars(MEB_EDIT_CHECKMARK, ENT_QUOTES, 'UTF-8') ?>" <?= (meb_edit_is_marked($row['fourPs']) && meb_edit_normalize_fourps($row['fourPs']) !== 'M') ? 'selected' : '' ?>>Legacy checkmark</option></select>
                                                <label class="form-check-label">Pantawid Pamilyang Pilipino Program (4Ps)</label>
                                            </div>
                                            <div class="form-check">
                                                <input
                                                    class="form-check-input meb-f-checkbox"
                                                    type="checkbox"
                                                    name="F[<?= $row['id'] ?>]"
                                                    value="✓"
                                                    <?= meb_edit_is_marked($row['F']) ? 'checked' : '' ?>
                                                    data-row-id="<?= (int) $row['id'] ?>">
                                                <label class="form-check-label">Farmers (F)</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <div class="form-check">
                                                <input
                                                    class="form-check-input meb-ff-checkbox"
                                                    type="checkbox"
                                                    name="FF[<?= $row['id'] ?>]"
                                                    value="✓"
                                                    <?= meb_edit_is_marked($row['FF']) ? 'checked' : '' ?>
                                                    data-row-id="<?= (int) $row['id'] ?>">
                                                <label class="form-check-label">Fisher-folks (FF)</label>
                                            </div>
                                            <div class="form-check">
                                                <input
                                                    class="form-check-input meb-is-checkbox"
                                                    type="checkbox"
                                                    name="IS[<?= $row['id'] ?>]"
                                                    value="✓"
                                                    <?= meb_edit_is_marked($row['IS']) ? 'checked' : '' ?>
                                                    data-row-id="<?= (int) $row['id'] ?>">
                                                <label class="form-check-label">Informal Sector (IS)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="IP[<?= $row['id'] ?>]" value="✓" <?= meb_edit_is_marked($row['IP']) ? 'checked' : '' ?>>
                                                <label class="form-check-label">Indigenous People (IP)</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <div class="form-check">
                                                <input
                                                    class="form-check-input meb-sc-checkbox"
                                                    type="checkbox"
                                                    name="SC[<?= $row['id'] ?>]"
                                                    value="✓"
                                                    <?= meb_edit_is_marked($row['SC']) ? 'checked' : '' ?>
                                                    data-row-id="<?= (int) $row['id'] ?>">
                                                <label class="form-check-label">Senior Citizen (SC)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="SP[<?= $row['id'] ?>]" value="✓" <?= meb_edit_is_marked($row['SP']) ? 'checked' : '' ?>>
                                                <label class="form-check-label">Solo Parent (SP)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="LW[<?= $row['id'] ?>]" value="✓" <?= meb_edit_is_marked($row['LW']) ? 'checked' : '' ?>>
                                                <label class="form-check-label">Lactating Women (LW)</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="PW[<?= $row['id'] ?>]" value="✓" <?= meb_edit_is_marked($row['PW']) ? 'checked' : '' ?>>
                                                <label class="form-check-label">Pregnant Women (PW)</label>
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
                                                <label class="form-check-label">Out of School Youth (OSY)</label>
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
                                                <label class="form-check-label">YAKAP Bayan/ Person Who Used Drugs (YB/PWUD)</label>
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
                    <?php endforeach; ?>
                    <button type="submit" class="btn bg-gradient-primary" id="mebUpdateSubmit">Save Changes</button>
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
<script src="<?php echo $app_root; ?>plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="<?php echo $app_root; ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables  & Plugins -->
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
<script src="../plugins/sweetalert2/sweetalert2.min.js"></script>
<!-- AdminLTE App -->
<script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>

<!-- Page specific script -->
<script>
  const mebUpdateFlash = <?php echo json_encode($updateFlash); ?>;

  if (mebUpdateFlash && mebUpdateFlash.message) {
      Swal.fire({
          icon: mebUpdateFlash.type === 'success' ? 'success' : 'error',
          title: mebUpdateFlash.type === 'success' ? 'Success' : 'Error',
          text: String(mebUpdateFlash.message)
      });
  }

  document.getElementById('mebUpdateForm').addEventListener('submit', function (event) {
      event.preventDefault();
      const form = event.target;
      const submitButton = document.getElementById('mebUpdateSubmit');

      Swal.fire({
          title: 'Are you sure?',
          text: 'Do you really want to save the changes?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: '<i class="fas fa-save"></i>',
          cancelButtonText: '<i class="fas fa-times"></i>'
      }).then(async (result) => {
          if (!result.isConfirmed) {
              return;
          }

          const formData = new FormData(form);
          submitButton.disabled = true;

          Swal.fire({
              title: 'Saving changes...',
              text: 'Please wait while the beneficiary record is updated.',
              allowOutsideClick: false,
              allowEscapeKey: false,
              didOpen: () => {
                  Swal.showLoading();
              }
          });

          try {
              const response = await fetch(form.action, {
                  method: 'POST',
                  body: formData,
                  headers: {
                      'X-Requested-With': 'XMLHttpRequest'
                  },
                  credentials: 'same-origin'
              });

              const payload = await response.json();

              if (!response.ok || !payload || payload.success !== true) {
                  throw new Error(payload && payload.message ? payload.message : 'Failed to save changes.');
              }

              await Swal.fire({
                  icon: 'success',
                  title: 'Success',
                  text: payload.message || 'Changes have been saved successfully.'
              });

              window.location.href = payload.redirect || '<?php echo htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8'); ?>';
          } catch (error) {
              submitButton.disabled = false;
              Swal.fire({
                  icon: 'error',
                  title: 'Save Failed',
                  text: error && error.message ? error.message : 'Unable to save changes right now.'
              });
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
    function calculateAgeFromBirthdate(value) {
        if (!value) {
            return '';
        }

        const birthdate = new Date(value + 'T00:00:00');
        if (Number.isNaN(birthdate.getTime())) {
            return '';
        }

        const today = new Date();
        let age = today.getFullYear() - birthdate.getFullYear();
        const monthDifference = today.getMonth() - birthdate.getMonth();

        if (monthDifference < 0 || (monthDifference === 0 && today.getDate() < birthdate.getDate())) {
            age -= 1;
        }

        return age >= 0 ? age : '';
    }

    function syncAgeAndSeniorCitizen(rowId) {
        const birthdateInput = document.querySelector('.meb-birthdate-input[data-row-id="' + rowId + '"]');
        const ageInput = document.querySelector('.meb-age-input[data-row-id="' + rowId + '"]');
        const seniorCitizenCheckbox = document.querySelector('.meb-sc-checkbox[data-row-id="' + rowId + '"]');

        if (!birthdateInput || !ageInput || !seniorCitizenCheckbox) {
            return;
        }

        const computedAge = calculateAgeFromBirthdate(birthdateInput.value);
        ageInput.value = computedAge;
        seniorCitizenCheckbox.checked = computedAge !== '' && Number(computedAge) >= 60;
    }

    function syncInformalSector(rowId) {
        const farmerCheckbox = document.querySelector('.meb-f-checkbox[data-row-id="' + rowId + '"]');
        const fisherfolkCheckbox = document.querySelector('.meb-ff-checkbox[data-row-id="' + rowId + '"]');
        const informalSectorCheckbox = document.querySelector('.meb-is-checkbox[data-row-id="' + rowId + '"]');

        if (!farmerCheckbox || !fisherfolkCheckbox || !informalSectorCheckbox) {
            return;
        }

        informalSectorCheckbox.checked = !farmerCheckbox.checked && !fisherfolkCheckbox.checked;
    }

    document.querySelectorAll('.meb-birthdate-input').forEach(function (input) {
        const rowId = input.getAttribute('data-row-id');
        if (!rowId) {
            return;
        }

        syncAgeAndSeniorCitizen(rowId);
        input.addEventListener('change', function () {
            syncAgeAndSeniorCitizen(rowId);
        });
        input.addEventListener('input', function () {
            syncAgeAndSeniorCitizen(rowId);
        });
    });

    document.querySelectorAll('.meb-f-checkbox, .meb-ff-checkbox').forEach(function (checkbox) {
        const rowId = checkbox.getAttribute('data-row-id');
        if (!rowId) {
            return;
        }

        syncInformalSector(rowId);
        checkbox.addEventListener('change', function () {
            syncInformalSector(rowId);
        });
    });

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
