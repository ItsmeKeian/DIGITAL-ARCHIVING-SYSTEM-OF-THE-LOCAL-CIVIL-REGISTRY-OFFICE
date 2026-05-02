<?php
/* ============================================
   LCR SYSTEM - DEATH RECORDS HANDLER (PDO)
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

    if (!empty($_FILES['document']['name'])) {
        $file    = $_FILES['document'];
        $allowed = ['jpg','jpeg','png','pdf'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) return ['error' => 'Invalid file type. Only JPG, PNG, PDF allowed.'];
        if ($file['size'] > 5 * 1024 * 1024) return ['error' => 'File size must not exceed 5MB.'];
        $filename = $prefix . time() . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            return ['path' => 'uploads/death/' . $filename];
        }
    }

    $captured = trim($_POST['captured_image'] ?? '');
    if (!empty($captured) && strpos($captured, 'data:image') === 0) {
        $img_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $captured));
        $filename = $prefix . 'cam_' . time() . '_' . uniqid() . '.jpg';
        file_put_contents($upload_dir . $filename, $img_data);
        return ['path' => 'uploads/death/' . $filename];
    }

    return ['path' => ''];
}

// ═══════════════════════════════════════
// GET SINGLE RECORD
// ═══════════════════════════════════════
if ($action === 'get') {
    $id   = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT d.*, u.full_name as added_by 
                           FROM death_records d 
                           LEFT JOIN users u ON d.created_by = u.id 
                           WHERE d.id = ?");
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
    $registry_number    = trim($_POST['registry_number']    ?? '');
    $date_registered    = trim($_POST['date_registered']    ?? '');
    $deceased_first_name  = trim($_POST['deceased_first_name']  ?? '');
    $deceased_middle_name = trim($_POST['deceased_middle_name'] ?? '');
    $deceased_last_name   = trim($_POST['deceased_last_name']   ?? '');
    $deceased_sex         = trim($_POST['deceased_sex']         ?? '');
    $date_of_death        = trim($_POST['date_of_death']        ?? '');
    $place_of_death       = trim($_POST['place_of_death']       ?? '');
    $cause_of_death       = trim($_POST['cause_of_death']       ?? '');
    $remarks              = trim($_POST['remarks']              ?? '');

    if (empty($registry_number) || empty($deceased_first_name) || empty($deceased_last_name) ||
        empty($deceased_sex) || empty($date_of_death) || empty($place_of_death) || empty($date_registered)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
        exit();
    }

    // Check duplicate
    $chk = $pdo->prepare("SELECT id FROM death_records WHERE registry_number = ?");
    $chk->execute([$registry_number]);
    if ($chk->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Registry number already exists.']);
        exit();
    }

    // Handle upload
    $upload = handleUpload('../uploads/death/', 'death_');
    if (isset($upload['error'])) { echo json_encode(['status' => 'error', 'message' => $upload['error']]); exit(); }
    $document_path = $upload['path'];

    $stmt = $pdo->prepare("INSERT INTO death_records 
        (registry_number, deceased_first_name, deceased_middle_name, deceased_last_name, deceased_sex,
         date_of_death, place_of_death, cause_of_death, date_registered, document_path, remarks, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    if ($stmt->execute([
        $registry_number,
        $deceased_first_name, $deceased_middle_name, $deceased_last_name, $deceased_sex,
        $date_of_death, $place_of_death, $cause_of_death ?: null,
        $date_registered, $document_path, $remarks, $user_id
    ])) {
        logAction($pdo, $user_id, "Added death record: $registry_number");
        echo json_encode(['status' => 'success', 'message' => 'Death record added successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save record.']);
    }
    exit();
}

// ═══════════════════════════════════════
// EDIT RECORD
// ═══════════════════════════════════════
if ($action === 'edit') {
    $id                   = intval($_POST['record_id']          ?? 0);
    $registry_number      = trim($_POST['registry_number']      ?? '');
    $date_registered      = trim($_POST['date_registered']      ?? '');
    $deceased_first_name  = trim($_POST['deceased_first_name']  ?? '');
    $deceased_middle_name = trim($_POST['deceased_middle_name'] ?? '');
    $deceased_last_name   = trim($_POST['deceased_last_name']   ?? '');
    $deceased_sex         = trim($_POST['deceased_sex']         ?? '');
    $date_of_death        = trim($_POST['date_of_death']        ?? '');
    $place_of_death       = trim($_POST['place_of_death']       ?? '');
    $cause_of_death       = trim($_POST['cause_of_death']       ?? '');
    $remarks              = trim($_POST['remarks']              ?? '');

    if ($id <= 0) { echo json_encode(['status' => 'error', 'message' => 'Invalid record.']); exit(); }

    // Check duplicate excluding current
    $chk = $pdo->prepare("SELECT id FROM death_records WHERE registry_number = ? AND id != ?");
    $chk->execute([$registry_number, $id]);
    if ($chk->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Registry number already exists.']);
        exit();
    }

    // Get existing document
    $ex = $pdo->prepare("SELECT document_path FROM death_records WHERE id = ?");
    $ex->execute([$id]);
    $existing      = $ex->fetch();
    $document_path = $existing['document_path'] ?? '';

    // Handle new upload
    $upload = handleUpload('../uploads/death/', 'death_');
    if (isset($upload['error'])) { echo json_encode(['status' => 'error', 'message' => $upload['error']]); exit(); }
    if (!empty($upload['path'])) {
        if ($document_path && file_exists('../' . $document_path)) unlink('../' . $document_path);
        $document_path = $upload['path'];
    }

    $stmt = $pdo->prepare("UPDATE death_records SET
        registry_number = ?, deceased_first_name = ?, deceased_middle_name = ?, deceased_last_name = ?, deceased_sex = ?,
        date_of_death = ?, place_of_death = ?, cause_of_death = ?, date_registered = ?,
        document_path = ?, remarks = ?, updated_at = NOW()
        WHERE id = ?");

    if ($stmt->execute([
        $registry_number,
        $deceased_first_name, $deceased_middle_name, $deceased_last_name, $deceased_sex,
        $date_of_death, $place_of_death, $cause_of_death ?: null,
        $date_registered, $document_path, $remarks, $id
    ])) {
        logAction($pdo, $user_id, "Updated death record: $registry_number");
        echo json_encode(['status' => 'success', 'message' => 'Death record updated successfully.']);
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
    $chk = $pdo->prepare("SELECT registry_number, document_path FROM death_records WHERE id = ?");
    $chk->execute([$id]);
    $rec = $chk->fetch();

    if (!$rec) { echo json_encode(['status' => 'error', 'message' => 'Record not found.']); exit(); }

    if (!empty($rec['document_path']) && file_exists('../' . $rec['document_path'])) {
        unlink('../' . $rec['document_path']);
    }

    $stmt = $pdo->prepare("DELETE FROM death_records WHERE id = ?");
    if ($stmt->execute([$id])) {
        logAction($pdo, $user_id, "Deleted death record: " . $rec['registry_number']);
        echo json_encode(['status' => 'success', 'message' => 'Death record deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete record.']);
    }
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
?>