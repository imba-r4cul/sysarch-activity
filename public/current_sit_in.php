<?php
session_start();
require_once '../config/database.php';

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

function esc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function studentInitials($firstName, $lastName)
{
    $first = trim((string) $firstName);
    $last = trim((string) $lastName);

    $a = $first !== '' ? strtoupper(substr($first, 0, 1)) : '';
    $b = $last !== '' ? strtoupper(substr($last, 0, 1)) : '';
    $initials = $a . $b;

    return $initials !== '' ? $initials : 'NA';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_completed'])) {
    $recordId = (int) ($_POST['record_id'] ?? 0);

    if ($recordId > 0) {
        $stmt = $conn->prepare("UPDATE sit_in_records SET status = 'Completed', time_out = NOW() WHERE id = ? AND status = 'Active'");
        if ($stmt) {
            $stmt->bind_param('i', $recordId);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $_SESSION['current_sitin_flash'] = 'Sit-in record marked as completed.';
            } else {
                $_SESSION['current_sitin_flash'] = 'Record not found or already completed.';
            }
            $stmt->close();
        } else {
            $_SESSION['current_sitin_flash'] = 'Database error. Please try again.';
        }
    } else {
        $_SESSION['current_sitin_flash'] = 'Invalid sit-in record selected.';
    }

    header('Location: current_sit_in.php');
    exit;
}

$currentRecords = [];
$activeCount = 0;
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
        (
            SELECT COUNT(*)
            FROM sit_in_records x
            WHERE x.user_id = sr.user_id AND x.id <= sr.id
        ) AS session_no
    FROM sit_in_records sr
    WHERE sr.status = 'Active'
    ORDER BY sr.time_in ASC
";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $currentRecords[] = $row;
    }
}

$activeResult = $conn->query("SELECT COUNT(*) AS total FROM sit_in_records WHERE status = 'Active'");
if ($activeResult && ($activeRow = $activeResult->fetch_assoc())) {
    $activeCount = (int) ($activeRow['total'] ?? 0);
}

