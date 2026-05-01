<?php
require_once 'authentication/session.php';
require_once 'authentication/db_connect.php';

if (isLoggedIn()) {
    // Log the logout activity
    $user_id    = $_SESSION['user_id'];
    $action     = 'Logged out';
    $ip_address = $_SERVER['REMOTE_ADDR'];

    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$user_id, $action, $ip_address]);
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login page
header('Location: index.php');
exit();
?>