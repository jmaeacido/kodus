<?php
include('../header.php');
include('../sidenav.php');

$userType = auth_current_user_type();
$canManageTargets = auth_can_manage_program_targets();
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
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
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
    .swal2-popup.kodus-edit-popup {
      width: min(980px, 96vw);
      padding: 1.25rem;
      border-radius: 24px;
    }
    .swal2-popup.kodus-edit-popup .swal2-html-container {
      margin: 0.75rem 0 0;
      padding: 0;
      overflow: visible;
    }
    .kodus-edit-shell {
      text-align: left;
      color: inherit;
    }
    .kodus-edit-header {
      padding: 1rem 1.1rem;
      margin-bottom: 1rem;
      border: 1px solid rgba(108, 117, 125, 0.22);
      border-radius: 18px;
      background: rgba(108, 117, 125, 0.06);
    }
    .kodus-edit-header-title {
      margin: 0;
      font-size: 1.15rem;
      font-weight: 700;
      line-height: 1.35;
    }
    .kodus-edit-header-note {
      margin: 0.45rem 0 0;
      line-height: 1.55;
      opacity: 0.82;
    }
    .kodus-edit-section {
      padding: 1rem 1.1rem;
      margin-bottom: 1rem;
      border: 1px solid rgba(108, 117, 125, 0.22);
      border-radius: 18px;
      background: rgba(108, 117, 125, 0.04);
    }
    .kodus-edit-section:last-child {
      margin-bottom: 0;
    }
    .kodus-edit-section-title {
      margin: 0 0 0.8rem;
      font-size: 0.92rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .kodus-edit-section-note {
      margin: 0 0 0.9rem;
      line-height: 1.5;
      opacity: 0.78;
    }
    .kodus-edit-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 14px;
      align-items: stretch;
    }
    .kodus-edit-grid--compact {
      grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    }
    .kodus-edit-field {
      display: flex;
      flex-direction: column;
      align-self: stretch;
    }
    .kodus-edit-field > label {
      font-size: 0.8rem;
      font-weight: 700;
      letter-spacing: 0.02em;
      margin-bottom: 6px;
      display: block;
    }
    #target-entry-list {
      display: grid;
      gap: 12px;
    }
    .target-entry-item {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 12px;
      padding: 0.95rem;
      border: 1px solid rgba(108, 117, 125, 0.24);
      border-radius: 16px;
      background: rgba(108, 117, 125, 0.06);
      align-items: start;
    }
    .target-entry-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }
    .target-entry-field {
      display: flex;
      flex-direction: column;
      min-width: 0;
    }
    .target-entry-field--wide {
      grid-column: span 2;
    }
    .target-entry-field label {
      margin-bottom: 6px;
      font-size: 0.76rem;
      font-weight: 700;
      letter-spacing: 0.03em;
      text-transform: uppercase;
      opacity: 0.8;
    }
    .target-entry-actions {
      display: flex;
      flex-direction: column;
      gap: 8px;
      min-width: 112px;
    }
    .target-entry-actions .btn {
      width: 100%;
      white-space: nowrap;
    }
    .target-fertilizer-followup,
    .aquatic-resource-followup {
      display: none;
      grid-column: span 2;
      border: 1px solid rgba(40, 167, 69, 0.22);
      border-radius: 14px;
      padding: .9rem 1rem;
      background: rgba(40, 167, 69, 0.08);
    }
    .aquatic-resource-followup {
      border-color: rgba(23, 162, 184, 0.24);
      background: rgba(23, 162, 184, 0.08);
    }
    .target-fertilizer-followup.is-visible,
    .aquatic-resource-followup.is-visible {
      display: block;
    }
    .target-fertilizer-question,
    .aquatic-resource-question {
      font-size: .82rem;
      font-weight: 700;
      margin-bottom: .55rem;
      text-transform: uppercase;
      letter-spacing: .03em;
    }
    .target-fertilizer-options,
    .aquatic-resource-options {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      margin-bottom: .85rem;
    }
    .target-fertilizer-grid,
    .aquatic-resource-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 12px;
    }
    .swal2-popup.kodus-edit-popup .form-control,
    .swal2-popup.kodus-edit-popup .custom-select {
      min-height: calc(2.25rem + 2px);
    }
    @media (max-width: 767.98px) {
      .swal2-popup.kodus-edit-popup {
        width: min(96vw, 96vw);
        padding: 1rem;
      }
      .kodus-edit-section,
      .kodus-edit-header {
        padding: 0.9rem;
      }
      .target-entry-item {
        grid-template-columns: 1fr;
      }
      .target-entry-grid {
        grid-template-columns: 1fr;
      }
      .target-entry-field--wide {
        grid-column: auto;
      }
      .target-entry-actions {
        flex-direction: row;
        min-width: 0;
      }
    }
    .table-container {
      width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    #project-targets-table {
      min-width: 1400px;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
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
              <?php if ($canManageTargets): ?>
                <button type="button" class="btn btn-primary" id="addTargetBtn" data-no-loader="true">Add Target</button>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body">
            <?php if (!$canManageTargets): ?>
              <div class="viewer-note">
                <strong>Viewer mode:</strong> You can review baseline targets here, but only administrators and implementation editors can add, import, edit, or delete target rows.
              </div>
            <?php endif; ?>

            <?php if ($canManageTargets): ?>
              <div class="target-import-card">
                <div class="target-help">Import an Excel file with these exact headers: <strong>PROVINCE</strong>, <strong>MUNICIPALITY</strong>, <strong>BARANGAY</strong>, <strong>PUROK</strong>, <strong>PROJECT NAME</strong>, <strong>PROJECT TYPE</strong>, <strong>PROJECT CLASSIFICATION</strong>, <strong>LAWA TARGET</strong>, <strong>BINHI TARGET</strong>, <strong>CAPBUILD TARGET</strong>, <strong>COMMUNITY ACTION PLAN TARGET</strong>, <strong>TARGET PARTNER-BENEFICIARIES</strong>. Each purok must line up with its matching project name, project type, and classification. For multiple entries in one row, separate each column with <strong>||</strong> in the same order. <strong>Target Partner-Beneficiaries</strong> should be the barangay's beneficiary target.</div>
                <form action="import-project-targets.php" method="POST" enctype="multipart/form-data">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="file" name="targetFile" accept=".xls,.xlsx" class="form-control-file" required>
                  <button type="submit" class="btn btn-success">Import Excel</button>
                  <a class="btn btn-link" href="helpers/Program_Targets_Template.xlsx" download>Download Template</a>
                </form>
              </div>
            <?php endif; ?>

            <div class="table-container kodus-table-scroll" style="--kodus-table-min-width: 1400px;">
              <table id="project-targets-table" class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>Action</th>
                    <th>Province</th>
                    <th>Municipality</th>
                    <th>Barangay</th>
                    <th>Purok</th>
                    <th>Project Name</th>
                    <th>Project Type</th>
                    <th>Project Classification</th>
                    <th>LAWA Target</th>
                    <th>BINHI Target</th>
                    <th>CapBuild Target</th>
                    <th>Community Action Plan Target</th>
                    <th>Target Partner-Beneficiaries</th>
                    <th>Last Updated</th>
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

<script src="<?php echo $app_root; ?>plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="../plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>
<script>
$(function() {
    const canManageTargets = <?= $canManageTargets ? 'true' : 'false' ?>;
    const textRenderer = $.fn.dataTable.render.text();
    const COVERAGE_TYPE_OPTIONS = {
        LAWA: [
            'Rehabilitation of Water System Level I (Manual Pump)',
            'Rehabilitation of Water System Level II (Pipe Laying)',
            'Construction of Small Farm Reservoir',
            'Rehabilitation of Water System',
            'Diversification of Water Supply',
            'Rehabilitation of Fishpond',
            'Installation of Shallow Tube Wells (STWs)',
            'Construction of Water Reservoir',
            'Rehabilitation of Small Farm Reservoir',
            'Installation of Pitcher Pump (Shallow Well)',
            'Installation of Jetmatic Pump (Deep Well)',
            'Rehabilitation of Water Supply'
        ],
        BINHI: [
            'Vegetable',
            'Crops (Banana, Corn, Rice)',
            'Disaster Resilient Crops (Taro, Sweet Potato)',
            'Fruit-Bearing Trees',
            'Tilapia (Fish pond)'
        ]
    };
    const AQUATIC_RESOURCE_TYPE_OPTIONS = ['CRABS', 'FISH'];
    const AQUATIC_RESOURCE_PROJECT_TYPES = [
        'Construction of Small Farm Reservoir',
        'Rehabilitation of Fishpond',
        'Rehabilitation of Small Farm Reservoir'
    ];

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    function renderLocationOptions(items, selectedValue, placeholder) {
        const normalizedSelected = String(selectedValue || '').trim();
        const options = Array.isArray(items) ? items : [];
        const hasSelected = normalizedSelected !== '' && options.some((item) => String(item.value || '') === normalizedSelected);
        return [
            `<option value="">${escapeHtml(placeholder)}</option>`,
            hasSelected ? '' : (normalizedSelected !== '' ? `<option value="${escapeHtml(normalizedSelected)}" selected>${escapeHtml(normalizedSelected)}</option>` : ''),
            options.map((item) => {
                const value = String(item.value || '');
                const label = String(item.label || value);
                return `<option value="${escapeHtml(value)}" ${value === normalizedSelected ? 'selected' : ''}>${escapeHtml(label)}</option>`;
            }).join('')
        ].join('');
    }

    function setLocationSelectLoading($select, placeholder) {
        $select.prop('disabled', true).html(`<option value="">${escapeHtml(placeholder)}</option>`);
    }

    function loadLocationOptions(type, parent = '', province = '') {
        const request = { type, parent };
        if (province) {
            request.province = province;
        }
        return $.getJSON('location-options.php', request).then(function(response) {
            if (!response || response.success === false) {
                throw new Error(response?.message || 'Could not load location options.');
            }
            return Array.isArray(response.items) ? response.items : [];
        });
    }

    function populateProvinceSelect(selectedProvince = '') {
        const $province = $('#target-province');
        setLocationSelectLoading($province, 'Loading provinces...');
        return loadLocationOptions('provinces').then(function(items) {
            $province.html(renderLocationOptions(items, selectedProvince, 'Select province')).prop('disabled', false);
        }).catch(function(error) {
            $province.html(renderLocationOptions([], selectedProvince, 'Select province')).prop('disabled', false);
            Swal.showValidationMessage(error.message || 'Could not load provinces.');
        });
    }

    function populateMunicipalitySelect(province, selectedMunicipality = '') {
        const $municipality = $('#target-municipality');
        const $barangay = $('#target-barangay');
        $barangay.html('<option value="">Select barangay</option>').prop('disabled', true);
        if (!province) {
            $municipality.html('<option value="">Select province first</option>').prop('disabled', true);
            return $.Deferred().resolve().promise();
        }
        setLocationSelectLoading($municipality, 'Loading municipalities...');
        return loadLocationOptions('municipalities', province).then(function(items) {
            $municipality.html(renderLocationOptions(items, selectedMunicipality, 'Select municipality')).prop('disabled', false);
        }).catch(function(error) {
            $municipality.html(renderLocationOptions([], selectedMunicipality, 'Select municipality')).prop('disabled', false);
            Swal.showValidationMessage(error.message || 'Could not load municipalities.');
        });
    }

    function populateBarangaySelect(municipality, selectedBarangay = '', province = '') {
        const $barangay = $('#target-barangay');
        if (!municipality) {
            $barangay.html('<option value="">Select municipality first</option>').prop('disabled', true);
            return $.Deferred().resolve().promise();
        }
        setLocationSelectLoading($barangay, 'Loading barangays...');
        return loadLocationOptions('barangays', municipality, province || $('#target-province').val()).then(function(items) {
            $barangay.html(renderLocationOptions(items, selectedBarangay, 'Select barangay')).prop('disabled', false);
        }).catch(function(error) {
            $barangay.html(renderLocationOptions([], selectedBarangay, 'Select barangay')).prop('disabled', false);
            Swal.showValidationMessage(error.message || 'Could not load barangays.');
        });
    }

    function initializeLocationDropdowns(target) {
        const selectedProvince = String(target?.province || '').trim();
        const selectedMunicipality = String(target?.municipality || '').trim();
        const selectedBarangay = String(target?.barangay || '').trim();

        $('#target-municipality').html('<option value="">Select province first</option>').prop('disabled', true);
        $('#target-barangay').html('<option value="">Select municipality first</option>').prop('disabled', true);

        populateProvinceSelect(selectedProvince).then(function() {
            return populateMunicipalitySelect($('#target-province').val(), selectedMunicipality);
        }).then(function() {
            return populateBarangaySelect($('#target-municipality').val(), selectedBarangay, $('#target-province').val());
        });
    }

    function registerProjectTypeOption(classification, typeValue) {
        const normalizedClassification = String(classification || '').trim().toUpperCase();
        const normalizedTypeValue = String(typeValue || '').trim();
        if (!['LAWA', 'BINHI'].includes(normalizedClassification) || normalizedTypeValue === '') {
            return;
        }

        if (!Array.isArray(COVERAGE_TYPE_OPTIONS[normalizedClassification])) {
            COVERAGE_TYPE_OPTIONS[normalizedClassification] = [];
        }

        if (!COVERAGE_TYPE_OPTIONS[normalizedClassification].includes(normalizedTypeValue)) {
            COVERAGE_TYPE_OPTIONS[normalizedClassification].push(normalizedTypeValue);
        }
    }

    function renderProjectTypeOptions(classification, selectedType) {
        const normalizedClassification = String(classification || '').trim().toUpperCase();
        const normalizedSelectedType = String(selectedType || '').trim();
        const options = COVERAGE_TYPE_OPTIONS[normalizedClassification] || [];
        const hasPresetMatch = normalizedSelectedType !== '' && options.includes(normalizedSelectedType);
        const optionHtml = options.map((option) => `
            <option value="${escapeHtml(option)}" ${normalizedSelectedType === option ? 'selected' : ''}>${escapeHtml(option)}</option>
        `).join('');

        return `
            <option value="">Project type</option>
            ${optionHtml}
            <option value="__custom__" ${normalizedSelectedType !== '' && !hasPresetMatch ? 'selected' : ''}>Custom type</option>
        `;
    }

    function renderProjectTypeField(classification, selectedType) {
        const normalizedClassification = String(classification || '').trim().toUpperCase();
        const normalizedSelectedType = String(selectedType || '').trim();
        const options = COVERAGE_TYPE_OPTIONS[normalizedClassification] || [];
        const isCustom = normalizedSelectedType !== '' && !options.includes(normalizedSelectedType);

        return `
            <div class="project-type-field">
                <select class="custom-select project-type-input">
                    ${renderProjectTypeOptions(normalizedClassification, normalizedSelectedType)}
                </select>
                <input type="text" class="form-control project-type-custom-input mt-1 ${isCustom ? '' : 'd-none'}" value="${escapeHtml(isCustom ? normalizedSelectedType : '')}" placeholder="Enter custom type">
            </div>
        `;
    }

    function getProjectTypeValue($row) {
        const selectedType = String($row.find('.project-type-input').val() || '').trim();
        if (selectedType === '__custom__') {
            return String($row.find('.project-type-custom-input').val() || '').trim();
        }

        return selectedType;
    }

    function syncProjectTypeField($row, selectedType = '') {
        const $customInput = $row.find('.project-type-custom-input');
        if (String($row.find('.project-type-input').val() || '').trim() === '__custom__') {
            $customInput.removeClass('d-none');
            if (selectedType && !$customInput.val()) {
                $customInput.val(selectedType);
            }
            return;
        }

        $customInput.addClass('d-none').val('');
    }

    function requiresAquaticResourcePrompt(classification, projectType) {
        return String(classification || '').trim().toUpperCase() === 'LAWA'
            && AQUATIC_RESOURCE_PROJECT_TYPES.includes(String(projectType || '').trim());
    }

    function shouldShowAquaticResourceFields($row) {
        return String($row.find('.aquatic-resource-enabled-input').val() || '').trim() === '1';
    }

    function requiresBinhiTargetQuantity(classification, projectType) {
        return String(classification || '').trim().toUpperCase() === 'BINHI'
            && String(projectType || '').trim() !== '';
    }

    function renderBinhiTargetQuantityField(selectedQuantity, isVisible) {
        return `
            <div class="binhi-target-quantity-field ${isVisible ? '' : 'd-none'}">
                <label>BINHI Target Quantity</label>
                <input type="number" min="0" class="form-control binhi-target-quantity-input" value="${escapeHtml(selectedQuantity || '')}" placeholder="Enter target quantity">
            </div>
        `;
    }

    function syncBinhiTargetQuantityField($row) {
        const classification = String($row.find('.project-classification-input').val() || '').trim().toUpperCase();
        const projectType = getProjectTypeValue($row);
        const shouldShow = requiresBinhiTargetQuantity(classification, projectType);
        const $field = $row.find('.binhi-target-quantity-field');
        $field.toggleClass('d-none', !shouldShow);

        if (!shouldShow) {
            $row.find('.binhi-target-quantity-input').val('');
        }
    }

    function renderAquaticResourceOptions(selectedResource) {
        const normalizedSelectedResource = String(selectedResource || '').trim().toUpperCase();
        const isCustom = normalizedSelectedResource !== '' && !AQUATIC_RESOURCE_TYPE_OPTIONS.includes(normalizedSelectedResource);
        const options = AQUATIC_RESOURCE_TYPE_OPTIONS.map((option) => `
            <option value="${escapeHtml(option)}" ${normalizedSelectedResource === option ? 'selected' : ''}>${escapeHtml(option)}</option>
        `).join('');

        return `
            <option value="" disabled ${normalizedSelectedResource === '' ? 'selected' : ''}>Aquatic resource</option>
            ${options}
            <option value="__custom__" ${isCustom ? 'selected' : ''}>Custom input</option>
        `;
    }

    function renderAquaticResourceField(classification, projectType, selectedResource, selectedQuantity, rowKey) {
        const normalizedSelectedResource = String(selectedResource || '').trim().toUpperCase();
        const customValue = normalizedSelectedResource !== '' && !AQUATIC_RESOURCE_TYPE_OPTIONS.includes(normalizedSelectedResource)
            ? selectedResource
            : '';
        const hasExistingValues = normalizedSelectedResource !== '' || String(selectedQuantity || '').trim() !== '';
        const isVisible = requiresAquaticResourcePrompt(classification, projectType) || hasExistingValues;
        const isEnabled = hasExistingValues;

        return `
            <div class="aquatic-resource-followup ${isVisible ? 'is-visible' : ''}">
                <input type="hidden" class="aquatic-resource-enabled-input" value="${isEnabled ? '1' : ''}">
                <div class="aquatic-resource-question">Does this project include aquatic resources?</div>
                <div class="aquatic-resource-options">
                    <label><input type="radio" class="aquatic-resource-enabled-choice" name="aquatic-resource-${escapeHtml(rowKey)}" value="1" ${isEnabled ? 'checked' : ''}> Yes</label>
                    <label><input type="radio" class="aquatic-resource-enabled-choice" name="aquatic-resource-${escapeHtml(rowKey)}" value="0"> No</label>
                </div>
                <div class="aquatic-resource-grid aquatic-resource-field ${isEnabled ? '' : 'd-none'}">
                    <div class="target-entry-field">
                        <label>Aquatic Resources</label>
                        <select class="custom-select aquatic-resource-input">
                            ${renderAquaticResourceOptions(normalizedSelectedResource)}
                        </select>
                        <input type="text" class="form-control aquatic-resource-custom-input mt-1" value="${escapeHtml(customValue)}" placeholder="Custom aquatic resource (optional)">
                    </div>
                    <div class="target-entry-field">
                        <label>Quantity</label>
                        <input type="number" min="0" class="form-control aquatic-resource-quantity-input" value="${escapeHtml(selectedQuantity || '')}" placeholder="Enter quantity">
                    </div>
                </div>
            </div>
        `;
    }

    function registerAquaticResourceOption(resourceValue) {
        const normalizedResourceValue = String(resourceValue || '').trim().toUpperCase();
        if (normalizedResourceValue === '') {
            return;
        }

        if (!AQUATIC_RESOURCE_TYPE_OPTIONS.includes(normalizedResourceValue)) {
            AQUATIC_RESOURCE_TYPE_OPTIONS.push(normalizedResourceValue);
        }
    }

    function getAquaticResourceValue($row) {
        const selectedResource = String($row.find('.aquatic-resource-input').val() || '').trim();
        if (selectedResource === '__custom__') {
            return String($row.find('.aquatic-resource-custom-input').val() || '').trim();
        }

        const customResource = String($row.find('.aquatic-resource-custom-input').val() || '').trim();
        if (customResource !== '') {
            return customResource;
        }

        return selectedResource;
    }

    function syncAquaticResourceField($row) {
        const classification = String($row.find('.project-classification-input').val() || '').trim().toUpperCase();
        const projectType = getProjectTypeValue($row);
        const shouldPrompt = requiresAquaticResourcePrompt(classification, projectType);
        const hasExistingValues = getAquaticResourceValue($row) !== '' || String($row.find('.aquatic-resource-quantity-input').val() || '').trim() !== '';
        const shouldShowFollowup = shouldPrompt || hasExistingValues;
        const shouldShowFields = shouldShowAquaticResourceFields($row);
        const $followup = $row.find('.aquatic-resource-followup');
        const $field = $row.find('.aquatic-resource-field');
        const $customInput = $row.find('.aquatic-resource-custom-input');

        $followup.toggleClass('is-visible', shouldShowFollowup);
        $field.toggleClass('d-none', !shouldShowFields);
        if (!shouldShowFollowup) {
            $row.find('.aquatic-resource-enabled-input').val('');
            $row.find('.aquatic-resource-enabled-choice').prop('checked', false);
            $row.find('.aquatic-resource-input').val('');
            $customInput.val('');
            $row.find('.aquatic-resource-quantity-input').val('');
            return;
        }

        if (!shouldShowFields) {
            $row.find('.aquatic-resource-input').val('');
            $customInput.val('');
            $row.find('.aquatic-resource-quantity-input').val('');
            return;
        }

        if (String($row.find('.aquatic-resource-input').val() || '').trim() === '__custom__') {
            $customInput.removeClass('d-none');
        } else {
            $customInput.addClass('d-none').val('');
        }
    }

    function persistCustomAquaticResource($row) {
        const selectedResource = String($row.find('.aquatic-resource-input').val() || '').trim();
        const customValue = String($row.find('.aquatic-resource-custom-input').val() || '').trim();
        if (selectedResource !== '__custom__' || customValue === '') {
            return;
        }

        registerAquaticResourceOption(customValue);
        $row.find('.aquatic-resource-input').html(renderAquaticResourceOptions(customValue));
        $row.find('.aquatic-resource-input').val(String(customValue).trim().toUpperCase());
        $row.find('.aquatic-resource-custom-input').addClass('d-none').val('');
    }

    function isBinhiGardenProject(classification, projectName) {
        const normalizedClassification = String(classification || '').trim().toUpperCase();
        const normalizedName = String(projectName || '').trim().toLowerCase();
        return normalizedClassification === 'BINHI' && /(garden|gardening|gulayan|backyard\s+garden|communal\s+garden|school\s+garden|container\s+garden|vegetable\s+garden|herbal\s+garden|orchard|nursery)/.test(normalizedName);
    }

    function renderTargetFertilizerFollowup(classification, projectName, enabledFlag, ohnTarget, concoctionTarget, vermicompostTarget, rowKey) {
        const isVisible = isBinhiGardenProject(classification, projectName) || String(enabledFlag || '').trim() !== '' || String(ohnTarget || '').trim() !== '' || String(concoctionTarget || '').trim() !== '' || String(vermicompostTarget || '').trim() !== '';
        const isEnabled = String(enabledFlag || '').trim() === '1';

        return `
            <div class="target-fertilizer-followup ${isVisible ? 'is-visible' : ''}">
                <input type="hidden" class="target-fertilizer-enabled-input" value="${escapeHtml(String(enabledFlag || '').trim())}">
                <div class="target-fertilizer-question">Does this project produce/reproduce Fertilizers?</div>
                <div class="target-fertilizer-options">
                    <label><input type="radio" class="target-fertilizer-enabled-choice" name="target-fertilizer-${escapeHtml(rowKey)}" value="1" ${isEnabled ? 'checked' : ''}> Yes</label>
                    <label><input type="radio" class="target-fertilizer-enabled-choice" name="target-fertilizer-${escapeHtml(rowKey)}" value="0" ${String(enabledFlag || '').trim() === '0' ? 'checked' : ''}> No</label>
                </div>
                <div class="target-fertilizer-grid target-fertilizer-fields ${isEnabled ? '' : 'd-none'}">
                    <div>
                        <label>Oriental Herbal Nutrients Target (liters)</label>
                        <input type="number" min="0" step="0.01" class="form-control target-fertilizer-ohn-input" value="${escapeHtml(String(ohnTarget || ''))}">
                    </div>
                    <div>
                        <label>Concoction/Vermitea Target (liters)</label>
                        <input type="number" min="0" step="0.01" class="form-control target-fertilizer-concoction-input" value="${escapeHtml(String(concoctionTarget || ''))}">
                    </div>
                    <div>
                        <label>Vermicompost/Vermicast Target (kg)</label>
                        <input type="number" min="0" step="0.01" class="form-control target-fertilizer-vermicompost-input" value="${escapeHtml(String(vermicompostTarget || ''))}">
                    </div>
                </div>
            </div>
        `;
    }

    function syncTargetFertilizerFollowup($row) {
        const classification = String($row.find('.project-classification-input').val() || '').trim().toUpperCase();
        const projectName = String($row.find('.project-name-input').val() || '').trim();
        const shouldShow = isBinhiGardenProject(classification, projectName);
        const $followup = $row.find('.target-fertilizer-followup');
        const isEnabled = String($row.find('.target-fertilizer-enabled-input').val() || '').trim() === '1';

        $followup.toggleClass('is-visible', shouldShow);
        if (!shouldShow) {
            $row.find('.target-fertilizer-enabled-input').val('');
            $row.find('.target-fertilizer-enabled-choice').prop('checked', false);
            $row.find('.target-fertilizer-fields').addClass('d-none');
            $row.find('.target-fertilizer-ohn-input, .target-fertilizer-concoction-input, .target-fertilizer-vermicompost-input').val('');
            return;
        }

        $row.find('.target-fertilizer-fields').toggleClass('d-none', !isEnabled);
    }

    function normalizeCoordinateInputValue(value) {
        return String(value || '').trim().replace(/\s+/g, '');
    }

    function generateProjectRowId(prefix = 'pt') {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return `${prefix}-${window.crypto.randomUUID()}`;
        }

        return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
    }

    function reindexTargetEntries() {
        $('#target-entry-list .target-entry-item').each(function(index) {
            $(this).attr('data-entry-index', index);
        });
    }

    function insertTargetEntryAfter($anchorRow, classification = '') {
        const html = renderTargetRows([''], [''], [''], [''], [''], [''], [classification], [''], [''], [''], [''], [''], [''], [''], ['']);
        const $newRow = $(html);
        $anchorRow.after($newRow);
        reindexTargetEntries();
        const $insertedRow = $anchorRow.next('.target-entry-item');
        if ($insertedRow.length) {
            syncProjectTypeField($insertedRow);
            syncTargetFertilizerFollowup($insertedRow);
            syncBinhiTargetQuantityField($insertedRow);
            syncAquaticResourceField($insertedRow);
        }
        return $insertedRow;
    }

    function renderTargetRows(rowIds, puroks, names, types, classifications, binhiTargetQuantities, aquaticResources, aquaticResourceQuantities, fertilizerEnabledFlags, fertilizerOhnTargets, fertilizerConcoctionTargets, fertilizerVermicompostTargets) {
        const targetRowIds = Array.isArray(rowIds) && rowIds.length ? rowIds : [''];
        const targetPuroks = Array.isArray(puroks) && puroks.length ? puroks : [''];
        const projectNames = Array.isArray(names) && names.length ? names : [''];
        const projectTypes = Array.isArray(types) && types.length ? types : [''];
        const projectClasses = Array.isArray(classifications) && classifications.length ? classifications : [''];
        const targetBinhiTargetQuantities = Array.isArray(binhiTargetQuantities) && binhiTargetQuantities.length ? binhiTargetQuantities : [''];
        const targetAquaticResources = Array.isArray(aquaticResources) && aquaticResources.length ? aquaticResources : [''];
        const targetAquaticResourceQuantities = Array.isArray(aquaticResourceQuantities) && aquaticResourceQuantities.length ? aquaticResourceQuantities : [''];
        const targetFertilizerEnabledFlags = Array.isArray(fertilizerEnabledFlags) && fertilizerEnabledFlags.length ? fertilizerEnabledFlags : [''];
        const targetFertilizerOhnTargets = Array.isArray(fertilizerOhnTargets) && fertilizerOhnTargets.length ? fertilizerOhnTargets : [''];
        const targetFertilizerConcoctionTargets = Array.isArray(fertilizerConcoctionTargets) && fertilizerConcoctionTargets.length ? fertilizerConcoctionTargets : [''];
        const targetFertilizerVermicompostTargets = Array.isArray(fertilizerVermicompostTargets) && fertilizerVermicompostTargets.length ? fertilizerVermicompostTargets : [''];
        const count = Math.max(targetRowIds.length, targetPuroks.length, projectNames.length, projectTypes.length, projectClasses.length, targetBinhiTargetQuantities.length, targetAquaticResources.length, targetAquaticResourceQuantities.length, targetFertilizerEnabledFlags.length, targetFertilizerOhnTargets.length, targetFertilizerConcoctionTargets.length, targetFertilizerVermicompostTargets.length, 1);
        const rows = [];

        for (let i = 0; i < count; i++) {
            const rowKey = `target-${i}-${Math.random().toString(36).slice(2, 8)}`;
            const targetRowId = String(targetRowIds[i] || '').trim() || generateProjectRowId();
            rows.push(`
                <div class="target-entry-item" data-entry-index="${i}">
                    <input type="hidden" class="target-row-id-input" value="${escapeHtml(targetRowId)}">
                    <div class="target-entry-grid">
                        <div class="target-entry-field">
                            <label>Purok</label>
                            <input type="text" class="form-control target-purok-input" value="${escapeHtml(targetPuroks[i] || '')}" placeholder="Enter purok">
                        </div>
                        <div class="target-entry-field">
                            <label>Classification</label>
                            <select class="custom-select project-classification-input">
                                <option value="">Select classification</option>
                                <option value="LAWA" ${(projectClasses[i] || '') === 'LAWA' ? 'selected' : ''}>LAWA</option>
                                <option value="BINHI" ${(projectClasses[i] || '') === 'BINHI' ? 'selected' : ''}>BINHI</option>
                            </select>
                        </div>
                        <div class="target-entry-field target-entry-field--wide">
                            <label>Project Name</label>
                            <input type="text" class="form-control project-name-input" value="${escapeHtml(projectNames[i] || '')}" placeholder="Enter project name">
                        </div>
                        <div class="target-entry-field target-entry-field--wide">
                            <label>Project Type</label>
                            ${renderProjectTypeField(projectClasses[i] || '', projectTypes[i] || '')}
                        </div>
                        ${renderTargetFertilizerFollowup(
                            projectClasses[i] || '',
                            projectNames[i] || '',
                            targetFertilizerEnabledFlags[i] || '',
                            targetFertilizerOhnTargets[i] || '',
                            targetFertilizerConcoctionTargets[i] || '',
                            targetFertilizerVermicompostTargets[i] || '',
                            rowKey
                        )}
                        <div class="target-entry-field target-entry-field--wide">
                            ${renderBinhiTargetQuantityField(
                                targetBinhiTargetQuantities[i] || '',
                                requiresBinhiTargetQuantity(projectClasses[i] || '', projectTypes[i] || '')
                            )}
                        </div>
                        <div class="target-entry-field target-entry-field--wide">
                            ${renderAquaticResourceField(
                                projectClasses[i] || '',
                                projectTypes[i] || '',
                                targetAquaticResources[i] || '',
                                targetAquaticResourceQuantities[i] || '',
                                rowKey
                            )}
                        </div>
                    </div>
                    <div class="target-entry-actions">
                        <button type="button" class="btn btn-outline-success btn-sm add-entry-btn">Add Row</button>
                        <button type="button" class="btn btn-outline-danger btn-sm remove-entry-btn">Remove</button>
                    </div>
                </div>
            `);
        }

        return rows.join('');
    }

    function syncClassificationTargets() {
        let lawaCount = 0;
        let binhiCount = 0;

        $('#target-entry-list .target-entry-item').each(function() {
            const classification = (($(this).find('.project-classification-input').val() || '').trim()).toUpperCase();
            if (classification === 'LAWA') {
                lawaCount += 1;
            } else if (classification === 'BINHI') {
                binhiCount += 1;
            }
        });

        $('#target-lawa').val(lawaCount);
        $('#target-binhi').val(binhiCount);
        syncBinhiTypeTargetTotals();
    }

    function syncBinhiTypeTargetTotals() {
        const totals = {
            vegetable: 0,
            crops: 0,
            disaster: 0,
            fruit: 0,
            tilapia: 0
        };

        $('#target-entry-list .target-entry-item').each(function() {
            const classification = (($(this).find('.project-classification-input').val() || '').trim()).toUpperCase();
            if (classification !== 'BINHI') {
                return;
            }

            const type = getProjectTypeValue($(this));
            const quantity = Math.max(0, parseInt($(this).find('.binhi-target-quantity-input').val(), 10) || 0);
            if (type === 'Vegetable') {
                totals.vegetable += quantity;
            } else if (type === 'Crops (Banana, Corn, Rice)') {
                totals.crops += quantity;
            } else if (type === 'Disaster Resilient Crops (Taro, Sweet Potato)') {
                totals.disaster += quantity;
            } else if (type === 'Fruit-Bearing Trees') {
                totals.fruit += quantity;
            } else if (type === 'Tilapia (Fish pond)') {
                totals.tilapia += quantity;
            }
        });

        $('#target-binhi-vegetable').val(totals.vegetable);
        $('#target-binhi-crops').val(totals.crops);
        $('#target-binhi-disaster-resilient-crops').val(totals.disaster);
        $('#target-binhi-fruit-bearing-trees').val(totals.fruit);
        $('#target-binhi-tilapia').val(totals.tilapia);
    }

    function trimClassificationRows(classification, removeCount) {
        if (removeCount <= 0) {
            return;
        }

        const matchingRows = $('#target-entry-list .target-entry-item').filter(function() {
            const currentClassification = (($(this).find('.project-classification-input').val() || '').trim()).toUpperCase();
            return currentClassification === classification;
        }).get().reverse();

        for (const rowElement of matchingRows) {
            if (removeCount <= 0) {
                break;
            }

            const $row = $(rowElement);
            const purok = ($row.find('.target-purok-input').val() || '').trim();
            const projectName = ($row.find('.project-name-input').val() || '').trim();
            const projectType = getProjectTypeValue($row);
            const list = $('#target-entry-list');

            if (purok === '' && projectName === '' && projectType === '' && list.find('.target-entry-item').length > 1) {
                $row.remove();
            } else {
                $row.find('.project-classification-input').val('');
            }

            removeCount -= 1;
        }
    }

    function syncCoverageRowsToTarget(classification, desiredCount) {
        const normalizedClassification = (classification || '').trim().toUpperCase();
        if (!['LAWA', 'BINHI'].includes(normalizedClassification)) {
            return;
        }

        const safeDesiredCount = Math.max(0, parseInt(desiredCount, 10) || 0);
        const currentCount = $('#target-entry-list .target-entry-item').filter(function() {
            const currentClassification = (($(this).find('.project-classification-input').val() || '').trim()).toUpperCase();
            return currentClassification === normalizedClassification;
        }).length;

        if (safeDesiredCount > currentCount) {
            const rowsToAdd = safeDesiredCount - currentCount;
            for (let i = 0; i < rowsToAdd; i++) {
                const $list = $('#target-entry-list');
                const $lastMatch = $list.find('.target-entry-item').filter(function() {
                    return String($(this).find('.project-classification-input').val() || '').trim().toUpperCase() === normalizedClassification;
                }).last();

                if ($lastMatch.length) {
                    insertTargetEntryAfter($lastMatch, normalizedClassification);
                } else if ($list.find('.target-entry-item').length) {
                    insertTargetEntryAfter($list.find('.target-entry-item').last(), normalizedClassification);
                } else {
                    $list.append(renderTargetRows([''], [''], [''], [''], [''], [''], [normalizedClassification], [''], [''], [''], [''], [''], [''], [''], ['']));
                    reindexTargetEntries();
                }
            }
        } else if (safeDesiredCount < currentCount) {
            trimClassificationRows(normalizedClassification, currentCount - safeDesiredCount);
        }

        syncClassificationTargets();
    }

    function persistCustomProjectType($row) {
        const classification = String($row.find('.project-classification-input').val() || '').trim().toUpperCase();
        const customValue = String($row.find('.project-type-custom-input').val() || '').trim();
        if (!['LAWA', 'BINHI'].includes(classification) || customValue === '') {
            return;
        }

        registerProjectTypeOption(classification, customValue);
        $row.find('.project-type-field').replaceWith(renderProjectTypeField(classification, customValue));
        syncProjectTypeField($row, customValue);
        syncBinhiTargetQuantityField($row);
        syncAquaticResourceField($row);
    }

    function promptAquaticResourceDecision($row) {
        const classification = String($row.find('.project-classification-input').val() || '').trim().toUpperCase();
        const projectType = getProjectTypeValue($row);
        if (!requiresAquaticResourcePrompt(classification, projectType)) {
            syncAquaticResourceField($row);
            return;
        }

        const currentDecision = String($row.find('.aquatic-resource-enabled-input').val() || '').trim();
        const hasExistingValues = getAquaticResourceValue($row) !== '' || String($row.find('.aquatic-resource-quantity-input').val() || '').trim() !== '';
        if (currentDecision !== '') {
            syncAquaticResourceField($row);
            return;
        }

        if (hasExistingValues) {
            $row.find('.aquatic-resource-enabled-input').val('1');
            $row.find('.aquatic-resource-enabled-choice[value="1"]').prop('checked', true);
            syncAquaticResourceField($row);
            return;
        }

        syncAquaticResourceField($row);
    }

    const table = $('#project-targets-table').DataTable({
        ajax: {
            url: 'fetch-project-targets.php',
            dataSrc: 'data'
        },
        columns: [
            { data: 'action', orderable: false, searchable: false },
            { data: 'province', render: textRenderer },
            { data: 'municipality', render: textRenderer },
            { data: 'barangay', render: textRenderer },
            { data: 'puroks_display', defaultContent: '', render: textRenderer },
            { data: 'project_names_display', defaultContent: '', render: textRenderer },
            { data: 'project_types_display', defaultContent: '', render: textRenderer },
            { data: 'project_classifications_display', defaultContent: '', render: textRenderer },
            { data: 'lawa_target' },
            { data: 'binhi_target' },
            { data: 'capbuild_target' },
            { data: 'community_action_plan_target' },
            { data: 'target_partner_beneficiaries' },
            { data: 'updated_at', render: textRenderer }
        ],
        responsive: false,
        scrollX: true,
        autoWidth: false,
        order: [[1, 'asc'], [2, 'asc'], [3, 'asc']]
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

    const DEFAULT_CAPBUILD_TARGET = 2;
    const DEFAULT_COMMUNITY_ACTION_PLAN_TARGET = 1;

    function openTargetModal(row) {
        const target = row || {};
        const capbuildTargetValue = target.capbuild_target ?? DEFAULT_CAPBUILD_TARGET;
        const communityActionPlanTargetValue = target.community_action_plan_target ?? DEFAULT_COMMUNITY_ACTION_PLAN_TARGET;
        Swal.fire({
            title: row ? 'Edit Baseline Target' : 'Add Baseline Target',
            width: 980,
            customClass: {
                popup: 'kodus-edit-popup'
            },
            html: `
                <div class="kodus-edit-shell">
                    <div class="kodus-edit-header">
                        <h3 class="kodus-edit-header-title">${row ? escapeHtml(target.barangay || 'Baseline Target') : 'New Baseline Target'}</h3>
                        <p class="kodus-edit-header-note">Set the baseline coverage, project names, project types, and target beneficiary counts for this barangay.</p>
                    </div>

                    <div class="kodus-edit-section">
                        <h6 class="kodus-edit-section-title">Location</h6>
                        <div class="kodus-edit-grid">
                            <div class="kodus-edit-field">
                                <label>Province</label>
                                <select id="target-province" class="custom-select" required>
                                    <option value="">Loading provinces...</option>
                                </select>
                            </div>
                            <div class="kodus-edit-field">
                                <label>Municipality</label>
                                <select id="target-municipality" class="custom-select" required disabled>
                                    <option value="">Select province first</option>
                                </select>
                            </div>
                            <div class="kodus-edit-field">
                                <label>Barangay</label>
                                <select id="target-barangay" class="custom-select" required disabled>
                                    <option value="">Select municipality first</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="kodus-edit-section">
                        <h6 class="kodus-edit-section-title">Coverage Entries</h6>
                        <p class="kodus-edit-section-note">Each entry should match one purok with its project name, classification, and project type. Use separate rows so the baseline list stays easy to review.</p>
                        <div class="kodus-edit-field kodus-edit-field--full">
                            <label>Purok, Project Name, Project Type, and Classification</label>
                            <div id="target-entry-list">${renderTargetRows(target.project_row_ids || [], target.puroks || [], target.project_names || [], target.project_types || [], target.project_classifications || [], target.binhi_target_quantities || [], target.aquatic_resources || [], target.aquatic_resource_quantities || [], target.fertilizer_enabled_flags || [], target.fertilizer_ohn_targets || [], target.fertilizer_concoction_targets || [], target.fertilizer_vermicompost_targets || [])}</div>
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
                                <input id="target-capbuild" type="number" min="0" class="form-control" value="${escapeHtml(capbuildTargetValue)}">
                            </div>
                            <div class="kodus-edit-field">
                                <label>Community Action Plan Target</label>
                                <input id="target-community-action-plan" type="number" min="0" class="form-control" value="${escapeHtml(communityActionPlanTargetValue)}">
                            </div>
                            <div class="kodus-edit-field">
                                <label>Total Target Partner-Beneficiaries</label>
                                <input id="target-total" type="number" min="0" class="form-control" value="${escapeHtml(target.target_partner_beneficiaries || 0)}">
                            </div>
                        </div>
                    </div>

                    <div class="kodus-edit-section">
                        <h6 class="kodus-edit-section-title">BINHI Target Quantities</h6>
                        <p class="kodus-edit-section-note">These are explicit target quantities per BINHI type for the summary matrix. They do not replace the overall BINHI Target field above.</p>
                        <div class="kodus-edit-grid kodus-edit-grid--compact">
                            <div class="kodus-edit-field">
                                <label>Vegetable Target</label>
                                <input id="target-binhi-vegetable" type="number" min="0" class="form-control" value="${escapeHtml(target.binhi_vegetable_target || 0)}">
                            </div>
                            <div class="kodus-edit-field">
                                <label>Crops Target</label>
                                <input id="target-binhi-crops" type="number" min="0" class="form-control" value="${escapeHtml(target.binhi_crops_target || 0)}">
                            </div>
                            <div class="kodus-edit-field">
                                <label>Disaster Resilient Crops Target</label>
                                <input id="target-binhi-disaster-resilient-crops" type="number" min="0" class="form-control" value="${escapeHtml(target.binhi_disaster_resilient_crops_target || 0)}">
                            </div>
                            <div class="kodus-edit-field">
                                <label>Fruit-Bearing Trees Target</label>
                                <input id="target-binhi-fruit-bearing-trees" type="number" min="0" class="form-control" value="${escapeHtml(target.binhi_fruit_bearing_trees_target || 0)}">
                            </div>
                            <div class="kodus-edit-field">
                                <label>Tilapia Target</label>
                                <input id="target-binhi-tilapia" type="number" min="0" class="form-control" value="${escapeHtml(target.binhi_tilapia_target || 0)}">
                            </div>
                        </div>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: row ? '<i class="fas fa-save"></i>' : '<i class="fas fa-plus"></i>',
            didOpen: () => {
                $(document).off('.targetModal');
                initializeLocationDropdowns(target);
                $(document).on('change.targetModal', '#target-province', function() {
                    const province = String($(this).val() || '').trim();
                    $('#target-municipality').html('<option value="">Select province first</option>').prop('disabled', true);
                    $('#target-barangay').html('<option value="">Select municipality first</option>').prop('disabled', true);
                    populateMunicipalitySelect(province);
                });
                $(document).on('change.targetModal', '#target-municipality', function() {
                    const municipality = String($(this).val() || '').trim();
                    $('#target-barangay').html('<option value="">Select municipality first</option>').prop('disabled', true);
                    populateBarangaySelect(municipality, '', $('#target-province').val());
                });
                $(document).on('click.targetModal', '.add-entry-btn', function() {
                    const $currentRow = $(this).closest('.target-entry-item');
                    const classification = String($currentRow.find('.project-classification-input').val() || '').trim().toUpperCase();
                    insertTargetEntryAfter($currentRow, classification);
                    syncClassificationTargets();
                });
                $(document).on('click.targetModal', '.remove-entry-btn', function() {
                    const list = $('#target-entry-list');
                    if (list.find('.target-entry-item').length === 1) {
                        list.find('.target-purok-input').val('');
                        list.find('.project-name-input').val('');
                        list.find('.project-type-input').val('');
                        list.find('.project-type-custom-input').addClass('d-none').val('');
                        list.find('.project-classification-input').val('');
                        list.find('.target-fertilizer-enabled-input').val('');
                        list.find('.target-fertilizer-enabled-choice').prop('checked', false);
                        list.find('.target-fertilizer-fields').addClass('d-none');
                        list.find('.target-fertilizer-followup').removeClass('is-visible');
                        list.find('.target-fertilizer-ohn-input, .target-fertilizer-concoction-input, .target-fertilizer-vermicompost-input').val('');
                        list.find('.binhi-target-quantity-input').val('');
                        list.find('.binhi-target-quantity-field').addClass('d-none');
                        list.find('.aquatic-resource-input').val('');
                        list.find('.aquatic-resource-custom-input').val('');
                        list.find('.aquatic-resource-quantity-input').val('');
                        list.find('.aquatic-resource-field').addClass('d-none');
                        list.find('.aquatic-resource-enabled-input').val('');
                        list.find('.aquatic-resource-enabled-choice').prop('checked', false);
                        list.find('.aquatic-resource-followup').removeClass('is-visible');
                        syncClassificationTargets();
                        reindexTargetEntries();
                        return;
                    }
                    $(this).closest('.target-entry-item').remove();
                    reindexTargetEntries();
                    syncClassificationTargets();
                });
                $(document).on('change.targetModal', '.project-type-input', function() {
                    const $row = $(this).closest('.target-entry-item');
                    syncProjectTypeField($row);
                    syncBinhiTargetQuantityField($row);
                    promptAquaticResourceDecision($row);
                });
                $(document).on('blur.targetModal', '.project-type-custom-input', function() {
                    persistCustomProjectType($(this).closest('.target-entry-item'));
                    syncClassificationTargets();
                });
                $(document).on('input.targetModal', '.project-name-input', function() {
                    syncTargetFertilizerFollowup($(this).closest('.target-entry-item'));
                });
                $(document).on('change.targetModal', '.target-fertilizer-enabled-choice', function() {
                    const $row = $(this).closest('.target-entry-item');
                    $row.find('.target-fertilizer-enabled-input').val($(this).val());
                    syncTargetFertilizerFollowup($row);
                });
                $(document).on('input.targetModal change.targetModal', '.binhi-target-quantity-input', function() {
                    syncBinhiTypeTargetTotals();
                });
                $(document).on('change.targetModal', '.aquatic-resource-enabled-choice', function() {
                    const $row = $(this).closest('.target-entry-item');
                    $row.find('.aquatic-resource-enabled-input').val($(this).val());
                    syncAquaticResourceField($row);
                });
                $(document).on('change.targetModal', '.aquatic-resource-input', function() {
                    syncAquaticResourceField($(this).closest('.target-entry-item'));
                });
                $(document).on('blur.targetModal', '.aquatic-resource-custom-input', function() {
                    persistCustomAquaticResource($(this).closest('.target-entry-item'));
                });
                $(document).on('click.targetModal', '.aquatic-resource-hide-btn', function() {
                    const $row = $(this).closest('.target-entry-item');
                    $row.find('.aquatic-resource-enabled-input').val('0');
                    syncAquaticResourceField($row);
                });
                $(document).on('change.targetModal', '.project-classification-input', function() {
                    const row = $(this).closest('.target-entry-item');
                    const previousValue = getProjectTypeValue(row);
                    row.find('.project-type-field').replaceWith(renderProjectTypeField($(this).val(), previousValue));
                    syncProjectTypeField(row, previousValue);
                    syncTargetFertilizerFollowup(row);
                    syncBinhiTargetQuantityField(row);
                    promptAquaticResourceDecision(row);
                    syncClassificationTargets();
                });
                $('#target-entry-list .target-entry-item').each(function() {
                    const $row = $(this);
                    registerProjectTypeOption($row.find('.project-classification-input').val(), getProjectTypeValue($row));
                    registerAquaticResourceOption(getAquaticResourceValue($row));
                    syncProjectTypeField($(this));
                    syncTargetFertilizerFollowup($(this));
                    syncBinhiTargetQuantityField($(this));
                    syncAquaticResourceField($(this));
                });
                reindexTargetEntries();
                $(document).on('input.targetModal change.targetModal', '#target-lawa', function() {
                    syncCoverageRowsToTarget('LAWA', $(this).val());
                });
                $(document).on('input.targetModal change.targetModal', '#target-binhi', function() {
                    syncCoverageRowsToTarget('BINHI', $(this).val());
                });
                syncClassificationTargets();
            },
            willClose: () => {
                $(document).off('.targetModal');
            },
            preConfirm: () => {
                if ($('#target-province, #target-municipality, #target-barangay').filter(':disabled').length) {
                    Swal.showValidationMessage('Please wait for the location dropdowns to finish loading.');
                    return false;
                }
                if (!$('#target-province').val() || !$('#target-municipality').val() || !$('#target-barangay').val()) {
                    Swal.showValidationMessage('Please select a province, municipality, and barangay.');
                    return false;
                }

                const entries = [];
                let hasAquaticValidationError = false;
                $('#target-entry-list .target-entry-item').each(function() {
                    persistCustomProjectType($(this));
                    persistCustomAquaticResource($(this));
                    const purok = $(this).find('.target-purok-input').val().trim();
                    const name = $(this).find('.project-name-input').val().trim();
                    const type = getProjectTypeValue($(this));
                    const classification = $(this).find('.project-classification-input').val();
                    const fertilizerEnabled = ($(this).find('.target-fertilizer-enabled-input').val() || '').trim();
                    const fertilizerOhnTarget = ($(this).find('.target-fertilizer-ohn-input').val() || '').trim();
                    const fertilizerConcoctionTarget = ($(this).find('.target-fertilizer-concoction-input').val() || '').trim();
                    const fertilizerVermicompostTarget = ($(this).find('.target-fertilizer-vermicompost-input').val() || '').trim();
                    const aquaticResource = getAquaticResourceValue($(this));
                    const aquaticResourceQuantity = $(this).find('.aquatic-resource-quantity-input').val().trim();
                    const aquaticResourcesEnabled = String($(this).find('.aquatic-resource-enabled-input').val() || '').trim() === '1';
                    if (aquaticResourcesEnabled && (aquaticResource === '' || !/^\d+$/.test(aquaticResourceQuantity))) {
                        Swal.showValidationMessage('Please complete the aquatic resource and quantity fields for entries marked with aquatic resources.');
                        hasAquaticValidationError = true;
                        return false;
                    }
                    const binhiTargetQuantity = ($(this).find('.binhi-target-quantity-input').val() || '').trim();
                    if (classification === 'BINHI' && type !== '' && binhiTargetQuantity !== '' && !/^\d+$/.test(binhiTargetQuantity)) {
                        Swal.showValidationMessage('Please enter a whole-number BINHI target quantity for BINHI entries.');
                        hasAquaticValidationError = true;
                        return false;
                    }
                    if (isBinhiGardenProject(classification, name) && fertilizerEnabled === '1' && fertilizerOhnTarget === '' && fertilizerConcoctionTarget === '' && fertilizerVermicompostTarget === '') {
                        Swal.showValidationMessage('Please enter at least one BINHI fertilizer target for garden-related projects.');
                        hasAquaticValidationError = true;
                        return false;
                    }
                    if (purok !== '' || name !== '' || type !== '' || classification !== '') {
                        entries.push({
                            row_id: ($(this).find('.target-row-id-input').val() || '').trim(),
                            purok,
                            name,
                            type,
                            classification,
                            fertilizer_enabled: fertilizerEnabled,
                            fertilizer_ohn_target: fertilizerOhnTarget,
                            fertilizer_concoction_target: fertilizerConcoctionTarget,
                            fertilizer_vermicompost_target: fertilizerVermicompostTarget,
                            binhi_target_quantity: binhiTargetQuantity,
                            aquatic_resource: aquaticResource,
                            aquatic_resource_quantity: aquaticResourceQuantity
                        });
                    }
                });

                if (hasAquaticValidationError) {
                    return false;
                }

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
                        binhi_vegetable_target: $('#target-binhi-vegetable').val(),
                        binhi_crops_target: $('#target-binhi-crops').val(),
                        binhi_disaster_resilient_crops_target: $('#target-binhi-disaster-resilient-crops').val(),
                        binhi_fruit_bearing_trees_target: $('#target-binhi-fruit-bearing-trees').val(),
                        binhi_tilapia_target: $('#target-binhi-tilapia').val(),
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

    if (canManageTargets) {
        $('#addTargetBtn').on('click', function() {
            if (window.KodusPageLoader && typeof window.KodusPageLoader.hideModalLoader === 'function') {
                window.KodusPageLoader.hideModalLoader();
            }
            openTargetModal(null);
        });

        $('#project-targets-table tbody').on('click', '.edit-target-btn', function() {
            const row = getTargetRowData(this);
            if (!row) {
                Swal.fire('Error', 'Could not load this target row.', 'error');
                return;
            }
            if (window.KodusPageLoader && typeof window.KodusPageLoader.hideModalLoader === 'function') {
                window.KodusPageLoader.hideModalLoader();
            }
            openTargetModal(row);
        });

        $('#project-targets-table tbody').on('click', '.delete-target-btn', function() {
            const row = getTargetRowData(this);
            if (!row) {
                Swal.fire('Error', 'Could not load this target row.', 'error');
                return;
            }
            if (window.KodusPageLoader && typeof window.KodusPageLoader.hideModalLoader === 'function') {
                window.KodusPageLoader.hideModalLoader();
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
