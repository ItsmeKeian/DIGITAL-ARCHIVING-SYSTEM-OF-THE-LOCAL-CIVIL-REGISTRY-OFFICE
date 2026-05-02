<?php


require_once 'authentication/session.php';
require_once 'authentication/db_connect.php';

requireLogin();

$user_role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];
$user_id   = $_SESSION['user_id'];

// ── Fetch all birth records ──
$search      = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_year = isset($_GET['year'])   ? trim($_GET['year'])   : '';

$sql    = "SELECT b.*, u.full_name as added_by 
           FROM birth_records b 
           LEFT JOIN users u ON b.created_by = u.id
           WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (b.child_first_name LIKE ? 
               OR b.child_last_name  LIKE ? 
               OR b.registry_number  LIKE ?
               OR b.father_name      LIKE ?
               OR b.mother_name      LIKE ?)";
    $s = '%' . $search . '%';
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}

if (!empty($filter_year)) {
    $sql .= " AND YEAR(b.date_of_birth) = ?";
    $params[] = $filter_year;
}

$sql .= " ORDER BY b.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();
$total   = count($records);

// ── Get available years for filter ──
$yr_stmt = $pdo->query("SELECT DISTINCT YEAR(date_of_birth) as yr FROM birth_records ORDER BY yr DESC");
$years   = $yr_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>LCR Digital Archiving System | Birth Records</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/main.css"/>
    <link rel="stylesheet" href="assets/css/birth_records.css"/>
 
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
            <li><a href="birth_records.php" class="nav-link active" data-tooltip="Birth Records"><i class="fas fa-baby nav-icon"></i><span class="nav-text">Birth Records</span></a></li>
            <li><a href="marriage_records.php" class="nav-link" data-tooltip="Marriage Records"><i class="fas fa-heart nav-icon"></i><span class="nav-text">Marriage Records</span></a></li>
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
        <div class="topbar-title">Birth Records</div>
        <div class="topbar-right">
            <span class="topbar-date"><i class="fas fa-calendar-alt"></i> <?= date('F d, Y') ?></span>
            <div class="topbar-user">
                <div class="topbar-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
                <span><?= htmlspecialchars($full_name) ?></span>
            </div>
        </div>
    </header>

    <main class="page-content">

        <!-- Page Alert -->
        <div class="page-alert" id="pageAlert"></div>

        <!-- Page Header -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
            <div>
                <h2 style="font-family:'Cinzel',serif;font-size:1.1rem;color:var(--primary);margin-bottom:3px;">Birth Records</h2>
                <p style="font-size:0.78rem;color:var(--text-muted);">Total: <strong><?= $total ?></strong> record(s) found</p>
            </div>
            <button class="btn btn-primary" id="btnAddRecord">
                <i class="fas fa-plus"></i> Add Birth Record
            </button>
        </div>

        <!-- Search & Filter -->
        <div class="search-bar">
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" class="search-input" id="searchInput" placeholder="Search by name, registry no., parent..." value="<?= htmlspecialchars($search) ?>"/>
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
            <a href="birth_records.php" class="btn btn-sm" style="background:#f0f0f0;color:#555;">
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
                            <th>Child's Name</th>
                            <th>Sex</th>
                            <th>Date of Birth</th>
                            <th>Place of Birth</th>
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
                                No birth records found.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($records as $i => $rec): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= htmlspecialchars($rec['registry_number']) ?></strong></td>
                            <td><?= htmlspecialchars($rec['child_first_name'] . ' ' . $rec['child_middle_name'] . ' ' . $rec['child_last_name']) ?></td>
                            <td>
                                <span class="badge <?= $rec['child_sex'] === 'Male' ? 'badge-birth' : '' ?>" 
                                      style="<?= $rec['child_sex'] === 'Female' ? 'background:rgba(106,27,154,0.1);color:#6A1B9A;' : '' ?>">
                                    <?= $rec['child_sex'] ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($rec['date_of_birth'])) ?></td>
                            <td><?= htmlspecialchars($rec['place_of_birth']) ?></td>
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
                                    <button class="btn-icon btn-view" title="View" onclick="viewRecord(<?= $rec['id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon btn-edit" title="Edit" onclick="editRecord(<?= $rec['id'] ?>)">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <?php if ($user_role === 'admin'): ?>
                                    <button class="btn-icon btn-delete" title="Delete" onclick="deleteRecord(<?= $rec['id'] ?>, '<?= htmlspecialchars($rec['registry_number']) ?>')">
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
     ADD / EDIT RECORD MODAL
