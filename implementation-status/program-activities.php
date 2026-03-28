<?php
include('../header.php');
include('../sidenav.php');

$userType = $_SESSION['user_type'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | Program Activities</title>
  <link rel="stylesheet" href="<?php echo $base_url;?>kodus/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $base_url;?>kodus/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $base_url;?>kodus/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $base_url;?>kodus/plugins/daterangepicker/daterangepicker.css">
  <style>
    .summary-card .small-box {
      margin-bottom: 0;
    }
    .activity-detail {
      text-align: left;
      line-height: 1.6;
    }
    #program-activities-table td {
      vertical-align: middle;
    }
    .edit-grid-row {
      display: grid;
      grid-template-columns: minmax(180px, 1fr) minmax(180px, 1fr) minmax(180px, 1fr);
      gap: 12px;
      margin-bottom: 12px;
      align-items: start;
      padding: 14px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 14px;
      background: rgba(255, 255, 255, 0.05);
    }
    @media (max-width: 768px) {
      .edit-grid-row {
        grid-template-columns: 1fr;
      }
    }
    .project-item {
      display: flex;
      gap: 8px;
      margin-bottom: 8px;
      align-items: center;
    }
    .coverage-entry-item {
      display: grid;
      grid-template-columns: minmax(130px, 0.95fr) minmax(180px, 1.2fr) minmax(150px, 0.9fr) auto auto;
      gap: 8px;
      margin-bottom: 8px;
      align-items: center;
    }
    @media (max-width: 768px) {
      .coverage-entry-item {
        grid-template-columns: 1fr;
      }
    }
    .project-item .form-control {
      flex: 1 1 auto;
    }
    .stage-phase-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 10px;
    }
    .stage-phase-card {
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.04);
      padding: 12px;
    }
    .stage-phase-card h6 {
      margin: 0 0 10px;
      font-size: 0.82rem;
      font-weight: 700;
      letter-spacing: 0.02em;
      text-transform: none;
    }
    .stage-phase-dates {
      display: block;
    }
    .date-range-field {
      position: relative;
    }
    .date-range-input {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='rgba(255,255,255,0.72)' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cline x1='16' y1='2' x2='16' y2='6'/%3E%3Cline x1='8' y1='2' x2='8' y2='6'/%3E%3Cline x1='3' y1='10' x2='21' y2='10'/%3E%3C/svg%3E") !important;
      background-repeat: no-repeat !important;
      background-position: right 0.85rem center !important;
      background-size: 1rem 1rem !important;
      cursor: pointer;
      padding-right: 2.5rem;
    }
    .date-range-hint {
      display: block;
      margin-top: 0.45rem;
      font-size: 0.74rem;
      opacity: 0.78;
    }
    .site-validation-item .date-range-field {
      min-width: 0;
      flex: 1 1 auto;
    }
    .readonly-display {
      min-height: calc(1.8125rem + 2px);
      padding: .45rem .65rem;
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: .55rem;
      background: rgba(255, 255, 255, 0.06);
      color: #f8f9fa;
      display: flex;
      align-items: center;
    }
    .readonly-display-rich {
      display: block;
      align-items: initial;
      line-height: 1.5;
    }
    .target-coverage-list {
      margin: 0;
      padding-left: 1rem;
    }
    .target-coverage-list li {
      margin-bottom: 0.45rem;
    }
    .target-coverage-sublist {
      margin: 0.35rem 0 0;
      padding-left: 1rem;
      opacity: 0.92;
    }
    .target-coverage-empty {
      opacity: 0.72;
      font-style: italic;
    }
    .activity-edit-shell {
      text-align: left;
      color: inherit;
    }
    .activity-edit-section {
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 16px;
      background: rgba(255, 255, 255, 0.04);
      padding: 16px 18px;
      margin-bottom: 16px;
      box-shadow: 0 10px 22px rgba(0, 0, 0, 0.16);
    }
    .activity-edit-section h6 {
      margin: 0 0 12px;
      font-size: 0.92rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #f8f9fa;
    }
    .activity-edit-section .section-note {
      margin: -4px 0 14px;
      color: rgba(255, 255, 255, 0.68);
      font-size: 0.84rem;
    }
    .forum-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
      gap: 10px;
      align-items: stretch;
    }
    .social-prep-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    @media (max-width: 992px) {
      .social-prep-grid {
        grid-template-columns: 1fr;
      }
    }
    .forum-card {
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 14px;
      background: linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(255,255,255,0.03));
      padding: 12px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .forum-card.full-width {
      grid-column: 1 / -1;
    }
    .forum-card-title {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 6px;
      font-size: 0.86rem;
      font-weight: 700;
      color: #f8f9fa;
    }
    .forum-date-grid {
      display: block;
    }
    .site-validation-list {
      display: grid;
      gap: 10px;
    }
    .site-validation-item {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto auto;
      gap: 8px;
      align-items: end;
    }
    .site-validation-item .btn {
      width: 2.1rem;
      min-width: 2.1rem;
      height: calc(1.5em + 0.75rem + 2px);
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      align-self: end;
      line-height: 1;
    }
    @media (max-width: 768px) {
      .site-validation-item,
      .forum-date-grid,
      .stage-phase-dates {
        grid-template-columns: 1fr;
      }
      .site-validation-item .btn {
        width: 100%;
      }
    }
    .forum-date-grid label,
    .edit-grid-row label {
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.02em;
      margin-bottom: 4px;
      display: block;
    }
    .swal2-popup .daterangepicker {
      z-index: 10010 !important;
    }
    .swal2-popup .daterangepicker.openscenter {
      left: 50% !important;
      right: auto !important;
      transform: translateX(-50%);
    }
    .barangay-edit-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 12px;
    }
    .barangay-edit-header h6 {
      margin: 0;
      font-size: 0.92rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .barangay-edit-header span {
      color: rgba(255, 255, 255, 0.68);
      font-size: 0.84rem;
    }
    .project-item .btn {
      flex: 0 0 auto;
    }
    .swal2-popup .activity-edit-shell .form-control {
      background: rgba(255, 255, 255, 0.08);
      border-color: rgba(255, 255, 255, 0.14);
      color: #f8f9fa;
    }
    .swal2-popup .activity-edit-shell .form-control:focus {
      background: rgba(255, 255, 255, 0.12);
      border-color: rgba(13, 110, 253, 0.55);
      color: #fff;
      box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.16);
    }
    .swal2-popup .activity-edit-shell .form-control[readonly],
    .swal2-popup .activity-edit-shell .form-control:disabled {
      background: rgba(255, 255, 255, 0.05);
      color: rgba(255, 255, 255, 0.78);
    }
    .swal2-popup .activity-edit-shell label {
      color: rgba(255, 255, 255, 0.82);
    }
    .activity-modal {
      text-align: left;
      color: inherit;
    }
    .activity-modal-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
      margin-bottom: 16px;
    }
    .activity-stat {
      border: 1px solid rgba(108, 117, 125, 0.35);
      border-radius: 12px;
      padding: 12px 14px;
      background: rgba(108, 117, 125, 0.08);
    }
    .activity-stat-label {
      display: block;
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      opacity: 0.75;
      margin-bottom: 4px;
    }
    .activity-stat-value {
      font-size: 1rem;
      font-weight: 700;
      line-height: 1.35;
      word-break: break-word;
    }
    .activity-section {
      border: 1px solid rgba(108, 117, 125, 0.28);
      border-radius: 14px;
      padding: 14px 16px;
      margin-bottom: 14px;
      background: rgba(108, 117, 125, 0.05);
    }
    .activity-section h6 {
      margin: 0 0 10px;
      font-size: 0.92rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .activity-section-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 10px;
    }
    .activity-field-label {
      display: block;
      font-size: 0.76rem;
      font-weight: 600;
      opacity: 0.72;
      margin-bottom: 4px;
    }
    .activity-field-value {
      display: block;
      line-height: 1.5;
      word-break: break-word;
    }
    .activity-list {
      margin: 0;
      padding-left: 1.1rem;
    }
    .activity-list li {
      margin-bottom: 0.35rem;
      line-height: 1.45;
    }
    .activity-empty {
      opacity: 0.72;
      font-style: italic;
    }
    .activity-readiness-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 0.35rem 0.7rem;
      border-radius: 999px;
      font-size: 0.86rem;
      font-weight: 700;
      line-height: 1;
    }
    .activity-readiness-badge.ready {
      background: rgba(40, 167, 69, 0.18);
      color: #7CFC9B;
    }
    .activity-readiness-badge.progress {
      background: rgba(255, 193, 7, 0.2);
      color: #FFD86B;
    }
    .activity-readiness-badge.update {
      background: rgba(108, 117, 125, 0.22);
      color: #E2E6EA;
    }
    body[data-theme="light"] .activity-stat,
    body[data-theme="light"] .activity-section,
    body[data-theme="light"] .activity-edit-section {
      background: #ffffff;
      border-color: rgba(13, 110, 253, 0.14);
      box-shadow: 0 0.4rem 1rem rgba(13, 110, 253, 0.06);
    }
    body[data-theme="light"] .edit-grid-row {
      background: rgba(255, 255, 255, 0.9);
      border-color: rgba(13, 110, 253, 0.12);
    }
    body[data-theme="light"] .stage-phase-card {
      background: rgba(248, 250, 252, 0.95);
      border-color: rgba(13, 110, 253, 0.12);
    }
    body[data-theme="light"] .readonly-display {
      background: rgba(108, 117, 125, 0.08);
      border-color: rgba(108, 117, 125, 0.32);
      color: #212529;
    }
    body[data-theme="light"] .activity-edit-section h6,
    body[data-theme="light"] .forum-card-title {
      color: #1f2d3d;
    }
    body[data-theme="light"] .activity-edit-section .section-note,
    body[data-theme="light"] .barangay-edit-header span {
      color: #6c757d;
    }
    body[data-theme="light"] .forum-card {
      background: linear-gradient(180deg, rgba(248, 250, 252, 0.95), rgba(255,255,255,0.98));
      border-color: rgba(13, 110, 253, 0.12);
    }
    body[data-theme="light"] .swal2-popup .activity-edit-shell .form-control {
      background: #ffffff;
      border-color: rgba(13, 110, 253, 0.14);
      color: #212529;
    }
    body[data-theme="light"] .swal2-popup .activity-edit-shell .form-control:focus {
      background: #ffffff;
      color: #212529;
    }
    body[data-theme="light"] .swal2-popup .activity-edit-shell .form-control[readonly],
    body[data-theme="light"] .swal2-popup .activity-edit-shell .form-control:disabled,
    body[data-theme="light"] .swal2-popup .activity-edit-shell label {
      color: #495057;
    }
    body[data-theme="light"] .activity-readiness-badge.ready {
      color: #1e7e34;
      background: rgba(40, 167, 69, 0.14);
    }
    body[data-theme="light"] .activity-readiness-badge.progress {
      color: #a16800;
      background: rgba(255, 193, 7, 0.18);
    }
    body[data-theme="light"] .activity-readiness-badge.update {
      color: #495057;
      background: rgba(108, 117, 125, 0.14);
    }
    .viewer-note {
      border-radius: 12px;
      padding: .9rem 1rem;
      margin-bottom: 1rem;
      border: 1px solid rgba(23, 162, 184, 0.28);
      background: rgba(23, 162, 184, 0.12);
      color: inherit;
    }
  </style>
