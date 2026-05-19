<?php
session_start();
require_once '../../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/dark_mode.php';

// Guard: admin only
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ../auth/index.php');
    exit;
}

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminId = (int) $_SESSION['admin_id'];

// ── Statistics ──
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

$r = $conn->query("SELECT COUNT(*) AS c FROM reservations WHERE status='Pending'");
if ($r) {
    $pendingCount = (int) $r->fetch_assoc()['c'];
}

$r = $conn->query("SELECT COUNT(*) AS c FROM reservations WHERE status='Approved'");
if ($r) {
    $approvedCount = (int) $r->fetch_assoc()['c'];
}

$r = $conn->query("SELECT COUNT(*) AS c FROM reservations WHERE status='Rejected'");
if ($r) {
    $rejectedCount = (int) $r->fetch_assoc()['c'];
}

// ── Handle toggle reservation system ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_reservations') {
    $newVal = ($_POST['enabled'] ?? '1') === '1' ? '1' : '0';
    $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'reservations_enabled'");
    if ($stmt) {
        $stmt->bind_param('s', $newVal);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: reservations_admin.php');
    exit;
}

// ── Handle Approve/Reject POST actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $resId = (int)($_POST['reservation_id'] ?? 0);

    if ($resId > 0) {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE reservations SET status = 'Approved', admin_note = NULL WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $resId);
                if ($stmt->execute()) {
                    $_SESSION['res_success'] = "Reservation Approved successfully!";
                } else {
                    $_SESSION['res_error'] = "Failed to approve reservation. Please try again.";
                }
                $stmt->close();
            }
        } elseif ($action === 'reject') {
            $adminNote = trim($_POST['admin_note'] ?? '');
            $stmt = $conn->prepare("UPDATE reservations SET status = 'Rejected', admin_note = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('si', $adminNote, $resId);
                if ($stmt->execute()) {
                    $_SESSION['res_success'] = "Reservation Rejected successfully!";
                } else {
                    $_SESSION['res_error'] = "Failed to reject reservation. Please try again.";
                }
                $stmt->close();
            }
        }
    }
    header('Location: reservations_admin.php');
    exit;
}

// ── Fetch reservations ──
$reservationsList = [];
$q = "SELECT r.id, r.user_id, r.purpose, r.lab, r.pc_number, r.reservation_date, r.reservation_time, r.status, r.admin_note, r.created_at,
             u.id_number, u.first_name, u.last_name,
             (SELECT COUNT(*) + 1 FROM reservations r2 WHERE r2.id < r.id) as display_id
      FROM reservations r
      JOIN users u ON r.user_id = u.id
      WHERE r.status = 'Pending'
      ORDER BY r.created_at ASC";
$res = $conn->query($q);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $reservationsList[] = $row;
    }
}

