<?php
session_start();
include 'db.php';

// 1. SECURITY CHECK (Only Super Admin is allowed)
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'Admin') { 
    header("Location: login.php"); 
    exit(); 
}

// 2. CHECK WHAT WE ARE DELETING
if (isset($_GET['type']) && isset($_GET['id'])) {
    $type = $_GET['type'];
    $id = intval($_GET['id']);

    if ($type === 'user') {
        // --- DELETE USER ---
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            // Redirect back to the users list
            header("Location: admin_users.php?role=All&msg=User deleted successfully");
            exit();
        }

    } elseif ($type === 'property') {
        // --- DELETE PROPERTY ---
        
        // Safety Step: Delete all rooms connected to this property first
        $del_rooms = $conn->prepare("DELETE FROM room_units WHERE property_id = ?");
        $del_rooms->bind_param("i", $id);
        $del_rooms->execute();
        $del_rooms->close();

        // Now delete the main property
        $stmt = $conn->prepare("DELETE FROM properties WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            // Redirect back to the properties list
            header("Location: admin_properties.php?msg=Property and all connected rooms deleted successfully");
            exit();
        }
    }
}

// Fallback redirect if something goes wrong or someone tries to access this page directly
header("Location: dashboard_admin.php");
exit();
?>