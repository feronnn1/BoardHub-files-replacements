<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'Admin') { header("Location: login.php"); exit(); }

// Fetch Properties with Landlord Name
$sql = "SELECT p.*, u.first_name, u.last_name FROM properties p JOIN users u ON p.landlord_id = u.id";
$props = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Properties</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --bg-dark: #0f0f0f; --bg-card: #1a1a1a; --accent-orange: #ff9000; }
        body { background: var(--bg-dark); color: white; font-family: 'Plus Jakarta Sans', sans-serif; overflow-x: hidden; }
        .sidebar { width: 260px; height: 100vh; background: #050505; position: fixed; top: 0; left: 0; border-right: 1px solid #222; padding: 20px; display: flex; flex-direction: column; }
        .nav-label { font-size: 11px; text-transform: uppercase; color: #666; font-weight: 700; margin-bottom: 10px; padding-left: 15px; letter-spacing: 1px; }
        .nav-link { color: #888; padding: 12px 18px; border-radius: 12px; margin-bottom: 5px; display: flex; align-items: center; gap: 14px; font-weight: 500; transition: all 0.2s ease; text-decoration: none; }
        .nav-link:hover, .nav-link.active { background: rgba(255, 144, 0, 0.15); color: var(--accent-orange); }
        .main-content { margin-left: 260px; padding: 40px 50px; }
        
        .prop-card { background: #1a1a1a; border: 1px solid #333; border-radius: 12px; overflow: hidden; margin-bottom: 20px; display: flex; }
        .prop-img { width: 200px; height: 150px; object-fit: cover; }
        .prop-body { padding: 20px; flex-grow: 1; display: flex; justify-content: space-between; align-items: center; }
        .btn-view { background: rgba(255, 144, 0, 0.15); color: var(--accent-orange); border: none; padding: 8px 16px; border-radius: 8px; margin-right: 10px; text-decoration: none; font-size: 14px; font-weight: 600; transition: 0.2s; display: inline-block; }
        .btn-view:hover { background: var(--accent-orange); color: black; }
        
        /* Modal Styles */
        .modal-content { background: var(--bg-card); border: 1px solid #333; color: white; border-radius: 16px; }
        .modal-header { border-bottom: 1px solid #333; }
        .modal-footer { border-top: 1px solid #333; }
        .btn-close-white { filter: invert(1); }
        .btn-delete { background: rgba(220, 53, 69, 0.15); color: #dc3545; border: none; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; transition: 0.2s; }
        .btn-delete:hover { background: #dc3545; color: white; }
    </style>
</head>
<body>

<div class="sidebar">
    <a href="#" class="nav-link" style="font-size: 24px; font-weight: 800; color: white; margin-bottom: 40px;"><i class="bi bi-shield-lock-fill text-primary-orange" style="color: var(--accent-orange);"></i> Admin</a>
    <div class="nav-label">Main</div>
    <a href="dashboard_admin.php" class="nav-link"><i class="bi bi-speedometer2"></i> Overview</a>
    <div class="nav-label mt-4">Management</div>
    <a href="admin_users.php?role=Landlord" class="nav-link"><i class="bi bi-person-tie"></i> Landlords</a>
    <a href="admin_users.php?role=Tenant" class="nav-link"><i class="bi bi-people"></i> Tenants</a>
    <a href="admin_properties.php" class="nav-link active"><i class="bi bi-houses"></i> Properties</a>
</div>

<div class="main-content">
    <h2 class="fw-bold mb-4">Manage Properties</h2>

    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-success border-0 rounded-3 mb-4"><?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>
    
    <?php while($p = $props->fetch_assoc()):
        $imgs = json_decode($p['images'], true);
        $thumb = !empty($imgs) ? "assets/uploads/rooms/".$imgs[0] : "assets/default_room.jpg";
    ?>
    <div class="prop-card">
        <img src="<?php echo $thumb; ?>" class="prop-img">
        <div class="prop-body">
            <div>
                <h4 class="fw-bold m-0"><?php echo htmlspecialchars($p['title']); ?></h4>
                <p class="text-secondary small mb-2"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($p['location']); ?></p>
                <div class="small text-secondary">Owner: <span class="text-white"><?php echo htmlspecialchars($p['first_name'].' '.$p['last_name']); ?></span></div>
            </div>
            <div>
                <a href="edit_room.php?id=<?php echo $p['id']; ?>" class="btn-view">Manage Rooms</a>
                
                <button type="button" class="btn-delete" data-bs-toggle="modal" data-bs-target="#deletePropModal<?php echo $p['id']; ?>">
                    Delete
                </button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deletePropModal<?php echo $p['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-white"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Delete Property</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-secondary">
                    Are you sure you want to permanently delete <strong class="text-white"><?php echo htmlspecialchars($p['title']); ?></strong>?
                    <br><br>
                    This will completely remove the boarding house and <strong>all associated rooms</strong> from the system. This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="admin_delete.php?type=property&id=<?php echo $p['id']; ?>" class="btn btn-danger px-4 fw-bold">Yes, Delete Property</a>
                </div>
            </div>
        </div>
    </div>

    <?php endwhile; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>