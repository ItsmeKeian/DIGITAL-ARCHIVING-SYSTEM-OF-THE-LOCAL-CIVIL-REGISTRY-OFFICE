<?php


require_once 'authentication/session.php';
require_once 'authentication/db_connect.php';

requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {

    // ── Summary Counts ──
    case 'get_counts':
        $data   = [];
        $tables = [
            'birth'    => 'birth_records',
            'marriage' => 'marriage_records',
            'death'    => 'death_records',
            'users'    => 'users',
        ];

        foreach ($tables as $key => $table) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $data[$key] = (int) $stmt->fetchColumn();
        }

        echo json_encode(['status' => 'success', 'data' => $data]);
        break;

    // ── Recent Records ──
    case 'get_recent':
        $records = [];

        // Birth - latest 3
        $stmt = $pdo->query("
            SELECT 'Birth' AS type, registry_number,
                   CONCAT(child_first_name, ' ', child_last_name) AS name,
                   date_of_birth AS record_date, created_at
            FROM birth_records ORDER BY created_at DESC LIMIT 3
        ");
        $records = array_merge($records, $stmt->fetchAll());

        // Marriage - latest 3
        $stmt = $pdo->query("
            SELECT 'Marriage' AS type, registry_number,
                   CONCAT(husband_first_name,' ',husband_last_name,' & ',wife_first_name,' ',wife_last_name) AS name,
                   date_of_marriage AS record_date, created_at
            FROM marriage_records ORDER BY created_at DESC LIMIT 3
        ");
        $records = array_merge($records, $stmt->fetchAll());

        // Death - latest 3
        $stmt = $pdo->query("
            SELECT 'Death' AS type, registry_number,
                   CONCAT(deceased_first_name,' ',deceased_last_name) AS name,
                   date_of_death AS record_date, created_at
            FROM death_records ORDER BY created_at DESC LIMIT 3
        ");
        $records = array_merge($records, $stmt->fetchAll());

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

        $stmt = $pdo->query("
            SELECT al.action, al.ip_address, al.created_at, u.full_name
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC LIMIT 7
        ");
        $logs = $stmt->fetchAll();

        echo json_encode(['status' => 'success', 'data' => $logs]);
        break;

    // ── Chart Data ──
    case 'get_chart':
        $birth    = (int) $pdo->query("SELECT COUNT(*) FROM birth_records")   ->fetchColumn();
        $marriage = (int) $pdo->query("SELECT COUNT(*) FROM marriage_records")->fetchColumn();
        $death    = (int) $pdo->query("SELECT COUNT(*) FROM death_records")   ->fetchColumn();

        echo json_encode([
            'status' => 'success',
            'data'   => [
                'labels' => ['Birth Records', 'Marriage Records', 'Death Records'],
                'values' => [$birth, $marriage, $death],
            ]
        ]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
}
?>