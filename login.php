<?php
/* ============================================
   LCR DIGITAL ARCHIVING SYSTEM - LOGIN HANDLER
   Municipality of San Julian, Eastern Samar
   ============================================ */

require_once 'authentication/session.php';
require_once 'authentication/db_connect.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

// Sanitize inputs
$username = trim(mysqli_real_escape_string($conn, $_POST['username'] ?? ''));
$password = trim($_POST['password'] ?? '');

// Validate inputs
if (empty($username) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Username and password are required.']);
    exit();
}

// Query the database
$sql    = "SELECT id, username, password, full_name, role, status FROM users WHERE username = ? LIMIT 1";
$stmt   = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($user = mysqli_fetch_assoc($result)) {

    // Check if account is active
    if ($user['status'] !== 'active') {
        echo json_encode(['status' => 'error', 'message' => 'Your account has been deactivated. Please contact the administrator.']);
        exit();
    }

    // Verify password
    if (password_verify($password, $user['password'])) {

        // Set session variables
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];

        // Log the login activity
        $user_id    = $user['id'];
        $action     = 'Logged in';
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $log_sql    = "INSERT INTO audit_logs (user_id, action, ip_address, created_at) VALUES (?, ?, ?, NOW())";
        $log_stmt   = mysqli_prepare($conn, $log_sql);
        mysqli_stmt_bind_param($log_stmt, 'iss', $user_id, $action, $ip_address);
        mysqli_stmt_execute($log_stmt);

        echo json_encode([
            'status'   => 'success',
            'message'  => 'Login successful.',
            'redirect' => 'dashboard.php'
        ]);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Incorrect username or password.']);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Incorrect username or password.']);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>