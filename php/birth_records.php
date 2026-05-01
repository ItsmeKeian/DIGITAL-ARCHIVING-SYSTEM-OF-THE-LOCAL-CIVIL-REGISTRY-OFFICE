<?php
/* ============================================
   LCR SYSTEM - BIRTH RECORDS HANDLER
   Municipality of San Julian, Eastern Samar
   ============================================ */

require_once '../authentication/session.php';
require_once '../authentication/db_connect.php';

requireLogin();

header('Content-Type: application/json');

$action  = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];

// ═══════════════════════════════════════
// GET SINGLE RECORD
// ═══════════════════════════════════════
if ($action === 'get') {
    $id   = intval($_GET['id'] ?? 0);
    $stmt = mysqli_prepare($conn, "SELECT b.*, u.full_name as added_by FROM birth_records b LEFT JOIN users u ON b.created_by = u.id WHERE b.id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res  = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($res);

    if ($data) {
        echo json_encode(['status' => 'success', 'data' => $data]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Record not found.']);
    }
    exit();
}

// ═══════════════════════════════════════
// ADD RECORD
// ═══════════════════════════════════════
if ($action === 'add') {
    $registry_number   = trim($_POST['registry_number']   ?? '');
    $child_first_name  = trim($_POST['child_first_name']  ?? '');
    $child_middle_name = trim($_POST['child_middle_name'] ?? '');
    $child_last_name   = trim($_POST['child_last_name']   ?? '');
    $child_sex         = trim($_POST['child_sex']         ?? '');
    $date_of_birth     = trim($_POST['date_of_birth']     ?? '');
    $place_of_birth    = trim($_POST['place_of_birth']    ?? '');
    $father_name       = trim($_POST['father_name']       ?? '');
    $mother_name       = trim($_POST['mother_name']       ?? '');
    $date_registered   = trim($_POST['date_registered']   ?? '');
    $remarks           = trim($_POST['remarks']           ?? '');
    $captured_image    = trim($_POST['captured_image']    ?? '');

    // Validate required fields
    if (empty($registry_number) || empty($child_first_name) || empty($child_last_name) ||
        empty($child_sex) || empty($date_of_birth) || empty($place_of_birth) || empty($date_registered)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
        exit();
    }

    // Check duplicate registry number
    $chk = mysqli_prepare($conn, "SELECT id FROM birth_records WHERE registry_number = ?");
    mysqli_stmt_bind_param($chk, 's', $registry_number);
    mysqli_stmt_execute($chk);
    mysqli_stmt_store_result($chk);
    if (mysqli_stmt_num_rows($chk) > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Registry number already exists.']);
        exit();
    }

    // Handle document upload
    $document_path = '';
    $upload_dir    = '../uploads/birth/';

    // From file upload
    if (!empty($_FILES['document']['name'])) {
        $file      = $_FILES['document'];
        $allowed   = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $max_size  = 5 * 1024 * 1024;

        if (!in_array($ext, $allowed)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, PDF allowed.']);
            exit();
        }
        if ($file['size'] > $max_size) {
            echo json_encode(['status' => 'error', 'message' => 'File size must not exceed 5MB.']);
            exit();
        }

        $filename      = 'birth_' . time() . '_' . uniqid() . '.' . $ext;
        $full_path     = $upload_dir . $filename;
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            $document_path = 'uploads/birth/' . $filename;
        }
    }
    // From camera capture (base64)
    elseif (!empty($captured_image) && strpos($captured_image, 'data:image') === 0) {
        $img_data  = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $captured_image));
        $filename  = 'birth_cam_' . time() . '_' . uniqid() . '.jpg';
        $full_path = $upload_dir . $filename;
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        file_put_contents($full_path, $img_data);
        $document_path = 'uploads/birth/' . $filename;
    }

    // Insert record
    $sql  = "INSERT INTO birth_records 
             (registry_number, child_first_name, child_middle_name, child_last_name, child_sex, 
              date_of_birth, place_of_birth, father_name, mother_name, date_registered, 
              document_path, remarks, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssssssssssssi',
        $registry_number, $child_first_name, $child_middle_name, $child_last_name,
        $child_sex, $date_of_birth, $place_of_birth, $father_name, $mother_name,
        $date_registered, $document_path, $remarks, $user_id
    );

    if (mysqli_stmt_execute($stmt)) {
        // Audit log
        logAction($conn, $user_id, "Added birth record: $registry_number");
        echo json_encode(['status' => 'success', 'message' => 'Birth record added successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save record. Please try again.']);
    }
    exit();
}

