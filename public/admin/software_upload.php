<?php
session_start();
require_once '../../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/dark_mode.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/index.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ../auth/index.php');
    exit;
}

$successMsg = '';
$errorMsg = '';

// ── Handle CSV Template Download ──
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="software_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['524', '526', '528', '530', '542', '544']);
    fputcsv($out, ['Python', 'C#', 'PHP', 'Java', 'C++', 'NodeJS']);
    fputcsv($out, ['VSCode', 'Visual Studio', 'XAMPP', 'Eclipse', 'CodeBlocks', 'Docker']);
    fputcsv($out, ['Git', 'SQL Server', 'MySQL Workbench', 'NetBeans', 'GitKraken', 'Postman']);
    fclose($out);
    exit;
}

// ── Handle Delete All Software ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_all') {
    $conn->query("DELETE FROM lab_software");
    $successMsg = 'All software records have been cleared.';
}

// ── Handle Register & Assign Software ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_assign') {
    $softwareName = trim($_POST['software_name'] ?? '');
    $version = trim($_POST['version'] ?? '');
    if ($version !== '') {
        $softwareName .= " ($version)";
    }
    $selectedLabs = $_POST['labs'] ?? [];

    if (empty($softwareName)) {
        $errorMsg = 'Software name is required.';
    } elseif (empty($selectedLabs)) {
        $errorMsg = 'Please select at least one laboratory room.';
    } else {
        $stmt = $conn->prepare("INSERT IGNORE INTO lab_software (lab, software_name) VALUES (?, ?)");
        $inserted = 0;
        foreach ($selectedLabs as $lab) {
            $stmt->bind_param('ss', $lab, $softwareName);
            if ($stmt->execute() && $conn->affected_rows > 0) {
                $inserted++;
            }
        }
        $stmt->close();
        if ($inserted > 0) {
            $successMsg = "Successfully registered '$softwareName' and assigned to $inserted lab(s).";
        } else {
            $successMsg = "Software '$softwareName' is already assigned to all chosen labs.";
        }
    }
}

// ── Handle Delete Single Software from Lab ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_software') {
    $lab = trim($_POST['lab'] ?? '');
    $softwareName = trim($_POST['software_name'] ?? '');
    if ($lab !== '' && $softwareName !== '') {
        $stmt = $conn->prepare("DELETE FROM lab_software WHERE lab = ? AND software_name = ?");
        $stmt->bind_param('ss', $lab, $softwareName);
        if ($stmt->execute() && $conn->affected_rows > 0) {
            $successMsg = "Successfully removed '$softwareName' from Room $lab.";
        } else {
            $errorMsg = "Failed to remove '$softwareName' from Room $lab.";
        }
        $stmt->close();
    }
}

// ── Handle Add Existing Software to Lab ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_existing_to_lab') {
    $lab = trim($_POST['lab'] ?? '');
    $softwareName = trim($_POST['software_name'] ?? '');
    if ($lab !== '' && $softwareName !== '') {
        $stmt = $conn->prepare("INSERT IGNORE INTO lab_software (lab, software_name) VALUES (?, ?)");
        $stmt->bind_param('ss', $lab, $softwareName);
        if ($stmt->execute() && $conn->affected_rows > 0) {
            $successMsg = "Assigned '$softwareName' to Room $lab.";
        } else {
            $successMsg = "'$softwareName' is already assigned to Room $lab.";
        }
        $stmt->close();
    }
}

// ── Handle CSV Upload ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'File upload failed. Please try again.';
    } elseif (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
        $errorMsg = 'Please upload a valid .csv file.';
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle) {
            // First row = lab room headers
            $headers = fgetcsv($handle);
            if (!$headers || count($headers) < 1) {
                $errorMsg = 'CSV file is empty or has no headers.';
            } else {
                $headers = array_map('trim', $headers);
                $insertCount = 0;

                $stmt = $conn->prepare("INSERT IGNORE INTO lab_software (lab, software_name) VALUES (?, ?)");

                while (($row = fgetcsv($handle)) !== false) {
                    foreach ($row as $colIdx => $softwareName) {
                        $softwareName = trim($softwareName);
                        if ($softwareName !== '' && isset($headers[$colIdx]) && $headers[$colIdx] !== '') {
                            $lab = $headers[$colIdx];
                            $stmt->bind_param('ss', $lab, $softwareName);
                            if ($stmt->execute()) {
                                if ($conn->affected_rows > 0) {
                                    $insertCount++;
                                }
                            }
                        }
                    }
                }
                $stmt->close();
                $successMsg = "CSV imported successfully! $insertCount new software entries added.";
            }
            fclose($handle);
        } else {
            $errorMsg = 'Could not read the uploaded file.';
        }
    }
}

