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

// Check if reservations are enabled
$reservationsEnabled = true;
$rCheck = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'reservations_enabled'");
if ($rCheck && $rRow = $rCheck->fetch_assoc()) {
    $reservationsEnabled = $rRow['setting_value'] === '1';
}

// Handle reservation form
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reservationsEnabled) {
    $purpose = trim($_POST['purpose'] ?? '');
    $lab = trim($_POST['lab'] ?? '');
    $pc_number = isset($_POST['pc_number']) ? (int)$_POST['pc_number'] : null;
    $date = trim($_POST['reservation_date'] ?? '');
    $time = trim($_POST['reservation_time'] ?? '');

    if (empty($purpose) || empty($lab) || empty($pc_number) || empty($date) || empty($time)) {
        $error = 'Please fill in all fields and select an available PC.';
    } else {
        $stmt = $conn->prepare("INSERT INTO reservations (user_id, purpose, lab, pc_number, reservation_date, reservation_time) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ississ', $userId, $purpose, $lab, $pc_number, $date, $time);
        if ($stmt->execute()) {
            $success = 'Reservation submitted successfully! Please wait for admin approval.';
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
    <link rel="stylesheet" href="../assets/css/shared/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/student/student_dashboard.css">
    <link rel="stylesheet" href="../assets/css/student/reservations.css?v=<?php echo time(); ?>">
    <link rel="icon" type="image/x-icon" href="../assets/images/ccs.png">
</head>

<body class="dashboard-body">
    <?php renderStudentNavbar('reservations', $newAnnCount); ?>

    <div class="reservation-page">
        <div class="reservation-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
            <h1 style="margin: 0; display: flex; align-items: center; gap: 12px; font-size: 32px; font-weight: 800; color: var(--primary-blue);">
                Sit-in Reservation
            </h1>
            <a href="reservation_history.php" class="history-link-btn" style="display: flex; align-items: center; gap: 8px; text-decoration: none; padding: 10px 20px; border-radius: 12px; background: #fff; color: var(--primary-blue); font-weight: 700; font-size: 14px; border: 1.5px solid var(--primary-blue); transition: all 0.2s;">
                <span class="material-symbols-outlined" style="font-size: 20px;">history</span>
                My Reservation History
            </a>
        </div>

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

        <?php if (!$reservationsEnabled): ?>
            <div style="background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%); border: 1.5px solid #fc8181; border-radius: 16px; padding: 2.5rem; text-align: center; margin-bottom: 2rem;">
                <span class="material-symbols-outlined" style="font-size: 56px; color: #c53030; display: block; margin-bottom: 12px;">lock</span>
                <h2 style="color: #c53030; font-size: 1.4rem; font-weight: 700; margin-bottom: 8px;">Reservations Temporarily Disabled</h2>
                <p style="color: #742a2a; font-size: 14px; max-width: 480px; margin: 0 auto; line-height: 1.6;">Sit-in reservations have been temporarily disabled by the laboratory administrator. Please check back later or contact your instructor for assistance.</p>
            </div>
        <?php else: ?>

        <form method="POST" action="reservations.php" id="reservationForm">
            <div class="bento-grid">
                <!-- Create Reservation Details (Left) -->
                <div class="res-card">
                    <div class="res-card-header" id="form-header">Create Reservation</div>
                    <div class="res-card-body">
                        <div class="res-form-rows">
                            <!-- Row 1: ID Number & Student Name -->
                            <div class="res-form-row">
                                <div class="res-field">
                                    <label for="id_number_display">ID Number</label>
                                    <input type="text" id="id_number_display" value="<?= esc($studentId) ?>" readonly aria-readonly="true">
                                </div>
                                <div class="res-field">
                                    <label for="student_name_display">Student Name</label>
                                    <input type="text" id="student_name_display" value="<?= esc($studentName) ?>" readonly aria-readonly="true">
                                </div>
                            </div>
                            
                            <!-- Row 2: Lab & Purpose -->
                            <div class="res-form-row">
                                <div class="res-field">
                                    <label for="lab">Lab</label>
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
                                    <label for="purpose">Purpose</label>
                                    <select id="purpose" name="purpose" required aria-required="true">
                                        <option value="" disabled selected>Select purpose</option>
                                        <option value="Python">Python</option>
                                        <option value="C#">C#</option>
                                        <option value="PHP">PHP</option>
                                        <option value="Java">Java</option>
                                        <option value="C++">C++</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Row 3: Reservation Date & Reservation Time -->
                            <div class="res-form-row">
                                <div class="res-field">
                                    <label for="reservation_date">Reservation Date</label>
                                    <input type="date" id="reservation_date" name="reservation_date" required aria-required="true">
                                </div>
                                <div class="res-field">
                                    <label for="reservation_time">Reservation Time</label>
                                    <input type="time" id="reservation_time" name="reservation_time" required aria-required="true">
                                </div>
                            </div>
                        </div>
                        
                        <div class="res-submit-row">
                            <button type="submit" class="res-submit-btn">
                                <span>Complete Reservation</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Select a PC (Right) -->
                <div class="res-card">
                    <div class="res-card-header" id="pc-header">Select a PC</div>
                    <div class="res-card-body" id="pcCardBody">
                        
                        <!-- PC Grid placeholder -->
                        <div id="pcSelectionPlaceholder" class="no-history" style="padding: 64px 24px; text-align: center; color: #94a3b8; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; min-height: 250px;">
                            <span class="material-symbols-outlined" style="font-size: 56px; margin-bottom: 16px; opacity: 0.5;">desktop_windows</span>
                            <p style="margin: 0; font-weight: 500; font-size: 15px;">Please select Lab to show available PCs.</p>
                        </div>

                        <!-- Interactive PC Grid (rendered dynamically) -->
                        <div class="res-field pc-grid-container" id="pcGridContainer" style="display: none; border: none; padding: 0; background: transparent;">
                            <p class="pc-instructions dh-text-xs dh-muted" style="margin-bottom: 16px;">Choose an available computer for your session.</p>
                            
                            <div class="pc-grid-legend" style="margin-bottom: 20px;">
                                <div class="legend-item">
                                    <div class="pc-box available">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">desktop_windows</span>
                                        <span style="font-size: 8px; font-weight: 700; text-transform: uppercase; margin-top: -2px;">PC</span>
                                    </div>
                                    Available
                                </div>
                                <div class="legend-item">
                                    <div class="pc-box selected">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">desktop_windows</span>
                                        <span style="font-size: 8px; font-weight: 700; text-transform: uppercase; margin-top: -2px;">PC</span>
                                    </div>
                                    Selected
                                </div>
                                <div class="legend-item">
                                    <div class="pc-box occupied">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">lock</span>
                                        <span style="font-size: 8px; font-weight: 700; text-transform: uppercase; margin-top: -2px;">PC</span>
                                    </div>
                                    Occupied
                                </div>
                            </div>
                            
                            <div class="pc-grid" id="pcGrid">
                                <!-- PCs will be injected via JS -->
                            </div>
                            <input type="hidden" id="pc_number" name="pc_number" required>
                        </div>

                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <?php renderStudentNotificationScript($notificationFeatureEnabled); ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const labSelect = document.getElementById('lab');
            const dateInput = document.getElementById('reservation_date');
            const pcGridContainer = document.getElementById('pcGridContainer');
            const pcSelectionPlaceholder = document.getElementById('pcSelectionPlaceholder');
            const pcGrid = document.getElementById('pcGrid');
            const pcNumberInput = document.getElementById('pc_number');
            const totalPCs = 30; // Assuming 30 PCs per lab
            
            function fetchAndRenderPCs() {
                const lab = labSelect.value;
                const date = dateInput.value;
                
                if (lab) {
                    pcSelectionPlaceholder.style.display = 'none';
                    pcGridContainer.style.display = 'block';
                    pcGrid.innerHTML = '<div style="text-align:center; padding:40px; grid-column:1/-1; color: #64748b;">Loading PCs...</div>';
                    
                    fetch(`reservations.php?action=get_occupied_pcs&lab=${encodeURIComponent(lab)}&date=${encodeURIComponent(date)}`)
                        .then(res => res.json())
                        .then(data => {
                            const occupied = data.occupied || [];
                            pcGrid.innerHTML = '';
                            
                            for (let i = 1; i <= totalPCs; i++) {
                                const pcBox = document.createElement('div');
                                pcBox.classList.add('pc-box');
                                
                                const iconSpan = document.createElement('span');
                                iconSpan.classList.add('material-symbols-outlined');
                                iconSpan.style.fontSize = '22px';
                                
                                const labelSpan = document.createElement('span');
                                labelSpan.textContent = `PC ${i}`;
                                labelSpan.style.fontSize = '10px';
                                labelSpan.style.fontWeight = '700';
                                
                                pcBox.appendChild(iconSpan);
                                pcBox.appendChild(labelSpan);
                                
                                if (occupied.includes(i)) {
                                    iconSpan.textContent = 'lock';
                                    pcBox.classList.add('occupied');
                                    pcBox.title = 'This PC is already reserved';
                                } else {
                                    iconSpan.textContent = 'desktop_windows';
                                    pcBox.classList.add('available');
                                    if (pcNumberInput.value == i) {
                                        pcBox.classList.add('selected');
                                    }
                                    
                                    pcBox.addEventListener('click', function() {
                                        // Deselect others inside grid ONLY (don't touch legend!)
                                        document.querySelectorAll('#pcGrid .pc-box.selected').forEach(box => {
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
                    pcSelectionPlaceholder.style.display = 'flex';
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

            // Auto-dismiss notification after 3 seconds
            const alerts = document.querySelectorAll('.res-msg');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.6s ease, transform 0.6s ease, margin-top 0.6s ease, padding 0.6s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    alert.style.marginTop = '-60px'; // Collapse space
                    alert.style.padding = '0';
                    setTimeout(() => alert.remove(), 600);
                }, 3000);
            });
        });
    </script>
</body>

</html>
