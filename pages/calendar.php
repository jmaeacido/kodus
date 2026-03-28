<?php
session_start();
include ('../header.php');
include ('../sidenav.php');

$calendarGuestUsers = [];
$calendarUsersResult = $conn->query("SELECT username, email FROM users WHERE deleted_at IS NULL ORDER BY username ASC");
if ($calendarUsersResult instanceof mysqli_result) {
    while ($calendarUserRow = $calendarUsersResult->fetch_assoc()) {
        $email = trim((string) ($calendarUserRow['email'] ?? ''));
        $username = trim((string) ($calendarUserRow['username'] ?? ''));
        if ($email !== '' && $username !== '') {
            $calendarGuestUsers[] = [
                'username' => $username,
                'email' => $email,
            ];
        }
    }
    $calendarUsersResult->close();
}
?>

<!DOCTYPE html>
<html lang="en" translate="no">
<head>
  <meta charset="utf-8">
  <meta name="google" content="notranslate">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | Calendar</title>

  <!-- Fonts & Icons -->
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <!-- fullCalendar -->
  <link rel="stylesheet" href="../plugins/fullcalendar/main.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">
  <style>
    #delete-zone {
      cursor: pointer;
      border: 2px dashed #fff;
    }
    .calendar-tools .small-box {
      margin-bottom: 0.75rem;
    }
    .calendar-tools .form-control,
    .calendar-tools .btn,
    .calendar-tools .custom-control-label {
      font-size: 0.92rem;
    }
    .calendar-tools .filter-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 999px;
      padding: 0.25rem 0.65rem;
      margin: 0.25rem 0.35rem 0 0;
      cursor: pointer;
      background: rgba(255,255,255,0.04);
    }
    .calendar-tools .filter-chip.active {
      border-color: rgba(255,255,255,0.4);
      background: rgba(255,255,255,0.12);
    }
    .calendar-tools .filter-dot {
      width: 10px;
      height: 10px;
      border-radius: 999px;
      display: inline-block;
    }
    .calendar-tools .agenda-list {
      max-height: 360px;
      overflow-y: auto;
    }
    .calendar-tools .agenda-item {
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 0.6rem;
      padding: 0.75rem;
      margin-bottom: 0.6rem;
      cursor: pointer;
      background: rgba(255,255,255,0.03);
    }
    .calendar-tools .agenda-item:hover {
      background: rgba(255,255,255,0.06);
    }
    .calendar-tools .agenda-title {
      font-weight: 700;
      margin-bottom: 0.2rem;
    }
    .calendar-tools .agenda-meta {
      color: #ced4da;
      font-size: 0.82rem;
    }
    .calendar-tools .agenda-empty {
      color: #adb5bd;
      text-align: center;
      padding: 1rem 0.5rem;
    }
    .fc-theme-standard .fc-toolbar-title,
    .fc-theme-standard .fc-col-header-cell-cushion,
    .fc-theme-standard .fc-daygrid-day-number {
      color: #1f2937 !important;
    }

    .fc .fc-button-primary,
    .fc .fc-button-primary:disabled,
    .fc .fc-button-primary:not(:disabled):not(.fc-button-active),
    .fc .fc-button-primary:not(:disabled).fc-button-active,
    .fc .fc-button-primary:not(:disabled):active {
      color: #1f2937 !important;
      background: #ffffff !important;
      border-color: #cbd5e1 !important;
      box-shadow: none !important;
    }

    .fc .fc-button-primary:hover,
    .fc .fc-button-primary:focus {
      color: #0f172a !important;
      background: #f8fafc !important;
      border-color: #94a3b8 !important;
      box-shadow: none !important;
    }

    .fc .fc-button-primary.fc-button-active,
    .fc .fc-button-primary:not(:disabled):active {
      color: #ffffff !important;
      background: #2563eb !important;
      border-color: #2563eb !important;
    }

    .fc-theme-standard .fc-event,
    .fc-theme-standard .fc-event-title,
    .fc-theme-standard .fc-event-time {
      color: #111827 !important;
    }

    .dark-mode .fc-theme-standard .fc-toolbar-title,
    .dark-mode .fc-theme-standard .fc-col-header-cell-cushion,
    .dark-mode .fc-theme-standard .fc-daygrid-day-number,
    .dark-mode .fc-theme-standard .fc-event,
    .dark-mode .fc-theme-standard .fc-event-title,
    .dark-mode .fc-theme-standard .fc-event-time {
      color: #ffffff !important;
    }

    .dark-mode .fc .fc-button-primary,
    .dark-mode .fc .fc-button-primary:disabled,
    .dark-mode .fc .fc-button-primary:not(:disabled):not(.fc-button-active),
    .dark-mode .fc .fc-button-primary:not(:disabled).fc-button-active,
    .dark-mode .fc .fc-button-primary:not(:disabled):active {
      color: #ffffff !important;
      background: #343a40 !important;
      border-color: #4b5563 !important;
      box-shadow: none !important;
    }

    .dark-mode .fc .fc-button-primary:hover,
    .dark-mode .fc .fc-button-primary:focus {
      color: #ffffff !important;
      background: #3f4a56 !important;
      border-color: #6b7280 !important;
      box-shadow: none !important;
    }

    .dark-mode .fc .fc-button-primary.fc-button-active,
    .dark-mode .fc .fc-button-primary:not(:disabled):active {
      color: #ffffff !important;
      background: #2563eb !important;
      border-color: #2563eb !important;
    }
    .external-event {
      margin-bottom: 6px; padding: 6px; cursor: move;
    }
    .icon-btn {
      color: #adb5bd !important;
      opacity: 0.5;
      transition: opacity 0.15s ease-in-out;
    }
    .icon-btn:hover {
      opacity: 0.75;
    }
    .icon-btn:focus {
      box-shadow: none;
    }
    .calendar-modal-close {
      color: inherit;
      opacity: 0.78;
      text-shadow: none;
      transition: opacity 0.15s ease-in-out;
    }
    .calendar-modal-close:hover,
    .calendar-modal-close:focus {
      color: inherit;
      opacity: 1;
    }
    .modal-dialog {
      max-height: 90vh; /* Limit modal height */
      display: flex;
      flex-direction: column;
    }

    .modal-content {
      max-height: 90vh;
      display: flex;
      flex-direction: column;
    }

    .modal-body {
      overflow-y: auto;
      flex: 1; /* Take remaining space between header and footer */
    }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

  <!-- Preloader -->

  <!-- Content Wrapper -->
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1>Calendar</h1>
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Calendar</li>
        </ol>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <!-- Left -->
          <div class="col-md-3">
            <div class="sticky-top mb-3 calendar-tools">
              <div class="card">
                <div class="card-header"><h4 class="card-title">Calendar Tools</h4></div>
                <div class="card-body">
                  <div class="form-group">
                    <label for="calendarSearch">Search events</label>
                    <input type="text" id="calendarSearch" class="form-control" placeholder="Title, guest, location">
                  </div>
                  <div class="form-group">
                    <label for="jumpToDate">Jump to date</label>
                    <div class="input-group">
                      <input type="date" id="jumpToDate" class="form-control">
                      <div class="input-group-append">
                        <button type="button" class="btn btn-outline-primary" id="jumpToDateBtn">Go</button>
                      </div>
                    </div>
                  </div>
                  <div class="d-flex flex-wrap mb-2">
                    <button type="button" class="btn btn-sm btn-outline-light mr-2 mb-2" id="focusTodayBtn">Today</button>
                    <button type="button" class="btn btn-sm btn-outline-light mb-2" id="clearCalendarFiltersBtn">Clear Filters</button>
                  </div>
                  <div class="custom-control custom-switch mb-2">
                    <input type="checkbox" class="custom-control-input" id="toggleHolidays" checked>
                    <label class="custom-control-label" for="toggleHolidays">Show holidays</label>
                  </div>
                  <div class="custom-control custom-switch mb-2">
                    <input type="checkbox" class="custom-control-input" id="toggleAllDayEvents" checked>
                    <label class="custom-control-label" for="toggleAllDayEvents">Show all-day events</label>
                  </div>
                  <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="toggleTimedEvents" checked>
                    <label class="custom-control-label" for="toggleTimedEvents">Show timed events</label>
                  </div>
                  <hr>
                  <label class="mb-1">Colors</label>
                  <div id="calendarColorFilters"></div>
                </div>
              </div>

              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h4 class="card-title mb-0">Upcoming</h4>
                  <small class="text-muted" id="agendaCountLabel">0 events</small>
                </div>
                <div class="card-body">
                  <div id="upcomingAgenda" class="agenda-list">
                    <div class="agenda-empty">Loading upcoming events...</div>
                  </div>
                </div>
              </div>

              <div class="card">
                <div class="card-header"><h4 class="card-title">Draggable Events</h4></div>
                <div class="card-body">
                  <div id="external-events">
                    <?php
                      include('../config.php');
                      $userId = $_SESSION['user_id'] ?? 0;
                      $res = $conn->prepare("SELECT id, title, color FROM draggable_events WHERE created_by=? ORDER BY id DESC");
                      $res->bind_param("i", $userId);
                      $res->execute();
                      $result = $res->get_result();
                      while ($row = $result->fetch_assoc()):
                    ?>
                      <div class="external-event" 
                        data-id="<?= (int)$row['id']; ?>" 
                        data-title="<?= htmlspecialchars($row['title'], ENT_QUOTES); ?>"
                        data-color="<?= htmlspecialchars($row['color'], ENT_QUOTES); ?>"
                        style="background-color: <?= htmlspecialchars($row['color']); ?>; border-color: <?= htmlspecialchars($row['color']); ?>; color:#fff; position: relative;">
                        <?= htmlspecialchars($row['title']); ?>
                        <span class="remove-draggable" 
                          style="position: absolute; right: 4px; top: 2px; cursor: pointer; color: #fff;">&times;</span>
                      </div>
                    <?php endwhile; ?>
                  </div>
                  <div class="form-check mt-2">
                    <input type="checkbox" id="drop-remove" class="form-check-input">
                    <label for="drop-remove" class="form-check-label">remove after drop</label>
                  </div>
                </div>
              </div>

              <!-- Create Event -->
              <div class="card">
                <div class="card-header"><h3 class="card-title">Create Event</h3></div>
                <div class="card-body">
                  <div class="btn-group mb-2" style="width:100%;">
                    <ul class="fc-color-picker" id="color-chooser">
                      <li><a class="text-primary" href="#"><i class="fas fa-square"></i></a></li>
                      <li><a class="text-warning" href="#"><i class="fas fa-square"></i></a></li>
                      <li><a class="text-success" href="#"><i class="fas fa-square"></i></a></li>
                      <li><a class="text-danger" href="#"><i class="fas fa-square"></i></a></li>
                      <li><a class="text-muted" href="#"><i class="fas fa-square"></i></a></li>
                    </ul>
                  </div>
                  <div class="input-group">
                    <input id="new-event" type="text" class="form-control" placeholder="Event Title">
                    <div class="input-group-append">
                      <button id="add-new-event" type="button" class="btn btn-primary">Add</button>
                    </div>
                  </div>
                  <div id="delete-zone" class="mt-3 p-2 bg-danger text-center rounded">Drag to Delete</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right -->
          <div class="col-md-9">
            <div class="card card-primary">
              <div class="card-body p-0">
                <div id="calendar"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <!-- View Event Modal (read-only) -->
  <div class="modal fade" id="viewEventModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="viewEventTitle">Event Details</h5>
          <div class="ml-auto d-flex align-items-center">
            <button type="button" id="viewDuplicateBtn" class="btn p-0 text-secondary icon-btn mr-3" title="Duplicate">
              <i class="fas fa-copy"></i>
            </button>
            <button type="button" id="viewEditBtn" class="btn p-0 text-secondary icon-btn" title="Edit">
              <i class="fas fa-pen"></i>
            </button>
            <button type="button" id="viewDeleteBtn" class="btn p-0 text-secondary ml-3 icon-btn" title="Delete">
              <i class="fas fa-trash"></i>
            </button>
            <button type="button" class="close text-reset ml-2 calendar-modal-close" data-bs-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        </div>
        <div class="modal-body">
          <p><strong>Description:</strong><br> <span id="viewEventDesc"></span></p>
          <p><strong>Guests:</strong><br> <span id="viewEventGuests"></span></p>
          <p><strong>Location:</strong><br> <span id="viewEventLocation"></span></p>
          <p><strong>Privacy:</strong><br> <span id="viewEventPrivacy"></span></p>
          <p><strong>Start:</strong><br> <span id="viewEventStart"></span></p>
          <p><strong>End:</strong><br> <span id="viewEventEnd"></span></p>
        </div>
      </div>
    </div>
  </div>

  <!-- Event Modal -->
  <div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog">
      <form id="eventForm" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Event</h5>
          <button type="button" class="close text-reset calendar-modal-close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="eventId" name="id">
          <div class="form-group">
            <label>Title</label>
            <input type="text" id="eventTitle" name="title" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea id="eventDescription" name="description" class="form-control" required></textarea>
          </div>
          <div class="form-group">
            <input type="checkbox" id="eventDuration" name="all_day" value="1">
            <label>All day</label>
          </div>
          <!-- All Day inputs -->
          <div class="row form-group d-none" id="allDayFields">
            <div class="col-md-6">
              <label>Start</label>
              <input type="date" id="eventStartDate" name="start" class="form-control">
            </div>
            <div class="col-md-6">
              <label>End</label>
              <input type="date" id="eventEndDate" name="end" class="form-control">
            </div>
          </div>
          <!-- Timed schedule inputs -->
          <div class="form-group" id="timeFields">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <label class="mb-0">Daily Schedule</label>
              <button type="button" class="btn btn-sm btn-outline-primary" id="addTimedScheduleRow">
                <i class="fas fa-plus mr-1"></i>Add Day
              </button>
            </div>
            <div id="timedScheduleRows"></div>
            <small class="text-muted">Add one row per date with its own start and end time.</small>
          </div>
            <div class="mb-3">
              <label class="form-label">Guests</label>
              <div class="d-flex flex-wrap mb-2" style="gap:0.5rem;">
                <button type="button" class="btn btn-sm btn-outline-primary" id="eventGuestsSelectAll">Select All</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="eventGuestsClear">Clear</button>
              </div>
              <select id="eventGuests" class="form-control" name="guests" multiple size="8">
                <?php foreach ($calendarGuestUsers as $calendarGuestUser): ?>
                  <option value="<?= htmlspecialchars($calendarGuestUser['email'], ENT_QUOTES) ?>">
                    <?= htmlspecialchars($calendarGuestUser['username'] . ' (' . $calendarGuestUser['email'] . ')', ENT_QUOTES) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted d-block mb-2">Use Ctrl or Cmd to select multiple users, or use Select All.</small>
              <label class="form-label mt-2">Additional emails</label>
              <input type="text" id="eventGuestExtras" class="form-control" placeholder="extra1@example.com, extra2@example.com">
              <small class="text-muted">Add emails here if they are not in the user list.</small>
            </div>

            <div class="mb-3">
              <label class="form-label">Location</label>
              <input type="text" class="form-control" id="eventLocation" name="location">
            </div>
            <div class="mb-3">
              <label class="form-label d-block">Privacy</label>
              <div class="custom-control custom-radio">
                <input type="radio" id="eventPrivacyPublic" class="custom-control-input" name="is_private" value="0" checked>
                <label class="custom-control-label" for="eventPrivacyPublic">Public</label>
              </div>
              <div class="custom-control custom-radio">
                <input type="radio" id="eventPrivacyPrivate" class="custom-control-input" name="is_private" value="1">
                <label class="custom-control-label" for="eventPrivacyPrivate">Private</label>
              </div>
              <small class="text-muted">Private events are visible only to you.</small>
            </div>
          <div class="form-group">
            <label>Color</label>
            <div id="eventColorChooser" class="d-flex gap-2 flex-wrap">
              <span class="color-choice" data-color="#007bff" style="background:#007bff;width:24px;height:24px;border-radius:4px;cursor:pointer;display:inline-block;"></span>
              <span class="color-choice" data-color="#ffc107" style="background:#ffc107;width:24px;height:24px;border-radius:4px;cursor:pointer;display:inline-block;"></span>
              <span class="color-choice" data-color="#28a745" style="background:#28a745;width:24px;height:24px;border-radius:4px;cursor:pointer;display:inline-block;"></span>
              <span class="color-choice" data-color="#dc3545" style="background:#dc3545;width:24px;height:24px;border-radius:4px;cursor:pointer;display:inline-block;"></span>
              <span class="color-choice" data-color="#6c757d" style="background:#6c757d;width:24px;height:24px;border-radius:4px;cursor:pointer;display:inline-block;"></span>
            </div>
            <input type="hidden" id="eventColor" name="color" value="#3788d8">
          </div>
          <small>
            <div id="createdByWrapper" style="display:none;">
              <i><label class="createdByLabel">Created by:</label></i>
            </div>
            <div class="mb-1" id="updatedByWrapper" style="display:none;">
              <i><label class="updatedByLabel">Updated by:</label></i>
            </div>
          </small>
        </div>
        <div class="modal-footer">
          <button type="button" id="deleteEvent" class="btn btn-danger">Delete</button>
          <button type="submit" class="btn btn-success">Save</button>
        </div>
      </form>
    </div>
  </div>

