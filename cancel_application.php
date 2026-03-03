<?php
session_start();
include 'db.php';

// Security check
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'Tenant') {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $app_id = intval($_GET['id']);
    $username = $_SESSION['user'];

    // BULLETPROOF: Get the exact tenant ID from the database using the session username
    $user_query = $conn->query("SELECT id FROM users WHERE username='$username'");
    $user_data = $user_query->fetch_assoc();
    $tenant_id = $user_data['id'];

    // STRICT DELETE: Only delete if it belongs to the logged-in user AND is still Pending
    $stmt = $conn->prepare("DELETE FROM applications WHERE id = ? AND tenant_id = ? AND status = 'Pending'");
    $stmt->bind_param("ii", $app_id, $tenant_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: dashboard_tenant.php?msg=Request Cancelled");
    } else {
        $stmt->close();
        header("Location: dashboard_tenant.php?error=Could not cancel request");
    }
} else {
    header("Location: dashboard_tenant.php");
}
?>