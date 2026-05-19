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

// Handle Export PDF (print-friendly)
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $search = $_GET['search'] ?? '';
    $lab = $_GET['lab'] ?? 'all';
    
    $whereClauses = ["sr.status <> 'Active'"];
    $params = [];
    $types = '';
    
    if ($lab !== 'all') {
        $whereClauses[] = "LOWER(sr.lab) = ?";
        $params[] = strtolower($lab);
        $types .= 's';
    }
    
    if ($search !== '') {
        $searchTerm = "%{$search}%";
        $whereClauses[] = "(sr.id_number LIKE ? OR sr.first_name LIKE ? OR sr.last_name LIKE ? OR sr.purpose LIKE ?)";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $types .= 'ssss';
    }
    
    $whereSql = implode(' AND ', $whereClauses);
    $sql = "
        SELECT sr.id, sr.id_number, sr.first_name, sr.last_name, sr.purpose, sr.lab, sr.pc_number, sr.status, sr.time_in, sr.time_out, sr.feedback
        FROM sit_in_records sr
        WHERE $whereSql
        ORDER BY sr.time_in DESC
    ";
    
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $generatedAt = date('Y-m-d H:i');
    $title = 'Sit-in Records History';
    $labLabel = $lab === 'all' ? 'All Labs' : strtoupper($lab);

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . esc($title) . '</title>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">';
    echo '<style>';
    echo 'body{font-family:"Manrope",sans-serif;margin:32px;color:#111827;}';
    echo '.header{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin-bottom:20px;}';
    echo 'h1{margin:0;font-size:24px;font-weight:800;color:#004085;}';
    echo '.meta{font-size:12px;color:#6b7280;}';
    echo '.filters{margin:10px 0 20px;font-size:13px;color:#374151;}';
    echo 'table{width:100%;border-collapse:collapse;font-size:12px;}';
    echo 'th,td{padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top;}';
    echo 'th{background:#f3f4f6;text-transform:uppercase;letter-spacing:0.08em;font-size:10px;color:#6b7280;}';
    echo '.status{font-weight:700;}';
    echo '@media print{body{margin:16px;} .no-print{display:none;}}';
    echo '</style>';
    echo '</head>';
    echo '<body>'; 
    echo '<div class="header">';
    echo '<h1>' . esc($title) . '</h1>';
    echo '<div class="meta">Generated: ' . esc($generatedAt) . '</div>';
    echo '</div>';
    echo '<div class="filters">';
    echo '<strong>Filters:</strong> Lab: ' . esc($labLabel) . ' &nbsp;|&nbsp; Search: ' . esc($search === '' ? 'All' : $search);
    echo '</div>';
    echo '<table>'; 
    echo '<thead><tr>';
    echo '<th>Record ID</th><th>ID Number</th><th>Name</th><th>Purpose</th><th>Lab</th><th>PC</th><th>Status</th><th>Time In</th><th>Time Out</th><th>Feedback</th>';
    echo '</tr></thead><tbody>';
    if (count($rows) === 0) {
        echo '<tr><td colspan="10">No records found.</td></tr>';
    } else {
        foreach ($rows as $row) {
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            echo '<tr>';
            echo '<td>' . esc($row['id']) . '</td>';
            echo '<td>' . esc($row['id_number']) . '</td>';
            echo '<td>' . esc($name) . '</td>';
            echo '<td>' . esc($row['purpose']) . '</td>';
            echo '<td>' . esc($row['lab']) . '</td>';
            echo '<td>' . esc($row['pc_number'] ?? 'N/A') . '</td>';
            echo '<td class="status">' . esc($row['status']) . '</td>';
            echo '<td>' . esc(formatDateTime($row['time_in'] ?? null)) . '</td>';
            echo '<td>' . esc(formatDateTime($row['time_out'] ?? null)) . '</td>';
            echo '<td>' . esc($row['feedback']) . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
    echo '<script>window.addEventListener("load",function(){window.print();});</script>';
    echo '</body></html>';
    exit;
}

$historyRecords = [];
$totalRecords = 0;

$sql = "
    SELECT
        sr.id,
        sr.user_id,
        sr.id_number,
        sr.first_name,
        sr.last_name,
        sr.purpose,
        sr.lab,
        sr.pc_number,
        sr.status,
        sr.feedback,
        sr.time_in,
        sr.time_out,
        (
            SELECT COUNT(*)
            FROM sit_in_records x
            WHERE x.user_id = sr.user_id AND x.id <= sr.id
        ) AS session_no
    FROM sit_in_records sr
    WHERE sr.status <> 'Active'
    ORDER BY sr.time_in DESC
";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $historyRecords[] = $row;
    }
}

