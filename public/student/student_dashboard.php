<?php
session_start();
require_once '../../config/database.php';

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
    header('Location: ../auth/index.php');
    exit;
}

$showLoginSuccess = isset($_SESSION['login_success_name']);
$successName = $showLoginSuccess
    ? $_SESSION['login_success_name']
    : trim($user['first_name'] . ' ' . $user['last_name']);
unset($_SESSION['login_success_name']);

$defaultProfileImage = '../assets/images/edit-profile.png';
$avatarPath = $defaultProfileImage;

if (!empty($user['profile_image'])) {
    $safeFileName = basename($user['profile_image']);
    $diskPath = __DIR__ . '/../assets/uploads/' . $safeFileName;
    if (is_file($diskPath)) {
        $avatarPath = '../assets/uploads/' . rawurlencode($safeFileName);
    }
}

// ── Remaining Sessions Query ──
$remainingSessions = 30; // default
$sessionStmt = $conn->prepare(
    'SELECT COUNT(*) AS total_sessions FROM sit_in_records WHERE user_id = ?'
);
$sessionStmt->bind_param('i', $userId);
$sessionStmt->execute();
$sessionResult = $sessionStmt->get_result();
if ($sessionRow = $sessionResult->fetch_assoc()) {
    $remainingSessions = max(0, 30 - (int) $sessionRow['total_sessions']);
}
$sessionStmt->close();

// ── Fetch Announcements ──
$announcements = [];
$annQuery = $conn->query(
    "SELECT a.content, a.created_at, au.display_name
     FROM announcements a
     JOIN admin_users au ON a.admin_id = au.id
     ORDER BY a.created_at DESC
     LIMIT 10"
);
if ($annQuery) {
    while ($row = $annQuery->fetch_assoc()) {
        $announcements[] = $row;
    }
}

// Count new announcements (last 7 days)
$newAnnCount = 0;
foreach ($announcements as $ann) {
    if (strtotime($ann['created_at']) >= strtotime('-7 days')) {
        $newAnnCount++;
    }
}

