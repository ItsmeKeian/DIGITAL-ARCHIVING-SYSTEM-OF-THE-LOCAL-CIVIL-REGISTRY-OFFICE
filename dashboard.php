<?php
/* ============================================
   LCR DIGITAL ARCHIVING SYSTEM - DASHBOARD
   Municipality of San Julian, Eastern Samar
   ============================================ */

require_once 'authentication/session.php';
require_once 'authentication/db_connect.php';

requireLogin();

$user_role    = $_SESSION['role'];
$full_name    = $_SESSION['full_name'];
$username     = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>LCR Digital Archiving System | Dashboard</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/main.css"/>
</head>
<body>

<!-- ═══════════════════════════════════════
     SIDEBAR
════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">

    <div class="sidebar-header">
        <img src="assets/images/logo.png" alt="Logo" class="sidebar-logo"/>
        <div class="sidebar-title">
            <span class="sidebar-title-main">LCR System</span>
            <span class="sidebar-title-sub">San Julian, E. Samar</span>
        </div>
        <button class="sidebar-toggle-btn" id="sidebarToggle" title="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <ul>
            <li>
                <a href="dashboard.php" class="nav-link active" data-tooltip="Dashboard">
                    <i class="fas fa-tachometer-alt nav-icon"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>

            <li class="nav-section-label"><span>Records</span></li>

            <li>
                <a href="birth_records.php" class="nav-link" data-tooltip="Birth Records">
                    <i class="fas fa-baby nav-icon"></i>
                    <span class="nav-text">Birth Records</span>
                </a>
            </li>
            <li>
                <a href="marriage_records.php" class="nav-link" data-tooltip="Marriage Records">
                    <i class="fas fa-heart nav-icon"></i>
                    <span class="nav-text">Marriage Records</span>
                </a>
            </li>
            <li>
                <a href="death_records.php" class="nav-link" data-tooltip="Death Records">
                    <i class="fas fa-cross nav-icon"></i>
                    <span class="nav-text">Death Records</span>
                </a>
            </li>

            <?php if ($user_role === 'admin'): ?>
            <li class="nav-section-label"><span>Administration</span></li>
            <li>
                <a href="users.php" class="nav-link" data-tooltip="User Management">
                    <i class="fas fa-users-cog nav-icon"></i>
                    <span class="nav-text">User Management</span>
                </a>
            </li>
            <li>
                <a href="audit_logs.php" class="nav-link" data-tooltip="Audit Logs">
                    <i class="fas fa-clipboard-list nav-icon"></i>
                    <span class="nav-text">Audit Logs</span>
                </a>
            </li>
            <li>
                <a href="backup.php" class="nav-link" data-tooltip="Backup & Restore">
                    <i class="fas fa-database nav-icon"></i>
                    <span class="nav-text">Backup & Restore</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-avatar">
            <?= strtoupper(substr($full_name, 0, 1)) ?>
        </div>
        <div class="sidebar-user-info">
            <span class="sidebar-user-name"><?= htmlspecialchars($full_name) ?></span>
            <span class="sidebar-user-role"><?= ucfirst($user_role) ?></span>
        </div>
        <a href="logout.php" class="btn-logout" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>

</aside>

<!-- ═══════════════════════════════════════
     MAIN WRAPPER
