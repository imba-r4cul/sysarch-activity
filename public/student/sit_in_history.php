<?php
session_start();
require_once '../../config/database.php';
require_once '../includes/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/index.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ../auth/index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$historyRecords = [];

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
    WHERE sr.status <> 'Active' AND sr.user_id = ?
    ORDER BY sr.time_in DESC
";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $historyRecords[] = $row;
    }
    $stmt->close();
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
    <link rel="stylesheet" href="../assets/css/shared/global.css">
    <link rel="stylesheet" href="../assets/css/student/student_dashboard.css">
    <link rel="stylesheet" href="../assets/css/student/sit_in_history_student.css">
</head>

<body class="dashboard-body">
    <nav class="navbar dashboard-nav">
        <h1 class="navbar-title">College of Computer Studies Sit-in Monitoring System</h1>
        <ul class="navbar-links dashboard-links">
            <li><a href="student_dashboard.php">Home</a></li>
            <li><a href="edit_profile.php">Edit Profile</a></li>
            <li><a href="reservations.php">Reservations</a></li>
            <li><a href="sit_in_history.php">Sit-in History</a></li>
            <li><a href="sit_in_history.php?logout=1" class="logout-btn">Logout</a></li>
        </ul>
    </nav>

    <main style="padding: 2rem 4rem;">
        <header class="history-header" style="justify-content: flex-start; margin-bottom: 2rem;">
            <div>
                <h1>Sit-in Records History</h1>
            </div>
        </header>

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
                                $status = (string) ($record['status'] ?? 'Unknown');
                                $statusClass = 'status-progress';
                                if (strcasecmp($status, 'Completed') === 0) {
                                    $statusClass = 'status-completed';
                                } elseif (strcasecmp($status, 'Ended') === 0) {
                                    $statusClass = 'status-ended';
                                }
                            ?>
                                <tr class="data-row">
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

    <script>
        (function () {
            const tableBody = document.getElementById('historyTableBody');
            const info = document.getElementById('historyInfo');
            const pageBtn = document.getElementById('historyCurrentPageBtn');
            const firstBtn = document.getElementById('historyFirstBtn');
            const prevBtn = document.getElementById('historyPrevBtn');
            const nextBtn = document.getElementById('historyNextBtn');
            const lastBtn = document.getElementById('historyLastBtn');

            if (!tableBody) return;

            const allRows = Array.from(tableBody.querySelectorAll('tr.data-row'));
            let currentPage = 1;
            const pageSize = 5;

            function getPageCount() {
                return Math.max(1, Math.ceil(allRows.length / pageSize));
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

            function updateInfo(start, end) {
                if (!info) return;
                if (allRows.length === 0) {
                    info.textContent = 'Showing 0 to 0 of 0 records';
                    return;
                }
                info.textContent = 'Showing ' + start + ' to ' + end + ' of ' + allRows.length + ' records';
            }

            function renderPageButtons() {
                const totalPages = getPageCount();
                const hasRows = allRows.length > 0;

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

                if (allRows.length === 0) {
                    updateInfo(0, 0);
                    renderPageButtons();
                    return;
                }

                const startIndex = (currentPage - 1) * pageSize;
                const endIndex = Math.min(startIndex + pageSize, allRows.length);
                allRows.slice(startIndex, endIndex).forEach((row) => {
                    row.style.display = '';
                });

                updateInfo(startIndex + 1, endIndex);
                renderPageButtons();
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

            renderRows();
        })();
    </script>
</body>

</html>