════════════════════════════════════════ -->
<div class="modal-overlay" id="recordModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="modalTitle"><i class="fas fa-baby"></i> Add Birth Record</div>
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
                        <input type="text" name="registry_number" id="registry_number" class="form-control" placeholder="e.g. 2024-B-001" required/>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date Registered <span style="color:red">*</span></label>
                        <input type="date" name="date_registered" id="date_registered" class="form-control" required/>
                    </div>

                    <!-- Child Info -->
                    <div class="section-divider">Child's Information</div>

                    <div class="form-group">
                        <label class="form-label">First Name <span style="color:red">*</span></label>
                        <input type="text" name="child_first_name" id="child_first_name" class="form-control" placeholder="First name" required/>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Middle Name</label>
                        <input type="text" name="child_middle_name" id="child_middle_name" class="form-control" placeholder="Middle name"/>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name <span style="color:red">*</span></label>
                        <input type="text" name="child_last_name" id="child_last_name" class="form-control" placeholder="Last name" required/>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sex <span style="color:red">*</span></label>
                        <select name="child_sex" id="child_sex" class="form-control" required>
                            <option value="">-- Select --</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth <span style="color:red">*</span></label>
                        <input type="date" name="date_of_birth" id="date_of_birth" class="form-control" required/>
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">Place of Birth <span style="color:red">*</span></label>
                        <input type="text" name="place_of_birth" id="place_of_birth" class="form-control" placeholder="e.g. San Julian District Hospital" required/>
                    </div>

                    <!-- Parents Info -->
                    <div class="section-divider">Parents' Information</div>

                    <div class="form-group">
                        <label class="form-label">Father's Name</label>
                        <input type="text" name="father_name" id="father_name" class="form-control" placeholder="Full name of father"/>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mother's Name</label>
                        <input type="text" name="mother_name" id="mother_name" class="form-control" placeholder="Full name of mother"/>
                    </div>

                    <!-- Remarks -->
                    <div class="form-group col-span-2">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" id="remarks" class="form-control" placeholder="Optional notes..."></textarea>
                    </div>

                    <!-- Document Upload -->
                    <div class="section-divider">Document / Scanned Copy</div>

                    <div class="form-group col-span-2">
                        <label class="form-label">Upload Scanned Document (PDF, JPG, PNG)</label>

                        <!-- Upload Drop Area -->
                        <div class="upload-area" id="uploadArea" onclick="$('#docFile').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to browse or drag & drop your file here</p>
                            <span>Supported: PDF, JPG, PNG (Max: 5MB)</span>
                        </div>
                        <input type="file" id="docFile" name="document" accept=".pdf,.jpg,.jpeg,.png" style="display:none"/>

                        <!-- Camera Capture Button -->
                        <button type="button" class="camera-btn" id="btnOpenCamera">
                            <i class="fas fa-camera"></i> Use Camera / Scanner
                        </button>

                        <!-- File Preview -->
                        <div class="preview-box" id="previewBox">
                            <img id="previewImg" src="" alt="Preview"/>
                            <div class="preview-name">
                                <span id="previewName">filename.jpg</span>
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
            <button class="btn btn-primary" id="btnSaveRecord">
                <i class="fas fa-save"></i> Save Record
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     VIEW RECORD MODAL
════════════════════════════════════════ -->
<div class="modal-overlay" id="viewModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-eye"></i> View Birth Record</div>
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
     DELETE CONFIRM MODAL
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
            <canvas id="captureCanvas"></canvas>
            <div style="margin-top:14px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                <button class="btn btn-primary" id="btnCapture"><i class="fas fa-camera"></i> Capture Photo</button>
                <button class="btn btn-outline" onclick="closeCamera()">Cancel</button>
            </div>
            <p style="font-size:0.72rem;color:#aaa;margin-top:10px;">
                Position the document in front of your camera or scanner, then click Capture.
            </p>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="assets/js/main.js"></script>
<script src="assets/js/birth_records.js"></script>

</body>
</html>