<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$idNumber = $_SESSION['id_number'] ?? '';

// Fetch student info for pre-fill
$stmt = $conn->prepare("SELECT id_number, first_name, last_name FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$studentName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$studentId = $user['id_number'] ?? '';

function esc($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

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
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/shared/global.css">
    <link rel="stylesheet" href="../assets/css/shared/navbar.css">
    <link rel="stylesheet" href="../assets/css/student/student_dashboard.css">
    <link rel="icon" type="image/x-icon" href="../assets/images/ccs.png">
    <style>
        .reservation-page {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .reservation-page h1 {
            text-align: center;
            font-size: 28px;
            color: #1a1a2e;
            margin-bottom: 24px;
        }

        .res-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .res-card-header {
            background: linear-gradient(135deg, #0b4b8f, #1a6fd4);
            color: #fff;
            padding: 12px 20px;
            font-size: 16px;
            font-weight: 700;
        }

        .res-card-body {
            padding: 24px;
        }

        .res-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px 24px;
        }

        .res-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .res-field label {
            font-size: 13px;
            font-weight: 600;
            color: #444;
        }

        .res-field input {
            padding: 10px 14px;
            border: 1.5px solid #dde2ea;
            border-radius: 8px;
            font-size: 14px;
            background: #f8f9fb;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .res-field input:focus {
            border-color: #4a90d9;
            box-shadow: 0 0 0 3px rgba(74, 144, 217, 0.12);
            background: #fff;
        }

        .res-field input[readonly] {
            background: #eee;
            cursor: not-allowed;
        }

        .res-submit-row {
            margin-top: 20px;
        }

        .res-submit-btn {
            background: linear-gradient(135deg, #0b4b8f, #2471c9);
            color: #fff;
            border: none;
            padding: 11px 36px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.2s;
        }

        .res-submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(11, 75, 143, 0.35);
        }

        .res-msg {
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 16px;
        }

        .res-msg.success {
            background: #e8f8e8;
            color: #27ae60;
            border: 1px solid #c3e6cb;
        }

        .res-msg.error {
            background: #fff0f0;
            color: #c0392b;
            border: 1px solid #f5c6cb;
        }

        /* History table */
        .history-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .history-table th {
            background: #0b4b8f;
            color: #fff;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
        }

        .history-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f0f0f0;
            color: #333;
        }

        .history-table tr:hover td {
            background: #f0f5ff;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .no-history {
            text-align: center;
            color: #999;
            padding: 30px;
            font-size: 15px;
        }

        @media (max-width: 600px) {
            .res-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="dashboard-body">

    <nav class="academic-ledger-navbar">
        <div class="nav-container">
            <div class="brand">
                <h1 class="brand-title">College of Computer Studies Sit-in Monitoring System</h1>
            </div>
            <div class="nav-links">
                <a class="nav-link" href="student_dashboard.php">Home</a>
                <a class="nav-link" href="edit_profile.php">Edit Profile</a>
                <a class="nav-link active" href="reservations.php">Reservations</a>
                <a class="nav-link" href="sit_in_history_student.php">Sit-in History</a>
                <a class="nav-logout" href="student_dashboard.php?logout=1">Log out</a>
            </div>
        </div>
    </nav>

    <div class="reservation-page">
        <h1>Sit-in Reservation</h1>

        <?php if ($success): ?>
            <div class="res-msg success"><?= esc($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="res-msg error"><?= esc($error) ?></div>
        <?php endif; ?>

        <!-- Create Reservation -->
        <div class="res-card">
            <div class="res-card-header">Create Reservation</div>
            <div class="res-card-body">
                <form method="POST" action="reservations.php">
                    <div class="res-grid">
                        <div class="res-field">
                            <label>ID Number</label>
                            <input type="text" value="<?= esc($studentId) ?>" readonly>
                        </div>
                        <div class="res-field">
                            <label>Student Name</label>
                            <input type="text" value="<?= esc($studentName) ?>" readonly>
                        </div>
                        <div class="res-field">
                            <label for="purpose">Purpose</label>
                            <input type="text" id="purpose" name="purpose" placeholder="e.g. C Programming" required>
                        </div>
                        <div class="res-field">
                            <label for="lab">Lab</label>
                            <input type="text" id="lab" name="lab" placeholder="e.g. 524" required>
                        </div>
                        <div class="res-field">
                            <label for="reservation_date">Reservation Date</label>
                            <input type="date" id="reservation_date" name="reservation_date" required>
                        </div>
                        <div class="res-field">
                            <label for="reservation_time">Reservation Time</label>
                            <input type="time" id="reservation_time" name="reservation_time" required>
                        </div>
                    </div>
                    <div class="res-submit-row">
                        <button type="submit" class="res-submit-btn">Reserve</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reservation History -->
        <div class="res-card">
            <div class="res-card-header">My Reservation History</div>
            <div class="res-card-body" style="padding: 0;">
                <?php if (empty($reservations)): ?>
                    <p class="no-history">You have no reservations yet.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Purpose</th>
                                    <th>Lab</th>
                                    <th>Schedule</th>
                                    <th>Status</th>
                                    <th>Admin Note</th>
                                    <th>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $res): ?>
                                    <tr>
                                        <td><?= esc($res['purpose']) ?></td>
                                        <td><?= esc($res['lab']) ?></td>
                                        <td><?= date('M d, Y h:i A', strtotime($res['reservation_date'] . ' ' . $res['reservation_time'])) ?>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = 'status-' . esc(strtolower($res['status']));
                                            ?>
                                            <span class="status-badge <?= $statusClass ?>"><?= esc($res['status']) ?></span>
                                        </td>
                                        <td><?= esc($res['admin_note'] ?? '') ?></td>
                                        <td><?= date('M d, Y h:i A', strtotime($res['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>

</html>