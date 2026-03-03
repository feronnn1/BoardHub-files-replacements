<?php
session_start();
include 'db.php';

// 1. SECURITY
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'Tenant') { header("Location: login.php"); exit(); }

$username = $_SESSION['user'];

// Fetch Tenant Profile AND exact User ID
$user = $conn->query("SELECT * FROM users WHERE username='$username'")->fetch_assoc();
$user_id = $user['id']; // Now we safely have the ID for the rest of the page!
$pp = !empty($user['profile_pic']) ? "assets/uploads/" . $user['profile_pic'] : "assets/default.jpg";

// 2. FETCH ACTIVE RENTAL (Added shared_price to the query)
$app_query = "
    SELECT 
        app.id as app_id, 
        app.next_due_date, 
        app.created_at as start_date,
        app.room_id,
        p.title as house_name, 
        p.location, 
        p.description,
        p.inclusions,
        p.paid_addons,
        p.contact_phone, 
        p.contact_facebook, 
        p.contact_email,
        p.price,
        p.shared_price,
        r.room_name,
        r.room_image,
        u.first_name as landlord_fname, 
        u.last_name as landlord_lname, 
        u.profile_pic as landlord_pic
    FROM applications app
    JOIN properties p ON app.property_id = p.id
    JOIN room_units r ON app.room_id = r.id
    JOIN users u ON p.landlord_id = u.id
    WHERE app.tenant_id = $user_id AND app.status = 'Approved'
    LIMIT 1
";
$active_rental = $conn->query($app_query)->fetch_assoc();
$has_rental = ($active_rental) ? true : false;

// 3. FETCH PAYMENT HISTORY
$payments = [];
if ($has_rental) {
    $pay_q = $conn->query("SELECT * FROM payments WHERE tenant_id = $user_id ORDER BY payment_date DESC LIMIT 5");
    while($row = $pay_q->fetch_assoc()) {
        $payments[] = $row;
    }
}

