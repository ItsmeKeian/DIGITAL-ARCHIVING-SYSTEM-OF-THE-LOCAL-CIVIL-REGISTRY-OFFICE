<?php
/* ============================================
   LCR DIGITAL ARCHIVING SYSTEM - DASHBOARD
   Municipality of San Julian, Eastern Samar
   ============================================ */

require_once 'authentication/session.php';
require_once 'authentication/db.php';

requireLogin();

$extra_js  = 'dashboard.js';
$user_role = $_SESSION['role'];

include 'includes/header.php';
?>

<!-- ── PAGE HEADER ── -->
<div style="margin-bottom: 24px;">
    <h2 style="font-family:'Cinzel',serif; font-size:1.2rem; color:var(--primary); margin-bottom:4px;">
        Welcome back, <?= htmlspecialchars($_SESSION['full_name']) ?>!
    </h2>
    <p style="font-size:0.82rem; color:var(--text-muted);">
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

<!-- ── CHART + AUDIT/INFO ROW ── -->
<div class="dashboard-grid">

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-chart-bar"></i> Records Overview</div>
        </div>
        <div class="card-body" style="height:240px; position:relative;">
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
            <p style="font-size:0.8rem;color:#aaa;text-align:center;padding:20px;">Loading...</p>
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

<?php include 'includes/footer.php'; ?>