// Session message flashers
$successMsg = $_SESSION['res_success'] ?? '';
$errorMsg = $_SESSION['res_error'] ?? '';
unset($_SESSION['res_success'], $_SESSION['res_error']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Management</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/ccs.png">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/shared/global.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/shared/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/admin/reservations_admin.css?v=<?= time() ?>">
</head>

<body>

    <nav class="academic-ledger-navbar">
        <div class="nav-container">
            <div class="brand">
                <h1 class="brand-title">CCS Sit-in Monitoring System (ADMIN)</h1>
            </div>
            <div class="nav-links">
                <button class="nav-link search-icon-btn" type="button" onclick="openModal('searchModal')" aria-label="Search" title="Search Student" style="background: transparent; border: none; padding: 8px 4px; display: inline-block; cursor: pointer; line-height: 1; vertical-align: baseline;">
                    <span class="material-symbols-outlined" style="font-size: 20px; vertical-align: -3px; display: inline-block;">search</span>
                </button>
                <a class="nav-link" href="admin_dashboard.php">Home</a>
                <a class="nav-link" href="student_information.php">Student Information</a>
                <a class="nav-link" href="active_sessions.php">Active Sessions</a>
                <a class="nav-link" href="leaderboard.php">Leaderboard</a>
                <a class="nav-link active" href="reservations_admin.php">Reservations</a>
                <a class="nav-link" href="software_upload.php">Software & Labs</a>

                <div class="nav-profile-dropdown">
                    <button class="profile-dropdown-btn" id="profileDropdownBtn" aria-haspopup="true" aria-expanded="false">
                        <div class="avatar-circle">
                            <span class="material-symbols-outlined">person</span>
                        </div>
                        <span class="admin-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'admin') ?></span>
                        <span class="material-symbols-outlined dropdown-arrow">expand_more</span>
                    </button>
                    <div class="profile-dropdown-menu" id="profileDropdownMenu">
                        <div class="dropdown-item theme-switch-item">
                            <div class="item-label-group">
                                <span class="material-symbols-outlined">dark_mode</span>
                                <span>Theme</span>
                            </div>
                            <?php renderDarkModeToggle(); ?>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item logout-item" href="?logout=1">
                            <span class="material-symbols-outlined">logout</span>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <?php include 'search_student_modal.php'; ?>
    <?php include 'sitin_form_modal.php'; ?>

    <script>
        function openModal(id) {
            const modal = document.getElementById(id);
            if (modal) modal.classList.add('active');
        }
        function closeModal(id) {
            const modal = document.getElementById(id);
            if (modal) modal.classList.remove('active');
        }
    </script>

    <main>
        <header class="history-header" style="align-items: center; margin-bottom: 25px;">
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <h1 style="margin-top: 0; margin-bottom: 0; line-height: 1.1;">Reservation Management</h1>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <?php
                    $resEnabled = '1';
                    $r = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'reservations_enabled'");
                    if ($r && $row = $r->fetch_assoc()) { $resEnabled = $row['setting_value']; }
                    ?>
                    <form method="POST" style="display:flex;align-items:center;gap:10px;margin:0;">
                        <input type="hidden" name="action" value="toggle_reservations">
                        <input type="hidden" name="enabled" value="<?= $resEnabled === '1' ? '0' : '1' ?>">
                        <span style="font-size:13px;font-weight:600;color:<?= $resEnabled === '1' ? '#2e7d32' : '#c62828' ?>;">
                            Reservations: <?= $resEnabled === '1' ? 'Enabled' : 'Disabled' ?>
                        </span>
                        <button type="submit" style="position:relative;width:48px;height:26px;border-radius:13px;border:none;cursor:pointer;transition:all 0.3s;background:<?= $resEnabled === '1' ? '#4caf50' : '#ccc' ?>;" title="Toggle Reservations">
                            <span style="position:absolute;top:3px;<?= $resEnabled === '1' ? 'left:25px' : 'left:3px' ?>;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.2);transition:all 0.3s;"></span>
                        </button>
                    </form>
                </div>
            </div>
            <div class="stats-container">
                <div class="stat-card">
                    <span class="stat-label">Pending Approval</span>
                    <div class="stat-value-row">
                        <span class="stat-value warning"><?= number_format($pendingCount) ?></span>
                        <span class="material-symbols-outlined stat-icon warning">pending_actions</span>
                    </div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Approved</span>
                    <div class="stat-value-row">
                        <span class="stat-value success"><?= number_format($approvedCount) ?></span>
                        <span class="material-symbols-outlined stat-icon success">check_circle</span>
                    </div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Rejected</span>
                    <div class="stat-value-row">
                        <span class="stat-value danger"><?= number_format($rejectedCount) ?></span>
                        <span class="material-symbols-outlined stat-icon danger">cancel</span>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($successMsg): ?>
            <div class="alert-banner success" role="alert">
                <span class="material-symbols-outlined">check_circle</span>
                <span><?= esc($successMsg) ?></span>
                <span class="material-symbols-outlined alert-banner-close" onclick="this.parentElement.remove()">close</span>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="alert-banner error" role="alert">
                <span class="material-symbols-outlined">error</span>
                <span><?= esc($errorMsg) ?></span>
                <span class="material-symbols-outlined alert-banner-close" onclick="this.parentElement.remove()">close</span>
            </div>
        <?php endif; ?>

        <section class="search-section">
            <div class="search-wrapper">
                <span class="material-symbols-outlined search-icon">search</span>
                <input class="search-input" id="reservationSearchInput" placeholder="Search by Student ID, Name, or Purpose..." type="text" aria-label="Search reservations">
            </div>
            <button class="filter-button" type="button" id="toggleFilterBtn">
                <span class="material-symbols-outlined">filter_list</span>
                <span>Filters</span>
            </button>
        </section>

        <section class="filters-panel" id="filtersPanel" hidden>
            <div class="filter-field">
                <label for="resEntriesPerPage">Show entries</label>
                <select id="resEntriesPerPage">
                    <option value="5" selected>5</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
            <div class="filter-field">
                <label for="labFilter">Lab</label>
                <select id="labFilter">
                    <option value="all">All Labs</option>
                    <?php
                    $labs = ['524' => true, '526' => true, '528' => true, '530' => true, '542' => true, '544' => true];
                    foreach ($reservationsList as $r) {
                        $lab = trim((string) ($r['lab'] ?? ''));
                        if ($lab !== '') {
                            $labs[$lab] = true;
                        }
                    }
                    $labNames = array_keys($labs);
                    sort($labNames, SORT_NATURAL | SORT_FLAG_CASE);
                    foreach ($labNames as $labName):
                    ?>
                        <option value="<?= esc(strtolower($labName)) ?>"><?= esc($labName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions" style="display: flex; gap: 8px; flex-grow: 1; align-items: flex-end;">
                <button type="button" class="clear-btn" id="clearFiltersBtn">Clear</button>
                <a class="reservation-history-btn" href="reservations_history_admin.php">
                    <span class="material-symbols-outlined">history</span>
                    Reservation History
                </a>
            </div>
        </section>

        <section class="table-container">
            <div class="scrollable-table">
                <table>
                    <thead>
                        <tr>
                            <th class="text-center">RESERVATION ID</th>
                            <th class="text-center">ID NUMBER</th>
                            <th>STUDENT NAME</th>
                            <th>LAB</th>
                            <th class="text-center">PC #</th>
                            <th>SCHEDULE</th>
                            <th>PURPOSE</th>
                            <th>STATUS</th>
                            <th>REASON / NOTE</th>
                            <th class="text-center">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="reservationsTableBody">
                        <?php
                        $avatarStyles = [
                            ['bg' => 'var(--primary-fixed)', 'fg' => 'var(--on-primary-fixed)'],
                            ['bg' => 'var(--tertiary-fixed)', 'fg' => 'var(--on-tertiary-fixed)'],
                        ];
                        ?>
                        <?php if (empty($reservationsList)): ?>
                            <tr id="resNoDataRow">
                                <td colspan="10" class="no-data">No reservations found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reservationsList as $idx => $record):
                                $style = $avatarStyles[$idx % count($avatarStyles)];
                                $displayName = trim(($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? ''));
                                $searchBlob = strtolower(trim(
                                    ($record['id'] ?? '') . ' ' .
                                    ($record['id_number'] ?? '') . ' ' .
                                    $displayName . ' ' .
                                    ($record['purpose'] ?? '') . ' ' .
                                    ($record['lab'] ?? '') . ' ' .
                                    ($record['status'] ?? '')
                                ));
                                $status = (string) ($record['status'] ?? 'Pending');
                                $statusClass = 'status-pending';
                                if ($status === 'Approved' || $status === 'Completed') {
                                    $statusClass = 'status-approved';
                                } elseif ($status === 'Rejected') {
                                    $statusClass = 'status-rejected';
                                }
                                $timeStr = date("h:i A", strtotime($record['reservation_time']));
                                $dateStr = date("M d, Y", strtotime($record['reservation_date']));
                            ?>
                                <tr
                                    class="data-row"
                                    data-search="<?= esc($searchBlob) ?>"
                                    data-status="<?= esc(strtolower($status)) ?>"
                                    data-lab="<?= esc(strtolower((string) ($record['lab'] ?? ''))) ?>">
                                    <td class="sit-id-col text-center"><?= esc($record['display_id']) ?></td>
                                    <td class="text-center"><?= esc($record['id_number']) ?></td>
                                    <td class="name-cell">
                                        <div class="avatar" style="background-color: <?= $style['bg'] ?>; color: <?= $style['fg'] ?>;">
                                            <?= esc(strtoupper(substr($record['first_name'] ?? 'U', 0, 1) . substr($record['last_name'] ?? 'S', 0, 1))) ?>
                                        </div>
                                        <span class="student-name"><?= esc($displayName) ?></span>
                                    </td>
                                    <td><span class="lab-badge"><?= esc($record['lab']) ?></span></td>
                                    <td class="text-center font-weight-bold">PC <?= esc($record['pc_number']) ?></td>
                                    <td>
                                        <div class="time-cell"><?= esc($dateStr) ?></div>
                                        <div class="time-cell muted"><?= esc($timeStr) ?></div>
                                    </td>
                                    <td><?= esc($record['purpose']) ?></td>
                                    <td>
                                        <span class="status-badge <?= $statusClass ?>">
                                            <span class="dot"></span>
                                            <?= esc($status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-size: 13px; color: var(--on-surface-variant); font-style: italic;">
                                            <?= $record['admin_note'] ? esc($record['admin_note']) : '-' ?>
                                        </span>
                                    </td>
                                    <td class="action-buttons-cell text-center">
                                        <?php if ($status === 'Pending'): ?>
                                            <div class="action-buttons-wrapper">
                                                <!-- Approve Trigger -->
                                                <button class="action-btn approve" type="button" title="Approve Reservation" onclick="openApproveModal(<?= $record['id'] ?>, '<?= esc(addslashes($displayName)) ?>')">
                                                    <span class="material-symbols-outlined" style="font-size: 18px;">check</span>
                                                </button>
                                                <!-- Reject Trigger -->
                                                <button class="action-btn reject" type="button" title="Reject Reservation" onclick="openRejectModal(<?= $record['id'] ?>, '<?= esc(addslashes($displayName)) ?>')">
                                                    <span class="material-symbols-outlined" style="font-size: 18px;">close</span>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span style="font-size: 12px; color: var(--on-surface-variant); opacity: 0.6;">No Action</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-bar">
                <span class="pagination-info" id="paginationInfo">Showing 0 to 0 of 0 records</span>
                <div class="pagination-controls">
                    <button class="page-btn" id="firstPageBtn" type="button" aria-label="First page">
                        <span class="material-symbols-outlined">keyboard_double_arrow_left</span>
                    </button>
                    <button class="page-btn" id="prevPageBtn" type="button" aria-label="Previous page">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </button>
                    <button class="page-btn active" type="button" id="pageNumberIndicator" aria-label="Current page">1</button>
                    <button class="page-btn" id="nextPageBtn" type="button" aria-label="Next page">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </button>
                    <button class="page-btn" id="lastPageBtn" type="button" aria-label="Last page">
                        <span class="material-symbols-outlined">keyboard_double_arrow_right</span>
                    </button>
                </div>
            </div>
        </section>
    </main>

    <!-- ─── Reject Note Modal ─── -->
    <div class="modal-overlay" id="rejectModal">
        <div class="modal-box" style="max-width: 440px;">
            <div class="modal-header">
                <span>Reject Reservation</span>
                <button class="modal-close" type="button" onclick="closeModal('rejectModal')">×</button>
            </div>
            <form method="POST" action="reservations_admin.php" id="rejectForm" style="margin: 0; padding: 0;">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="reservation_id" id="rejectReservationId">
                <div class="modal-body" style="padding: 24px;">
                    <div style="margin-bottom: 16px; font-size: 14px; color: var(--on-surface-variant);">
                        Rejecting reservation for: <strong id="rejectStudentName" style="color: var(--on-surface);"></strong>
                    </div>
                    <div class="modal-input-field" style="margin-bottom: 0;">
                        <label for="rejectNote">Reason / Note (Optional)</label>
                        <textarea name="admin_note" id="rejectNote" placeholder="e.g. Lab closed for maintenance, please select another time."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="modal-btn cancel" type="button" onclick="closeModal('rejectModal')">Cancel</button>
                    <button class="modal-btn reject" type="submit">Reject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Approve Confirmation Modal -->
    <div class="modal-overlay" id="approveModal">
        <div class="modal-box" style="max-width: 440px;">
            <div class="modal-header">
                <span>Confirm Approval</span>
                <button class="modal-close" type="button" onclick="closeModal('approveModal')">×</button>
            </div>
            <div class="modal-body" style="padding: 24px; text-align: center;">
                <div style="background: #10b981; color: white; width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <span class="material-symbols-outlined" style="font-size: 36px;">check_circle</span>
                </div>
                <h3 style="margin: 0 0 8px; color: var(--on-surface);">Approve Reservation?</h3>
                <p style="margin: 0; color: var(--on-surface-variant); font-size: 14px;">
                    Are you sure you want to approve the reservation for <strong id="approveStudentName"></strong>?
                </p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn cancel" type="button" onclick="closeModal('approveModal')">Cancel</button>
                <form method="POST" action="reservations_admin.php" id="approveForm" style="margin: 0; padding: 0; display: inline-block; flex: 0;">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="reservation_id" id="approveReservationId">
                    <button class="modal-btn approve" type="submit">Confirm</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Client-side Javascript Filtering & Pagination -->
    <script>
        function openRejectModal(id, studentName) {
            document.getElementById('rejectReservationId').value = id;
            document.getElementById('rejectStudentName').textContent = studentName;
            document.getElementById('rejectNote').value = '';
            openModal('rejectModal');
        }

        function openApproveModal(id, studentName) {
            document.getElementById('approveReservationId').value = id;
            document.getElementById('approveStudentName').textContent = studentName;
            openModal('approveModal');
        }

        (function () {
            const searchInput = document.getElementById('reservationSearchInput');
            const toggleFilterBtn = document.getElementById('toggleFilterBtn');
            const filtersPanel = document.getElementById('filtersPanel');
            const entriesPerPage = document.getElementById('resEntriesPerPage');
            const labFilter = document.getElementById('labFilter');
            const clearFiltersBtn = document.getElementById('clearFiltersBtn');

            const tableBody = document.getElementById('reservationsTableBody');
            const allRows = Array.from(tableBody.querySelectorAll('.data-row'));
            const noDataRow = document.getElementById('resNoDataRow');

            const info = document.getElementById('paginationInfo');
            const pageIndicator = document.getElementById('pageNumberIndicator');
            const firstBtn = document.getElementById('firstPageBtn');
            const prevBtn = document.getElementById('prevPageBtn');
            const nextBtn = document.getElementById('nextPageBtn');
            const lastBtn = document.getElementById('lastPageBtn');

            const filteredValuePending = document.querySelector('.stats-container .stat-card:nth-child(1) .stat-value');
            const filteredValueApproved = document.querySelector('.stats-container .stat-card:nth-child(2) .stat-value');
            const filteredValueRejected = document.querySelector('.stats-container .stat-card:nth-child(3) .stat-value');

            let filteredRows = allRows.slice();
            let currentPage = 1;

            function getPageSize() {
                const size = entriesPerPage ? parseInt(entriesPerPage.value, 10) : 5;
                return Number.isNaN(size) || size <= 0 ? 5 : size;
            }

            function getPageCount() {
                return Math.max(1, Math.ceil(filteredRows.length / getPageSize()));
            }

            function updateInfo(start, end) {
                if (!info) return;
                if (filteredRows.length === 0) {
                    info.textContent = 'Showing 0 to 0 of 0 records';
                    return;
                }
                info.textContent = 'Showing ' + start + ' to ' + end + ' of ' + filteredRows.length + ' records';
            }

            function renderPageButtons() {
                const totalPages = getPageCount();
                const hasRows = filteredRows.length > 0;

                if (pageIndicator) pageIndicator.textContent = String(totalPages === 0 ? 0 : currentPage);
                if (firstBtn) firstBtn.disabled = !hasRows || currentPage <= 1;
                if (prevBtn) prevBtn.disabled = !hasRows || currentPage <= 1;
                if (nextBtn) nextBtn.disabled = !hasRows || currentPage >= totalPages;
                if (lastBtn) lastBtn.disabled = !hasRows || currentPage >= totalPages;
            }

            function renderRows() {
                allRows.forEach((row) => {
                    row.style.display = 'none';
                });

                if (filteredRows.length === 0) {
                    if (noDataRow) noDataRow.style.display = '';
                    updateInfo(0, 0);
                    renderPageButtons();
                    return;
                }

                if (noDataRow) noDataRow.style.display = 'none';

                const pageSize = getPageSize();
                const startIndex = (currentPage - 1) * pageSize;
                const endIndex = Math.min(startIndex + pageSize, filteredRows.length);
                filteredRows.slice(startIndex, endIndex).forEach((row) => {
                    row.style.display = '';
                });

                updateInfo(startIndex + 1, endIndex);
                renderPageButtons();
            }

            function applyFilters() {
                const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
                const wantedLab = labFilter ? labFilter.value : 'all';

                filteredRows = allRows.filter((row) => {
                    const blob = row.dataset.search || '';
                    const lab = row.dataset.lab || '';

                    const queryPass = query === '' || blob.includes(query);
                    const labPass = wantedLab === 'all' || lab === wantedLab;

                    return queryPass && labPass;
                });

                currentPage = 1;
                renderRows();
            }

            if (searchInput) searchInput.addEventListener('input', applyFilters);
            if (entriesPerPage) {
                entriesPerPage.addEventListener('change', function () {
                    currentPage = 1;
                    renderRows();
                });
            }
            if (labFilter) labFilter.addEventListener('change', applyFilters);

            if (clearFiltersBtn) {
                clearFiltersBtn.addEventListener('click', function () {
                    if (searchInput) searchInput.value = '';
                    if (entriesPerPage) entriesPerPage.value = '5';
                    if (labFilter) labFilter.value = 'all';
                    applyFilters();
                });
            }

            if (firstBtn) {
                firstBtn.addEventListener('click', function () {
                    currentPage = 1;
                    renderRows();
                });
            }

            if (prevBtn) {
                prevBtn.addEventListener('click', function () {
                    if (currentPage > 1) {
                        currentPage -= 1;
                        renderRows();
                    }
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function () {
                    const totalPages = getPageCount();
                    if (currentPage < totalPages) {
                        currentPage += 1;
                        renderRows();
                    }
                });
            }

            if (lastBtn) {
                lastBtn.addEventListener('click', function () {
                    currentPage = getPageCount();
                    renderRows();
                });
            }

            if (toggleFilterBtn && filtersPanel) {
                toggleFilterBtn.addEventListener('click', function () {
                    filtersPanel.hidden = !filtersPanel.hidden;
                });
            }

            renderRows();

            // Auto-dismiss alerts after 3 seconds
            const alertBanners = document.querySelectorAll('.alert-banner');
            alertBanners.forEach(banner => {
                setTimeout(() => {
                    banner.style.transition = 'opacity 0.6s ease, transform 0.6s ease, margin-top 0.6s ease, padding 0.6s ease';
                    banner.style.opacity = '0';
                    banner.style.transform = 'translateY(-10px)';
                    banner.style.marginTop = '-50px'; // Collapse space
                    banner.style.padding = '0';
                    setTimeout(() => banner.remove(), 600);
                }, 3000);
            });
        })();
    </script>
<?php renderDarkModeScript(); ?>
</body>

</html>
