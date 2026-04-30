<?php
/* ============================================
   LCR SYSTEM - LOGOUT HANDLER
   Municipality of San Julian, Eastern Samar
   ============================================ */

require_once 'authentication/session.php';
require_once 'authentication/db_connect.php';

if (isLoggedIn()) {
    // Log the logout activity
    $user_id    = $_SESSION['user_id'];
    $action     = 'Logged out';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $log_sql    = "INSERT INTO audit_logs (user_id, action, ip_address, created_at) VALUES (?, ?, ?, NOW())";
    $log_stmt   = mysqli_prepare($conn, $log_sql);
    mysqli_stmt_bind_param($log_stmt, 'iss', $user_id, $action, $ip_address);
    mysqli_stmt_execute($log_stmt);
}

// Destroy session
session_unset();
session_destroy();

echo json_encode(['status' => 'success']);
?>