// ── Fetch current software by lab ──
$labSoftware = [];
$standardLabs = ['524', '526', '528', '530', '542', '544'];
foreach ($standardLabs as $lab) {
    $labSoftware[$lab] = [];
}
$r = $conn->query("SELECT lab, software_name FROM lab_software ORDER BY lab, software_name");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $labSoftware[$row['lab']][] = $row['software_name'];
    }
}

$totalSoftware = 0;
foreach ($labSoftware as $items) {
    $totalSoftware += count($items);
}

// ── Fetch all unique software names in system ──
$allUniqueSoftware = [];
$uniqueQuery = $conn->query("SELECT DISTINCT software_name FROM lab_software ORDER BY software_name");
if ($uniqueQuery) {
    while ($urow = $uniqueQuery->fetch_assoc()) {
        $allUniqueSoftware[] = $urow['software_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Software Management - Admin</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/ccs.png">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/shared/global.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/shared/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/admin/admin_dashboard.css?v=<?= time() ?>">
    <style>
        body {
            overflow: auto !important;
            height: auto !important;
        }
        .sw-page { padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .sw-page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .sw-page-header h1 { font-family: 'Manrope', sans-serif; font-size: 1.75rem; font-weight: 800; color: var(--primary); }
        .sw-page-header .sw-stats { display: flex; gap: 1.5rem; }
        .sw-stat-chip { background: var(--primary); color: #fff; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .sw-stat-chip .material-symbols-outlined { font-size: 18px; }

        .sw-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        @media (max-width: 900px) { .sw-grid { grid-template-columns: 1fr; } }

        .sw-upload-card {
            background: #fff; border-radius: 16px; border: 1px solid var(--outline-variant);
            padding: 2rem; display: flex; flex-direction: column; gap: 1.25rem;
        }
        .sw-upload-card h3 { font-family: 'Manrope', sans-serif; font-weight: 700; font-size: 1.1rem; color: var(--primary); display: flex; align-items: center; gap: 8px; }
        .sw-upload-card h3 .material-symbols-outlined { font-size: 22px; color: var(--secondary); }

        .sw-dropzone {
            border: 2px dashed var(--outline-variant); border-radius: 12px; padding: 2.5rem 1.5rem;
            text-align: center; transition: all 0.25s ease; cursor: pointer; position: relative;
            background: var(--surface-container-low);
        }
        .sw-dropzone:hover, .sw-dropzone.drag-over { border-color: var(--primary); background: rgba(0,42,92,0.04); }
        .sw-dropzone .material-symbols-outlined { font-size: 48px; color: var(--outline); margin-bottom: 8px; }
        .sw-dropzone p { color: var(--on-surface-variant); font-size: 14px; margin-top: 4px; }
        .sw-dropzone .sw-file-name { font-weight: 600; color: var(--primary); margin-top: 8px; display: none; }
        .sw-dropzone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }

        .sw-btn-row { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .sw-btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 10px;
            font-size: 13px; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
        }
        .sw-btn-primary { background: var(--primary); color: #fff; }
        .sw-btn-primary:hover { background: var(--primary-container); }
        .sw-btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
        .sw-btn-outline { background: #fff; color: var(--primary); border: 1.5px solid var(--primary); }
        .sw-btn-outline:hover { background: rgba(0,42,92,0.06); }
        .sw-btn-danger { background: #fff; color: var(--secondary); border: 1.5px solid var(--secondary); }
        .sw-btn-danger:hover { background: rgba(182,23,30,0.06); }
        .sw-btn .material-symbols-outlined { font-size: 18px; }

        .sw-msg { padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 8px; }
        .sw-msg-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .sw-msg-error { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }

        .sw-table-card {
            background: #fff; border-radius: 16px; border: 1px solid var(--outline-variant); overflow: hidden;
        }
        .sw-table-card h3 { font-family: 'Manrope', sans-serif; font-weight: 700; font-size: 1.1rem; color: var(--primary); padding: 1.25rem 1.5rem 0; display: flex; align-items: center; gap: 8px; }
        .sw-table-card h3 .material-symbols-outlined { font-size: 22px; color: var(--secondary); }
        .sw-table-wrapper { padding: 1rem 1.5rem 1.5rem; overflow-x: auto; }

        .sw-lab-card ul { list-style: none; padding: 0; margin: 0; }
        .sw-lab-card ul li { font-size: 12.5px; color: var(--on-surface-variant); padding: 5px 0; }
        .sw-lab-empty { font-size: 12px; color: var(--outline); font-style: italic; margin-bottom: 10px; display: inline-block; }

        .sw-clear-form { display: inline; }

        /* Form Controls for Registration */
        .sw-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .sw-form-group label {
            font-family: 'Manrope', sans-serif;
            font-size: 12px;
            font-weight: 600;
            color: var(--on-surface-variant);
        }
        .sw-form-group input[type="text"] {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid var(--outline-variant);
            font-size: 13px;
            background: var(--surface-container-low);
            color: var(--on-surface);
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .sw-form-group input[type="text"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,42,92,0.1);
        }
        .sw-checkbox-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 4px;
        }
        .sw-checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--on-surface);
            cursor: pointer;
            padding: 8px 10px;
            background: var(--surface-container-low);
            border: 1px solid var(--outline-variant);
            border-radius: 8px;
            transition: all 0.2s;
            font-weight: 500;
        }
        .sw-checkbox-label:hover {
            border-color: var(--primary);
            background: rgba(0,42,92,0.03);
        }
        .sw-checkbox-label input[type="checkbox"] {
            accent-color: var(--primary);
            width: 15px;
            height: 15px;
            cursor: pointer;
        }
        .sw-lab-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.25rem; padding: 1rem 1.5rem 1.5rem; }
    </style>
</head>

<body>

    <nav class="academic-ledger-navbar">
        <div class="nav-container">
            <div class="brand">
                <h1 class="brand-title">CCS Sit-in Monitoring System (ADMIN)</h1>
            </div>
            <div class="nav-links">
                <a class="nav-link" href="admin_dashboard.php">Home</a>
                <a class="nav-link" href="student_information.php">Student Information</a>
                <a class="nav-link" href="active_sessions.php">Active Sessions</a>
                <a class="nav-link" href="leaderboard.php">Leaderboard</a>
                <a class="nav-link" href="reservations_admin.php">Reservations</a>
                <a class="nav-link active" href="software_upload.php">Software & Labs</a>
                <?php renderDarkModeToggle(); ?>
                <a class="nav-logout" href="software_upload.php?logout=1">Logout</a>
            </div>
        </div>
    </nav>

    <main class="ledger-main">
        <div class="sw-page">
            <div class="sw-page-header">
                <h1>
                    <span class="material-symbols-outlined" style="vertical-align: -4px; font-size: 28px;">apps</span>
                    Software & Lab Management
                </h1>
                <div class="sw-stats">
                    <div class="sw-stat-chip">
                        <span class="material-symbols-outlined">inventory_2</span>
                        <?= $totalSoftware ?> Software Entries
                    </div>
                    <div class="sw-stat-chip" style="background: var(--secondary);">
                        <span class="material-symbols-outlined">door_open</span>
                        <?= count($standardLabs) ?> Labs
                    </div>
                </div>
            </div>

            <?php if ($successMsg): ?>
                <div class="sw-msg sw-msg-success">
                    <span class="material-symbols-outlined">check_circle</span>
                    <?= esc($successMsg) ?>
                </div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
                <div class="sw-msg sw-msg-error">
                    <span class="material-symbols-outlined">error</span>
                    <?= esc($errorMsg) ?>
                </div>
            <?php endif; ?>

            <div class="sw-grid" style="margin-top: 1rem;">
                <div class="sw-left-col" style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <!-- Register & Assign Card -->
                    <div class="sw-upload-card">
                        <h3>
                            <span class="material-symbols-outlined" style="color: var(--primary);">app_registration</span>
                            Register & Assign Software
                        </h3>
                        <form method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
                            <input type="hidden" name="action" value="register_assign">
                            <div class="sw-form-group">
                                <label for="software_name">Software Name</label>
                                <input type="text" id="software_name" name="software_name" placeholder="e.g. Visual Studio Code" required>
                            </div>
                            <div class="sw-form-group">
                                <label for="version">Version (Optional)</label>
                                <input type="text" id="version" name="version" placeholder="e.g. 2026.1">
                            </div>
                            <div class="sw-form-group">
                                <label>Assign to Labs (Select all that apply)</label>
                                <div class="sw-checkbox-grid">
                                    <?php foreach ($standardLabs as $lab): ?>
                                        <label class="sw-checkbox-label">
                                            <input type="checkbox" name="labs[]" value="<?= esc($lab) ?>">
                                            Lab <?= esc($lab) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="sw-btn-row" style="margin-top: 0.5rem;">
                                <button type="submit" class="sw-btn sw-btn-primary" style="width: 100%; justify-content: center;">
                                    <span class="material-symbols-outlined">add_task</span>
                                    Register and Assign
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Upload Card -->
                    <div class="sw-upload-card">
                        <h3>
                            <span class="material-symbols-outlined">upload_file</span>
                            Import Software via CSV
                        </h3>
                        <p style="font-size: 13px; color: var(--on-surface-variant); line-height: 1.6;">
                            Upload a CSV file where each column header is a lab room number (e.g. <strong>524, 526, 528</strong>)
                            and rows below list the software installed in that lab.
                        </p>
                        <form method="POST" enctype="multipart/form-data" id="csvUploadForm">
                            <div class="sw-dropzone" id="dropzone">
                                <span class="material-symbols-outlined">cloud_upload</span>
                                <p>Drag & drop your CSV file here, or <strong>click to browse</strong></p>
                                <div class="sw-file-name" id="fileName"></div>
                                <input type="file" name="csv_file" id="csvFileInput" accept=".csv" required>
                            </div>
                            <div class="sw-btn-row" style="margin-top: 1rem;">
                                <button type="submit" class="sw-btn sw-btn-primary" id="uploadBtn" disabled>
                                    <span class="material-symbols-outlined">upload</span>
                                    Upload & Import
                                </button>
                                <a href="software_upload.php?download_template=1" class="sw-btn sw-btn-outline">
                                    <span class="material-symbols-outlined">download</span>
                                    Download Template
                                </a>
                            </div>
                        </form>
                        <?php if ($totalSoftware > 0): ?>
                            <form method="POST" class="sw-clear-form" id="clearAllForm">
                                <input type="hidden" name="action" value="clear_all">
                                <button type="button" class="sw-btn sw-btn-danger" onclick="openModal('confirmClearModal')">
                                    <span class="material-symbols-outlined">delete_sweep</span>
                                    Clear All Software
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Current Software Display -->
                <div class="sw-table-card">
                    <h3>
                        <span class="material-symbols-outlined">dns</span>
                        Current Software by Lab
                    </h3>
                    <div class="sw-lab-grid">
                        <?php foreach ($labSoftware as $lab => $items): ?>
                            <div class="sw-lab-card" style="background: var(--surface-container-low); border-radius: 12px; padding: 1.25rem; border: 1px solid var(--outline-variant); transition: all 0.2s;">
                                <h4 style="font-family: 'Manrope', sans-serif; font-weight: 700; font-size: 14px; color: var(--primary); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                                    <span class="material-symbols-outlined">computer</span>
                                    Room <?= esc($lab) ?>
                                </h4>
                                <?php if (empty($items)): ?>
                                    <span class="sw-lab-empty">No software</span>
                                <?php else: ?>
                                    <ul style="margin-bottom: 12px;">
                                        <?php foreach ($items as $sw): ?>
                                            <li style="display: flex; justify-content: space-between; align-items: center; gap: 4px;">
                                                <span><?= esc($sw) ?></span>
                                                <button type="button" onclick="confirmDeleteSoftware('<?= esc($lab) ?>', '<?= esc($sw) ?>')" style="background:transparent; border:none; padding:2px; cursor:pointer; color:var(--secondary); display:flex; align-items:center;" title="Remove software">
                                                    <span class="material-symbols-outlined" style="font-size:15px;">close</span>
                                                </button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <!-- Quick Add existing dropdown -->
                                <form method="POST" style="margin-top: 10px; display: flex; gap: 6px; align-items: center; width: 100%;">
                                    <input type="hidden" name="action" value="add_existing_to_lab">
                                    <input type="hidden" name="lab" value="<?= esc($lab) ?>">
                                    <select name="software_name" style="flex: 1; font-size: 11px; padding: 4px 8px; border: 1px solid var(--outline-variant); border-radius: 6px; background: var(--surface-container); color: var(--on-surface); height: 28px;" required>
                                        <option value="" disabled selected>+ Add existing...</option>
                                        <?php foreach ($allUniqueSoftware as $uniqueSw): ?>
                                            <?php if (!in_array($uniqueSw, $items)): ?>
                                                <option value="<?= esc($uniqueSw) ?>"><?= esc($uniqueSw) ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="sw-btn sw-btn-primary" style="padding: 0 10px; height: 28px; border-radius: 6px; font-size: 11px;" title="Add to Lab">Add</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Confirm Clear Modal Overlay -->
    <div class="modal-overlay" id="confirmClearModal">
        <div class="modal-box confirmation-box" style="max-width: 400px; text-align: center;">
            <div class="modal-header" style="flex-direction: column; justify-content: center; align-items: center; border-bottom: none; padding: 8px;">
                <span class="material-symbols-outlined" style="font-size: 50px; color: #d32f2f;">warning</span>
            </div>
            <div class="modal-body" style="padding: 5px 15px 15px;">
                <h3 style="margin: 0 0 5px; color: var(--on-surface); font-size: 18px; font-family: 'Manrope', sans-serif;">Confirm Clear</h3>
                <p style="margin: 0; color: var(--on-surface-variant); font-size: 14px; font-family: 'Inter', sans-serif;">Are you sure you want to clear ALL software records? This cannot be undone.</p>
            </div>
            <div class="modal-actions" style="justify-content: center; background: var(--surface-container-low); padding: 12px; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                <button type="button" class="modal-btn btn-cancel" onclick="closeModal('confirmClearModal')">Cancel</button>
                <button type="button" class="modal-btn btn-confirm" id="confirmClearBtn" style="background-color: #d32f2f; color: white;">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Confirm Delete Modal Overlay -->
    <div class="modal-overlay" id="confirmDeleteModal">
        <div class="modal-box confirmation-box" style="max-width: 400px; text-align: center;">
            <div class="modal-header" style="flex-direction: column; justify-content: center; align-items: center; border-bottom: none; padding: 8px;">
                <span class="material-symbols-outlined" style="font-size: 50px; color: #d32f2f;">warning</span>
            </div>
            <div class="modal-body" style="padding: 5px 15px 15px;">
                <h3 style="margin: 0 0 5px; color: var(--on-surface); font-size: 18px; font-family: 'Manrope', sans-serif;">Confirm Removal</h3>
                <p id="deleteModalMessage" style="margin: 0; color: var(--on-surface-variant); font-size: 14px; font-family: 'Inter', sans-serif;">Are you sure you want to remove this software?</p>
            </div>
            <div class="modal-actions" style="justify-content: center; background: var(--surface-container-low); padding: 12px; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                <button type="button" class="modal-btn btn-cancel" onclick="closeModal('confirmDeleteModal')">Cancel</button>
                <button type="button" class="modal-btn btn-confirm" id="confirmDeleteBtn" style="background-color: #d32f2f; color: white;">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Hidden Form for Specific Software Delete -->
    <form id="deleteSoftwareForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete_software">
        <input type="hidden" name="lab" id="delete_software_lab" value="">
        <input type="hidden" name="software_name" id="delete_software_name" value="">
    </form>

    <script>
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('csvFileInput');
        const fileName = document.getElementById('fileName');
        const uploadBtn = document.getElementById('uploadBtn');

        fileInput.addEventListener('change', function () {
            if (this.files.length > 0) {
                fileName.textContent = this.files[0].name;
                fileName.style.display = 'block';
                uploadBtn.disabled = false;
            } else {
                fileName.style.display = 'none';
                uploadBtn.disabled = true;
            }
        });

        dropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });

        dropzone.addEventListener('dragleave', function () {
            this.classList.remove('drag-over');
        });

        dropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                fileName.textContent = e.dataTransfer.files[0].name;
                fileName.style.display = 'block';
                uploadBtn.disabled = false;
            }
        });

        // Modal Functions
        function openModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('active');
        }
        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('active');
        }

        const confirmClearBtn = document.getElementById('confirmClearBtn');
        if (confirmClearBtn) {
            confirmClearBtn.addEventListener('click', function() {
                document.getElementById('clearAllForm').submit();
            });
        }

        // Specific Delete Modal Functionality
        let activeDeleteLab = '';
        let activeDeleteSoftware = '';

        function confirmDeleteSoftware(lab, softwareName) {
            activeDeleteLab = lab;
            activeDeleteSoftware = softwareName;
            document.getElementById('deleteModalMessage').textContent = `Are you sure you want to remove ${softwareName} from Room ${lab}?`;
            openModal('confirmDeleteModal');
        }

        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', function() {
                document.getElementById('delete_software_lab').value = activeDeleteLab;
                document.getElementById('delete_software_name').value = activeDeleteSoftware;
                document.getElementById('deleteSoftwareForm').submit();
            });
        }

        // Auto-dismiss alerts after 3 seconds
        const swMessages = document.querySelectorAll('.sw-msg');
        swMessages.forEach(msg => {
            setTimeout(() => {
                msg.style.transition = 'opacity 0.6s ease, transform 0.6s ease, margin-top 0.6s ease, padding 0.6s ease';
                msg.style.opacity = '0';
                msg.style.transform = 'translateY(-10px)';
                msg.style.marginTop = '-50px'; // Collapse space
                msg.style.padding = '0';
                setTimeout(() => msg.remove(), 600);
            }, 3000);
        });
    </script>

    <?php renderDarkModeScript(); ?>

</body>
</html>
