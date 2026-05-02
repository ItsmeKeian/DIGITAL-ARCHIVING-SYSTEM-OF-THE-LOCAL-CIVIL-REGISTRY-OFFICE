<?php


require_once 'authentication/session.php';
require_once 'authentication/db_connect.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

// Get inputs — no need for real_escape_string in PDO
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

// Validate inputs
if (empty($username) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Username and password are required.']);
    exit();
}

// Query the database
$stmt = $pdo->prepare("SELECT id, username, password, full_name, role, status FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user) {

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
        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address, created_at) VALUES (?, ?, ?, NOW())");
        $log->execute([$user['id'], 'Logged in', $_SERVER['REMOTE_ADDR']]);

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
?>