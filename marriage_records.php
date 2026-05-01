<?php
/* ============================================
   LCR DIGITAL ARCHIVING SYSTEM - MARRIAGE RECORDS
   Municipality of San Julian, Eastern Samar
   ============================================ */

require_once 'authentication/session.php';
require_once 'authentication/db_connect.php';

requireLogin();

$user_role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];
$user_id   = $_SESSION['user_id'];

// ── Fetch all marriage records ──
$search      = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_year = isset($_GET['year'])   ? trim($_GET['year'])   : '';

$sql    = "SELECT m.*, u.full_name as added_by 
           FROM marriage_records m 
           LEFT JOIN users u ON m.created_by = u.id
           WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (m.husband_first_name LIKE ? 
               OR m.husband_last_name  LIKE ?
               OR m.wife_first_name    LIKE ?
               OR m.wife_last_name     LIKE ?
               OR m.registry_number    LIKE ?
               OR m.place_of_marriage  LIKE ?)";
    $s = '%' . $search . '%';
    $params = array_merge($params, [$s, $s, $s, $s, $s, $s]);
}

if (!empty($filter_year)) {
    $sql .= " AND YEAR(m.date_of_marriage) = ?";
    $params[] = $filter_year;
}

$sql .= " ORDER BY m.created_at DESC";

$stmt    = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();
$total   = count($records);

