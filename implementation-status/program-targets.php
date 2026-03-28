<?php
include('../header.php');
include('../sidenav.php');

$userType = $_SESSION['user_type'] ?? 'user';
$selectedYear = (int) ($_SESSION['selected_year'] ?? date('Y'));
$importSuccess = $_SESSION['target_import_success'] ?? null;
$importError = $_SESSION['target_import_error'] ?? null;
unset($_SESSION['target_import_success'], $_SESSION['target_import_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | Baseline Targets</title>
  <link rel="stylesheet" href="<?php echo $base_url;?>kodus/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $base_url;?>kodus/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $base_url;?>kodus/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <style>
    .target-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
      margin-bottom: 1rem;
    }
    .target-meta {
      display: flex;
      gap: .75rem;
      flex-wrap: wrap;
      align-items: center;
    }
    .target-pill {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      padding: .45rem .8rem;
      border-radius: 999px;
      background: rgba(13, 110, 253, 0.12);
      color: inherit;
      font-weight: 600;
    }
    .target-import-card {
      border: 1px dashed rgba(13, 110, 253, 0.28);
      border-radius: 14px;
      padding: 1rem;
      margin-bottom: 1rem;
      background: rgba(13, 110, 253, 0.04);
    }
    .target-import-card form {
      display: flex;
      gap: .75rem;
      flex-wrap: wrap;
      align-items: center;
    }
    .target-help {
      color: inherit;
      opacity: .8;
      margin-bottom: .75rem;
    }
    .viewer-note {
      border-radius: 12px;
      padding: .9rem 1rem;
      margin-bottom: 1rem;
      border: 1px solid rgba(23, 162, 184, 0.28);
      background: rgba(23, 162, 184, 0.12);
      color: inherit;
    }
    .target-item {
      display: flex;
      gap: 8px;
      margin-bottom: 8px;
      align-items: center;
    }
    .target-item .form-control,
    .target-item .custom-select {
      flex: 1 1 auto;
    }
    .kodus-edit-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
      align-items: stretch;
    }
    .kodus-edit-field {
      display: flex;
      flex-direction: column;
      align-self: stretch;
    }
    .kodus-edit-field > label {
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.02em;
      margin-bottom: 4px;
      display: block;
      min-height: 2.4rem;
    }
    .kodus-edit-field > .form-control,
    .kodus-edit-field > .custom-select,
    .kodus-edit-field > #target-entry-list {
      margin-top: auto;
    }
  </style>
