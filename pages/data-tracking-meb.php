<?php
include('../header.php');
include('../sidenav.php');

$isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | MEB</title>
</head>
<body>
<div class="wrapper">


  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Masterlist of Eligible Beneficiaries</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../home">Home</a></li>
              <li class="breadcrumb-item active">MEB</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header d-flex align-items-center">
            <h4 class="m-0 flex-grow-1">Master list of Eligible Beneficiaries</h4>
            <?php if ($isAdmin): ?>
            <form action="import" method="POST" enctype="multipart/form-data" class="mb-0">
              <label for="excelFile" class="btn btn-info btn-sm" style="font-size: 10px; position: relative; top: 4px;">Choose Excel File:</label>
              <input type="file" name="excelFile" id="excelFile" accept=".xlsx, .xls" style="font-size: 10px; display: none;" onchange="displayFileName()">
              <span id="file-name"></span>
              <button type="submit" class="btn btn-success btn-sm" name="import" style="font-size: 10px; width: 60px;">Import</button>
            </form>&nbsp;
            <?php endif; ?>
            <button id="exportBtn" class="btn btn-info btn-sm" style="font-size: 10px; width: auto;">Export to Excel</button>
          </div>
          <div class="table-container">
            <?php if ($isAdmin): ?>
            <form id="bulkActionForm" action="bulk_action" method="POST">
            <?php endif; ?>
            <table id="table1" class="table table-bordered table-striped" style="text-align: center; width: 100%; table-layout: auto;">
              <thead>
                <tr>
                  <th rowspan="3">Action</th>
                  <?php if ($isAdmin): ?>
                  <th rowspan="3"></th>
                  <?php endif; ?>
                  <th style="width: 10px;" rowspan="3">NO.</th>
                  <th colspan="4" rowspan="2">NAME</th>
                  <th style="width: 10px;" rowspan="3">PUROK</th>
                  <th style="width: 10px;" rowspan="3">BARANGAY</th>
                  <th style="width: 10px;" rowspan="3">LGU</th>
                  <th style="width: 10px;" rowspan="3">PROVINCE</th>
                  <th style="width: 10px;" rowspan="3">BIRTHDATE</th>
                  <th style="width: 10px;" rowspan="3">AGE</th>
                  <th style="width: 10px;" rowspan="3">SEX</th>
                  <th style="width: 10px;" rowspan="3">CIVIL STATUS</th>
                  <th colspan="14">CLASSIFICATIONS</th>
                </tr>
                <tr class="narrow">
                  <th rowspan="2">National Household Targeting System for Poverty Reduction (NHTS-PR) Poor</th>
                  <th rowspan="2">National Household Targeting System for Poverty Reduction (NHTS-PR) Non-poor but considered poor by LSWDO assessment</th>
                  <th colspan="12">SECTORS</th>
                </tr>
                <tr>
                  <th style="white-space: nowrap; width: 10px;">LAST NAME</th>
                  <th style="white-space: nowrap; width: 10px;">FIRST NAME</th>
                  <th style="white-space: nowrap; width: 10px;">MIDDLE NAME</th>
                  <th style="white-space: nowrap; width: 10px;">EXT.</th>
                  <th style="width: 10px;">Pantawid Pamilyang Pilipino Program (4Ps)</th>
                  <th style="width: 10px;">Farmers (F)</th>
                  <th style="width: 10px;">Fisher-folks (FF)</th>
                  <th style="width: 10px;">Indigenous People (IP)</th>
                  <th style="width: 10px;">Senior Citizen (SC)</th>
                  <th style="width: 10px;">Solo Parent (SP)</th>
                  <th style="width: 10px;">Pregnant Women (PW)</th>
                  <th style="width: 10px;">Persons with Disability (PWD)</th>
                  <th style="width: 10px;">Out-of-School Youth (OSY)</th>
                  <th style="width: 10px;">Former Rebel (FR)</th>
                  <th style="width: 10px;">YAKAP Bayan/ Drug Surenderee (YB/DS)</th>
                  <th style="width: 10px;">LGBTQIA+</th>
                </tr>
              </thead>
              <tbody style="font-size: 10px; white-space: nowrap;"></tbody>
            </table>
            <?php if ($isAdmin): ?>
            <button class="btn btn-outline-info" type="submit" name="action" value="edit">Edit Selected</button>
            <button class="btn btn-outline-danger" type="submit" name="action" value="delete" id="deleteButton">Delete Selected</button>
            </form>
            <br>
            <form action="delete_batch" method="POST">
              <select class="btn btn-outline-info" name="batchId" id="batchId" required>
                <option value="" disabled selected>Select Batch ID</option>
              </select>
              <button class="btn btn-outline-danger" type="submit" name="deleteBatch">Delete Batch</button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/jszip/jszip.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?php echo $base_url; ?>kodus/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../dist/js/adminlte.min.js"></script>
