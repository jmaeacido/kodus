<?php
include('../header.php');
include('../sidenav.php');
require_once __DIR__ . '/helpers/generator.php';

$selectedYear = (int) ($_SESSION['selected_year'] ?? date('Y'));
$locations = cash_advance_location_options($conn, $selectedYear);
$manualFields = cash_advance_manual_fields();
$templates = cash_advance_templates();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>KODUS | Cash Advance Requirements</title>
  <style>
    .ca-tool-card {
      border: 1px solid rgba(13, 110, 253, 0.12);
      border-radius: 0.65rem;
      background: linear-gradient(135deg, rgba(13, 110, 253, 0.07), rgba(32, 201, 151, 0.05));
      padding: 1rem;
    }

    .ca-requirements-page {
      padding-bottom: 7rem;
    }

    .ca-requirements-page .content {
      padding-bottom: 4rem;
    }

    .ca-tool-card h5 {
      margin-bottom: 0.35rem;
      font-weight: 700;
    }

    .ca-tool-card p,
    .ca-manual-note {
      color: #6c757d;
    }

    body[data-theme="dark"] .ca-tool-card p,
    body[data-theme="dark"] .ca-manual-note {
      color: #b8c7d9;
    }

    .ca-template-list {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
      gap: 0.75rem;
      margin: 1rem 0 0;
    }

    .ca-template-item {
      display: flex;
      gap: 0.7rem;
      align-items: flex-start;
      min-height: 4.75rem;
      padding: 0.85rem;
      border: 1px solid rgba(108, 117, 125, 0.18);
      border-radius: 0.5rem;
      background: rgba(255, 255, 255, 0.72);
    }

    body[data-theme="dark"] .ca-template-item {
      background: rgba(255, 255, 255, 0.04);
      border-color: rgba(255, 255, 255, 0.12);
    }

    .ca-template-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 2rem;
      height: 2rem;
      border-radius: 0.45rem;
      background: rgba(40, 167, 69, 0.13);
      color: #198754;
      flex: 0 0 auto;
    }

    .ca-template-title {
      margin: 0;
      font-weight: 700;
      line-height: 1.25;
    }

    .ca-template-file {
      display: block;
      margin-top: 0.2rem;
      font-size: 0.82rem;
      color: #6c757d;
      word-break: break-word;
    }

    body[data-theme="dark"] .ca-template-file {
      color: #b8c7d9;
    }

    .ca-manual-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 0.9rem 1rem;
    }

    .ca-manual-group {
      padding: 1rem;
      border: 1px solid rgba(108, 117, 125, 0.18);
      border-radius: 0.55rem;
      background: rgba(248, 249, 250, 0.72);
    }

    body[data-theme="dark"] .ca-manual-group {
      background: rgba(255, 255, 255, 0.03);
      border-color: rgba(255, 255, 255, 0.12);
    }

    .ca-manual-group-title {
      margin: 0 0 0.75rem;
      font-size: 0.86rem;
      font-weight: 800;
      letter-spacing: 0.03em;
      text-transform: uppercase;
      color: #495057;
    }

    body[data-theme="dark"] .ca-manual-group-title {
      color: #d7e3f2;
    }
  </style>
</head>
<body>
<div class="content-wrapper ca-requirements-page">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0">Cash Advance Requirements</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="<?= htmlspecialchars($app_root, ENT_QUOTES, 'UTF-8') ?>home">Home</a></li>
            <li class="breadcrumb-item active">Cash Advance Requirements</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-lg-12">
          <div class="card card-primary card-outline">
            <div class="card-header">
              <h5 class="card-title mb-0">Generate Forms</h5>
            </div>
            <div class="card-body">
              <div class="ca-tool-card">
                <h5>Municipality Package</h5>
                <p>Select a municipality to generate the required Excel files using MEB data from fiscal year <?= htmlspecialchars((string) $selectedYear, ENT_QUOTES, 'UTF-8') ?>. Manual marker fields are optional and will be left blank when skipped.</p>
                <div class="ca-template-list">
                  <?php foreach ($templates as $template): ?>
                    <div class="ca-template-item">
                      <span class="ca-template-icon"><i class="fas fa-file-excel"></i></span>
                      <span>
                        <span class="ca-template-title"><?= htmlspecialchars((string) $template['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="ca-template-file"><?= htmlspecialchars((string) $template['filename'], ENT_QUOTES, 'UTF-8') ?></span>
                      </span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>

              <form action="generate.php" method="post" class="mt-4" id="cashAdvanceGenerateForm" data-loader-text="Generating cash advance requirement files...">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                  <label for="ca-location">Municipality</label>
                  <select name="location" id="ca-location" class="form-control" required>
                    <option value="">Select municipality</option>
                    <?php foreach ($locations as $location): ?>
                      <option value="<?= htmlspecialchars((string) $location['value'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars((string) $location['label'], ENT_QUOTES, 'UTF-8') ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="mt-4">
                  <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                    <h5 class="mb-0">Optional Manual Fields</h5>
                    <span class="ca-manual-note">Blank values are allowed.</span>
                  </div>

                  <?php
                    $fieldsByTemplate = [];
                    foreach ($manualFields as $name => $field) {
                        $fieldsByTemplate[(string) $field['template']][$name] = $field;
                    }
                  ?>
                  <div class="ca-manual-grid">
                    <?php foreach ($fieldsByTemplate as $templateLabel => $fields): ?>
                      <div class="ca-manual-group">
                        <h6 class="ca-manual-group-title"><?= htmlspecialchars($templateLabel, ENT_QUOTES, 'UTF-8') ?></h6>
                        <?php foreach ($fields as $name => $field): ?>
                          <div class="form-group mb-3">
                            <label for="ca-field-<?= htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $field['label'], ENT_QUOTES, 'UTF-8') ?></label>
                            <input
                              type="<?= ($field['type'] ?? '') === 'date' ? 'date' : 'text' ?>"
                              name="<?= htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8') ?>"
                              id="ca-field-<?= htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8') ?>"
                              class="form-control"
                              maxlength="255"
                            >
                            <small class="form-text text-muted"><?= htmlspecialchars((string) $field['context'], ENT_QUOTES, 'UTF-8') ?></small>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>

                <div class="mt-4">
                  <button type="submit" class="btn btn-success">
                    <i class="fas fa-download mr-1"></i>
                    Generate Package
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../dist/js/adminlte.min.js"></script>
<script>
$(function() {
  if ($.fn.select2) {
    $('#ca-location').select2({
      width: '100%',
      placeholder: 'Select municipality'
    });
  }
});
</script>
</body>
</html>
