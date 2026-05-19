<?php
session_start();
require_once '../../config/database.php';

$students = [];

function authEsc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function studentAvatarPath($profileImage)
{
    if (!empty($profileImage)) {
        $safeFileName = basename($profileImage);
        $diskPath = __DIR__ . '/../assets/uploads/' . $safeFileName;

        if (is_file($diskPath)) {
            return '../assets/uploads/' . rawurlencode($safeFileName);
        }
    }

    return '../assets/images/edit-profile.png';
}

$sql = "
    SELECT u.id, u.id_number, u.first_name, u.last_name, u.profile_image, u.earned_points,
    COALESCE((SELECT COUNT(*) FROM sit_in_records WHERE user_id = u.id AND status = 'Completed'), 0) as tasks_completed,
    COALESCE((SELECT SUM(TIMESTAMPDIFF(MINUTE, time_in, time_out))/60
              FROM sit_in_records
              WHERE user_id = u.id AND time_out IS NOT NULL), 0) as total_hours
    FROM users u
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $score = ($row['earned_points'] * 0.5) + ((float)$row['total_hours'] * 0.3) + ($row['tasks_completed'] * 0.2);
        $row['score'] = $score;
        $students[] = $row;
    }
}

usort($students, function ($a, $b) {
    if (abs($b['score'] - $a['score']) < 0.001) {
        return $b['total_hours'] <=> $a['total_hours'];
    }

    return $b['score'] <=> $a['score'];
});

$topStudents = array_slice($students, 0, 3);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboards - CCS</title>
    <link rel="stylesheet" href="../assets/css/shared/global.css">
    <link rel="stylesheet" href="../assets/css/shared/navbar.css">
    <link rel="stylesheet" href="../assets/css/auth/auth.css">
    <link rel="icon" type="image/x-icon" href="../assets/images/ccs.png">
</head>

<body class="bg-light">

    <nav class="academic-ledger-navbar">
        <div class="nav-container">
            <div class="brand">
                <h1 class="brand-title">College of Computer Studies Sit-in Monitoring System</h1>
            </div>
            <div class="nav-links">
                <a class="nav-link" href="index.php">Login</a>
                <a class="nav-link active" href="leaderboards.php">Leaderboards</a>
                <a class="nav-link" href="register.php">Register</a>
            </div>
        </div>
    </nav>

    <main class="leaderboards-page">
        <section class="public-leaderboards" aria-labelledby="leaderboards-title">
            <div class="leaderboards-shell">
                <div class="leaderboards-heading">
                    <h2 id="leaderboards-title">Student Leaderboards</h2>
                    <p>Top students based on earned points, sit-in hours, and completed sessions.</p>
                </div>

                <?php if (empty($students)): ?>
                    <div class="leaderboards-empty">
                        <h3>No students found</h3>
                        <p>Rankings will appear here once student records are available.</p>
                    </div>
                <?php else: ?>
                    <div class="podium-grid" aria-label="Top ranked students">
                        <?php foreach ($topStudents as $index => $student): ?>
                            <?php
                                $rank = $index + 1;
                                $fullName = trim($student['first_name'] . ' ' . $student['last_name']);
                                $avatarPath = studentAvatarPath($student['profile_image']);
                            ?>
                            <article class="podium-card podium-rank-<?= $rank ?>">
                                <div class="podium-rank-label">Rank <?= $rank ?></div>
                                <img class="podium-avatar" src="<?= authEsc($avatarPath) ?>" alt="<?= authEsc($fullName) ?> profile picture">
                                <h3><?= authEsc($fullName) ?></h3>
                                <div class="podium-score"><?= number_format($student['score'], 1) ?></div>
                                <p>Overall score</p>
                                <div class="podium-stats">
                                    <span><?= (int)$student['earned_points'] ?> pts</span>
                                    <span><?= (int)$student['tasks_completed'] ?> sessions</span>
                                    <span><?= number_format((float)$student['total_hours'], 2) ?>h</span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="leaderboard-table-card">
                        <div class="leaderboard-table-wrap">
                            <table class="public-leaderboard-table">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Student</th>
                                        <th>ID Number</th>
                                        <th>Earned Points</th>
                                        <th>Total Hours</th>
                                        <th>Completed Sessions</th>
                                        <th>Overall Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $index => $student): ?>
                                        <?php
                                            $rank = $index + 1;
                                            if ($rank <= 3) continue; // Skip top 3 ranks
                                            $fullName = trim($student['first_name'] . ' ' . $student['last_name']);
                                            $avatarPath = studentAvatarPath($student['profile_image']);
                                        ?>
                                        <tr>
                                            <td><span class="table-rank">#<?= $rank ?></span></td>
                                            <td>
                                                <div class="table-student">
                                                    <img src="<?= authEsc($avatarPath) ?>" alt="<?= authEsc($fullName) ?> profile picture">
                                                    <span><?= authEsc($fullName) ?></span>
                                                </div>
                                            </td>
                                            <td><?= authEsc($student['id_number']) ?></td>
                                            <td><?= (int)$student['earned_points'] ?></td>
                                            <td><?= number_format((float)$student['total_hours'], 2) ?>h</td>
                                            <td><?= (int)$student['tasks_completed'] ?></td>
                                            <td><strong><?= number_format($student['score'], 1) ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

</body>

</html>
