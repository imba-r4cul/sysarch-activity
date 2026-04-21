<?php
session_start();
require_once '../../config/database.php';
require_once '../includes/helpers.php';

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
    <link rel="stylesheet" href="../assets/css/shared/navbar.css">
    <link rel="stylesheet" href="../assets/css/admin/admin_dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin/sit_in_history_admin.css">
</head>

<body>
    <nav class="academic-ledger-navbar">
        <div class="nav-container">
            <div class="brand">
                <h1 class="brand-title">CCS Sit-in Monitoring System (ADMIN DASHBOARD)</h1>
            </div>
            <div class="nav-links">
                <a class="nav-link" href="admin_dashboard.php">Home</a>
                <button class="nav-link" type="button" onclick="openModal('searchModal')">Search</button>
                <a class="nav-link" href="student_information.php">Student Information</a>
                <a class="nav-link" href="active_sessions.php">Active Sessions</a>
                <a class="nav-link active" href="sit_in_history_admin.php">Sit-in History</a>
                <a class="nav-logout" href="sit_in_history_admin.php?logout=1">Logout</a>
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
            <div class="filter-actions" style="display: flex; gap: 8px; flex-grow: 1; align-items: flex-end;">
                <button type="button" class="clear-btn" id="clearFiltersBtn">Clear</button>
                <div style="flex-grow: 1;"></div>
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
        <div class="modal-box" style="display:flex; flex-direction:column;">
            <div class="modal-header">
                <h2>Student Feedbacks</h2>
                <button type="button" class="modal-close-btn" onclick="closeModal('feedbacksModal')">
                    <span class="material-symbols-outlined">close</span>
                </button>
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
</body>

</html>