// 4. FETCH ROOMMATES
$roommates = [];
if ($has_rental) {
    $r_id = $active_rental['room_id'];
    $mate_q = $conn->query("
        SELECT u.first_name, u.last_name, u.profile_pic, u.phone 
        FROM applications a 
        JOIN users u ON a.tenant_id = u.id 
        WHERE a.room_id = $r_id AND a.status = 'Approved' AND a.tenant_id != $user_id
    ");
    while($mate = $mate_q->fetch_assoc()) {
        $roommates[] = $mate;
    }
}

// 5. FETCH PENDING APPS
$pending_sql = "
    SELECT app.id as app_id, app.created_at, p.title, r.room_name 
    FROM applications app
    JOIN properties p ON app.property_id = p.id
    JOIN room_units r ON app.room_id = r.id
    WHERE app.tenant_id = $user_id AND app.status = 'Pending'
    ORDER BY app.created_at DESC
";
$pending_apps = $conn->query($pending_sql);

// 6. LOGIC
$due_status = "Up to Date";
$formatted_due_date = "N/A";
$is_overdue = false;
$room_desc = "";
$amenities = [];
$addons = [];
$room_img = "assets/default_room.jpg";

if ($has_rental) {
    $due_date_str = !empty($active_rental['next_due_date']) ? $active_rental['next_due_date'] : date('Y-m-d', strtotime($active_rental['start_date'] . ' +1 month'));
    $formatted_due_date = date("F d, Y", strtotime($due_date_str));
    
    $today = time();
    $due_time = strtotime($due_date_str);
    
    if ($due_time < $today) {
        $due_status = "Overdue";
        $status_class = "text-danger";
        $is_overdue = true;
    } elseif ($due_time < $today + (86400 * 5)) {
        $due_status = "Due Soon";
        $status_class = "text-warning";
    }

    $room_desc = nl2br(htmlspecialchars($active_rental['description']));
    $amenities = !empty($active_rental['inclusions']) ? explode(',', $active_rental['inclusions']) : [];
    $addons = !empty($active_rental['paid_addons']) ? explode(',', $active_rental['paid_addons']) : [];

    $raw_img = $active_rental['room_image'];
    if(!empty($raw_img)) {
        $decoded = json_decode($raw_img, true);
        if(is_array($decoded) && !empty($decoded[0])) {
            $room_img = "assets/uploads/rooms/" . $decoded[0];
        } elseif(!is_array($decoded)) {
            $room_img = "assets/uploads/rooms/" . $raw_img;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --bg-dark: #0f0f0f; --bg-card: #1a1a1a; --accent-orange: #ff9000; }
        body { background: var(--bg-dark); color: white; font-family: 'Plus Jakarta Sans', sans-serif; }
        
        .sidebar { width: 250px; height: 100vh; background: #050505; position: fixed; top: 0; left: 0; border-right: 1px solid #222; padding: 20px; display: flex; flex-direction: column; }
        .brand { font-size: 20px; font-weight: 800; margin-bottom: 40px; color: white; text-decoration: none; padding-left: 10px; }
        .nav-link { color: #888; padding: 12px 15px; border-radius: 10px; margin-bottom: 5px; display: flex; align-items: center; gap: 12px; text-decoration: none; font-weight: 500; transition: 0.2s; cursor: pointer; }
        .nav-link:hover, .nav-link.active { background: rgba(255, 144, 0, 0.1); color: var(--accent-orange); }
        .main-content { margin-left: 250px; padding: 40px; }

        .stat-card { background: var(--bg-card); border: 1px solid #333; border-radius: 16px; padding: 25px; margin-bottom: 25px; position: relative; overflow: hidden; }
        .stat-label { font-size: 13px; text-transform: uppercase; color: #888; font-weight: 700; letter-spacing: 0.5px; }
        .stat-value { font-size: 28px; font-weight: 800; margin: 5px 0 0 0; }
        
        .contact-pill { background: #222; border: 1px solid #333; padding: 10px 16px; border-radius: 10px; font-size: 13px; color: #ccc; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.2s; margin-bottom: 8px; width: 100%; }
        .contact-pill:hover { background: #333; color: white; border-color: #555; }
        .contact-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th { text-align: left; padding: 15px; color: #666; font-size: 12px; text-transform: uppercase; border-bottom: 1px solid #333; }
        .history-table td { padding: 15px; border-bottom: 1px solid #222; color: #ddd; font-size: 14px; }
        
        .empty-state { padding: 60px 20px; text-align: center; border: 2px dashed #333; border-radius: 20px; background: var(--bg-card); }
        
        /* ROOM DETAILS UI */
        .room-details-container { background: #151515; border-radius: 20px; border: 1px solid #333; overflow: hidden; }
        .room-hero-img { width: 100%; height: 350px; object-fit: cover; }
        .room-content-body { padding: 35px; }
        
        .occupant-card { background: #222; border: 1px solid #333; border-radius: 12px; padding: 15px; display: flex; align-items: center; gap: 15px; margin-bottom: 10px; transition: 0.2s; }
        .occupant-card:hover { border-color: #555; }
        .occupant-img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; }
        
        .amenity-pill { background: rgba(25, 135, 84, 0.15); color: #2ecc71; border: 1px solid rgba(25, 135, 84, 0.3); padding: 6px 14px; border-radius: 30px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; margin: 0 6px 8px 0; }
        .addon-pill { background: rgba(13, 202, 240, 0.15); color: #0dcaf0; border: 1px solid rgba(13, 202, 240, 0.3); padding: 6px 14px; border-radius: 30px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; margin: 0 6px 8px 0; }

        .modal-content { background-color: #1a1a1a; border: 1px solid #333; color: white; }
        .modal-header { border-bottom: 1px solid #333; }
        .modal-footer { border-top: 1px solid #333; }
        .btn-close-white { filter: invert(1); }
        .form-control { background: #222; border: 1px solid #333; color: white; }
        .form-control:focus { background: #222; border-color: var(--accent-orange); color: white; box-shadow: none; }
    </style>
</head>
<body>

<div class="sidebar">
    <a href="#" class="brand">Board<span class="text-warning">Hub</span></a>
    <a href="dashboard_tenant.php" class="nav-link active"><i class="bi bi-grid-fill"></i> Dashboard</a>
    <a href="listings.php" class="nav-link"><i class="bi bi-search"></i> Browse Rooms</a>
    <a href="profile_setup.php" class="nav-link"><i class="bi bi-person-circle"></i> Profile</a>
    <a href="#" class="nav-link text-danger mt-auto" data-bs-toggle="modal" data-bs-target="#logoutModal">
        <i class="bi bi-box-arrow-right"></i> Logout
    </a>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold m-0">Welcome, <?php echo htmlspecialchars($user['first_name']); ?></h3>
            <p class="text-secondary small">Manage your stay and payments.</p>
        </div>
        <img src="<?php echo $pp; ?>" style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-orange);">
    </div>

    <?php if ($pending_apps->num_rows > 0): ?>
    <h5 class="fw-bold text-warning mb-3"><i class="bi bi-hourglass-split"></i> Pending Requests</h5>
    <div class="row mb-4">
        <?php while($app = $pending_apps->fetch_assoc()): ?>
        <div class="col-md-6">
            <div class="stat-card border-warning border-opacity-25" style="border-color: rgba(255, 193, 7, 0.3) !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold m-0"><?php echo htmlspecialchars($app['title']); ?></h5>
                        <div class="text-secondary small mb-2">Room: <?php echo htmlspecialchars($app['room_name']); ?></div>
                        <span class="badge bg-warning text-dark">Waiting for Approval</span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#cancelModal<?php echo $app['app_id']; ?>">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="cancelModal<?php echo $app['app_id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold text-white"><i class="bi bi-x-circle-fill text-danger me-2"></i>Cancel Request</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-secondary">
                        Are you sure you want to cancel your application for <strong class="text-white"><?php echo htmlspecialchars($app['room_name']); ?></strong> at <strong class="text-white"><?php echo htmlspecialchars($app['title']); ?></strong>?
                        <br><br>
                        This action cannot be undone.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Request</button>
                        <a href="cancel_application.php?id=<?php echo $app['app_id']; ?>" class="btn btn-danger px-4 fw-bold">Yes, Cancel</a>
                    </div>
                </div>
            </div>
        </div>

        <?php endwhile; ?>
    </div>
    <?php endif; ?>

    <?php if ($has_rental): ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="stat-label">Next Rent Due</span>
                        <h2 class="stat-value <?php echo $status_class; ?>">
                            <?php echo $formatted_due_date; ?>
                        </h2>
                        <span class="badge bg-dark border border-secondary mt-2 text-secondary"><?php echo $due_status; ?></span>
                    </div>
                    <div class="text-end">
                        <button class="btn btn-warning btn-sm fw-bold rounded-pill px-3 mt-1" data-bs-toggle="modal" data-bs-target="#paymentModal">
                            <i class="bi bi-credit-card-2-back-fill me-1"></i> Pay Rent
                        </button>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <h5 class="fw-bold mb-3">Payment History</h5>
                <?php if (count($payments) > 0): ?>
                    <table class="history-table">
                        <thead><tr><th>Date Paid</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($payments as $pay): ?>
                            <tr>
                                <td><?php echo date("M d, Y", strtotime($pay['payment_date'])); ?></td>
                                <td class="fw-bold text-success">₱<?php echo number_format($pay['amount'], 2); ?></td>
                                <td>
                                    <?php $status = $pay['status']; 
                                    $badge = ($status == 'Confirmed') ? "bg-success text-white" : "bg-warning text-dark"; 
                                    if($status == 'Rejected') $badge = "bg-danger text-white"; ?>
                                    <span class="badge <?php echo $badge; ?> bg-opacity-75"><?php echo $status; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-secondary small fst-italic">No payments recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <span class="stat-label d-block text-start">Your Room</span>
                        <h4 class="m-0"><?php echo htmlspecialchars($active_rental['room_name']); ?></h4>
                        <small class="text-secondary"><?php echo htmlspecialchars($active_rental['house_name']); ?></small>
                    </div>
                </div>

                <h6 class="text-uppercase text-secondary fw-bold small mb-3">Landlord Contact</h6>
                <div class="d-flex align-items-center mb-3">
                    <img src="<?php echo !empty($active_rental['landlord_pic']) ? 'assets/uploads/'.$active_rental['landlord_pic'] : 'assets/default.jpg'; ?>" 
                         style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-right: 15px;">
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($active_rental['landlord_fname'] . ' ' . $active_rental['landlord_lname']); ?></div>
                        <div class="small text-secondary">Property Owner</div>
                    </div>
                </div>
                
                <div class="d-flex flex-column">
                    <?php if(!empty($active_rental['contact_phone'])): ?>
                        <a href="tel:<?php echo $active_rental['contact_phone']; ?>" class="contact-pill">
                            <i class="bi bi-telephone-fill text-success" style="width: 20px; text-align: center;"></i>
                            <span class="contact-text"><?php echo htmlspecialchars($active_rental['contact_phone']); ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if(!empty($active_rental['contact_facebook'])): ?>
                        <a href="https://facebook.com/<?php echo $active_rental['contact_facebook']; ?>" target="_blank" class="contact-pill">
                            <i class="bi bi-facebook text-primary" style="width: 20px; text-align: center;"></i>
                            <span class="contact-text"><?php echo htmlspecialchars($active_rental['contact_facebook']); ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if(!empty($active_rental['contact_email'])): ?>
                        <a href="mailto:<?php echo $active_rental['contact_email']; ?>" class="contact-pill">
                            <i class="bi bi-envelope-fill text-danger" style="width: 20px; text-align: center;"></i>
                            <span class="contact-text"><?php echo htmlspecialchars($active_rental['contact_email']); ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-5">
        <div class="room-details-container">
            <img src="<?php echo $room_img; ?>" class="room-hero-img">
            
            <div class="room-content-body">
                <div class="row g-5">
                    <div class="col-lg-8">
                        <div class="mb-4">
                            <h2 class="text-white fw-bold mb-1"><?php echo htmlspecialchars($active_rental['room_name']); ?></h2>
                            <h5 class="text-warning mb-3"><?php echo htmlspecialchars($active_rental['house_name']); ?></h5>
                            <p class="text-secondary small mb-2"><i class="bi bi-geo-alt-fill me-2"></i> <?php echo htmlspecialchars($active_rental['location']); ?></p>
                        </div>

                        <div class="mb-5">
                            <h6 class="text-uppercase text-secondary fw-bold small mb-3">About the Property</h6>
                            <p class="text-secondary" style="line-height: 1.7;"><?php echo $room_desc; ?></p>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <h6 class="text-uppercase text-secondary fw-bold small mb-3">Amenities Included</h6>
                                <div>
                                    <?php if(!empty($amenities)): foreach($amenities as $am): ?>
                                        <span class="amenity-pill"><i class="bi bi-check-lg"></i> <?php echo trim($am); ?></span>
                                    <?php endforeach; else: echo '<span class="text-secondary small">None listed.</span>'; endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <h6 class="text-uppercase text-secondary fw-bold small mb-3">Available Add-ons</h6>
                                <div>
                                    <?php if(!empty($addons)): foreach($addons as $ad): ?>
                                        <span class="addon-pill"><i class="bi bi-plus-lg"></i> <?php echo trim($ad); ?></span>
                                    <?php endforeach; else: echo '<span class="text-secondary small">None listed.</span>'; endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <h6 class="text-uppercase text-secondary fw-bold small mb-3">Room Occupants</h6>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="occupant-card">
                                    <img src="<?php echo $pp; ?>" class="occupant-img" style="border: 2px solid var(--accent-orange);">
                                    <div>
                                        <div class="fw-bold text-white"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                        <div class="small text-secondary"><?php echo htmlspecialchars($user['phone']); ?></div>
                                        <span class="badge bg-warning text-dark mt-1" style="font-size: 10px;">YOU</span>
                                    </div>
                                </div>
                                <?php foreach($roommates as $mate): 
                                    $m_pic = !empty($mate['profile_pic']) ? "assets/uploads/".$mate['profile_pic'] : "assets/default.jpg";
                                ?>
                                <div class="occupant-card">
                                    <img src="<?php echo $m_pic; ?>" class="occupant-img">
                                    <div>
                                        <div class="fw-bold text-white"><?php echo htmlspecialchars($mate['first_name'] . ' ' . $mate['last_name']); ?></div>
                                        <div class="small text-secondary"><?php echo htmlspecialchars($mate['phone']); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="stat-card border-warning" style="background: rgba(255, 144, 0, 0.05);">
                            <h5 class="fw-bold text-warning mb-4">Rental Details</h5>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom border-secondary">
                                <span class="text-secondary">Start Date</span>
                                <span class="fw-bold text-white"><?php echo date("M d, Y", strtotime($active_rental['start_date'])); ?></span>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom border-secondary">
                                <span class="text-secondary">Next Due</span>
                                <span class="fw-bold text-white"><?php echo $formatted_due_date; ?></span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom border-secondary">
                                <span class="text-secondary">Price per Head</span>
                                <span class="fw-bold text-white">₱<?php echo number_format($active_rental['shared_price']); ?></span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-secondary">Monthly Rent</span>
                                <span class="fw-bold fs-4 text-white">₱<?php echo number_format($active_rental['price']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-house-door fs-1 text-secondary d-block mb-3"></i>
            <h3>You are not renting any room.</h3>
            <p class="text-secondary">Browse our listings and find your next home.</p>
            <a href="listings.php" class="btn btn-warning mt-3 rounded-pill px-4 fw-bold">Find a Room</a>
        </div>
    <?php endif; ?>

</div>

<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Submit Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="submit_payment.php" method="POST">
                <div class="modal-body">
                    <?php if($is_overdue): ?>
                    <div class="alert alert-danger py-2 small fw-bold mb-3">
                        <i class="bi bi-exclamation-circle-fill me-1"></i> Note: You are paying after the due date.
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="small text-secondary mb-1">Amount Paid (₱)</label>
                        <input type="number" name="amount" class="form-control" placeholder="e.g. 3000" required step="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="small text-secondary mb-1">Date Paid</label>
                        <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_payment" class="btn btn-warning fw-bold">Submit Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Sign Out?</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-secondary">
        Are you sure you want to end your session?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="logout.php" class="btn btn-danger px-4">Logout</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>