// ── Get available years for filter ──
$yr_stmt = $pdo->query("SELECT DISTINCT YEAR(date_of_marriage) as yr FROM marriage_records ORDER BY yr DESC");
$years   = $yr_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>LCR Digital Archiving System | Marriage Records</title>
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
            width: 100%; max-width: 820px;
            max-height: 90vh; overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalIn 0.3s ease;
        }
        .modal-sm { max-width: 440px; }
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
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-grid.three { grid-template-columns: 1fr 1fr 1fr; }
        .col-span-2 { grid-column: span 2; }
        .col-span-3 { grid-column: span 3; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
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
        textarea.form-control { resize: vertical; min-height: 80px; }
        .section-divider {
            font-family: 'Cinzel', serif; font-size: 0.78rem;
            font-weight: 700; color: var(--primary);
            padding: 12px 0 6px; border-bottom: 1px solid #f0f0f0;
            margin-bottom: 4px; grid-column: span 2;
        }
        .section-divider.span3 { grid-column: span 3; }
        .upload-area {
            border: 2px dashed #ddd; border-radius: 10px;
            padding: 20px; text-align: center;
            cursor: pointer; transition: all 0.2s; background: #f8f9fa;
        }
        .upload-area:hover, .upload-area.dragover { border-color: var(--primary); background: rgba(139,0,0,0.03); }
        .upload-area i { font-size: 2rem; color: #ccc; margin-bottom: 8px; display: block; }
        .upload-area p { font-size: 0.8rem; color: #888; margin: 0; }
        .upload-area span { font-size: 0.72rem; color: #aaa; }
        .camera-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: 8px;
            background: var(--primary); color: #fff;
            border: none; cursor: pointer; font-size: 0.8rem;
            font-family: 'Lato', sans-serif; font-weight: 700;
            margin-top: 10px; transition: all 0.2s;
        }
        .camera-btn:hover { background: var(--primary-dark); }
        .preview-box {
            margin-top: 12px; display: none;
            border-radius: 8px; overflow: hidden; border: 1px solid #ddd;
        }
        .preview-box img { width: 100%; max-height: 200px; object-fit: contain; background: #f0f0f0; }
        .preview-box .preview-name {
            padding: 6px 10px; font-size: 0.75rem;
            background: #f8f9fa; color: #555;
            display: flex; align-items: center; justify-content: space-between;
        }
        .view-field { margin-bottom: 14px; }
        .view-label { font-size: 0.7rem; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
        .view-value { font-size: 0.88rem; color: #2d2d2d; }
        .view-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .view-doc { width: 100%; border-radius: 8px; margin-top: 10px; border: 1px solid #ddd; }
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
            outline: none; cursor: pointer; min-width: 130px;
        }
        .action-btns { display: flex; gap: 6px; }
        .btn-icon {
            width: 30px; height: 30px; border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            border: none; cursor: pointer; font-size: 0.8rem;
            transition: all 0.2s; text-decoration: none;
        }
        .btn-view   { background: rgba(21,101,192,0.1);  color: #1565C0; }
        .btn-edit   { background: rgba(230,126,34,0.1);  color: #e67e22; }
        .btn-delete { background: rgba(192,57,43,0.1);   color: #c0392b; }
        .btn-view:hover   { background: #1565C0; color: #fff; }
        .btn-edit:hover   { background: #e67e22; color: #fff; }
        .btn-delete:hover { background: #c0392b; color: #fff; }
        .page-alert {
            padding: 12px 16px; border-radius: 8px;
            font-size: 0.83rem; margin-bottom: 18px;
            display: none; align-items: center; gap: 10px;
        }
        .page-alert.show { display: flex; }
        .page-alert.success { background: #f0fff4; color: #27ae60; border-left: 4px solid #27ae60; }
        .page-alert.danger  { background: #fff0f0; color: #c0392b; border-left: 4px solid #c0392b; }
        @media (max-width: 600px) {
            .form-grid, .form-grid.three { grid-template-columns: 1fr; }
            .col-span-2, .col-span-3 { grid-column: span 1; }
            .view-grid { grid-template-columns: 1fr; }
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
            <li><a href="marriage_records.php" class="nav-link active" data-tooltip="Marriage Records"><i class="fas fa-heart nav-icon"></i><span class="nav-text">Marriage Records</span></a></li>
            <li><a href="death_records.php" class="nav-link" data-tooltip="Death Records"><i class="fas fa-cross nav-icon"></i><span class="nav-text">Death Records</span></a></li>
            <?php if ($user_role === 'admin'): ?>
            <li class="nav-section-label"><span>Administration</span></li>
            <li><a href="users.php" class="nav-link" data-tooltip="User Management"><i class="fas fa-users-cog nav-icon"></i><span class="nav-text">User Management</span></a></li>
            <li><a href="audit_logs.php" class="nav-link" data-tooltip="Audit Logs"><i class="fas fa-clipboard-list nav-icon"></i><span class="nav-text">Audit Logs</span></a></li>
            <li><a href="backup.php" class="nav-link" data-tooltip="Backup & Restore"><i class="fas fa-database nav-icon"></i><span class="nav-text">Backup & Restore</span></a></li>
            <?php endif; ?>
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
        <div class="topbar-title">Marriage Records</div>
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
                <h2 style="font-family:'Cinzel',serif;font-size:1.1rem;color:var(--primary);margin-bottom:3px;">Marriage Records</h2>
                <p style="font-size:0.78rem;color:var(--text-muted);">Total: <strong><?= $total ?></strong> record(s) found</p>
            </div>
            <button class="btn btn-primary" id="btnAddRecord">
                <i class="fas fa-plus"></i> Add Marriage Record
            </button>
        </div>

        <!-- Search & Filter -->
        <div class="search-bar">
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" class="search-input" id="searchInput" placeholder="Search by name, registry no., place..." value="<?= htmlspecialchars($search) ?>"/>
            </div>
            <select class="filter-select" id="yearFilter">
                <option value="">All Years</option>
                <?php foreach ($years as $yr): ?>
                <option value="<?= $yr ?>" <?= $filter_year == $yr ? 'selected' : '' ?>><?= $yr ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-outline btn-sm" id="btnSearch">
                <i class="fas fa-search"></i> Search
            </button>
            <?php if (!empty($search) || !empty($filter_year)): ?>
            <a href="marriage_records.php" class="btn btn-sm" style="background:#f0f0f0;color:#555;">
                <i class="fas fa-times"></i> Clear
            </a>
            <?php endif; ?>
        </div>

        <!-- Records Table -->
        <div class="card">
            <div style="overflow-x:auto;">
                <table class="lcr-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Registry No.</th>
                            <th>Husband's Name</th>
                            <th>Wife's Name</th>
                            <th>Date of Marriage</th>
                            <th>Place of Marriage</th>
                            <th>Date Registered</th>
                            <th>Document</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="9" style="text-align:center;color:#aaa;padding:32px;">
                                <i class="fas fa-folder-open" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                                No marriage records found.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($records as $i => $rec): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= htmlspecialchars($rec['registry_number']) ?></strong></td>
                            <td><?= htmlspecialchars($rec['husband_first_name'] . ' ' . $rec['husband_middle_name'] . ' ' . $rec['husband_last_name']) ?></td>
                            <td><?= htmlspecialchars($rec['wife_first_name'] . ' ' . $rec['wife_middle_name'] . ' ' . $rec['wife_last_name']) ?></td>
                            <td><?= date('M d, Y', strtotime($rec['date_of_marriage'])) ?></td>
                            <td><?= htmlspecialchars($rec['place_of_marriage']) ?></td>
                            <td><?= date('M d, Y', strtotime($rec['date_registered'])) ?></td>
                            <td>
                                <?php if (!empty($rec['document_path'])): ?>
                                    <a href="<?= htmlspecialchars($rec['document_path']) ?>" target="_blank" class="badge badge-active" style="text-decoration:none;">
                                        <i class="fas fa-file"></i> View
                                    </a>
                                <?php else: ?>
                                    <span style="font-size:0.72rem;color:#aaa;">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-icon btn-view" title="View" onclick="viewRecord(<?= $rec['id'] ?>)"><i class="fas fa-eye"></i></button>
                                    <button class="btn-icon btn-edit" title="Edit" onclick="editRecord(<?= $rec['id'] ?>)"><i class="fas fa-pen"></i></button>
                                    <?php if ($user_role === 'admin'): ?>
                                    <button class="btn-icon btn-delete" title="Delete" onclick="deleteRecord(<?= $rec['id'] ?>, '<?= htmlspecialchars($rec['registry_number']) ?>')"><i class="fas fa-trash"></i></button>
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
     ADD / EDIT MODAL
════════════════════════════════════════ -->
<div class="modal-overlay" id="recordModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="modalTitle"><i class="fas fa-heart"></i> Add Marriage Record</div>
            <button class="modal-close" onclick="closeModal('recordModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="recordForm" enctype="multipart/form-data">
                <input type="hidden" id="record_id" name="record_id" value=""/>
                <input type="hidden" name="action" id="formAction" value="add"/>

                <div class="form-grid">

                    <!-- Registry Info -->
                    <div class="section-divider">Registry Information</div>
                    <div class="form-group">
                        <label class="form-label">Registry Number <span style="color:red">*</span></label>
                        <input type="text" name="registry_number" id="registry_number" class="form-control" placeholder="e.g. 2024-M-001" required/>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date Registered <span style="color:red">*</span></label>
                        <input type="date" name="date_registered" id="date_registered" class="form-control" required/>
                    </div>

                    <!-- Marriage Info -->
                    <div class="section-divider">Marriage Information</div>
                    <div class="form-group">
                        <label class="form-label">Date of Marriage <span style="color:red">*</span></label>
                        <input type="date" name="date_of_marriage" id="date_of_marriage" class="form-control" required/>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Place of Marriage <span style="color:red">*</span></label>
                        <input type="text" name="place_of_marriage" id="place_of_marriage" class="form-control" placeholder="e.g. San Julian Parish Church" required/>
                    </div>

                </div>

                <!-- Husband & Wife in 3-column grid -->
                <div class="form-grid three" style="margin-top:16px;">

                    <!-- Husband -->
                    <div class="section-divider span3">Husband's Information</div>
                    <div class="form-group">
                        <label class="form-label">First Name <span style="color:red">*</span></label>
                        <input type="text" name="husband_first_name" id="husband_first_name" class="form-control" placeholder="First name" required/>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Middle Name</label>
                        <input type="text" name="husband_middle_name" id="husband_middle_name" class="form-control" placeholder="Middle name"/>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name <span style="color:red">*</span></label>
                        <input type="text" name="husband_last_name" id="husband_last_name" class="form-control" placeholder="Last name" required/>
                    </div>
                    <div class="form-group col-span-3">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="husband_date_of_birth" id="husband_date_of_birth" class="form-control"/>
                    </div>

                    <!-- Wife -->
                    <div class="section-divider span3">Wife's Information</div>
                    <div class="form-group">
                        <label class="form-label">First Name <span style="color:red">*</span></label>
                        <input type="text" name="wife_first_name" id="wife_first_name" class="form-control" placeholder="First name" required/>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Middle Name</label>
                        <input type="text" name="wife_middle_name" id="wife_middle_name" class="form-control" placeholder="Middle name"/>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name <span style="color:red">*</span></label>
                        <input type="text" name="wife_last_name" id="wife_last_name" class="form-control" placeholder="Last name" required/>
                    </div>
                    <div class="form-group col-span-3">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="wife_date_of_birth" id="wife_date_of_birth" class="form-control"/>
                    </div>

                </div>

                <div class="form-grid" style="margin-top:16px;">
                    <!-- Remarks -->
                    <div class="form-group col-span-2">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" id="remarks" class="form-control" placeholder="Optional notes..."></textarea>
                    </div>

                    <!-- Document Upload -->
                    <div class="section-divider">Document / Scanned Copy</div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Upload Scanned Document (PDF, JPG, PNG)</label>
                        <div class="upload-area" id="uploadArea" onclick="$('#docFile').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to browse or drag & drop your file here</p>
                            <span>Supported: PDF, JPG, PNG (Max: 5MB)</span>
                        </div>
                        <input type="file" id="docFile" name="document" accept=".pdf,.jpg,.jpeg,.png" style="display:none"/>
                        <button type="button" class="camera-btn" id="btnOpenCamera">
                            <i class="fas fa-camera"></i> Use Camera / Scanner
                        </button>
                        <div class="preview-box" id="previewBox">
                            <img id="previewImg" src="" alt="Preview"/>
                            <div class="preview-name">
                                <span id="previewName"></span>
                                <button type="button" onclick="clearFile()" style="background:none;border:none;color:#c0392b;cursor:pointer;font-size:0.8rem;">
                                    <i class="fas fa-times"></i> Remove
                                </button>
                            </div>
                        </div>
                        <input type="hidden" id="capturedImageData" name="captured_image"/>
                    </div>
                </div>

            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('recordModal')">Cancel</button>
            <button class="btn btn-primary" id="btnSaveRecord"><i class="fas fa-save"></i> Save Record</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     VIEW MODAL
════════════════════════════════════════ -->
<div class="modal-overlay" id="viewModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-eye"></i> View Marriage Record</div>
            <button class="modal-close" onclick="closeModal('viewModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="viewModalBody">
            <p style="text-align:center;color:#aaa;"><i class="fas fa-spinner fa-spin"></i> Loading...</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     DELETE MODAL
════════════════════════════════════════ -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <div class="modal-title" style="color:#c0392b;"><i class="fas fa-trash"></i> Delete Record</div>
            <button class="modal-close" onclick="closeModal('deleteModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p style="font-size:0.88rem;color:#555;line-height:1.6;">
                Are you sure you want to delete registry record <strong id="deleteRegNum"></strong>?
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
     CAMERA MODAL
════════════════════════════════════════ -->
<div class="modal-overlay" id="cameraModal">
    <div class="modal" style="max-width:520px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-camera"></i> Camera / Scanner Capture</div>
            <button class="modal-close" onclick="closeCamera()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="text-align:center;">
            <video id="cameraFeed" autoplay playsinline style="width:100%;border-radius:8px;background:#000;max-height:320px;"></video>
            <canvas id="captureCanvas" style="display:none;"></canvas>
            <div style="margin-top:14px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                <button class="btn btn-primary" id="btnCapture"><i class="fas fa-camera"></i> Capture Photo</button>
                <button class="btn btn-outline" onclick="closeCamera()">Cancel</button>
            </div>
            <p style="font-size:0.72rem;color:#aaa;margin-top:10px;">Position the document in front of your camera, then click Capture.</p>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="assets/js/main.js"></script>
<script>

// ── Search ──
$('#btnSearch').on('click', function () {
    const q = $('#searchInput').val().trim();
    const y = $('#yearFilter').val();
    let url  = 'marriage_records.php?';
    if (q) url += 'search=' + encodeURIComponent(q) + '&';
    if (y) url += 'year='   + encodeURIComponent(y);
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
        closeCamera();
    }
});

// ── Alert ──
function showAlert(type, msg) {
    $('#pageAlert').removeClass('show success danger')
        .addClass('show ' + type)
        .html('<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + msg);
    setTimeout(() => $('#pageAlert').removeClass('show'), 4000);
}

// ── Add Record ──
$('#btnAddRecord').on('click', function () {
    $('#recordForm')[0].reset();
    $('#record_id').val('');
    $('#formAction').val('add');
    $('#modalTitle').html('<i class="fas fa-heart"></i> Add Marriage Record');
    clearFile();
    openModal('recordModal');
});

// ── File Upload ──
$('#docFile').on('change', function () {
    const file = this.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) { showAlert('danger', 'File size must not exceed 5MB.'); return; }
    showFilePreview(file);
});

const uploadArea = document.getElementById('uploadArea');
uploadArea.addEventListener('dragover',  e => { e.preventDefault(); uploadArea.classList.add('dragover'); });
uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
uploadArea.addEventListener('drop', e => {
    e.preventDefault(); uploadArea.classList.remove('dragover');
    const file = e.dataTransfer.files[0];
    if (file) { $('#docFile')[0].files = e.dataTransfer.files; showFilePreview(file); }
});

function showFilePreview(file) {
    $('#previewName').text(file.name);
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => { $('#previewImg').attr('src', e.target.result).show(); };
        reader.readAsDataURL(file);
    } else {
        $('#previewImg').attr('src', '').hide();
    }
    $('#previewBox').show();
    $('#capturedImageData').val('');
}

function clearFile() {
    $('#docFile').val('');
    $('#previewBox').hide();
    $('#previewImg').attr('src', '');
    $('#previewName').text('');
    $('#capturedImageData').val('');
}

// ── Camera ──
let mediaStream = null;
$('#btnOpenCamera').on('click', function () {
    openModal('cameraModal');
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(stream => { mediaStream = stream; document.getElementById('cameraFeed').srcObject = stream; })
        .catch(err  => { showAlert('danger', 'Cannot access camera: ' + err.message); closeModal('cameraModal'); });
});
$('#btnCapture').on('click', function () {
    const video = document.getElementById('cameraFeed');
    const canvas = document.getElementById('captureCanvas');
    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
    $('#capturedImageData').val(dataUrl);
    $('#previewImg').attr('src', dataUrl).show();
    $('#previewName').text('camera_capture.jpg');
    $('#previewBox').show();
    clearFile();
    closeCamera();
    showAlert('success', 'Photo captured successfully!');
});
function closeCamera() {
    if (mediaStream) { mediaStream.getTracks().forEach(t => t.stop()); mediaStream = null; }
    const feed = document.getElementById('cameraFeed');
    if (feed) feed.srcObject = null;
    closeModal('cameraModal');
}

// ── Save Record ──
$('#btnSaveRecord').on('click', function () {
    const form = $('#recordForm')[0];
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
    $.ajax({
        url: 'php/marriage_handler.php',
        type: 'POST',
        data: new FormData(form),
        processData: false, contentType: false, dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                closeModal('recordModal');
                showAlert('success', res.message);
                setTimeout(() => location.reload(), 1200);
            } else { showAlert('danger', res.message); }
        },
        error: function () { showAlert('danger', 'Server error. Please try again.'); },
        complete: function () { $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Save Record'); }
    });
});

// ── View Record ──
function viewRecord(id) {
    $('#viewModalBody').html('<p style="text-align:center;color:#aaa;"><i class="fas fa-spinner fa-spin"></i> Loading...</p>');
    openModal('viewModal');
    $.ajax({
        url: 'php/marriage_handler.php?action=get&id=' + id,
        type: 'GET', dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                const r = res.data;
                const husband = [r.husband_first_name, r.husband_middle_name, r.husband_last_name].filter(Boolean).join(' ');
                const wife    = [r.wife_first_name,    r.wife_middle_name,    r.wife_last_name   ].filter(Boolean).join(' ');
                let docHtml = r.document_path
                    ? `<a href="${r.document_path}" target="_blank" class="btn btn-outline btn-sm" style="margin-top:8px;"><i class="fas fa-file"></i> View Document</a>`
                    : '<span style="color:#aaa;font-size:0.82rem;">No document uploaded.</span>';
                if (r.document_path && /\.(jpg|jpeg|png)$/i.test(r.document_path)) {
                    docHtml = `<img src="${r.document_path}" class="view-doc" alt="Document"/>` + docHtml;
                }
                $('#viewModalBody').html(`
                    <div class="view-grid">
                        <div class="view-field"><div class="view-label">Registry Number</div><div class="view-value">${r.registry_number}</div></div>
                        <div class="view-field"><div class="view-label">Date Registered</div><div class="view-value">${formatDate(r.date_registered)}</div></div>
                        <div class="view-field"><div class="view-label">Date of Marriage</div><div class="view-value">${formatDate(r.date_of_marriage)}</div></div>
                        <div class="view-field"><div class="view-label">Place of Marriage</div><div class="view-value">${r.place_of_marriage}</div></div>
                        <div class="view-field" style="grid-column:span 2"><div class="view-label">Husband's Full Name</div><div class="view-value" style="font-weight:700;">${husband}</div></div>
                        <div class="view-field"><div class="view-label">Husband's Date of Birth</div><div class="view-value">${formatDate(r.husband_date_of_birth)}</div></div>
                        <div class="view-field" style="grid-column:span 2"><div class="view-label">Wife's Full Name</div><div class="view-value" style="font-weight:700;">${wife}</div></div>
                        <div class="view-field"><div class="view-label">Wife's Date of Birth</div><div class="view-value">${formatDate(r.wife_date_of_birth)}</div></div>
                        <div class="view-field" style="grid-column:span 2"><div class="view-label">Remarks</div><div class="view-value">${r.remarks || '—'}</div></div>
                        <div class="view-field"><div class="view-label">Added By</div><div class="view-value">${r.added_by || '—'}</div></div>
                        <div class="view-field" style="grid-column:span 2"><div class="view-label">Scanned Document</div><div class="view-value">${docHtml}</div></div>
                    </div>`);
            } else {
                $('#viewModalBody').html('<p style="color:#c0392b;">Failed to load record.</p>');
            }
        }
    });
}

// ── Edit Record ──
function editRecord(id) {
    $.ajax({
        url: 'php/marriage_handler.php?action=get&id=' + id,
        type: 'GET', dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                const r = res.data;
                $('#record_id').val(r.id);
                $('#formAction').val('edit');
                $('#modalTitle').html('<i class="fas fa-pen"></i> Edit Marriage Record');
                $('#registry_number').val(r.registry_number);
                $('#date_registered').val(r.date_registered);
                $('#date_of_marriage').val(r.date_of_marriage);
                $('#place_of_marriage').val(r.place_of_marriage);
                $('#husband_first_name').val(r.husband_first_name);
                $('#husband_middle_name').val(r.husband_middle_name);
                $('#husband_last_name').val(r.husband_last_name);
                $('#husband_date_of_birth').val(r.husband_date_of_birth);
                $('#wife_first_name').val(r.wife_first_name);
                $('#wife_middle_name').val(r.wife_middle_name);
                $('#wife_last_name').val(r.wife_last_name);
                $('#wife_date_of_birth').val(r.wife_date_of_birth);
                $('#remarks').val(r.remarks);
                clearFile();
                openModal('recordModal');
            }
        }
    });
}

// ── Delete Record ──
let deleteId = null;
function deleteRecord(id, regNum) {
    deleteId = id;
    $('#deleteRegNum').text(regNum);
    openModal('deleteModal');
}
$('#btnConfirmDelete').on('click', function () {
    if (!deleteId) return;
    const $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Deleting...');
    $.ajax({
        url: 'php/marriage_handler.php',
        type: 'POST',
        data: { action: 'delete', record_id: deleteId },
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

// ── Date Format Helper ──
function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
}
</script>

</body>
</html>