<?php
/* ============================================
   LCR DIGITAL ARCHIVING SYSTEM - AUDIT LOGS
   Municipality of San Julian, Eastern Samar
   ============================================ */

require_once 'authentication/session.php';
require_once 'authentication/db_connect.php';

requireLogin();
requireAdmin();

$user_role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

// ── Filters ──
$search       = isset($_GET['search'])  ? trim($_GET['search'])  : '';
$filter_user  = isset($_GET['user_id']) ? trim($_GET['user_id']) : '';
$filter_date  = isset($_GET['date'])    ? trim($_GET['date'])    : '';
$filter_month = isset($_GET['month'])   ? trim($_GET['month'])   : '';

// ── Pagination Setup ──
$per_page     = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset       = ($current_page - 1) * $per_page;

// ── Build WHERE clause ──
$where  = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (al.action LIKE ? OR u.full_name LIKE ? OR u.username LIKE ? OR al.ip_address LIKE ?)";
    $s = '%' . $search . '%';
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if (!empty($filter_user)) {
    $where .= " AND al.user_id = ?";
    $params[] = $filter_user;
}
if (!empty($filter_date)) {
    $where .= " AND DATE(al.created_at) = ?";
    $params[] = $filter_date;
}
if (!empty($filter_month)) {
    $where .= " AND DATE_FORMAT(al.created_at, '%Y-%m') = ?";
    $params[] = $filter_month;
}

// ── Get total count for pagination ──
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id $where");
$count_stmt->execute($params);
$total_records = (int) $count_stmt->fetchColumn();
$total_pages   = max(1, ceil($total_records / $per_page));

// Clamp current page
if ($current_page > $total_pages) $current_page = $total_pages;

// ── Fetch only current page records ──
$sql  = "SELECT al.*, u.full_name, u.username, u.role 
         FROM audit_logs al
         LEFT JOIN users u ON al.user_id = u.id
         $where
         ORDER BY al.created_at DESC
         LIMIT " . intval($per_page) . " OFFSET " . intval($offset);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// ── Summary counts (all time, not paginated) ──
$sum_stmt    = $pdo->query("SELECT action FROM audit_logs");
$all_actions = $sum_stmt->fetchAll(PDO::FETCH_COLUMN);
$count_total   = count($all_actions);
$count_login   = count(array_filter($all_actions, fn($a) => stripos($a, 'logged in')  !== false));
$count_add     = count(array_filter($all_actions, fn($a) => stripos($a, 'added')      !== false));
$count_changes = count(array_filter($all_actions, fn($a) => stripos($a, 'updated')    !== false || stripos($a, 'deleted') !== false));

// ── Pagination URL builder ──
$query_params = [];
if (!empty($search))       $query_params['search']  = $search;
if (!empty($filter_user))  $query_params['user_id'] = $filter_user;
if (!empty($filter_date))  $query_params['date']    = $filter_date;
if (!empty($filter_month)) $query_params['month']   = $filter_month;

function paginate_url($page, $qp) {
    $qp['page'] = $page;
    return 'audit_logs.php?' . http_build_query($qp);
}

