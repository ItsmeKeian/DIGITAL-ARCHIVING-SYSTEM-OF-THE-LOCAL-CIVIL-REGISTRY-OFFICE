<?php
// includes/header.php
// Requires session to be started before including this file
if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$user_role    = $_SESSION['role'];
$full_name    = $_SESSION['full_name'];
$username     = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>LCR Digital Archiving System | <?= ucfirst($current_page) ?></title>
    <link rel="icon" type="image/png" href="assets/images/logo.png"/>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet"/>
    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Main CSS -->
    <link rel="stylesheet" href="assets/css/main.css"/>
    <?php if (isset($extra_css)): ?>
        <link rel="stylesheet" href="assets/css/<?= $extra_css ?>"/>
    <?php endif; ?>
</head>
<body>

<!-- ── SIDEBAR ── -->
<aside class="sidebar" id="sidebar">

    <!-- Sidebar Header -->
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

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <ul>
            <!-- Dashboard -->
            <li>
                <a href="dashboard.php" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt nav-icon"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>

            <!-- Records Section -->
            <li class="nav-section-label"><span>Records</span></li>

            <li>
                <a href="birth_records.php" class="nav-link <?= $current_page === 'birth_records' ? 'active' : '' ?>">
                    <i class="fas fa-baby nav-icon"></i>
                    <span class="nav-text">Birth Records</span>
                </a>
            </li>

            <li>
                <a href="marriage_records.php" class="nav-link <?= $current_page === 'marriage_records' ? 'active' : '' ?>">
                    <i class="fas fa-rings-wedding nav-icon"></i>
                    <span class="nav-text">Marriage Records</span>
                </a>
            </li>

            <li>
                <a href="death_records.php" class="nav-link <?= $current_page === 'death_records' ? 'active' : '' ?>">
                    <i class="fas fa-cross nav-icon"></i>
                    <span class="nav-text">Death Records</span>
                </a>
            </li>

            <?php if ($user_role === 'admin'): ?>
            <!-- Admin Section -->
            <li class="nav-section-label"><span>Administration</span></li>

            <li>
                <a href="users.php" class="nav-link <?= $current_page === 'users' ? 'active' : '' ?>">
                    <i class="fas fa-users-cog nav-icon"></i>
                    <span class="nav-text">User Management</span>
                </a>
            </li>

            <li>
                <a href="audit_logs.php" class="nav-link <?= $current_page === 'audit_logs' ? 'active' : '' ?>">
                    <i class="fas fa-clipboard-list nav-icon"></i>
                    <span class="nav-text">Audit Logs</span>
                </a>
            </li>

            <li>
                <a href="backup.php" class="nav-link <?= $current_page === 'backup' ? 'active' : '' ?>">
                    <i class="fas fa-database nav-icon"></i>
                    <span class="nav-text">Backup & Restore</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar">
                <?= strtoupper(substr($full_name, 0, 1)) ?>
            </div>
            <div class="sidebar-user-info">
                <span class="sidebar-user-name"><?= htmlspecialchars($full_name) ?></span>
                <span class="sidebar-user-role"><?= ucfirst($user_role) ?></span>
            </div>
        </div>
        <a href="logout.php" class="btn-logout" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>

</aside>

<!-- ── MAIN CONTENT WRAPPER ── -->
<div class="main-wrapper" id="mainWrapper">

    <!-- Top Bar -->
    <header class="topbar">
        <button class="topbar-menu-btn" id="mobileSidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="topbar-title">
            <?php
            $page_titles = [
                'dashboard'       => 'Dashboard',
                'birth_records'   => 'Birth Records',
                'marriage_records'=> 'Marriage Records',
                'death_records'   => 'Death Records',
                'users'           => 'User Management',
                'audit_logs'      => 'Audit Logs',
                'backup'          => 'Backup & Restore',
            ];
            echo $page_titles[$current_page] ?? ucfirst($current_page);
            ?>
        </div>
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

    <!-- Page Content starts here -->
    <main class="page-content">