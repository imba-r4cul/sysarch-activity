<?php
session_start();
require_once '../../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/student_notifications.php';
require_once '../includes/student_navbar.php';

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
$notificationFeatureEnabled = studentNotificationFeatureEnabled($conn);
studentHandleNotificationAjax($conn, $userId, $notificationFeatureEnabled);
$newAnnCount = studentFetchUnreadNotificationCount($conn, $userId, $notificationFeatureEnabled);
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
        sr.feedback,
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
    <link rel="stylesheet" href="../assets/css/shared/global.css">
    <link rel="stylesheet" href="../assets/css/shared/navbar.css">
    <link rel="stylesheet" href="../assets/css/student/student_dashboard.css">
    <link rel="stylesheet" href="../assets/css/student/sit_in_history_student.css">
    <!-- FontAwesome CDN for standard icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="dashboard-body">
    <?php renderStudentNavbar('history', $newAnnCount); ?>

    <main style="padding: 2rem 4rem;">
        <header class="history-header" style="justify-content: flex-start; margin-bottom: 15px;">
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
                            <!-- <th>ID NUMBER</th> -->
                            <!-- <th>NAME</th> -->
                            <th>PURPOSE</th>
                            <th>SIT LAB</th>
                            <th class="text-center">SESSION #</th>
                            <th>STATUS</th>
                            <th>STARTED AT</th>
                            <th>ENDED AT</th>
                            <th>FEEDBACK</th>
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
                                    <!-- <td><?= esc($record['id_number']) ?></td> -->
                                    <!-- <td>
                                        <div class="name-cell">
                                            <div class="avatar" style="background-color: <?= esc($style['bg']) ?>; color: <?= esc($style['fg']) ?>;">
                                                <?= esc(studentInitials($record['first_name'], $record['last_name'])) ?>
                                            </div>
                                            <span class="student-name"><?= esc(strtoupper($displayName)) ?></span>
                                        </div>
                                    </td> -->
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
                                    <td class="feedback-cell">
                                        <?php if (!empty($record['feedback'])): ?>
                                            <div class="feedback-display" id="fb-display-<?= esc($record['id']) ?>">
                                                <span class="feedback-text"><?= esc($record['feedback']) ?></span>
                                                <button class="feedback-edit-btn" onclick="editFeedback(<?= esc($record['id']) ?>)" title="Edit Feedback" aria-label="Edit Feedback">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M12 20h9"></path>
                                                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="feedback-edit" id="fb-edit-<?= esc($record['id']) ?>" style="display: none;">
                                                <div class="feedback-input-wrapper">
                                                    <label for="fb-text-<?= esc($record['id']) ?>" class="sr-only">How was the PC?</label>
                                                    <textarea id="fb-text-<?= esc($record['id']) ?>" class="feedback-textarea" placeholder="How was the PC?" maxlength="300"><?= esc($record['feedback']) ?></textarea>
                                                </div>
                                                <button id="fb-btn-<?= esc($record['id']) ?>" class="feedback-submit-btn" onclick="submitFeedback(<?= esc($record['id']) ?>)">Update</button>
                                                <div id="fb-err-<?= esc($record['id']) ?>" class="feedback-error"></div>
                                            </div>
                                        <?php else: ?>
                                            <div class="feedback-edit" id="fb-edit-<?= esc($record['id']) ?>">
                                                <div class="feedback-input-wrapper">
                                                    <label for="fb-text-<?= esc($record['id']) ?>" class="sr-only">How was the PC?</label>
                                                    <textarea id="fb-text-<?= esc($record['id']) ?>" class="feedback-textarea" placeholder="How was the PC?" maxlength="300"></textarea>
                                                </div>
                                                <button id="fb-btn-<?= esc($record['id']) ?>" class="feedback-submit-btn" onclick="submitFeedback(<?= esc($record['id']) ?>)">Submit</button>
                                                <div id="fb-err-<?= esc($record['id']) ?>" class="feedback-error"></div>
                                            </div>
                                            <div class="feedback-display" id="fb-display-<?= esc($record['id']) ?>" style="display: none;">
                                                <span class="feedback-text"></span>
                                                <button class="feedback-edit-btn" onclick="editFeedback(<?= esc($record['id']) ?>)" title="Edit Feedback" aria-label="Edit Feedback">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M12 20h9"></path>
                                                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        <?php endif; ?>
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
        function editFeedback(id) {
            document.getElementById('fb-display-' + id).style.display = 'none';
            document.getElementById('fb-edit-' + id).style.display = 'flex';
            document.getElementById('fb-text-' + id).focus();
        }

        async function submitFeedback(id) {
            const textarea = document.getElementById('fb-text-' + id);
            const btn = document.getElementById('fb-btn-' + id);
            const errDiv = document.getElementById('fb-err-' + id);
            const displayDiv = document.getElementById('fb-display-' + id);
            const editDiv = document.getElementById('fb-edit-' + id);
            const textSpan = displayDiv.querySelector('.feedback-text');
            const val = textarea.value.trim();

            errDiv.textContent = '';
            if (!val) {
                errDiv.textContent = 'Feedback cannot be empty.';
                return;
            }

            textarea.disabled = true;
            btn.disabled = true;
            const originalBtnText = btn.textContent;
            btn.textContent = '...';

            const formData = new FormData();
            formData.append('sit_in_id', id);
            formData.append('feedback', val);

            try {
                const res = await fetch('submit_feedback.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    textSpan.textContent = val;
                    editDiv.style.display = 'none';
                    displayDiv.style.display = 'flex';
                } else {
                    errDiv.textContent = data.error || 'Failed to submit feedback.';
                }
            } catch (e) {
                errDiv.textContent = 'A network error occurred.';
            } finally {
                textarea.disabled = false;
                btn.disabled = false;
                btn.textContent = val ? 'Update' : 'Submit';
            }
        }

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
    <?php renderStudentNotificationScript($notificationFeatureEnabled); ?>
</body>

</html>