$totalResult = $conn->query("SELECT COUNT(*) AS total FROM sit_in_records WHERE status <> 'Active'");
if ($totalResult && ($totalRow = $totalResult->fetch_assoc())) {
    $totalRecords = (int) ($totalRow['total'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-in History</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/ccs.png">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/shared/global.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/shared/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/admin/admin_dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/admin/sit_in_history_admin.css?v=<?= time() ?>">
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
                <a class="nav-link active" href="active_sessions.php">Active Sessions</a>
                <a class="nav-link" href="leaderboard.php">Leaderboard</a>
                <a class="nav-link" href="reservations_admin.php">Reservations</a>
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
        <header class="history-header">
            <div>
                <h1>Sit-in Records History</h1>
            </div>
            <div class="stats-container">
                <div class="stat-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; gap: 12px;">
                        <span class="stat-label" style="margin-bottom: 0;">Total Records</span>
                        <span class="material-symbols-outlined" style="font-size: 22px; color: var(--primary); opacity: 0.85;">history</span>
                    </div>
                    <span class="stat-value primary" id="totalRecordsValue" style="text-align: center; margin-top: 8px;"><?= number_format($totalRecords) ?></span>
                </div>
                <div class="stat-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; gap: 12px;">
                        <span class="stat-label" style="margin-bottom: 0;">Filtered Results</span>
                        <span class="material-symbols-outlined" style="font-size: 22px; color: var(--on-surface-variant); opacity: 0.85;">filter_alt</span>
                    </div>
                    <span class="stat-value" id="filteredRecordsValue" style="text-align: center; margin-top: 8px;"><?= number_format(count($historyRecords)) ?></span>
                </div>
            </div>
        </header>

        <section class="search-section">
            <div class="search-wrapper">
                <span class="material-symbols-outlined search-icon">search</span>
                <input class="search-input" id="historySearchInput" placeholder="Search by SIT ID, Student ID, or Name..." type="text" aria-label="Search sit-in history">
            </div>
            <button class="filter-button" type="button" id="toggleFilterBtn">
                <span class="material-symbols-outlined">filter_list</span>
                <span>Filters</span>
            </button>
        </section>

        <section class="filters-panel" id="filtersPanel" hidden>
            <div class="filter-field">
                <label for="historyEntriesPerPage">Show entries</label>
                <select id="historyEntriesPerPage">
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
                    $labs = [];
                    foreach ($historyRecords as $record) {
                        $lab = trim((string) ($record['lab'] ?? ''));
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
                <div style="flex-grow: 1;"></div>
                <button type="button" class="view-feedbacks-btn export-btn" id="exportPdfBtn" style="margin-right: 8px;">
                    <span class="material-symbols-outlined">download</span>
                    Export PDF
                </button>
                <button type="button" class="view-feedbacks-btn" onclick="openModal('feedbacksModal')">
                    <span class="material-symbols-outlined">chat_bubble</span>
                    View Feedbacks
                </button>
            </div>
        </section>

        <section class="table-container">
            <div class="scrollable-table">
                <table>
                    <thead>
                        <tr>
                            <th class="text-center">SIT ID NUMBER</th>
                            <th class="text-center">ID NUMBER</th>
                            <th>NAME</th>
                            <th>PURPOSE</th>
                            <th>SIT LAB</th>
                            <th class="text-center">PC</th>
                            <th class="text-center">SESSION #</th>
                            <th>STATUS</th>
                            <th>STARTED AT</th>
                            <th>ENDED AT</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        <?php
                        $avatarStyles = [
                            ['bg' => 'var(--primary-fixed)', 'fg' => 'var(--on-primary-fixed)'],
                            ['bg' => 'var(--tertiary-fixed)', 'fg' => 'var(--on-tertiary-fixed)'],
                        ];
                        ?>
                        <?php if (empty($historyRecords)): ?>
                            <tr id="historyNoDataRow">
                                <td colspan="10" class="no-data">No sit-in history available</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($historyRecords as $idx => $record):
                                $style = $avatarStyles[$idx % count($avatarStyles)];
                                $displayName = trim(($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? ''));
                                $searchBlob = strtolower(trim(
                                    ($record['id'] ?? '') . ' ' .
                                    ($record['id_number'] ?? '') . ' ' .
                                    $displayName . ' ' .
                                    ($record['purpose'] ?? '') . ' ' .
                                    ($record['lab'] ?? '') . ' ' .
                                    ($record['pc_number'] ?? '') . ' ' .
                                    ($record['status'] ?? '')
                                ));
                                $status = (string) ($record['status'] ?? 'Unknown');
                                $statusClass = 'status-progress';
                                if (strcasecmp($status, 'Completed') === 0) {
                                    $statusClass = 'status-completed';
                                } elseif (strcasecmp($status, 'Ended') === 0) {
                                    $statusClass = 'status-ended';
                                }
                            ?>
                                <tr
                                    class="data-row"
                                    data-search="<?= esc($searchBlob) ?>"
                                    data-status="<?= esc(strtolower($status)) ?>"
                                    data-lab="<?= esc(strtolower((string) ($record['lab'] ?? ''))) ?>"
                                    data-feedback="<?= esc($record['feedback'] ?? '') ?>"
                                    data-id-number="<?= esc($record['id_number']) ?>"
                                    data-name="<?= esc(strtoupper($displayName)) ?>"
                                    data-lab-raw="<?= esc($record['lab']) ?>">
                                    <td class="sit-id-col text-center"><?= esc($record['id']) ?></td>
                                    <td class="text-center"><?= esc($record['id_number']) ?></td>
                                    <td class="name-cell">
                                        <div class="avatar" style="background-color: <?= $style['bg'] ?>; color: <?= $style['fg'] ?>;">
                                            <?= esc(strtoupper(substr($record['first_name'] ?? 'U', 0, 1) . substr($record['last_name'] ?? 'S', 0, 1))) ?>
                                        </div>
                                        <span class="student-name"><?= esc($record['last_name'] . ', ' . $record['first_name']) ?></span>
                                    </td>
                                    <td><?= esc($record['purpose']) ?></td>
                                    <td><span class="lab-badge"><?= esc($record['lab']) ?></span></td>
                                    <td class="text-center font-weight-bold" style="color: var(--primary);">PC <?= esc($record['pc_number'] ?? 'N/A') ?></td>
                                    <td class="text-center"><?= esc($record['session_no']) ?></td>
                                    <td>
                                        <div class="status-badge <?= $statusClass ?>">
                                            <span class="dot"></span>
                                            <?= esc(strtoupper($status)) ?>
                                        </div>
                                    </td>
                                    <td class="time-cell"><?= esc(formatDateTime($record['time_in'] ?? null)) ?></td>
                                    <td class="time-cell<?= empty($record['time_out']) ? ' muted' : '' ?>">
                                        <?= esc(formatDateTime($record['time_out'] ?? null)) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="historyNoDataRow" style="display:none;">
                                <td colspan="9" class="no-data">No sit-in history available</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-bar">
                <span class="pagination-info" id="historyInfo">Showing 0 to 0 of 0 records</span>
                <div class="pagination-controls">
                    <button class="page-btn" id="historyFirstBtn" type="button" aria-label="First page">
                        <span class="material-symbols-outlined">keyboard_double_arrow_left</span>
                    </button>
                    <button class="page-btn" id="historyPrevBtn" type="button" aria-label="Previous page">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </button>
                    <button class="page-btn active" type="button" id="historyCurrentPageBtn" aria-label="Current page">1</button>
                    <button class="page-btn" id="historyNextBtn" type="button" aria-label="Next page">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </button>
                    <button class="page-btn" id="historyLastBtn" type="button" aria-label="Last page">
                        <span class="material-symbols-outlined">keyboard_double_arrow_right</span>
                    </button>
                </div>
            </div>
        </section>
    </main>

    <div id="feedbacksModal" class="modal-overlay" onclick="if(event.target===this) closeModal('feedbacksModal')">
        <div class="modal-box">
            <div class="modal-header">
                <span>Student Feedbacks</span>
                <button type="button" class="modal-close" onclick="closeModal('feedbacksModal')">&times;</button>
            </div>
            <div style="padding: 16px 24px; border-bottom: 1px solid var(--surface-container-highest);">
                <div class="filter-field" style="width: auto;">
                    <label for="modalLabFilter">Filter by Lab</label>
                    <select id="modalLabFilter" onchange="renderFeedbacksModal()">
                        <option value="all">All Labs</option>
                    </select>
                </div>
            </div>
            <div class="modal-body">
                <table class="feedbacks-table">
                    <thead>
                        <tr>
                            <th class="text-center">ID NUMBER</th>
                            <th>NAME</th>
                            <th>LAB</th>
                            <th>FEEDBACK</th>
                        </tr>
                    </thead>
                    <tbody id="feedbacksTableBody">
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const searchInput = document.getElementById('historySearchInput');
            const entriesPerPage = document.getElementById('historyEntriesPerPage');
            const labFilter = document.getElementById('labFilter');
            const clearFiltersBtn = document.getElementById('clearFiltersBtn');
            const tableBody = document.getElementById('historyTableBody');
            const noDataRow = document.getElementById('historyNoDataRow');
            const info = document.getElementById('historyInfo');
            const filteredValue = document.getElementById('filteredRecordsValue');
            const pageBtn = document.getElementById('historyCurrentPageBtn');
            const firstBtn = document.getElementById('historyFirstBtn');
            const prevBtn = document.getElementById('historyPrevBtn');
            const nextBtn = document.getElementById('historyNextBtn');
            const lastBtn = document.getElementById('historyLastBtn');
            const toggleFilterBtn = document.getElementById('toggleFilterBtn');
            const filtersPanel = document.getElementById('filtersPanel');

            if (!tableBody) return;

            const allRows = Array.from(tableBody.querySelectorAll('tr.data-row'));
            const numberFormatter = new Intl.NumberFormat();

            let filteredRows = allRows.slice();
            let currentPage = 1;

            function getPageSize() {
                const size = entriesPerPage ? parseInt(entriesPerPage.value, 10) : 10;
                return Number.isNaN(size) || size <= 0 ? 10 : size;
            }

            function getPageCount() {
                return Math.max(1, Math.ceil(filteredRows.length / getPageSize()));
            }

            function buildPageModel(totalPages, page) {
                if (totalPages <= 5) {
                    return Array.from({ length: totalPages }, (_, i) => i + 1);
                }

                if (page <= 3) {
                    return [1, 2, 3, '...', totalPages];
                }

                if (page >= totalPages - 2) {
                    return [1, '...', totalPages - 2, totalPages - 1, totalPages];
                }

                return [1, '...', page - 1, page, page + 1, '...', totalPages];
            }

            function updateStats() {
                if (filteredValue) {
                    filteredValue.textContent = numberFormatter.format(filteredRows.length);
                }
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

                if (pageBtn) pageBtn.textContent = String(totalPages === 0 ? 0 : currentPage);
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
                updateStats();
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
            
            const exportPdfBtn = document.getElementById('exportPdfBtn');
            if (exportPdfBtn) {
                exportPdfBtn.addEventListener('click', function () {
                    const search = searchInput ? searchInput.value.trim() : '';
                    const lab = labFilter ? labFilter.value : 'all';
                    const url = `sit_in_history_admin.php?export=pdf&search=${encodeURIComponent(search)}&lab=${encodeURIComponent(lab)}`;
                    window.open(url, '_blank', 'noopener');
                });
            }

            updateStats();
            renderRows();

            window.renderFeedbacksModal = function() {
                const tbody = document.getElementById('feedbacksTableBody');
                const labFilter = document.getElementById('modalLabFilter').value;
                tbody.innerHTML = '';
                
                let count = 0;
                allRows.forEach(row => {
                    const feedback = row.getAttribute('data-feedback');
                    const lab = row.getAttribute('data-lab');
                    if (feedback && feedback.trim() !== '') {
                        if (labFilter === 'all' || lab === labFilter) {
                            count++;
                            const idNum = row.getAttribute('data-id-number');
                            const name = row.getAttribute('data-name');
                            const labRaw = row.getAttribute('data-lab-raw');
                            
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td class="text-center">${idNum}</td>
                                <td>${name}</td>
                                <td><span class="lab-badge">${labRaw}</span></td>
                                <td class="feedback-comment-col">${feedback}</td>
                            `;
                            tbody.appendChild(tr);
                        }
                    }
                });

                if (count === 0) {
                    tbody.innerHTML = `<tr><td colspan="4" class="no-data">No feedbacks matching this filter.</td></tr>`;
                }
            };

            const observer = new MutationObserver(mutations => {
                mutations.forEach(mutation => {
                    if (mutation.target.classList.contains('active') && mutation.target.id === 'feedbacksModal') {
                        const allLabs = new Set();
                        allRows.forEach(row => {
                            const f = row.getAttribute('data-feedback');
                            if (f && f.trim() !== '') {
                                const l = row.getAttribute('data-lab-raw');
                                if (l) allLabs.add(l);
                            }
                        });
                        const select = document.getElementById('modalLabFilter');
                        const currentVal = select.value;
                        select.innerHTML = '<option value="all">All Labs</option>';
                        const sortedLabs = Array.from(allLabs).sort();
                        sortedLabs.forEach(lab => {
                            const opt = document.createElement('option');
                            opt.value = lab.toLowerCase();
                            opt.textContent = lab;
                            select.appendChild(opt);
                        });
                        select.value = Array.from(select.options).some(o => o.value === currentVal) ? currentVal : 'all';
                        
                        renderFeedbacksModal();
                    }
                });
            });
            const fbModal = document.getElementById('feedbacksModal');
            if (fbModal) {
                observer.observe(fbModal, { attributes: true, attributeFilter: ['class'] });
            }

        })();
    </script>
    <?php renderDarkModeScript(); ?>
</body>

</html>