</div>

<!-- JS -->
<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../plugins/jquery-ui/jquery-ui.min.js"></script>
<script src="../plugins/fullcalendar/main.js"></script>
<script src="<?php echo isset($base_url) ? $base_url.'kodus/dist/js/adminlte.min.js' : '../dist/js/adminlte.min.js'; ?>"></script>

<script>
$(function () {
  const currentUsername = <?= json_encode($_SESSION['username'] ?? '') ?>;
  const calendarGuestDirectory = <?= json_encode($calendarGuestUsers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const calendarGuestMap = new Map(calendarGuestDirectory.map(user => [String(user.email).toLowerCase(), user.username]));
  let calendarSearchTerm = '';
  let selectedColors = [];
  let showHolidays = true;
  let showAllDayEvents = true;
  let showTimedEvents = true;
  let knownEventColors = [];
  let isApplyingCalendarFilters = false;

  function rgbToHex(color) {
    if (!color) return '#3788d8';
    if (color.startsWith('#')) return color;
    let m = color.match(/\d+/g);
    if (!m) return '#3788d8';
    return "#" + m.slice(0,3).map(x => parseInt(x,10).toString(16).padStart(2,'0')).join('');
  }

  function formatDateLocal(date) {
    if (!date) return '';
    let d = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));
    return d.toISOString().slice(0,16);
  }

  function parseGuestEmails(rawGuests) {
    if (!rawGuests) return [];
    return String(rawGuests)
      .split(/[\s,;]+/)
      .map(value => value.trim().toLowerCase())
      .filter(Boolean);
  }

  function setGuestSelection(rawGuests) {
    const selectedEmails = new Set(parseGuestEmails(rawGuests));
    const customEmails = [];
    $('#eventGuests option').each(function() {
      const optionEmail = String(this.value).toLowerCase();
      const isSelected = selectedEmails.has(optionEmail);
      $(this).prop('selected', isSelected);
      if (isSelected) {
        selectedEmails.delete(optionEmail);
      }
    });
    customEmails.push(...selectedEmails);
    $('#eventGuestExtras').val(customEmails.join(', '));
  }

  function collectGuestEmails() {
    const selectedEmails = ($('#eventGuests').val() || []).map(value => String(value).trim().toLowerCase());
    const extraEmails = parseGuestEmails($('#eventGuestExtras').val() || '');
    return Array.from(new Set(selectedEmails.concat(extraEmails)));
  }

  function formatGuestLabels(rawGuests) {
    const emails = parseGuestEmails(rawGuests);
    if (!emails.length) return '-';

    return emails.map(function(email) {
      const username = calendarGuestMap.get(email);
      return username ? `${username} (${email})` : email;
    }).join('<br>');
  }

  // === Prefill defaults when modal opens ===
  $('#eventModal').on('show.bs.modal', function (e) {
    let trigger = $(e.relatedTarget);
    let clickedDate = trigger.data('date');
    let eventData   = trigger.data('event');

    if (eventData) {
      // Existing event
      $('#eventId').val(getParentEventId(eventData));
      $('#eventTitle').val(eventData.title);
      $('#eventDescription').val(eventData.extendedProps?.description || '');
      setGuestSelection(eventData.extendedProps?.guests || '');
      $('#eventLocation').val(eventData.extendedProps?.location || '');
      $('input[name="is_private"][value="' + (eventData.extendedProps?.isPrivate ? '1' : '0') + '"]').prop('checked', true);
      $('#eventColor').val(eventData.backgroundColor);

      // highlight chosen color
      $('#eventColorChooser .color-choice').css('border','none');
      $(`#eventColorChooser .color-choice[data-color="${rgbToHex(eventData.backgroundColor)}"]`).css('border','3px solid #000');

      if (eventData.allDay) {
        $('#eventDuration').prop('checked', true).trigger('change');
        $('#eventStartDate').val(eventData.startStr.substring(0,10));
        $('#eventEndDate').val(getInclusiveAllDayEndValue(eventData));
      } else {
        $('#eventDuration').prop('checked', false).trigger('change');
        renderTimedScheduleRows(deriveSchedulesFromEvent(eventData));
      }

    } else if (clickedDate) {
      // New event
      let dateStr = clickedDate.substring(0,10);
      $('#eventForm')[0].reset();
      $('#eventId').val('');
      $('#eventTitle').val('');
      $('#eventDescription').val('');
      setGuestSelection('');
      $('#eventLocation').val('');
      $('input[name="is_private"][value="0"]').prop('checked', true);
      $('#eventColor').val('#3788d8');

      // highlight default color
      $('#eventColorChooser .color-choice').css('border','none');
      $('#eventColorChooser .color-choice[data-color="#3788d8"]').css('border','3px solid #000');

      $('#eventDuration').prop('checked', true).trigger('change');
      $('#eventStartDate').val(dateStr);
      $('#eventEndDate').val(dateStr);
      renderTimedScheduleRows([{ date: dateStr, start_time: '08:00', end_time: '17:00' }]);

      // focus title input
      setTimeout(()=>$('#eventTitle').trigger('focus'), 300);
    }
  });

  function makeLinksClickable(text) {
    if (!text) return '-';
    // Escape HTML to prevent XSS
    let escaped = $('<div>').text(text).html();
    // Convert URLs into clickable links
    return escaped.replace(
      /(https?:\/\/[^\s]+)/g,
      '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
    ).replace(/\n/g, '<br>'); // keep line breaks
  }

  function formatDateInputValue(date) {
    if (!date) return '';

    return (
      date.getFullYear() + '-' +
      String(date.getMonth() + 1).padStart(2, '0') + '-' +
      String(date.getDate()).padStart(2, '0')
    );
  }

  function getInclusiveAllDayEndValue(event) {
    if (!event || !event.start) return '';
    if (!event.end) return event.startStr.substring(0, 10);

    const endDate = new Date(event.end);
    endDate.setDate(endDate.getDate() - 1);
    return formatDateInputValue(endDate);
  }

  function formatAllDayDisplay(date) {
    if (!date) return '';

    return date.toLocaleDateString([], {
      month: 'short',
      day: 'numeric',
      weekday: 'short',
      year: 'numeric'
    });
  }

  function getParentEventId(event) {
    if (!event) return '';
    return event.extendedProps && event.extendedProps.parentEventId
      ? String(event.extendedProps.parentEventId)
      : String(event.id || '');
  }

  function buildTimedScheduleRow(schedule) {
    const normalized = schedule || {};
    const row = $(`
      <div class="border rounded p-2 mb-2 timed-schedule-row">
        <div class="form-row align-items-end">
          <div class="col-md-4">
            <label class="small text-muted">Date</label>
            <input type="date" class="form-control timed-schedule-date">
          </div>
          <div class="col-md-3">
            <label class="small text-muted">Start</label>
            <input type="time" class="form-control timed-schedule-start">
          </div>
          <div class="col-md-3">
            <label class="small text-muted">End</label>
            <input type="time" class="form-control timed-schedule-end">
          </div>
          <div class="col-md-2">
            <button type="button" class="btn btn-outline-danger btn-block remove-timed-schedule">Remove</button>
          </div>
        </div>
      </div>
    `);

    row.attr('data-schedule-id', normalized.id || '');
    row.find('.timed-schedule-date').val(normalized.date || '');
    row.find('.timed-schedule-start').val((normalized.start_time || normalized.startTime || '').substring(0, 5));
    row.find('.timed-schedule-end').val((normalized.end_time || normalized.endTime || '').substring(0, 5));

    return row;
  }

  function renderTimedScheduleRows(schedules) {
    const rows = Array.isArray(schedules) && schedules.length ? schedules : [{}];
    const container = $('#timedScheduleRows');
    container.empty();

    rows.forEach(function(schedule) {
      container.append(buildTimedScheduleRow(schedule));
    });
  }

  function addTimedScheduleRow(schedule) {
    $('#timedScheduleRows').append(buildTimedScheduleRow(schedule));
  }

  function deriveSchedulesFromEvent(event) {
    if (event && event.extendedProps && Array.isArray(event.extendedProps.dailySchedules) && event.extendedProps.dailySchedules.length) {
      return event.extendedProps.dailySchedules.map(function(schedule) {
        return {
          id: schedule.id || '',
          date: schedule.date || '',
          start_time: (schedule.start_time || '').substring(0, 5),
          end_time: (schedule.end_time || '').substring(0, 5)
        };
      });
    }

    if (!event || !event.start) {
      return [{}];
    }

    return [{
      id: '',
      date: formatDateInputValue(new Date(event.start)),
      start_time: new Date(event.start).toTimeString().slice(0, 5),
      end_time: event.end ? new Date(event.end).toTimeString().slice(0, 5) : ''
    }];
  }

  function buildSchedulesFromDateRange(startDate, endDate) {
    if (!startDate) return [{}];

    const schedules = [];
    const current = new Date(startDate + 'T00:00:00');
    const last = new Date((endDate || startDate) + 'T00:00:00');

    while (current <= last) {
      schedules.push({
        date: formatDateInputValue(current),
        start_time: '08:00',
        end_time: '17:00'
      });
      current.setDate(current.getDate() + 1);
    }

    return schedules;
  }

  function collectTimedSchedules() {
    const schedules = [];
    let hasErrors = false;

    $('#timedScheduleRows .timed-schedule-row').each(function() {
      const row = $(this);
      const scheduleId = row.attr('data-schedule-id') || '';
      const date = row.find('.timed-schedule-date').val();
      const startTime = row.find('.timed-schedule-start').val();
      const endTime = row.find('.timed-schedule-end').val();

      if (!date && !startTime && !endTime) {
        return;
      }

      if (!date || !startTime || !endTime) {
        hasErrors = true;
        return false;
      }

      if ((date + 'T' + startTime) >= (date + 'T' + endTime)) {
        hasErrors = true;
        return false;
      }

      schedules.push({
        id: scheduleId,
        date: date,
        start_time: startTime,
        end_time: endTime
      });
    });

    if (hasErrors || !schedules.length) {
      return null;
    }

    schedules.sort(function(left, right) {
      const leftKey = left.date + 'T' + left.start_time;
      const rightKey = right.date + 'T' + right.start_time;
      return leftKey.localeCompare(rightKey);
    });

    return schedules;
  }

  function openEventDetails(ev) {
    if (!ev) return;

    $('#viewEventTitle').text(ev.title || 'Untitled Event');
    $('#viewEventDesc').html(makeLinksClickable(ev.extendedProps.description));
    $('#viewEventGuests').html(formatGuestLabels(ev.extendedProps.guests));
    $('#viewEventLocation').html(makeLinksClickable(ev.extendedProps.location));
    $('#viewEventPrivacy').text(ev.extendedProps.isPrivate ? 'Private' : 'Public');

    const startStr = ev.start
      ? (ev.allDay ? formatAllDayDisplay(new Date(ev.start)) : new Date(ev.start).toLocaleString())
      : '';
    let endStr = '';

    if (ev.end) {
      if (ev.allDay) {
        const inclusiveEnd = new Date(ev.end);
        inclusiveEnd.setDate(inclusiveEnd.getDate() - 1);
        endStr = formatAllDayDisplay(inclusiveEnd);
      } else {
        endStr = new Date(ev.end).toLocaleString();
      }
    }
    $('#viewEventStart').text(startStr);
    $('#viewEventEnd').text(endStr);

    ['Guests', 'Location'].forEach(field => {
      const value = ev.extendedProps[field.toLowerCase()] || '';
      const element = $('#viewEvent' + field);

      if (!value.trim()) {
        element.closest('p').hide();
      } else {
        element.closest('p').show();
      }
    });

    if (startStr === endStr || !endStr.trim()) {
      $('#viewEventEnd').closest('p').hide();
    } else {
      $('#viewEventEnd').closest('p').show();
    }

    $('#viewEditBtn').data('event', ev);
    $('#viewDeleteBtn').data('id', getParentEventId(ev));
    $('#viewDuplicateBtn').data('event', ev);
    $('#viewEventModal').modal('show');
  }

  function formatAgendaDate(event) {
    const start = event.start;
    if (!start) return 'No date';

    if (event.allDay) {
      return start.toLocaleDateString([], { month: 'short', day: 'numeric', weekday: 'short' });
    }

    return start.toLocaleString([], {
      month: 'short',
      day: 'numeric',
      weekday: 'short',
      hour: 'numeric',
      minute: '2-digit'
    });
  }

  function isHolidayEvent(event) {
    return !event.id && (event.backgroundColor === '#ff9800' || event.title?.toLowerCase().includes('holiday'));
  }

  function eventMatchesFilters(event) {
    const haystack = [
      event.title || '',
      event.extendedProps?.description || '',
      event.extendedProps?.guests || '',
      event.extendedProps?.location || '',
      event.extendedProps?.createdBy || ''
    ].join(' ').toLowerCase();

    const searchOk = !calendarSearchTerm || haystack.includes(calendarSearchTerm);
    const color = rgbToHex(event.backgroundColor || event.borderColor || '#3788d8');
    const colorOk = selectedColors.length === 0 || selectedColors.includes(color);
    const holidayOk = showHolidays || !isHolidayEvent(event);
    const allDayOk = showAllDayEvents || !event.allDay;
    const timedOk = showTimedEvents || event.allDay;

    return searchOk && colorOk && holidayOk && allDayOk && timedOk;
  }

  function applyCalendarFilters() {
    if (isApplyingCalendarFilters) {
      return;
    }

    isApplyingCalendarFilters = true;
    calendar.getEvents().forEach(function(event) {
      const nextDisplay = eventMatchesFilters(event) ? 'auto' : 'none';
      if (event.display !== nextDisplay) {
        event.setProp('display', nextDisplay);
      }
    });
    isApplyingCalendarFilters = false;
    renderUpcomingAgenda();
  }

  function renderColorFilters(events) {
    const colors = [...new Set(events
      .filter(event => event.id)
      .map(event => rgbToHex(event.backgroundColor || event.borderColor || '#3788d8'))
    )];

    knownEventColors = colors;
    const container = $('#calendarColorFilters');
    container.empty();

    if (colors.length === 0) {
      container.html('<div class="text-muted small">No event colors available.</div>');
      return;
    }

    colors.forEach(function(color) {
      const isActive = selectedColors.length === 0 || selectedColors.includes(color);
      container.append(`
        <span class="filter-chip ${isActive ? 'active' : ''}" data-color="${color}">
          <span class="filter-dot" style="background:${color}"></span>
          <span>${color}</span>
        </span>
      `);
    });
  }

  function renderUpcomingAgenda() {
    const agenda = $('#upcomingAgenda');
    const now = new Date();
    const events = calendar.getEvents()
      .filter(event => event.start && eventMatchesFilters(event))
      .sort((a, b) => a.start - b.start)
      .filter(event => event.end ? event.end >= now : event.start >= new Date(now.getFullYear(), now.getMonth(), now.getDate()))
      .slice(0, 8);

    $('#agendaCountLabel').text(events.length + (events.length === 1 ? ' event' : ' events'));

    if (events.length === 0) {
      agenda.html('<div class="agenda-empty">No upcoming events match the current filters.</div>');
      return;
    }

    agenda.html(events.map(function(event) {
      const color = rgbToHex(event.backgroundColor || event.borderColor || '#3788d8');
      const meta = [];
      meta.push(formatAgendaDate(event));
      if (event.extendedProps?.location) meta.push(event.extendedProps.location);
      if (event.extendedProps?.guests) meta.push(event.extendedProps.guests);

      return `
        <div class="agenda-item" data-event-id="${event.id || ''}" data-start="${event.start ? event.start.toISOString() : ''}">
          <div class="agenda-title">
            <span class="filter-dot" style="background:${color}; margin-right:0.35rem;"></span>
            ${$('<div>').text(event.title || 'Untitled Event').html()}
          </div>
          <div class="agenda-meta">${$('<div>').text(meta.join(' | ')).html()}</div>
        </div>
      `;
    }).join(''));
  }

  // === Calendar setup ===
  let calendarEl = document.getElementById('calendar');
  let deleteZone = $('#delete-zone');

  let calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay'
    },
    editable: true,
    selectable: true,
    droppable: true,
    events: 'fetch_events.php',
    nowIndicator: true,
    dayMaxEventRows: true,
    moreLinkClick: 'popover',
    eventTimeFormat: {
      hour: 'numeric',
      minute: '2-digit',
      meridiem: 'short'
    },
    eventsSet: function(events) {
      if (isApplyingCalendarFilters) {
        return;
      }
      renderColorFilters(events);
      applyCalendarFilters();
    },

    dateClick: function(info) {
      $('#eventForm')[0].reset();
      $('#eventId').val('');
  
      // Reset meta labels
      $('#createdByWrapper').hide();
      $('#updatedByWrapper').hide();
      $('#eventModal').find('label.createdByLabel').text("Created by:");
      $('#eventModal').find('label.updatedByLabel').text("Updated by:");

      $('#eventDuration').prop('checked', false).trigger('change');
      let clicked = new Date(info.date);  
      let yyyy = clicked.getFullYear();
      let mm = String(clicked.getMonth() + 1).padStart(2, '0');
      let dd = String(clicked.getDate()).padStart(2, '0');
      let dateStr = `${yyyy}-${mm}-${dd}`;

      renderTimedScheduleRows([{ date: dateStr, start_time: '08:00', end_time: '17:00' }]);
      $('#eventModal').modal('show');
    },

    eventClick: function(info) {
      let ev = info.event;
      openEventDetails(ev);

      // Reset first
      $('#createdByWrapper').hide();
      $('#updatedByWrapper').hide();
      $('#eventModal').find('label.createdByLabel').text("Created by:");
      $('#eventModal').find('label.updatedByLabel').text("Updated by:");

      $('#eventId').val(getParentEventId(ev));
      $('#eventTitle').val(ev.title || '');
      $('#eventDescription').val(ev.extendedProps.description || '');
      setGuestSelection(ev.extendedProps.guests || '');
      $('#eventLocation').val(ev.extendedProps.location || '');

      // Created by
      if (ev.extendedProps.createdBy && ev.extendedProps.createdBy.trim() !== "") {
        $('#createdByWrapper').show();
        $('#eventModal').find('label.createdByLabel').text("Created by: " + ev.extendedProps.createdBy);
      } else {
        $('#createdByWrapper').hide();
      }

      // Updated by
      if (ev.extendedProps.updatedBy && ev.extendedProps.updatedBy.trim() !== "") {
        $('#updatedByWrapper').show();
        $('#eventModal').find('label.updatedByLabel').text("Updated by: " + ev.extendedProps.updatedBy);
      } else {
        $('#updatedByWrapper').hide();
      }

      let color = ev.extendedProps.color || ev.backgroundColor || '#3788d8';
      $('#eventColor').val(rgbToHex(color));

      if (ev.allDay) {
        $('#eventDuration').prop('checked', true).trigger('change');
        if (ev.start) {
          let start = new Date(ev.start);
          $('#eventStartDate').val(
            `${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2,'0')}-${String(start.getDate()).padStart(2,'0')}`
          );
        }
        if (ev.end) {
          let endDate = new Date(ev.end);
          endDate.setDate(endDate.getDate() - 1); // FullCalendar exclusive end
          $('#eventEndDate').val(
            `${endDate.getFullYear()}-${String(endDate.getMonth() + 1).padStart(2,'0')}-${String(endDate.getDate()).padStart(2,'0')}`
          );
        } else {
          $('#eventEndDate').val($('#eventStartDate').val());
        }
      } else {
        $('#eventDuration').prop('checked', false).trigger('change');
        if (ev.start) {
          renderTimedScheduleRows(deriveSchedulesFromEvent(ev));
        }
      }

      //$('#eventModal').modal('show');
    },

    // Called when external draggable dropped onto calendar
    eventReceive: function(info) {
      let ev = info.event;

      // ensure end time exists (default 1h if timed)
      if (!ev.allDay && !ev.end) {
        let end = new Date(ev.start);
        end.setHours(end.getHours() + 1);
        ev.setEnd(end);
      }

      // Prepare new event payload (save to DB in local form)
      function formatLocalDateTime(date) {
        if (!date) return '';
        return (
          date.getFullYear() + '-' +
          String(date.getMonth() + 1).padStart(2,'0') + '-' +
          String(date.getDate()).padStart(2,'0') + 'T' +
          String(date.getHours()).padStart(2,'0') + ':' +
          String(date.getMinutes()).padStart(2,'0')
        );
      }

      let newEvent = {
        title: ev.title ? ev.title.trim() : '',
        start: ev.start ? formatLocalDateTime(new Date(ev.start)) : '',
        end: ev.end ? formatLocalDateTime(new Date(ev.end)) : '',
        all_day: ev.allDay ? 1 : 0,
        color: ev.backgroundColor || ev.extendedProps.color || '#3788d8',
        guests: '',
        location: ''
      };

      // Save to DB
      $.post('add_event.php', newEvent, function(res) {
        try {
          let data = typeof res === 'object' ? res : JSON.parse(res);
          if (data.id) {
            //ev.setProp('id', data.id);
            ev.remove();

            // Prefill modal with dropped date/time (local)
            $('#eventForm')[0].reset();
            $('#eventId').val(data.id);
            $('#eventTitle').val(newEvent.title);
            $('#eventColor').val(rgbToHex(newEvent.color));

            if (ev.allDay) {
              $('#eventDuration').prop('checked', true).trigger('change');
              if (ev.start) {
                let start = new Date(ev.start);
                $('#eventStartDate').val(`${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2,'0')}-${String(start.getDate()).padStart(2,'0')}`);
              }
              if (ev.end) {
                let endDate = new Date(ev.end);
                endDate.setDate(endDate.getDate() - 1);
                $('#eventEndDate').val(`${endDate.getFullYear()}-${String(endDate.getMonth() + 1).padStart(2,'0')}-${String(endDate.getDate()).padStart(2,'0')}`);
              } else {
                $('#eventEndDate').val($('#eventStartDate').val());
              }
            } else {
              $('#eventDuration').prop('checked', false).trigger('change');
              renderTimedScheduleRows(deriveSchedulesFromEvent(ev));
            }

            $('#eventModal').modal('show');
          } else {
            console.error('Unexpected response', data);
            calendar.refetchEvents();
          }
        } catch (e) {
          console.error('Invalid JSON from add_event.php:', res);
          calendar.refetchEvents();
        }
      }, 'json').fail(function() {
        calendar.refetchEvents();
      });

      if ($('#drop-remove').is(':checked')) {
        $(info.draggedEl).remove();
      }
    },

    eventDrop: function(info) { saveEvent(info.event); },
    eventResize: function(info) { saveEvent(info.event); },

    // when dragging stops, check for delete zone
    eventDragStop: function(info) {
      let trash = deleteZone[0].getBoundingClientRect();
      let x = info.jsEvent.clientX, y = info.jsEvent.clientY;
      if (x >= trash.left && x <= trash.right && y >= trash.top && y <= trash.bottom) {
        // If this is a draggable-event originally from external draggable source, check extended prop dbId
        if (info.event.extendedProps && info.event.extendedProps.dbId) {
          // delete draggable event record
          $.post('delete_draggable.php', {id: info.event.extendedProps.dbId}, function() {
            info.event.remove();
            Swal.fire('Deleted!', '', 'success');
          });
        } else {
          // normal calendar event
          $.post('delete_event.php', {id: info.event.id}, function() {
            info.event.remove();
            Swal.fire('Deleted!', '', 'success');
            calendar.refetchEvents();
          });
        }
      }
    },

    drop: function(info) {
      // handled in eventReceive and by drop-remove checkbox
      if ($('#drop-remove').is(':checked')) {
        $(info.draggedEl).remove();
      }
    }
  });

  // Edit -> open editable modal with same event data
  $('#viewDuplicateBtn').click(function() {
    let ev = $(this).data('event');
    if (!ev) return;

    $('#viewEventModal').modal('hide');

    $('#eventForm')[0].reset();
    $('#eventId').val('');
    $('#eventTitle').val((ev.title || '') + ' (Copy)');
    $('#eventDescription').val(ev.extendedProps.description || '');
    setGuestSelection(ev.extendedProps.guests || '');
    $('#eventLocation').val(ev.extendedProps.location || '');
    $('#eventColor').val(rgbToHex(ev.backgroundColor || '#3788d8'));
    $('#eventColorChooser .color-choice').css('border', 'none');
    $(`#eventColorChooser .color-choice[data-color="${rgbToHex(ev.backgroundColor || '#3788d8')}"]`).css('border', '3px solid #000');

    if (ev.allDay) {
      $('#eventDuration').prop('checked', true).trigger('change');
      $('#eventStartDate').val(ev.startStr.substring(0,10));
      $('#eventEndDate').val(getInclusiveAllDayEndValue(ev));
    } else {
      $('#eventDuration').prop('checked', false).trigger('change');
      renderTimedScheduleRows(deriveSchedulesFromEvent(ev));
    }

    $('#createdByWrapper').hide();
    $('#updatedByWrapper').hide();
    $('#eventModal').modal('show');
  });

  $('#viewEditBtn').click(function() {
    let ev = $(this).data('event');
    if (!ev) return;

    $('#viewEventModal').modal('hide');

    // Fill eventModal form fields (reuse your old logic)
    $('#eventId').val(getParentEventId(ev));
    $('#eventTitle').val(ev.title || '');
    $('#eventDescription').val(ev.extendedProps.description || '');
    setGuestSelection(ev.extendedProps.guests || '');
    $('#eventLocation').val(ev.extendedProps.location || '');
    $('#eventColor').val(ev.backgroundColor || '#3788d8');

    if (ev.allDay) {
      $('#eventDuration').prop('checked', true).trigger('change');
      $('#eventStartDate').val(ev.startStr.substring(0,10));
      $('#eventEndDate').val(getInclusiveAllDayEndValue(ev));
    } else {
      $('#eventDuration').prop('checked', false).trigger('change');
      renderTimedScheduleRows(deriveSchedulesFromEvent(ev));
    }

    $('#eventModal').modal('show');
  });

  // Delete directly
  $('#viewDeleteBtn').click(function() {
    let id = $(this).data('id');
    if (!id) return;

    Swal.fire({
      title: 'Delete this event?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it'
    }).then(result => {
      if (result.isConfirmed) {
        $.post('delete_event.php', {id}, function() {
          $('#viewEventModal').modal('hide');
          Swal.fire('Deleted!', '', 'success');
          calendar.refetchEvents();
        });
      }
    });
  });

  // Save/update event to DB
  function saveEvent(event) {
    function formatLocalDateTime(date) {
      if (!date) return '';
      return (
        date.getFullYear() + '-' +
        String(date.getMonth() + 1).padStart(2,'0') + '-' +
        String(date.getDate()).padStart(2,'0') + 'T' +
        String(date.getHours()).padStart(2,'0') + ':' +
        String(date.getMinutes()).padStart(2,'0')
      );
    }

    let payload = {
      id: getParentEventId(event),
      title: event.title,
      description: event.extendedProps.description || '',
      start: event.start ? formatLocalDateTime(new Date(event.start)) : '',
      end: event.end ? formatLocalDateTime(new Date(event.end)) : '',
      guests: event.extendedProps.guests || '',
      location: event.extendedProps.location || '',
      color: event.backgroundColor || event.borderColor || '#3788d8',
      all_day: event.allDay ? 1 : 0
    };

    if (!event.allDay && event.extendedProps && event.extendedProps.eventScheduleId) {
      payload.parent_id = getParentEventId(event);
      payload.schedule_id = event.extendedProps.eventScheduleId;
    }

    $.post('update_event.php', payload, function(res) {
      try {
        let data = typeof res === 'object' ? res : JSON.parse(res);
        if (data.success) {
          Swal.fire('Updated!', '', 'success');
        } else {
          Swal.fire('Error', data.message || 'Update failed.', 'error');
          calendar.refetchEvents();
        }
      } catch (e) {
        console.error('Invalid JSON from update_event.php:', res);
        calendar.refetchEvents();
      }
    }).fail(function() {
      Swal.fire('Error', 'Could not update event.', 'error');
      calendar.refetchEvents();
    });
  }

  calendar.render();

  $('#addTimedScheduleRow').on('click', function() {
    addTimedScheduleRow({});
  });

  $('#eventGuestsSelectAll').on('click', function() {
    $('#eventGuests option').prop('selected', true);
  });

  $('#eventGuestsClear').on('click', function() {
    $('#eventGuests option').prop('selected', false);
    $('#eventGuestExtras').val('');
  });

  $(document).on('click', '.remove-timed-schedule', function() {
    const rows = $('#timedScheduleRows .timed-schedule-row');
    if (rows.length <= 1) {
      const row = rows.first();
      row.attr('data-schedule-id', '');
      row.find('input').val('');
      return;
    }

    $(this).closest('.timed-schedule-row').remove();
  });

  // Toggle all day / time fields
  $('#eventDuration').on('change', function(){
    if ($(this).is(':checked')) {
      // Switching TO all-day
      const schedules = collectTimedSchedules() || [];
      const startDate = schedules.length ? schedules[0].date : '';
      const endDate = schedules.length ? schedules[schedules.length - 1].date : '';

      if (startDate) {
        $('#eventStartDate').val(startDate);
        $('#eventEndDate').val(endDate || startDate);
      }

      $('#allDayFields').removeClass('d-none');
      $('#timeFields').addClass('d-none');
    } else {
      // Switching TO timed
      let startDate = $('#eventStartDate').val();
      let endDate   = $('#eventEndDate').val();

      if (startDate) {
        renderTimedScheduleRows(buildSchedulesFromDateRange(startDate, endDate || startDate));
      } else if (!$('#timedScheduleRows .timed-schedule-row').length) {
        renderTimedScheduleRows([{}]);
      }

      $('#allDayFields').addClass('d-none');
      $('#timeFields').removeClass('d-none');
    }
  });

  // Form submission
  $('#eventForm').submit(function(e){
    e.preventDefault();
    let allDay = $('#eventDuration').is(':checked');
    let payload = { 
      id: $('#eventId').val(),
      title: $('#eventTitle').val(),
      description: $('#eventDescription').val(),
      all_day: allDay ? 1 : 0,
      guests: collectGuestEmails().join(', '),
      location: $('#eventLocation').val(),
      color: $('#eventColor').val() || '#3788d8',
      is_private: $('input[name="is_private"]:checked').val() || '0'
    };

    if (allDay) {
      payload.start = $('#eventStartDate').val();
      payload.end = $('#eventEndDate').val();
    } else {
      let schedules = collectTimedSchedules();
      if (!schedules) {
        Swal.fire('Error', 'Each schedule row needs a date, start time, and end time, and the end must be after the start.', 'error');
        return;
      }

      payload.schedules = JSON.stringify(schedules);
      payload.start = schedules[0].date + 'T' + schedules[0].start_time;
      payload.end = schedules[schedules.length - 1].date + 'T' + schedules[schedules.length - 1].end_time;
    }

    let url = payload.id ? 'update_event.php' : 'add_event.php';
    $.post(url, payload, function(res){
      try {
        let data = typeof res==='object'?res:JSON.parse(res);
        if (data.success || data.id) {
          $('#eventModal').modal('hide');
          Swal.fire('Saved!', '', 'success');
          calendar.refetchEvents();
        } else Swal.fire('Error', data.message || 'Save failed','error');
      } catch(e){ console.error(e); Swal.fire('Error','Invalid server response','error'); }
    }, 'json').fail(()=>Swal.fire('Error','Cannot save event','error'));
  });

  $('#deleteEvent').click(function() {
    let id = $('#eventId').val();
    if (!id) return;
    Swal.fire({
      title: 'Delete this event?',
      icon: 'warning',
      showCancelButton: true,
      allowOutsideClick: false,
      confirmButtonText: 'Yes, delete it'
    }).then(result => {
      if (result.isConfirmed) {
        $.post('delete_event.php', {id}, function() {
          $('#eventModal').modal('hide');
          Swal.fire('Deleted!', '', 'success');
          calendar.refetchEvents();
        });
      }
    });
  });

  // === Draggables ===
  // Use Draggable on container (safer)
  try {
    new FullCalendar.Draggable(document.getElementById('external-events'), {
      itemSelector: '.external-event',
      eventData: function(el) {
        let $el = $(el);
        return {
          title: $el.data('title') || $el.text().trim(),
          color: $el.data('color') || $el.css('background-color'),
          extendedProps: { dbId: $el.data('id') }
        };
      }
    });
  } catch (e) {
    console.warn('Draggable not available or failed to initialize:', e);
  }

  // Color chooser
  let currColor = '#3788d8';
  $('#color-chooser > li > a').click(function(e) {
    e.preventDefault();
    let col = $(this).css('color') || '#3788d8';
    currColor = rgbToHex(col);
    $('#add-new-event').css({
      'background-color': currColor,
      'border-color': currColor
    });
  });

  // Add new draggable event
  $('#add-new-event').click(function(e) {
    e.preventDefault();
    let val = $('#new-event').val().trim();
    if (!val) return;
    $.post('add_draggable.php', {title: val, color: currColor}, function(res) {
      let data = typeof res === 'object' ? res : JSON.parse(res);
      if (!data.id) {
        Swal.fire('Error', 'Could not create draggable', 'error');
        return;
      }
      let eventEl = $('<div />')
        .addClass('external-event')
        .attr('data-id', data.id)
        .attr('data-title', data.title)
        .attr('data-color', data.color)
        .css({
          'background-color': data.color,
          'border-color': data.color,
          'color': '#fff',
          'padding': '6px',
          'margin-bottom': '6px',
          'cursor': 'move',
          'position': 'relative'
        }).text(data.title);

      $('<span>&times;</span>')
        .addClass('remove-draggable')
        .css({
          'position': 'absolute',
          'right': '4px',
          'top': '2px',
          'cursor': 'pointer',
          'color': '#fff'
        }).appendTo(eventEl);

      $('#external-events').prepend(eventEl);
      // Re-init Draggable (FullCalendar Draggable watches container)
      $('#new-event').val('');
    }, 'json').fail(function() {
      Swal.fire('Error', 'Could not create draggable', 'error');
    });
  });

  // Delete draggable by ×
  $('#external-events').on('click', '.remove-draggable', function(e) {
    e.stopPropagation();
    let parent = $(this).closest('.external-event');
    let id = parent.data('id');
    Swal.fire({
      title: 'Delete this draggable event?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it'
    }).then(result => {
      if (result.isConfirmed) {
        $.post('delete_draggable.php', {id}, function() {
          parent.remove();
          Swal.fire('Deleted!', '', 'success');
        });
      }
    });
  });

  // Prevent Bootstrap modal focus enforcement conflict with other libs
  if ($.fn.modal && $.fn.modal.Constructor) {
    $.fn.modal.Constructor.prototype._enforceFocus = function() {};
  }

  // Color chooser in modal
  $('#eventColorChooser').on('click', '.color-choice', function() {
    let selected = $(this).data('color');
    $('#eventColor').val(selected); // update hidden input
    // highlight selection
    $('#eventColorChooser .color-choice').css('border', 'none');
    $(this).css('border', '3px solid #000');
  });

  $('#calendarSearch').on('input', function() {
    calendarSearchTerm = ($(this).val() || '').toLowerCase().trim();
    applyCalendarFilters();
  });

  $('#jumpToDateBtn').on('click', function() {
    const target = $('#jumpToDate').val();
    if (target) {
      calendar.gotoDate(target);
    }
  });

  $('#focusTodayBtn').on('click', function() {
    calendar.today();
  });

  $('#clearCalendarFiltersBtn').on('click', function() {
    calendarSearchTerm = '';
    selectedColors = [];
    showHolidays = true;
    showAllDayEvents = true;
    showTimedEvents = true;
    $('#calendarSearch').val('');
    $('#toggleHolidays').prop('checked', true);
    $('#toggleAllDayEvents').prop('checked', true);
    $('#toggleTimedEvents').prop('checked', true);
    renderColorFilters(calendar.getEvents());
    applyCalendarFilters();
  });

  $('#toggleHolidays').on('change', function() {
    showHolidays = $(this).is(':checked');
    applyCalendarFilters();
  });

  $('#toggleAllDayEvents').on('change', function() {
    showAllDayEvents = $(this).is(':checked');
    applyCalendarFilters();
  });

  $('#toggleTimedEvents').on('change', function() {
    showTimedEvents = $(this).is(':checked');
    applyCalendarFilters();
  });

  $(document).on('click', '#calendarColorFilters .filter-chip', function() {
    const color = $(this).data('color');
    if (!color) return;

    if (selectedColors.includes(color)) {
      selectedColors = selectedColors.filter(item => item !== color);
    } else {
      selectedColors.push(color);
    }

    const usingExplicitFilter = selectedColors.length > 0;
    $('#calendarColorFilters .filter-chip').removeClass('active');
    if (!usingExplicitFilter) {
      $('#calendarColorFilters .filter-chip').addClass('active');
    } else {
      selectedColors.forEach(function(activeColor) {
        $(`#calendarColorFilters .filter-chip[data-color="${activeColor}"]`).addClass('active');
      });
    }

    applyCalendarFilters();
  });

  $(document).on('click', '.agenda-item', function() {
    const eventId = $(this).data('event-id');
    const start = $(this).data('start');
    if (start) {
      calendar.gotoDate(start);
    }

    if (eventId) {
      const event = calendar.getEventById(String(eventId));
      if (event) {
        openEventDetails(event);
      }
    }
  });

  $(document).on('keydown', function(e) {
    if ($(e.target).is('input, textarea')) {
      return;
    }

    if (e.key === '/' && !e.ctrlKey && !e.metaKey) {
      e.preventDefault();
      $('#calendarSearch').trigger('focus');
    }

    if ((e.key === 't' || e.key === 'T') && !e.ctrlKey && !e.metaKey) {
      e.preventDefault();
      calendar.today();
    }
  });
});
</script>
</body>
</html>
