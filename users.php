<?php
/* ============================================
   LCR DIGITAL ARCHIVING SYSTEM - USER MANAGEMENT
   Municipality of San Julian, Eastern Samar
   ============================================ */

require_once 'authentication/session.php';
require_once 'authentication/db_connect.php';

requireLogin();
requireAdmin(); // Admin only page

$user_role    = $_SESSION['role'];
$full_name    = $_SESSION['full_name'];
$current_uid  = $_SESSION['user_id'];

// ── Fetch all users ──
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_role   = isset($_GET['role'])   ? trim($_GET['role'])   : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

$sql    = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (full_name LIKE ? OR username LIKE ? OR email LIKE ?)";
    $s = '%' . $search . '%';
    $params = array_merge($params, [$s, $s, $s]);
}
if (!empty($filter_role)) {
    $sql .= " AND role = ?";
    $params[] = $filter_role;
}
if (!empty($filter_status)) {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
}

$sql .= " ORDER BY role ASC, full_name ASC";
$stmt    = $pdo->prepare($sql);
$stmt->execute($params);
$users   = $stmt->fetchAll();
$total   = count($users);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>LCR Digital Archiving System | User Management</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/main.css"/>
    <style>
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.55); z-index: 500;
            align-items: center; justify-content: center; padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: #fff; border-radius: 14px;
            width: 100%; max-width: 500px;
            max-height: 90vh; overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalIn 0.3s ease;
        }
        .modal-sm { max-width: 420px; }
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(-20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 24px; border-bottom: 1px solid #e9ecef;
            position: sticky; top: 0; background: #fff; z-index: 10;
        }
        .modal-title {
            font-family: 'Cinzel', serif; font-size: 1rem;
            font-weight: 700; color: var(--primary);
            display: flex; align-items: center; gap: 8px;
        }
        .modal-close {
            background: none; border: none; font-size: 1.2rem;
            color: #aaa; cursor: pointer; transition: color 0.2s;
            width: 32px; height: 32px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
        }
        .modal-close:hover { color: var(--primary); background: #f5f5f5; }
        .modal-body { padding: 24px; }
        .modal-footer {
            padding: 16px 24px; border-top: 1px solid #e9ecef;
            display: flex; justify-content: flex-end; gap: 10px;
            position: sticky; bottom: 0; background: #fff;
        }
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
        .form-label {
            font-size: 0.75rem; font-weight: 700;
            color: #555; text-transform: uppercase; letter-spacing: 0.4px;
        }
        .form-control {
            padding: 9px 12px; border: 1.5px solid #ddd;
            border-radius: 8px; font-size: 0.85rem;
            font-family: 'Lato', sans-serif; color: #2d2d2d;
            background: #f8f9fa; outline: none; transition: all 0.2s;
        }
        .form-control:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(139,0,0,0.08); }
        select.form-control { cursor: pointer; }
        .password-wrap { position: relative; }
        .password-wrap .form-control { padding-right: 40px; }
        .toggle-pw {
            position: absolute; right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none; color: #aaa;
            cursor: pointer; font-size: 0.9rem;
        }
        .toggle-pw:hover { color: var(--primary); }
        .form-hint { font-size: 0.72rem; color: #aaa; margin-top: 2px; }
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
        .filter-select {
            padding: 9px 12px; border: 1.5px solid #ddd;
            border-radius: 8px; font-size: 0.85rem;
            font-family: 'Lato', sans-serif; background: #f8f9fa;
            outline: none; cursor: pointer; min-width: 120px;
        }
        .action-btns { display: flex; gap: 6px; }
        .btn-icon {
            width: 30px; height: 30px; border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            border: none; cursor: pointer; font-size: 0.8rem;
            transition: all 0.2s; text-decoration: none;
        }
        .btn-edit     { background: rgba(230,126,34,0.1);  color: #e67e22; }
        .btn-delete   { background: rgba(192,57,43,0.1);   color: #c0392b; }
        .btn-activate { background: rgba(46,125,50,0.1);   color: #2E7D32; }
        .btn-deactivate { background: rgba(230,126,34,0.1); color: #e67e22; }
        .btn-password { background: rgba(21,101,192,0.1);  color: #1565C0; }
        .btn-edit:hover       { background: #e67e22; color: #fff; }
        .btn-delete:hover     { background: #c0392b; color: #fff; }
        .btn-activate:hover   { background: #2E7D32; color: #fff; }
        .btn-deactivate:hover { background: #e67e22; color: #fff; }
        .btn-password:hover   { background: #1565C0; color: #fff; }
        .page-alert {
            padding: 12px 16px; border-radius: 8px;
            font-size: 0.83rem; margin-bottom: 18px;
            display: none; align-items: center; gap: 10px;
        }
        .page-alert.show { display: flex; }
        .page-alert.success { background: #f0fff4; color: #27ae60; border-left: 4px solid #27ae60; }
        .page-alert.danger  { background: #fff0f0; color: #c0392b; border-left: 4px solid #c0392b; }
        .user-avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: var(--primary); color: #FFD700;
            display: inline-flex; align-items: center; justify-content: center;
            font-family: 'Cinzel', serif; font-weight: 700; font-size: 0.85rem;
            flex-shrink: 0;
        }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-name-text { font-weight: 700; font-size: 0.85rem; color: #2d2d2d; }
        .user-email-text { font-size: 0.72rem; color: #888; }
        .you-badge {
            font-size: 0.62rem; background: rgba(139,0,0,0.1);
            color: var(--primary); padding: 2px 7px; border-radius: 20px;
            font-weight: 700; margin-left: 6px;
        }
    </style>
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
        <button class="sidebar-toggle-btn" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    <nav class="sidebar-nav">
        <ul>
            <li><a href="dashboard.php" class="nav-link" data-tooltip="Dashboard"><i class="fas fa-tachometer-alt nav-icon"></i><span class="nav-text">Dashboard</span></a></li>
            <li class="nav-section-label"><span>Records</span></li>
            <li><a href="birth_records.php" class="nav-link" data-tooltip="Birth Records"><i class="fas fa-baby nav-icon"></i><span class="nav-text">Birth Records</span></a></li>
            <li><a href="marriage_records.php" class="nav-link" data-tooltip="Marriage Records"><i class="fas fa-heart nav-icon"></i><span class="nav-text">Marriage Records</span></a></li>
            <li><a href="death_records.php" class="nav-link" data-tooltip="Death Records"><i class="fas fa-cross nav-icon"></i><span class="nav-text">Death Records</span></a></li>
            <li class="nav-section-label"><span>Administration</span></li>
            <li><a href="users.php" class="nav-link active" data-tooltip="User Management"><i class="fas fa-users-cog nav-icon"></i><span class="nav-text">User Management</span></a></li>
            <li><a href="audit_logs.php" class="nav-link" data-tooltip="Audit Logs"><i class="fas fa-clipboard-list nav-icon"></i><span class="nav-text">Audit Logs</span></a></li>
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

<!-- ═══════════════════════════════════════
     MAIN WRAPPER
════════════════════════════════════════ -->
<div class="main-wrapper" id="mainWrapper">
    <header class="topbar">
        <button class="topbar-menu-btn" id="mobileSidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">User Management</div>
        <div class="topbar-right">
            <span class="topbar-date"><i class="fas fa-calendar-alt"></i> <?= date('F d, Y') ?></span>
            <div class="topbar-user">
                <div class="topbar-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
                <span><?= htmlspecialchars($full_name) ?></span>
            </div>
        </div>
    </header>

    <main class="page-content">

        <div class="page-alert" id="pageAlert"></div>

        <!-- Page Header -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
            <div>
                <h2 style="font-family:'Cinzel',serif;font-size:1.1rem;color:var(--primary);margin-bottom:3px;">User Management</h2>
                <p style="font-size:0.78rem;color:var(--text-muted);">Total: <strong><?= $total ?></strong> user(s) found</p>
            </div>
            <button class="btn btn-primary" id="btnAddUser">
                <i class="fas fa-user-plus"></i> Add Staff Account
            </button>
        </div>

        <!-- Search & Filter -->
        <div class="search-bar">
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" class="search-input" id="searchInput" placeholder="Search by name, username, email..." value="<?= htmlspecialchars($search) ?>"/>
            </div>
            <select class="filter-select" id="roleFilter">
                <option value="">All Roles</option>
                <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="staff" <?= $filter_role === 'staff' ? 'selected' : '' ?>>Staff</option>
            </select>
            <select class="filter-select" id="statusFilter">
                <option value="">All Status</option>
                <option value="active"   <?= $filter_status === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <button class="btn btn-outline btn-sm" id="btnSearch">
                <i class="fas fa-search"></i> Search
            </button>
            <?php if (!empty($search) || !empty($filter_role) || !empty($filter_status)): ?>
            <a href="users.php" class="btn btn-sm" style="background:#f0f0f0;color:#555;">
                <i class="fas fa-times"></i> Clear
            </a>
            <?php endif; ?>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div style="overflow-x:auto;">
                <table class="lcr-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Date Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center;color:#aaa;padding:32px;">
                                <i class="fas fa-users" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                                No users found.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($users as $i => $u): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar"><?= strtoupper(substr($u['full_name'], 0, 1)) ?></div>
                                    <div>
                                        <div class="user-name-text">
                                            <?= htmlspecialchars($u['full_name']) ?>
                                            <?php if ($u['id'] == $current_uid): ?>
                                            <span class="you-badge">You</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['email'] ?: '—') ?></td>
                            <td>
                                <span class="badge <?= $u['role'] === 'admin' ? 'badge-admin' : 'badge-staff' ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $u['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= ucfirst($u['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <div class="action-btns">
                                    <!-- Edit -->
                                    <button class="btn-icon btn-edit" title="Edit"
                                        onclick="editUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name']) ?>', '<?= htmlspecialchars($u['username']) ?>', '<?= htmlspecialchars($u['email']) ?>', '<?= $u['role'] ?>')">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <!-- Change Password -->
                                    <button class="btn-icon btn-password" title="Change Password"
                                        onclick="changePassword(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name']) ?>')">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <?php if ($u['id'] != $current_uid): ?>
                                    <!-- Toggle Status -->
                                    <?php if ($u['status'] === 'active'): ?>
                                    <button class="btn-icon btn-deactivate" title="Deactivate"
                                        onclick="toggleStatus(<?= $u['id'] ?>, 'inactive', '<?= htmlspecialchars($u['full_name']) ?>')">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn-icon btn-activate" title="Activate"
                                        onclick="toggleStatus(<?= $u['id'] ?>, 'active', '<?= htmlspecialchars($u['full_name']) ?>')">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                    <?php endif; ?>
                                    <!-- Delete -->
                                    <button class="btn-icon btn-delete" title="Delete"
                                        onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name']) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ═══════════════════════════════════════
     ADD / EDIT USER MODAL
════════════════════════════════════════ -->
<div class="modal-overlay" id="userModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="userModalTitle"><i class="fas fa-user-plus"></i> Add Staff Account</div>
            <button class="modal-close" onclick="closeModal('userModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="userForm">
                <input type="hidden" id="user_id" name="user_id" value=""/>
                <input type="hidden" name="action" id="userAction" value="add"/>

                <div class="form-group">
                    <label class="form-label">Full Name <span style="color:red">*</span></label>
                    <input type="text" name="full_name" id="full_name" class="form-control" placeholder="e.g. Juan Dela Cruz" required/>
                </div>
                <div class="form-group">
                    <label class="form-label">Username <span style="color:red">*</span></label>
                    <input type="text" name="username" id="inp_username" class="form-control" placeholder="e.g. juan.delacruz" required/>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" placeholder="e.g. juan@sanjulian.gov.ph"/>
                </div>
                <div class="form-group">
                    <label class="form-label">Role <span style="color:red">*</span></label>
                    <select name="role" id="role" class="form-control" required>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group" id="passwordGroup">
                    <label class="form-label">Password <span style="color:red">*</span></label>
                    <div class="password-wrap">
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter password"/>
                        <button type="button" class="toggle-pw" onclick="togglePw('password', this)"><i class="fas fa-eye"></i></button>
                    </div>
                    <div class="form-hint">Minimum 8 characters.</div>
                </div>

            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('userModal')">Cancel</button>
            <button class="btn btn-primary" id="btnSaveUser"><i class="fas fa-save"></i> Save</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     CHANGE PASSWORD MODAL
════════════════════════════════════════ -->
<div class="modal-overlay" id="passwordModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-key"></i> Change Password</div>
            <button class="modal-close" onclick="closeModal('passwordModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p style="font-size:0.83rem;color:#555;margin-bottom:16px;">
                Changing password for: <strong id="pwUserName"></strong>
            </p>
            <form id="passwordForm">
                <input type="hidden" id="pw_user_id" name="user_id"/>
                <input type="hidden" name="action" value="change_password"/>
                <div class="form-group">
                    <label class="form-label">New Password <span style="color:red">*</span></label>
                    <div class="password-wrap">
                        <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Enter new password" required/>
                        <button type="button" class="toggle-pw" onclick="togglePw('new_password', this)"><i class="fas fa-eye"></i></button>
                    </div>
                    <div class="form-hint">Minimum 8 characters.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password <span style="color:red">*</span></label>
                    <div class="password-wrap">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm new password" required/>
                        <button type="button" class="toggle-pw" onclick="togglePw('confirm_password', this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('passwordModal')">Cancel</button>
            <button class="btn btn-primary" id="btnSavePassword"><i class="fas fa-save"></i> Save Password</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     DELETE CONFIRM MODAL
════════════════════════════════════════ -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <div class="modal-title" style="color:#c0392b;"><i class="fas fa-trash"></i> Delete User</div>
            <button class="modal-close" onclick="closeModal('deleteModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p style="font-size:0.88rem;color:#555;line-height:1.6;">
                Are you sure you want to delete the account of <strong id="deleteUserName"></strong>?
                This action <strong>cannot be undone</strong>.
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
            <button class="btn btn-primary" id="btnConfirmDelete" style="background:#c0392b;">
                <i class="fas fa-trash"></i> Yes, Delete
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     TOGGLE STATUS CONFIRM MODAL
════════════════════════════════════════ -->
<div class="modal-overlay" id="statusModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <div class="modal-title" id="statusModalTitle"><i class="fas fa-ban"></i> Deactivate User</div>
            <button class="modal-close" onclick="closeModal('statusModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p style="font-size:0.88rem;color:#555;line-height:1.6;" id="statusModalMsg"></p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('statusModal')">Cancel</button>
            <button class="btn btn-primary" id="btnConfirmStatus">Confirm</button>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="assets/js/main.js"></script>
<script>

// ── Search ──
$('#btnSearch').on('click', function () {
    const q  = $('#searchInput').val().trim();
    const r  = $('#roleFilter').val();
    const s  = $('#statusFilter').val();
    let url  = 'users.php?';
    if (q) url += 'search=' + encodeURIComponent(q) + '&';
    if (r) url += 'role='   + encodeURIComponent(r) + '&';
    if (s) url += 'status=' + encodeURIComponent(s);
    window.location.href = url;
});
$('#searchInput').on('keypress', function (e) { if (e.which === 13) $('#btnSearch').click(); });

// ── Modal Helpers ──
function openModal(id)  { $('#' + id).addClass('active'); $('body').css('overflow', 'hidden'); }
function closeModal(id) { $('#' + id).removeClass('active'); $('body').css('overflow', ''); }
$('.modal-overlay').on('click', function (e) {
    if ($(e.target).hasClass('modal-overlay')) {
        $(this).removeClass('active');
        $('body').css('overflow', '');
    }
});

// ── Alert ──
function showAlert(type, msg) {
    $('#pageAlert').removeClass('show success danger')
        .addClass('show ' + type)
        .html('<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + msg);
    setTimeout(() => $('#pageAlert').removeClass('show'), 4000);
}

// ── Toggle Password Visibility ──
function togglePw(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    input.type  = input.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}

// ── Add User ──
$('#btnAddUser').on('click', function () {
    $('#userForm')[0].reset();
    $('#user_id').val('');
    $('#userAction').val('add');
    $('#userModalTitle').html('<i class="fas fa-user-plus"></i> Add Staff Account');
    $('#passwordGroup').show();
    $('#password').attr('required', true);
    openModal('userModal');
});

// ── Edit User ──
function editUser(id, fullName, username, email, role) {
    $('#user_id').val(id);
    $('#userAction').val('edit');
    $('#userModalTitle').html('<i class="fas fa-pen"></i> Edit User Account');
    $('#full_name').val(fullName);
    $('#inp_username').val(username);
    $('#email').val(email);
    $('#role').val(role);
    $('#passwordGroup').hide();
    $('#password').attr('required', false).val('');
    openModal('userModal');
}

// ── Save User ──
$('#btnSaveUser').on('click', function () {
    const form = $('#userForm')[0];
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const action   = $('#userAction').val();
    const password = $('#password').val();

    if (action === 'add' && password.length < 8) {
        showAlert('danger', 'Password must be at least 8 characters.');
        return;
    }

    const $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

    $.ajax({
        url: 'php/user_handler.php',
        type: 'POST',
        data: $('#userForm').serialize(),
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                closeModal('userModal');
                showAlert('success', res.message);
                setTimeout(() => location.reload(), 1200);
            } else { showAlert('danger', res.message); }
        },
        error: function () { showAlert('danger', 'Server error. Please try again.'); },
        complete: function () { $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Save'); }
    });
});

// ── Change Password ──
function changePassword(id, name) {
    $('#pw_user_id').val(id);
    $('#pwUserName').text(name);
    $('#passwordForm')[0].reset();
    openModal('passwordModal');
}

$('#btnSavePassword').on('click', function () {
    const newPw  = $('#new_password').val();
    const conPw  = $('#confirm_password').val();

    if (newPw.length < 8) { showAlert('danger', 'Password must be at least 8 characters.'); return; }
    if (newPw !== conPw)  { showAlert('danger', 'Passwords do not match.'); return; }

    const $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

    $.ajax({
        url: 'php/user_handler.php',
        type: 'POST',
        data: $('#passwordForm').serialize(),
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                closeModal('passwordModal');
                showAlert('success', res.message);
            } else { showAlert('danger', res.message); }
        },
        error: function () { showAlert('danger', 'Server error. Please try again.'); },
        complete: function () { $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Save Password'); }
    });
});

// ── Toggle Status ──
let statusUserId = null, statusNewStatus = null;
function toggleStatus(id, newStatus, name) {
    statusUserId    = id;
    statusNewStatus = newStatus;
    const isDeactivate = newStatus === 'inactive';
    $('#statusModalTitle').html('<i class="fas fa-' + (isDeactivate ? 'ban' : 'check-circle') + '"></i> ' + (isDeactivate ? 'Deactivate' : 'Activate') + ' User');
    $('#statusModalMsg').html(
        'Are you sure you want to <strong>' + (isDeactivate ? 'deactivate' : 'activate') + '</strong> the account of <strong>' + name + '</strong>?' +
        (isDeactivate ? ' They will no longer be able to log in.' : ' They will be able to log in again.')
    );
    $('#btnConfirmStatus')
        .css('background', isDeactivate ? '#e67e22' : '#2E7D32')
        .html('<i class="fas fa-' + (isDeactivate ? 'ban' : 'check-circle') + '"></i> ' + (isDeactivate ? 'Deactivate' : 'Activate'));
    openModal('statusModal');
}

$('#btnConfirmStatus').on('click', function () {
    const $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
    $.ajax({
        url: 'php/user_handler.php',
        type: 'POST',
        data: { action: 'toggle_status', user_id: statusUserId, status: statusNewStatus },
        dataType: 'json',
        success: function (res) {
            closeModal('statusModal');
            if (res.status === 'success') {
                showAlert('success', res.message);
                setTimeout(() => location.reload(), 1200);
            } else { showAlert('danger', res.message); }
        },
        complete: function () { $btn.prop('disabled', false); }
    });
});

// ── Delete User ──
let deleteUserId = null;
function deleteUser(id, name) {
    deleteUserId = id;
    $('#deleteUserName').text(name);
    openModal('deleteModal');
}

$('#btnConfirmDelete').on('click', function () {
    const $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Deleting...');
    $.ajax({
        url: 'php/user_handler.php',
        type: 'POST',
        data: { action: 'delete', user_id: deleteUserId },
        dataType: 'json',
        success: function (res) {
            closeModal('deleteModal');
            if (res.status === 'success') {
                showAlert('success', res.message);
                setTimeout(() => location.reload(), 1200);
            } else { showAlert('danger', res.message); }
        },
        complete: function () { $btn.prop('disabled', false).html('<i class="fas fa-trash"></i> Yes, Delete'); }
    });
});
</script>

</body>
</html>