</head>
<body class="hold-transition dark-mode sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">

  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">Baseline Targets</h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../home">Home</a></li>
              <li class="breadcrumb-item active">Baseline Targets</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header">
            <div class="target-toolbar">
              <div class="target-meta">
                <span class="target-pill"><i class="fas fa-calendar-alt"></i> Fiscal Year <?php echo htmlspecialchars((string) $selectedYear, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="target-pill"><i class="fas fa-bullseye"></i> Target planning for LAWA and BINHI</span>
              </div>
              <?php if ($userType === 'admin'): ?>
                <button type="button" class="btn btn-primary" id="addTargetBtn">Add Target</button>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body">
            <?php if ($userType !== 'admin'): ?>
              <div class="viewer-note">
                <strong>Viewer mode:</strong> You can review baseline targets here, but only administrators can add, import, edit, or delete target rows.
              </div>
            <?php endif; ?>

            <?php if ($userType === 'admin'): ?>
              <div class="target-import-card">
                <div class="target-help">Import an Excel file with these exact headers: <strong>PROVINCE</strong>, <strong>MUNICIPALITY</strong>, <strong>BARANGAY</strong>, <strong>PUROK</strong>, <strong>PROJECT NAME</strong>, <strong>PROJECT CLASSIFICATION</strong>, <strong>LAWA TARGET</strong>, <strong>BINHI TARGET</strong>, <strong>CAPBUILD TARGET</strong>, <strong>COMMUNITY ACTION PLAN TARGET</strong>, <strong>TARGET PARTNER-BENEFICIARIES</strong>. Each purok must line up with its matching project name and classification. For multiple entries in one row, separate each column with <strong>||</strong> in the same order. <strong>Target Partner-Beneficiaries</strong> should be the barangay's beneficiary target.</div>
                <form action="import-project-targets.php" method="POST" enctype="multipart/form-data">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="file" name="targetFile" accept=".xls,.xlsx" class="form-control-file" required>
                  <button type="submit" class="btn btn-success">Import Excel</button>
                  <a class="btn btn-link" href="helpers/Program_Targets_Template.xlsx" download>Download Template</a>
                </form>
              </div>
            <?php endif; ?>

            <div class="table-container">
              <table id="project-targets-table" class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>Province</th>
                    <th>Municipality</th>
                    <th>Barangay</th>
                    <th>Purok</th>
                    <th>Project Name</th>
                    <th>Project Classification</th>
                    <th>LAWA Target</th>
                    <th>BINHI Target</th>
                    <th>CapBuild Target</th>
                    <th>Community Action Plan Target</th>
                    <th>Target Partner-Beneficiaries</th>
                    <th>Last Updated</th>
                    <th>Action</th>
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

<script src="<?php echo $base_url;?>kodus/plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo $base_url;?>kodus/dist/js/adminlte.min.js"></script>
<script>
$(function() {
    const isAdmin = <?= $userType === 'admin' ? 'true' : 'false' ?>;

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    function renderTargetRows(puroks, names, classifications) {
        const targetPuroks = Array.isArray(puroks) && puroks.length ? puroks : [''];
        const projectNames = Array.isArray(names) && names.length ? names : [''];
        const projectClasses = Array.isArray(classifications) && classifications.length ? classifications : [''];
        const count = Math.max(targetPuroks.length, projectNames.length, projectClasses.length, 1);
        const rows = [];

        for (let i = 0; i < count; i++) {
            rows.push(`
                <div class="target-item target-entry-item">
                    <input type="text" class="form-control target-purok-input" value="${escapeHtml(targetPuroks[i] || '')}" placeholder="Purok">
                    <input type="text" class="form-control project-name-input" value="${escapeHtml(projectNames[i] || '')}" placeholder="Project name">
                    <select class="custom-select project-classification-input">
                        <option value="">Classification</option>
                        <option value="LAWA" ${(projectClasses[i] || '') === 'LAWA' ? 'selected' : ''}>LAWA</option>
                        <option value="BINHI" ${(projectClasses[i] || '') === 'BINHI' ? 'selected' : ''}>BINHI</option>
                    </select>
                    <button type="button" class="btn btn-success btn-sm add-entry-btn">+</button>
                    <button type="button" class="btn btn-danger btn-sm remove-entry-btn">-</button>
                </div>
            `);
        }

        return rows.join('');
    }

    const table = $('#project-targets-table').DataTable({
        ajax: {
            url: 'fetch-project-targets.php',
            dataSrc: 'data'
        },
        columns: [
            { data: 'province' },
            { data: 'municipality' },
            { data: 'barangay' },
            { data: 'puroks_display', defaultContent: '' },
            { data: 'project_names_display', defaultContent: '' },
            { data: 'project_classifications_display', defaultContent: '' },
            { data: 'lawa_target' },
            { data: 'binhi_target' },
            { data: 'capbuild_target' },
            { data: 'community_action_plan_target' },
            { data: 'target_partner_beneficiaries' },
            { data: 'updated_at' },
            { data: 'action', orderable: false, searchable: false }
        ],
        responsive: true,
        autoWidth: false,
        order: [[0, 'asc'], [1, 'asc'], [2, 'asc']]
    });

    function getTargetRowData(trigger) {
        const currentRow = $(trigger).closest('tr');
        let row = table.row(currentRow).data();

        if (row) {
            return row;
        }

        if (currentRow.hasClass('child')) {
            row = table.row(currentRow.prev()).data();
        }

        return row || null;
    }

    function openTargetModal(row) {
        const target = row || {};
        Swal.fire({
            title: row ? 'Edit Baseline Target' : 'Add Baseline Target',
            width: 720,
            customClass: {
                popup: 'kodus-edit-popup'
            },
            html: `
                <div class="kodus-edit-shell">
                    <div class="kodus-edit-header">
                        <h3 class="kodus-edit-header-title">${row ? escapeHtml(target.barangay || 'Baseline Target') : 'New Baseline Target'}</h3>
                        <p class="kodus-edit-header-note">Set the baseline coverage, linked projects, and target beneficiary counts for this barangay.</p>
                    </div>

                    <div class="kodus-edit-section">
                        <h6 class="kodus-edit-section-title">Location</h6>
                        <div class="kodus-edit-grid">
                            <div class="kodus-edit-field">
                                <label>Province</label>
                                <input id="target-province" class="form-control" value="${escapeHtml(target.province || '')}">
                            </div>
                            <div class="kodus-edit-field">
                                <label>Municipality</label>
                                <input id="target-municipality" class="form-control" value="${escapeHtml(target.municipality || '')}">
                            </div>
                            <div class="kodus-edit-field">
                                <label>Barangay</label>
                                <input id="target-barangay" class="form-control" value="${escapeHtml(target.barangay || '')}">
                            </div>
                        </div>
                    </div>

                    <div class="kodus-edit-section">
                        <h6 class="kodus-edit-section-title">Coverage Entries</h6>
                        <div class="kodus-edit-field kodus-edit-field--full">
                            <label>Purok, Project Name, and Classification</label>
                            <div id="target-entry-list">${renderTargetRows(target.puroks || [], target.project_names || [], target.project_classifications || [])}</div>
                        </div>
                    </div>

                    <div class="kodus-edit-section">
                        <h6 class="kodus-edit-section-title">Target Counts</h6>
                        <div class="kodus-edit-grid kodus-edit-grid--compact">
                            <div class="kodus-edit-field">
                                <label>LAWA Target</label>
                                <input id="target-lawa" type="number" min="0" class="form-control" value="${escapeHtml(target.lawa_target || 0)}">
                            </div>
                            <div class="kodus-edit-field">
                                <label>BINHI Target</label>
                                <input id="target-binhi" type="number" min="0" class="form-control" value="${escapeHtml(target.binhi_target || 0)}">
                            </div>
                            <div class="kodus-edit-field">
                                <label>CapBuild Target</label>
                                <input id="target-capbuild" type="number" min="0" class="form-control" value="${escapeHtml(target.capbuild_target || 0)}">
                            </div>
                            <div class="kodus-edit-field">
                                <label>Community Action Plan Target</label>
                                <input id="target-community-action-plan" type="number" min="0" class="form-control" value="${escapeHtml(target.community_action_plan_target || 0)}">
                            </div>
                            <div class="kodus-edit-field">
                                <label>Total Target Partner-Beneficiaries</label>
                                <input id="target-total" type="number" min="0" class="form-control" value="${escapeHtml(target.target_partner_beneficiaries || 0)}">
                            </div>
                        </div>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: row ? '<i class="fas fa-save"></i>' : '<i class="fas fa-plus"></i>',
            didOpen: () => {
                $(document).off('click.targetModal');
                $(document).on('click.targetModal', '.add-entry-btn', function() {
                    $('#target-entry-list').append(renderTargetRows([''], [''], ['']));
                });
                $(document).on('click.targetModal', '.remove-entry-btn', function() {
                    const list = $('#target-entry-list');
                    if (list.find('.target-entry-item').length === 1) {
                        list.find('.target-purok-input').val('');
                        list.find('.project-name-input').val('');
                        list.find('.project-classification-input').val('');
                        return;
                    }
                    $(this).closest('.target-entry-item').remove();
                });
            },
            willClose: () => {
                $(document).off('click.targetModal');
            },
            preConfirm: () => {
                const entries = [];
                $('#target-entry-list .target-entry-item').each(function() {
                    const purok = $(this).find('.target-purok-input').val().trim();
                    const name = $(this).find('.project-name-input').val().trim();
                    const classification = $(this).find('.project-classification-input').val();
                    if (purok !== '' || name !== '' || classification !== '') {
                        entries.push({ purok, name, classification });
                    }
                });

                return $.ajax({
                    url: 'save-project-target.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        id: target.id || '',
                        province: $('#target-province').val().trim(),
                        municipality: $('#target-municipality').val().trim(),
                        barangay: $('#target-barangay').val().trim(),
                        entries: entries,
                        lawa_target: $('#target-lawa').val(),
                        binhi_target: $('#target-binhi').val(),
                        capbuild_target: $('#target-capbuild').val(),
                        community_action_plan_target: $('#target-community-action-plan').val(),
                        target_partner_beneficiaries: $('#target-total').val(),
                        csrf_token: window.KODUS_CSRF_TOKEN
                    }
                }).then(function(response) {
                    if (!response.success) {
                        throw new Error(response.message || 'Could not save target.');
                    }
                    return response;
                }).catch(function(error) {
                    const message = error.responseJSON?.message || error.message || 'Could not save target.';
                    Swal.showValidationMessage(message);
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Saved',
                    text: result.value?.message || 'Baseline target saved successfully.',
                    timer: 1400,
                    showConfirmButton: false
                });
                table.ajax.reload(null, false);
            }
        });
    }

    if (isAdmin) {
        $('#addTargetBtn').on('click', function() {
            openTargetModal(null);
        });

        $('#project-targets-table tbody').on('click', '.edit-target-btn', function() {
            const row = getTargetRowData(this);
            if (!row) {
                Swal.fire('Error', 'Could not load this target row.', 'error');
                return;
            }
            openTargetModal(row);
        });

        $('#project-targets-table tbody').on('click', '.delete-target-btn', function() {
            const row = getTargetRowData(this);
            if (!row) {
                Swal.fire('Error', 'Could not load this target row.', 'error');
                return;
            }
            Swal.fire({
                icon: 'warning',
                title: 'Delete Baseline Target',
                text: `Remove target for ${row.barangay}, ${row.municipality}?`,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-trash"></i>',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                $.ajax({
                    url: 'delete-project-target.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        id: row.id,
                        csrf_token: window.KODUS_CSRF_TOKEN
                    }
                }).done(function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted',
                        text: response.message || 'Baseline target deleted successfully.',
                        timer: 1400,
                        showConfirmButton: false
                    });
                    table.ajax.reload(null, false);
                }).fail(function(xhr) {
                    Swal.fire('Delete Failed', xhr.responseJSON?.message || 'Could not delete target.', 'error');
                });
            });
        });
    }

    <?php if ($importSuccess): ?>
    Swal.fire({
        icon: 'success',
        title: 'Import Complete',
        text: <?= json_encode($importSuccess) ?>
    });
    <?php endif; ?>

    <?php if ($importError): ?>
    Swal.fire({
        icon: 'error',
        title: 'Import Failed',
        text: <?= json_encode($importError) ?>
    });
    <?php endif; ?>
});
</script>
</body>
</html>
