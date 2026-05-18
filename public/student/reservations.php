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

// Handle AJAX request for occupied PCs
if (isset($_GET['action']) && $_GET['action'] === 'get_occupied_pcs') {
    $lab = $_GET['lab'] ?? '';
    $date = $_GET['date'] ?? '';
    
    $occupied = [];
    if (!empty($lab) && !empty($date)) {
        $stmt = $conn->prepare("SELECT pc_number FROM reservations WHERE lab = ? AND reservation_date = ? AND status IN ('Pending', 'Approved') AND pc_number IS NOT NULL");
        $stmt->bind_param('ss', $lab, $date);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $occupied[] = (int) $row['pc_number'];
        }
        $stmt->close();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['occupied' => $occupied]);
    exit;
}

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
    $pc_number = isset($_POST['pc_number']) ? (int)$_POST['pc_number'] : null;
    $date = trim($_POST['reservation_date'] ?? '');
    $time = trim($_POST['reservation_time'] ?? '');

    if (empty($purpose) || empty($lab) || empty($pc_number) || empty($date) || empty($time)) {
        $error = 'Please fill in all fields and select an available PC.';
    } else {
        $stmt = $conn->prepare("INSERT INTO reservations (user_id, id_number, student_name, purpose, lab, pc_number, reservation_date, reservation_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issssiss', $userId, $studentId, $studentName, $purpose, $lab, $pc_number, $date, $time);
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
                                <select id="lab" name="lab" required aria-required="true">
                                    <option value="" disabled selected>Select lab</option>
                                    <option value="524">524</option>
                                    <option value="526">526</option>
                                    <option value="528">528</option>
                                    <option value="530">530</option>
                                    <option value="542">542</option>
                                    <option value="544">544</option>
                                </select>
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

                        <!-- Interactive PC Grid -->
                        <div class="res-field pc-grid-container" id="pcGridContainer" style="display: none; margin-top: 1.5rem;">
                            <label>Select a PC <span class="required" aria-hidden="true">*</span></label>
                            <p class="pc-instructions dh-text-xs dh-muted">Choose an available computer for your session.</p>
                            
                            <div class="pc-grid-legend">
                                <div class="legend-item"><div class="pc-box available"></div> Available</div>
                                <div class="legend-item"><div class="pc-box selected"></div> Selected</div>
                                <div class="legend-item"><div class="pc-box occupied"></div> Occupied</div>
                            </div>
                            
                            <div class="pc-grid" id="pcGrid">
                                <!-- PCs will be injected via JS -->
                            </div>
                            <input type="hidden" id="pc_number" name="pc_number" required>
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
    </div>

    <?php renderStudentNotificationScript($notificationFeatureEnabled); ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const labSelect = document.getElementById('lab');
            const dateInput = document.getElementById('reservation_date');
            const pcGridContainer = document.getElementById('pcGridContainer');
            const pcGrid = document.getElementById('pcGrid');
            const pcNumberInput = document.getElementById('pc_number');
            const totalPCs = 30; // Assuming 30 PCs per lab
            
            function fetchAndRenderPCs() {
                const lab = labSelect.value;
                const date = dateInput.value;
                
                if (lab && date) {
                    pcGridContainer.style.display = 'block';
                    pcGrid.innerHTML = '<div style="text-align:center; padding:20px; grid-column:1/-1;">Loading PCs...</div>';
                    
                    fetch(`reservations.php?action=get_occupied_pcs&lab=${encodeURIComponent(lab)}&date=${encodeURIComponent(date)}`)
                        .then(res => res.json())
                        .then(data => {
                            const occupied = data.occupied || [];
                            pcGrid.innerHTML = '';
                            
                            for (let i = 1; i <= totalPCs; i++) {
                                const pcBox = document.createElement('div');
                                pcBox.classList.add('pc-box');
                                pcBox.textContent = `PC ${i}`;
                                
                                if (occupied.includes(i)) {
                                    pcBox.classList.add('occupied');
                                    pcBox.title = 'This PC is already reserved';
                                } else {
                                    pcBox.classList.add('available');
                                    if (pcNumberInput.value == i) {
                                        pcBox.classList.add('selected');
                                    }
                                    
                                    pcBox.addEventListener('click', function() {
                                        // Deselect others
                                        document.querySelectorAll('.pc-box.selected').forEach(box => {
                                            box.classList.remove('selected');
                                        });
                                        
                                        // Select this one
                                        this.classList.add('selected');
                                        pcNumberInput.value = i;
                                    });
                                }
                                
                                pcGrid.appendChild(pcBox);
                            }
                        })
                        .catch(err => {
                            console.error('Failed to fetch PCs', err);
                            pcGrid.innerHTML = '<div style="color:red; grid-column:1/-1;">Error loading PCs. Please try again.</div>';
                        });
                } else {
                    pcGridContainer.style.display = 'none';
                    pcNumberInput.value = ''; // Clear selection
                }
            }
            
            labSelect.addEventListener('change', fetchAndRenderPCs);
            dateInput.addEventListener('change', fetchAndRenderPCs);
            
            // Initial check if values are pre-filled (e.g. after form error)
            if (labSelect.value && dateInput.value) {
                fetchAndRenderPCs();
            }
        });
    </script>
</body>

</html>