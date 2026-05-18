<?php
session_start();
require_once '../../config/database.php';
require_once '../includes/helpers.php';

// Guard: admin only
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

// Handle Add Points form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $uid = (int)$_POST['user_id'];
    $earned_points = (int)$_POST['earned_points'];
    $tasks_completed = (int)$_POST['tasks_completed'];
    
    $stmt = $conn->prepare("UPDATE users SET earned_points = ?, tasks_completed = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('iii', $earned_points, $tasks_completed, $uid);
        $stmt->execute();
        $stmt->close();
    }
    
    header('Location: leaderboard.php?success=1');
    exit;
}

// Fetch all students and calculate total hours
$students = [];
$sql = "
    SELECT u.id, u.id_number, u.first_name, u.last_name, u.profile_image, u.earned_points, u.tasks_completed,
    COALESCE((SELECT SUM(TIMESTAMPDIFF(MINUTE, time_in, time_out))/60 
              FROM sit_in_records 
              WHERE user_id = u.id AND time_out IS NOT NULL), 0) as total_hours
    FROM users u
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Calculate weighted score: (points * 0.5) + (hours * 0.3) + (tasks * 0.2)
        $score = ($row['earned_points'] * 0.5) + ((float)$row['total_hours'] * 0.3) + ($row['tasks_completed'] * 0.2);
        $row['score'] = $score;
        $students[] = $row;
    }
}

// Sort students by score descending
usort($students, function($a, $b) {
    // If scores are equal, sort by hours, then points
    if (abs($b['score'] - $a['score']) < 0.001) {
        return $b['total_hours'] <=> $a['total_hours'];
    }
    return $b['score'] <=> $a['score'];
});
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - Admin Dashboard</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/ccs.png">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/shared/navbar.css">
    <link rel="stylesheet" href="../assets/css/admin/admin_dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin/leaderboard.css">
</head>

<body>

    <nav class="academic-ledger-navbar">
        <div class="nav-container">
            <div class="brand">
                <h1 class="brand-title">CCS Sit-in Monitoring System (ADMIN)</h1>
            </div>
            <div class="nav-links">
                <a class="nav-link" href="admin_dashboard.php">Home</a>
                <button class="nav-link" type="button" onclick="openModal('searchModal')">Search</button>
                <a class="nav-link" href="student_information.php">Student Information</a>
                <a class="nav-link" href="active_sessions.php">Active Sessions</a>
                <a class="nav-link" href="sit_in_history_admin.php">Sit-in History</a>
                <a class="nav-link active" href="leaderboard.php">Leaderboard</a>
                <a class="nav-logout" href="leaderboard.php?logout=1">Logout</a>
            </div>
        </div>
    </nav>

    <main class="ledger-main">
        <div class="container">
            <header class="page-header">
                <div>
                    <h1 class="dashboard-title">Leaderboard</h1>
                    <p class="dashboard-subtitle">Top Students based on Points, Sit-in Hours, and Tasks Completed.</p>
                </div>
            </header>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="res-msg success" style="background:#ecfdf5;color:#065f46;padding:16px;border-radius:12px;margin-bottom:24px;">
                    Points updated successfully!
                </div>
            <?php endif; ?>

            <div class="leaderboard-card">
                <div class="leaderboard-header">
                    <h3>Top Students - 2nd Semester</h3>
                    <div class="current-badge">Current</div>
                </div>
                
                <div class="table-responsive">
                    <table class="leaderboard-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Student</th>
                                <th>ID</th>
                                <th>Earned Points</th>
                                <th>Total Hours</th>
                                <th>Tasks</th>
                                <th>Score</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding: 40px; color:#64748b;">No students found.</td>
                                </tr>
                            <?php else: ?>
                                <?php $rank = 1; foreach ($students as $student): ?>
                                    <tr class="<?= $rank <= 3 ? 'top-rank rank-' . $rank : '' ?>">
                                        <td>
                                            <?php if ($rank === 1): ?>
                                                <span class="medal">🥇</span>
                                            <?php elseif ($rank === 2): ?>
                                                <span class="medal">🥈</span>
                                            <?php elseif ($rank === 3): ?>
                                                <span class="medal">🥉</span>
                                            <?php else: ?>
                                                <span class="rank-num">#<?= $rank ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="student-cell">
                                            <?= esc($student['first_name'] . ' ' . $student['last_name']) ?>
                                        </td>
                                        <td><?= esc($student['id_number']) ?></td>
                                        <td><?= (int)$student['earned_points'] ?></td>
                                        <td><?= number_format($student['total_hours'], 2) ?>h</td>
                                        <td><?= (int)$student['tasks_completed'] ?></td>
                                        <td><strong><?= number_format($student['score'], 1) ?></strong></td>
                                        <td>
                                            <button type="button" class="btn-add-points" 
                                                    onclick="openPointsModal(
                                                        <?= $student['id'] ?>, 
                                                        '<?= esc($student['first_name'] . ' ' . $student['last_name']) ?>',
                                                        <?= (int)$student['earned_points'] ?>,
                                                        <?= (int)$student['tasks_completed'] ?>
                                                    )">
                                                + Points
                                            </button>
                                        </td>
                                    </tr>
                                <?php $rank++; endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Points Modal -->
    <div class="modal-overlay" id="addPointsModal">
        <div class="modal-box">
            <div class="modal-header">
                <span>Add Reward / Points</span>
                <button type="button" class="modal-close" onclick="closeModal('addPointsModal')">×</button>
            </div>
            <form id="addPointsForm" method="POST" action="leaderboard.php">
                <div class="modal-body">
                    <p style="margin-bottom: 20px; color: var(--on-surface-variant);">Update points and completed tasks for <strong id="modalStudentName" style="color: var(--primary);"></strong>.</p>
                    <input type="hidden" name="user_id" id="modalUserId">
                    
                    <div class="modal-field">
                        <label for="earned_points">Earned Points</label>
                        <input type="number" id="earned_points" name="earned_points" min="0" required>
                    </div>
                    
                    <div class="modal-field">
                        <label for="tasks_completed">Tasks Completed</label>
                        <input type="number" id="tasks_completed" name="tasks_completed" min="0" required>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn btn-cancel" onclick="closeModal('addPointsModal')">Cancel</button>
                    <button type="submit" class="modal-btn btn-confirm">Save Updates</button>
                </div>
            </form>
        </div>
    </div>
    <?php include 'search_student_modal.php'; ?>
    <?php include 'sitin_form_modal.php'; ?>

    <script>
        function openModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('active');
        }

        function openPointsModal(userId, name, currentPoints, currentTasks) {
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalStudentName').textContent = name;
            document.getElementById('earned_points').value = currentPoints;
            document.getElementById('tasks_completed').value = currentTasks;
            
            openModal('addPointsModal');
        }

        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('active');
        }

        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
