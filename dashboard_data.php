<?php
/* ============================================
   LCR SYSTEM - DASHBOARD DATA HANDLER (AJAX)
   Municipality of San Julian, Eastern Samar
   ============================================ */

require_once 'authentication/session.php';
require_once 'authentication/db_connect.php';

requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {

    // ── Summary Counts ──
    case 'get_counts':
        $data = [];

        $tables = [
            'birth'    => 'birth_records',
            'marriage' => 'marriage_records',
            'death'    => 'death_records',
            'users'    => 'users',
        ];

        foreach ($tables as $key => $table) {
            $res = mysqli_query($conn, "SELECT COUNT(*) as total FROM $table");
            $row = mysqli_fetch_assoc($res);
            $data[$key] = (int) $row['total'];
        }

        echo json_encode(['status' => 'success', 'data' => $data]);
        break;

    // ── Recent Records ──
    case 'get_recent':
        $records = [];

        // Birth - latest 3
        $res = mysqli_query($conn, "
            SELECT 'Birth' AS type, registry_number,
                   CONCAT(child_first_name, ' ', child_last_name) AS name,
                   date_of_birth AS record_date, created_at
            FROM birth_records ORDER BY created_at DESC LIMIT 3
        ");
        while ($row = mysqli_fetch_assoc($res)) $records[] = $row;

        // Marriage - latest 3
        $res = mysqli_query($conn, "
            SELECT 'Marriage' AS type, registry_number,
                   CONCAT(husband_first_name,' ',husband_last_name,' & ',wife_first_name,' ',wife_last_name) AS name,
                   date_of_marriage AS record_date, created_at
            FROM marriage_records ORDER BY created_at DESC LIMIT 3
        ");
        while ($row = mysqli_fetch_assoc($res)) $records[] = $row;

        // Death - latest 3
        $res = mysqli_query($conn, "
            SELECT 'Death' AS type, registry_number,
                   CONCAT(deceased_first_name,' ',deceased_last_name) AS name,
                   date_of_death AS record_date, created_at
            FROM death_records ORDER BY created_at DESC LIMIT 3
        ");
        while ($row = mysqli_fetch_assoc($res)) $records[] = $row;

        // Sort all by created_at DESC, take top 8
        usort($records, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
        $records = array_slice($records, 0, 8);

        echo json_encode(['status' => 'success', 'data' => $records]);
        break;

    // ── Audit Logs (Admin only) ──
    case 'get_audit':
        if ($_SESSION['role'] !== 'admin') {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            break;
        }

        $logs = [];
        $res  = mysqli_query($conn, "
            SELECT al.action, al.ip_address, al.created_at,
                   u.full_name
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC LIMIT 7
        ");

        while ($row = mysqli_fetch_assoc($res)) $logs[] = $row;
        echo json_encode(['status' => 'success', 'data' => $logs]);
        break;

    // ── Chart Data ──
    case 'get_chart':
        $birth    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM birth_records"))['c'];
        $marriage = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM marriage_records"))['c'];
        $death    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM death_records"))['c'];

        echo json_encode([
            'status' => 'success',
            'data'   => [
                'labels' => ['Birth Records', 'Marriage Records', 'Death Records'],
                'values' => [(int)$birth, (int)$marriage, (int)$death],
            ]
        ]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
}

mysqli_close($conn);
?>