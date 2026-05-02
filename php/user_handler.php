<?php
/* ============================================
   LCR SYSTEM - USER MANAGEMENT HANDLER (PDO)
   Municipality of San Julian, Eastern Samar
   ============================================ */

require_once '../authentication/session.php';
require_once '../authentication/db_connect.php';

requireLogin();
requireAdmin();

header('Content-Type: application/json');

$action      = $_POST['action'] ?? '';
$current_uid = $_SESSION['user_id'];

// ── Audit Log Helper ──
function logAction($pdo, $user_id, $action) {
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$user_id, $action, $_SERVER['REMOTE_ADDR']]);
}

// ═══════════════════════════════════════
// ADD USER
// ═══════════════════════════════════════
if ($action === 'add') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $role      = trim($_POST['role']      ?? 'staff');
    $password  = trim($_POST['password']  ?? '');

    if (empty($full_name) || empty($username) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Full name, username, and password are required.']);
        exit();
    }

    if (strlen($password) < 8) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters.']);
        exit();
    }

    if (!in_array($role, ['admin', 'staff'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid role.']);
        exit();
    }

    // Check duplicate username
    $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $chk->execute([$username]);
    if ($chk->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Username already exists.']);
        exit();
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, role, password, status, created_at)
                           VALUES (?, ?, ?, ?, ?, 'active', NOW())");

    if ($stmt->execute([$full_name, $username, $email ?: null, $role, $hashed])) {
        logAction($pdo, $current_uid, "Added user account: $username ($role)");
        echo json_encode(['status' => 'success', 'message' => 'User account created successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create account.']);
    }
    exit();
}

// ═══════════════════════════════════════
// EDIT USER
// ═══════════════════════════════════════
if ($action === 'edit') {
    $id        = intval($_POST['user_id']  ?? 0);
    $full_name = trim($_POST['full_name']  ?? '');
    $username  = trim($_POST['username']   ?? '');
    $email     = trim($_POST['email']      ?? '');
    $role      = trim($_POST['role']       ?? 'staff');

    if ($id <= 0 || empty($full_name) || empty($username)) {
        echo json_encode(['status' => 'error', 'message' => 'Full name and username are required.']);
        exit();
    }

    if (!in_array($role, ['admin', 'staff'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid role.']);
        exit();
    }

    // Check duplicate username excluding current
    $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $chk->execute([$username, $id]);
    if ($chk->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Username already exists.']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, role = ?, updated_at = NOW() WHERE id = ?");

    if ($stmt->execute([$full_name, $username, $email ?: null, $role, $id])) {
        logAction($pdo, $current_uid, "Updated user account: $username");
        echo json_encode(['status' => 'success', 'message' => 'User account updated successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update account.']);
    }
    exit();
}

// ═══════════════════════════════════════
// CHANGE PASSWORD
// ═══════════════════════════════════════
if ($action === 'change_password') {
    $id          = intval($_POST['user_id']      ?? 0);
    $new_password = trim($_POST['new_password']  ?? '');

    if ($id <= 0 || empty($new_password)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
        exit();
    }

    if (strlen($new_password) < 8) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters.']);
        exit();
    }

    $hashed = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt   = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");

    if ($stmt->execute([$hashed, $id])) {
        // Get username for log
        $u = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $u->execute([$id]);
        $user = $u->fetch();
        logAction($pdo, $current_uid, "Changed password for user: " . ($user['username'] ?? $id));
        echo json_encode(['status' => 'success', 'message' => 'Password changed successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to change password.']);
    }
    exit();
}

// ═══════════════════════════════════════
// TOGGLE STATUS (ACTIVATE / DEACTIVATE)
// ═══════════════════════════════════════
if ($action === 'toggle_status') {
    $id         = intval($_POST['user_id'] ?? 0);
    $new_status = trim($_POST['status']    ?? '');

    if ($id <= 0 || !in_array($new_status, ['active', 'inactive'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
        exit();
    }

    // Prevent admin from deactivating own account
    if ($id === $current_uid) {
        echo json_encode(['status' => 'error', 'message' => 'You cannot deactivate your own account.']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");

    if ($stmt->execute([$new_status, $id])) {
        $u = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $u->execute([$id]);
        $user = $u->fetch();
        $label = $new_status === 'active' ? 'Activated' : 'Deactivated';
        logAction($pdo, $current_uid, "$label user account: " . ($user['username'] ?? $id));
        echo json_encode(['status' => 'success', 'message' => "Account {$label} successfully."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update status.']);
    }
    exit();
}

// ═══════════════════════════════════════
// DELETE USER
// ═══════════════════════════════════════
if ($action === 'delete') {
    $id = intval($_POST['user_id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
        exit();
    }

    // Prevent admin from deleting own account
    if ($id === $current_uid) {
        echo json_encode(['status' => 'error', 'message' => 'You cannot delete your own account.']);
        exit();
    }

    // Get username before delete for log
    $u = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $u->execute([$id]);
    $user = $u->fetch();

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'User not found.']);
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");

    if ($stmt->execute([$id])) {
        logAction($pdo, $current_uid, "Deleted user account: " . $user['username']);
        echo json_encode(['status' => 'success', 'message' => 'User account deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete account.']);
    }
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
?>