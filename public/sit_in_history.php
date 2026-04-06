<?php
session_start();
require_once '../config/database.php';
require_once 'helpers.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: index.php');
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
        sr.status,
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
    <link rel="icon" type="image/x-icon" href="./images/ccs.png">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin_dashboard.css">
    <link rel="stylesheet" href="css/sit_in_history.css">
</head>

<body>
    <nav class="admin-nav">
        <span class="brand">CCS Sit-in Monitoring System (ADMIN DASHBOARD)</span>
        <ul>
            <li><a href="admin_dashboard.php">Home</a></li>
            <li><button type="button" onclick="openModal('searchModal')">Search</button></li>
            <li><a href="admin_dashboard.php?view=students">Student Information</a></li>
            <li><a href="current_sit_in.php">Active session</a></li>
            <li><a href="sit_in_history.php" class="nav-active">Sit-in History</a></li>
            <li><a href="sit_in_history.php?logout=1" class="logout-link">Log out</a></li>
        </ul>
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
                    <span class="stat-label">Total Records</span>
                    <span class="stat-value primary" id="totalRecordsValue"><?= number_format($totalRecords) ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Filtered Results</span>
                    <span class="stat-value" id="filteredRecordsValue"><?= number_format(count($historyRecords)) ?></span>
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
            <div class="filter-actions">
                <button type="button" class="clear-btn" id="clearFiltersBtn">Clear</button>
            </div>
        </section>

        <section class="table-container">
            <div class="scrollable-table">
                <table>
                    <thead>
                        <tr>
                            <th class="text-center">SIT ID NUMBER</th>
                            <th>ID NUMBER</th>
                            <th>NAME</th>
                            <th>PURPOSE</th>
                            <th>SIT LAB</th>
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
                                <td colspan="9" class="no-data">No sit-in history available</td>
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
                                    data-lab="<?= esc(strtolower((string) ($record['lab'] ?? ''))) ?>">
                                    <td class="sit-id-col text-center"><?= esc($record['id']) ?></td>
                                    <td><?= esc($record['id_number']) ?></td>
                                    <td>
                                        <div class="name-cell">
                                            <div class="avatar" style="background-color: <?= esc($style['bg']) ?>; color: <?= esc($style['fg']) ?>;">
                                                <?= esc(studentInitials($record['first_name'], $record['last_name'])) ?>
                                            </div>
                                            <span class="student-name"><?= esc(strtoupper($displayName)) ?></span>
                                        </div>
                                    </td>
                                    <td><?= esc($record['purpose']) ?></td>
                                    <td><span class="lab-badge"><?= esc($record['lab']) ?></span></td>
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
                    <button class="page-btn" id="historyPrevBtn" type="button" aria-label="Previous page">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </button>
                    <div id="pageNumbers" class="page-number-list"></div>
                    <button class="page-btn" id="historyNextBtn" type="button" aria-label="Next page">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </button>
                </div>
            </div>
        </section>
    </main>

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
            const pageNumbers = document.getElementById('pageNumbers');
            const prevBtn = document.getElementById('historyPrevBtn');
            const nextBtn = document.getElementById('historyNextBtn');
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
                if (!pageNumbers) return;

                pageNumbers.innerHTML = '';
                const totalPages = getPageCount();
                const model = buildPageModel(totalPages, currentPage);

                model.forEach((item) => {
                    if (item === '...') {
                        const dots = document.createElement('span');
                        dots.className = 'page-ellipsis';
                        dots.textContent = '...';
                        pageNumbers.appendChild(dots);
                        return;
                    }

                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'page-btn';
                    btn.textContent = String(item);
                    if (item === currentPage) {
                        btn.classList.add('active');
                    }
                    btn.addEventListener('click', function () {
                        currentPage = item;
                        renderRows();
                    });
                    pageNumbers.appendChild(btn);
                });

                prevBtn.disabled = currentPage <= 1 || filteredRows.length === 0;
                nextBtn.disabled = currentPage >= totalPages || filteredRows.length === 0;
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

            if (toggleFilterBtn && filtersPanel) {
                toggleFilterBtn.addEventListener('click', function () {
                    filtersPanel.hidden = !filtersPanel.hidden;
                });
            }

            updateStats();
            renderRows();
        })();
    </script>
</body>

</html>