function esc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Year level label helper
function yearLabel($level) {
    $n = (int) $level;
    if ($n <= 0) return 'N/A';
    $suffix = 'th';
    if (($n % 100) < 11 || ($n % 100) > 13) {
        if (($n % 10) === 1) $suffix = 'st';
        elseif (($n % 10) === 2) $suffix = 'nd';
        elseif (($n % 10) === 3) $suffix = 'rd';
    }
    return $n . $suffix . ' Year';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="../assets/css/shared/global.css">
    <link rel="stylesheet" href="../assets/css/student/student_dashboard.css">
    <!-- FontAwesome CDN for standard icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Material Symbols for Close Icon -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../assets/images/ccs.png">
</head>

<body class="dashboard-body">

    <nav class="navbar dashboard-nav">
        <h1 class="navbar-title">College of Computer Studies Sit-in
            Monitoring System</h1>
        <ul class="navbar-links dashboard-links">
            <li><a href="student_dashboard.php">Home</a></li>
            <li><a href="edit_profile.php">Edit Profile</a></li>
            <li><a href="reservations.php">Reservations</a></li>
            <li><a href="sit_in_history.php">Sit-in History</a></li>   
            <li><a href="student_dashboard.php?logout=1" class="logout-btn">Log out</a></li>
        </ul>
    </nav>

    <main class="dashboard-home-container">
        <div class="dashboard-home-grid">
            <!-- Card 1: Student Information -->
            <section class="dh-col-4">
                <div class="dh-card">
                    <div class="dh-header-flex dh-header-center">
                        <h2 style="display: flex; align-items: center; gap: 0.5rem">
                            <span class="material-symbols-outlined" style="color: var(--dh-primary)">person</span>
                            Student Information
                        </h2>
                    </div>
                    <div class="dh-profile-header">
                        <a href="edit_profile.php" class="dh-avatar-wrapper" title="Click to upload or change profile picture">
                            <img src="./<?= esc($avatarPath) ?>" alt="Student avatar">
                        </a>
                        <h2><?= esc($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                        <p class="dh-text-xs" style="margin-top: 0.5rem; font-weight: 500">
                            ID: <?= esc($user['id_number']) ?>
                        </p>
                    </div>
                    <div class="dh-info-group">
                        <span class="dh-label-tiny">Course &amp; Department</span>
                        <p class="dh-text-sm"><?= esc($user['course']) ?> - College of Computer Studies</p>
                    </div>
                    <div class="dh-info-row">
                        <div class="dh-info-group">
                            <span class="dh-label-tiny">Year Level</span>
                            <p class="dh-text-sm"><?= esc(yearLabel($user['course_level'])) ?></p>
                        </div>
                        <div class="dh-info-group">
                            <span class="dh-label-tiny">Remaining Sessions</span>
                            <p class="dh-text-sm"><?= $remainingSessions ?></p>
                        </div>
                    </div>
                    <div class="dh-info-group">
                        <span class="dh-label-tiny">Email Address</span>
                        <p class="dh-text-sm"><?= esc($user['email']) ?></p>
                    </div>
                    <div class="dh-info-group" style="border: none; margin-bottom: 0">
                        <span class="dh-label-tiny">Home Address</span>
                        <p class="dh-text-sm"><?= esc($user['address']) ?></p>
                    </div>
                </div>
            </section>

            <!-- Card 2: Announcement Feed -->
            <section class="dh-col-4">
                <div class="dh-card dh-card-low">
                    <div class="dh-header-flex dh-header-center">
                        <h2 style="display: flex; align-items: center; gap: 0.5rem">
                            <span class="material-symbols-outlined" style="color: var(--dh-secondary)">campaign</span>
                            Announcements
                        </h2>
                        <?php if ($newAnnCount > 0): ?>
                            <span class="dh-badge-overlap"><?= $newAnnCount ?> NEW</span>
                        <?php endif; ?>
                    </div>
                    <div class="dh-announcement-list">
                        <?php if (empty($announcements)): ?>
                            <div class="dh-empty-state">
                                <span class="material-symbols-outlined">campaign</span>
                                <p>No announcements yet.</p>
                            </div>
                        <?php else: ?>
                            <?php
                            $borderColors = ['dh-secondary', 'dh-primary', 'dh-muted'];
                            foreach ($announcements as $idx => $ann):
                                $colorClass = '';
                                if ($idx === 0) $colorClass = '';
                                elseif ($idx === 1) $colorClass = 'dh-primary';
                                else $colorClass = 'dh-muted';

                                $isNew = strtotime($ann['created_at']) >= strtotime('-7 days');
                            ?>
                                <article class="dh-announcement-item <?= $colorClass ?>">
                                    <div class="dh-item-meta">
                                        <span class="dh-label-tiny" style="color: <?= $idx === 0 ? 'var(--dh-secondary)' : ($idx === 1 ? 'var(--dh-primary)' : 'var(--dh-outline)') ?>; margin: 0">
                                            <?= esc($ann['display_name']) ?>
                                        </span>
                                        <span class="dh-label-tiny" style="margin: 0">
                                            <?= date('Y-M-d', strtotime($ann['created_at'])) ?>
                                        </span>
                                    </div>
                                    <p class="dh-text-xs dh-announcement-content">
                                        <?= nl2br(esc($ann['content'])) ?>
                                    </p>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Card 3: Laboratory Policies -->
            <section class="dh-col-4">
                <div class="dh-card">
                    <div class="dh-header-flex dh-header-center">
                        <h2 style="display: flex; align-items: center; gap: 0.5rem">
                            <span class="material-symbols-outlined" style="color: var(--dh-tertiary)">gavel</span>
                            Rules and Regulations
                        </h2>
                    </div>
                    <ol class="dh-policy-list">
                        <li class="dh-policy-item">
                            <span class="dh-policy-number">01</span>
                            <div>
                                <h3>Strict Silence Policy</h3>
                                <p class="dh-text-xs">
                                    Maintain absolute silence. Group discussions should be held
                                    in designated collaboration hubs only.
                                </p>
                            </div>
                        </li>
                        <li class="dh-policy-item">
                            <span class="dh-policy-number">02</span>
                            <div>
                                <h3>Equipment Handling</h3>
                                <p class="dh-text-xs">
                                    Report hardware malfunctions. Do not attempt to repair or
                                    dismantle peripheral devices.
                                </p>
                            </div>
                        </li>
                        <li class="dh-policy-item">
                            <span class="dh-policy-number">03</span>
                            <div>
                                <h3>Consumption Prohibition</h3>
                                <p class="dh-text-xs">
                                    Food, drinks, and water bottles are strictly prohibited
                                    inside the terminal area.
                                </p>
                            </div>
                        </li>
                        <li class="dh-policy-item">
                            <span class="dh-policy-number">04</span>
                            <div>
                                <h3>Data Management</h3>
                                <p class="dh-text-xs">
                                    Local drives are wiped weekly. Use cloud storage or external
                                    drives for project files.
                                </p>
                            </div>
                        </li>
                    </ol>
                    <div class="dh-warning-footer">
                        <span class="material-symbols-outlined dh-icon-small" style="color: var(--dh-tertiary)">gavel</span>
                        <p>
                            Non-compliance with policies may result in temporary suspension
                            of laboratory privileges.
                        </p>
                    </div>
                </div>
            </section>
        </div>
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