<?php
/* ============================================
   LCR SYSTEM - MARRIAGE RECORDS HANDLER (PDO)
   Municipality of San Julian, Eastern Samar
   ============================================ */

require_once '../authentication/session.php';
require_once '../authentication/db_connect.php';

requireLogin();

header('Content-Type: application/json');

$action  = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];

// ── Audit Log Helper ──
function logAction($pdo, $user_id, $action) {
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$user_id, $action, $_SERVER['REMOTE_ADDR']]);
}

// ── Upload Helper ──
function handleUpload($upload_dir, $prefix) {
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    // File upload
    if (!empty($_FILES['document']['name'])) {
        $file    = $_FILES['document'];
        $allowed = ['jpg','jpeg','png','pdf'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) return ['error' => 'Invalid file type. Only JPG, PNG, PDF allowed.'];
        if ($file['size'] > 5 * 1024 * 1024) return ['error' => 'File size must not exceed 5MB.'];
        $filename = $prefix . time() . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            return ['path' => 'uploads/marriage/' . $filename];
        }
    }

    // Camera capture
    $captured = trim($_POST['captured_image'] ?? '');
    if (!empty($captured) && strpos($captured, 'data:image') === 0) {
        $img_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $captured));
        $filename = $prefix . 'cam_' . time() . '_' . uniqid() . '.jpg';
        file_put_contents($upload_dir . $filename, $img_data);
        return ['path' => 'uploads/marriage/' . $filename];
    }

    return ['path' => ''];
}

// ═══════════════════════════════════════
// GET SINGLE RECORD
// ═══════════════════════════════════════
if ($action === 'get') {
    $id   = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT m.*, u.full_name as added_by 
                           FROM marriage_records m 
                           LEFT JOIN users u ON m.created_by = u.id 
                           WHERE m.id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    echo $data
        ? json_encode(['status' => 'success', 'data' => $data])
        : json_encode(['status' => 'error', 'message' => 'Record not found.']);
    exit();
}

