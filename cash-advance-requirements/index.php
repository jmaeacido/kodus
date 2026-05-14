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

    .ca-source-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 0.75rem;
    }

    .ca-source-option {
      position: relative;
      display: flex;
      gap: 0.75rem;
      align-items: flex-start;
      min-height: 5.25rem;
      padding: 0.95rem;
      border: 1px solid rgba(108, 117, 125, 0.22);
      border-radius: 0.5rem;
      cursor: pointer;
      background: rgba(255, 255, 255, 0.7);
    }

    body[data-theme="dark"] .ca-source-option {
      background: rgba(255, 255, 255, 0.03);
      border-color: rgba(255, 255, 255, 0.12);
    }

    .ca-source-option input {
      margin-top: 0.2rem;
    }

    .ca-source-title {
      display: block;
      font-weight: 700;
      line-height: 1.25;
    }

    .ca-source-description {
      display: block;
      margin-top: 0.18rem;
      color: #6c757d;
      font-size: 0.88rem;
      line-height: 1.35;
    }

    body[data-theme="dark"] .ca-source-description {
      color: #b8c7d9;
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

              <form action="generate.php" method="post" enctype="multipart/form-data" class="mt-4" id="cashAdvanceGenerateForm" data-loader-text="Generating cash advance requirement files...">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                  <label>Data Source</label>
                  <div class="ca-source-grid">
                    <label class="ca-source-option" for="ca-source-database">
                      <input type="radio" name="source" id="ca-source-database" value="database" checked>
                      <span>
                        <span class="ca-source-title">KODUS MEB Database</span>
                        <span class="ca-source-description">Use the current fiscal year records stored in KODUS.</span>
                      </span>
                    </label>
                    <label class="ca-source-option" for="ca-source-upload">
                      <input type="radio" name="source" id="ca-source-upload" value="upload">
                      <span>
                        <span class="ca-source-title">Uploaded MEB Workbook</span>
                        <span class="ca-source-description">Read an .xlsx or .xlsm MEB or beneficiary list from the request only.</span>
                      </span>
                    </label>
                  </div>
                </div>

                <div class="form-group" id="ca-location-group">
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

                <div class="form-group d-none" id="ca-upload-group">
                  <label for="ca-meb-file">MEB Workbook</label>
                  <input type="file" name="meb_file" id="ca-meb-file" class="form-control" accept=".xlsx,.xlsm">
                  <small class="form-text text-muted">Accepted columns for simple lists: Last Name, First Name, Middle Name, Ext., Barangay, Municipality, and Province. The workbook is processed from PHP temporary storage and is not saved inside the KODUS directory.</small>

                  <label for="ca-upload-beneficiary-amount" class="mt-3">Amount per Beneficiary</label>
                  <input
                    type="number"
                    name="upload_beneficiary_amount"
                    id="ca-upload-beneficiary-amount"
                    class="form-control"
                    min="0.01"
                    step="0.01"
                    inputmode="decimal"
                    placeholder="0.00"
                  >
                  <small class="form-text text-muted">Used for uploaded workbooks instead of the configured daily wage rate and working days.</small>

                  <div class="mt-3">
                    <label>Program Purpose</label>
                    <div class="custom-control custom-radio">
                      <input type="radio" name="upload_rrp_cftw" value="yes" id="ca-upload-rrp-yes" class="custom-control-input" checked>
                      <label class="custom-control-label" for="ca-upload-rrp-yes">For RRP-CFTW</label>
                    </div>
                    <div class="custom-control custom-radio">
                      <input type="radio" name="upload_rrp_cftw" value="no" id="ca-upload-rrp-no" class="custom-control-input">
                      <label class="custom-control-label" for="ca-upload-rrp-no">For another program or purpose</label>
                    </div>
                  </div>

                  <div class="mt-3">
                    <label>Include Time Tally Sheet</label>
                    <div class="custom-control custom-radio">
                      <input type="radio" name="upload_include_tts" value="yes" id="ca-upload-tts-yes" class="custom-control-input" checked>
                      <label class="custom-control-label" for="ca-upload-tts-yes">Yes</label>
                    </div>
                    <div class="custom-control custom-radio">
                      <input type="radio" name="upload_include_tts" value="no" id="ca-upload-tts-no" class="custom-control-input">
                      <label class="custom-control-label" for="ca-upload-tts-no">No</label>
                    </div>
                  </div>

                  <div id="ca-custom-purpose-group" class="d-none mt-3">
                    <div class="form-group">
                      <label for="ca-custom-particulars">Activity/Purpose/Particulars</label>
                      <textarea name="custom_particulars" id="ca-custom-particulars" class="form-control" rows="2">CA re: &lt;program / purpose&gt; in the Municipality of {municipality}, {province}</textarea>
                      <small class="form-text text-muted">Used in Request for CA, Obligation Request and Status, and Disbursement Voucher.</small>
                    </div>
                    <div class="form-group">
                      <label for="ca-custom-atp-statement">Authority to Pay Statement</label>
                      <textarea name="custom_atp_statement" id="ca-custom-atp-statement" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group" id="ca-custom-tts-group">
                      <label for="ca-custom-tts-certification">Time Tally Sheet Certification</label>
                      <textarea name="custom_tts_certification" id="ca-custom-tts-certification" class="form-control" rows="3"></textarea>
                    </div>
                  </div>
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

  function updateCashAdvanceSource() {
    var source = $('input[name="source"]:checked').val();
    var useUpload = source === 'upload';
    var useRrp = $('input[name="upload_rrp_cftw"]:checked').val() !== 'no';
    var includeTts = $('input[name="upload_include_tts"]:checked').val() !== 'no';
    $('#ca-location-group').toggleClass('d-none', useUpload);
    $('#ca-upload-group').toggleClass('d-none', !useUpload);
    $('#ca-custom-purpose-group').toggleClass('d-none', !useUpload || useRrp);
    $('#ca-custom-tts-group').toggleClass('d-none', !includeTts);
    $('#ca-location').prop('required', !useUpload).prop('disabled', useUpload);
    $('#ca-meb-file').prop('required', useUpload).prop('disabled', !useUpload);
    $('#ca-upload-beneficiary-amount').prop('required', useUpload).prop('disabled', !useUpload);
    $('#ca-custom-particulars').prop('required', useUpload && !useRrp).prop('disabled', !useUpload || useRrp);
    $('#ca-custom-atp-statement').prop('required', useUpload && !useRrp).prop('disabled', !useUpload || useRrp);
    $('#ca-custom-tts-certification').prop('required', useUpload && !useRrp && includeTts).prop('disabled', !useUpload || useRrp || !includeTts);
  }

  $('input[name="source"], input[name="upload_rrp_cftw"], input[name="upload_include_tts"]').on('change', updateCashAdvanceSource);
  updateCashAdvanceSource();

  $('#cashAdvanceGenerateForm').on('submit', function(event) {
    var form = this;
    if (!window.fetch || !window.FormData || !window.URL || !window.Blob) {
      return;
    }

    event.preventDefault();
    var submitButton = form.querySelector('button[type="submit"]');
    var originalHtml = submitButton ? submitButton.innerHTML : '';
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Generating...';
    }

    var loaderHeld = false;
    if (window.KodusPageLoader) {
      if (typeof window.KodusPageLoader.hold === 'function') {
        window.KodusPageLoader.hold(form.dataset.loaderText || 'Generating cash advance requirement files...');
        loaderHeld = true;
      } else {
        window.KodusPageLoader.show(form.dataset.loaderText || 'Generating cash advance requirement files...');
      }
    }

    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin'
    }).then(function(response) {
      if (!response.ok) {
        return response.text().then(function(text) {
          throw new Error(text || 'Unable to generate the package.');
        });
      }

      var disposition = response.headers.get('Content-Disposition') || '';
      var filename = 'Cash Advance Requirements.zip';
      var match = disposition.match(/filename="([^"]+)"/i) || disposition.match(/filename=([^;]+)/i);
      if (match && match[1]) {
        filename = match[1].trim();
      }

      return response.blob().then(function(blob) {
        return { blob: blob, filename: filename };
      });
    }).then(function(download) {
      var url = window.URL.createObjectURL(download.blob);
      var link = document.createElement('a');
      link.href = url;
      link.download = download.filename;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.setTimeout(function() {
        window.URL.revokeObjectURL(url);
      }, 1000);
    }).catch(function(error) {
      alert(error.message || 'Unable to generate the package.');
    }).finally(function() {
      if (window.KodusPageLoader) {
        if (loaderHeld && typeof window.KodusPageLoader.release === 'function') {
          window.KodusPageLoader.release();
        } else {
          window.KodusPageLoader.hide();
        }
      }
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.innerHTML = originalHtml;
      }
    });
  });
});
</script>
</body>
</html>
