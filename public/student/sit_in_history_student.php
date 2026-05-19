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

// ── User Sit-in Summary Stats ──
$totalHours = 0;
$numSessions = 0;
$avgDuration = 0;
$longestSession = 0;

$statSql = "
    SELECT 
        COUNT(*) AS num_sessions,
        COALESCE(SUM(TIMESTAMPDIFF(MINUTE, time_in, time_out)) / 60, 0) AS total_hours,
        COALESCE(AVG(TIMESTAMPDIFF(MINUTE, time_in, time_out)) / 60, 0) AS avg_duration,
        COALESCE(MAX(TIMESTAMPDIFF(MINUTE, time_in, time_out)) / 60, 0) AS longest_session
    FROM sit_in_records
    WHERE user_id = ? AND status = 'Completed' AND time_out IS NOT NULL
";
$statStmt = $conn->prepare($statSql);
if ($statStmt) {
    $statStmt->bind_param('i', $userId);
    $statStmt->execute();
    $statResult = $statStmt->get_result();
    if ($statRow = $statResult->fetch_assoc()) {
        $numSessions = (int) $statRow['num_sessions'];
        $totalHours = (float) $statRow['total_hours'];
        $avgDuration = (float) $statRow['avg_duration'];
        $longestSession = (float) $statRow['longest_session'];
    }
    $statStmt->close();
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
    <link rel="stylesheet" href="../assets/css/shared/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/student/student_dashboard.css">
    <link rel="stylesheet" href="../assets/css/student/sit_in_history_student.css">
    <!-- Material Symbols Outlined -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700&display=swap" rel="stylesheet">
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

        <!-- User Sit-in Summary -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
            <!-- Total Hours -->
            <div style="background: var(--surface-container, #fff); color: var(--on-surface, #1e293b); border: 1px solid var(--outline-variant, #e0e0e0); border-top: 4px solid var(--primary, #002a5c); border-radius: 12px; padding: 1.25rem 1.5rem; position: relative; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <div style="font-size: 12px; font-weight: 600; color: var(--on-surface-variant, #64748b); text-transform: uppercase; letter-spacing: 0.5px;">Total Sit-in Hours</div>
                <div style="font-size: 2rem; font-weight: 800; margin-top: 4px; color: var(--primary, #002a5c);"><?= number_format($totalHours, 1) ?></div>
                <span class="material-symbols-outlined" style="position: absolute; top: 1.25rem; right: 1.5rem; font-size: 28px; color: var(--primary, #002a5c); opacity: 0.85;">schedule</span>
            </div>
            <!-- Number of Sessions -->
            <div style="background: var(--surface-container, #fff); color: var(--on-surface, #1e293b); border: 1px solid var(--outline-variant, #e0e0e0); border-top: 4px solid #2e7d32; border-radius: 12px; padding: 1.25rem 1.5rem; position: relative; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <div style="font-size: 12px; font-weight: 600; color: var(--on-surface-variant, #64748b); text-transform: uppercase; letter-spacing: 0.5px;">Number of Sessions</div>
                <div style="font-size: 2rem; font-weight: 800; margin-top: 4px; color: #2e7d32;"><?= $numSessions ?></div>
                <span class="material-symbols-outlined" style="position: absolute; top: 1.25rem; right: 1.5rem; font-size: 28px; color: #2e7d32; opacity: 0.85;">counter_7</span>
            </div>
            <!-- Average Duration -->
            <div style="background: var(--surface-container, #fff); color: var(--on-surface, #1e293b); border: 1px solid var(--outline-variant, #e0e0e0); border-top: 4px solid #e65100; border-radius: 12px; padding: 1.25rem 1.5rem; position: relative; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <div style="font-size: 12px; font-weight: 600; color: var(--on-surface-variant, #64748b); text-transform: uppercase; letter-spacing: 0.5px;">Average Session Duration</div>
                <div style="font-size: 2rem; font-weight: 800; margin-top: 4px; color: #e65100;"><?= number_format($avgDuration, 2) ?></div>
                <span class="material-symbols-outlined" style="position: absolute; top: 1.25rem; right: 1.5rem; font-size: 28px; color: #e65100; opacity: 0.85;">avg_pace</span>
            </div>
            <!-- Longest Session -->
            <div style="background: var(--surface-container, #fff); color: var(--on-surface, #1e293b); border: 1px solid var(--outline-variant, #e0e0e0); border-top: 4px solid #7b1fa2; border-radius: 12px; padding: 1.25rem 1.5rem; position: relative; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <div style="font-size: 12px; font-weight: 600; color: var(--on-surface-variant, #64748b); text-transform: uppercase; letter-spacing: 0.5px;">Longest Session</div>
                <div style="font-size: 2rem; font-weight: 800; margin-top: 4px; color: #7b1fa2;"><?= number_format($longestSession, 2) ?></div>
                <span class="material-symbols-outlined" style="position: absolute; top: 1.25rem; right: 1.5rem; font-size: 28px; color: #7b1fa2; opacity: 0.85;">timer</span>
            </div>
        </div>

        <section class="table-container">
            <div class="scrollable-table">
                <table>
                    <thead>
                        <tr>
                            <th class="text-center">SIT ID NUMBER</th>
                            <th>PURPOSE</th>
                            <th>SIT LAB</th>
                            <th class="text-center">SESSION #</th>
                            <th class="text-center">PC NO.</th>
                            <th>STATUS</th>
                            <th>DATE</th>
                            <th>TIME-IN</th>
                            <th>TIME-OUT</th>
                            <th class="text-center">DURATION</th>
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
                                <td colspan="11" class="no-data">No sit-in history available</td>
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
                                // Calculate duration
                                $duration = '—';
                                if (!empty($record['time_in']) && !empty($record['time_out'])) {
                                    $tin = strtotime($record['time_in']);
                                    $tout = strtotime($record['time_out']);
                                    if ($tin && $tout && $tout > $tin) {
                                        $durationHrs = ($tout - $tin) / 3600;
                                        $duration = number_format($durationHrs, 2) . ' hrs';
                                    }
                                }
                                // Extract date only
                                $dateOnly = !empty($record['time_in']) ? date('M d, Y', strtotime($record['time_in'])) : '—';
                                $timeInOnly = !empty($record['time_in']) ? date('h:i A', strtotime($record['time_in'])) : '—';
                                $timeOutOnly = !empty($record['time_out']) ? date('h:i A', strtotime($record['time_out'])) : '—';
                            ?>
                                <tr class="data-row">
                                    <td class="sit-id-col text-center"><?= esc($record['id']) ?></td>
                                    <td><?= esc($record['purpose']) ?></td>
                                    <td><span class="lab-badge"><?= esc($record['lab']) ?></span></td>
                                    <td class="text-center"><?= esc($record['session_no']) ?></td>
                                    <td class="text-center" style="color: var(--outline); font-style: italic;">—</td>
                                    <td>
                                        <div class="status-badge <?= $statusClass ?>">
                                            <span class="dot"></span>
                                            <?= esc(strtoupper($status)) ?>
                                        </div>
                                    </td>
                                    <td class="time-cell"><?= esc($dateOnly) ?></td>
                                    <td class="time-cell"><?= esc($timeInOnly) ?></td>
                                    <td class="time-cell<?= empty($record['time_out']) ? ' muted' : '' ?>">
                                        <?= esc($timeOutOnly) ?>
                                    </td>
                                    <td class="text-center" style="font-weight: 600;"><?= $duration ?></td>
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
                                                    <button id="fb-btn-<?= esc($record['id']) ?>" class="feedback-submit-icon-btn" onclick="submitFeedback(<?= esc($record['id']) ?>)" title="Update Feedback">
                                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <line x1="22" y1="2" x2="11" y2="13"></line>
                                                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                                        </svg>
                                                    </button>
                                                </div>
                                                <div id="fb-err-<?= esc($record['id']) ?>" class="feedback-error"></div>
                                            </div>
                                        <?php else: ?>
                                            <div class="feedback-edit" id="fb-edit-<?= esc($record['id']) ?>">
                                                <div class="feedback-input-wrapper">
                                                    <label for="fb-text-<?= esc($record['id']) ?>" class="sr-only">How was the PC?</label>
                                                    <textarea id="fb-text-<?= esc($record['id']) ?>" class="feedback-textarea" placeholder="How was the PC?" maxlength="300"></textarea>
                                                    <button id="fb-btn-<?= esc($record['id']) ?>" class="feedback-submit-icon-btn" onclick="submitFeedback(<?= esc($record['id']) ?>)" title="Submit Feedback">
                                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <line x1="22" y1="2" x2="11" y2="13"></line>
                                                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                                        </svg>
                                                    </button>
                                                </div>
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
            btn.style.opacity = '0.5';

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
                btn.style.opacity = '1';
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
