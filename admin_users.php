<?php
session_start();
include 'db.php';

// 1. SECURITY
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'Admin') { header("Location: login.php"); exit(); }

// 2. HANDLE USER UPDATE (Backend Logic)
if (isset($_POST['update_user'])) {
    $uid = intval($_POST['user_id']);
    $fname = $_POST['first_name'];
    $lname = $_POST['last_name'];
    $email = $_POST['email'];
    $fb_name = $_POST['facebook_name']; 
    $phone = $_POST['phone'];
    $role = $_POST['role'];

    // Update query with facebook_name
    $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, facebook_name=?, phone=?, role=? WHERE id=?");
    $stmt->bind_param("ssssssi", $fname, $lname, $email, $fb_name, $phone, $role, $uid);

    if ($stmt->execute()) {
        $msg = "User updated successfully!";
        $msg_type = "success";
    } else {
        $msg = "Error updating user.";
        $msg_type = "danger";
    }
    $stmt->close();
}

// 3. FETCH USERS
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'All';
$sql = "SELECT * FROM users";
if($role_filter != 'All') {
    $sql .= " WHERE role = '$role_filter'";
}
$users = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage <?php echo $role_filter; ?>s</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --bg-dark: #0f0f0f; --bg-card: #1a1a1a; --accent-orange: #ff9000; }
        body { background: var(--bg-dark); color: white; font-family: 'Plus Jakarta Sans', sans-serif; overflow-x: hidden; }
        
        /* SIDEBAR */
        .sidebar { width: 260px; height: 100vh; background: #050505; position: fixed; top: 0; left: 0; border-right: 1px solid #222; padding: 20px; display: flex; flex-direction: column; z-index: 1000; }
        .nav-label { font-size: 11px; text-transform: uppercase; color: #666; font-weight: 700; margin-bottom: 10px; padding-left: 15px; letter-spacing: 1px; }
        .nav-link { color: #888; padding: 12px 18px; border-radius: 12px; margin-bottom: 5px; display: flex; align-items: center; gap: 14px; font-weight: 500; transition: all 0.2s ease; text-decoration: none; }
        .nav-link:hover, .nav-link.active { background: rgba(255, 144, 0, 0.15); color: var(--accent-orange); }
        
        .main-content { margin-left: 260px; padding: 40px 50px; }

        /* TABLE UI */
        .table-card { background: var(--bg-card); border: 1px solid #333; border-radius: 16px; overflow: hidden; }
        .custom-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .custom-table th { background: #222; padding: 15px 20px; text-align: left; color: #888; font-size: 12px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; border-bottom: 1px solid #333; }
        .custom-table td { padding: 18px 20px; border-bottom: 1px solid #2a2a2a; color: #eee; font-size: 14px; vertical-align: middle; background: var(--bg-card); transition: background 0.2s; }
        .custom-table tr:last-child td { border-bottom: none; }
        .custom-table tr:hover td { background: #222; }

        .user-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 15px; border: 2px solid #333; }
        
        /* Buttons */
        .btn-action { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; border: none; transition: 0.2s; margin-left: 5px; }
        .btn-edit { background: rgba(13, 202, 240, 0.15); color: #0dcaf0; }
        .btn-edit:hover { background: #0dcaf0; color: white; }
        .btn-delete { background: rgba(220, 53, 69, 0.15); color: #dc3545; }
        .btn-delete:hover { background: #dc3545; color: white; }

        /* Badges */
        .role-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-Landlord { background: rgba(255, 144, 0, 0.15); color: var(--accent-orange); border: 1px solid rgba(255, 144, 0, 0.3); }
        .badge-Tenant { background: rgba(13, 202, 240, 0.15); color: #0dcaf0; border: 1px solid rgba(13, 202, 240, 0.3); }
        .badge-Admin { background: rgba(220, 53, 69, 0.15); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.3); }

        /* MODAL */
        .modal-content { background: var(--bg-card); border: 1px solid #333; color: white; border-radius: 16px; }
        .modal-header { border-bottom: 1px solid #333; }
        .modal-footer { border-top: 1px solid #333; }
        .form-control, .form-select { background: #111; border: 1px solid #333; color: white; padding: 10px 15px; border-radius: 10px; }
        .form-control:focus, .form-select:focus { background: #111; border-color: var(--accent-orange); color: white; box-shadow: none; }
        .form-label { color: #888; font-size: 13px; font-weight: 600; margin-bottom: 5px; }
        .btn-close-white { filter: invert(1); }
    </style>
</head>
<body>

<div class="sidebar">
    <a href="#" class="nav-link" style="font-size: 24px; font-weight: 800; color: white; margin-bottom: 40px;">
        <i class="bi bi-shield-lock-fill text-primary-orange" style="color: var(--accent-orange);"></i> Admin
    </a>
    
    <div class="nav-label">Main</div>
    <a href="dashboard_admin.php" class="nav-link"><i class="bi bi-speedometer2"></i> Overview</a>
    
    <div class="nav-label mt-4">Management</div>
    <a href="admin_users.php?role=Landlord" class="nav-link <?php echo ($role_filter == 'Landlord') ? 'active' : ''; ?>"><i class="bi bi-person-tie"></i> Landlords</a>
    <a href="admin_users.php?role=Tenant" class="nav-link <?php echo ($role_filter == 'Tenant') ? 'active' : ''; ?>"><i class="bi bi-people"></i> Tenants</a>
    <a href="admin_properties.php" class="nav-link"><i class="bi bi-houses"></i> Properties</a>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold m-0">Manage <?php echo $role_filter; ?>s</h2>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-success border-0 rounded-3 mb-4"><?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>
    <?php if(isset($msg)): ?>
        <div class="alert alert-<?php echo $msg_type; ?> border-0 rounded-3 mb-4"><?php echo $msg; ?></div>
    <?php endif; ?>
    
    <div class="table-card">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>User Profile</th>
                    <th>Role</th>
                    <th>Contact Info</th>
                    <th>Date Joined</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users->num_rows > 0): ?>
                    <?php while($u = $users->fetch_assoc()):
                        $pic = !empty($u['profile_pic']) ? "assets/uploads/".$u['profile_pic'] : "assets/default.jpg";
                        $email = isset($u['email']) ? htmlspecialchars($u['email']) : '';
                        $fb = isset($u['facebook_name']) ? htmlspecialchars($u['facebook_name']) : ''; 
                        $phone = isset($u['phone']) ? htmlspecialchars($u['phone']) : '';
                        $fname = htmlspecialchars($u['first_name']);
                        $lname = htmlspecialchars($u['last_name']);
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="<?php echo $pic; ?>" class="user-img">
                                <div>
                                    <div class="fw-bold text-white"><?php echo $fname . ' ' . $lname; ?></div>
                                    <div class="small text-secondary" style="font-size: 11px;">@<?php echo htmlspecialchars($u['username']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="role-badge badge-<?php echo $u['role']; ?>"><?php echo $u['role']; ?></span></td>
                        <td>
                            <div class="d-flex flex-column gap-1">
                                <?php if($email): ?><span class="small text-white"><i class="bi bi-envelope me-2 text-secondary"></i><?php echo $email; ?></span><?php endif; ?>
                                <?php if($fb): ?><span class="small text-secondary"><i class="bi bi-facebook me-2 text-primary"></i><?php echo $fb; ?></span><?php endif; ?>
                                <?php if($phone): ?><span class="small text-secondary"><i class="bi bi-telephone me-2 text-success"></i><?php echo $phone; ?></span><?php endif; ?>
                            </div>
                        </td>
                        <td class="text-secondary"><?php echo date("M d, Y", strtotime($u['created_at'])); ?></td>
                        <td class="text-end">
                            <?php if($u['role'] != 'Admin'): ?>
                                <button class="btn-action btn-edit"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editUserModal"
                                        data-id="<?php echo $u['id']; ?>"
                                        data-fname="<?php echo $fname; ?>"
                                        data-lname="<?php echo $lname; ?>"
                                        data-email="<?php echo $email; ?>"
                                        data-fb="<?php echo $fb; ?>"
                                        data-phone="<?php echo $phone; ?>"
                                        data-role="<?php echo $u['role']; ?>">
                                    <i class="bi bi-pencil-fill" style="font-size: 12px;"></i>
                                </button>

                                <button type="button" class="btn-action btn-delete" data-bs-toggle="modal" data-bs-target="#deleteUserModal<?php echo $u['id']; ?>">
                                    <i class="bi bi-trash-fill" style="font-size: 12px;"></i>
                                </button>
                            <?php else: ?>
                                <span class="badge bg-secondary">Locked</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <div class="modal fade" id="deleteUserModal<?php echo $u['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold text-white"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Delete User</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body text-secondary">
                                    Are you sure you want to permanently delete the account of <strong class="text-white"><?php echo $fname . ' ' . $lname; ?></strong>?
                                    <br><br>
                                    All their associated data, properties, and records will be lost. This action cannot be undone.
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <a href="admin_delete.php?type=user&id=<?php echo $u['id']; ?>" class="btn btn-danger px-4 fw-bold">Yes, Delete User</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-secondary">No users found in this category.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit User Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" id="edit_fname" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" id="edit_lname" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Facebook Name (Text Only)</label>
                        <input type="text" name="facebook_name" id="edit_fb" class="form-control" placeholder="e.g. Juan Cruz">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" id="edit_role" class="form-select">
                            <option value="Tenant">Tenant</option>
                            <option value="Landlord">Landlord</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_user" class="btn btn-warning fw-bold text-dark">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const editModal = document.getElementById('editUserModal');
    editModal.addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;
        
        document.getElementById('edit_user_id').value = button.getAttribute('data-id');
        document.getElementById('edit_fname').value = button.getAttribute('data-fname');
        document.getElementById('edit_lname').value = button.getAttribute('data-lname');
        document.getElementById('edit_email').value = button.getAttribute('data-email');
        document.getElementById('edit_fb').value = button.getAttribute('data-fb'); 
        document.getElementById('edit_phone').value = button.getAttribute('data-phone');
        document.getElementById('edit_role').value = button.getAttribute('data-role');
    });
</script>

</body>
</html>