<script>
  const isAdminView = <?php echo $isAdmin ? 'true' : 'false'; ?>;

  function displayFileName() {
    const fileInput = document.getElementById('excelFile');
    const fileName = fileInput && fileInput.files[0] ? fileInput.files[0].name : 'No file chosen';
    const fileNameNode = document.getElementById('file-name');
    if (fileNameNode) {
      fileNameNode.textContent = fileName;
    }
  }
</script>
<script>
  $(document).ready(function() {
      let selectedIds = [];

      function escapeHtml(value) {
          return $('<div>').text(value ?? '').html();
      }

      function formatFallback(value, fallback = 'Not set') {
          const text = String(value ?? '').trim();
          return text !== '' ? escapeHtml(text) : `<span class="kodus-detail-empty">${escapeHtml(fallback)}</span>`;
      }

      function isMarked(value) {
          const normalized = String(value ?? '').trim().toLowerCase();
          return ['✓', 'âœ“', 'yes', 'y', 'true', '1'].includes(normalized);
      }

      function renderClassificationBadge(label) {
          return `
              <div class="kodus-detail-stat">
                  <span class="kodus-detail-value kodus-detail-value--strong kodus-detail-value--positive">${escapeHtml(label)}</span>
              </div>
          `;
      }

      function getPwdCategoryLabel(value) {
          const code = String(value ?? '').trim().toUpperCase();
          const categories = {
              A: 'Multiple Disabilities',
              B: 'Intellectual Disability',
              C: 'Learning Disability',
              D: 'Mental Disability',
              E: 'Physical Disability (Orthopedic)',
              F: 'Psychosocial Disability',
              G: 'Non-apparent Visual Disability',
              H: 'Non-apparent Speech and Language Impairment',
              I: 'Non-apparent Cancer',
              J: 'Non-apparent Rare Disease',
              K: 'Deaf/Hard of Hearing Disability'
          };

          return categories[code] ? `PWD - ${categories[code]}` : '';
      }

      function renderMebDetails(rowData) {
          const lastName = String(rowData.lastName ?? '').trim();
          const givenNames = [
              rowData.firstName,
              rowData.middleName,
              rowData.ext
          ].filter(value => String(value ?? '').trim() !== '').join(' ');
          const fullName = [lastName, givenNames]
              .filter(value => value !== '')
              .join(lastName && givenNames ? ', ' : '');

          const classificationCards = [
              isMarked(rowData.nhts1) ? renderClassificationBadge('NHTS-PR Poor') : '',
              isMarked(rowData.nhts2) ? renderClassificationBadge('NHTS-PR Non-poor') : '',
              isMarked(rowData.fourPs) ? renderClassificationBadge('4Ps') : '',
              isMarked(rowData.F) ? renderClassificationBadge('Farmer') : '',
              isMarked(rowData.FF) ? renderClassificationBadge('Fisherfolk') : '',
              isMarked(rowData.IP) ? renderClassificationBadge('Indigenous Person') : '',
              isMarked(rowData.SC) ? renderClassificationBadge('Senior Citizen') : '',
              isMarked(rowData.SP) ? renderClassificationBadge('Solo Parent') : '',
              isMarked(rowData.PW) ? renderClassificationBadge('Pregnant Woman') : '',
              getPwdCategoryLabel(rowData.PWD) ? renderClassificationBadge(getPwdCategoryLabel(rowData.PWD)) : '',
              isMarked(rowData.OSY) ? renderClassificationBadge('Out-of-School Youth') : '',
              isMarked(rowData.FR) ? renderClassificationBadge('Former Rebel') : '',
              isMarked(rowData.ybDs) ? renderClassificationBadge('YAKAP Bayan / Drug Surrenderee') : '',
              isMarked(rowData.lgbtqia) ? renderClassificationBadge('LGBTQIA+') : ''
          ].filter(Boolean).join('');

          return `
              <div class="kodus-detail-modal">
                  <div class="kodus-detail-hero">
                      <div>
                          <span class="kodus-detail-eyebrow">Eligible Beneficiary</span>
                          <h3 class="kodus-detail-title">${formatFallback(fullName, 'No recorded name')}</h3>
                          <p class="kodus-detail-subtitle">${formatFallback(rowData.barangay, 'No barangay')}, ${formatFallback(rowData.lgu, 'No municipality')}, ${formatFallback(rowData.province, 'No province')}</p>
                      </div>
                      <div class="kodus-detail-pill">Record #${escapeHtml(rowData.id ?? 'N/A')}</div>
                  </div>

                  <div class="kodus-detail-grid">
                      <div class="kodus-detail-stat">
                          <span class="kodus-detail-label">Age</span>
                          <span class="kodus-detail-value kodus-detail-value--strong">${formatFallback(rowData.age)}</span>
                      </div>
                      <div class="kodus-detail-stat">
                          <span class="kodus-detail-label">Sex</span>
                          <span class="kodus-detail-value kodus-detail-value--strong">${formatFallback(rowData.sex)}</span>
                      </div>
                      <div class="kodus-detail-stat">
                          <span class="kodus-detail-label">Civil Status</span>
                          <span class="kodus-detail-value kodus-detail-value--strong">${formatFallback(rowData.civilStatus)}</span>
                      </div>
                      <div class="kodus-detail-stat">
                          <span class="kodus-detail-label">Birthdate</span>
                          <span class="kodus-detail-value kodus-detail-value--strong">${formatFallback(rowData.birthDate)}</span>
                      </div>
                  </div>

                  <div class="kodus-detail-section">
                      <h6 class="kodus-detail-section-title">Identity</h6>
                      <div class="kodus-detail-section-grid">
                          <div>
                              <span class="kodus-detail-label">Last Name</span>
                              <span class="kodus-detail-value">${formatFallback(rowData.lastName)}</span>
                          </div>
                          <div>
                              <span class="kodus-detail-label">First Name</span>
                              <span class="kodus-detail-value">${formatFallback(rowData.firstName)}</span>
                          </div>
                          <div>
                              <span class="kodus-detail-label">Middle Name</span>
                              <span class="kodus-detail-value">${formatFallback(rowData.middleName)}</span>
                          </div>
                          <div>
                              <span class="kodus-detail-label">Extension</span>
                              <span class="kodus-detail-value">${formatFallback(rowData.ext, 'None')}</span>
                          </div>
                      </div>
                  </div>

                  <div class="kodus-detail-section">
                      <h6 class="kodus-detail-section-title">Location</h6>
                      <div class="kodus-detail-section-grid">
                          <div>
                              <span class="kodus-detail-label">Purok</span>
                              <span class="kodus-detail-value">${formatFallback(rowData.purok)}</span>
                          </div>
                          <div>
                              <span class="kodus-detail-label">Barangay</span>
                              <span class="kodus-detail-value">${formatFallback(rowData.barangay)}</span>
                          </div>
                          <div>
                              <span class="kodus-detail-label">LGU</span>
                              <span class="kodus-detail-value">${formatFallback(rowData.lgu)}</span>
                          </div>
                          <div>
                              <span class="kodus-detail-label">Province</span>
                              <span class="kodus-detail-value">${formatFallback(rowData.province)}</span>
                          </div>
                      </div>
                  </div>

                  <div class="kodus-detail-section mb-0">
                      <h6 class="kodus-detail-section-title">Classifications</h6>
                      <div class="kodus-detail-grid">
                          ${classificationCards || '<span class="kodus-detail-empty">No classifications tagged</span>'}
                      </div>
                  </div>
              </div>
          `;
      }

      function renderMebCarousel(position, total) {
          const prevDisabled = position <= 1 ? 'disabled' : '';
          const nextDisabled = position >= total ? 'disabled' : '';

          return `
              <div class="d-flex justify-content-between align-items-center mb-3">
                  <button type="button" class="btn btn-outline-secondary btn-sm meb-carousel-btn" data-direction="prev" ${prevDisabled}>&larr;</button>
                  <span class="kodus-detail-label mb-0">Record ${position} of ${total}</span>
                  <button type="button" class="btn btn-outline-secondary btn-sm meb-carousel-btn" data-direction="next" ${nextDisabled}>&rarr;</button>
              </div>
          `;
      }

      function openMebDetailsModal(startId, searchValue) {
          let activeId = startId;
          let isLoading = false;
          let popupElement = null;

          const handleArrowNavigation = (event) => {
              if (!Swal.isVisible() || isLoading) {
                  return;
              }

              if (event.key === 'ArrowLeft') {
                  const prevButton = Swal.getHtmlContainer()?.querySelector('.meb-carousel-btn[data-direction="prev"]');
                  if (prevButton && !prevButton.disabled) {
                      event.preventDefault();
                      prevButton.click();
                  }
              }

              if (event.key === 'ArrowRight') {
                  const nextButton = Swal.getHtmlContainer()?.querySelector('.meb-carousel-btn[data-direction="next"]');
                  if (nextButton && !nextButton.disabled) {
                      event.preventDefault();
                      nextButton.click();
                  }
              }
          };

          const renderLoadingState = () => {
              const htmlContainer = Swal.getHtmlContainer();
              if (!htmlContainer) {
                  return;
              }

              htmlContainer.innerHTML = `
                  <div class="text-center py-4">
                      <div class="spinner-border text-info mb-3" role="status" aria-hidden="true"></div>
                      <div class="kodus-detail-label mb-0">Loading beneficiary details...</div>
                  </div>
              `;
          };

          const loadRecord = (direction = 'current') => {
              isLoading = true;
              renderLoadingState();

              $.getJSON('fetch_meb_detail.php', {
                  id: activeId,
                  direction: direction,
                  search: searchValue
              }).done(function(response) {
                  const htmlContainer = Swal.getHtmlContainer();

                  if (!response || !response.success || !response.row || !htmlContainer) {
                      isLoading = false;
                      if (htmlContainer) {
                          htmlContainer.innerHTML = '<span class="kodus-detail-empty">Unable to load beneficiary details.</span>';
                      }
                      return;
                  }

                  activeId = response.row.id;
                  isLoading = false;
                  htmlContainer.innerHTML = `
                      ${renderMebCarousel(Number(response.position || 1), Number(response.total || 1))}
                      ${renderMebDetails(response.row)}
                  `;
                  if (popupElement) {
                      popupElement.focus();
                  }

                  htmlContainer.querySelectorAll('.meb-carousel-btn').forEach((button) => {
                      button.addEventListener('click', function() {
                          const nextDirection = this.getAttribute('data-direction');
                          if (!this.disabled) {
                              loadRecord(nextDirection);
                          }
                      });
                  });
              }).fail(function() {
                  isLoading = false;
                  const htmlContainer = Swal.getHtmlContainer();
                  if (htmlContainer) {
                      htmlContainer.innerHTML = '<span class="kodus-detail-empty">Unable to load beneficiary details.</span>';
                  }
              });
          };

          Swal.fire({
              title: 'Partner-Beneficiary Details',
              customClass: {
                  popup: 'kodus-detail-popup'
              },
              width: 980,
              html: '',
              confirmButtonText: '<i class="fas fa-times"></i>',
              stopKeydownPropagation: false,
              didOpen: function() {
                  popupElement = Swal.getPopup();
                  if (popupElement) {
                      popupElement.setAttribute('tabindex', '0');
                      popupElement.addEventListener('keydown', handleArrowNavigation);
                      popupElement.focus();
                  }
                  loadRecord('current');
              },
              willClose: function() {
                  if (popupElement) {
                      popupElement.removeEventListener('keydown', handleArrowNavigation);
                  }
                  popupElement = null;
              }
          });
      }

      function updateSelectedIds() {
          selectedIds = Array.from($('input[name="selected[]"]:checked')).map((checkbox) => checkbox.value);
      }

      const columns = [];
      columns.push({
          data: null,
          orderable: false,
          searchable: false,
          render: function() {
              return '<span class="kodus-row-actions"><button type="button" class="btn btn-info btn-sm meb-details-btn" style="font-size:10px;" title="View details" aria-label="View details"><i class="nav-icon fas fa-eye"></i></button></span>';
          }
      });

      if (isAdminView) {
          columns.push({
              data: 'id',
              render: function(data) {
                  return `<input type="checkbox" name="selected[]" value="${data}" class="select-checkbox">`;
              }
          });
      }

      columns.push({
          data: null,
          render: function(data, type, row, meta) {
              return meta.row + 1;
          }
      });

      columns.push(
          { data: 'lastName' },
          { data: 'firstName' },
          { data: 'middleName' },
          { data: 'ext' },
          { data: 'purok' },
          { data: 'barangay' },
          { data: 'lgu' },
          { data: 'province' },
          { data: 'birthDate' },
          { data: 'age' },
          { data: 'sex' },
          { data: 'civilStatus' },
          { data: 'nhts1' },
          { data: 'nhts2' },
          { data: 'fourPs' },
          { data: 'F' },
          { data: 'FF' },
          { data: 'IP' },
          { data: 'SC' },
          { data: 'SP' },
          { data: 'PW' },
          { data: 'PWD' },
          { data: 'OSY' },
          { data: 'FR' },
          { data: 'ybDs' },
          { data: 'lgbtqia' }
      );

      const table = $("#table1").DataTable({
          processing: false,
          serverSide: true,
          ajax: {
              url: "fetch_data.php",
              type: "GET",
              dataSrc: function(json) {
                  if (isAdminView) {
                      setTimeout(() => {
                          selectedIds.forEach((id) => {
                              $(`input[name="selected[]"][value="${id}"]`).prop('checked', true);
                          });
                      }, 100);
                  }
                  return json.data;
              }
          },
          columns: columns,
          lengthChange: true,
          lengthMenu: [[10,25,50,100,200,300,-1], [10,25,50,100,200,300,"All"]],
          pageLength: 10,
          paging: true,
          responsive: false,
          rowCallback: function(row, data, index) {
              const counterColumnIndex = isAdminView ? 2 : 1;
              $('td:eq(' + counterColumnIndex + ')', row).html(index + 1 + table.page.info().start);
          }
      });

      $('#table1 tbody').on('click', '.meb-details-btn', function() {
          const rowData = table.row($(this).closest('tr')).data();

          if (!rowData || !rowData.id) {
              return;
          }

          openMebDetailsModal(rowData.id, table.search());
      });

      if (isAdminView) {
          $("#table1 tbody").on("change", 'input[name="selected[]"]', function() {
              updateSelectedIds();
          });

          setInterval(() => {
              table.ajax.reload(null, false);
          }, 5000);
      }
  });
