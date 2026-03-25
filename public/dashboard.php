<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

$stmt = $conn->prepare(
    'SELECT id_number, first_name, last_name, course, course_level, email, address, profile_image
     FROM users
     WHERE id = ?
     LIMIT 1'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

$showLoginSuccess = isset($_SESSION['login_success_name']);
$successName = $showLoginSuccess
    ? $_SESSION['login_success_name']
    : trim($user['first_name'] . ' ' . $user['last_name']);
unset($_SESSION['login_success_name']);

$defaultProfileImage = 'images/edit-profile.png';
$avatarPath = $defaultProfileImage;

if (!empty($user['profile_image'])) {
    $safeFileName = basename($user['profile_image']);
    $diskPath = __DIR__ . '/uploads/' . $safeFileName;
    if (is_file($diskPath)) {
        $avatarPath = 'uploads/' . rawurlencode($safeFileName);
    }
}

function esc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/dashboard.css">
</head>

<body class="dashboard-body">

    <nav class="navbar dashboard-nav">
        <h1 class="navbar-title">College of Computer Studies Sit-in
            Monitoring System</h1>
        <ul class="navbar-links dashboard-links">
            <li><a href="dashboard.php">Home</a></li>
            <li><a href="edit_profile.php">Edit Profile</a></li>
            <li><a href="reservations.php">Reservations</a></li>
            <li><a href="dashboard.php?logout=1" class="logout-btn">Log out</a></li>
        </ul>
    </nav>

    <main class="dashboard-container single-panel-layout">
        <section class="student-panel">
            <div class="panel-header">Student Information</div>
            <div class="student-content">
                <a href="edit_profile.php" class="avatar-link" title="Click to upload or change profile picture">
                    <img src="./<?= esc($avatarPath) ?>" alt="Student avatar" class="student-avatar">
                </a>

                <div class="student-item"><strong>Name:</strong>
                    <?= esc($user['first_name'] . ' ' . $user['last_name']) ?></div>
                <div class="student-item"><strong>Course:</strong> <?= esc($user['course']) ?></div>
                <div class="student-item"><strong>Year:</strong> <?= esc($user['course_level']) ?></div>
                <div class="student-item"><strong>Email:</strong> <?= esc($user['email']) ?></div>
                <div class="student-item"><strong>Address:</strong> <?= esc($user['address']) ?></div>
                <div class="student-item"><strong>ID Number:</strong> <?= esc($user['id_number']) ?></div>
            </div>
        </section>
    </main>

    <?php if ($showLoginSuccess): ?>
        <div class="login-success-overlay" id="loginSuccessOverlay">
            <div class="login-success-modal">
                <div class="success-icon">✓</div>
                <h2>Successful Login!</h2>
                <p>Welcome, <?= esc($successName) ?>!</p>
                <button type="button" id="closeSuccessModal">OK</button>
            </div>
        </div>

        <script>
            (function () {
                const overlay = document.getElementById('loginSuccessOverlay');
                const closeBtn = document.getElementById('closeSuccessModal');

                if (closeBtn && overlay) {
                    closeBtn.addEventListener('click', function () {
                        overlay.style.display = 'none';
                    });
                }
            })();
        </script>
    <?php endif; ?>

</body>

</html>