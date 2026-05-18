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
                <button class="nav-link search-icon-btn" type="button" onclick="openModal('searchModal')" aria-label="Search" title="Search Student" style="background: transparent; border: none; padding: 8px 4px; display: inline-block; cursor: pointer; line-height: 1; vertical-align: baseline;">
                    <span class="material-symbols-outlined" style="font-size: 20px; vertical-align: -3px; display: inline-block;">search</span>
                </button>
                <a class="nav-link" href="admin_dashboard.php">Home</a>
                <a class="nav-link" href="student_information.php">Student Information</a>
                <a class="nav-link" href="active_sessions.php">Active Sessions</a>
                <a class="nav-link" href="sit_in_history_admin.php">Sit-in History</a>
                <a class="nav-link active" href="leaderboard.php">Leaderboard</a>
                <a class="nav-link" href="reservations_admin.php">Reservations</a>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding: 40px; color:#64748b;">No students found.</td>
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
                                    </tr>
                                <?php $rank++; endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <?php include 'search_student_modal.php'; ?>
    <?php include 'sitin_form_modal.php'; ?>

    <script>
        function openModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('active');
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
