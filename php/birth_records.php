<?php


require_once '../authentication/session.php';
require_once '../authentication/db_connect.php';

requireLogin();

header('Content-Type: application/json');

$action  = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];

// ═══════════════════════════════════════
// AUDIT LOG HELPER
// ═══════════════════════════════════════
function logAction($pdo, $user_id, $action) {
    $ip   = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$user_id, $action, $ip]);
}

// ═══════════════════════════════════════
// GET SINGLE RECORD
// ═══════════════════════════════════════
if ($action === 'get') {
    $id   = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT b.*, u.full_name as added_by 
                           FROM birth_records b 
                           LEFT JOIN users u ON b.created_by = u.id 
                           WHERE b.id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();

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
    $chk = $pdo->prepare("SELECT id FROM birth_records WHERE registry_number = ?");
    $chk->execute([$registry_number]);
    if ($chk->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Registry number already exists.']);
        exit();
    }

    // Handle document upload
    $document_path = '';
    $upload_dir    = '../uploads/birth/';

    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    // From file upload
    if (!empty($_FILES['document']['name'])) {
        $file     = $_FILES['document'];
        $allowed  = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $max_size = 5 * 1024 * 1024;

        if (!in_array($ext, $allowed)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, PDF allowed.']);
            exit();
        }
        if ($file['size'] > $max_size) {
            echo json_encode(['status' => 'error', 'message' => 'File size must not exceed 5MB.']);
            exit();
        }

        $filename = 'birth_' . time() . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            $document_path = 'uploads/birth/' . $filename;
        }
    }
    // From camera capture (base64)
    elseif (!empty($captured_image) && strpos($captured_image, 'data:image') === 0) {
        $img_data      = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $captured_image));
        $filename      = 'birth_cam_' . time() . '_' . uniqid() . '.jpg';
        file_put_contents($upload_dir . $filename, $img_data);
        $document_path = 'uploads/birth/' . $filename;
    }

    // Insert record
    $stmt = $pdo->prepare("INSERT INTO birth_records 
        (registry_number, child_first_name, child_middle_name, child_last_name, child_sex, 
         date_of_birth, place_of_birth, father_name, mother_name, date_registered, 
         document_path, remarks, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    if ($stmt->execute([
        $registry_number, $child_first_name, $child_middle_name, $child_last_name,
        $child_sex, $date_of_birth, $place_of_birth, $father_name, $mother_name,
        $date_registered, $document_path, $remarks, $user_id
    ])) {
        logAction($pdo, $user_id, "Added birth record: $registry_number");
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

    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid record.']);
        exit();
    }

    // Check duplicate registry number excluding current record
    $chk = $pdo->prepare("SELECT id FROM birth_records WHERE registry_number = ? AND id != ?");
    $chk->execute([$registry_number, $id]);
    if ($chk->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Registry number already exists.']);
        exit();
    }

    // Get existing document path
    $existing      = $pdo->prepare("SELECT document_path FROM birth_records WHERE id = ?");
    $existing->execute([$id]);
    $existingData  = $existing->fetch();
    $document_path = $existingData['document_path'] ?? '';
    $upload_dir    = '../uploads/birth/';

    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    // New file uploaded
    if (!empty($_FILES['document']['name'])) {
        $file    = $_FILES['document'];
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
            $filename = 'birth_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                // Delete old file
                if ($document_path && file_exists('../' . $document_path)) {
                    unlink('../' . $document_path);
                }
                $document_path = 'uploads/birth/' . $filename;
            }
        }
    }
    // Camera capture
    elseif (!empty($captured_image) && strpos($captured_image, 'data:image') === 0) {
        $img_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $captured_image));
        $filename = 'birth_cam_' . time() . '_' . uniqid() . '.jpg';
        file_put_contents($upload_dir . $filename, $img_data);
        if ($document_path && file_exists('../' . $document_path)) {
            unlink('../' . $document_path);
        }
        $document_path = 'uploads/birth/' . $filename;
    }

    $stmt = $pdo->prepare("UPDATE birth_records SET 
        registry_number = ?, child_first_name = ?, child_middle_name = ?, child_last_name = ?,
        child_sex = ?, date_of_birth = ?, place_of_birth = ?, father_name = ?, mother_name = ?,
        date_registered = ?, document_path = ?, remarks = ?, updated_at = NOW()
        WHERE id = ?");

    if ($stmt->execute([
        $registry_number, $child_first_name, $child_middle_name, $child_last_name,
        $child_sex, $date_of_birth, $place_of_birth, $father_name, $mother_name,
        $date_registered, $document_path, $remarks, $id
    ])) {
        logAction($pdo, $user_id, "Updated birth record: $registry_number");
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
    $chk = $pdo->prepare("SELECT registry_number, document_path FROM birth_records WHERE id = ?");
    $chk->execute([$id]);
    $rec = $chk->fetch();

    if (!$rec) {
        echo json_encode(['status' => 'error', 'message' => 'Record not found.']);
        exit();
    }

    // Delete document file
    if (!empty($rec['document_path']) && file_exists('../' . $rec['document_path'])) {
        unlink('../' . $rec['document_path']);
    }

    $stmt = $pdo->prepare("DELETE FROM birth_records WHERE id = ?");
    if ($stmt->execute([$id])) {
        logAction($pdo, $user_id, "Deleted birth record: " . $rec['registry_number']);
        echo json_encode(['status' => 'success', 'message' => 'Birth record deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete record.']);
    }
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
?>