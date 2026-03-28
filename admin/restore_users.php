<?php
include('../header.php');
include('../sidenav.php');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: ../'); //index.php
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

// Get soft-deleted users
$users = $conn->query("SELECT id, username, email, deleted_at FROM users WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>KODUS | Restore Users</title>
	<!-- DataTables CSS -->
	<link rel="stylesheet" href="<?php echo $base_url;?>kodus/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
	<link rel="stylesheet" href="<?php echo $base_url;?>kodus/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
	<link rel="stylesheet" href="<?php echo $base_url;?>kodus/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
</head>
<body class="bg-light">
	
<div class="wrapper">


	<div class="content-wrapper">

		<div class="content-header">
			<div class="container-fluid">
				<div class="row mb-2">
					<div class="col-sm-6">
						<h1 class="m-0">Soft-Deleted Users</h1>
					</div>
					<div class="col-sm-6">
						<ol class="breadcrumb float-sm-right">
							<li class="breadcrumb-item"><a href="<?php echo $base_url; ?>kodus/home">Home</a></li>
							<li class="breadcrumb-item active">Restore Users</li>
						</ol>
					</div><!-- /.col -->
				</div>
			</div>
		</div>

		<div class="content">
			<div class="container-fluid">
				<div class="card">
					<div class="card-header">
						<h3 class="card-title">Soft-Deleted Users</h3>
					</div>
					<div class="card-body">

						<div class="table-container">
							<table id="deletedUsersTable" class="table table-bordered table-striped" style="text-align: center; width: 100%; table-layout: auto;">
								<thead style="font-size: 10px;">
									<tr>
										<th>User ID</th>
										<th>Username</th>
										<th>Email</th>
										<th>Deleted At</th>
										<th>Action</th>
									</tr>
								</thead>
								<tbody>
								<?php if ($users->num_rows > 0): ?>
									<?php while ($row = $users->fetch_assoc()): ?>
									<tr>
										<td><?= htmlspecialchars($row['id']) ?></td>
										<td><?= htmlspecialchars($row['username']) ?></td>
										<td><?= htmlspecialchars($row['email']) ?></td>
										<td><?= htmlspecialchars($row['deleted_at']) ?></td>
										<td>
											<button class="btn btn-success btn-sm restore-btn" data-id="<?= $row['id'] ?>">Restore</button>
										</td>
									</tr>
									<?php endwhile; ?>
								<?php else: ?>
									<tr>
										<td colspan="5" class="text-center">No data available</td>
									</tr>
								<?php endif; ?>
								</tbody>
							</table>
						</div>

					</div>
				</div>
			</div>
		</div>

	</div>

</div>
<!-- ./wrapper -->

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
<script>
$('.restore-btn').click(function () {
    const userId = $(this).data('id');
    Swal.fire({
        title: 'Restore User?',
        text: "Are you sure you want to restore this account?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, Restore'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                    title: 'Restoring...',
                    html: 'Please wait while the selected account is being restored...',
                    icon: 'info',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                $.ajax({
                    url: 'restore_user.php',
                    method: 'POST',
                    dataType: 'json',
                    data: { user_id: userId, csrf_token: window.KODUS_CSRF_TOKEN },
                    success: function (response) {
                        Swal.close();
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'User Restored',
                                text: response.message
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Restore Failed',
                                text: response.message
                            });
                        }

                        if (response.email_error) {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'warning',
                                title: 'Email failed to send.',
                                showConfirmButton: false,
                                timer: 3000
                            });
                        }
                    },
                    error: function () {
                        Swal.close();
                        Swal.fire({
                            icon: 'error',
                            title: 'Unexpected Server Response',
                            text: 'Something went wrong. Please try again later.'
                        });
                    }
                });
            }
    });
});
</script>

<script>
$(document).ready(function () {
    $('#deletedUsersTable').DataTable({
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
</script>

</body>
</html>
