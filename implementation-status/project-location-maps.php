<?php
require_once '../security.php';
// This page uses Leaflet tiles from OpenStreetMap and Esri, so allow only those image hosts here.
security_set_content_security_policy(security_compile_content_security_policy([
    "default-src 'self'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'self'",
    "object-src 'none'",
    "img-src 'self' data: blob: https://*.tile.openstreetmap.org https://services.arcgisonline.com",
    "font-src 'self' data:",
    "style-src 'self' 'unsafe-inline'",
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://caraga-connect.dswd.gov.ph",
    "connect-src 'self' ws: wss: https://caraga-connect.dswd.gov.ph",
    "frame-src 'self'",
    "media-src 'self' data: blob:",
    "worker-src 'self' blob:",
]));
include('../header.php');
include('../sidenav.php');

$selectedYear = (int) ($_SESSION['selected_year'] ?? date('Y'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | Project Location Maps</title>
  <link rel="stylesheet" href="<?php echo $app_root; ?>plugins/leaflet/leaflet.css">
  <style>
    .project-map-page .content-wrapper {
      background:
        radial-gradient(circle at top left, rgba(13, 110, 253, 0.08), transparent 30%),
        radial-gradient(circle at bottom right, rgba(40, 167, 69, 0.07), transparent 28%);
    }
    .project-map-shell {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }
    .project-map-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
    }
    .project-map-pills {
      display: flex;
      gap: .75rem;
      flex-wrap: wrap;
    }
    .project-map-pill {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      padding: .45rem .8rem;
      border-radius: 999px;
      background: rgba(13, 110, 253, 0.12);
      font-weight: 600;
    }
    .project-map-layout {
      display: grid;
      grid-template-columns: minmax(0, 1.7fr) minmax(320px, .9fr);
      gap: 1rem;
      align-items: start;
    }
    .project-map-card,
    .project-detail-card,
    .project-filter-card {
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 16px 32px rgba(15, 23, 42, 0.08);
    }
    .project-map-card .card-header,
    .project-detail-card .card-header,
    .project-filter-card .card-header {
      border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    }
    .project-map-card .card-header {
      gap: .9rem;
    }
    .project-map-card .card-title {
      float: none;
      line-height: 1.2;
    }
    .project-map-canvas {
      position: relative;
    }
    #project-location-map {
      width: 100%;
      min-height: 68vh;
      border-radius: 0 0 18px 18px;
      background: #dbeafe;
    }
    .project-map-empty {
      min-height: 68vh;
      display: none;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      gap: .75rem;
      text-align: center;
      color: #4b5563;
      background: linear-gradient(135deg, rgba(13, 110, 253, 0.06), rgba(40, 167, 69, 0.05));
      padding: 2rem;
    }
    .project-map-empty.is-visible {
      display: flex;
    }
    .project-map-empty i {
      font-size: 2.5rem;
      color: #0d6efd;
    }
    .project-detail-card {
      position: sticky;
      top: 5.5rem;
    }
    .project-detail-state {
      min-height: 68vh;
      height: 68vh;
      display: flex;
      flex-direction: column;
      gap: 1rem;
      overflow-y: auto;
      overflow-x: hidden;
    }
    .project-detail-placeholder {
      flex: 1 1 auto;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      border: 1px dashed rgba(13, 110, 253, 0.2);
      border-radius: 16px;
      background: rgba(13, 110, 253, 0.04);
      padding: 1.5rem;
      color: #6b7280;
    }
    .project-detail-body {
      display: none;
      flex-direction: column;
      gap: 1rem;
    }
    .project-detail-body.is-visible {
      display: flex;
    }
    .project-detail-title {
      margin: 0;
      font-size: 1.15rem;
      font-weight: 700;
      color: #16324f;
    }
    .project-detail-subtitle {
      margin: .35rem 0 0;
      color: #5f7488;
    }
    .project-detail-badges {
      display: flex;
      gap: .5rem;
      flex-wrap: wrap;
    }
    .project-detail-badge {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .38rem .7rem;
      border-radius: 999px;
      font-size: .75rem;
      font-weight: 700;
      letter-spacing: .02em;
      background: rgba(13, 110, 253, 0.1);
      color: #0d3f8f;
    }
    .project-detail-badge--activity {
      background: rgba(40, 167, 69, 0.12);
      color: #1d6f42;
    }
    .project-detail-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: .75rem;
    }
    .project-detail-item {
      padding: .8rem .9rem;
      border-radius: 14px;
      background: rgba(13, 110, 253, 0.04);
      border: 1px solid rgba(13, 110, 253, 0.10);
      min-width: 0;
    }
    .project-detail-item dt {
      margin: 0 0 .35rem;
      font-size: .72rem;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #5f7488;
    }
    .project-detail-item dd {
      margin: 0;
      font-weight: 600;
      word-break: break-word;
      color: #1f2d3d;
    }
    .project-detail-section {
      border: 1px solid rgba(13, 110, 253, 0.10);
      border-radius: 16px;
      padding: .95rem 1rem;
      background: linear-gradient(180deg, rgba(248, 250, 252, 0.96), rgba(255, 255, 255, 0.98));
    }
    .project-detail-section-title {
      margin: 0 0 .75rem;
      font-size: .9rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #16324f;
    }
    .project-filter-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: .75rem;
    }
    .project-map-legend {
      display: flex;
      gap: .75rem;
      flex-wrap: wrap;
      margin-top: .75rem;
    }
    .project-map-legend-item {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      font-size: .82rem;
      font-weight: 600;
    }
    .project-map-legend-dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      display: inline-block;
    }
    .leaflet-popup-content-wrapper {
      border-radius: 14px;
    }
    .project-popup-title {
      margin: 0 0 .35rem;
      font-size: .95rem;
      font-weight: 700;
    }
    .project-popup-meta {
      font-size: .8rem;
      color: #4b5563;
      line-height: 1.45;
    }
    .project-popup-link {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      margin-top: .55rem;
      font-weight: 700;
      color: #0d6efd;
      cursor: pointer;
      text-decoration: none;
    }
    .project-map-view-switcher {
      position: absolute;
      top: .8rem;
      right: .8rem;
      z-index: 500;
      display: inline-flex;
      gap: .4rem;
      flex-wrap: wrap;
      justify-content: flex-end;
      max-width: calc(100% - 1.6rem);
    }
    .project-map-view-tile {
      position: relative;
      min-width: 0;
      border: 1px solid rgba(13, 110, 253, 0.16);
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.96);
      color: #1f2d3d;
      padding: .35rem .65rem;
      text-align: left;
      cursor: pointer;
      transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background-color .18s ease;
      box-shadow: 0 8px 18px rgba(15, 23, 42, 0.12);
      display: inline-flex;
      align-items: center;
      gap: .38rem;
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
    }
    .project-map-view-tile:hover,
    .project-map-view-tile:focus {
      outline: none;
      transform: translateY(-1px);
      border-color: rgba(13, 110, 253, 0.28);
      box-shadow: 0 10px 20px rgba(13, 110, 253, 0.14);
    }
    .project-map-view-tile.is-active {
      border-color: rgba(13, 110, 253, 0.48);
      background: linear-gradient(180deg, rgba(13, 110, 253, 0.22), rgba(13, 110, 253, 0.14));
      box-shadow: 0 10px 22px rgba(13, 110, 253, 0.20);
    }
    .project-map-view-tile.is-active .project-map-view-label {
      color: #083b88;
    }
    .project-map-view-tile.is-active .project-map-view-note {
      color: #1f4c8d;
    }
    .project-map-view-preview {
      width: 15px;
      height: 15px;
      border-radius: 50%;
      margin-bottom: 0;
      border: 1px solid rgba(15, 23, 42, 0.08);
      overflow: hidden;
      background-size: cover;
      background-position: center;
      flex: 0 0 auto;
    }
    .project-map-view-preview--standard {
      background:
        linear-gradient(135deg, rgba(56, 189, 248, 0.85), rgba(14, 116, 144, 0.72)),
        linear-gradient(0deg, rgba(34, 197, 94, 0.72) 0 45%, rgba(191, 219, 254, 0.92) 45% 100%);
    }
    .project-map-view-preview--satellite {
      background:
        linear-gradient(135deg, rgba(22, 101, 52, 0.78), rgba(113, 63, 18, 0.72)),
        radial-gradient(circle at 30% 35%, rgba(134, 239, 172, 0.38), transparent 28%),
        radial-gradient(circle at 72% 58%, rgba(253, 224, 71, 0.22), transparent 24%);
    }
    .project-map-view-preview--hybrid {
      background:
        linear-gradient(135deg, rgba(22, 101, 52, 0.72), rgba(30, 41, 59, 0.78)),
        repeating-linear-gradient(90deg, rgba(255, 255, 255, 0.28) 0 2px, transparent 2px 18px),
        repeating-linear-gradient(0deg, rgba(255, 255, 255, 0.18) 0 2px, transparent 2px 16px);
    }
    .project-map-view-label {
      display: block;
      font-size: .73rem;
      font-weight: 700;
      line-height: 1.1;
    }
    .project-map-view-note {
      display: block;
      margin-top: 0;
      font-size: .62rem;
      color: #5f7488;
      line-height: 1.1;
      white-space: nowrap;
    }
    body.dark-mode .project-map-view-tile,
    body[data-theme="dark"] .project-map-view-tile {
      border-color: rgba(125, 196, 255, 0.24);
      background: rgba(20, 30, 43, 0.88);
      color: #e8eef5;
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.28);
    }
    body.dark-mode .project-map-view-tile:hover,
    body.dark-mode .project-map-view-tile:focus,
    body[data-theme="dark"] .project-map-view-tile:hover,
    body[data-theme="dark"] .project-map-view-tile:focus {
      border-color: rgba(125, 196, 255, 0.42);
      box-shadow: 0 12px 26px rgba(13, 110, 253, 0.24);
    }
    body.dark-mode .project-map-view-tile.is-active,
    body[data-theme="dark"] .project-map-view-tile.is-active {
      border-color: rgba(157, 214, 255, 0.62);
      background: linear-gradient(180deg, rgba(25, 89, 179, 0.78), rgba(13, 110, 253, 0.52));
      box-shadow: 0 12px 26px rgba(13, 110, 253, 0.34);
    }
    body.dark-mode .project-map-view-tile .project-map-view-label,
    body[data-theme="dark"] .project-map-view-tile .project-map-view-label {
      color: #e8eef5;
    }
    body.dark-mode .project-map-view-tile .project-map-view-note,
    body[data-theme="dark"] .project-map-view-tile .project-map-view-note {
      color: #b4c3d3;
    }
    body.dark-mode .project-map-view-tile.is-active .project-map-view-label,
    body[data-theme="dark"] .project-map-view-tile.is-active .project-map-view-label {
      color: #ffffff;
    }
    body.dark-mode .project-map-view-tile.is-active .project-map-view-note,
    body[data-theme="dark"] .project-map-view-tile.is-active .project-map-view-note {
      color: rgba(255, 255, 255, 0.88);
    }
    body.dark-mode .project-map-view-preview,
    body[data-theme="dark"] .project-map-view-preview {
      border-color: rgba(255, 255, 255, 0.14);
    }
    body[data-theme="light"] .project-map-view-tile {
      border-color: rgba(13, 110, 253, 0.16);
      background: rgba(255, 255, 255, 0.96);
      color: #1f2d3d;
      box-shadow: 0 8px 18px rgba(15, 23, 42, 0.12);
    }
    body[data-theme="light"] .project-map-view-tile.is-active {
      border-color: rgba(13, 110, 253, 0.48);
      background: linear-gradient(180deg, rgba(13, 110, 253, 0.22), rgba(13, 110, 253, 0.14));
      box-shadow: 0 10px 22px rgba(13, 110, 253, 0.20);
    }
    #map-status-text {
      margin: 0;
      line-height: 1.3;
      white-space: normal;
    }
    .project-map-page .leaflet-bar a {
      background-color: #ffffff;
      color: #1f2d3d;
      border-bottom-color: rgba(15, 23, 42, 0.06);
    }
    .project-map-page .leaflet-bar a:hover,
    .project-map-page .leaflet-bar a:focus {
      background-color: rgba(13, 110, 253, 0.08);
      color: #0d6efd;
    }
    .project-detail-modal .modal-content {
      border: 0;
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 20px 42px rgba(15, 23, 42, 0.2);
    }
    .project-detail-modal .modal-header {
      border-bottom: 1px solid rgba(13, 110, 253, 0.10);
      background: linear-gradient(180deg, rgba(248, 250, 252, 0.96), rgba(255, 255, 255, 0.98));
    }
    .project-detail-modal .modal-title {
      color: #16324f;
      font-weight: 700;
    }
    .project-detail-modal .modal-body {
      background: #ffffff;
      padding: 1rem;
    }
    @media (max-width: 1199.98px) {
      .project-map-layout {
        grid-template-columns: 1fr;
      }
      .project-detail-card {
        position: static;
      }
      .project-filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }
    @media (max-width: 767.98px) {
      #project-location-map,
      .project-map-empty,
      .project-detail-state {
        min-height: 55vh;
        height: 55vh;
      }
      .project-detail-card {
        display: none;
      }
      .project-filter-grid,
      .project-detail-grid {
        grid-template-columns: 1fr;
      }
      .project-map-view-tile {
        padding: .32rem .58rem;
      }
      .project-map-view-note {
        display: none;
      }
      .project-map-view-switcher {
        top: .6rem;
        right: .6rem;
        left: .6rem;
        max-width: none;
      }
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed project-map-page">
<div class="wrapper">
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">Project Location Maps</h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../home">Home</a></li>
              <li class="breadcrumb-item active">Project Location Maps</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="project-map-shell">
          <div class="card project-filter-card">
            <div class="card-header">
              <div class="project-map-toolbar">
                <div class="project-map-pills">
                  <span class="project-map-pill"><i class="fas fa-calendar-alt"></i> Fiscal Year <?php echo htmlspecialchars((string) $selectedYear, ENT_QUOTES, 'UTF-8'); ?></span>
                  <span class="project-map-pill"><i class="fas fa-map-pin"></i> Actual implementation locations</span>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="fitAllMarkersBtn">
                  <i class="fas fa-expand-arrows-alt mr-1"></i> Fit All Markers
                </button>
              </div>
            </div>
            <div class="card-body">
              <div class="project-filter-grid">
                <div class="form-group mb-0">
                  <label for="project-source-filter">Source</label>
                  <select id="project-source-filter" class="custom-select">
                    <option value="all">All actual sources</option>
                    <option value="program-activities">Program Activities</option>
                  </select>
                </div>
                <div class="form-group mb-0">
                  <label for="project-municipality-filter">Municipality</label>
                  <select id="project-municipality-filter" class="custom-select">
                    <option value="all">All municipalities</option>
                  </select>
                </div>
                <div class="form-group mb-0">
                  <label for="project-classification-filter">Classification</label>
                  <select id="project-classification-filter" class="custom-select">
                    <option value="all">All classifications</option>
                  </select>
                </div>
                <div class="form-group mb-0">
                  <label for="project-search-filter">Search</label>
                  <input id="project-search-filter" type="text" class="form-control" placeholder="Project, barangay, purok">
                </div>
              </div>
              <div class="project-map-legend">
                <span class="project-map-legend-item"><span class="project-map-legend-dot" style="background:#28a745;"></span>Program Activity</span>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-3 col-6">
              <div class="small-box bg-info">
                <div class="inner">
                  <h3 id="summary-total-markers">0</h3>
                  <p>Total Markers</p>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="small-box bg-primary">
                <div class="inner">
                  <h3 id="summary-target-markers">0</h3>
                  <p>Linked Target Rows</p>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="small-box bg-success">
                <div class="inner">
                  <h3 id="summary-activity-markers">0</h3>
                  <p>Activity Pins</p>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="small-box bg-secondary">
                <div class="inner">
                  <h3 id="summary-municipalities">0</h3>
                  <p>Municipalities Covered</p>
                </div>
              </div>
            </div>
          </div>

          <div class="project-map-layout">
            <div class="card project-map-card">
              <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:.75rem;">
                <div>
                  <h3 class="card-title mb-0">Map View</h3>
                  <span class="text-muted small d-block mt-1" id="map-status-text">Loading project markers...</span>
                </div>
              </div>
              <div class="card-body p-0">
                <div class="project-map-canvas">
                  <div class="project-map-view-switcher" aria-label="Map view selector">
                    <button type="button" class="project-map-view-tile" data-layer-key="standard" aria-pressed="false">
                      <span class="project-map-view-preview project-map-view-preview--standard"></span>
                      <span>
                        <span class="project-map-view-label">Standard</span>
                        <span class="project-map-view-note">Roads and places</span>
                      </span>
                    </button>
                    <button type="button" class="project-map-view-tile" data-layer-key="satellite" aria-pressed="false">
                      <span class="project-map-view-preview project-map-view-preview--satellite"></span>
                      <span>
                        <span class="project-map-view-label">Satellite</span>
                        <span class="project-map-view-note">Imagery only</span>
                      </span>
                    </button>
                    <button type="button" class="project-map-view-tile is-active" data-layer-key="hybrid" aria-pressed="true">
                      <span class="project-map-view-preview project-map-view-preview--hybrid"></span>
                      <span>
                        <span class="project-map-view-label">Hybrid</span>
                        <span class="project-map-view-note">Imagery with labels</span>
                      </span>
                    </button>
                  </div>
                  <div id="project-location-map"></div>
                </div>
                <div id="project-map-empty" class="project-map-empty">
                  <i class="fas fa-map-marked-alt"></i>
                  <h4 class="mb-1">No Coordinates Available</h4>
                  <p class="mb-0">No projects with valid latitude and longitude were found for the active filters.</p>
                </div>
              </div>
            </div>

            <div class="card project-detail-card">
              <div class="card-header">
                <h3 class="card-title mb-0">Project Details</h3>
              </div>
              <div class="card-body">
                <div class="project-detail-state">
                  <div id="project-detail-placeholder" class="project-detail-placeholder">
                    Select a marker on the map to view the full details for that specific project.
                  </div>
                  <div id="project-detail-body" class="project-detail-body"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade project-detail-modal" id="projectDetailModal" tabindex="-1" role="dialog" aria-labelledby="projectDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="projectDetailModalLabel">Project Details</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="project-detail-modal-body"></div>
      </div>
    </div>
  </div>
</div>

<script src="<?php echo $app_root; ?>plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/leaflet/leaflet.js"></script>
<script>
$(function() {
    const endpointUrl = 'fetch-project-location-maps.php';
    const defaultCenter = [12.8797, 121.7740];
    const map = L.map('project-location-map', {
        zoomControl: true,
        attributionControl: true
    }).setView(defaultCenter, 6);
    const standardLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    });
    const satelliteLayer = L.tileLayer('https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19,
        attribution: 'Tiles &copy; Esri'
    });
    const hybridLabelsLayer = L.tileLayer('https://services.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19,
        attribution: 'Labels &copy; Esri'
    });
    const hybridLayer = L.layerGroup([satelliteLayer, hybridLabelsLayer]);
    const baseLayers = {
        standard: standardLayer,
        satellite: satelliteLayer,
        hybrid: hybridLayer
    };
    let currentBaseLayerKey = 'standard';
    let satelliteWarningShown = false;
    let satelliteFailureCount = 0;
    let satelliteFailureTimer = null;

    function resetSatelliteFailureWindow() {
        satelliteFailureCount = 0;
        if (satelliteFailureTimer) {
            clearTimeout(satelliteFailureTimer);
            satelliteFailureTimer = null;
        }
    }

    function showSatelliteUnavailableMessage() {
        if (satelliteWarningShown) {
            return;
        }
        satelliteWarningShown = true;
        if (window.Swal) {
            Swal.fire({
                icon: 'warning',
                title: 'Satellite View Unavailable',
                text: 'Satellite tiles are currently unavailable, so the map has been switched back to the standard view.',
                confirmButtonText: 'OK'
            });
        }
    }

    function switchBaseLayer(layerKey, options = {}) {
        const normalizedKey = baseLayers[layerKey] ? layerKey : 'standard';
        Object.keys(baseLayers).forEach((key) => {
            if (map.hasLayer(baseLayers[key])) {
                map.removeLayer(baseLayers[key]);
            }
        });
        map.addLayer(baseLayers[normalizedKey]);
        currentBaseLayerKey = normalizedKey;
        $('.project-map-view-tile').removeClass('is-active').attr('aria-pressed', 'false');
        $(`.project-map-view-tile[data-layer-key="${normalizedKey}"]`).addClass('is-active').attr('aria-pressed', 'true');
        if (normalizedKey === 'standard') {
            resetSatelliteFailureWindow();
        }
        if (options.updateStatus !== false) {
            const tileLabels = {
                standard: 'standard',
                satellite: 'satellite',
                hybrid: 'hybrid'
            };
            $('#map-status-text').text('Loading ' + tileLabels[normalizedKey] + ' tiles...');
        }
    }

    function handleSatelliteTileError() {
        if (currentBaseLayerKey === 'standard') {
            return;
        }
        satelliteFailureCount += 1;
        if (satelliteFailureTimer) {
            clearTimeout(satelliteFailureTimer);
        }
        satelliteFailureTimer = setTimeout(resetSatelliteFailureWindow, 4000);
        if (satelliteFailureCount < 3) {
            return;
        }
        switchBaseLayer('standard', { updateStatus: false });
        showSatelliteUnavailableMessage();
        const markerCount = markerLayer.getLayers().length;
        $('#map-status-text').text(markerCount ? `${markerCount} project location${markerCount === 1 ? '' : 's'} shown on the map.` : 'Satellite view is unavailable right now. Showing the standard map instead.');
    }

    satelliteLayer.on('tileerror', handleSatelliteTileError);
    hybridLabelsLayer.on('tileerror', handleSatelliteTileError);
    switchBaseLayer('hybrid', { updateStatus: false });

    const markerLayer = L.layerGroup().addTo(map);
    const markerByProjectId = new Map();
    const mobileDetailMedia = window.matchMedia('(max-width: 767.98px)');
    let allMarkers = [];
    let activeProjectId = null;

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    function formatFallback(value, fallback = 'Not provided') {
        const text = String(value || '').trim();
        return text !== '' ? escapeHtml(text) : `<span class="text-muted">${escapeHtml(fallback)}</span>`;
    }

    function titleCaseStatus(value) {
        const normalized = String(value || '').trim();
        if (normalized === '') {
            return 'Not specified';
        }
        return normalized.split(/[_\s-]+/).map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');
    }

    function markerColorForSource(source) {
        return source === 'program-activities' ? '#28a745' : '#0d6efd';
    }

    function buildPopupHtml(marker) {
        return `
            <div>
                <div class="project-popup-title">${escapeHtml(marker.title)}</div>
                <div class="project-popup-meta">
                    ${escapeHtml(marker.module_label)}<br>
                    ${escapeHtml([marker.barangay, marker.municipality].filter(Boolean).join(', '))}<br>
                    ${escapeHtml(marker.latitude.toFixed(6))}, ${escapeHtml(marker.longitude.toFixed(6))}
                </div>
                <a href="#" class="project-popup-link js-project-popup-link" data-project-id="${escapeHtml(marker.project_id)}">
                    <i class="fas fa-info-circle"></i> View details
                </a>
            </div>
        `;
    }

    function buildDetailGridItem(label, value, fallback) {
        return `
            <div class="project-detail-item">
                <dt>${escapeHtml(label)}</dt>
                <dd>${formatFallback(value, fallback)}</dd>
            </div>
        `;
    }

    function isMobileDetailMode() {
        return mobileDetailMedia.matches;
    }

    function buildProjectDetailsHtml(marker) {
        const detailEntries = [
            ['Project ID', marker.project_id, 'Not assigned'],
            ['Module Source', marker.module_label, 'Not provided'],
            ['Status', marker.status_label || titleCaseStatus(marker.status), 'Not provided'],
            ['Classification', marker.classification, 'Not provided'],
            ['Project Type', marker.project_type, 'Not provided'],
            ['Province', marker.province, 'Not provided'],
            ['Municipality', marker.municipality, 'Not provided'],
            ['Barangay', marker.barangay, 'Not provided'],
            ['Purok', marker.purok, 'Not provided'],
            ['Latitude', marker.latitude != null ? marker.latitude.toFixed(6) : '', 'Not provided'],
            ['Longitude', marker.longitude != null ? marker.longitude.toFixed(6) : '', 'Not provided'],
            ['Last Updated', marker.updated_at, 'Not provided']
        ];

        const details = marker.details || {};
        const extraEntries = [
            ['Actual Accomplishment', details.actual_accomplishment, 'Not provided'],
            ['Land Utilization', details.land_area, 'Not provided'],
            ['Land Ownership', details.land_ownership, 'Not provided'],
            ['Target Partner-Beneficiaries', marker.target_partner_beneficiaries, 'Not provided'],
            ['Fertilizer Enabled', details.fertilizer_enabled === '1' ? 'Yes' : details.fertilizer_enabled === '0' ? 'No' : '', 'Not provided'],
            ['OHN', details.fertilizer_ohn_target || details.fertilizer_ohn_quantity, 'Not provided'],
            ['Concoction/Vermitea', details.fertilizer_concoction_target || details.fertilizer_concoction_quantity, 'Not provided'],
            ['Vermicompost/Vermicast', details.fertilizer_vermicompost_target || details.fertilizer_vermicompost_quantity, 'Not provided'],
            ['BINHI Target Quantity', details.binhi_target_quantity, 'Not provided'],
            ['Aquatic Resource', details.aquatic_resource, 'Not provided'],
            ['Aquatic Resource Quantity', details.aquatic_resource_quantity, 'Not provided']
        ];

        const html = `
            <div class="project-detail-section">
                <h4 class="project-detail-title">${escapeHtml(marker.title)}</h4>
                <p class="project-detail-subtitle mb-0">${escapeHtml([marker.purok, marker.barangay, marker.municipality].filter(Boolean).join(', ')) || 'Location details not provided'}</p>
            </div>
            <div class="project-detail-badges">
                <span class="project-detail-badge ${marker.module_source === 'program-activities' ? 'project-detail-badge--activity' : ''}">
                    <i class="fas ${marker.module_source === 'program-activities' ? 'fa-tasks' : 'fa-bullseye'}"></i>
                    ${escapeHtml(marker.module_label)}
                </span>
                <span class="project-detail-badge">
                    <i class="fas fa-map-pin"></i>
                    ${escapeHtml(marker.latitude.toFixed(6))}, ${escapeHtml(marker.longitude.toFixed(6))}
                </span>
            </div>
            <div class="project-detail-section">
                <h5 class="project-detail-section-title">Primary Details</h5>
                <div class="project-detail-grid">
                    ${detailEntries.map(([label, value, fallback]) => buildDetailGridItem(label, value, fallback)).join('')}
                </div>
            </div>
            <div class="project-detail-section">
                <h5 class="project-detail-section-title">Additional Metadata</h5>
                <div class="project-detail-grid">
                    ${extraEntries.map(([label, value, fallback]) => buildDetailGridItem(label, value, fallback)).join('')}
                </div>
            </div>
        `;

        return html;
    }

    function hideProjectDetailsModal() {
        if ($('#projectDetailModal').hasClass('show')) {
            $('#projectDetailModal').modal('hide');
        }
    }

    function showProjectDetailsModal(marker, detailHtml) {
        $('#projectDetailModalLabel').text(marker && marker.title ? marker.title : 'Project Details');
        $('#project-detail-modal-body').html(detailHtml || '');
        $('#projectDetailModal').modal('show');
    }

    function renderProjectDetails(marker, options = {}) {
        if (!marker) {
            $('#project-detail-body').removeClass('is-visible').empty();
            $('#project-detail-placeholder').show();
            $('#project-detail-modal-body').empty();
            hideProjectDetailsModal();
            return;
        }

        const detailHtml = buildProjectDetailsHtml(marker);

        $('#project-detail-placeholder').hide();
        $('#project-detail-body').html(detailHtml).addClass('is-visible');

        if (isMobileDetailMode()) {
            if (options.showModal) {
                showProjectDetailsModal(marker, detailHtml);
            }
            return;
        }

        $('#project-detail-modal-body').html(detailHtml);
        hideProjectDetailsModal();
    }

    function selectMarkerById(projectId, options = {}) {
        const markerKey = String(projectId || '').trim();
        if (markerKey === '' || !markerByProjectId.has(markerKey)) {
            return;
        }

        const selection = markerByProjectId.get(markerKey);
        activeProjectId = selection.marker.project_id;
        renderProjectDetails(selection.marker, { showModal: !!options.showModal });

        if (options.openPopup) {
            selection.leafletMarker.openPopup();
        }

        if (options.focusMap) {
            map.setView(selection.leafletMarker.getLatLng(), Math.max(map.getZoom(), 14));
        }
    }

    function populateFilterOptions(markers) {
        const municipalityOptions = [...new Set(markers.map((marker) => (marker.municipality || '').trim()).filter(Boolean))].sort();
        const classificationOptions = [...new Set(markers.map((marker) => (marker.classification || '').trim()).filter(Boolean))].sort();

        $('#project-municipality-filter').html(['<option value="all">All municipalities</option>'].concat(
            municipalityOptions.map((value) => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`)
        ).join(''));

        $('#project-classification-filter').html(['<option value="all">All classifications</option>'].concat(
            classificationOptions.map((value) => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`)
        ).join(''));
    }

    function getFilteredMarkers() {
        const sourceFilter = $('#project-source-filter').val();
        const municipalityFilter = $('#project-municipality-filter').val();
        const classificationFilter = $('#project-classification-filter').val();
        const searchFilter = ($('#project-search-filter').val() || '').trim().toLowerCase();

        return allMarkers.filter((marker) => {
            if (sourceFilter !== 'all' && marker.module_source !== sourceFilter) {
                return false;
            }
            if (municipalityFilter !== 'all' && String(marker.municipality || '') !== municipalityFilter) {
                return false;
            }
            if (classificationFilter !== 'all' && String(marker.classification || '') !== classificationFilter) {
                return false;
            }
            if (searchFilter !== '') {
                const haystack = [
                    marker.title,
                    marker.project_type,
                    marker.classification,
                    marker.purok,
                    marker.barangay,
                    marker.municipality,
                    marker.province,
                    marker.project_id
                ].join(' ').toLowerCase();
                if (!haystack.includes(searchFilter)) {
                    return false;
                }
            }
            return true;
        });
    }

    function fitMarkers(markers) {
        if (!markers.length) {
            map.setView(defaultCenter, 6);
            return;
        }

        const bounds = L.latLngBounds(markers.map((marker) => [marker.latitude, marker.longitude]));
        map.fitBounds(bounds.pad(0.2), { maxZoom: 15 });
    }

    function refreshSummary(markers) {
        const municipalities = new Set(markers.map((marker) => (marker.municipality || '').trim()).filter(Boolean));
        $('#summary-total-markers').text(markers.length);
        $('#summary-target-markers').text(markers.filter((marker) => String(marker.target_project_row_id || '').trim() !== '').length);
        $('#summary-activity-markers').text(markers.filter((marker) => marker.module_source === 'program-activities').length);
        $('#summary-municipalities').text(municipalities.size);
        $('#map-status-text').text(markers.length ? `${markers.length} project location${markers.length === 1 ? '' : 's'} shown on the map.` : 'No projects match the active filters.');
    }

    function renderMarkers() {
        const filteredMarkers = getFilteredMarkers();
        markerLayer.clearLayers();
        markerByProjectId.clear();
        refreshSummary(filteredMarkers);

        if (!filteredMarkers.length) {
            $('#project-location-map').hide();
            $('#project-map-empty').addClass('is-visible');
            renderProjectDetails(null);
            return;
        }

        $('#project-map-empty').removeClass('is-visible');
        $('#project-location-map').show();

        filteredMarkers.forEach((marker) => {
            const leafletMarker = L.circleMarker([marker.latitude, marker.longitude], {
                radius: 8,
                weight: 2,
                color: '#ffffff',
                fillColor: markerColorForSource(marker.module_source),
                fillOpacity: 0.9
            });

            leafletMarker.bindPopup(buildPopupHtml(marker), { maxWidth: 280 });
            leafletMarker.on('click', function() {
                selectMarkerById(marker.project_id, { showModal: true });
            });
            leafletMarker.on('popupopen', function() {
                selectMarkerById(marker.project_id);
            });

            leafletMarker.addTo(markerLayer);
            markerByProjectId.set(String(marker.project_id), { marker, leafletMarker });
        });

        fitMarkers(filteredMarkers);

        if (activeProjectId && markerByProjectId.has(String(activeProjectId))) {
            selectMarkerById(activeProjectId);
        } else {
            selectMarkerById(filteredMarkers[0].project_id);
        }
    }

    function normalizeMarker(rawMarker) {
        return {
            ...rawMarker,
            latitude: Number(rawMarker.latitude),
            longitude: Number(rawMarker.longitude)
        };
    }

    function loadMarkers() {
        $('#map-status-text').text('Loading project markers...');
        $.getJSON(endpointUrl)
            .done(function(response) {
                if (!response.success) {
                    throw new Error(response.message || 'Could not load project locations.');
                }

                allMarkers = (response.markers || []).map(normalizeMarker).filter((marker) =>
                    Number.isFinite(marker.latitude) && Number.isFinite(marker.longitude)
                );
                populateFilterOptions(allMarkers);
                renderMarkers();
            })
            .fail(function(xhr) {
                const message = xhr.responseJSON?.message || 'Could not load project locations.';
                $('#project-location-map').hide();
                $('#project-map-empty')
                    .addClass('is-visible')
                    .html(`<i class="fas fa-exclamation-triangle"></i><h4 class="mb-1">Map Unavailable</h4><p class="mb-0">${escapeHtml(message)}</p>`);
                $('#map-status-text').text('Unable to load map data.');
                if (window.Swal) {
                    Swal.fire('Error', message, 'error');
                }
            });
    }

    $('#project-source-filter, #project-municipality-filter, #project-classification-filter').on('change', renderMarkers);
    $('#project-search-filter').on('input', renderMarkers);
    $('#fitAllMarkersBtn').on('click', function() {
        fitMarkers(getFilteredMarkers());
    });
    $('.project-map-view-tile').on('click', function() {
        const layerKey = String($(this).data('layerKey') || '').trim();
        if (layerKey === '') {
            return;
        }
        switchBaseLayer(layerKey);
    });

    $(document).on('click', '.js-project-popup-link', function(event) {
        event.preventDefault();
        const projectId = String($(this).data('projectId') || '');
        selectMarkerById(projectId, { openPopup: true, focusMap: true, showModal: true });
    });

    $(window).on('resize', function() {
        if (!isMobileDetailMode()) {
            hideProjectDetailsModal();
        }
    });

    loadMarkers();
});
</script>
</body>
</html>