</head>
<body class="hold-transition dark-mode sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">


  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">Program Activities</h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../home">Home</a></li>
              <li class="breadcrumb-item active">Program Activities</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Program Activities</h3></div>
          <div class="card-body">
            <input type="hidden" id="user-type" value="<?= htmlspecialchars($userType) ?>">
            <?php if ($userType !== 'admin'): ?>
              <div class="viewer-note">
                <strong>Viewer mode:</strong> You can browse implementation status details, but only administrators can edit activity records.
              </div>
            <?php endif; ?>

            <div class="row mb-3 summary-card">
              <div class="col-md-3 col-6">
                <div class="small-box bg-info">
                  <div class="inner">
                    <h3 id="summary-municipalities">0</h3>
                    <p>Municipalities</p>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="small-box bg-success">
                  <div class="inner">
                    <h3 id="summary-beneficiaries">0</h3>
                    <p>Target Beneficiaries</p>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="small-box bg-warning">
                  <div class="inner">
                    <h3 id="summary-projects">0</h3>
                    <p>Distinct Projects</p>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="small-box bg-secondary">
                  <div class="inner">
                    <h3 id="summary-ready">0</h3>
                    <p>Ready Municipalities</p>
                  </div>
                </div>
              </div>
            </div>

            <div id="track-documents-container" style="display: none; margin-bottom:10px;">
              <button id="track-documents" class="btn btn-outline-primary btn-xs" style="color: white;">Track Incoming Documents</button>
            </div>

            <div class="table-container">
              <table id="program-activities-table" class="table table-bordered table-striped" style="text-align: center; width:100%; table-layout: auto;">
                <thead style="font-size: 10px;">
                  <tr>
                    <th rowspan="2">Action</th>
                    <th rowspan="2" style="display:none;">Province</th>
                    <th rowspan="2">Municipality</th>
                    <th rowspan="2">Target Partner-Beneficiaries</th>
                    <th rowspan="2">Amount</th>
                    <th colspan="4">FORUM SCHEDULES</th>
                    <th colspan="3">IMPLEMENTATION PHASES</th>
                    <th colspan="5">PROJECT DETAILS</th>
                  </tr>
                  <tr>
                    <th>PLGU Forum (From - To)</th>
                    <th>MLGU Forum (From - To)</th>
                    <th>BLGU Forum (From - To)</th>
                    <th>Site Validation</th>
                    <th>Stage 1 (Start - End)</th>
                    <th>Stage 2 (Start - End)</th>
                    <th>Stage 3 (Start - End)</th>
                    <th>No. of Barangays</th>
                    <th>Name of Brgys. and No. of Partner Beneficiaries</th>
                    <th style="max-width: 35vh;">Project Names</th>
                    <th>Readiness</th>
                    <th>Last Updated</th>
                  </tr>
                </thead>
                <tbody style="font-size: 10px;"></tbody>
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
<script src="<?php echo $base_url;?>kodus/plugins/jszip/jszip.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo $base_url;?>kodus/dist/js/adminlte.min.js"></script>