$flashMessage = $_SESSION['current_sitin_flash'] ?? '';
unset($_SESSION['current_sitin_flash']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Current Sit in</title>
    <link rel="icon" type="image/x-icon" href="./images/ccs.png">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin_dashboard.css">
    <link rel="stylesheet" href="css/current_sit_in.css">
</head>

<body>
    <nav class="admin-nav">
        <span class="brand">CCS Sit-in Monitoring System (ADMIN DASHBOARD)</span>
        <ul>
            <li><a href="admin_dashboard.php">Home</a></li>
            <li><button type="button" onclick="openModal('searchModal')">Search</button></li>
            <li><a href="admin_dashboard.php?view=students">Student Information</a></li>
            <li><a href="current_sit_in.php" class="nav-active">Active session</a></li>
            <li><a href="sit_in_history.php">Sit-in History</a></li>
            <li><a href="current_sit_in.php?logout=1" class="logout-link">Log out</a></li>
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
        <div class="header-section">
            <h1>Active Sessions</h1>
        </div>

        <?php if ($flashMessage !== ''): ?>
            <div class="flash"><?= esc($flashMessage) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-toolbar">
                <div class="entries-control">
                    <label class="label-uppercase" for="entriesPerPage">Show entries</label>
                    <div class="select-wrapper">
                        <select id="entriesPerPage" aria-label="Entries per page">
                            <option value="5" selected>5</option>
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                        <span class="material-symbols-outlined">expand_more</span>
                    </div>
                </div>
                <div class="search-wrapper">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text" id="searchInput" aria-label="Search entries" placeholder="Search by ID, name, purpose, lab">
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Sit ID Number</th>
                            <th>ID Number</th>
                            <th>Name</th>
                            <th>Purpose</th>
                            <th>Sit Lab</th>
                            <th style="text-align: center;">Session</th>
                            <th style="text-align: center;">Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="currentSitTableBody">
                        <?php
                        $avatarStyles = [
                            ['bg' => 'var(--primary-fixed)', 'fg' => 'var(--on-primary-fixed)'],
                            ['bg' => 'var(--tertiary-fixed)', 'fg' => 'var(--on-tertiary-fixed)'],
                        ];
                        ?>
                        <?php if (empty($currentRecords)): ?>
                            <tr id="noDataRow">
                                <td colspan="8" style="text-align: center; color: var(--on-surface-variant); font-style: italic; padding: 32px;">No data available</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($currentRecords as $idx => $record):
                                $style = $avatarStyles[$idx % count($avatarStyles)];
                                $displayName = trim(($record['last_name'] ?? '') . ', ' . ($record['first_name'] ?? ''));
                                $searchBlob = strtolower(trim(
                                    ($record['id_number'] ?? '') . ' ' .
                                    $displayName . ' ' .
                                    ($record['purpose'] ?? '') . ' ' .
                                    ($record['lab'] ?? '')
                                ));
                            ?>
                            <tr class="data-row" data-search="<?= esc($searchBlob) ?>">
                                <td class="sit-id"><?= esc($record['id']) ?></td>
                                <td><?= esc($record['id_number']) ?></td>
                                <td class="student-name">
                                    <div class="name-cell">
                                        <div class="avatar" style="background-color: <?= $style['bg'] ?>; color: <?= $style['fg'] ?>;">
                                            <?= esc(studentInitials($record['first_name'], $record['last_name'])) ?>
                                        </div>
                                        <span><?= esc($displayName) ?></span>
                                    </div>
                                </td>
                                <td><?= esc($record['purpose']) ?></td>
                                <td class="lab-text"><?= esc($record['lab']) ?></td>
                                <td class="session-time" style="text-align: center;"><?= esc($record['session_no']) ?></td>
                                <td style="text-align: center;">
                                    <span class="status-badge<?= ($record['status'] ?? '') === 'Completed' ? ' completed' : '' ?>">
                                        <span class="status-dot"></span>
                                        <?= esc($record['status'] ?? 'Unknown') ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php if (($record['status'] ?? '') === 'Active'): ?>
                                        <form method="POST" action="current_sit_in.php" class="action-form" onsubmit="return confirm('Mark this sit-in as completed?');">
                                            <input type="hidden" name="record_id" value="<?= esc($record['id']) ?>">
                                            <button type="submit" name="mark_completed" value="1" class="btn-end">End</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="action-done">Done</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr id="noDataRow" style="display:none;">
                                <td colspan="8" style="text-align: center; color: var(--on-surface-variant); font-style: italic; padding: 32px;">No data available</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-footer">
                <span class="pagination-info" id="tableInfo">Showing 0 to 0 of 0 entries</span>
                <nav class="pagination-nav">
                    <button class="page-btn" type="button" id="firstBtn" aria-label="First page">
                        <span class="material-symbols-outlined">keyboard_double_arrow_left</span>
                    </button>
                    <button class="page-btn" type="button" id="prevBtn" aria-label="Previous page">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </button>
                    <button class="page-btn active" type="button" id="pageBtn" aria-label="Current page">1</button>
                    <button class="page-btn" type="button" id="nextBtn" aria-label="Next page">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </button>
                    <button class="page-btn" type="button" id="lastBtn" aria-label="Last page">
                        <span class="material-symbols-outlined">keyboard_double_arrow_right</span>
                    </button>
                </nav>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-label">Active Sessions</span>
                    <span class="material-symbols-outlined">sensors</span>
                </div>
                <div>
                    <h3 class="stat-value"><?= $activeCount ?></h3>
                    <p class="stat-subtext">Across 6 Computer Labs</p>
                </div>
            </div>
        </div>
    </main>

    <script>
        (function () {
            const searchInput = document.getElementById('searchInput');
            const entriesPerPage = document.getElementById('entriesPerPage');
            const tableBody = document.getElementById('currentSitTableBody');
            const info = document.getElementById('tableInfo');
            const noDataRow = document.getElementById('noDataRow');
            const firstBtn = document.getElementById('firstBtn');
            const prevBtn = document.getElementById('prevBtn');
            const pageBtn = document.getElementById('pageBtn');
            const nextBtn = document.getElementById('nextBtn');
            const lastBtn = document.getElementById('lastBtn');

            if (!tableBody) return;

            const allRows = Array.from(tableBody.querySelectorAll('tr.data-row'));
            let filteredRows = allRows.slice();
            let page = 1;

            function getPageSize() {
                const size = parseInt(entriesPerPage.value, 10);
                return Number.isNaN(size) || size <= 0 ? 10 : size;
            }

            function pageCount() {
                const total = filteredRows.length;
                const size = getPageSize();
                return Math.max(1, Math.ceil(total / size));
            }

            function updatePagerButtons() {
                const totalPages = pageCount();
                const hasRows = filteredRows.length > 0;

                pageBtn.textContent = String(totalPages === 0 ? 0 : page);
                firstBtn.disabled = !hasRows || page <= 1;
                prevBtn.disabled = !hasRows || page <= 1;
                nextBtn.disabled = !hasRows || page >= totalPages;
                lastBtn.disabled = !hasRows || page >= totalPages;
            }

            function updateInfo() {
                const total = filteredRows.length;
                if (total === 0) {
                    info.textContent = 'Showing 0 to 0 of 0 entries';
                    return;
                }

                const size = getPageSize();
                const start = (page - 1) * size + 1;
                const end = Math.min(page * size, total);
                info.textContent = 'Showing ' + start + ' to ' + end + ' of ' + total + ' entries';
            }

            function renderRows() {
                allRows.forEach((row) => row.style.display = 'none');

                if (filteredRows.length === 0) {
                    if (noDataRow) noDataRow.style.display = '';
                    updateInfo();
                    updatePagerButtons();
                    return;
                }

                if (noDataRow) noDataRow.style.display = 'none';

                const size = getPageSize();
                const start = (page - 1) * size;
                const rowsToShow = filteredRows.slice(start, start + size);
                rowsToShow.forEach((row) => row.style.display = '');

                updateInfo();
                updatePagerButtons();
            }

            function applyFilters() {
                const query = searchInput.value.trim().toLowerCase();
                filteredRows = allRows.filter((row) => {
                    if (query === '') return true;

                    // If query is numeric, avoid false positive substring matches inside lab/session numbers (e.g. '2' matching lab '526')
                    if (/^\d+$/.test(query)) {
                        const id = row.cells[1].textContent.trim().toLowerCase();
                        const lab = row.cells[4].textContent.trim().toLowerCase();
                        const session = row.cells[5].textContent.trim().toLowerCase();
                        return id.startsWith(query) || lab.startsWith(query) || session.startsWith(query);
                    }

                    const blob = row.dataset.search || '';
                    return blob.includes(query);
                });

                page = 1;
                renderRows();
            }

            searchInput.addEventListener('input', applyFilters);
            entriesPerPage.addEventListener('change', function () {
                page = 1;
                renderRows();
            });

            firstBtn.addEventListener('click', function () {
                page = 1;
                renderRows();
            });

            prevBtn.addEventListener('click', function () {
                if (page > 1) {
                    page -= 1;
                    renderRows();
                }
            });

            nextBtn.addEventListener('click', function () {
                const totalPages = pageCount();
                if (page < totalPages) {
                    page += 1;
                    renderRows();
                }
            });

            lastBtn.addEventListener('click', function () {
                page = pageCount();
                renderRows();
            });

            renderRows();
        })();

        // Auto-dismiss flash message after 3 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const flash = document.querySelector('.flash');
            if (flash) {
                setTimeout(() => {
                    flash.style.animation = 'fadeOut 0.5s ease-out forwards';
                    setTimeout(() => {
                        flash.remove();
                    }, 500);
                }, 3000);
            }
        });
    </script>
</body>

</html>
