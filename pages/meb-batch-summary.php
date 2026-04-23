<?php
include('../header.php');
include('../sidenav.php');

$batchId = trim((string) ($_GET['batch_id'] ?? ''));
$batchSummary = null;
$locationRows = [];

if ($batchId !== '') {
    $summaryStmt = $conn->prepare(
        "SELECT
            batch_id,
            COUNT(*) AS beneficiary_count,
            COUNT(DISTINCT province) AS province_count,
            COUNT(DISTINCT lgu) AS municipality_count,
            COUNT(DISTINCT barangay) AS barangay_count,
            GROUP_CONCAT(DISTINCT province ORDER BY province ASC SEPARATOR ', ') AS provinces,
            GROUP_CONCAT(DISTINCT lgu ORDER BY lgu ASC SEPARATOR ', ') AS municipalities
         FROM meb
         WHERE batch_id = ?
         GROUP BY batch_id
         LIMIT 1"
    );

    if ($summaryStmt) {
        $summaryStmt->bind_param('s', $batchId);
        $summaryStmt->execute();
        $batchSummary = db_stmt_fetch_one_assoc($summaryStmt) ?: null;
        $summaryStmt->close();
    }

    $locationsStmt = $conn->prepare(
        "SELECT
            province,
            lgu AS municipality,
            GROUP_CONCAT(DISTINCT barangay ORDER BY barangay ASC SEPARATOR ', ') AS barangays,
            COUNT(DISTINCT barangay) AS barangay_count,
            COUNT(*) AS beneficiary_count
         FROM meb
         WHERE batch_id = ?
         GROUP BY province, lgu
         ORDER BY province ASC, lgu ASC"
    );

    if ($locationsStmt) {
        $locationsStmt->bind_param('s', $batchId);
        $locationsStmt->execute();
        $locationRows = db_stmt_fetch_all_assoc($locationsStmt);
        $locationsStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | MEB Batch Summary</title>
  <style>
    .meb-batch-hero {
      border: 1px solid rgba(13, 110, 253, 0.12);
      border-radius: 1rem;
      background: linear-gradient(135deg, rgba(13, 110, 253, 0.08), rgba(32, 201, 151, 0.08));
      padding: 1rem 1.1rem;
      margin-bottom: 1rem;
    }

    .meb-batch-hero p {
      margin: 0.35rem 0 0;
      color: #6c757d;
    }

    body[data-theme="dark"] .meb-batch-hero p {
      color: #b8c7d9;
    }

    .meb-batch-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 0.9rem;
      margin-bottom: 1rem;
    }

    .meb-batch-stat {
      padding: 1rem;
      border-radius: 1rem;
      border: 1px solid rgba(148, 163, 184, 0.18);
      background: rgba(255, 255, 255, 0.88);
      box-shadow: 0 14px 28px rgba(15, 23, 42, 0.06);
    }

    .meb-batch-stat-label {
      display: block;
      margin-bottom: 0.35rem;
      color: #6c757d;
      font-size: 0.84rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .meb-batch-stat-value {
      display: block;
      font-size: 1.45rem;
      font-weight: 700;
      line-height: 1.2;
    }

    body[data-theme="dark"] .meb-batch-stat {
      background: rgba(17, 24, 39, 0.88);
      border-color: rgba(148, 163, 184, 0.14);
      box-shadow: 0 14px 28px rgba(0, 0, 0, 0.18);
    }

    body[data-theme="dark"] .meb-batch-stat-label {
      color: #b8c7d9;
    }

    .meb-batch-empty {
      padding: 2.5rem 1rem;
      text-align: center;
      color: #6c757d;
    }

    body[data-theme="dark"] .meb-batch-empty {
      color: #b8c7d9;
    }

    .meb-batch-table-wrap {
      overflow-x: auto;
    }
    @media (max-width: 1600px) {
      .meb-batch-hero {
        padding: 0.9rem 1rem;
      }
      .meb-batch-stat {
        padding: 0.9rem;
      }
    }
    @media (max-width: 1366px) {
      .meb-batch-grid {
        gap: 0.75rem;
      }
      .meb-batch-stat-value {
        font-size: 1.24rem;
      }
      .meb-batch-stat-label,
      .meb-batch-hero p {
        font-size: 0.8rem;
      }
    }
    @media (max-width: 1280px) {
      .meb-batch-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
      .meb-batch-hero h4 {
        font-size: 1rem;
      }
    }
    @media (max-width: 1024px) {
      .meb-batch-stat {
        padding: 0.8rem 0.85rem;
      }
    }

    @media (max-width: 991.98px) {
      .meb-batch-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 575.98px) {
      .meb-batch-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">MEB Batch Summary</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../home">Home</a></li>
              <li class="breadcrumb-item"><a href="data-tracking-meb">MEB</a></li>
              <li class="breadcrumb-item active">Batch Summary</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <?php if ($batchSummary === null): ?>
          <div class="card">
            <div class="card-body meb-batch-empty">
              <?= $batchId === '' ? 'No batch was selected.' : 'No MEB records were found for this batch.' ?>
            </div>
          </div>
        <?php else: ?>
          <div class="meb-batch-hero">
            <h4 class="m-0">Batch ID: <?= htmlspecialchars($batchId, ENT_QUOTES, 'UTF-8') ?></h4>
            <p>Review the imported province, city or municipality coverage, barangays, and beneficiary totals for this MEB batch.</p>
          </div>

          <div class="meb-batch-grid">
            <div class="meb-batch-stat">
              <span class="meb-batch-stat-label">Province</span>
              <span class="meb-batch-stat-value"><?= htmlspecialchars((string) ($batchSummary['provinces'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="meb-batch-stat">
              <span class="meb-batch-stat-label">City / Municipality</span>
              <span class="meb-batch-stat-value"><?= number_format((int) ($batchSummary['municipality_count'] ?? 0)) ?></span>
            </div>
            <div class="meb-batch-stat">
              <span class="meb-batch-stat-label">Barangays</span>
              <span class="meb-batch-stat-value"><?= number_format((int) ($batchSummary['barangay_count'] ?? 0)) ?></span>
            </div>
            <div class="meb-batch-stat">
              <span class="meb-batch-stat-label">Beneficiaries</span>
              <span class="meb-batch-stat-value"><?= number_format((int) ($batchSummary['beneficiary_count'] ?? 0)) ?></span>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <h4 class="m-0">Location Breakdown</h4>
            </div>
            <div class="card-body p-0">
              <div class="meb-batch-table-wrap table-container kodus-table-scroll" style="--kodus-table-min-width: 960px;">
                <table class="table table-bordered table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Province</th>
                      <th>City / Municipality</th>
                      <th>Barangays</th>
                      <th>Barangay Count</th>
                      <th>Beneficiaries</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($locationRows as $row): ?>
                      <tr>
                        <td><?= htmlspecialchars((string) ($row['province'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['barangays'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= number_format((int) ($row['barangay_count'] ?? 0)) ?></td>
                        <td><?= number_format((int) ($row['beneficiary_count'] ?? 0)) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../dist/js/adminlte.min.js"></script>
</body>
</html>
