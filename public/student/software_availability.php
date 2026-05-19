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

// Fetch software by lab
$standardLabs = ['524', '526', '528', '530', '542', '544'];
$labSoftware = [];
foreach ($standardLabs as $lab) {
    $labSoftware[$lab] = [];
}
$r = $conn->query("SELECT lab, software_name FROM lab_software ORDER BY lab, software_name");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $labSoftware[$row['lab']][] = $row['software_name'];
    }
}

$labColors = [
    '524' => 'var(--primary, #002a5c)',
    '526' => '#2e7d32',
    '528' => '#e65100',
    '530' => '#7b1fa2',
    '542' => '#c2185b',
    '544' => '#00796b',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Software & Lab Availability</title>
    <link rel="stylesheet" href="../assets/css/shared/global.css">
    <link rel="stylesheet" href="../assets/css/shared/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/student/student_dashboard.css">
    <!-- Material Symbols Outlined -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="../assets/images/ccs.png">
    <style>
        .sw-student-page { padding: 2rem 4rem; }
        .sw-student-page h1 {
            font-family: 'Manrope', sans-serif; font-size: 1.75rem; font-weight: 800;
            color: var(--primary-blue, #002a5c); margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 10px;
        }
        .sw-student-page h1 .material-symbols-outlined { font-size: 28px; }
        .sw-lab-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.25rem;
        }
        .sw-lab-panel {
            background: var(--surface-container, #fff);
            border-radius: 12px; overflow: hidden;
            border: 1px solid var(--outline-variant, #e0e0e0); 
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .sw-lab-panel:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.08); }
        .sw-lab-panel-header {
            padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid var(--outline-variant, #f0f0f0);
        }
        .sw-lab-panel-header h3 { font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: 8px; margin: 0; }
        .sw-lab-panel-header h3 .material-symbols-outlined { font-size: 22px; }
        .sw-lab-panel-header .sw-count { font-size: 12px; padding: 3px 10px; border-radius: 12px; font-weight: 600; }
        .sw-lab-panel-body { padding: 1rem 1.5rem 1.25rem; }
        .sw-software-list { list-style: none; padding: 0; margin: 0; }
        .sw-software-list li {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 0; border-bottom: 1px solid var(--outline-variant, #f0f0f0); font-size: 13.5px;
            font-weight: 500; color: var(--on-surface, #1e293b);
        }
        .sw-software-list li:last-child { border-bottom: none; }
        .sw-software-list li .sw-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
        .sw-empty { color: #999; font-size: 13px; font-style: italic; padding: 8px 0; }

        @media (max-width: 768px) {
            .sw-student-page { padding: 1.5rem 1rem; }
            .sw-lab-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body class="dashboard-body">
    <?php renderStudentNavbar('software', $newAnnCount); ?>

    <main class="sw-student-page">
        <h1>
            Software & Laboratory Availability
        </h1>

        <div class="sw-lab-grid">
            <?php foreach ($labSoftware as $lab => $items):
                $accentColor = $labColors[$lab] ?? 'var(--primary, #002a5c)';
            ?>
                <div class="sw-lab-panel" style="border-top: 4px solid <?= $accentColor ?>;">
                    <div class="sw-lab-panel-header">
                        <h3 style="color: <?= $accentColor ?>;">
                            <span class="material-symbols-outlined">computer</span>
                            Room <?= esc($lab) ?>
                        </h3>
                        <span class="sw-count" style="color: <?= $accentColor ?>; background: <?= $accentColor ?>15;"><?= count($items) ?> software</span>
                    </div>
                    <div class="sw-lab-panel-body">
                        <?php if (empty($items)): ?>
                            <p class="sw-empty">No software listed for this lab yet.</p>
                        <?php else: ?>
                            <ul class="sw-software-list">
                                <?php foreach ($items as $sw): ?>
                                    <li>
                                        <span class="sw-dot" style="background: <?= $accentColor ?>;"></span>
                                        <?= esc($sw) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <?php renderStudentNotificationScript($notificationFeatureEnabled); ?>
</body>
</html>