</script>
<?php if ($isAdmin): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    function loadBatchOptions() {
        fetch('fetch_batches.php')
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById("batchId");
                if (!select) {
                    return;
                }

                select.innerHTML = '<option value="" disabled selected>-- Select Batch ID --</option>';

                if (data.length > 0) {
                    data.forEach((batchId) => {
                        const option = document.createElement("option");
                        option.value = batchId;
                        option.textContent = `Batch ID: ${batchId}`;
                        select.appendChild(option);
                    });
                } else {
                    const option = document.createElement("option");
                    option.value = "";
                    option.disabled = true;
                    option.textContent = "No batches found";
                    select.appendChild(option);
                }
            })
            .catch(error => console.error("Error fetching batch IDs:", error));
    }

    loadBatchOptions();

    const deleteBatchForm = document.querySelector("form[action='delete_batch']");
    if (!deleteBatchForm) {
        return;
    }

    deleteBatchForm.addEventListener("submit", function(event) {
      event.preventDefault();

      const formData = new FormData(this);

      fetch('delete_batch.php', {
          method: 'POST',
          body: formData
      })
      .then(response => response.text())
      .then(text => {
          try {
              return JSON.parse(text);
          } catch (error) {
              console.error("Invalid JSON response:", text);
              throw new Error("Invalid JSON response from server.");
          }
      })
      .then(result => {
          if (result.success) {
              Swal.fire({
                  icon: 'success',
                  title: 'Success',
                  text: 'Batch deleted successfully!',
              }).then(() => {
                  location.reload();
              });
          } else {
              Swal.fire({
                  icon: 'error',
                  title: 'Error',
                  text: result.error || "An unknown error occurred.",
              });
          }
      })
      .catch(error => {
          console.error("Error deleting batch:", error);
          Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'Something went wrong. Please try again.',
          });
      });
    });
});
</script>
<?php endif; ?>
<script>
  document.getElementById('exportBtn').addEventListener('click', function () {
      window.location.href = 'export_meb';
  });
</script>
</body>
</html>
