<?php
include('../header.php');
include('../sidenav.php');
include('../config.php');

// Get logged-in user ID
$userId = $_SESSION['user_id'] ?? null;

// Redirect if not logged in
if (!$userId) {
    header("Location: ../");
    exit;
}

// Ensure user is admin
$stmt = $conn->prepare("SELECT userType FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($userType);
$stmt->fetch();
$stmt->close();

if ($userType !== 'admin') {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
      Swal.fire({
        icon: 'error',
        title: 'Access Denied',
        text: 'You are not authorized to view this page.',
      }).then(() => window.location.href = '../');
    </script>";
    exit;
}

// Fetch messages
$messages = $conn->query("SELECT * FROM contact_messages ORDER BY sent_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>KODUS | Inbox</title>
  <!-- DataTables CSS -->
  <link rel="stylesheet" href="<?php echo $base_url;?>kodus/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $base_url;?>kodus/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $base_url;?>kodus/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <style>
    .inbox-container { display: flex; height: 80vh; }
    .message-list { width: 35%; border-right: 1px solid #ddd; overflow-y: auto; }
    .message-item { padding: 10px; cursor: pointer; border-bottom: 1px solid #eee; }
    .message-item:hover { background: #6c757d; }
    .message-item.active { background: #6c757d; color: #fff; /* optional for readability */ }
    .unread { font-weight: bold; background: #3f6791; }
    .message-detail { flex-grow: 1; padding: 20px; overflow-y: auto; }
    .reply-box { margin-top: 20px; }
    .empty-inbox, .empty-detail { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; }
  </style>
</head>
<body class="bg-light">

<div class="wrapper">


  <div class="content-wrapper">

    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Inbox</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>kodus/home">Home</a></li>
              <li class="breadcrumb-item active">Inbox</li>
            </ol>
          </div><!-- /.col -->
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Inbox</h3>
          </div>
          <div class="card-body">
            <div class="inbox-container">
              <!-- LEFT: Message list -->
                <div class="message-list" id="messageList">
                  <?php if ($messages->num_rows > 0): ?>
                    <?php while ($row = $messages->fetch_assoc()): ?>
                      <div class="message-item <?= $row['is_read'] ? '' : 'unread' ?>" 
                           data-id="<?= $row['id'] ?>" 
                           data-email="<?= htmlspecialchars($row['user_email']) ?>" 
                           data-name="<?= htmlspecialchars($row['user_name']) ?>" 
                           data-subject="<?= htmlspecialchars($row['subject']) ?>" 
                           data-message="<?= htmlspecialchars($row['message']) ?>" 
                           data-sent="<?= $row['sent_at'] ?>">
                        <strong><?= htmlspecialchars($row['user_name']) ?></strong><br>
                        <small><?= htmlspecialchars($row['subject']) ?></small><br>
                        <small><i><?= $row['sent_at'] ?></i></small>
                      </div>
                    <?php endwhile; ?>
                  <?php else: ?>
                    <div class="empty-inbox text-center p-4">
                      <img src="<?php echo $base_url; ?>kodus/dist/img/empty-inbox.png" alt="Empty Inbox" style="max-width:150px; opacity:0.6;">
                      <p class="mt-3 text-muted"><i>No messages in your inbox</i></p>
                    </div>
                  <?php endif; ?>
                </div>


              <!-- RIGHT: Message details + reply -->
              <div class="message-detail" id="messageDetail">
                <?php if ($messages->num_rows > 0): ?>
                  <p><i>Select a message to view details...</i></p>
                <?php else: ?>
                  <div class="empty-detail text-center p-4">
                    <!-- <img src="<?php echo $base_url; ?>kodus/dist/img/empty-detail.png" 
                         alt="No Details" style="max-width:180px; opacity:0.6;"> -->
                    <p class="mt-3 text-muted"><i>No message selected</i></p>
                    <p class="mt-3 text-muted"><i>(inbox is empty)</i></p>
                  </div>
                <?php endif; ?>
              </div>

            </div>
            </div>

          </div>
        </div>
      </div>

    </div>
  <footer class="main-footer">
    <div class="float-right d-none d-sm-inline">
      Anything you want
    </div>
    <strong>Copyright &copy; 2014-2021 <a href="https://adminlte.io">AdminLTE.io</a>.</strong> All rights reserved.
  </footer>

</div>

<!-- REQUIRED SCRIPTS -->

<!-- jQuery -->
<script src="<?php echo $base_url;?>kodus/plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="<?php echo $base_url;?>kodus/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables  & Plugins -->
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
<!-- AdminLTE App -->
<script src="<?php echo $base_url;?>kodus/dist/js/adminlte.min.js"></script>

<!-- DataTables Scripts -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<!-- <script>
  $(function () {
    $('#messagesTable').DataTable({
        paging: true,
        lengthChange: true,
        searching: true,
        ordering: true,
        info: true,
        autoWidth: false,
        responsive: true,
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]]
    });
  });
</script> -->

<script>
  let lastOpenedId = null;

  $(document).on('click', '.message-item', function() {
    let id = $(this).data('id');
    lastOpenedId = id;

    // Remove active class from all items, then add to the clicked one
    $('.message-item').removeClass('active');
    $(this).addClass('active').removeClass('unread');

    // Load conversation via AJAX
    $.get('get_thread.php', {id:id}, function(html) {
      $('#messageDetail').html(html);
    });

    // Mark as read in DB
    $.post('mark_read.php', {id: id}, function() {
      updateUnreadCount();
    });
  });

  // Refresh badge every 30s
  setInterval(updateUnreadCount, 30000);

  // Refresh message list every 30s
  setInterval(updateMessageList, 30000);

  function updateMessageList() {
    $.get('fetch_messages.php', function(html) {
      $('#messageList').html(html);

      if (lastOpenedId) {
        $(`#messageList .message-item[data-id="${lastOpenedId}"]`).addClass("active");
      }

      // If inbox is empty, also reset right pane
      if ($('#messageList').find('.message-item').length === 0) {
        $('#messageDetail').html(`
          <div class="empty-detail text-center p-4">
            <p class="mt-3 text-muted"><i>No message selected</i></p>
            <p class="mt-3 text-muted"><i>(inbox is empty)</i></p>
          </div>
        `);
      }
    });
  }

  function updateUnreadCount() {
    $.getJSON('get_unread_count.php', function(data) {
      let badge = $('.nav-link .badge');
      if (data.count > 0) {
        if (badge.length) {
          badge.text(data.count);
        } else {
          $('.nav-link:contains("Inbox") p').append(
            `<span class="right badge badge-danger">${data.count}</span>`
          );
        }
      } else {
        badge.remove();
      }
    });
  }

  function sendReply(id, email) {
  let reply = $('#replyText').val();
  if (!reply.trim()) {
    Swal.fire('Oops','Reply cannot be empty!','warning');
    return;
  }

  // Get subject & message from the selected message item
  let subject = $(`.message-item[data-id="${id}"]`).data('subject');
  let originalMessage = $(`.message-item[data-id="${id}"]`).data('message');

  Swal.fire({
    icon: 'info',
    title: 'Sending Reply...',
    text: 'Please wait.',
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });

  // Send reply via AJAX with subject & message
  $.post('send_reply.php', {
      id:id, 
      email:email, 
      reply:reply, 
      subject:subject, 
      message:originalMessage
  }, function(res) {
      if (res.trim() === "success") {
        Swal.fire('Success','Reply sent successfully!','success')
          .then(() => {
            if (lastOpenedId) {
              window.location.href = './?id=' + lastOpenedId;
            } else {
              window.location.href = './';
            }
          });
      } else {
        Swal.fire('Error', res, 'error')
          .then(() => {
            if (lastOpenedId) {
              window.location.href = './?id=' + lastOpenedId;
            } else {
              window.location.href = './';
            }
          });
      }
  });
}

  $(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const id = urlParams.get('id');
    if (id) {
      lastOpenedId = id;
      $.get('get_thread.php', {id:id}, function(html) {
        $('#messageDetail').html(html);
      });
      $.post('mark_read.php', {id:id}, function() {
        updateUnreadCount();
      });
      // Highlight the message in the list
      $('.message-item').removeClass('active');
      $(`.message-item[data-id="${id}"]`).addClass('active').removeClass('unread');
    }
  });
</script>
</body>
</html>