// ═══════════════════════════════════════
// EDIT RECORD
// ═══════════════════════════════════════
if ($action === 'edit') {
    $id                = intval($_POST['record_id']        ?? 0);
    $registry_number   = trim($_POST['registry_number']    ?? '');
    $child_first_name  = trim($_POST['child_first_name']   ?? '');
    $child_middle_name = trim($_POST['child_middle_name']  ?? '');
    $child_last_name   = trim($_POST['child_last_name']    ?? '');
    $child_sex         = trim($_POST['child_sex']          ?? '');
    $date_of_birth     = trim($_POST['date_of_birth']      ?? '');
    $place_of_birth    = trim($_POST['place_of_birth']     ?? '');
    $father_name       = trim($_POST['father_name']        ?? '');
    $mother_name       = trim($_POST['mother_name']        ?? '');
    $date_registered   = trim($_POST['date_registered']    ?? '');
    $remarks           = trim($_POST['remarks']            ?? '');
    $captured_image    = trim($_POST['captured_image']     ?? '');

    if ($id <= 0) { echo json_encode(['status' => 'error', 'message' => 'Invalid record.']); exit(); }

    // Check duplicate registry number (excluding current record)
    $chk = mysqli_prepare($conn, "SELECT id FROM birth_records WHERE registry_number = ? AND id != ?");
    mysqli_stmt_bind_param($chk, 'si', $registry_number, $id);
    mysqli_stmt_execute($chk);
    mysqli_stmt_store_result($chk);
    if (mysqli_stmt_num_rows($chk) > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Registry number already exists.']);
        exit();
    }

    // Get existing document path
    $existing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT document_path FROM birth_records WHERE id = $id"));
    $document_path = $existing['document_path'] ?? '';
    $upload_dir    = '../uploads/birth/';

    // New file uploaded
    if (!empty($_FILES['document']['name'])) {
        $file    = $_FILES['document'];
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
            $filename  = 'birth_' . time() . '_' . uniqid() . '.' . $ext;
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                // Delete old file
                if ($document_path && file_exists('../' . $document_path)) unlink('../' . $document_path);
                $document_path = 'uploads/birth/' . $filename;
            }
        }
    }
    // Camera capture
    elseif (!empty($captured_image) && strpos($captured_image, 'data:image') === 0) {
        $img_data  = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $captured_image));
        $filename  = 'birth_cam_' . time() . '_' . uniqid() . '.jpg';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        file_put_contents($upload_dir . $filename, $img_data);
        if ($document_path && file_exists('../' . $document_path)) unlink('../' . $document_path);
        $document_path = 'uploads/birth/' . $filename;
    }

    $sql  = "UPDATE birth_records SET 
             registry_number=?, child_first_name=?, child_middle_name=?, child_last_name=?,
             child_sex=?, date_of_birth=?, place_of_birth=?, father_name=?, mother_name=?,
             date_registered=?, document_path=?, remarks=?, updated_at=NOW()
             WHERE id=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssssssssssssi',
        $registry_number, $child_first_name, $child_middle_name, $child_last_name,
        $child_sex, $date_of_birth, $place_of_birth, $father_name, $mother_name,
        $date_registered, $document_path, $remarks, $id
    );

    if (mysqli_stmt_execute($stmt)) {
        logAction($conn, $user_id, "Updated birth record: $registry_number");
        echo json_encode(['status' => 'success', 'message' => 'Birth record updated successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update record.']);
    }
    exit();
}

// ═══════════════════════════════════════
// DELETE RECORD
// ═══════════════════════════════════════
if ($action === 'delete') {
    if ($role !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
        exit();
    }

    $id  = intval($_POST['record_id'] ?? 0);
    $rec = mysqli_fetch_assoc(mysqli_query($conn, "SELECT registry_number, document_path FROM birth_records WHERE id = $id"));

    if (!$rec) { echo json_encode(['status' => 'error', 'message' => 'Record not found.']); exit(); }

    // Delete document file
    if (!empty($rec['document_path']) && file_exists('../' . $rec['document_path'])) {
        unlink('../' . $rec['document_path']);
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM birth_records WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);

    if (mysqli_stmt_execute($stmt)) {
        logAction($conn, $user_id, "Deleted birth record: " . $rec['registry_number']);
        echo json_encode(['status' => 'success', 'message' => 'Birth record deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete record.']);
    }
    exit();
}

// ═══════════════════════════════════════
// AUDIT LOG HELPER
// ═══════════════════════════════════════
function logAction($conn, $user_id, $action) {
    $ip   = $_SERVER['REMOTE_ADDR'];
    $stmt = mysqli_prepare($conn, "INSERT INTO audit_logs (user_id, action, ip_address, created_at) VALUES (?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, 'iss', $user_id, $action, $ip);
    mysqli_stmt_execute($stmt);
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
?>