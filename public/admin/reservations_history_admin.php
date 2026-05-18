<?php
session_start();
require_once '../../config/database.php';
require_once '../includes/helpers.php';

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

// ── Fetch reservations (Approved & Rejected only) ──
$reservationsList = [];
$q = "SELECT r.id, r.user_id, r.purpose, r.lab, r.pc_number, r.reservation_date, r.reservation_time, r.status, r.admin_note, r.created_at,
             u.id_number, u.first_name, u.last_name,
             (SELECT COUNT(*) + 1 FROM reservations r2 WHERE r2.id < r.id) as display_id
      FROM reservations r
      JOIN users u ON r.user_id = u.id
      WHERE r.status IN ('Approved', 'Rejected')
      ORDER BY r.created_at DESC";
$res = $conn->query($q);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $reservationsList[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation History</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/ccs.png">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
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
                <a class="nav-logout" href="reservations_history_admin.php?logout=1">Logout</a>
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
        <header class="history-header">
            <div>
                <h1>Reservation History</h1>
            </div>
            <div class="stats-container">
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
                <div class="stat-card">
                    <span class="stat-label">Total History</span>
                    <div class="stat-value-row">
                        <span class="stat-value primary" style="color: var(--primary);"><?= number_format($approvedCount + $rejectedCount) ?></span>
                        <span class="material-symbols-outlined stat-icon primary" style="color: var(--primary);">history</span>
                    </div>
                </div>
            </div>
        </header>

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
            <div class="filter-field">
                <label for="statusFilter">Status</label>
                <select id="statusFilter">
                    <option value="all">All Statuses</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="filter-actions" style="display: flex; gap: 8px; flex-grow: 1; align-items: flex-end;">
                <button type="button" class="clear-btn" id="clearFiltersBtn">Clear</button>
                <a class="reservation-history-btn" href="reservations_admin.php">
                    <span class="material-symbols-outlined">arrow_back</span>
                    Back to Reservations
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
                                <td colspan="9" class="no-data">No history records found</td>
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
                                $status = (string) ($record['status'] ?? 'Approved');
                                $statusClass = 'status-approved';
                                if ($status === 'Rejected') {
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

    <script>
        (function () {
            const searchInput = document.getElementById('reservationSearchInput');
            const toggleFilterBtn = document.getElementById('toggleFilterBtn');
            const filtersPanel = document.getElementById('filtersPanel');
            const entriesPerPage = document.getElementById('resEntriesPerPage');
            const labFilter = document.getElementById('labFilter');
            const statusFilter = document.getElementById('statusFilter');
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
                const wantedStatus = statusFilter ? statusFilter.value : 'all';

                filteredRows = allRows.filter((row) => {
                    const blob = row.dataset.search || '';
                    const lab = row.dataset.lab || '';
                    const status = row.dataset.status || '';

                    const queryPass = query === '' || blob.includes(query);
                    const labPass = wantedLab === 'all' || lab === wantedLab;
                    const statusPass = wantedStatus === 'all' || status === wantedStatus;

                    return queryPass && labPass && statusPass;
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
            if (statusFilter) statusFilter.addEventListener('change', applyFilters);

            if (clearFiltersBtn) {
                clearFiltersBtn.addEventListener('click', function () {
                    if (searchInput) searchInput.value = '';
                    if (entriesPerPage) entriesPerPage.value = '5';
                    if (labFilter) labFilter.value = 'all';
                    if (statusFilter) statusFilter.value = 'all';
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
                        currentPage--;
                        renderRows();
                    }
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function () {
                    const totalPages = getPageCount();
                    if (currentPage < totalPages) {
                        currentPage++;
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
                    const isHidden = filtersPanel.hasAttribute('hidden');
                    if (isHidden) {
                        filtersPanel.removeAttribute('hidden');
                        this.classList.add('active');
                    } else {
                        filtersPanel.setAttribute('hidden', '');
                        this.classList.remove('active');
                    }
                });
            }

            // Initial render
            applyFilters();
        })();
    </script>
</body>

</html>