════════════════════════════════════════ -->
<div class="main-wrapper" id="mainWrapper">

    <!-- Top Bar -->
    <header class="topbar">
        <button class="topbar-menu-btn" id="mobileSidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="topbar-title">Dashboard</div>
        <div class="topbar-right">
            <span class="topbar-date">
                <i class="fas fa-calendar-alt"></i>
                <?= date('F d, Y') ?>
            </span>
            <div class="topbar-user">
                <div class="topbar-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
                <span><?= htmlspecialchars($full_name) ?></span>
            </div>
        </div>
    </header>

    <!-- ═══════════════════════════════════════
         PAGE CONTENT
    ════════════════════════════════════════ -->
    <main class="page-content">

        <!-- Page Heading -->
        <div style="margin-bottom:24px;">
            <h2 style="font-family:'Cinzel',serif;font-size:1.2rem;color:var(--primary);margin-bottom:4px;">
                Welcome back, <?= htmlspecialchars($full_name) ?>!
            </h2>
            <p style="font-size:0.82rem;color:var(--text-muted);">
                Here is an overview of the Civil Registry records as of <?= date('F d, Y') ?>.
            </p>
        </div>

        <!-- ── SUMMARY CARDS ── -->
        <div class="stats-grid">

            <div class="stat-card birth">
                <div class="stat-icon"><i class="fas fa-baby"></i></div>
                <div class="stat-info">
                    <div class="stat-value" id="count-birth">0</div>
                    <div class="stat-label">Birth Records</div>
                    <a href="birth_records.php" class="stat-link">View all <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <div class="stat-card marriage">
                <div class="stat-icon"><i class="fas fa-heart"></i></div>
                <div class="stat-info">
                    <div class="stat-value" id="count-marriage">0</div>
                    <div class="stat-label">Marriage Records</div>
                    <a href="marriage_records.php" class="stat-link">View all <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <div class="stat-card death">
                <div class="stat-icon"><i class="fas fa-cross"></i></div>
                <div class="stat-info">
                    <div class="stat-value" id="count-death">0</div>
                    <div class="stat-label">Death Records</div>
                    <a href="death_records.php" class="stat-link">View all <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <?php if ($user_role === 'admin'): ?>
            <div class="stat-card users">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <div class="stat-value" id="count-users">0</div>
                    <div class="stat-label">System Users</div>
                    <a href="users.php" class="stat-link">Manage <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- ── CHART + AUDIT ROW ── -->
        <div class="dashboard-grid">

            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-chart-bar"></i> Records Overview</div>
                </div>
                <div class="card-body" style="height:240px;position:relative;">
                    <canvas id="recordsChart"></canvas>
                </div>
            </div>

            <?php if ($user_role === 'admin'): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-clipboard-list"></i> Recent Activity</div>
                    <a href="audit_logs.php" class="card-link">View all <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="card-body" style="padding:10px 20px;" id="audit-log-list">
                    <p style="font-size:0.8rem;color:#aaa;text-align:center;padding:20px;">
                        <i class="fas fa-spinner fa-spin"></i> Loading...
                    </p>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-info-circle"></i> Quick Guide</div>
                </div>
                <div class="card-body">
                    <p style="font-size:0.83rem;color:var(--text-muted);line-height:1.8;">
                        Use the sidebar to navigate through the records modules.
                        You can add, search, and view civil registry documents.
                        For account concerns, please contact the System Administrator.
                    </p>
                    <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;">
                        <a href="birth_records.php"    class="btn btn-outline btn-sm"><i class="fas fa-baby"></i> Birth</a>
                        <a href="marriage_records.php" class="btn btn-outline btn-sm"><i class="fas fa-heart"></i> Marriage</a>
                        <a href="death_records.php"    class="btn btn-outline btn-sm"><i class="fas fa-cross"></i> Death</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- ── RECENT RECORDS TABLE ── -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-clock"></i> Recently Added Records</div>
            </div>
            <div style="overflow-x:auto;">
                <table class="lcr-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Registry No.</th>
                            <th>Name</th>
                            <th>Record Date</th>
                            <th>Date Added</th>
                        </tr>
                    </thead>
                    <tbody id="recent-records-body">
                        <tr>
                            <td colspan="5" style="text-align:center;color:#aaa;padding:24px;">
                                <i class="fas fa-spinner fa-spin"></i> Loading records...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</div>
<!-- end main-wrapper -->

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="assets/js/main.js"></script>
<script src="assets/js/dashboard.js"></script>

</body>
</html>