// ═══════════════════════════════════════
// ADD RECORD
// ═══════════════════════════════════════
if ($action === 'add') {
    $registry_number      = trim($_POST['registry_number']      ?? '');
    $date_registered      = trim($_POST['date_registered']      ?? '');
    $date_of_marriage     = trim($_POST['date_of_marriage']     ?? '');
    $place_of_marriage    = trim($_POST['place_of_marriage']    ?? '');
    $husband_first_name   = trim($_POST['husband_first_name']   ?? '');
    $husband_middle_name  = trim($_POST['husband_middle_name']  ?? '');
    $husband_last_name    = trim($_POST['husband_last_name']    ?? '');
    $husband_date_of_birth= trim($_POST['husband_date_of_birth']?? '');
    $wife_first_name      = trim($_POST['wife_first_name']      ?? '');
    $wife_middle_name     = trim($_POST['wife_middle_name']     ?? '');
    $wife_last_name       = trim($_POST['wife_last_name']       ?? '');
    $wife_date_of_birth   = trim($_POST['wife_date_of_birth']   ?? '');
    $remarks              = trim($_POST['remarks']              ?? '');

    if (empty($registry_number) || empty($date_of_marriage) || empty($place_of_marriage) ||
        empty($husband_first_name) || empty($husband_last_name) ||
        empty($wife_first_name)   || empty($wife_last_name)    || empty($date_registered)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
        exit();
    }

    // Check duplicate
    $chk = $pdo->prepare("SELECT id FROM marriage_records WHERE registry_number = ?");
    $chk->execute([$registry_number]);
    if ($chk->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Registry number already exists.']);
        exit();
    }

    // Handle upload
    $upload = handleUpload('../uploads/marriage/', 'marriage_');
    if (isset($upload['error'])) { echo json_encode(['status' => 'error', 'message' => $upload['error']]); exit(); }
    $document_path = $upload['path'];

    $stmt = $pdo->prepare("INSERT INTO marriage_records 
        (registry_number, husband_first_name, husband_middle_name, husband_last_name, husband_date_of_birth,
         wife_first_name, wife_middle_name, wife_last_name, wife_date_of_birth,
         date_of_marriage, place_of_marriage, date_registered, document_path, remarks, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    if ($stmt->execute([
        $registry_number,
        $husband_first_name, $husband_middle_name, $husband_last_name, $husband_date_of_birth ?: null,
        $wife_first_name,    $wife_middle_name,    $wife_last_name,    $wife_date_of_birth    ?: null,
        $date_of_marriage, $place_of_marriage, $date_registered, $document_path, $remarks, $user_id
    ])) {
        logAction($pdo, $user_id, "Added marriage record: $registry_number");
        echo json_encode(['status' => 'success', 'message' => 'Marriage record added successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save record.']);
    }
    exit();
}

// ═══════════════════════════════════════
// EDIT RECORD
// ═══════════════════════════════════════
if ($action === 'edit') {
    $id                   = intval($_POST['record_id']           ?? 0);
    $registry_number      = trim($_POST['registry_number']       ?? '');
    $date_registered      = trim($_POST['date_registered']       ?? '');
    $date_of_marriage     = trim($_POST['date_of_marriage']      ?? '');
    $place_of_marriage    = trim($_POST['place_of_marriage']     ?? '');
    $husband_first_name   = trim($_POST['husband_first_name']    ?? '');
    $husband_middle_name  = trim($_POST['husband_middle_name']   ?? '');
    $husband_last_name    = trim($_POST['husband_last_name']     ?? '');
    $husband_date_of_birth= trim($_POST['husband_date_of_birth'] ?? '');
    $wife_first_name      = trim($_POST['wife_first_name']       ?? '');
    $wife_middle_name     = trim($_POST['wife_middle_name']      ?? '');
    $wife_last_name       = trim($_POST['wife_last_name']        ?? '');
    $wife_date_of_birth   = trim($_POST['wife_date_of_birth']    ?? '');
    $remarks              = trim($_POST['remarks']               ?? '');

    if ($id <= 0) { echo json_encode(['status' => 'error', 'message' => 'Invalid record.']); exit(); }

    // Check duplicate excluding current
    $chk = $pdo->prepare("SELECT id FROM marriage_records WHERE registry_number = ? AND id != ?");
    $chk->execute([$registry_number, $id]);
    if ($chk->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Registry number already exists.']);
        exit();
    }

    // Get existing document
    $ex = $pdo->prepare("SELECT document_path FROM marriage_records WHERE id = ?");
    $ex->execute([$id]);
    $existing      = $ex->fetch();
    $document_path = $existing['document_path'] ?? '';

    // Handle new upload
    $upload = handleUpload('../uploads/marriage/', 'marriage_');
    if (isset($upload['error'])) { echo json_encode(['status' => 'error', 'message' => $upload['error']]); exit(); }
    if (!empty($upload['path'])) {
        if ($document_path && file_exists('../' . $document_path)) unlink('../' . $document_path);
        $document_path = $upload['path'];
    }

    $stmt = $pdo->prepare("UPDATE marriage_records SET
        registry_number = ?, husband_first_name = ?, husband_middle_name = ?, husband_last_name = ?, husband_date_of_birth = ?,
        wife_first_name = ?, wife_middle_name = ?, wife_last_name = ?, wife_date_of_birth = ?,
        date_of_marriage = ?, place_of_marriage = ?, date_registered = ?, document_path = ?, remarks = ?, updated_at = NOW()
        WHERE id = ?");

    if ($stmt->execute([
        $registry_number,
        $husband_first_name, $husband_middle_name, $husband_last_name, $husband_date_of_birth ?: null,
        $wife_first_name,    $wife_middle_name,    $wife_last_name,    $wife_date_of_birth    ?: null,
        $date_of_marriage, $place_of_marriage, $date_registered, $document_path, $remarks, $id
    ])) {
        logAction($pdo, $user_id, "Updated marriage record: $registry_number");
        echo json_encode(['status' => 'success', 'message' => 'Marriage record updated successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update record.']);
    }
    exit();
}

// ═══════════════════════════════════════
// DELETE RECORD
// ═══════════════════════════════════════
if ($action === 'delete') {
    if ($role !== 'admin') { echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']); exit(); }

    $id  = intval($_POST['record_id'] ?? 0);
    $chk = $pdo->prepare("SELECT registry_number, document_path FROM marriage_records WHERE id = ?");
    $chk->execute([$id]);
    $rec = $chk->fetch();

    if (!$rec) { echo json_encode(['status' => 'error', 'message' => 'Record not found.']); exit(); }

    if (!empty($rec['document_path']) && file_exists('../' . $rec['document_path'])) {
        unlink('../' . $rec['document_path']);
    }

    $stmt = $pdo->prepare("DELETE FROM marriage_records WHERE id = ?");
    if ($stmt->execute([$id])) {
        logAction($pdo, $user_id, "Deleted marriage record: " . $rec['registry_number']);
        echo json_encode(['status' => 'success', 'message' => 'Marriage record deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete record.']);
    }
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
?>