<script>
$(document).ready(function() {
    const isAdmin = $('#user-type').val() === 'admin';

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    function updateSummary(rows) {
        const municipalityCount = rows.length;
        const beneficiaries = rows.reduce((sum, row) => sum + (parseInt(row.target_partner_beneficiaries, 10) || 0), 0);
        const projectCount = rows.reduce((sum, row) => sum + (parseInt(row.project_count, 10) || 0), 0);
        const readyCount = rows.filter(row => (row.readiness || '').includes('Ready')).length;

        $('#summary-municipalities').text(municipalityCount.toLocaleString());
        $('#summary-beneficiaries').text(beneficiaries.toLocaleString());
        $('#summary-projects').text(projectCount.toLocaleString());
        $('#summary-ready').text(readyCount.toLocaleString());
    }

    function renderCoverageInputs(puroks, names, classifications) {
        const targetPuroks = Array.isArray(puroks) && puroks.length ? puroks : [''];
        const projectNames = Array.isArray(names) && names.length ? names : [''];
        const projectClasses = Array.isArray(classifications) && classifications.length ? classifications : [''];
        const count = Math.max(targetPuroks.length, projectNames.length, projectClasses.length, 1);
        const rows = [];

        for (let i = 0; i < count; i++) {
            rows.push(`
                <div class="coverage-entry-item">
                    <input type="text" class="form-control form-control-sm coverage-purok" value="${escapeHtml(targetPuroks[i] || '')}" placeholder="Purok">
                    <input type="text" class="form-control form-control-sm coverage-project-name" value="${escapeHtml(projectNames[i] || '')}" placeholder="Project name">
                    <select class="custom-select custom-select-sm coverage-project-classification">
                        <option value="">Classification</option>
                        <option value="LAWA" ${(projectClasses[i] || '') === 'LAWA' ? 'selected' : ''}>LAWA</option>
                        <option value="BINHI" ${(projectClasses[i] || '') === 'BINHI' ? 'selected' : ''}>BINHI</option>
                    </select>
                    <button type="button" class="btn btn-success btn-sm add-coverage-btn">+</button>
                    <button type="button" class="btn btn-danger btn-sm remove-coverage-btn">-</button>
                </div>
            `);
        }

        return rows.join('');
    }

    function parseSiteValidationEntries(rawValue) {
        const value = String(rawValue || '').trim();
        if (!value) {
            return [{ start: '', end: '' }];
        }

        const entries = value.includes('||')
            ? value.split('||').map(item => item.trim()).filter(Boolean)
            : value.split(/\s*,\s*/).map(item => item.trim()).filter(Boolean);
        if (!entries.length) {
            return [{ start: '', end: '' }];
        }

        return entries.map((entry) => {
            if (entry.includes('~')) {
                const parts = entry.split('~');
                const start = (parts[0] || '').trim();
                const end = (parts[1] || '').trim();
                return { start, end: end || start };
            }

            const rangeMatch = entry.match(/^(\d{4}-\d{2}-\d{2})\s*-\s*(\d{4}-\d{2}-\d{2})$/);
            if (rangeMatch) {
                return { start: rangeMatch[1], end: rangeMatch[2] };
            }

            const cleaned = entry.trim();
            return { start: cleaned, end: cleaned };
        });
    }

    function renderSiteValidationInputs(rawValue) {
        const entries = parseSiteValidationEntries(rawValue);
        return entries.map((entry) => `
            <div class="site-validation-item">
                <div class="date-range-field">
                    <label>Schedule</label>
                    <input type="text" class="form-control site-validation-range date-range-input js-date-range-picker" value="${escapeHtml(formatDateRangeInputValue(entry.start || '', entry.end || ''))}" placeholder="Select date range" readonly>
                    <input type="hidden" class="site-validation-start" value="${escapeHtml(entry.start || '')}">
                    <input type="hidden" class="site-validation-end" value="${escapeHtml(entry.end || '')}">
                </div>
                <button type="button" class="btn btn-success btn-sm add-site-validation-btn">+</button>
                <button type="button" class="btn btn-danger btn-sm remove-site-validation-btn">-</button>
            </div>
        `).join('');
    }

    function renderStagePhaseInputs(row) {
        const stageDefinitions = [
            { key: 'stage1', label: 'Stage 1 - Cash-for-Training' },
            { key: 'stage2', label: 'Stage 2 - Cash-for-Work' },
            { key: 'stage3', label: 'Stage 3 - Cash-for-Training (Sustainability Training)' }
        ];

        return `
            <div class="stage-phase-grid">
                ${stageDefinitions.map((stage) => `
                    <div class="stage-phase-card">
                        <h6>${escapeHtml(stage.label)}</h6>
                        <div class="stage-phase-dates">
                            <div class="date-range-field">
                                <label>Schedule</label>
                                <input type="text" class="form-control form-control-sm ${stage.key}-range date-range-input js-date-range-picker" value="${escapeHtml(formatDateRangeInputValue(row[`${stage.key}_start_date`] || '', row[`${stage.key}_end_date`] || ''))}" placeholder="Select date range" readonly>
                                <input type="hidden" class="${stage.key}-start-date" value="${escapeHtml(row[`${stage.key}_start_date`] || '')}">
                                <input type="hidden" class="${stage.key}-end-date" value="${escapeHtml(row[`${stage.key}_end_date`] || '')}">
                                <span class="date-range-hint">Pick one day to set both dates, then reopen it later if you need to extend the end date.</span>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    function formatDateRangeInputValue(startDate, endDate) {
        const start = String(startDate || '').trim();
        const end = String(endDate || '').trim();

        if (!start && !end) {
            return '';
        }

        if (start && end) {
            return start === end ? start : `${start} - ${end}`;
        }

        return start || end;
    }

    function stripHtml(value) {
        return $('<div>').html(value ?? '').text().trim();
    }

    function formatFallback(value, fallback = 'Not set') {
        const text = String(value ?? '').trim();
        return text !== '' ? escapeHtml(text) : `<span class="kodus-detail-empty">${escapeHtml(fallback)}</span>`;
    }

    function formatNumber(value, fallback = '0') {
        const parsed = Number(value);
        if (!Number.isFinite(parsed)) {
            return escapeHtml(fallback);
        }
        return escapeHtml(parsed.toLocaleString());
    }

    function formatCurrency(value) {
        const parsed = Number(String(value ?? '').replace(/,/g, ''));
        if (!Number.isFinite(parsed)) {
            return '<span class="kodus-detail-empty">Not available</span>';
        }
        return escapeHtml(new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 2
        }).format(parsed));
    }

    function formatList(value, fallback) {
        const raw = String(value ?? '');
        const items = raw
            .split(raw.includes('||') ? /\|\|/ : /\s*,\s*/)
            .map(item => item.trim())
            .filter(Boolean);

        if (!items.length) {
            return `<div class="kodus-detail-empty">${escapeHtml(fallback)}</div>`;
        }

        return `<ul class="kodus-detail-list">${items.map(item => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`;
    }

    function buildGroupedTargetRows(puroks, names, classifications) {
        const targetPuroks = Array.isArray(puroks) ? puroks : [];
        const projectNames = Array.isArray(names) ? names : [];
        const projectClasses = Array.isArray(classifications) ? classifications : [];
        const count = Math.max(targetPuroks.length, projectNames.length, projectClasses.length);
        const rows = [];
        const map = new Map();

        for (let i = 0; i < count; i++) {
            const purok = String(targetPuroks[i] || '').trim();
            const name = String(projectNames[i] || '').trim();
            const classification = String(projectClasses[i] || '').trim();

            if (!purok && !name && !classification) {
                continue;
            }

            const key = purok || `__row_${i}`;
            if (!map.has(key)) {
                const row = { purok: purok || `Target Row ${rows.length + 1}`, projects: [] };
                map.set(key, row);
                rows.push(row);
            }

            if (name || classification) {
                map.get(key).projects.push({ name, classification });
            }
        }

        return rows;
    }

    function renderTargetCoverageList(puroks, names, classifications, fallback) {
        const groupedRows = buildGroupedTargetRows(puroks, names, classifications);
        if (!groupedRows.length) {
            return `<div class="target-coverage-empty">${escapeHtml(fallback)}</div>`;
        }

        const items = groupedRows.map((row) => {
            if (!row.projects.length) {
                return `<li><strong>${escapeHtml(row.purok)}</strong></li>`;
            }

            const projects = row.projects.map((project) => {
                const projectName = project.name ? escapeHtml(project.name) : 'Unnamed project';
                if (project.classification) {
                    return `<li>${projectName} <span class="text-muted">(${escapeHtml(project.classification)})</span></li>`;
                }
                return `<li>${projectName}</li>`;
            }).join('');

            return `
                <li>
                    <strong>${escapeHtml(row.purok)}</strong>
                    <ul class="target-coverage-sublist">${projects}</ul>
                </li>
            `;
        }).join('');

        return `<ul class="target-coverage-list">${items}</ul>`;
    }

    function renderReadinessBadge(value) {
        const readinessText = stripHtml(value);
        const normalized = readinessText.toLowerCase();
        let valueClass = '';

        if (normalized.includes('ready')) {
            valueClass = ' kodus-detail-value--positive';
        } else if (normalized.includes('progress')) {
            valueClass = ' kodus-detail-value--warning';
        }

        return `<span class="kodus-detail-badge${valueClass}">${escapeHtml(readinessText || 'Not set')}</span>`;
    }

    function renderActivityDetails(data) {
        return `
            <div class="kodus-detail-modal">
                <div class="kodus-detail-hero">
                    <div>
                        <span class="kodus-detail-eyebrow">Program Activity</span>
                        <h3 class="kodus-detail-title">${formatFallback(data.municipality, 'No municipality')}</h3>
                        <p class="kodus-detail-subtitle">${formatFallback(data.province, 'No province')}</p>
                    </div>
                    <div class="kodus-detail-pill">${formatFallback(data.last_updated, 'No updates yet')}</div>
                </div>

                <div class="kodus-detail-grid">
                    <div class="kodus-detail-stat">
                        <span class="kodus-detail-label">Municipality</span>
                        <span class="kodus-detail-value kodus-detail-value--strong">${formatFallback(data.municipality)}</span>
                    </div>
                    <div class="kodus-detail-stat">
                        <span class="kodus-detail-label">Province</span>
                        <span class="kodus-detail-value kodus-detail-value--strong">${formatFallback(data.province)}</span>
                    </div>
                    <div class="kodus-detail-stat">
                        <span class="kodus-detail-label">Target Beneficiaries</span>
                        <span class="kodus-detail-value kodus-detail-value--strong">${formatNumber(data.target_partner_beneficiaries)}</span>
                    </div>
                    <div class="kodus-detail-stat">
                        <span class="kodus-detail-label">Imported Beneficiaries</span>
                        <span class="kodus-detail-value kodus-detail-value--strong">${formatNumber(data.actual_partner_beneficiaries)}</span>
                    </div>
                    <div class="kodus-detail-stat">
                        <span class="kodus-detail-label">Estimated Amount</span>
                        <span class="kodus-detail-value kodus-detail-value--strong">${formatCurrency(data.amount)}</span>
                    </div>
                    <div class="kodus-detail-stat">
                        <span class="kodus-detail-label">Variance</span>
                        <span class="kodus-detail-value kodus-detail-value--strong">${formatNumber(data.variance_partner_beneficiaries)}</span>
                    </div>
                </div>

                <div class="kodus-detail-section">
                    <h6 class="kodus-detail-section-title">Readiness Snapshot</h6>
                    <div class="kodus-detail-section-grid">
                        <div>
                            <span class="kodus-detail-label">Overall Status</span>
                            <span class="kodus-detail-value">${renderReadinessBadge(data.readiness)}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Last Updated</span>
                            <span class="kodus-detail-value">${formatFallback(data.last_updated, 'No updates yet')}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Number of Barangays</span>
                            <span class="kodus-detail-value">${formatNumber(data.no_of_barangays)}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Imported Barangays</span>
                            <span class="kodus-detail-value">${formatNumber(data.actual_barangay_count)}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Target vs Actual</span>
                            <span class="kodus-detail-value">${data.validation_snapshot || '<span class="kodus-detail-empty">No comparison available</span>'}</span>
                        </div>
                    </div>
                </div>

                <div class="kodus-detail-section">
                    <h6 class="kodus-detail-section-title">Forum Schedules</h6>
                    <div class="kodus-detail-section-grid">
                        <div>
                            <span class="kodus-detail-label">PLGU Forum</span>
                            <span class="kodus-detail-value">${formatFallback(data.plgu_forum)}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">MLGU Forum</span>
                            <span class="kodus-detail-value">${formatFallback(data.mlgu_forum)}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">BLGU Forum</span>
                            <span class="kodus-detail-value">${formatFallback(data.blgu_forum)}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Site Validation</span>
                            <span class="kodus-detail-value">${formatFallback(data.site_validation, 'Not set')}</span>
                        </div>
                    </div>
                </div>

                <div class="kodus-detail-section">
                    <h6 class="kodus-detail-section-title">Implementation Phases</h6>
                    <div class="kodus-detail-section-grid">
                        <div>
                            <span class="kodus-detail-label">Stage 1 - Cash-for-Training</span>
                            <span class="kodus-detail-value">${formatFallback(data.stage1_phase, 'Not set')}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Stage 2 - Cash-for-Work</span>
                            <span class="kodus-detail-value">${formatFallback(data.stage2_phase, 'Not set')}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Stage 3 - Cash-for-Training (Sustainability Training)</span>
                            <span class="kodus-detail-value">${formatFallback(data.stage3_phase, 'Not set')}</span>
                        </div>
                    </div>
                </div>

                <div class="kodus-detail-section">
                    <h6 class="kodus-detail-section-title">Coverage and Beneficiaries</h6>
                    <span class="kodus-detail-label">Barangays and Partner-Beneficiaries</span>
                    ${formatList(data.barangays_and_beneficiaries, 'No barangay breakdown recorded yet')}
                </div>

                <div class="kodus-detail-section mb-0">
                    <h6 class="kodus-detail-section-title">Project Portfolio</h6>
                    <span class="kodus-detail-label">Project Names</span>
                    ${formatList(data.project_names, 'No projects recorded yet')}
                    <span class="kodus-detail-label mt-3">Baseline Target Areas</span>
                    ${renderTargetCoverageList(
                        String(data.target_puroks || '').split(String(data.target_puroks || '').includes('||') ? /\|\|/ : /\s*,\s*/).filter(Boolean),
                        String(data.target_project_names || '').split(String(data.target_project_names || '').includes('||') ? /\|\|/ : /\s*,\s*/).filter(Boolean),
                        String(data.target_project_classifications || '').split(String(data.target_project_classifications || '').includes('||') ? /\|\|/ : /\s*,\s*/).filter(Boolean),
                        'No baseline target areas recorded yet'
                    )}
                </div>
            </div>
        `;
    }

    var table = $('#program-activities-table').DataTable({
        "ajax": {
            "url": "fetch-program-activities.php",
            "dataSrc": function(json) {
                updateSummary(json.data || []);
                return json.data || [];
            }
        },
        "columns": [
            { "data": "action", "orderable": false },
            { "data": "province", "visible": false }, // hidden column
            { "data": "municipality" },
            { "data": "target_partner_beneficiaries" },
            { "data": "amount" },
            { "data": "plgu_forum" },
            { "data": "mlgu_forum" },
            { "data": "blgu_forum" },
            { "data": "site_validation" },
            { "data": "stage1_phase" },
            { "data": "stage2_phase" },
            { "data": "stage3_phase" },
            { "data": "no_of_barangays" },
            { "data": "barangays_and_beneficiaries" },
            { "data": "project_names" },
            { "data": "readiness", "orderable": false },
            { "data": "last_updated" }
        ],
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "order": [[1, 'asc'], [2, 'asc']] // sort by province (hidden), then municipality
    });

    // Details button
    $('#program-activities-table tbody').on('click', '.details-btn', function() {
        var data = table.row($(this).parents('tr')).data();
        Swal.fire({
            title: 'Program Activity Details',
            width: 900,
            customClass: {
                popup: 'kodus-detail-popup'
            },
            html: renderActivityDetails(data),
            confirmButtonText: '<i class="fas fa-times"></i>'
        });
    });

    $('#program-activities-table tbody').on('click', '.edit-btn', function() {
        if (!isAdmin) {
            return;
        }

        const data = table.row($(this).parents('tr')).data();
        $.getJSON('get-program-activity.php', { municipality: data.municipality, province: data.province })
            .done(function(response) {
                if (!response.success) {
                    Swal.fire('Error', response.message || 'Could not load activity details.', 'error');
                    return;
                }

                const rows = response.rows || [];
                const first = rows[0] || {};
                const rowHtml = rows.map((row, index) => `
                        <div class="edit-grid-row">
                        <input type="hidden" class="row-barangay" value="${escapeHtml(row.barangay)}">
                        <div>
                            <label class="mb-1">Barangay</label>
                            <div class="readonly-display">${escapeHtml(row.barangay)}</div>
                        </div>
                        <div>
                            <label class="mb-1">Beneficiaries</label>
                            <div class="readonly-display">Target: ${escapeHtml(row.target_partner_beneficiaries ?? 0)} | LAWA: ${escapeHtml(row.lawa_target ?? 0)} | BINHI: ${escapeHtml(row.binhi_target ?? 0)} | Actual: ${escapeHtml(row.actual_beneficiaries ?? 0)}</div>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label class="mb-1">Target Coverage</label>
                            <div class="coverage-list">
                                ${renderCoverageInputs(row.puroks || [], row.target_project_names || [], row.project_classifications || [])}
                            </div>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label class="mb-1">Site Validation</label>
                            <div class="site-validation-list row-site-validation-list">${renderSiteValidationInputs(row.site_validation || '')}</div>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label class="mb-1">Implementation Phases</label>
                            ${renderStagePhaseInputs(row)}
                        </div>
                    </div>
                `).join('');

                Swal.fire({
                    title: `Edit ${escapeHtml(data.municipality)}`,
                    width: 1180,
                    customClass: {
                        popup: 'kodus-edit-popup'
                    },
                    html: `
                        <div class="activity-edit-shell">
                            <div class="activity-edit-section">
                                <h6>Coverage</h6>
                                <div class="form-row mb-0">
                                    <div class="form-group col-md-6 mb-2">
                                        <label>Province</label>
                                        <input type="text" id="edit-province" class="form-control" value="${escapeHtml(first.province || data.province)}" readonly>
                                    </div>
                                    <div class="form-group col-md-6 mb-2">
                                        <label>Municipality</label>
                                        <input type="text" id="edit-municipality" class="form-control" value="${escapeHtml(data.municipality)}" disabled>
                                    </div>
                                </div>
                            </div>

                            <div class="activity-edit-section">
                                <h6>Forum Schedules</h6>
                                <div class="section-note">Forum dates are optional. If you set a From or To date for a forum, complete both fields for that same forum.</div>
                                <div class="forum-grid social-prep-grid">
                                    <div class="forum-card">
                                        <div class="forum-card-title"><i class="fas fa-users"></i><span>PLGU Forum</span></div>
                                        <div class="forum-date-grid">
                                            <div class="date-range-field">
                                                <label>Schedule</label>
                                                <input type="text" id="edit-plgu-range" class="form-control date-range-input js-date-range-picker" value="${escapeHtml(formatDateRangeInputValue(first.plgu_forum_from || '', first.plgu_forum_to || ''))}" placeholder="Select date range" readonly>
                                                <input type="hidden" id="edit-plgu-from" value="${first.plgu_forum_from || ''}">
                                                <input type="hidden" id="edit-plgu-to" value="${first.plgu_forum_to || ''}">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="forum-card">
                                        <div class="forum-card-title"><i class="fas fa-landmark"></i><span>MLGU Forum</span></div>
                                        <div class="forum-date-grid">
                                            <div class="date-range-field">
                                                <label>Schedule</label>
                                                <input type="text" id="edit-mlgu-range" class="form-control date-range-input js-date-range-picker" value="${escapeHtml(formatDateRangeInputValue(first.mlgu_forum_from || '', first.mlgu_forum_to || ''))}" placeholder="Select date range" readonly>
                                                <input type="hidden" id="edit-mlgu-from" value="${first.mlgu_forum_from || ''}">
                                                <input type="hidden" id="edit-mlgu-to" value="${first.mlgu_forum_to || ''}">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="forum-card">
                                        <div class="forum-card-title"><i class="fas fa-map-marked-alt"></i><span>BLGU Forum</span></div>
                                        <div class="forum-date-grid">
                                            <div class="date-range-field">
                                                <label>Schedule</label>
                                                <input type="text" id="edit-blgu-range" class="form-control date-range-input js-date-range-picker" value="${escapeHtml(formatDateRangeInputValue(first.blgu_forum_from || '', first.blgu_forum_to || ''))}" placeholder="Select date range" readonly>
                                                <input type="hidden" id="edit-blgu-from" value="${first.blgu_forum_from || ''}">
                                                <input type="hidden" id="edit-blgu-to" value="${first.blgu_forum_to || ''}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="activity-edit-section">
                                <div class="barangay-edit-header">
                                    <h6>Barangay Project Entries</h6>
                                    <span>Edit linked target rows. Each project name must always have a matching classification.</span>
                                </div>
                                <div id="edit-rows-container">${rowHtml}</div>
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-save"></i>',
                    didOpen: () => {
                        initializeDateRangePickers($('.swal2-popup'));
                    },
                    preConfirm: () => {
                        const plguFrom = $('#edit-plgu-from').val();
                        const plguTo = $('#edit-plgu-to').val();
                        const mlguFrom = $('#edit-mlgu-from').val();
                        const mlguTo = $('#edit-mlgu-to').val();
                        const blguFrom = $('#edit-blgu-from').val();
                        const blguTo = $('#edit-blgu-to').val();
                        const forumRanges = [
                            { label: 'PLGU Forum', from: plguFrom, to: plguTo },
                            { label: 'MLGU Forum', from: mlguFrom, to: mlguTo },
                            { label: 'BLGU Forum', from: blguFrom, to: blguTo }
                        ];

                        for (const forum of forumRanges) {
                            if ((forum.from && !forum.to) || (!forum.from && forum.to)) {
                                Swal.showValidationMessage(`${forum.label} needs both From and To dates when one of them is filled in.`);
                                return false;
                            }

                            if (forum.from && forum.to && forum.from > forum.to) {
                                Swal.showValidationMessage(`${forum.label} From date must be earlier than or equal to its To date.`);
                                return false;
                            }
                        }

                        const rowsPayload = [];
                        let hasRowValidationError = false;
                        $('#edit-rows-container .edit-grid-row').each(function() {
                            const targetEntries = [];
                            let rowHasValidationError = false;
                            const barangayName = $(this).find('.row-barangay').val() || 'this barangay';

                            $(this).find('.coverage-entry-item').each(function() {
                                const purok = $(this).find('.coverage-purok').val().trim();
                                const projectName = $(this).find('.coverage-project-name').val().trim();
                                const classification = $(this).find('.coverage-project-classification').val().trim();

                                if (purok === '' && projectName === '' && classification === '') {
                                    return;
                                }

                                if (purok === '' || projectName === '' || classification === '') {
                                    rowHasValidationError = true;
                                    return false;
                                }

                                if (!['LAWA', 'BINHI'].includes(classification)) {
                                    rowHasValidationError = true;
                                    return false;
                                }

                                targetEntries.push({
                                    purok: purok,
                                    name: projectName,
                                    classification: classification
                                });
                            });

                            if (rowHasValidationError) {
                                Swal.showValidationMessage(`Each target row in ${barangayName} must include a purok, project name, and classification of LAWA or BINHI.`);
                                hasRowValidationError = true;
                                return false;
                            }

                            const siteValidationEntries = [];
                            $(this).find('.site-validation-item').each(function() {
                                const startDate = $(this).find('.site-validation-start').val().trim();
                                const endDate = $(this).find('.site-validation-end').val().trim();

                                if (!startDate && !endDate) {
                                    return;
                                }

                                if (!startDate) {
                                    Swal.showValidationMessage(`${barangayName}: each Site Validation row needs a Start date.`);
                                    hasRowValidationError = true;
                                    return false;
                                }

                                const normalizedEndDate = endDate || startDate;
                                if (startDate > normalizedEndDate) {
                                    Swal.showValidationMessage(`${barangayName}: each Site Validation range must have a Start date earlier than or equal to its End date.`);
                                    hasRowValidationError = true;
                                    return false;
                                }

                                siteValidationEntries.push(`${startDate}~${normalizedEndDate}`);
                            });

                            if (hasRowValidationError) {
                                return false;
                            }

                            const stagePayload = {
                                site_validation: siteValidationEntries.join('||'),
                                stage1_start_date: $(this).find('.stage1-start-date').val(),
                                stage1_end_date: $(this).find('.stage1-end-date').val(),
                                stage2_start_date: $(this).find('.stage2-start-date').val(),
                                stage2_end_date: $(this).find('.stage2-end-date').val(),
                                stage3_start_date: $(this).find('.stage3-start-date').val(),
                                stage3_end_date: $(this).find('.stage3-end-date').val()
                            };

                            const stageLabels = [
                                ['stage1_start_date', 'stage1_end_date', 'Stage 1 - Cash-for-Training'],
                                ['stage2_start_date', 'stage2_end_date', 'Stage 2 - Cash-for-Work'],
                                ['stage3_start_date', 'stage3_end_date', 'Stage 3 - Cash-for-Training (Sustainability Training)']
                            ];

                            for (const [startKey, endKey, label] of stageLabels) {
                                const startDate = stagePayload[startKey];
                                const endDate = stagePayload[endKey];

                                if (!startDate && endDate) {
                                    Swal.showValidationMessage(`${barangayName}: ${label} needs a Start date before its End date can be set.`);
                                    hasRowValidationError = true;
                                    return false;
                                }

                                if (startDate && endDate && startDate > endDate) {
                                    Swal.showValidationMessage(`${barangayName}: ${label} Start date must be earlier than or equal to its End date.`);
                                    hasRowValidationError = true;
                                    return false;
                                }
                            }

                            if (hasRowValidationError) {
                                return false;
                            }

                            rowsPayload.push({
                                barangay: $(this).find('.row-barangay').val(),
                                target_entries: targetEntries,
                                ...stagePayload
                            });
                        });

                        if (hasRowValidationError) {
                            return false;
                        }

                        return $.ajax({
                            url: 'save-imp-status.php',
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                municipality: data.municipality,
                                province: $('#edit-province').val().trim(),
                                plgu_from: plguFrom,
                                plgu_to: plguTo,
                                mlgu_from: mlguFrom,
                                mlgu_to: mlguTo,
                                blgu_from: blguFrom,
                                blgu_to: blguTo,
                                rows: JSON.stringify(rowsPayload),
                                csrf_token: window.KODUS_CSRF_TOKEN
                            }
                        }).then(function(saveResponse) {
                            if (!saveResponse.success) {
                                throw new Error(saveResponse.message || 'Could not save changes.');
                            }
                            return saveResponse;
                        }).catch(function(xhr) {
                            const message = xhr.responseJSON?.message || xhr.message || 'Could not save changes.';
                            Swal.showValidationMessage(message);
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Saved',
                            text: result.value?.message || 'Program activity updated successfully.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        table.ajax.reload(null, false);
                    }
                });
            })
            .fail(function() {
                Swal.fire('Error', 'Could not load activity details.', 'error');
            });
    });

    $(document).on('click', '.add-coverage-btn', function() {
        const list = $(this).closest('.coverage-list');
        list.append(renderCoverageInputs([''], [''], ['']));
    });

    $(document).on('click', '.remove-coverage-btn', function() {
        const list = $(this).closest('.coverage-list');
        if (list.find('.coverage-entry-item').length === 1) {
            list.find('.coverage-purok').val('');
            list.find('.coverage-project-name').val('');
            list.find('.coverage-project-classification').val('');
            return;
        }
        $(this).closest('.coverage-entry-item').remove();
    });

    $(document).on('click', '.add-site-validation-btn', function() {
        const list = $(this).closest('.row-site-validation-list');
        list.append(renderSiteValidationInputs(''));
        initializeDateRangePickers(list);
    });

    $(document).on('click', '.remove-site-validation-btn', function() {
        const list = $(this).closest('.row-site-validation-list');
        if (list.find('.site-validation-item').length === 1) {
            list.find('.site-validation-start').val('');
            list.find('.site-validation-end').val('');
            list.find('.site-validation-range').val('');
            return;
        }
        $(this).closest('.site-validation-item').remove();
    });

    function initializeDateRangePickers($scope) {
        $scope.find('.js-date-range-picker').each(function() {
            const $input = $(this);
            if ($input.data('daterangepicker')) {
                return;
            }

            const $field = $input.closest('.date-range-field');
            const $start = $field.find('input[type="hidden"]').eq(0);
            const $end = $field.find('input[type="hidden"]').eq(1);
            const startValue = ($start.val() || '').trim();
            const endValue = ($end.val() || '').trim();
            const initialStart = startValue || endValue || moment().format('YYYY-MM-DD');
            const initialEnd = endValue || startValue || initialStart;
            const parentEl = $input.closest('.swal2-popup');

            $input.daterangepicker({
                autoUpdateInput: false,
                autoApply: false,
                alwaysShowCalendars: true,
                opens: 'center',
                drops: 'auto',
                parentEl: parentEl.length ? parentEl : 'body',
                startDate: moment(initialStart, 'YYYY-MM-DD'),
                endDate: moment(initialEnd, 'YYYY-MM-DD'),
                locale: {
                    format: 'YYYY-MM-DD',
                    cancelLabel: 'Clear'
                }
            });

            $input.on('apply.daterangepicker', function(ev, picker) {
                const start = picker.startDate.format('YYYY-MM-DD');
                const end = picker.endDate.format('YYYY-MM-DD');
                $start.val(start);
                $end.val(end);
                $input.val(start === end ? start : `${start} - ${end}`);
            });

            $input.on('cancel.daterangepicker', function() {
                $start.val('');
                $end.val('');
                $input.val('');
            });
        });
    }
});
</script>

<script src="<?php echo $base_url;?>kodus/plugins/moment/moment.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/daterangepicker/daterangepicker.js"></script>

</body>
</html>