// ── Get all users for filter dropdown ──
$users_stmt = $pdo->query("SELECT id, full_name, username FROM users ORDER BY full_name ASC");
$all_users  = $users_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>LCR Digital Archiving System | Audit Logs</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/main.css"/>
    <style>
        .search-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 20px; }
        .search-input-wrap { position: relative; flex: 1; min-width: 200px; }
        .search-input-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 0.85rem; }
        .search-input {
            width: 100%; padding: 9px 12px 9px 36px;
            border: 1.5px solid #ddd; border-radius: 8px;
            font-size: 0.85rem; font-family: 'Lato', sans-serif;
            outline: none; background: #f8f9fa; transition: all 0.2s;
        }
        .search-input:focus { border-color: var(--primary); background: #fff; }
        .filter-select, .filter-date {
            padding: 9px 12px; border: 1.5px solid #ddd;
            border-radius: 8px; font-size: 0.85rem;
            font-family: 'Lato', sans-serif; background: #f8f9fa;
            outline: none; cursor: pointer;
        }
        .filter-select { min-width: 150px; }
        .filter-date   { min-width: 140px; }
        .filter-select:focus, .filter-date:focus { border-color: var(--primary); }

        .log-action { font-weight: 700; font-size: 0.83rem; color: #2d2d2d; display: flex; align-items: center; gap: 7px; }
        .log-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; background: var(--primary); }
        .log-dot.login    { background: #2E7D32; }
        .log-dot.logout   { background: #888; }
        .log-dot.add      { background: #1565C0; }
        .log-dot.update   { background: #e67e22; }
        .log-dot.delete   { background: #c0392b; }
        .log-dot.other    { background: var(--primary); }

        .log-user { display: flex; align-items: center; gap: 8px; }
        .log-avatar {
            width: 28px; height: 28px; border-radius: 50%;
            background: var(--primary); color: #FFD700;
            display: inline-flex; align-items: center; justify-content: center;
            font-family: 'Cinzel', serif; font-weight: 700; font-size: 0.72rem; flex-shrink: 0;
        }
        .log-username { font-size: 0.72rem; color: #888; }
        .log-time { font-size: 0.78rem; color: #555; white-space: nowrap; }
        .log-ip   { font-size: 0.72rem; color: #aaa; font-family: monospace; }

        .log-summary {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 14px; margin-bottom: 24px;
        }
        .log-stat {
            background: #fff; border-radius: 10px; padding: 16px;
            box-shadow: var(--shadow); border-left: 4px solid transparent;
            display: flex; align-items: center; gap: 12px;
        }
        .log-stat.total   { border-left-color: var(--primary); }
        .log-stat.login   { border-left-color: #2E7D32; }
        .log-stat.add     { border-left-color: #1565C0; }
        .log-stat.changes { border-left-color: #e67e22; }
        .log-stat-icon { width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
        .log-stat.total   .log-stat-icon { background: rgba(139,0,0,0.1);   color: var(--primary); }
        .log-stat.login   .log-stat-icon { background: rgba(46,125,50,0.1); color: #2E7D32; }
        .log-stat.add     .log-stat-icon { background: rgba(21,101,192,0.1);color: #1565C0; }
        .log-stat.changes .log-stat-icon { background: rgba(230,126,34,0.1);color: #e67e22; }
        .log-stat-val   { font-family: 'Cinzel', serif; font-size: 1.4rem; font-weight: 700; color: #2d2d2d; line-height: 1; }
        .log-stat-label { font-size: 0.7rem; color: #888; margin-top: 3px; }

        /* ── Pagination ── */
        .pagination-wrap {
            padding: 14px 20px;
            border-top: 1px solid #f0f0f0;
            display: flex; align-items: center;
            justify-content: space-between;
            flex-wrap: wrap; gap: 10px;
        }
        .pagination-info { font-size: 0.78rem; color: #888; }
        .pagination-btns { display: flex; gap: 5px; flex-wrap: wrap; }
        .pg-btn {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 32px; height: 32px; padding: 0 8px;
            border-radius: 7px; border: 1.5px solid #ddd;
            background: #fff; color: #555; font-size: 0.82rem;
            text-decoration: none; transition: all 0.2s; font-weight: 700;
        }
        .pg-btn:hover   { border-color: var(--primary); color: var(--primary); background: rgba(139,0,0,0.05); }
        .pg-btn.active  { background: var(--primary); color: #fff; border-color: var(--primary); pointer-events: none; }
        .pg-btn.disabled{ opacity: 0.4; pointer-events: none; }
    </style>
</head>
<body>

<!-- ═══════════════════════════════════════ SIDEBAR ════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="assets/images/logo.png" alt="Logo" class="sidebar-logo"/>
        <div class="sidebar-title">
            <span class="sidebar-title-main">LCR System</span>
            <span class="sidebar-title-sub">San Julian, E. Samar</span>
        </div>
        <button class="sidebar-toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    </div>
    <nav class="sidebar-nav">
        <ul>
            <li><a href="dashboard.php" class="nav-link" data-tooltip="Dashboard"><i class="fas fa-tachometer-alt nav-icon"></i><span class="nav-text">Dashboard</span></a></li>
            <li class="nav-section-label"><span>Records</span></li>
            <li><a href="birth_records.php" class="nav-link" data-tooltip="Birth Records"><i class="fas fa-baby nav-icon"></i><span class="nav-text">Birth Records</span></a></li>
            <li><a href="marriage_records.php" class="nav-link" data-tooltip="Marriage Records"><i class="fas fa-heart nav-icon"></i><span class="nav-text">Marriage Records</span></a></li>
            <li><a href="death_records.php" class="nav-link" data-tooltip="Death Records"><i class="fas fa-cross nav-icon"></i><span class="nav-text">Death Records</span></a></li>
            <li class="nav-section-label"><span>Administration</span></li>
            <li><a href="users.php" class="nav-link" data-tooltip="User Management"><i class="fas fa-users-cog nav-icon"></i><span class="nav-text">User Management</span></a></li>
            <li><a href="audit_logs.php" class="nav-link active" data-tooltip="Audit Logs"><i class="fas fa-clipboard-list nav-icon"></i><span class="nav-text">Audit Logs</span></a></li>
            <li><a href="backup.php" class="nav-link" data-tooltip="Backup & Restore"><i class="fas fa-database nav-icon"></i><span class="nav-text">Backup & Restore</span></a></li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
        <div class="sidebar-user-info">
            <span class="sidebar-user-name"><?= htmlspecialchars($full_name) ?></span>
            <span class="sidebar-user-role"><?= ucfirst($user_role) ?></span>
        </div>
        <a href="logout.php" class="btn-logout" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
    </div>
</aside>

<!-- ═══════════════════════════════════════ MAIN WRAPPER ════════════════════════════════════════ -->
<div class="main-wrapper" id="mainWrapper">
    <header class="topbar">
        <button class="topbar-menu-btn" id="mobileSidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">Audit Logs</div>
        <div class="topbar-right">
            <span class="topbar-date"><i class="fas fa-calendar-alt"></i> <?= date('F d, Y') ?></span>
            <div class="topbar-user">
                <div class="topbar-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
                <span><?= htmlspecialchars($full_name) ?></span>
            </div>
        </div>
    </header>

    <main class="page-content">

        <!-- Page Header -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
            <div>
                <h2 style="font-family:'Cinzel',serif;font-size:1.1rem;color:var(--primary);margin-bottom:3px;">Audit Logs</h2>
                <p style="font-size:0.78rem;color:var(--text-muted);">
                    <?php if ($total_records > 0): ?>
                    Showing <strong><?= ($offset + 1) ?>–<?= min($offset + $per_page, $total_records) ?></strong>
                    of <strong><?= $total_records ?></strong> log(s)
                    <?php else: ?>
                    No logs found.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="log-summary">
            <div class="log-stat total">
                <div class="log-stat-icon"><i class="fas fa-list"></i></div>
                <div><div class="log-stat-val"><?= $count_total ?></div><div class="log-stat-label">Total Activities</div></div>
            </div>
            <div class="log-stat login">
                <div class="log-stat-icon"><i class="fas fa-sign-in-alt"></i></div>
                <div><div class="log-stat-val"><?= $count_login ?></div><div class="log-stat-label">Login Activities</div></div>
            </div>
            <div class="log-stat add">
                <div class="log-stat-icon"><i class="fas fa-plus-circle"></i></div>
                <div><div class="log-stat-val"><?= $count_add ?></div><div class="log-stat-label">Records Added</div></div>
            </div>
            <div class="log-stat changes">
                <div class="log-stat-icon"><i class="fas fa-edit"></i></div>
                <div><div class="log-stat-val"><?= $count_changes ?></div><div class="log-stat-label">Updates & Deletes</div></div>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="search-bar">
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" class="search-input" id="searchInput"
                    placeholder="Search action, user, IP address..."
                    value="<?= htmlspecialchars($search) ?>"/>
            </div>
            <select class="filter-select" id="userFilter">
                <option value="">All Users</option>
                <?php foreach ($all_users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $filter_user == $u['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['username']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <input type="date"  class="filter-date" id="dateFilter"  value="<?= htmlspecialchars($filter_date) ?>"  title="Filter by date"/>
            <input type="month" class="filter-date" id="monthFilter" value="<?= htmlspecialchars($filter_month) ?>" title="Filter by month"/>
            <button class="btn btn-outline btn-sm" id="btnSearch">
                <i class="fas fa-search"></i> Search
            </button>
            <?php if (!empty($search) || !empty($filter_user) || !empty($filter_date) || !empty($filter_month)): ?>
            <a href="audit_logs.php" class="btn btn-sm" style="background:#f0f0f0;color:#555;">
                <i class="fas fa-times"></i> Clear
            </a>
            <?php endif; ?>
        </div>

        <!-- Logs Table -->
        <div class="card">
            <div style="overflow-x:auto;">
                <table class="lcr-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Action</th>
                            <th>User</th>
                            <th>IP Address</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;color:#aaa;padding:32px;">
                                <i class="fas fa-clipboard-list" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                                No audit logs found.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($logs as $i => $log):
                            $al = strtolower($log['action']);
                            if      (strpos($al, 'logged in')  !== false) $dot = 'login';
                            elseif  (strpos($al, 'logged out') !== false) $dot = 'logout';
                            elseif  (strpos($al, 'added')      !== false) $dot = 'add';
                            elseif  (strpos($al, 'updated')    !== false) $dot = 'update';
                            elseif  (strpos($al, 'deleted')    !== false) $dot = 'delete';
                            else    $dot = 'other';
                            // Row number continues across pages
                            $row_num = $offset + $i + 1;
                        ?>
                        <tr>
                            <td style="color:#aaa;font-size:0.78rem;"><?= $row_num ?></td>
                            <td>
                                <div class="log-action">
                                    <div class="log-dot <?= $dot ?>"></div>
                                    <?= htmlspecialchars($log['action']) ?>
                                </div>
                            </td>
                            <td>
                                <div class="log-user">
                                    <div class="log-avatar"><?= strtoupper(substr($log['full_name'] ?? 'U', 0, 1)) ?></div>
                                    <div>
                                        <div style="font-size:0.82rem;font-weight:700;color:#2d2d2d;"><?= htmlspecialchars($log['full_name'] ?? 'Unknown') ?></div>
                                        <div class="log-username">@<?= htmlspecialchars($log['username'] ?? '—') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="log-ip"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></span></td>
                            <td>
                                <div class="log-time"><?= date('M d, Y', strtotime($log['created_at'])) ?></div>
                                <div class="log-ip"><?= date('h:i:s A', strtotime($log['created_at'])) ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ── PAGINATION ── -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-wrap">
                <div class="pagination-info">
                    Page <strong><?= $current_page ?></strong> of <strong><?= $total_pages ?></strong>
                </div>
                <div class="pagination-btns">

                    <!-- First & Prev -->
                    <a href="<?= paginate_url(1, $query_params) ?>" class="pg-btn <?= $current_page <= 1 ? 'disabled' : '' ?>" title="First">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="<?= paginate_url($current_page - 1, $query_params) ?>" class="pg-btn <?= $current_page <= 1 ? 'disabled' : '' ?>" title="Previous">
                        <i class="fas fa-angle-left"></i>
                    </a>

                    <!-- Page Numbers -->
                    <?php
                    $start = max(1, $current_page - 2);
                    $end   = min($total_pages, $current_page + 2);
                    for ($p = $start; $p <= $end; $p++):
                    ?>
                    <a href="<?= paginate_url($p, $query_params) ?>" class="pg-btn <?= $p === $current_page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                    <?php endfor; ?>

                    <!-- Next & Last -->
                    <a href="<?= paginate_url($current_page + 1, $query_params) ?>" class="pg-btn <?= $current_page >= $total_pages ? 'disabled' : '' ?>" title="Next">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="<?= paginate_url($total_pages, $query_params) ?>" class="pg-btn <?= $current_page >= $total_pages ? 'disabled' : '' ?>" title="Last">
                        <i class="fas fa-angle-double-right"></i>
                    </a>

                </div>
            </div>
            <?php endif; ?>

        </div>

    </main>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="assets/js/main.js"></script>
<script>
$('#btnSearch').on('click', function () {
    const q = $('#searchInput').val().trim();
    const u = $('#userFilter').val();
    const d = $('#dateFilter').val();
    const m = $('#monthFilter').val();
    let url = 'audit_logs.php?';
    if (q) url += 'search='  + encodeURIComponent(q) + '&';
    if (u) url += 'user_id=' + encodeURIComponent(u) + '&';
    if (d) url += 'date='    + encodeURIComponent(d) + '&';
    if (m) url += 'month='   + encodeURIComponent(m);
    window.location.href = url;
});
$('#searchInput').on('keypress', function (e) { if (e.which === 13) $('#btnSearch').click(); });
</script>

</body>
</html>