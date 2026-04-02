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
    ORDER BY sr.time_in DESC
";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $currentRecords[] = $row;
    }
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
    <link rel="stylesheet" href="css/admin_dashboard.css">
    <style>
        * {
            box-sizing: border-box;
        }

        .page {
            max-width: 1180px;
            margin: 20px auto;
            padding: 0 14px 28px;
        }

        h1 {
            text-align: center;
            margin: 6px 0 18px;
            font-size: 46px;
            line-height: 1.1;
        }

        .table-card {
            background: #fff;
            border: 1px solid #d7dde6;
            padding: 12px;
        }

        .flash {
            margin: 0 0 12px;
            padding: 10px 12px;
            border: 1px solid #bdd7ee;
            background: #eef6ff;
            color: #0b4b8f;
            font-size: 14px;
        }

        .table-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .entries-control,
        .search-control {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .entries-control select,
        .search-control input {
            border: 1px solid #bcc6d1;
            background: #fff;
            padding: 4px 8px;
            font-size: 14px;
            min-height: 30px;
        }

        .search-control input {
            width: 220px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .table-wrap table {
            width: 100%;
            border-collapse: collapse;
            min-width: 940px;
            font-size: 14px;
        }

        .table-wrap th,
        .table-wrap td {
            text-align: left;
            border-bottom: 1px solid #e3e7ec;
            padding: 10px 8px;
            vertical-align: middle;
        }

        .table-wrap th {
            color: #252f3a;
            font-weight: 700;
            background: #f9fbfd;
        }

        tr.no-data-row td {
            text-align: center;
            color: #616f7f;
            font-style: italic;
            background: #fafafa;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            color: #11653f;
            background: #dff6e9;
            border: 1px solid #c6e9d5;
        }

        .action-btn {
            border: 1px solid #0d6efd;
            color: #0d6efd;
            background: #fff;
            border-radius: 4px;
            padding: 5px 10px;
            font-size: 13px;
            cursor: pointer;
        }

        .action-btn:hover {
            background: #0d6efd;
            color: #fff;
        }

        .table-footer {
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 14px;
        }

        .pager {
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .pager button {
            min-width: 30px;
            height: 28px;
            border: 1px solid #bec7d1;
            background: #fff;
            cursor: pointer;
            font-size: 14px;
        }

        .pager button.active {
            background: #eff3f8;
            border-color: #8797ab;
            font-weight: 600;
        }

        .pager button:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        @media (max-width: 900px) {
            h1 {
                font-size: 34px;
            }

            .table-toolbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-control input {
                width: 100%;
                min-width: 200px;
            }
        }
    </style>
</head>

<body>
    <nav class="admin-nav">
        <span class="brand">CCS Sit-in Monitoring System (ADMIN DASHBOARD)</span>
        <ul>
            <li><a href="admin_dashboard.php">Home</a></li>
            <li><a href="admin_dashboard.php?view=students">Student Information</a></li>
            <li><button type="button" onclick="openModal('searchModal')">Search</button></li>
            <li><a href="current_sit_in.php" class="nav-active">Current Sit in</a></li>
            <li><a href="admin_dashboard.php">Sit-in Form</a></li>
            <li><a href="current_sit_in.php?logout=1" class="logout-link">Log out</a></li>
        </ul>
    </nav>

    <?php include 'search_student_modal.php'; ?>

    <script>
        // Shared modal functions for non-dashboard pages
        function openModal(id) {
            const modal = document.getElementById(id);
            if (modal) modal.classList.add('active');
        }
        function closeModal(id) {
            const modal = document.getElementById(id);
            if (modal) modal.classList.remove('active');
        }
    </script>

    <main class="page">
        <h1>Current Sit in</h1>

        <section class="table-card">
            <?php if ($flashMessage !== ''): ?>
                <p class="flash"><?= esc($flashMessage) ?></p>
            <?php endif; ?>

            <div class="table-toolbar">
                <div class="entries-control">
                    <select id="entriesPerPage" aria-label="Entries per page">
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    <span>entries per page</span>
                </div>

                <div class="search-control">
                    <label for="searchInput">Search:</label>
                    <input type="text" id="searchInput" placeholder="Search by ID, name, purpose, lab">
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Sit ID Number</th>
                            <th>ID Number</th>
                            <th>Name</th>
                            <th>Purpose</th>
                            <th>Sit Lab</th>
                            <th>Session</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="currentSitTableBody">
                        <?php if (empty($currentRecords)): ?>
                            <tr class="no-data-row" id="noDataRow">
                                <td colspan="8">No data available</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($currentRecords as $record):
                                $displayName = trim(($record['last_name'] ?? '') . ', ' . ($record['first_name'] ?? ''));
                                $searchBlob = strtolower(trim(
                                    ($record['id'] ?? '') . ' ' .
                                    ($record['id_number'] ?? '') . ' ' .
                                    $displayName . ' ' .
                                    ($record['purpose'] ?? '') . ' ' .
                                    ($record['lab'] ?? '')
                                ));
                                ?>
                                <tr class="data-row" data-search="<?= esc($searchBlob) ?>">
                                    <td><?= esc($record['id']) ?></td>
                                    <td><?= esc($record['id_number']) ?></td>
                                    <td><?= esc($displayName) ?></td>
                                    <td><?= esc($record['purpose']) ?></td>
                                    <td><?= esc($record['lab']) ?></td>
                                    <td><?= esc($record['session_no']) ?></td>
                                    <td><span class="status-pill">Active</span></td>
                                    <td>
                                        <form method="POST" action="current_sit_in.php"
                                            onsubmit="return confirm('Mark this sit-in as completed?');">
                                            <input type="hidden" name="record_id" value="<?= esc($record['id']) ?>">
                                            <button type="submit" name="mark_completed" value="1" class="action-btn">End</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="no-data-row" id="noDataRow" style="display:none;">
                                <td colspan="8">No data available</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <div id="tableInfo">Showing 0 to 0 of 0 entries</div>
                <div class="pager">
                    <button type="button" id="firstBtn" aria-label="First page">&laquo;</button>
                    <button type="button" id="prevBtn" aria-label="Previous page">&lsaquo;</button>
                    <button type="button" id="pageBtn" class="active" aria-label="Current page">1</button>
                    <button type="button" id="nextBtn" aria-label="Next page">&rsaquo;</button>
                    <button type="button" id="lastBtn" aria-label="Last page">&raquo;</button>
                </div>
            </div>
        </section>
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

            if (!tableBody) {
                return;
            }

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
                allRows.forEach((row) => {
                    row.style.display = 'none';
                });

                if (filteredRows.length === 0) {
                    if (noDataRow) {
                        noDataRow.style.display = '';
                    }
                    updateInfo();
                    updatePagerButtons();
                    return;
                }

                if (noDataRow) {
                    noDataRow.style.display = 'none';
                }

                const size = getPageSize();
                const start = (page - 1) * size;
                const rowsToShow = filteredRows.slice(start, start + size);
                rowsToShow.forEach((row) => {
                    row.style.display = '';
                });

                updateInfo();
                updatePagerButtons();
            }

            function applyFilters() {
                const query = searchInput.value.trim().toLowerCase();
                filteredRows = allRows.filter((row) => {
                    const blob = row.dataset.search || '';
                    return query === '' || blob.includes(query);
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
    </script>
</body>

</html>
