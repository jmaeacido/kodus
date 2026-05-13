<?php
include('../header.php');
include('../sidenav.php');

$userType = auth_current_user_type();
$canManageActivities = auth_can_manage_program_activities();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | Program Activities</title>
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/daterangepicker/daterangepicker.css">
  <style>
    .summary-card .small-box {
      margin-bottom: 0;
    }
    .activity-detail {
      text-align: left;
      line-height: 1.6;
    }
    .table-container {
      overflow-x: auto;
      overflow-y: visible;
      -webkit-overflow-scrolling: touch;
    }
    .table-container .dataTables_wrapper {
      min-width: 0;
      width: 100%;
      max-width: 100%;
    }
    #program-activities-table {
      min-width: 1400px;
    }
    #program-activities-table td {
      vertical-align: middle;
    }
    .edit-grid-row {
      display: block;
      margin-bottom: 12px;
      padding: 14px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 14px;
      background: rgba(255, 255, 255, 0.05);
      box-shadow: 0 12px 28px rgba(0, 0, 0, 0.16);
    }
    .project-item {
      display: flex;
      gap: 8px;
      margin-bottom: 8px;
      align-items: center;
    }
    .coverage-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .coverage-entry-item {
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 16px;
      background: rgba(255, 255, 255, 0.04);
      padding: 12px;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
      max-width: 100%;
      overflow-x: auto;
    }
    .coverage-entry-item.is-pending {
      border-color: rgba(255, 193, 7, 0.28);
    }
    .coverage-entry-item.is-confirmed {
      border-color: rgba(40, 167, 69, 0.3);
    }
    .coverage-entry-item.is-custom {
      border-color: rgba(13, 110, 253, 0.28);
    }
    .coverage-entry-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 10px;
    }
    .coverage-entry-status-pill {
      display: inline-flex;
      align-items: center;
      padding: .3rem .65rem;
      border-radius: 999px;
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .03em;
      margin-bottom: .35rem;
    }
    .coverage-entry-status-pill--pending {
      background: rgba(255, 193, 7, 0.18);
      color: #ffd86b;
    }
    .coverage-entry-status-pill--confirmed {
      background: rgba(40, 167, 69, 0.18);
      color: #8ff0ae;
    }
    .coverage-entry-status-pill--custom {
      background: rgba(13, 110, 253, 0.18);
      color: #9dd7ff;
    }
    .coverage-target-reference {
      font-size: .78rem;
      line-height: 1.45;
      color: rgba(255, 255, 255, 0.74);
    }
    .coverage-entry-grid {
      display: grid;
      grid-template-columns:
        minmax(110px, 1fr)
        minmax(150px, 1.35fr)
        minmax(125px, 1fr)
        minmax(170px, 1.4fr)
        minmax(150px, 1.1fr)
        minmax(90px, 0.85fr)
        minmax(90px, 0.85fr)
        minmax(130px, 1.1fr)
        minmax(130px, 1.1fr);
      gap: 10px;
      align-items: end;
    }
    .coverage-field {
      display: flex;
      flex-direction: column;
      gap: 4px;
      min-width: 0;
    }
    .coverage-field--coordinate-pair {
      grid-column: span 2;
    }
    .coverage-coordinate-row {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }
    .coverage-field--purok,
    .coverage-field--project,
    .coverage-field--classification,
    .coverage-field--type,
    .coverage-field--aquatic-resource,
    .coverage-field--aquatic-quantity,
    .coverage-field--actual,
    .coverage-field--land,
    .coverage-field--ownership {
      grid-column: auto;
    }
    .coverage-field-label {
      display: block;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      line-height: 1.25;
      white-space: normal;
      word-break: normal;
      overflow-wrap: break-word;
      color: rgba(255, 255, 255, 0.68);
    }
    .coverage-entry-actions {
      display: inline-flex;
      gap: 6px;
      justify-content: flex-end;
      align-self: flex-start;
      padding-top: 1.5rem;
    }
    .coverage-entry-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-top: 10px;
      padding-top: 10px;
      border-top: 1px solid rgba(255, 255, 255, 0.08);
    }
    .coverage-entry-caption {
      font-size: 0.76rem;
      color: rgba(255, 255, 255, 0.6);
      line-height: 1.4;
    }
    .coverage-fertilizer-followup {
      display: none;
      grid-column: 1 / -1;
      border: 1px solid rgba(40, 167, 69, 0.22);
      border-radius: 14px;
      padding: .9rem 1rem;
      background: rgba(40, 167, 69, 0.08);
    }
    .coverage-fertilizer-followup.is-visible {
      display: block;
    }
    .coverage-fertilizer-options {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      margin: .55rem 0 .85rem;
    }
    .coverage-fertilizer-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 10px;
    }
    @media (max-width: 1400px) {
      .coverage-entry-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
      .coverage-field--purok,
      .coverage-field--coordinate-pair,
      .coverage-field--project,
      .coverage-field--classification,
      .coverage-field--type,
      .coverage-field--actual,
      .coverage-field--land,
      .coverage-field--ownership {
        grid-column: span 1;
      }
      .coverage-entry-footer {
        flex-direction: column;
        align-items: stretch;
      }
      .coverage-entry-actions {
        justify-content: flex-start;
        padding-top: 0;
      }
    }
    @media (max-width: 768px) {
      .coverage-entry-grid {
        grid-template-columns: 1fr;
      }
      .coverage-coordinate-row {
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
      display: flex;
      flex-direction: column;
    }
    .stage-phase-card h6 {
      margin: 0 0 10px;
      min-height: 2.8rem;
      font-size: 0.82rem;
      font-weight: 700;
      letter-spacing: 0.02em;
      text-transform: none;
    }
    .stage-phase-dates {
      display: block;
      margin-top: auto;
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
    .activity-edit-shell .form-control,
    .activity-edit-shell .custom-select {
      background-color: rgba(9, 16, 28, 0.62);
      border-color: rgba(255, 255, 255, 0.14);
      color: #f8f9fa;
    }
    .activity-edit-shell .form-control:focus,
    .activity-edit-shell .custom-select:focus {
      background-color: rgba(9, 16, 28, 0.78);
      border-color: rgba(125, 196, 255, 0.45);
      color: #f8f9fa;
      box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.18);
    }
    .activity-edit-shell .custom-select option {
      background-color: #182230;
      color: #f8f9fa;
    }
    .activity-edit-shell .form-control:disabled,
    .activity-edit-shell .form-control[readonly],
    .activity-edit-shell .custom-select:disabled {
      background-color: rgba(255, 255, 255, 0.08);
      color: rgba(248, 249, 250, 0.72);
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
    .activity-edit-hero {
      position: relative;
      overflow: hidden;
      border: 1px solid rgba(255, 255, 255, 0.14);
      border-radius: 18px;
      padding: 18px 20px;
      margin-bottom: 16px;
      background:
        radial-gradient(circle at top right, rgba(255, 193, 7, 0.18), transparent 34%),
        linear-gradient(135deg, rgba(13, 110, 253, 0.22), rgba(32, 201, 151, 0.12));
      box-shadow: 0 16px 36px rgba(0, 0, 0, 0.2);
    }
    .activity-edit-hero::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0));
      pointer-events: none;
    }
    .activity-edit-hero-top {
      position: relative;
      z-index: 1;
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 14px;
    }
    .activity-edit-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 8px;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: rgba(255, 255, 255, 0.78);
    }
    .activity-edit-title {
      margin: 0;
      font-size: 1.5rem;
      font-weight: 800;
      line-height: 1.1;
      color: #fff;
    }
    .activity-edit-subtitle {
      margin: 6px 0 0;
      color: rgba(255, 255, 255, 0.76);
      font-size: 0.92rem;
      line-height: 1.45;
    }
    .activity-edit-badge {
      position: relative;
      z-index: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 132px;
      padding: 0.6rem 0.9rem;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.12);
      border: 1px solid rgba(255, 255, 255, 0.16);
      color: #fff;
      font-size: 0.78rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      backdrop-filter: blur(6px);
    }
    .activity-edit-meta {
      position: relative;
      z-index: 1;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 10px;
    }
    .activity-edit-meta-card {
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 14px;
      padding: 11px 12px;
      background: rgba(255, 255, 255, 0.08);
      backdrop-filter: blur(6px);
    }
    .activity-edit-meta-label {
      display: block;
      margin-bottom: 4px;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      color: rgba(255, 255, 255, 0.7);
    }
    .activity-edit-meta-value {
      display: block;
      color: #fff;
      font-size: 0.98rem;
      font-weight: 700;
      line-height: 1.35;
      word-break: break-word;
    }
    @media (max-width: 768px) {
      .activity-edit-hero-top {
        flex-direction: column;
      }
      .activity-edit-badge {
        min-width: 0;
      }
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
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .implementation-support-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    @media (max-width: 992px) {
      .social-prep-grid,
      .implementation-support-grid {
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
      align-items: flex-start;
      gap: 8px;
      margin-bottom: 6px;
      min-height: 2.6rem;
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
    .stage-phase-grid > div,
    .forum-date-grid > div {
      display: flex;
      flex-direction: column;
      align-self: stretch;
    }
    .stage-phase-grid > div > label,
    .forum-date-grid > div > label,
    .forum-card .date-range-field > label,
    .site-validation-item .date-range-field > label {
      min-height: 2.4rem;
      display: block;
    }
    .stage-phase-grid > div > .form-control,
    .stage-phase-grid > div > .custom-select,
    .stage-phase-grid > div > .date-range-field,
    .forum-date-grid > div > .form-control,
    .forum-date-grid > div > .custom-select,
    .forum-date-grid > div > .date-range-field {
      margin-top: auto;
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
    .barangay-pane-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 14px;
      padding: 14px 16px;
      margin: -2px -2px 4px;
      border-radius: 14px;
      background: linear-gradient(135deg, rgba(13, 110, 253, 0.16), rgba(32, 201, 151, 0.09));
      border: 1px solid rgba(255, 255, 255, 0.08);
    }
    .barangay-pane-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 6px;
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: rgba(255, 255, 255, 0.72);
    }
    .barangay-pane-title {
      margin: 0;
      font-size: 1.08rem;
      font-weight: 800;
      color: #fff;
      line-height: 1.2;
    }
    .barangay-pane-subtitle {
      margin: 4px 0 0;
      font-size: 0.84rem;
      color: rgba(255, 255, 255, 0.74);
      line-height: 1.45;
    }
    .barangay-pane-meta {
      display: flex;
      flex-wrap: wrap;
      justify-content: flex-end;
      gap: 8px;
    }
    .barangay-pane-actions {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-left: 8px;
    }
    .barangay-pane-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 0.45rem 0.7rem;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.12);
      color: #fff;
      font-size: 0.76rem;
      font-weight: 700;
      line-height: 1;
      white-space: nowrap;
    }
    .barangay-pane-toggle {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 2.2rem;
      height: 2.2rem;
      border: 1px solid rgba(255, 255, 255, 0.14);
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.08);
      color: #fff;
      cursor: pointer;
      transition: transform 0.18s ease, background 0.18s ease;
    }
    .edit-grid-row.is-collapsed .barangay-pane-toggle i {
      transform: rotate(-90deg);
    }
    .barangay-pane-toggle:hover {
      background: rgba(255, 255, 255, 0.14);
    }
    .barangay-pane-body {
      margin-top: 14px;
    }
    .edit-grid-row.is-collapsed .barangay-pane-body {
      display: none;
    }
    .barangay-tab-nav {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 14px;
    }
    .barangay-tab {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 0.58rem 0.9rem;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.12);
      background: rgba(255, 255, 255, 0.05);
      color: rgba(255, 255, 255, 0.82);
      font-size: 0.78rem;
      font-weight: 700;
      cursor: pointer;
      transition: background 0.18s ease, border-color 0.18s ease, color 0.18s ease;
    }
    .barangay-tab.is-active {
      background: rgba(13, 110, 253, 0.18);
      border-color: rgba(13, 110, 253, 0.38);
      color: #fff;
    }
    .barangay-tab-panel {
      display: none;
    }
    .barangay-tab-panel.is-active {
      display: grid;
      gap: 14px;
    }
    .barangay-panel-section {
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 14px;
      padding: 14px;
      background: rgba(255, 255, 255, 0.03);
    }
    .barangay-panel-section-title {
      display: block;
      margin-bottom: 10px;
      font-size: 0.8rem;
      font-weight: 800;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: rgba(255, 255, 255, 0.82);
    }
    .barangay-panel-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
    }
    @media (max-width: 768px) {
      .barangay-pane-header {
        flex-direction: column;
      }
      .barangay-pane-meta {
        justify-content: flex-start;
      }
    }
    .project-item .btn {
      flex: 0 0 auto;
    }
    .swal2-popup .activity-edit-shell .form-control,
    .swal2-popup .activity-edit-shell .custom-select {
      background: rgba(255, 255, 255, 0.08);
      border-color: rgba(255, 255, 255, 0.14);
      color: #f8f9fa;
    }
    .swal2-popup .activity-edit-shell .form-control:focus,
    .swal2-popup .activity-edit-shell .custom-select:focus {
      background: rgba(255, 255, 255, 0.12);
      border-color: rgba(13, 110, 253, 0.55);
      color: #fff;
      box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.16);
    }
    .swal2-popup .activity-edit-shell .form-control[readonly],
    .swal2-popup .activity-edit-shell .form-control:disabled,
    .swal2-popup .activity-edit-shell .custom-select:disabled {
      background: rgba(255, 255, 255, 0.05);
      color: rgba(255, 255, 255, 0.78);
    }
    .swal2-popup .activity-edit-shell label {
      color: rgba(255, 255, 255, 0.82);
    }
    .kodus-edit-popup {
      width: min(1180px, calc(100vw - 2rem)) !important;
      max-width: calc(100vw - 2rem) !important;
    }
    .swal2-container.kodus-scrollable-swal {
      align-items: flex-start !important;
      overflow-x: hidden !important;
      overflow-y: auto !important;
      padding: 1.75rem 0.75rem !important;
    }
    .swal2-container.kodus-scrollable-swal .swal2-popup {
      max-height: none !important;
      margin: 0 auto !important;
      overflow: visible !important;
    }
    .swal2-container.kodus-scrollable-swal .swal2-html-container {
      flex: none !important;
      max-height: none !important;
      overflow: visible !important;
    }
    .kodus-edit-popup .swal2-html-container {
      overflow-x: hidden;
    }
    .kodus-edit-popup .swal2-actions {
      margin-top: 0.85rem;
      padding-top: 0.85rem;
      width: 100%;
    }
    .activity-modal {
      text-align: left;
      color: inherit;
    }
    .activity-edit-shell,
    #edit-rows-container,
    .edit-grid-row,
    .barangay-pane-body,
    .barangay-tab-panel,
    .barangay-panel-section {
      min-width: 0;
      max-width: 100%;
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
    body[data-theme="light"] .activity-edit-hero {
      border-color: rgba(13, 110, 253, 0.14);
      background:
        radial-gradient(circle at top right, rgba(255, 193, 7, 0.18), transparent 34%),
        linear-gradient(135deg, #eef6ff 0%, #f6fbff 52%, #ffffff 100%);
      box-shadow: 0 0.8rem 1.8rem rgba(13, 110, 253, 0.12);
    }
    body[data-theme="light"] .activity-edit-hero::after {
      background: linear-gradient(180deg, rgba(255,255,255,0.4), rgba(255,255,255,0));
    }
    body[data-theme="light"] .activity-edit-eyebrow {
      color: #4a6785;
    }
    body[data-theme="light"] .activity-edit-title {
      color: #16324f;
    }
    body[data-theme="light"] .activity-edit-subtitle {
      color: #5f7488;
    }
    body[data-theme="light"] .activity-edit-badge {
      background: rgba(255, 255, 255, 0.82);
      border-color: rgba(13, 110, 253, 0.14);
      color: #0d6efd;
    }
    body[data-theme="light"] .activity-edit-meta-card {
      background: rgba(255, 255, 255, 0.74);
      border-color: rgba(13, 110, 253, 0.12);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.78);
    }
    body[data-theme="light"] .activity-edit-meta-label {
      color: #61758a;
    }
    body[data-theme="light"] .activity-edit-meta-value {
      color: #16324f;
    }
    body[data-theme="light"] .edit-grid-row {
      background: rgba(255, 255, 255, 0.9);
      border-color: rgba(13, 110, 253, 0.12);
    }
    body[data-theme="light"] .stage-phase-card {
      background: rgba(248, 250, 252, 0.95);
      border-color: rgba(13, 110, 253, 0.12);
    }
    body[data-theme="light"] .coverage-entry-item {
      background: rgba(248, 250, 252, 0.95);
      border-color: rgba(13, 110, 253, 0.12);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
    }
    body[data-theme="light"] .coverage-target-reference {
      color: #61758a;
    }
    body[data-theme="light"] .coverage-field-label,
    body[data-theme="light"] .coverage-entry-caption,
    body[data-theme="light"] .date-range-hint,
    body[data-theme="light"] .barangay-edit-header span {
      color: #61758a;
    }
    body[data-theme="light"] .stage-phase-card h6,
    body[data-theme="light"] .barangay-edit-header h6,
    body[data-theme="light"] .barangay-panel-section .section-note,
    body[data-theme="light"] .forum-date-grid label,
    body[data-theme="light"] .edit-grid-row label,
    body[data-theme="light"] .coverage-entry-item .text-muted {
      color: #405261 !important;
    }
    body[data-theme="light"] .readonly-display {
      background: rgba(108, 117, 125, 0.08);
      border-color: rgba(108, 117, 125, 0.32);
      color: #212529;
    }
    body[data-theme="light"] .activity-edit-shell .form-control,
    body[data-theme="light"] .activity-edit-shell .custom-select {
      background-color: #ffffff;
      border-color: rgba(13, 110, 253, 0.14);
      color: #1f2d3d;
    }
    body[data-theme="light"] .activity-edit-shell .form-control:focus,
    body[data-theme="light"] .activity-edit-shell .custom-select:focus {
      background-color: #ffffff;
      border-color: rgba(13, 110, 253, 0.35);
      color: #1f2d3d;
      box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.12);
    }
    body[data-theme="light"] .activity-edit-shell .custom-select option {
      background-color: #ffffff;
      color: #1f2d3d;
    }
    body[data-theme="light"] .activity-edit-shell .form-control:disabled,
    body[data-theme="light"] .activity-edit-shell .form-control[readonly],
    body[data-theme="light"] .activity-edit-shell .custom-select:disabled {
      background-color: rgba(108, 117, 125, 0.08);
      color: #61758a;
    }
    body[data-theme="light"] .activity-edit-section h6,
    body[data-theme="light"] .forum-card-title {
      color: #1f2d3d;
    }
    body[data-theme="light"] .activity-edit-section .section-note {
      color: #6c757d;
    }
    body[data-theme="light"] .barangay-pane-header {
      background: linear-gradient(135deg, rgba(13, 110, 253, 0.1), rgba(32, 201, 151, 0.08));
      border-color: rgba(13, 110, 253, 0.1);
    }
    body[data-theme="light"] .barangay-pane-eyebrow,
    body[data-theme="light"] .barangay-pane-subtitle {
      color: #5c6b7a;
    }
    body[data-theme="light"] .barangay-pane-title,
    body[data-theme="light"] .barangay-pane-pill {
      color: #1f2d3d;
    }
    body[data-theme="light"] .barangay-pane-pill {
      background: rgba(255, 255, 255, 0.72);
      border-color: rgba(13, 110, 253, 0.12);
    }
    body[data-theme="light"] .barangay-tab {
      background: rgba(255, 255, 255, 0.72);
      border-color: rgba(13, 110, 253, 0.1);
      color: #405261;
    }
    body[data-theme="light"] .barangay-tab.is-active {
      background: rgba(13, 110, 253, 0.12);
      border-color: rgba(13, 110, 253, 0.24);
      color: #16324f;
    }
    body[data-theme="light"] .barangay-panel-section {
      background: rgba(255, 255, 255, 0.84);
      border-color: rgba(13, 110, 253, 0.1);
    }
    body[data-theme="light"] .barangay-panel-section-title {
      color: #405261;
    }
    body[data-theme="light"] .barangay-pane-toggle {
      color: #1f2d3d;
      background: rgba(255, 255, 255, 0.74);
      border-color: rgba(13, 110, 253, 0.12);
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
    body[data-theme="light"] .swal2-popup .activity-edit-shell .custom-select {
      background-color: #ffffff;
      border-color: rgba(13, 110, 253, 0.14);
      color: #212529;
    }
    body[data-theme="light"] .swal2-popup .activity-edit-shell .form-control:focus,
    body[data-theme="light"] .swal2-popup .activity-edit-shell .custom-select:focus {
      background: #ffffff;
      color: #212529;
    }
    body[data-theme="light"] .swal2-popup .activity-edit-shell .form-control[readonly],
    body[data-theme="light"] .swal2-popup .activity-edit-shell .form-control:disabled,
    body[data-theme="light"] .swal2-popup .activity-edit-shell .custom-select:disabled,
    body[data-theme="light"] .swal2-popup .activity-edit-shell label {
      color: #495057;
    }
    body[data-theme="light"] .swal2-popup .activity-edit-shell .date-range-input {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='rgba(64,82,97,0.88)' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cline x1='16' y1='2' x2='16' y2='6'/%3E%3Cline x1='8' y1='2' x2='8' y2='6'/%3E%3Cline x1='3' y1='10' x2='21' y2='10'/%3E%3C/svg%3E") !important;
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
    .kodus-track-documents-btn {
      color: #9ec5fe;
      border-color: rgba(13, 110, 253, 0.55);
      background: rgba(13, 110, 253, 0.12);
    }
    .kodus-track-documents-btn:hover,
    .kodus-track-documents-btn:focus {
      color: #ffffff;
      border-color: #2f80ff;
      background: rgba(13, 110, 253, 0.28);
      box-shadow: 0 0 0 0.16rem rgba(13, 110, 253, 0.18);
    }
    body[data-theme="light"] .kodus-track-documents-btn {
      color: #0d6efd;
      border-color: rgba(13, 110, 253, 0.38);
      background: rgba(13, 110, 253, 0.04);
    }
    body[data-theme="light"] .kodus-track-documents-btn:hover,
    body[data-theme="light"] .kodus-track-documents-btn:focus {
      color: #ffffff;
      background: #0d6efd;
      border-color: #0d6efd;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
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
            <?php if (!$canManageActivities): ?>
              <div class="viewer-note">
                <strong>Viewer mode:</strong> You can browse implementation status details, but only administrators and implementation editors can edit activity records.
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
              <button id="track-documents" class="btn btn-outline-primary btn-xs kodus-track-documents-btn">Track Incoming Documents</button>
            </div>

            <div class="table-container kodus-table-scroll" style="--kodus-table-min-width: 1400px;">
              <table id="program-activities-table" class="table table-bordered table-striped" style="text-align: center; width:100%; table-layout: auto;">
                <thead style="font-size: 10px;">
                  <tr>
                    <th rowspan="2">Action</th>
                    <th rowspan="2" style="display:none;">Province</th>
                    <th rowspan="2">Municipality</th>
                    <th rowspan="2">LAWA Target</th>
                    <th rowspan="2">BINHI Target</th>
                    <th rowspan="2">CapBuild Target</th>
                    <th rowspan="2">Community Action Plan Target</th>
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

<script src="<?php echo $app_root; ?>plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
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
<script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>

<script>
$(document).ready(function() {
    const canManageActivities = <?= $canManageActivities ? 'true' : 'false' ?>;
    const BENEFICIARY_WAGE_RATE = 435;
    const BENEFICIARY_WORK_DAYS = 20;
    const BENEFICIARY_RATE = BENEFICIARY_WAGE_RATE * BENEFICIARY_WORK_DAYS;

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

    function splitMultiValue(value) {
        const raw = String(value ?? '');
        if (!raw.trim()) {
            return [];
        }

        return raw
            .split(raw.includes('||') ? /\|\|/ : /\s*,\s*/)
            .map(item => item.trim())
            .filter(Boolean);
    }

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

    function registerCoverageTypeOption(classification, typeValue) {
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

    function renderCoverageTypeOptions(classification, selectedType) {
        const normalizedClassification = String(classification || '').trim().toUpperCase();
        const normalizedSelectedType = String(selectedType || '').trim();
        const options = COVERAGE_TYPE_OPTIONS[normalizedClassification] || [];
        const hasPresetMatch = normalizedSelectedType !== '' && options.includes(normalizedSelectedType);
        const optionHtml = options.map((option) => `
            <option value="${escapeHtml(option)}" ${normalizedSelectedType === option ? 'selected' : ''}>${escapeHtml(option)}</option>
        `).join('');

        return `
            <option value="" disabled ${normalizedSelectedType === '' ? 'selected' : ''}>Type</option>
            ${optionHtml}
            <option value="__custom__" ${normalizedSelectedType !== '' && !hasPresetMatch ? 'selected' : ''}>Custom type</option>
        `;
    }

    function renderCoverageTypeField(classification, selectedType) {
        const normalizedClassification = String(classification || '').trim().toUpperCase();
        const normalizedSelectedType = String(selectedType || '').trim();
        const options = COVERAGE_TYPE_OPTIONS[normalizedClassification] || [];
        const isCustom = normalizedSelectedType !== '' && !options.includes(normalizedSelectedType);

        return `
            <div class="coverage-type-field">
                <select class="custom-select custom-select-sm coverage-project-type">
                    ${renderCoverageTypeOptions(normalizedClassification, normalizedSelectedType)}
                </select>
                <input type="text" class="form-control form-control-sm coverage-project-type-custom mt-1 ${isCustom ? '' : 'd-none'}" value="${escapeHtml(isCustom ? normalizedSelectedType : '')}" placeholder="Enter custom type">
            </div>
        `;
    }

    function getCoverageTypeValue($item) {
        const selectedType = String($item.find('.coverage-project-type').val() || '').trim();
        if (selectedType === '__custom__') {
            return String($item.find('.coverage-project-type-custom').val() || '').trim();
        }

        return selectedType;
    }

    function syncCoverageTypeField($item, selectedType = '') {
        const $customInput = $item.find('.coverage-project-type-custom');
        if (String($item.find('.coverage-project-type').val() || '').trim() === '__custom__') {
            $customInput.removeClass('d-none');
            if (selectedType && !$customInput.val()) {
                $customInput.val(selectedType);
            }
            return;
        }

        $customInput.addClass('d-none').val('');
    }

    function persistCustomCoverageType($item) {
        const classification = String($item.find('.coverage-project-classification').val() || '').trim().toUpperCase();
        const customValue = String($item.find('.coverage-project-type-custom').val() || '').trim();
        if (!['LAWA', 'BINHI'].includes(classification) || customValue === '') {
            return;
        }

        registerCoverageTypeOption(classification, customValue);
        $item.find('.coverage-type-field').replaceWith(renderCoverageTypeField(classification, customValue));
        syncCoverageTypeField($item, customValue);
        syncCoverageAquaticFields($item);
    }

    function requiresCoverageAquaticPrompt(classification, typeValue) {
        return String(classification || '').trim().toUpperCase() === 'LAWA'
            && AQUATIC_RESOURCE_PROJECT_TYPES.includes(String(typeValue || '').trim());
    }

    function shouldShowCoverageAquaticFields($item) {
        return String($item.find('.coverage-aquatic-enabled').val() || '').trim() === '1';
    }

    function renderCoverageAquaticResourceOptions(selectedResource) {
        const normalizedSelectedResource = String(selectedResource || '').trim().toUpperCase();
        const isCustom = normalizedSelectedResource !== '' && !AQUATIC_RESOURCE_TYPE_OPTIONS.includes(normalizedSelectedResource);
        const optionHtml = AQUATIC_RESOURCE_TYPE_OPTIONS.map((option) => `
            <option value="${escapeHtml(option)}" ${normalizedSelectedResource === option ? 'selected' : ''}>${escapeHtml(option)}</option>
        `).join('');

        return `
            <option value="" disabled ${normalizedSelectedResource === '' ? 'selected' : ''}>Aquatic resource</option>
            ${optionHtml}
            <option value="__custom__" ${isCustom ? 'selected' : ''}>Custom input</option>
        `;
    }

    function renderCoverageAquaticField(selectedResource, selectedQuantity, isVisible) {
        const normalizedSelectedResource = String(selectedResource || '').trim().toUpperCase();
        const customValue = normalizedSelectedResource !== '' && !AQUATIC_RESOURCE_TYPE_OPTIONS.includes(normalizedSelectedResource)
            ? selectedResource
            : '';

        return `
            <input type="hidden" class="coverage-aquatic-enabled" value="${isVisible ? '1' : '0'}">
            <div class="coverage-field coverage-field--aquatic-resource ${isVisible ? '' : 'd-none'}">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="coverage-field-label mb-0">Aquatic Resource</label>
                    <button type="button" class="btn btn-outline-secondary btn-xs coverage-aquatic-hide-btn">Hide</button>
                </div>
                <select class="custom-select custom-select-sm coverage-aquatic-resource">
                    ${renderCoverageAquaticResourceOptions(normalizedSelectedResource)}
                </select>
                <input type="text" class="form-control form-control-sm coverage-aquatic-resource-custom mt-1" value="${escapeHtml(customValue)}" placeholder="Custom aquatic resource (optional)">
            </div>
            <div class="coverage-field coverage-field--aquatic-quantity ${isVisible ? '' : 'd-none'}">
                <label class="coverage-field-label">Aquatic Quantity</label>
                <input type="number" min="0" class="form-control form-control-sm coverage-aquatic-resource-quantity" value="${escapeHtml(selectedQuantity || '')}" placeholder="Quantity">
            </div>
        `;
    }

    function registerCoverageAquaticResourceOption(resourceValue) {
        const normalizedResourceValue = String(resourceValue || '').trim().toUpperCase();
        if (normalizedResourceValue === '') {
            return;
        }

        if (!AQUATIC_RESOURCE_TYPE_OPTIONS.includes(normalizedResourceValue)) {
            AQUATIC_RESOURCE_TYPE_OPTIONS.push(normalizedResourceValue);
        }
    }

    function getCoverageAquaticResourceValue($item) {
        const selectedResource = String($item.find('.coverage-aquatic-resource').val() || '').trim();
        if (selectedResource === '__custom__') {
            return String($item.find('.coverage-aquatic-resource-custom').val() || '').trim();
        }

        const customResource = String($item.find('.coverage-aquatic-resource-custom').val() || '').trim();
        if (customResource !== '') {
            return customResource;
        }

        return selectedResource;
    }

    function syncCoverageAquaticFields($item) {
        const classification = String($item.find('.coverage-project-classification').val() || '').trim().toUpperCase();
        const projectType = getCoverageTypeValue($item);
        const shouldPrompt = requiresCoverageAquaticPrompt(classification, projectType);
        const shouldShow = shouldPrompt && shouldShowCoverageAquaticFields($item);
        const $resourceField = $item.find('.coverage-field--aquatic-resource');
        const $quantityField = $item.find('.coverage-field--aquatic-quantity');
        const $customInput = $item.find('.coverage-aquatic-resource-custom');

        $resourceField.toggleClass('d-none', !shouldShow);
        $quantityField.toggleClass('d-none', !shouldShow);

        if (!shouldPrompt) {
            $item.find('.coverage-aquatic-enabled').val('0');
            $item.find('.coverage-aquatic-resource').val('');
            $customInput.val('');
            $item.find('.coverage-aquatic-resource-quantity').val('');
            return;
        }

        if (!shouldShow) {
            $item.find('.coverage-aquatic-resource').val('');
            $customInput.val('');
            $item.find('.coverage-aquatic-resource-quantity').val('');
            return;
        }

        if (String($item.find('.coverage-aquatic-resource').val() || '').trim() === '__custom__') {
            $customInput.removeClass('d-none');
        } else {
            $customInput.addClass('d-none').val('');
        }
    }

    function persistCustomCoverageAquaticResource($item) {
        const selectedResource = String($item.find('.coverage-aquatic-resource').val() || '').trim();
        const customValue = String($item.find('.coverage-aquatic-resource-custom').val() || '').trim();
        if (selectedResource !== '__custom__' || customValue === '') {
            return;
        }

        registerCoverageAquaticResourceOption(customValue);
        $item.find('.coverage-aquatic-resource').html(renderCoverageAquaticResourceOptions(customValue));
        $item.find('.coverage-aquatic-resource').val(String(customValue).trim().toUpperCase());
        $item.find('.coverage-aquatic-resource-custom').addClass('d-none').val('');
    }

    function promptCoverageAquaticDecision($item) {
        const classification = String($item.find('.coverage-project-classification').val() || '').trim().toUpperCase();
        const projectType = getCoverageTypeValue($item);
        if (!requiresCoverageAquaticPrompt(classification, projectType)) {
            $item.find('.coverage-aquatic-enabled').val('0');
            syncCoverageAquaticFields($item);
            return;
        }

        const hasExistingValues = getCoverageAquaticResourceValue($item) !== '' || String($item.find('.coverage-aquatic-resource-quantity').val() || '').trim() !== '';
        if (hasExistingValues) {
            $item.find('.coverage-aquatic-enabled').val('1');
            syncCoverageAquaticFields($item);
            return;
        }

        const includesAquaticResources = window.confirm('Does this project include aquatic resources?');
        $item.find('.coverage-aquatic-enabled').val(includesAquaticResources ? '1' : '0');
        syncCoverageAquaticFields($item);
    }

    function supportsTypeAccomplishment(classification) {
        const normalized = String(classification || '').trim().toUpperCase();
        return normalized === 'LAWA' || normalized === 'BINHI';
    }

    function normalizeCoverageStatus(status) {
        const normalized = String(status || '').trim().toLowerCase();
        if (normalized === 'confirmed' || normalized === 'custom') {
            return normalized;
        }
        return 'pending';
    }

    function isCoverageStatusActive(status) {
        return normalizeCoverageStatus(status) !== 'pending';
    }

    function renderCoverageActionButtons(status, hasTargetReference) {
        const normalizedStatus = normalizeCoverageStatus(status);
        const confirmLabel = hasTargetReference ? 'Use Target as Actual' : 'Use Entry as Actual';

        if (normalizedStatus === 'confirmed') {
            return `
                <button type="button" class="btn btn-success btn-sm confirm-target-btn">${confirmLabel}</button>
                <button type="button" class="btn btn-primary btn-sm edit-actual-btn">Edit Actual</button>
                <button type="button" class="btn btn-outline-secondary btn-sm reset-coverage-btn">Reset</button>
            `;
        }

        if (normalizedStatus === 'custom') {
            return `
                <button type="button" class="btn btn-success btn-sm confirm-target-btn">${confirmLabel}</button>
                <button type="button" class="btn btn-primary btn-sm edit-actual-btn">Actual is Different</button>
                <button type="button" class="btn btn-outline-secondary btn-sm reset-coverage-btn">Reset</button>
            `;
        }

        return `
            <button type="button" class="btn btn-success btn-sm confirm-target-btn">${confirmLabel}</button>
            <button type="button" class="btn btn-primary btn-sm edit-actual-btn">Actual is Different</button>
            <button type="button" class="btn btn-outline-secondary btn-sm reset-coverage-btn">Reset</button>
        `;
    }

    function isBinhiGardenCoverageProject(classification, projectName) {
        const normalizedClassification = String(classification || '').trim().toUpperCase();
        const normalizedName = String(projectName || '').trim().toLowerCase();
        return normalizedClassification === 'BINHI' && /(garden|gardening|gulayan|backyard\s+garden|communal\s+garden|school\s+garden|container\s+garden|vegetable\s+garden|herbal\s+garden|orchard|nursery)/.test(normalizedName);
    }

    function renderCoverageFertilizerFollowup(classification, projectName, enabledFlag, ohnQuantity, concoctionQuantity, vermicompostQuantity, rowKey) {
        const visible = isBinhiGardenCoverageProject(classification, projectName) || String(enabledFlag || '').trim() !== '' || String(ohnQuantity || '').trim() !== '' || String(concoctionQuantity || '').trim() !== '' || String(vermicompostQuantity || '').trim() !== '';
        const enabled = String(enabledFlag || '').trim() === '1';

        return `
            <div class="coverage-fertilizer-followup ${visible ? 'is-visible' : ''}">
                <input type="hidden" class="coverage-fertilizer-enabled" value="${escapeHtml(String(enabledFlag || '').trim())}">
                <label class="coverage-field-label">Does this project produce/reproduce Fertilizers?</label>
                <div class="coverage-fertilizer-options">
                    <label><input type="radio" class="coverage-fertilizer-enabled-choice" name="coverage-fertilizer-${escapeHtml(rowKey)}" value="1" ${enabled ? 'checked' : ''}> Yes</label>
                    <label><input type="radio" class="coverage-fertilizer-enabled-choice" name="coverage-fertilizer-${escapeHtml(rowKey)}" value="0" ${String(enabledFlag || '').trim() === '0' ? 'checked' : ''}> No</label>
                </div>
                <div class="coverage-fertilizer-grid coverage-fertilizer-fields ${enabled ? '' : 'd-none'}">
                    <div>
                        <label class="coverage-field-label">Oriental Herbal Nutrients (liters)</label>
                        <input type="number" min="0" step="0.01" class="form-control form-control-sm coverage-fertilizer-ohn" value="${escapeHtml(String(ohnQuantity || ''))}">
                    </div>
                    <div>
                        <label class="coverage-field-label">Concoction/Vermitea (liters)</label>
                        <input type="number" min="0" step="0.01" class="form-control form-control-sm coverage-fertilizer-concoction" value="${escapeHtml(String(concoctionQuantity || ''))}">
                    </div>
                    <div>
                        <label class="coverage-field-label">Vermicompost/Vermicast (kg)</label>
                        <input type="number" min="0" step="0.01" class="form-control form-control-sm coverage-fertilizer-vermicompost" value="${escapeHtml(String(vermicompostQuantity || ''))}">
                    </div>
                </div>
            </div>
        `;
    }

    function syncCoverageFertilizerFollowup($item) {
        const classification = String($item.find('.coverage-project-classification').val() || '').trim().toUpperCase();
        const projectName = String($item.find('.coverage-project-name').val() || '').trim();
        const shouldShow = isBinhiGardenCoverageProject(classification, projectName);
        const enabled = String($item.find('.coverage-fertilizer-enabled').val() || '').trim() === '1';

        $item.find('.coverage-fertilizer-followup').toggleClass('is-visible', shouldShow);
        if (!shouldShow) {
            $item.find('.coverage-fertilizer-enabled').val('');
            $item.find('.coverage-fertilizer-enabled-choice').prop('checked', false);
            $item.find('.coverage-fertilizer-fields').addClass('d-none');
            $item.find('.coverage-fertilizer-ohn, .coverage-fertilizer-concoction, .coverage-fertilizer-vermicompost').val('');
            return;
        }

        $item.find('.coverage-fertilizer-fields').toggleClass('d-none', !enabled);
    }

    function normalizeCoordinateInputValue(value) {
        return String(value || '').trim().replace(/\s+/g, '');
    }

    function generateProjectRowId(prefix = 'pa') {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return `${prefix}-${window.crypto.randomUUID()}`;
        }

        return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
    }

    function reindexCoverageEntries($container) {
        $container.find('.coverage-entry-item').each(function(index) {
            $(this).attr('data-entry-index', index);
        });
    }

    function insertCoverageEntryAfter($anchorItem) {
        const $list = $anchorItem.closest('.coverage-list');
        const inheritedTargetRowId = String($anchorItem.find('.target-project-row-id').val() || '').trim();
        const inheritedTargetPurok = String($anchorItem.find('.coverage-target-purok').val() || '').trim();
        const inheritedTargetName = String($anchorItem.find('.coverage-target-project-name').val() || '').trim();
        const inheritedTargetClassification = String($anchorItem.find('.coverage-target-project-classification').val() || '').trim();
        const inheritedTargetType = String($anchorItem.find('.coverage-target-project-type').val() || '').trim();
        const inheritedFertilizerEnabled = String($anchorItem.find('.coverage-target-fertilizer-enabled').val() || '').trim();
        const inheritedFertilizerOhn = String($anchorItem.find('.coverage-target-fertilizer-ohn').val() || '').trim();
        const inheritedFertilizerConcoction = String($anchorItem.find('.coverage-target-fertilizer-concoction').val() || '').trim();
        const inheritedFertilizerVermicompost = String($anchorItem.find('.coverage-target-fertilizer-vermicompost').val() || '').trim();
        const inheritedAquaticResource = String($anchorItem.find('.coverage-target-aquatic-resource').val() || '').trim();
        const inheritedAquaticResourceQuantity = String($anchorItem.find('.coverage-target-aquatic-resource-quantity').val() || '').trim();
        const html = renderCoverageInputs(
            [inheritedTargetRowId],
            [inheritedTargetPurok],
            [inheritedTargetName],
            [inheritedTargetClassification],
            [inheritedTargetType],
            [inheritedFertilizerEnabled],
            [inheritedFertilizerOhn],
            [inheritedFertilizerConcoction],
            [inheritedFertilizerVermicompost],
            [inheritedAquaticResource],
            [inheritedAquaticResourceQuantity],
            [''],
            [inheritedTargetRowId],
            [''],
            [''],
            [''],
            [''],
            [''],
            [''],
            [''],
            [''],
            [''],
            [''],
            [''],
            [''],
            [''],
            [''],
            [''],
            [''],
            ['custom']
        );
        const $newItem = $(html);
        $anchorItem.after($newItem);
        reindexCoverageEntries($list);
        const $insertedItem = $anchorItem.next('.coverage-entry-item');
        return $insertedItem;
    }

    function renderCoverageInputs(targetRowIds, targetPuroks, targetNames, targetClassifications, targetTypes, targetFertilizerEnabledFlags, targetFertilizerOhnTargets, targetFertilizerConcoctionTargets, targetFertilizerVermicompostTargets, targetAquaticResources, targetAquaticResourceQuantities, actualProjectIds, targetProjectRowIdsForActuals, actualPuroks, actualLatitudes, actualLongitudes, actualNames, actualClassifications, actualTypes, actualFertilizerEnabledFlags, actualFertilizerOhnQuantities, actualFertilizerConcoctionQuantities, actualFertilizerVermicompostQuantities, actualAquaticResources, actualAquaticResourceQuantities, accomplishments, landAreas, landOwnerships, driveLinks, statuses) {
        const targetRowIdList = Array.isArray(targetRowIds) ? targetRowIds : [];
        const targetPurokList = Array.isArray(targetPuroks) ? targetPuroks : [];
        const targetNameList = Array.isArray(targetNames) ? targetNames : [];
        const targetClassificationList = Array.isArray(targetClassifications) ? targetClassifications : [];
        const targetTypeList = Array.isArray(targetTypes) ? targetTypes : [];
        const targetFertilizerEnabledList = Array.isArray(targetFertilizerEnabledFlags) ? targetFertilizerEnabledFlags : [];
        const targetFertilizerOhnList = Array.isArray(targetFertilizerOhnTargets) ? targetFertilizerOhnTargets : [];
        const targetFertilizerConcoctionList = Array.isArray(targetFertilizerConcoctionTargets) ? targetFertilizerConcoctionTargets : [];
        const targetFertilizerVermicompostList = Array.isArray(targetFertilizerVermicompostTargets) ? targetFertilizerVermicompostTargets : [];
        const targetAquaticResourceList = Array.isArray(targetAquaticResources) ? targetAquaticResources : [];
        const targetAquaticResourceQuantityList = Array.isArray(targetAquaticResourceQuantities) ? targetAquaticResourceQuantities : [];
        const actualProjectIdList = Array.isArray(actualProjectIds) ? actualProjectIds : [];
        const targetProjectRowIdList = Array.isArray(targetProjectRowIdsForActuals) ? targetProjectRowIdsForActuals : [];
        const actualPurokList = Array.isArray(actualPuroks) ? actualPuroks : [];
        const actualLatitudeList = Array.isArray(actualLatitudes) ? actualLatitudes : [];
        const actualLongitudeList = Array.isArray(actualLongitudes) ? actualLongitudes : [];
        const actualNameList = Array.isArray(actualNames) ? actualNames : [];
        const actualClassificationList = Array.isArray(actualClassifications) ? actualClassifications : [];
        const actualTypeList = Array.isArray(actualTypes) ? actualTypes : [];
        const actualFertilizerEnabledList = Array.isArray(actualFertilizerEnabledFlags) ? actualFertilizerEnabledFlags : [];
        const actualFertilizerOhnList = Array.isArray(actualFertilizerOhnQuantities) ? actualFertilizerOhnQuantities : [];
        const actualFertilizerConcoctionList = Array.isArray(actualFertilizerConcoctionQuantities) ? actualFertilizerConcoctionQuantities : [];
        const actualFertilizerVermicompostList = Array.isArray(actualFertilizerVermicompostQuantities) ? actualFertilizerVermicompostQuantities : [];
        const actualAquaticResourceList = Array.isArray(actualAquaticResources) ? actualAquaticResources : [];
        const actualAquaticResourceQuantityList = Array.isArray(actualAquaticResourceQuantities) ? actualAquaticResourceQuantities : [];
        const actualAccomplishments = Array.isArray(accomplishments) ? accomplishments : [];
        const coverageLandAreas = Array.isArray(landAreas) ? landAreas : [];
        const coverageLandOwnerships = Array.isArray(landOwnerships) ? landOwnerships : [];
        const coverageDriveLinks = Array.isArray(driveLinks) ? driveLinks : [];
        const coverageStatuses = Array.isArray(statuses) ? statuses : [];
        const count = Math.max(
            targetRowIdList.length,
            targetPurokList.length,
            targetNameList.length,
            targetClassificationList.length,
            targetTypeList.length,
            targetFertilizerEnabledList.length,
            targetFertilizerOhnList.length,
            targetFertilizerConcoctionList.length,
            targetFertilizerVermicompostList.length,
            targetAquaticResourceList.length,
            targetAquaticResourceQuantityList.length,
            actualProjectIdList.length,
            targetProjectRowIdList.length,
            actualPurokList.length,
            actualLatitudeList.length,
            actualLongitudeList.length,
            actualNameList.length,
            actualClassificationList.length,
            actualTypeList.length,
            actualFertilizerEnabledList.length,
            actualFertilizerOhnList.length,
            actualFertilizerConcoctionList.length,
            actualFertilizerVermicompostList.length,
            actualAquaticResourceList.length,
            actualAquaticResourceQuantityList.length,
            actualAccomplishments.length,
            coverageLandAreas.length,
            coverageLandOwnerships.length,
            coverageDriveLinks.length,
            coverageStatuses.length,
            1
        );
        const rows = [];

        for (let i = 0; i < count; i++) {
            const targetRowId = String(targetRowIdList[i] || '').trim() || generateProjectRowId('pt');
            const targetPurok = String(targetPurokList[i] || '').trim();
            const targetName = String(targetNameList[i] || '').trim();
            const targetClassification = String(targetClassificationList[i] || '').trim().toUpperCase();
            const targetType = String(targetTypeList[i] || '').trim();
            const targetFertilizerEnabled = String(targetFertilizerEnabledList[i] || '').trim();
            const targetFertilizerOhn = String(targetFertilizerOhnList[i] || '').trim();
            const targetFertilizerConcoction = String(targetFertilizerConcoctionList[i] || '').trim();
            const targetFertilizerVermicompost = String(targetFertilizerVermicompostList[i] || '').trim();
            const targetAquaticResource = String(targetAquaticResourceList[i] || '').trim();
            const targetAquaticResourceQuantity = String(targetAquaticResourceQuantityList[i] || '').trim();
            const hasTargetReference = [targetPurok, targetName, targetClassification, targetType, targetFertilizerEnabled, targetFertilizerOhn, targetFertilizerConcoction, targetFertilizerVermicompost, targetAquaticResource, targetAquaticResourceQuantity].some(Boolean);
            const hasExistingActual = [
                actualPurokList[i],
                actualLatitudeList[i],
                actualLongitudeList[i],
                actualNameList[i],
                actualClassificationList[i],
                actualTypeList[i],
                actualFertilizerEnabledList[i],
                actualFertilizerOhnList[i],
                actualFertilizerConcoctionList[i],
                actualFertilizerVermicompostList[i],
                actualAquaticResourceList[i],
                actualAquaticResourceQuantityList[i],
                actualAccomplishments[i],
                coverageLandAreas[i],
                coverageLandOwnerships[i]
            ].some((value) => String(value || '').trim() !== '');
            const status = normalizeCoverageStatus(coverageStatuses[i] || (hasExistingActual ? 'custom' : 'pending'));
            const actualProjectId = String(actualProjectIdList[i] || '').trim() || generateProjectRowId('pa');
            const linkedTargetProjectRowId = String(targetProjectRowIdList[i] || '').trim() || targetRowId;
            const actualPurok = String(actualPurokList[i] || (status === 'confirmed' ? targetPurok : '')).trim();
            const actualLatitude = normalizeCoordinateInputValue(actualLatitudeList[i] || '');
            const actualLongitude = normalizeCoordinateInputValue(actualLongitudeList[i] || '');
            const actualName = String(actualNameList[i] || (status === 'confirmed' ? targetName : '')).trim();
            const actualClassification = String(actualClassificationList[i] || (status === 'confirmed' ? targetClassification : '')).trim().toUpperCase();
            const actualType = String(actualTypeList[i] || (status === 'confirmed' ? targetType : '')).trim();
            const actualFertilizerEnabled = String(actualFertilizerEnabledList[i] || (status === 'confirmed' ? targetFertilizerEnabled : '')).trim();
            const actualFertilizerOhn = String(actualFertilizerOhnList[i] || (status === 'confirmed' ? targetFertilizerOhn : '')).trim();
            const actualFertilizerConcoction = String(actualFertilizerConcoctionList[i] || (status === 'confirmed' ? targetFertilizerConcoction : '')).trim();
            const actualFertilizerVermicompost = String(actualFertilizerVermicompostList[i] || (status === 'confirmed' ? targetFertilizerVermicompost : '')).trim();
            const actualAquaticResource = String(actualAquaticResourceList[i] || (status === 'confirmed' ? targetAquaticResource : '')).trim();
            const actualAquaticResourceQuantity = String(actualAquaticResourceQuantityList[i] || (status === 'confirmed' ? targetAquaticResourceQuantity : '')).trim();
            const actualAccomplishment = String(actualAccomplishments[i] || '').trim();
            const actualDriveLink = String(coverageDriveLinks[i] || '').trim();
            const supportsAccomplishment = supportsTypeAccomplishment(actualClassification || targetClassification || '');
            const showsAquaticFields = String(actualAquaticResource || '').trim() !== '' || String(actualAquaticResourceQuantity || '').trim() !== '';
            const targetSummaryParts = [];

            if (targetPurok) targetSummaryParts.push(`Purok: ${escapeHtml(targetPurok)}`);
            if (targetName) targetSummaryParts.push(`Project: ${escapeHtml(targetName)}`);
            if (targetClassification) targetSummaryParts.push(`Classification: ${escapeHtml(targetClassification)}`);
            if (targetType) targetSummaryParts.push(`Type: ${escapeHtml(targetType)}`);
            if (targetFertilizerEnabled === '1') {
                const fertilizerParts = [];
                if (targetFertilizerOhn) fertilizerParts.push(`OHN: ${escapeHtml(targetFertilizerOhn)} L`);
                if (targetFertilizerConcoction) fertilizerParts.push(`Concoction/Vermitea: ${escapeHtml(targetFertilizerConcoction)} L`);
                if (targetFertilizerVermicompost) fertilizerParts.push(`Vermicompost/Vermicast: ${escapeHtml(targetFertilizerVermicompost)} kg`);
                if (fertilizerParts.length) targetSummaryParts.push(`Fertilizers: ${fertilizerParts.join(', ')}`);
            }
            if (targetAquaticResource) targetSummaryParts.push(`Aquatic resource: ${escapeHtml(targetAquaticResource)}`);
            if (targetAquaticResourceQuantity) targetSummaryParts.push(`Quantity: ${escapeHtml(targetAquaticResourceQuantity)}`);

            const rowKey = `coverage-${i}-${Math.random().toString(36).slice(2, 8)}`;
            rows.push(`
                <div class="coverage-entry-item is-${escapeHtml(status)}" data-origin="${hasTargetReference ? 'target' : 'manual'}" data-entry-index="${i}">
                    <input type="hidden" class="actual-project-id" value="${escapeHtml(actualProjectId)}">
                    <input type="hidden" class="coverage-entry-id" value="${escapeHtml(actualProjectId)}">
                    <input type="hidden" class="coverage-status" value="${escapeHtml(status)}">
                    <input type="hidden" class="target-project-row-id" value="${escapeHtml(linkedTargetProjectRowId)}">
                    <input type="hidden" class="coverage-target-row-id" value="${escapeHtml(linkedTargetProjectRowId)}">
                    <input type="hidden" class="coverage-target-purok" value="${escapeHtml(targetPurok)}">
                    <input type="hidden" class="coverage-target-project-name" value="${escapeHtml(targetName)}">
                    <input type="hidden" class="coverage-target-project-classification" value="${escapeHtml(targetClassification)}">
                    <input type="hidden" class="coverage-target-project-type" value="${escapeHtml(targetType)}">
                    <input type="hidden" class="coverage-target-fertilizer-enabled" value="${escapeHtml(targetFertilizerEnabled)}">
                    <input type="hidden" class="coverage-target-fertilizer-ohn" value="${escapeHtml(targetFertilizerOhn)}">
                    <input type="hidden" class="coverage-target-fertilizer-concoction" value="${escapeHtml(targetFertilizerConcoction)}">
                    <input type="hidden" class="coverage-target-fertilizer-vermicompost" value="${escapeHtml(targetFertilizerVermicompost)}">
                    <input type="hidden" class="coverage-target-aquatic-resource" value="${escapeHtml(targetAquaticResource)}">
                    <input type="hidden" class="coverage-target-aquatic-resource-quantity" value="${escapeHtml(targetAquaticResourceQuantity)}">
                    <div class="coverage-entry-header">
                        <div>
                            <div class="coverage-entry-status-pill coverage-entry-status-pill--${escapeHtml(status)}">${escapeHtml(status === 'pending' ? 'Awaiting confirmation' : status === 'confirmed' ? 'Target confirmed as actual' : 'Custom actual recorded')}</div>
                            <div class="coverage-target-reference">
                                ${targetSummaryParts.length ? targetSummaryParts.join(' | ') : 'No linked target reference for this row yet.'}
                            </div>
                        </div>
                    </div>
                    <div class="coverage-entry-grid">
                        <div class="coverage-field coverage-field--purok">
                            <label class="coverage-field-label">Actual Purok</label>
                            <input type="text" class="form-control form-control-sm coverage-purok" value="${escapeHtml(actualPurok)}" placeholder="Enter actual purok">
                        </div>
                        <div class="coverage-field coverage-field--coordinate-pair">
                            <label class="coverage-field-label">Coordinates</label>
                            <div class="coverage-coordinate-row">
                                <input type="text" class="form-control form-control-sm coverage-latitude" value="${escapeHtml(actualLatitude)}" placeholder="Latitude e.g. 8.93163" inputmode="decimal">
                                <input type="text" class="form-control form-control-sm coverage-longitude" value="${escapeHtml(actualLongitude)}" placeholder="Longitude e.g. 125.37904" inputmode="decimal">
                            </div>
                        </div>
                        <div class="coverage-field coverage-field--project">
                            <label class="coverage-field-label">Actual Project Name</label>
                            <input type="text" class="form-control form-control-sm coverage-project-name" value="${escapeHtml(actualName)}" placeholder="Enter actual project name">
                        </div>
                        <div class="coverage-field coverage-field--classification">
                            <label class="coverage-field-label">Actual Classification</label>
                            <select class="custom-select custom-select-sm coverage-project-classification">
                                <option value="" disabled ${actualClassification === '' ? 'selected' : ''}>Classification</option>
                                <option value="LAWA" ${actualClassification === 'LAWA' ? 'selected' : ''}>LAWA</option>
                                <option value="BINHI" ${actualClassification === 'BINHI' ? 'selected' : ''}>BINHI</option>
                            </select>
                        </div>
                        <div class="coverage-field coverage-field--type">
                            <label class="coverage-field-label">Actual Type</label>
                            ${renderCoverageTypeField(actualClassification || targetClassification, actualType)}
                        </div>
                        ${renderCoverageFertilizerFollowup(actualClassification || targetClassification, actualName, actualFertilizerEnabled, actualFertilizerOhn, actualFertilizerConcoction, actualFertilizerVermicompost, rowKey)}
                        ${renderCoverageAquaticField(actualAquaticResource, actualAquaticResourceQuantity, showsAquaticFields)}
                        <div class="coverage-field coverage-field--actual">
                            <label class="coverage-field-label">Actual Count</label>
                            <input type="number" min="0" class="form-control form-control-sm coverage-type-accomplishment" value="${escapeHtml(actualAccomplishment)}" placeholder="Count" ${supportsAccomplishment ? '' : 'disabled'}>
                        </div>
                        <div class="coverage-field coverage-field--land">
                            <label class="coverage-field-label">Land Utilization</label>
                            <input type="text" class="form-control form-control-sm coverage-land-area" value="${escapeHtml(coverageLandAreas[i] || '')}" placeholder="sqm or linear meter">
                        </div>
                        <div class="coverage-field coverage-field--ownership">
                            <label class="coverage-field-label">Land Ownership</label>
                            <input type="text" class="form-control form-control-sm coverage-land-ownership" value="${escapeHtml(coverageLandOwnerships[i] || '')}" placeholder="Ownership">
                        </div>
                        <div class="coverage-field coverage-field--drive-link">
                            <label class="coverage-field-label">Drive Link</label>
                            <input type="url" class="form-control form-control-sm coverage-drive-link" value="${escapeHtml(actualDriveLink)}" placeholder="https://drive.google.com/...">
                        </div>
                    </div>
                    <div class="coverage-entry-footer">
                        <div class="coverage-entry-caption">
                            ${status === 'pending'
                                ? 'This target is loaded as a reference only. Confirm it as the actual accomplishment or switch to a custom actual before it counts.'
                                : status === 'confirmed'
                                    ? 'This target is currently counted as the actual accomplishment.'
                                    : 'This row is counted as an actual accomplishment using the edited values below.'}
                        </div>
                        <div class="coverage-entry-actions">
                            ${renderCoverageActionButtons(status, hasTargetReference)}
                            <button type="button" class="btn btn-success btn-sm add-coverage-btn">+</button>
                            ${hasTargetReference ? '' : '<button type="button" class="btn btn-danger btn-sm remove-coverage-btn">-</button>'}
                        </div>
                    </div>
                </div>
            `);
        }

        return rows.join('');
    }

    function renderCoverageInputsFromRows(targetRowIds, targetPuroks, targetNames, targetClassifications, targetTypes, targetFertilizerEnabledFlags, targetFertilizerOhnTargets, targetFertilizerConcoctionTargets, targetFertilizerVermicompostTargets, targetAquaticResources, targetAquaticResourceQuantities, coverageRows) {
        const targetRowsById = new Map();
        const renderedActualRowKeys = new Set();
        const targetCount = Math.max(
            Array.isArray(targetRowIds) ? targetRowIds.length : 0,
            Array.isArray(targetPuroks) ? targetPuroks.length : 0,
            Array.isArray(targetNames) ? targetNames.length : 0,
            Array.isArray(targetClassifications) ? targetClassifications.length : 0,
            Array.isArray(targetTypes) ? targetTypes.length : 0
        );

        for (let i = 0; i < targetCount; i++) {
            const targetRowId = String((targetRowIds || [])[i] || '').trim();
            if (targetRowId === '') {
                continue;
            }

            targetRowsById.set(targetRowId, {
                rowId: targetRowId,
                purok: String((targetPuroks || [])[i] || '').trim(),
                name: String((targetNames || [])[i] || '').trim(),
                classification: String((targetClassifications || [])[i] || '').trim(),
                type: String((targetTypes || [])[i] || '').trim(),
                fertilizerEnabled: String((targetFertilizerEnabledFlags || [])[i] || '').trim(),
                fertilizerOhn: String((targetFertilizerOhnTargets || [])[i] || '').trim(),
                fertilizerConcoction: String((targetFertilizerConcoctionTargets || [])[i] || '').trim(),
                fertilizerVermicompost: String((targetFertilizerVermicompostTargets || [])[i] || '').trim(),
                aquaticResource: String((targetAquaticResources || [])[i] || '').trim(),
                aquaticResourceQuantity: String((targetAquaticResourceQuantities || [])[i] || '').trim()
            });
        }

        const coverageRowList = Array.isArray(coverageRows) ? coverageRows : [];
        const actualRowsByTargetId = new Map();

        coverageRowList.forEach((coverageRow) => {
            const linkedTargetRowId = String(coverageRow.target_project_row_id || '').trim();
            if (linkedTargetRowId !== '' && !actualRowsByTargetId.has(linkedTargetRowId)) {
                actualRowsByTargetId.set(linkedTargetRowId, coverageRow);
            }
        });

        function renderMergedCoverageRow(targetRow, coverageRow) {
            const resolvedCoverageRow = coverageRow || {};
            const actualRowKey = String(resolvedCoverageRow.actual_project_id || resolvedCoverageRow.project_id || '').trim();
            if (actualRowKey !== '') {
                renderedActualRowKeys.add(actualRowKey);
            }

            return renderCoverageInputs(
                [targetRow.rowId],
                [targetRow.purok],
                [targetRow.name],
                [targetRow.classification],
                [targetRow.type],
                [targetRow.fertilizerEnabled],
                [targetRow.fertilizerOhn],
                [targetRow.fertilizerConcoction],
                [targetRow.fertilizerVermicompost],
                [targetRow.aquaticResource],
                [targetRow.aquaticResourceQuantity],
                [resolvedCoverageRow.actual_project_id || resolvedCoverageRow.project_id || ''],
                [String(resolvedCoverageRow.target_project_row_id || '').trim() || targetRow.rowId],
                [resolvedCoverageRow.purok || ''],
                [resolvedCoverageRow.latitude || ''],
                [resolvedCoverageRow.longitude || ''],
                [resolvedCoverageRow.project_name || ''],
                [resolvedCoverageRow.classification || ''],
                [resolvedCoverageRow.project_type || ''],
                [resolvedCoverageRow.fertilizer_enabled || ''],
                [resolvedCoverageRow.fertilizer_ohn_quantity || ''],
                [resolvedCoverageRow.fertilizer_concoction_quantity || ''],
                [resolvedCoverageRow.fertilizer_vermicompost_quantity || ''],
                [resolvedCoverageRow.aquatic_resource || ''],
                [resolvedCoverageRow.aquatic_resource_quantity || ''],
                [resolvedCoverageRow.actual_accomplishment || ''],
                [resolvedCoverageRow.land_area || ''],
                [resolvedCoverageRow.land_ownership || ''],
                [resolvedCoverageRow.drive_link || ''],
                [resolvedCoverageRow.status || 'pending']
            );
        }

        const rows = [];

        if (targetRowsById.size) {
            targetRowsById.forEach((targetRow) => {
                rows.push(renderMergedCoverageRow(targetRow, actualRowsByTargetId.get(targetRow.rowId) || null));
            });
            return rows.join('');
        }

        coverageRowList.forEach((coverageRow, index) => {
            const linkedTargetRowId = String(coverageRow.target_project_row_id || '').trim();
            const fallbackTargetRowId = String((targetRowIds || [])[index] || '').trim();
            const resolvedTarget = targetRowsById.get(linkedTargetRowId) || targetRowsById.get(fallbackTargetRowId) || {
                rowId: linkedTargetRowId || fallbackTargetRowId,
                purok: '',
                name: '',
                classification: '',
                type: '',
                fertilizerEnabled: '',
                fertilizerOhn: '',
                fertilizerConcoction: '',
                fertilizerVermicompost: '',
                aquaticResource: '',
                aquaticResourceQuantity: ''
            };

            rows.push(renderMergedCoverageRow(resolvedTarget, coverageRow));
        });

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

    function renderBlguForumInput(row) {
        return `
            <div class="forum-card">
                <div class="forum-card-title"><i class="fas fa-map-marked-alt"></i><span>BLGU Forum</span></div>
                <div class="forum-date-grid">
                    <div class="date-range-field">
                        <label>Schedule</label>
                        <input type="text" class="form-control form-control-sm date-range-input js-date-range-picker row-blgu-range" value="${escapeHtml(formatDateRangeInputValue(row.blgu_forum_from || '', row.blgu_forum_to || ''))}" placeholder="Select date range" readonly>
                        <input type="hidden" class="row-blgu-from" value="${escapeHtml(row.blgu_forum_from || '')}">
                        <input type="hidden" class="row-blgu-to" value="${escapeHtml(row.blgu_forum_to || '')}">
                    </div>
                </div>
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

    function formatPercent(value, fallback = '0.00%') {
        const parsed = Number(value);
        if (!Number.isFinite(parsed)) {
            return escapeHtml(fallback);
        }

        return escapeHtml(`${parsed.toFixed(2)}%`);
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

    function buildGroupedTargetRows(puroks, names, classifications, types, aquaticResources, aquaticResourceQuantities, accomplishments, landAreas, landOwnerships) {
        const targetPuroks = Array.isArray(puroks) ? puroks : [];
        const projectNames = Array.isArray(names) ? names : [];
        const projectClasses = Array.isArray(classifications) ? classifications : [];
        const projectTypes = Array.isArray(types) ? types : [];
        const projectAquaticResources = Array.isArray(aquaticResources) ? aquaticResources : [];
        const projectAquaticResourceQuantities = Array.isArray(aquaticResourceQuantities) ? aquaticResourceQuantities : [];
        const projectAccomplishments = Array.isArray(accomplishments) ? accomplishments : [];
        const coverageLandAreas = Array.isArray(landAreas) ? landAreas : [];
        const coverageLandOwnerships = Array.isArray(landOwnerships) ? landOwnerships : [];
        const count = Math.max(targetPuroks.length, projectNames.length, projectClasses.length, projectTypes.length, projectAquaticResources.length, projectAquaticResourceQuantities.length, projectAccomplishments.length, coverageLandAreas.length, coverageLandOwnerships.length);
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
                map.get(key).projects.push({
                    name,
                    classification,
                    type: String(projectTypes[i] || '').trim(),
                    aquaticResource: String(projectAquaticResources[i] || '').trim(),
                    aquaticResourceQuantity: String(projectAquaticResourceQuantities[i] || '').trim(),
                    accomplishment: String(projectAccomplishments[i] || '').trim(),
                    landArea: String(coverageLandAreas[i] || '').trim(),
                    landOwnership: String(coverageLandOwnerships[i] || '').trim()
                });
            }
        }

        return rows;
    }

    function renderTargetCoverageList(puroks, names, classifications, types, aquaticResources, aquaticResourceQuantities, accomplishments, landAreas, landOwnerships, fallback) {
        const groupedRows = buildGroupedTargetRows(puroks, names, classifications, types, aquaticResources, aquaticResourceQuantities, accomplishments, landAreas, landOwnerships);
        if (!groupedRows.length) {
            return `<div class="target-coverage-empty">${escapeHtml(fallback)}</div>`;
        }

        const items = groupedRows.map((row) => {
            if (!row.projects.length) {
                return `<li><strong>${escapeHtml(row.purok)}</strong></li>`;
            }

            const projects = row.projects.map((project) => {
                const projectName = project.name ? escapeHtml(project.name) : 'Unnamed project';
                const details = [];
                if (project.classification) {
                    details.push(`Classification: ${escapeHtml(project.classification)}`);
                }
                if (project.type) {
                    details.push(`Type: ${escapeHtml(project.type)}`);
                }
                if (project.aquaticResource) {
                    details.push(`Aquatic Resource: ${escapeHtml(project.aquaticResource)}`);
                }
                if (project.aquaticResourceQuantity) {
                    details.push(`Aquatic Quantity: ${escapeHtml(project.aquaticResourceQuantity)}`);
                }
                if (project.accomplishment) {
                    details.push(`Actual accomplishment: ${escapeHtml(project.accomplishment)}`);
                }
                if (project.landArea) {
                    details.push(`Land Utilization: ${escapeHtml(project.landArea)}`);
                }
                if (project.landOwnership) {
                    details.push(`Land Ownership: ${escapeHtml(project.landOwnership)}`);
                }
                const detailsHtml = details.length
                    ? ` <span class="text-muted">(${details.join(' | ')})</span>`
                    : '';
                return `<li>${projectName}${detailsHtml}</li>`;
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

    function toInt(value) {
        const parsed = parseInt(value, 10);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function syncCoverageActualTotals($row) {
        let lawaTotal = 0;
        let binhiTotal = 0;

        $row.find('.coverage-entry-item').each(function() {
            const status = normalizeCoverageStatus($(this).find('.coverage-status').val());
            if (!isCoverageStatusActive(status)) {
                return;
            }
            const classification = String($(this).find('.coverage-project-classification').val() || '').trim().toUpperCase();
            const accomplishmentCount = toInt($(this).find('.coverage-type-accomplishment').val());
            if (classification === 'LAWA') {
                lawaTotal += accomplishmentCount;
            } else if (classification === 'BINHI') {
                binhiTotal += 1;
            }
        });

        $row.find('.row-actual-lawa').val(lawaTotal);
        $row.find('.row-actual-binhi').val(binhiTotal);
    }

    function copyTargetValuesToCoverageEntry($item) {
        const targetPurok = String($item.find('.coverage-target-purok').val() || '').trim();
        const targetProjectName = String($item.find('.coverage-target-project-name').val() || '').trim();
        const targetClassification = String($item.find('.coverage-target-project-classification').val() || '').trim().toUpperCase();
        const targetType = String($item.find('.coverage-target-project-type').val() || '').trim();
        const targetFertilizerEnabled = String($item.find('.coverage-target-fertilizer-enabled').val() || '').trim();
        const targetFertilizerOhn = String($item.find('.coverage-target-fertilizer-ohn').val() || '').trim();
        const targetFertilizerConcoction = String($item.find('.coverage-target-fertilizer-concoction').val() || '').trim();
        const targetFertilizerVermicompost = String($item.find('.coverage-target-fertilizer-vermicompost').val() || '').trim();
        const targetAquaticResource = String($item.find('.coverage-target-aquatic-resource').val() || '').trim();
        const targetAquaticResourceQuantity = String($item.find('.coverage-target-aquatic-resource-quantity').val() || '').trim();

        $item.find('.coverage-purok').val(targetPurok);
        $item.find('.coverage-project-name').val(targetProjectName);
        $item.find('.coverage-project-classification').val(targetClassification);
        registerCoverageTypeOption(targetClassification, targetType);
        $item.find('.coverage-type-field').replaceWith(renderCoverageTypeField(targetClassification, targetType));
        syncCoverageTypeField($item, targetType);
        $item.find('.coverage-fertilizer-enabled').val(targetFertilizerEnabled);
        $item.find('.coverage-fertilizer-enabled-choice').prop('checked', false);
        if (targetFertilizerEnabled !== '') {
            $item.find(`.coverage-fertilizer-enabled-choice[value="${targetFertilizerEnabled}"]`).prop('checked', true);
        }
        $item.find('.coverage-fertilizer-ohn').val(targetFertilizerOhn);
        $item.find('.coverage-fertilizer-concoction').val(targetFertilizerConcoction);
        $item.find('.coverage-fertilizer-vermicompost').val(targetFertilizerVermicompost);
        syncCoverageFertilizerFollowup($item);
        $item.find('.coverage-aquatic-resource').val(
            AQUATIC_RESOURCE_TYPE_OPTIONS.includes(targetAquaticResource.toUpperCase()) ? targetAquaticResource.toUpperCase() : (targetAquaticResource ? '__custom__' : '')
        );
        $item.find('.coverage-aquatic-resource-custom').val(
            AQUATIC_RESOURCE_TYPE_OPTIONS.includes(targetAquaticResource.toUpperCase()) ? '' : targetAquaticResource
        );
        $item.find('.coverage-aquatic-resource-quantity').val(targetAquaticResourceQuantity);
        $item.find('.coverage-aquatic-enabled').val(targetAquaticResource || targetAquaticResourceQuantity ? '1' : '0');
        syncCoverageAquaticFields($item);
        if (supportsTypeAccomplishment(targetClassification) && String($item.find('.coverage-type-accomplishment').val() || '').trim() === '') {
            $item.find('.coverage-type-accomplishment').val('1');
        }
    }

    function updateCoverageEntryState($item) {
        const status = normalizeCoverageStatus($item.find('.coverage-status').val());
        const isPending = status === 'pending';
        const isConfirmed = status === 'confirmed';
        const actualClassification = String($item.find('.coverage-project-classification').val() || '').trim().toUpperCase();
        const supportsAccomplishment = supportsTypeAccomplishment(actualClassification);
        const disableInputs = isPending || isConfirmed;
        const disableAccomplishmentInput = isPending || !supportsAccomplishment;

        $item.removeClass('is-pending is-confirmed is-custom').addClass(`is-${status}`);
        $item.find('.coverage-entry-status-pill')
            .removeClass('coverage-entry-status-pill--pending coverage-entry-status-pill--confirmed coverage-entry-status-pill--custom')
            .addClass(`coverage-entry-status-pill--${status}`)
            .text(
                status === 'pending'
                    ? 'Awaiting confirmation'
                    : status === 'confirmed'
                        ? 'Target confirmed as actual'
                        : 'Custom actual recorded'
            );

        $item.find('.coverage-purok, .coverage-latitude, .coverage-longitude, .coverage-project-name, .coverage-project-classification, .coverage-project-type, .coverage-project-type-custom')
            .prop('disabled', disableInputs);
        $item.find('.coverage-fertilizer-enabled-choice, .coverage-fertilizer-ohn, .coverage-fertilizer-concoction, .coverage-fertilizer-vermicompost')
            .prop('disabled', disableInputs);
        $item.find('.coverage-aquatic-resource, .coverage-aquatic-resource-custom').prop('disabled', disableInputs);
        $item.find('.coverage-aquatic-resource-quantity').prop('disabled', disableInputs);

        $item.find('.coverage-land-area, .coverage-land-ownership, .coverage-drive-link').prop('disabled', false);

        $item.find('.coverage-type-accomplishment')
            .prop('disabled', disableAccomplishmentInput);

        $item.find('.confirm-target-btn').text($item.attr('data-origin') === 'target' ? 'Use Target as Actual' : 'Use Entry as Actual');
        $item.find('.edit-actual-btn').text(status === 'custom' ? 'Editing Actual' : 'Actual is Different');

        const caption = isPending
            ? 'This target is loaded as a reference only. Confirm it as the actual accomplishment or switch to a custom actual before it counts.'
            : isConfirmed
                ? 'This target is currently counted as the actual accomplishment.'
                : 'This row is counted as an actual accomplishment using the edited values below.';
        $item.find('.coverage-entry-caption').text(caption);
    }

    function calculateDayDifference(fromDate, toDate) {
        if (!fromDate || !toDate) {
            return '';
        }

        const start = new Date(`${fromDate}T00:00:00`);
        const end = new Date(`${toDate}T00:00:00`);

        if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
            return '';
        }

        return String(Math.round((end.getTime() - start.getTime()) / 86400000));
    }

    function renderEditModalHeader(data, rows) {
        const municipality = data.municipality || 'Unspecified municipality';
        const province = rows[0]?.province || data.province || 'Unspecified province';
        const barangayCount = Array.isArray(rows) ? rows.length : 0;
        const totalTarget = Array.isArray(rows)
            ? rows.reduce((sum, row) => sum + toInt(row.target_partner_beneficiaries), 0)
            : 0;
        const projectCount = Array.isArray(rows)
            ? rows.reduce((sum, row) => {
                const projects = Array.isArray(row.projects) ? row.projects.length : 0;
                return sum + projects;
            }, 0)
            : 0;

        return `
            <div class="activity-edit-hero">
                <div class="activity-edit-hero-top">
                    <div>
                        <span class="activity-edit-eyebrow"><i class="fas fa-pen-nib"></i>Program Activities Editor</span>
                        <h3 class="activity-edit-title">${escapeHtml(municipality)}</h3>
                        <p class="activity-edit-subtitle">${escapeHtml(province)}<br>Review coverage, implementation, post-implementation, and performance rating in one place.</p>
                    </div>
                    <div class="activity-edit-badge">Edit Session</div>
                </div>
                <div class="activity-edit-meta">
                    <div class="activity-edit-meta-card">
                        <span class="activity-edit-meta-label">Barangays</span>
                        <span class="activity-edit-meta-value">${escapeHtml(String(barangayCount))}</span>
                    </div>
                    <div class="activity-edit-meta-card">
                        <span class="activity-edit-meta-label">Target Beneficiaries</span>
                        <span class="activity-edit-meta-value">${escapeHtml(String(totalTarget))}</span>
                    </div>
                    <div class="activity-edit-meta-card">
                        <span class="activity-edit-meta-label">Recorded Projects</span>
                        <span class="activity-edit-meta-value">${escapeHtml(String(projectCount))}</span>
                    </div>
                </div>
            </div>
        `;
    }

    function calculatePostImplementationMetrics(rowData) {
        const targetPartnerBeneficiaries = toInt(rowData.target_partner_beneficiaries);
        const obligatedPartnerBeneficiaries = Math.max(toInt(rowData.fund_obligation_partner_beneficiaries), 0);
        const servedPartnerBeneficiaries = Math.max(toInt(rowData.fund_disbursement_served_partner_beneficiaries), 0);
        const normalizedServedPartnerBeneficiaries = Math.min(servedPartnerBeneficiaries, obligatedPartnerBeneficiaries);
        const obligationAmount = obligatedPartnerBeneficiaries * BENEFICIARY_RATE;
        const disbursedAmount = normalizedServedPartnerBeneficiaries * BENEFICIARY_RATE;
        const unservedPartnerBeneficiaries = Math.max(obligatedPartnerBeneficiaries - normalizedServedPartnerBeneficiaries, 0);
        const undisbursedAmount = Math.max(obligationAmount - disbursedAmount, 0);
        const obligationPercentage = targetPartnerBeneficiaries > 0
            ? (obligatedPartnerBeneficiaries / targetPartnerBeneficiaries) * 100
            : 0;
        const disbursementPercentage = obligatedPartnerBeneficiaries > 0
            ? (normalizedServedPartnerBeneficiaries / obligatedPartnerBeneficiaries) * 100
            : 0;

        return {
            obligationAmount,
            obligationPercentage,
            normalizedServedPartnerBeneficiaries,
            disbursedAmount,
            unservedPartnerBeneficiaries,
            undisbursedAmount,
            disbursementPercentage
        };
    }

    function renderPostImplementationInputs(row) {
        const metrics = calculatePostImplementationMetrics(row);
        const payoutReferenceDate = row.payout_schedule_to || row.payout_schedule_from || '';
        const payoutToCompletionAging = calculateDayDifference(row.last_day_project_implementation || '', payoutReferenceDate);
        const checkToLiquidationAging = calculateDayDifference(row.check_issuance_date || '', row.liquidation_date || '');

        return `
            <div class="activity-edit-section mt-3 mb-0">
                <h6>Post-Implementation Activities</h6>
                <div class="section-note">Amounts are computed using this year's wage rate of PHP ${escapeHtml(BENEFICIARY_WAGE_RATE.toFixed(2))} for ${escapeHtml(String(BENEFICIARY_WORK_DAYS))} days.</div>

                <div class="forum-grid">
                    <div class="forum-card full-width">
                        <div class="forum-card-title"><i class="fas fa-clipboard-check"></i><span>Monitoring and Technical Assistance</span></div>
                        <div class="stage-phase-grid">
                            <div>
                                <label>DRMD Monitoring Schedule</label>
                                <div class="date-range-field">
                                    <input type="text" class="form-control form-control-sm date-range-input js-date-range-picker drmd-monitoring-range" value="${escapeHtml(formatDateRangeInputValue(row.drmd_monitoring_from || '', row.drmd_monitoring_to || ''))}" placeholder="Select date range" readonly>
                                    <input type="hidden" class="drmd-monitoring-from" value="${escapeHtml(row.drmd_monitoring_from || '')}">
                                    <input type="hidden" class="drmd-monitoring-to" value="${escapeHtml(row.drmd_monitoring_to || '')}">
                                </div>
                            </div>
                            <div>
                                <label>Participants (DRMD Monitoring Schedule)</label>
                                <textarea class="form-control form-control-sm drmd-monitoring-participants" rows="2" placeholder="Name of staffs">${escapeHtml(row.drmd_monitoring_participants || '')}</textarea>
                            </div>
                            <div>
                                <label>Joint DRMB-DRMD Post-Monitoring Schedule</label>
                                <div class="date-range-field">
                                    <input type="text" class="form-control form-control-sm date-range-input js-date-range-picker joint-post-monitoring-range" value="${escapeHtml(formatDateRangeInputValue(row.joint_post_monitoring_from || '', row.joint_post_monitoring_to || ''))}" placeholder="Select date range" readonly>
                                    <input type="hidden" class="joint-post-monitoring-from" value="${escapeHtml(row.joint_post_monitoring_from || '')}">
                                    <input type="hidden" class="joint-post-monitoring-to" value="${escapeHtml(row.joint_post_monitoring_to || '')}">
                                </div>
                            </div>
                            <div>
                                <label>Participants (Joint DRMB-DRMD Post-Monitoring Schedule)</label>
                                <textarea class="form-control form-control-sm joint-post-monitoring-participants" rows="2" placeholder="Name of staffs">${escapeHtml(row.joint_post_monitoring_participants || '')}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="forum-card">
                        <div class="forum-card-title"><i class="fas fa-calendar-alt"></i><span>Payout Schedule and Status</span></div>
                        <div class="date-range-field">
                            <label>Payout Schedule</label>
                            <input type="text" class="form-control form-control-sm date-range-input js-date-range-picker payout-schedule-range" value="${escapeHtml(formatDateRangeInputValue(row.payout_schedule_from || '', row.payout_schedule_to || ''))}" placeholder="Select date range" readonly>
                            <input type="hidden" class="payout-schedule-from" value="${escapeHtml(row.payout_schedule_from || '')}">
                            <input type="hidden" class="payout-schedule-to" value="${escapeHtml(row.payout_schedule_to || '')}">
                        </div>
                    </div>

                    <div class="forum-card">
                        <div class="forum-card-title"><i class="fas fa-file-invoice-dollar"></i><span>Fund Obligation Status</span></div>
                        <div class="stage-phase-grid">
                            <div>
                                <label>No. of Partner-beneficiaries</label>
                                <input type="number" min="0" class="form-control form-control-sm fund-obligation-partner-beneficiaries" value="${escapeHtml(String(row.fund_obligation_partner_beneficiaries || 0))}">
                            </div>
                            <div>
                                <label>Amount</label>
                                <input type="text" class="form-control form-control-sm fund-obligation-amount" value="${escapeHtml(new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(metrics.obligationAmount))}" readonly>
                            </div>
                            <div>
                                <label>Percentage</label>
                                <input type="text" class="form-control form-control-sm fund-obligation-percentage" value="${escapeHtml(metrics.obligationPercentage.toFixed(2))}%" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="forum-card">
                        <div class="forum-card-title"><i class="fas fa-coins"></i><span>Fund Disbursement Status</span></div>
                        <div class="stage-phase-grid">
                            <div>
                                <label>No. of Served Partner-beneficiaries (during the payout)</label>
                                <input type="number" min="0" class="form-control form-control-sm fund-disbursement-served-partner-beneficiaries" value="${escapeHtml(String(row.fund_disbursement_served_partner_beneficiaries || 0))}">
                            </div>
                            <div>
                                <label>Disbursed Amount</label>
                                <input type="text" class="form-control form-control-sm fund-disbursed-amount" value="${escapeHtml(new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(metrics.disbursedAmount))}" readonly>
                            </div>
                            <div>
                                <label>No. of Unserved Partner-beneficiaries</label>
                                <input type="text" class="form-control form-control-sm fund-unserved-partner-beneficiaries" value="${escapeHtml(String(metrics.unservedPartnerBeneficiaries))}" readonly>
                            </div>
                            <div>
                                <label>Undisbursed Amount</label>
                                <input type="text" class="form-control form-control-sm fund-undisbursed-amount" value="${escapeHtml(new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(metrics.undisbursedAmount))}" readonly>
                            </div>
                            <div>
                                <label>Percentage</label>
                                <input type="text" class="form-control form-control-sm fund-disbursement-percentage" value="${escapeHtml(metrics.disbursementPercentage.toFixed(2))}%" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="forum-card">
                        <div class="forum-card-title"><i class="fas fa-folder-open"></i><span>Liquidation Status</span></div>
                        <div class="stage-phase-grid">
                            <div>
                                <label>Date</label>
                                <input type="date" class="form-control form-control-sm liquidation-date" value="${escapeHtml(row.liquidation_date || '')}">
                            </div>
                            <div>
                                <label>Special Disbursing Officer</label>
                                <input type="text" class="form-control form-control-sm special-disbursing-officer" value="${escapeHtml(row.special_disbursing_officer || '')}" placeholder="Name of officer">
                            </div>
                        </div>
                    </div>

                    <div class="forum-card full-width">
                        <div class="forum-card-title"><i class="fas fa-star"></i><span>Performance Rating</span></div>
                        <div class="section-note mb-0">MOVs for Timeliness (CCEF and DPC Purposes)</div>
                        <div class="stage-phase-grid mt-2">
                            <div>
                                <label>Last Day of Project Implementation</label>
                                <input type="date" class="form-control form-control-sm last-day-project-implementation" value="${escapeHtml(row.last_day_project_implementation || '')}">
                            </div>
                            <div>
                                <label>Difference from Completion to Payout Date</label>
                                <input type="text" class="form-control form-control-sm payout-to-completion-aging" value="${escapeHtml(payoutToCompletionAging !== '' ? `${payoutToCompletionAging} day(s)` : '')}" placeholder="Calculated from completion and payout dates" readonly>
                            </div>
                            <div>
                                <label>Check Issuance Date</label>
                                <input type="date" class="form-control form-control-sm check-issuance-date" value="${escapeHtml(row.check_issuance_date || '')}">
                            </div>
                            <div>
                                <label>Difference from Check Issuance to Liquidation (Aging)</label>
                                <input type="text" class="form-control form-control-sm check-to-liquidation-aging" value="${escapeHtml(checkToLiquidationAging !== '' ? `${checkToLiquidationAging} day(s)` : '')}" placeholder="Calculated from check issuance and liquidation" readonly>
                            </div>
                            <div>
                                <label>Status of Work Accomplishment Report</label>
                                <input type="text" class="form-control form-control-sm work-accomplishment-report-status" value="${escapeHtml(row.work_accomplishment_report_status || '')}" placeholder="Enter status">
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label>Remarks</label>
                                <textarea class="form-control form-control-sm performance-rating-remarks" rows="2" placeholder="Enter remarks">${escapeHtml(row.performance_rating_remarks || '')}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function renderBinhiSupplementalInputs(row) {
        return `
            <div class="barangay-panel-section">
                <span class="barangay-panel-section-title">BINHI Supplemental Metrics</span>
                <div class="section-note">Use these barangay-level BINHI values to complete the summary fields that are not derived from coverage rows alone.</div>
                <div class="stage-phase-grid">
                    <div>
                        <label class="mb-1">BINHI Sites Established Target</label>
                        <input type="number" min="0" class="form-control form-control-sm binhi-sites-established-target" value="${escapeHtml(String(row.binhi_sites_established_target ?? 0))}">
                    </div>
                    <div>
                        <label class="mb-1">BINHI Sites Established Actual</label>
                        <input type="number" min="0" class="form-control form-control-sm binhi-sites-established-actual" value="${escapeHtml(String(row.binhi_sites_established_actual ?? 0))}">
                    </div>
                    <div>
                        <label class="mb-1">BINHI Facilities Added Target</label>
                        <input type="number" min="0" class="form-control form-control-sm binhi-facilities-added-target" value="${escapeHtml(String(row.binhi_facilities_added_target ?? 0))}">
                    </div>
                    <div>
                        <label class="mb-1">BINHI Facilities Added Actual</label>
                        <input type="number" min="0" class="form-control form-control-sm binhi-facilities-added-actual" value="${escapeHtml(String(row.binhi_facilities_added_actual ?? 0))}">
                    </div>
                    <div>
                        <label class="mb-1">Oriental Herbal Nutrients Target (liters)</label>
                        <input type="number" min="0" step="0.01" class="form-control form-control-sm fertilizer-ohn-target" value="${escapeHtml(String(row.fertilizer_ohn_target ?? 0))}">
                    </div>
                    <div>
                        <label class="mb-1">Oriental Herbal Nutrients Actual (liters)</label>
                        <input type="number" min="0" step="0.01" class="form-control form-control-sm fertilizer-ohn-actual" value="${escapeHtml(String(row.fertilizer_ohn_actual ?? 0))}">
                    </div>
                    <div>
                        <label class="mb-1">Concoction/Vermitea Target (liters)</label>
                        <input type="number" min="0" step="0.01" class="form-control form-control-sm fertilizer-concoction-target" value="${escapeHtml(String(row.fertilizer_concoction_target ?? 0))}">
                    </div>
                    <div>
                        <label class="mb-1">Concoction/Vermitea Actual (liters)</label>
                        <input type="number" min="0" step="0.01" class="form-control form-control-sm fertilizer-concoction-actual" value="${escapeHtml(String(row.fertilizer_concoction_actual ?? 0))}">
                    </div>
                    <div>
                        <label class="mb-1">Vermicompost/Vermicast Target (kg)</label>
                        <input type="number" min="0" step="0.01" class="form-control form-control-sm fertilizer-vermicompost-target" value="${escapeHtml(String(row.fertilizer_vermicompost_target ?? 0))}">
                    </div>
                    <div>
                        <label class="mb-1">Vermicompost/Vermicast Actual (kg)</label>
                        <input type="number" min="0" step="0.01" class="form-control form-control-sm fertilizer-vermicompost-actual" value="${escapeHtml(String(row.fertilizer_vermicompost_actual ?? 0))}">
                    </div>
                    <div>
                        <label class="mb-1">Area of Land Utilized Target (sqm)</label>
                        <input type="number" min="0" step="0.01" class="form-control form-control-sm area-land-utilized-target" value="${escapeHtml(String(row.area_land_utilized_target ?? 0))}">
                    </div>
                </div>
            </div>
        `;
    }

    function updatePostImplementationMetrics($row) {
        const targetPartnerBeneficiaries = toInt($row.find('.row-total-target').val());
        const obligatedPartnerBeneficiaries = Math.max(toInt($row.find('.fund-obligation-partner-beneficiaries').val()), 0);
        let servedPartnerBeneficiaries = Math.max(toInt($row.find('.fund-disbursement-served-partner-beneficiaries').val()), 0);

        if (servedPartnerBeneficiaries > obligatedPartnerBeneficiaries) {
            servedPartnerBeneficiaries = obligatedPartnerBeneficiaries;
            $row.find('.fund-disbursement-served-partner-beneficiaries').val(servedPartnerBeneficiaries);
        }

        const metrics = calculatePostImplementationMetrics({
            target_partner_beneficiaries: targetPartnerBeneficiaries,
            fund_obligation_partner_beneficiaries: obligatedPartnerBeneficiaries,
            fund_disbursement_served_partner_beneficiaries: servedPartnerBeneficiaries
        });
        const numberFormatter = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        $row.find('.fund-obligation-amount').val(numberFormatter.format(metrics.obligationAmount));
        $row.find('.fund-obligation-percentage').val(`${metrics.obligationPercentage.toFixed(2)}%`);
        $row.find('.fund-disbursed-amount').val(numberFormatter.format(metrics.disbursedAmount));
        $row.find('.fund-unserved-partner-beneficiaries').val(metrics.unservedPartnerBeneficiaries);
        $row.find('.fund-undisbursed-amount').val(numberFormatter.format(metrics.undisbursedAmount));
        $row.find('.fund-disbursement-percentage').val(`${metrics.disbursementPercentage.toFixed(2)}%`);

        const payoutReferenceDate = $row.find('.payout-schedule-to').val() || $row.find('.payout-schedule-from').val();
        const payoutToCompletionAging = calculateDayDifference($row.find('.last-day-project-implementation').val(), payoutReferenceDate);
        const checkToLiquidationAging = calculateDayDifference($row.find('.check-issuance-date').val(), $row.find('.liquidation-date').val());

        $row.find('.payout-to-completion-aging').val(payoutToCompletionAging !== '' ? `${payoutToCompletionAging} day(s)` : '');
        $row.find('.check-to-liquidation-aging').val(checkToLiquidationAging !== '' ? `${checkToLiquidationAging} day(s)` : '');
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
                        <span class="kodus-detail-label">LAWA Target</span>
                        <span class="kodus-detail-value kodus-detail-value--strong">${formatNumber(data.lawa_target_beneficiaries)}</span>
                    </div>
                    <div class="kodus-detail-stat">
                        <span class="kodus-detail-label">BINHI Target</span>
                        <span class="kodus-detail-value kodus-detail-value--strong">${formatNumber(data.binhi_target_beneficiaries)}</span>
                    </div>
                    <div class="kodus-detail-stat">
                        <span class="kodus-detail-label">CapBuild Target</span>
                        <span class="kodus-detail-value kodus-detail-value--strong">${formatNumber(data.capbuild_target_beneficiaries)}</span>
                    </div>
                    <div class="kodus-detail-stat">
                        <span class="kodus-detail-label">Community Action Plan Target</span>
                        <span class="kodus-detail-value kodus-detail-value--strong">${formatNumber(data.community_action_plan_target_beneficiaries)}</span>
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

                <div class="kodus-detail-section">
                    <h6 class="kodus-detail-section-title">Post-Implementation Activities</h6>
                    <div class="kodus-detail-section-grid">
                        <div>
                            <span class="kodus-detail-label">DRMD Monitoring Schedule</span>
                            <span class="kodus-detail-value">${formatFallback(data.drmd_monitoring_schedule, 'Not set')}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Participants (DRMD Monitoring Schedule)</span>
                            <span class="kodus-detail-value">${formatFallback(data.drmd_monitoring_participants, 'Not set')}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Joint DRMB-DRMD Post-Monitoring Schedule</span>
                            <span class="kodus-detail-value">${formatFallback(data.joint_post_monitoring_schedule, 'Not set')}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Participants (Joint DRMB-DRMD Post-Monitoring Schedule)</span>
                            <span class="kodus-detail-value">${formatFallback(data.joint_post_monitoring_participants, 'Not set')}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Payout Schedule</span>
                            <span class="kodus-detail-value">${formatFallback(data.payout_schedule, 'Not set')}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Fund Obligation Status</span>
                            <span class="kodus-detail-value">No. of Partner-beneficiaries: ${formatNumber(data.fund_obligation_partner_beneficiaries)} | Amount: ${formatCurrency(data.fund_obligation_amount)} | Percentage: ${formatPercent(data.fund_obligation_percentage)}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Fund Disbursement Status</span>
                            <span class="kodus-detail-value">Served: ${formatNumber(data.fund_disbursement_served_partner_beneficiaries)} | Disbursed: ${formatCurrency(data.fund_disbursement_amount)} | Unserved: ${formatNumber(data.fund_disbursement_unserved_partner_beneficiaries)} | Undisbursed: ${formatCurrency(data.fund_disbursement_undisbursed_amount)} | Percentage: ${formatPercent(data.fund_disbursement_percentage)}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Liquidation Date</span>
                            <span class="kodus-detail-value">${formatFallback(data.liquidation_dates, 'Not set')}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Special Disbursing Officer</span>
                            <span class="kodus-detail-value">${formatFallback(data.special_disbursing_officers, 'Not set')}</span>
                        </div>
                    </div>
                </div>

                <div class="kodus-detail-section">
                    <h6 class="kodus-detail-section-title">Performance Rating</h6>
                    <div class="kodus-detail-section-grid">
                        <div>
                            <span class="kodus-detail-label">Timeliness (CCEF and DPC Purposes) - Last Day of Project Implementation</span>
                            <span class="kodus-detail-value">${formatFallback(data.last_day_project_implementation_dates, 'Not set')}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Difference from Completion to Payout Date</span>
                            <span class="kodus-detail-value">${formatFallback(data.payout_to_completion_aging, 'Not set')}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Check Issuance Date</span>
                            <span class="kodus-detail-value">${formatFallback(data.check_issuance_dates, 'Not set')}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Difference from Check Issuance to Liquidation (Aging)</span>
                            <span class="kodus-detail-value">${formatFallback(data.check_to_liquidation_aging, 'Not set')}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Status of Work Accomplishment Report</span>
                            <span class="kodus-detail-value">${formatFallback(data.work_accomplishment_report_statuses, 'Not set')}</span>
                        </div>
                        <div>
                            <span class="kodus-detail-label">Remarks</span>
                            <span class="kodus-detail-value">${formatFallback(data.performance_rating_remarks, 'Not set')}</span>
                        </div>
                    </div>
                </div>

                <div class="kodus-detail-section mb-0">
                    <h6 class="kodus-detail-section-title">Project Portfolio</h6>
                    <span class="kodus-detail-label">Project Names</span>
                    ${formatList(data.project_names, 'No projects recorded yet')}
                    <span class="kodus-detail-label mt-3">Coverage Accomplishment Entries</span>
                    ${renderTargetCoverageList(
                        splitMultiValue(data.coverage_puroks || data.target_puroks),
                        splitMultiValue(data.coverage_project_names || data.target_project_names),
                        splitMultiValue(data.coverage_project_classifications || data.target_project_classifications),
                        splitMultiValue(data.coverage_project_types || data.target_project_types),
                        splitMultiValue(data.coverage_aquatic_resources || data.target_aquatic_resources),
                        splitMultiValue(data.coverage_aquatic_resource_quantities || data.target_aquatic_resource_quantities),
                        splitMultiValue(data.coverage_actual_accomplishments),
                        splitMultiValue(data.coverage_land_areas),
                        splitMultiValue(data.coverage_land_ownerships),
                        'No coverage accomplishment entries recorded yet'
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
            { "data": "lawa_target_beneficiaries" },
            { "data": "binhi_target_beneficiaries" },
            { "data": "capbuild_target_beneficiaries" },
            { "data": "community_action_plan_target_beneficiaries" },
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
        "responsive": false,
        "scrollX": true,
        "lengthChange": true,
        "autoWidth": false,
        "order": [[1, 'asc'], [2, 'asc']] // sort by province (hidden), then municipality
    });

    function openProgramActivityEditor(data) {
        if (!canManageActivities || !data) {
            return;
        }

        $.getJSON('get-program-activity.php', { municipality: data.municipality, province: data.province })
            .done(function(response) {
                if (!response.success) {
                    Swal.fire('Error', response.message || 'Could not load activity details.', 'error');
                    return;
                }

                const rows = response.rows || [];
                const first = rows[0] || {};
                const editHeaderHtml = renderEditModalHeader(data, rows);
                const rowHtml = rows.map((row, index) => `
                        <div class="edit-grid-row${index === 0 ? '' : ' is-collapsed'}" data-pane-index="${index}">
                        <input type="hidden" class="row-barangay" value="${escapeHtml(row.barangay)}">
                        <div class="barangay-pane-header">
                            <div>
                                <span class="barangay-pane-eyebrow"><i class="fas fa-map-marker-alt"></i>Barangay Entry ${index + 1}</span>
                                <h6 class="barangay-pane-title">${escapeHtml(row.barangay || `Barangay ${index + 1}`)}</h6>
                                <p class="barangay-pane-subtitle">Review target references and encode actual accomplishment, schedules, disbursement details, and performance data for this barangay.</p>
                            </div>
                            <div class="barangay-pane-actions">
                                <div class="barangay-pane-meta">
                                    <span class="barangay-pane-pill"><i class="fas fa-users"></i>Target ${escapeHtml(String(row.target_partner_beneficiaries ?? 0))}</span>
                                    <span class="barangay-pane-pill"><i class="fas fa-diagram-project"></i>Projects ${escapeHtml(String(Array.isArray(row.projects) ? row.projects.length : 0))}</span>
                                </div>
                                <button type="button" class="barangay-pane-toggle" aria-label="Toggle barangay pane">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                        </div>
                        <div class="barangay-pane-body">
                            <div class="barangay-tab-nav">
                                <button type="button" class="barangay-tab is-active" data-tab="targets"><i class="fas fa-bullseye"></i>Accomplishment</button>
                                <button type="button" class="barangay-tab" data-tab="implementation"><i class="fas fa-layer-group"></i>Implementation</button>
                                <button type="button" class="barangay-tab" data-tab="post"><i class="fas fa-chart-line"></i>Post-Implementation</button>
                            </div>

                            <div class="barangay-tab-panel is-active" data-tab-panel="targets">
                                <div class="barangay-panel-section">
                                    <div class="section-note">Targets remain in Program Targets. Use the coverage accomplishment rows below for the actual implementation entries. LAWA and BINHI totals auto-update from the number of rows classified as LAWA or BINHI, while Actual Partner-Beneficiaries stays sourced from imported MEBs.</div>
                                    <span class="barangay-panel-section-title">Barangay Accomplishment Counts</span>
                                    <div class="stage-phase-grid">
                                        <div>
                                            <label class="mb-1">LAWA Actual Accomplishment</label>
                                            <input type="number" min="0" class="form-control form-control-sm row-actual-lawa" value="${escapeHtml(row.actual_lawa_accomplishment ?? 0)}" readonly>
                                        </div>
                                        <div>
                                            <label class="mb-1">BINHI Actual Accomplishment</label>
                                            <input type="number" min="0" class="form-control form-control-sm row-actual-binhi" value="${escapeHtml(row.actual_binhi_accomplishment ?? 0)}" readonly>
                                        </div>
                                        <div>
                                            <label class="mb-1">CapBuild Actual Accomplishment</label>
                                            <input type="number" min="0" class="form-control form-control-sm row-actual-capbuild" value="${escapeHtml(row.actual_capbuild_accomplishment ?? 0)}">
                                        </div>
                                        <div>
                                            <label class="mb-1">Community Action Plan Actual Accomplishment</label>
                                            <input type="number" min="0" class="form-control form-control-sm row-actual-community-action-plan" value="${escapeHtml(row.actual_community_action_plan_accomplishment ?? 0)}">
                                        </div>
                                        <div>
                                            <label class="mb-1">Total Target Partner-Beneficiaries</label>
                                            <input type="number" min="0" class="form-control form-control-sm row-total-target" value="${escapeHtml(row.target_partner_beneficiaries ?? 0)}" readonly>
                                        </div>
                                        <div>
                                            <label class="mb-1">Actual Partner-Beneficiaries (Imported MEB)</label>
                                            <input type="number" min="0" class="form-control form-control-sm row-actual-beneficiaries" value="${escapeHtml(row.actual_beneficiaries ?? 0)}" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="barangay-panel-section">
                                    <span class="barangay-panel-section-title">Coverage Accomplishment Entries</span>
                                        <div class="coverage-list">
                                        ${Array.isArray(row.coverage_rows) && row.coverage_rows.length
                                            ? renderCoverageInputsFromRows(
                                                row.target_project_row_ids || [],
                                                row.puroks || [],
                                                row.target_project_names || [],
                                                row.project_classifications || [],
                                                row.target_project_types || [],
                                                row.target_fertilizer_enabled_flags || [],
                                                row.target_fertilizer_ohn_targets || [],
                                                row.target_fertilizer_concoction_targets || [],
                                                row.target_fertilizer_vermicompost_targets || [],
                                                row.target_aquatic_resources || [],
                                                row.target_aquatic_resource_quantities || [],
                                                row.coverage_rows || []
                                            )
                                            : renderCoverageInputs(
                                                row.target_project_row_ids || [],
                                                row.puroks || [],
                                                row.target_project_names || [],
                                                row.project_classifications || [],
                                                row.target_project_types || [],
                                                row.target_fertilizer_enabled_flags || [],
                                                row.target_fertilizer_ohn_targets || [],
                                                row.target_fertilizer_concoction_targets || [],
                                                row.target_fertilizer_vermicompost_targets || [],
                                                row.target_aquatic_resources || [],
                                                row.target_aquatic_resource_quantities || [],
                                                row.actual_project_ids || row.coverage_entry_ids || [],
                                                row.target_project_row_ids_for_actuals || [],
                                                row.coverage_puroks || [],
                                                row.coverage_latitudes || [],
                                                row.coverage_longitudes || [],
                                                row.coverage_project_names || [],
                                                row.coverage_project_classifications || [],
                                                row.coverage_project_types || [],
                                                row.coverage_fertilizer_enabled_flags || [],
                                                row.coverage_fertilizer_ohn_quantities || [],
                                                row.coverage_fertilizer_concoction_quantities || [],
                                                row.coverage_fertilizer_vermicompost_quantities || [],
                                                row.coverage_aquatic_resources || [],
                                                row.coverage_aquatic_resource_quantities || [],
                                                row.coverage_actual_accomplishments || [],
                                                row.coverage_land_areas || [],
                                                row.coverage_land_ownerships || [],
                                                row.coverage_drive_links || [],
                                                row.coverage_actual_statuses || []
                                            )}
                                    </div>
                                </div>
                                ${renderBinhiSupplementalInputs(row)}
                            </div>

                            <div class="barangay-tab-panel" data-tab-panel="implementation">
                                <div class="barangay-panel-section">
                                    <span class="barangay-panel-section-title">BLGU Forum and Site Validation</span>
                                    <div class="forum-grid implementation-support-grid">
                                        ${renderBlguForumInput(row)}
                                        <div class="forum-card">
                                            <div class="forum-card-title"><i class="fas fa-clipboard-check"></i><span>Site Validation</span></div>
                                            <div class="site-validation-list row-site-validation-list">${renderSiteValidationInputs(row.site_validation || '')}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="barangay-panel-section">
                                    <span class="barangay-panel-section-title">Implementation Phases</span>
                                    ${renderStagePhaseInputs(row)}
                                </div>
                            </div>

                            <div class="barangay-tab-panel" data-tab-panel="post">
                                ${renderPostImplementationInputs(row)}
                            </div>
                        </div>
                    </div>
                `).join('');

                Swal.fire({
                    width: 1180,
                    customClass: {
                        container: 'kodus-scrollable-swal',
                        popup: 'kodus-edit-popup'
                    },
                    html: `
                        <div class="activity-edit-shell">
                            ${editHeaderHtml}
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
                                <div class="section-note">Set the PLGU and MLGU schedules here. BLGU forum dates and site validation are managed per barangay below in the Implementation tab.</div>
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
                        $('#edit-rows-container .edit-grid-row').each(function() {
                            $(this).find('.coverage-entry-item').each(function() {
                                const $item = $(this);
                                registerCoverageTypeOption($item.find('.coverage-project-classification').val(), getCoverageTypeValue($item));
                                registerCoverageTypeOption($item.find('.coverage-target-project-classification').val(), $item.find('.coverage-target-project-type').val());
                                registerCoverageAquaticResourceOption(getCoverageAquaticResourceValue($item));
                                registerCoverageAquaticResourceOption($item.find('.coverage-target-aquatic-resource').val());
                                syncCoverageTypeField($item, getCoverageTypeValue($item));
                                syncCoverageFertilizerFollowup($item);
                                syncCoverageAquaticFields($item);
                                updateCoverageEntryState($(this));
                            });
                            syncCoverageActualTotals($(this));
                        });
                        if (window.KodusPageLoader && typeof window.KodusPageLoader.hideModalLoader === 'function') {
                            window.KodusPageLoader.hideModalLoader();
                        }
                    },
                    preConfirm: () => {
                        const plguFrom = $('#edit-plgu-from').val();
                        const plguTo = $('#edit-plgu-to').val();
                        const mlguFrom = $('#edit-mlgu-from').val();
                        const mlguTo = $('#edit-mlgu-to').val();
                        const forumRanges = [
                            { label: 'PLGU Forum', from: plguFrom, to: plguTo },
                            { label: 'MLGU Forum', from: mlguFrom, to: mlguTo }
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
                            const barangayName = $(this).find('.row-barangay').val() || 'this barangay';
                            const blguFrom = $(this).find('.row-blgu-from').val().trim();
                            const blguTo = $(this).find('.row-blgu-to').val().trim();

                            if ((blguFrom && !blguTo) || (!blguFrom && blguTo)) {
                                Swal.showValidationMessage(`${barangayName}: BLGU Forum needs both From and To dates when one of them is filled in.`);
                                hasRowValidationError = true;
                                return false;
                            }

                            if (blguFrom && blguTo && blguFrom > blguTo) {
                                Swal.showValidationMessage(`${barangayName}: BLGU Forum From date must be earlier than or equal to its To date.`);
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
                                stage3_end_date: $(this).find('.stage3-end-date').val(),
                                drmd_monitoring_from: $(this).find('.drmd-monitoring-from').val(),
                                drmd_monitoring_to: $(this).find('.drmd-monitoring-to').val(),
                                drmd_monitoring_participants: $(this).find('.drmd-monitoring-participants').val().trim(),
                                joint_post_monitoring_from: $(this).find('.joint-post-monitoring-from').val(),
                                joint_post_monitoring_to: $(this).find('.joint-post-monitoring-to').val(),
                                joint_post_monitoring_participants: $(this).find('.joint-post-monitoring-participants').val().trim(),
                                payout_schedule_from: $(this).find('.payout-schedule-from').val(),
                                payout_schedule_to: $(this).find('.payout-schedule-to').val(),
                                fund_obligation_partner_beneficiaries: toInt($(this).find('.fund-obligation-partner-beneficiaries').val()),
                                fund_disbursement_served_partner_beneficiaries: toInt($(this).find('.fund-disbursement-served-partner-beneficiaries').val()),
                                liquidation_date: $(this).find('.liquidation-date').val(),
                                last_day_project_implementation: $(this).find('.last-day-project-implementation').val(),
                                check_issuance_date: $(this).find('.check-issuance-date').val(),
                                work_accomplishment_report_status: $(this).find('.work-accomplishment-report-status').val().trim(),
                                performance_rating_remarks: $(this).find('.performance-rating-remarks').val().trim(),
                                special_disbursing_officer: $(this).find('.special-disbursing-officer').val().trim(),
                                binhi_sites_established_target: toInt($(this).find('.binhi-sites-established-target').val()),
                                binhi_sites_established_actual: toInt($(this).find('.binhi-sites-established-actual').val()),
                                binhi_facilities_added_target: toInt($(this).find('.binhi-facilities-added-target').val()),
                                binhi_facilities_added_actual: toInt($(this).find('.binhi-facilities-added-actual').val()),
                                fertilizer_ohn_target: ($(this).find('.fertilizer-ohn-target').val() || '0').trim(),
                                fertilizer_ohn_actual: ($(this).find('.fertilizer-ohn-actual').val() || '0').trim(),
                                fertilizer_concoction_target: ($(this).find('.fertilizer-concoction-target').val() || '0').trim(),
                                fertilizer_concoction_actual: ($(this).find('.fertilizer-concoction-actual').val() || '0').trim(),
                                fertilizer_vermicompost_target: ($(this).find('.fertilizer-vermicompost-target').val() || '0').trim(),
                                fertilizer_vermicompost_actual: ($(this).find('.fertilizer-vermicompost-actual').val() || '0').trim(),
                                area_land_utilized_target: ($(this).find('.area-land-utilized-target').val() || '0').trim()
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

                            const postImplementationLabels = [
                                ['drmd_monitoring_from', 'drmd_monitoring_to', 'DRMD Monitoring Schedule'],
                                ['joint_post_monitoring_from', 'joint_post_monitoring_to', 'Joint DRMB-DRMD Post-Monitoring Schedule'],
                                ['payout_schedule_from', 'payout_schedule_to', 'Payout Schedule']
                            ];

                            for (const [startKey, endKey, label] of postImplementationLabels) {
                                const startDate = stagePayload[startKey];
                                const endDate = stagePayload[endKey];

                                if ((startDate && !endDate) || (!startDate && endDate)) {
                                    Swal.showValidationMessage(`${barangayName}: ${label} needs both From and To dates when one of them is filled in.`);
                                    hasRowValidationError = true;
                                    return false;
                                }

                                if (startDate && endDate && startDate > endDate) {
                                    Swal.showValidationMessage(`${barangayName}: ${label} From date must be earlier than or equal to its To date.`);
                                    hasRowValidationError = true;
                                    return false;
                                }
                            }

                            if (stagePayload.fund_disbursement_served_partner_beneficiaries > stagePayload.fund_obligation_partner_beneficiaries) {
                                Swal.showValidationMessage(`${barangayName}: served partner-beneficiaries cannot be greater than the obligated partner-beneficiaries.`);
                                hasRowValidationError = true;
                                return false;
                            }

                            let hasAquaticValidationError = false;
                            $(this).find('.coverage-entry-item').each(function() {
                                const latitude = normalizeCoordinateInputValue($(this).find('.coverage-latitude').val());
                                const longitude = normalizeCoordinateInputValue($(this).find('.coverage-longitude').val());
                                if ((latitude && !longitude) || (!latitude && longitude)) {
                                    Swal.showValidationMessage(`${barangayName}: latitude and longitude must both be provided when one coordinate is filled in.`);
                                    hasRowValidationError = true;
                                    return false;
                                }
                                if (latitude && !/^-?\d+(?:\.\d+)?$/.test(latitude)) {
                                    Swal.showValidationMessage(`${barangayName}: latitude must be a valid number.`);
                                    hasRowValidationError = true;
                                    return false;
                                }
                                if (longitude && !/^-?\d+(?:\.\d+)?$/.test(longitude)) {
                                    Swal.showValidationMessage(`${barangayName}: longitude must be a valid number.`);
                                    hasRowValidationError = true;
                                    return false;
                                }

                                if (String($(this).find('.coverage-aquatic-enabled').val() || '').trim() !== '1') {
                                    return;
                                }

                                persistCustomCoverageAquaticResource($(this));
                                const aquaticResource = getCoverageAquaticResourceValue($(this));
                                const aquaticQuantity = ($(this).find('.coverage-aquatic-resource-quantity').val() || '').trim();
                                if (aquaticResource === '' || !/^\d+$/.test(aquaticQuantity)) {
                                    Swal.showValidationMessage(`${barangayName}: aquatic resource and quantity are required for Small Farm Reservoir and Fishpond LAWA entries.`);
                                    hasRowValidationError = true;
                                    hasAquaticValidationError = true;
                                    return false;
                                }
                            });

                            if (hasAquaticValidationError) {
                                return false;
                            }

                            if (hasRowValidationError) {
                                return false;
                            }

                            rowsPayload.push({
                                barangay: $(this).find('.row-barangay').val(),
                                actual_lawa_accomplishment: parseInt($(this).find('.row-actual-lawa').val(), 10) || 0,
                                actual_binhi_accomplishment: parseInt($(this).find('.row-actual-binhi').val(), 10) || 0,
                                actual_capbuild_accomplishment: parseInt($(this).find('.row-actual-capbuild').val(), 10) || 0,
                                actual_community_action_plan_accomplishment: parseInt($(this).find('.row-actual-community-action-plan').val(), 10) || 0,
                                coverage_puroks: $(this).find('.coverage-purok').map(function() {
                                    return $(this).val().trim();
                                }).get(),
                                actual_project_ids: $(this).find('.actual-project-id').map(function() {
                                    return ($(this).val() || '').trim();
                                }).get(),
                                coverage_entry_ids: $(this).find('.actual-project-id').map(function() {
                                    return ($(this).val() || '').trim();
                                }).get(),
                                target_project_row_ids: $(this).find('.target-project-row-id').map(function() {
                                    return ($(this).val() || '').trim();
                                }).get(),
                                coverage_latitudes: $(this).find('.coverage-latitude').map(function() {
                                    return normalizeCoordinateInputValue($(this).val());
                                }).get(),
                                coverage_longitudes: $(this).find('.coverage-longitude').map(function() {
                                    return normalizeCoordinateInputValue($(this).val());
                                }).get(),
                                coverage_project_names: $(this).find('.coverage-project-name').map(function() {
                                    return $(this).val().trim();
                                }).get(),
                                coverage_project_classifications: $(this).find('.coverage-project-classification').map(function() {
                                    return ($(this).val() || '').trim();
                                }).get(),
                                coverage_project_types: $(this).find('.coverage-project-type').map(function() {
                                    return getCoverageTypeValue($(this).closest('.coverage-entry-item'));
                                }).get(),
                                coverage_fertilizer_enabled_flags: $(this).find('.coverage-fertilizer-enabled').map(function() {
                                    return ($(this).val() || '').trim();
                                }).get(),
                                coverage_fertilizer_ohn_quantities: $(this).find('.coverage-fertilizer-ohn').map(function() {
                                    return ($(this).val() || '').trim();
                                }).get(),
                                coverage_fertilizer_concoction_quantities: $(this).find('.coverage-fertilizer-concoction').map(function() {
                                    return ($(this).val() || '').trim();
                                }).get(),
                                coverage_fertilizer_vermicompost_quantities: $(this).find('.coverage-fertilizer-vermicompost').map(function() {
                                    return ($(this).val() || '').trim();
                                }).get(),
                                coverage_aquatic_resources: $(this).find('.coverage-aquatic-resource').map(function() {
                                    return getCoverageAquaticResourceValue($(this).closest('.coverage-entry-item'));
                                }).get(),
                                coverage_aquatic_resource_quantities: $(this).find('.coverage-aquatic-resource-quantity').map(function() {
                                    return ($(this).val() || '').trim();
                                }).get(),
                                coverage_actual_accomplishments: $(this).find('.coverage-type-accomplishment').map(function() {
                                    return ($(this).val() || '').trim();
                                }).get(),
                                coverage_actual_statuses: $(this).find('.coverage-status').map(function() {
                                    return ($(this).val() || 'pending').trim();
                                }).get(),
                                coverage_land_areas: $(this).find('.coverage-land-area').map(function() {
                                    return $(this).val().trim();
                                }).get(),
                                coverage_land_ownerships: $(this).find('.coverage-land-ownership').map(function() {
                                    return $(this).val().trim();
                                }).get(),
                                coverage_drive_links: $(this).find('.coverage-drive-link').map(function() {
                                    return $(this).val().trim();
                                }).get(),
                                blgu_forum_from: blguFrom,
                                blgu_forum_to: blguTo,
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
                                rows: JSON.stringify(rowsPayload),
                                csrf_token: window.KODUS_CSRF_TOKEN
                            }
                        }).then(function(saveResponse) {
                            if (!saveResponse.success) {
                                throw new Error(saveResponse.message || 'Could not save changes.');
                            }
                            return saveResponse;
                        }).catch(function(xhr) {
                            const message = xhr.responseJSON?.debug || xhr.responseJSON?.message || xhr.message || 'Could not save changes.';
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
    }

    // Details button
    $('#program-activities-table tbody').on('click', '.details-btn', function() {
        var data = table.row($(this).parents('tr')).data();
        const canEditActivity = canManageActivities && String(data?.can_edit ?? '0') === '1';
        Swal.fire({
            title: 'Program Activity Details',
            width: 900,
            customClass: {
                container: 'kodus-scrollable-swal',
                popup: 'kodus-detail-popup'
            },
            html: renderActivityDetails(data),
            didOpen: () => {
                if (window.KodusPageLoader && typeof window.KodusPageLoader.hideModalLoader === 'function') {
                    window.KodusPageLoader.hideModalLoader();
                }
            },
            confirmButtonText: canEditActivity ? '<i class="fas fa-times"></i> Close' : '<i class="fas fa-times"></i>',
            showDenyButton: canEditActivity,
            denyButtonText: '<i class="fas fa-pen"></i> Edit',
            denyButtonColor: '#007bff'
        }).then((result) => {
            if (result.isDenied) {
                openProgramActivityEditor(data);
            }
        });
    });

    $('#program-activities-table tbody').on('click', '.edit-btn', function() {
        if (!canManageActivities) {
            return;
        }

        const data = table.row($(this).parents('tr')).data();
        if (String(data?.can_edit ?? '0') !== '1') {
            return;
        }
        openProgramActivityEditor(data);
    });

    $(document).on('click', '.add-coverage-btn', function() {
        const $paneRow = $(this).closest('.edit-grid-row');
        const $currentItem = $(this).closest('.coverage-entry-item');
        const $newItem = insertCoverageEntryAfter($currentItem);
        syncCoverageFertilizerFollowup($newItem);
        syncCoverageAquaticFields($newItem);
        updateCoverageEntryState($newItem);
        syncCoverageActualTotals($paneRow);
    });

    $(document).on('click', '.barangay-pane-toggle', function() {
        $(this).closest('.edit-grid-row').toggleClass('is-collapsed');
    });

    $(document).on('click', '.barangay-tab', function() {
        const $tab = $(this);
        const tabKey = $tab.data('tab');
        const $row = $tab.closest('.edit-grid-row');

        $row.find('.barangay-tab').removeClass('is-active');
        $tab.addClass('is-active');
        $row.find('.barangay-tab-panel').removeClass('is-active');
        $row.find(`.barangay-tab-panel[data-tab-panel="${tabKey}"]`).addClass('is-active');
    });

    $(document).on('change', '.coverage-project-classification', function() {
        const $rowItem = $(this).closest('.coverage-entry-item');
        const classification = $(this).val() || '';
        const $accomplishmentInput = $rowItem.find('.coverage-type-accomplishment');
        const previousValue = getCoverageTypeValue($rowItem);
        $rowItem.find('.coverage-type-field').replaceWith(renderCoverageTypeField(classification, previousValue));
        syncCoverageTypeField($rowItem, previousValue);
        syncCoverageFertilizerFollowup($rowItem);
        promptCoverageAquaticDecision($rowItem);
        if (supportsTypeAccomplishment(classification)) {
            if (isCoverageStatusActive($rowItem.find('.coverage-status').val())) {
                $accomplishmentInput.prop('disabled', false);
            }
        } else {
            $accomplishmentInput.val('').prop('disabled', true);
        }
        updateCoverageEntryState($rowItem);
        syncCoverageActualTotals($(this).closest('.edit-grid-row'));
    });

    $(document).on('change', '.coverage-project-type', function() {
        const $rowItem = $(this).closest('.coverage-entry-item');
        syncCoverageTypeField($rowItem);
        promptCoverageAquaticDecision($rowItem);
    });

    $(document).on('input', '.coverage-project-name', function() {
        syncCoverageFertilizerFollowup($(this).closest('.coverage-entry-item'));
    });

    $(document).on('change', '.coverage-fertilizer-enabled-choice', function() {
        const $rowItem = $(this).closest('.coverage-entry-item');
        $rowItem.find('.coverage-fertilizer-enabled').val($(this).val());
        syncCoverageFertilizerFollowup($rowItem);
    });

    $(document).on('blur', '.coverage-project-type-custom', function() {
        persistCustomCoverageType($(this).closest('.coverage-entry-item'));
    });

    $(document).on('change', '.coverage-aquatic-resource', function() {
        syncCoverageAquaticFields($(this).closest('.coverage-entry-item'));
    });

    $(document).on('blur', '.coverage-aquatic-resource-custom', function() {
        persistCustomCoverageAquaticResource($(this).closest('.coverage-entry-item'));
    });

    $(document).on('click', '.coverage-aquatic-hide-btn', function() {
        const $rowItem = $(this).closest('.coverage-entry-item');
        $rowItem.find('.coverage-aquatic-enabled').val('0');
        syncCoverageAquaticFields($rowItem);
    });

    $(document).on('click', '.confirm-target-btn', function() {
        const $rowItem = $(this).closest('.coverage-entry-item');
        $rowItem.find('.coverage-status').val('confirmed');
        copyTargetValuesToCoverageEntry($rowItem);
        updateCoverageEntryState($rowItem);
        syncCoverageActualTotals($rowItem.closest('.edit-grid-row'));
    });

    $(document).on('click', '.edit-actual-btn', function() {
        const $rowItem = $(this).closest('.coverage-entry-item');
        if (normalizeCoverageStatus($rowItem.find('.coverage-status').val()) === 'pending') {
            copyTargetValuesToCoverageEntry($rowItem);
        }
        $rowItem.find('.coverage-status').val('custom');
        updateCoverageEntryState($rowItem);
        $rowItem.find('.coverage-project-name').trigger('focus');
        syncCoverageActualTotals($rowItem.closest('.edit-grid-row'));
    });

    $(document).on('click', '.reset-coverage-btn', function() {
        const $rowItem = $(this).closest('.coverage-entry-item');
        const isManualRow = $rowItem.attr('data-origin') === 'manual';
        if (isManualRow) {
            $rowItem.remove();
            syncCoverageActualTotals($(this).closest('.edit-grid-row'));
            return;
        }

        $rowItem.find('.coverage-status').val('pending');
        $rowItem.find('.coverage-purok').val('');
        $rowItem.find('.coverage-latitude').val('');
        $rowItem.find('.coverage-longitude').val('');
        $rowItem.find('.coverage-project-name').val('');
        $rowItem.find('.coverage-project-classification').val('');
        $rowItem.find('.coverage-type-field').replaceWith(renderCoverageTypeField('', ''));
        $rowItem.find('.coverage-fertilizer-enabled').val('');
        $rowItem.find('.coverage-fertilizer-enabled-choice').prop('checked', false);
        $rowItem.find('.coverage-fertilizer-ohn, .coverage-fertilizer-concoction, .coverage-fertilizer-vermicompost').val('');
        $rowItem.find('.coverage-fertilizer-fields').addClass('d-none');
        $rowItem.find('.coverage-fertilizer-followup').removeClass('is-visible');
        $rowItem.find('.coverage-aquatic-enabled').val('0');
        $rowItem.find('.coverage-aquatic-resource').val('');
        $rowItem.find('.coverage-aquatic-resource-custom').val('');
        $rowItem.find('.coverage-aquatic-resource-quantity').val('');
        $rowItem.find('.coverage-field--aquatic-resource, .coverage-field--aquatic-quantity').addClass('d-none');
        $rowItem.find('.coverage-type-accomplishment').val('');
        $rowItem.find('.coverage-land-area').val('');
        $rowItem.find('.coverage-land-ownership').val('');
        $rowItem.find('.coverage-drive-link').val('');
        updateCoverageEntryState($rowItem);
        syncCoverageActualTotals($rowItem.closest('.edit-grid-row'));
    });

    $(document).on('input change', '.coverage-land-area, .coverage-land-ownership, .coverage-drive-link', function() {
        const $rowItem = $(this).closest('.coverage-entry-item');
        if (normalizeCoverageStatus($rowItem.find('.coverage-status').val()) !== 'pending') {
            return;
        }

        copyTargetValuesToCoverageEntry($rowItem);
        $rowItem.find('.coverage-status').val('confirmed');
        updateCoverageEntryState($rowItem);
        syncCoverageActualTotals($rowItem.closest('.edit-grid-row'));
    });

    $(document).on('input change', '.coverage-type-accomplishment', function() {
        const $rowItem = $(this).closest('.coverage-entry-item');
        syncCoverageActualTotals($rowItem.closest('.edit-grid-row'));
    });

    $(document).on('click', '.remove-coverage-btn', function() {
        const $paneRow = $(this).closest('.edit-grid-row');
        const $list = $(this).closest('.coverage-list');
        $(this).closest('.coverage-entry-item').remove();
        reindexCoverageEntries($list);
        syncCoverageActualTotals($paneRow);
    });

    $(document).on('change', '.coverage-aquatic-resource', function() {
        syncCoverageAquaticFields($(this).closest('.coverage-entry-item'));
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

    $(document).on('input change', '.row-total-target, .fund-obligation-partner-beneficiaries, .fund-disbursement-served-partner-beneficiaries, .last-day-project-implementation, .check-issuance-date, .liquidation-date', function() {
        updatePostImplementationMetrics($(this).closest('.edit-grid-row'));
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
                updatePostImplementationMetrics($input.closest('.edit-grid-row'));
            });

            $input.on('cancel.daterangepicker', function() {
                $start.val('');
                $end.val('');
                $input.val('');
                updatePostImplementationMetrics($input.closest('.edit-grid-row'));
            });
        });
    }
});
</script>

<script src="<?php echo $app_root; ?>plugins/moment/moment.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/daterangepicker/daterangepicker.js"></script>

</body>
</html>
