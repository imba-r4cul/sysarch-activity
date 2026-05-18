<?php
session_start();
require_once '../../config/database.php';
require_once '../includes/helpers.php';
require_once '../includes/student_notifications.php';
require_once '../includes/student_navbar.php';

// Guard: student only
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$studentId = $_SESSION['student_id'] ?? '';
$studentName = $_SESSION['student_name'] ?? 'Student';

$notificationFeatureEnabled = studentNotificationFeatureEnabled($conn);
studentHandleNotificationAjax($conn, $userId, $notificationFeatureEnabled);
$newAnnCount = studentFetchUnreadNotificationCount($conn, $userId, $notificationFeatureEnabled);

// Fetch reservation history
$reservations = [];
$stmt = $conn->prepare("SELECT purpose, lab, pc_number, reservation_date, reservation_time, status, admin_note, created_at FROM reservations WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $reservations[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservation History</title>
    <link rel="stylesheet" href="../assets/css/shared/global.css">
    <link rel="stylesheet" href="../assets/css/shared/navbar.css">
    <link rel="stylesheet" href="../assets/css/student/student_dashboard.css">
    <link rel="stylesheet" href="../assets/css/student/reservations.css">
    <link rel="icon" type="image/x-icon" href="../assets/images/ccs.png">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700&display=swap" rel="stylesheet">
</head>

<body class="dashboard-body">
    <?php renderStudentNavbar('reservations', $newAnnCount); ?>

    <div class="reservation-page">
        <div class="reservation-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
            <h1 style="margin: 0; display: flex; align-items: center; gap: 12px; font-size: 32px; font-weight: 800; color: var(--primary-blue);">
                <span class="material-symbols-outlined" style="font-size: 36px;">history</span>
                My Reservation History
            </h1>
            <a href="reservations.php" class="history-link-btn" style="display: flex; align-items: center; gap: 8px; text-decoration: none; padding: 10px 20px; border-radius: 12px; background: #fff; color: var(--primary-blue); font-weight: 700; font-size: 14px; border: 1.5px solid var(--primary-blue); transition: all 0.2s;">
                <span class="material-symbols-outlined" style="font-size: 20px;">add_circle</span>
                New Reservation
            </a>
        </div>

        <div class="res-card" style="box-shadow: var(--card-shadow); border-radius: 20px; overflow: hidden; background: var(--glass-bg); border: 1px solid rgba(226, 232, 240, 0.8);">
            <div class="res-card-header" id="history-header">All Reservations</div>
            <div class="res-card-body" style="padding: 0;">
                <?php if (empty($reservations)): ?>
                    <div class="no-history">
                        <p>You have no reservations yet.</p>
                    </div>
                <?php else: ?>
                    <div class="history-table-container">
                        <table class="history-table" aria-labelledby="history-header">
                            <thead>
                                <tr>
                                    <th scope="col">Purpose</th>
                                    <th scope="col">Lab / PC</th>
                                    <th scope="col">Schedule</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Admin Note</th>
                                    <th scope="col">Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $res): ?>
                                    <tr>
                                        <td data-label="Purpose"><?= esc($res['purpose']) ?></td>
                                        <td data-label="Lab / PC">Lab <?= esc($res['lab']) ?> - PC <?= esc($res['pc_number'] ?? 'N/A') ?></td>
                                        <td data-label="Schedule">
                                            <strong><?= date('M d, Y', strtotime($res['reservation_date'])) ?></strong><br>
                                            <small><?= date('h:i A', strtotime($res['reservation_time'])) ?></small>
                                        </td>
                                        <td data-label="Status">
                                            <?php
                                            $statusClass = 'status-' . esc(strtolower($res['status']));
                                            ?>
                                            <span class="status-badge <?= $statusClass ?>" role="status"><?= esc($res['status']) ?></span>
                                        </td>
                                        <td data-label="Admin Note"><?= esc($res['admin_note'] ?? '—') ?></td>
                                        <td data-label="Submitted">
                                            <small><?= date('M d, Y h:i A', strtotime($res['created_at'])) ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php renderStudentNotificationScript($notificationFeatureEnabled); ?>
</body>
</html>
