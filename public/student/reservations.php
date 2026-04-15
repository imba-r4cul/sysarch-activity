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
$idNumber = $_SESSION['id_number'] ?? '';

$notificationFeatureEnabled = studentNotificationFeatureEnabled($conn);
studentHandleNotificationAjax($conn, $userId, $notificationFeatureEnabled);
$newAnnCount = studentFetchUnreadNotificationCount($conn, $userId, $notificationFeatureEnabled);

// Fetch student info for pre-fill
$stmt = $conn->prepare("SELECT id_number, first_name, last_name FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$studentName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$studentId = $user['id_number'] ?? '';

// Handle reservation form
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purpose = trim($_POST['purpose'] ?? '');
    $lab = trim($_POST['lab'] ?? '');
    $date = trim($_POST['reservation_date'] ?? '');
    $time = trim($_POST['reservation_time'] ?? '');

    if (empty($purpose) || empty($lab) || empty($date) || empty($time)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare("INSERT INTO reservations (user_id, id_number, student_name, purpose, lab, reservation_date, reservation_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issssss', $userId, $studentId, $studentName, $purpose, $lab, $date, $time);
        if ($stmt->execute()) {
            $success = 'Reservation submitted successfully!';
        } else {
            $error = 'Failed to submit reservation. Please try again.';
        }
        $stmt->close();
    }
}

// Fetch history
$reservations = [];
$stmt = $conn->prepare("SELECT purpose, lab, reservation_date, reservation_time, status, admin_note, created_at FROM reservations WHERE user_id = ? ORDER BY created_at DESC");
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
    <title>Sit-in Reservation</title>
    <link rel="stylesheet" href="../assets/css/shared/global.css">
    <link rel="stylesheet" href="../assets/css/shared/navbar.css">
    <link rel="stylesheet" href="../assets/css/student/student_dashboard.css">
    <link rel="stylesheet" href="../assets/css/student/reservations.css">
    <link rel="icon" type="image/x-icon" href="../assets/images/ccs.png">
</head>

<body class="dashboard-body">
    <?php renderStudentNavbar('reservations', $newAnnCount); ?>

    <div class="reservation-page">
        <h1>
            Sit-in Reservation
        </h1>

        <?php if ($success): ?>
            <div class="res-msg success" role="alert" aria-live="polite">
                <span class="res-msg-icon">✓</span>
                <?= esc($success) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="res-msg error" role="alert" aria-live="assertive">
                <span class="res-msg-icon">✕</span>
                <?= esc($error) ?>
            </div>
        <?php endif; ?>

        <div class="bento-grid">
            <!-- Create Reservation -->
            <div class="res-card">
                <div class="res-card-header" id="form-header">Create Reservation</div>
                <div class="res-card-body">
                    <form method="POST" action="reservations.php" aria-labelledby="form-header">
                        <div class="res-grid">
                            <div class="res-field">
                                <label for="id_number_display">ID Number</label>
                                <input type="text" id="id_number_display" value="<?= esc($studentId) ?>" readonly aria-readonly="true">
                            </div>
                            <div class="res-field">
                                <label for="student_name_display">Student Name</label>
                                <input type="text" id="student_name_display" value="<?= esc($studentName) ?>" readonly aria-readonly="true">
                            </div>
                            <div class="res-field">
                                <label for="purpose">Purpose <span class="required" aria-hidden="true">*</span></label>
                                <input type="text" id="purpose" name="purpose" placeholder="e.g. C Programming" required aria-required="true">
                            </div>
                            <div class="res-field">
                                <label for="lab">Lab <span class="required" aria-hidden="true">*</span></label>
                                <input type="text" id="lab" name="lab" placeholder="e.g. 524" required aria-required="true">
                            </div>
                            <div class="res-field">
                                <label for="reservation_date">Reservation Date <span class="required" aria-hidden="true">*</span></label>
                                <input type="date" id="reservation_date" name="reservation_date" required aria-required="true">
                            </div>
                            <div class="res-field">
                                <label for="reservation_time">Reservation Time <span class="required" aria-hidden="true">*</span></label>
                                <input type="time" id="reservation_time" name="reservation_time" required aria-required="true">
                            </div>
                        </div>
                        <div class="res-submit-row">
                            <button type="submit" class="res-submit-btn">
                                <span>Complete Reservation</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Reservation History -->
            <div class="res-card">
                <div class="res-card-header" id="history-header">My Reservation History</div>
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
                                        <th scope="col">Lab</th>
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
                                            <td data-label="Lab"><?= esc($res['lab']) ?></td>
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
    </div>

    <?php renderStudentNotificationScript($notificationFeatureEnabled); ?>

</body>

</html>