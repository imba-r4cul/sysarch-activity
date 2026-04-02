<?php
session_start();
require_once '../config/database.php';

// Guard: admin only
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminId = (int) $_SESSION['admin_id'];

function esc($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function studentInitials($firstName, $lastName)
{
    $first = trim((string) $firstName);
    $last = trim((string) $lastName);

    $a = $first !== '' ? strtoupper(substr($first, 0, 1)) : '';
    $b = $last !== '' ? strtoupper(substr($last, 0, 1)) : '';
    $initials = $a . $b;

    return $initials !== '' ? $initials : 'NA';
}

function studentDisplayName($student)
{
    $last = trim((string) ($student['last_name'] ?? ''));
    $first = trim((string) ($student['first_name'] ?? ''));
    $middle = trim((string) ($student['middle_name'] ?? ''));

    $name = $last;
    if ($first !== '') {
        $name .= ($name !== '' ? ', ' : '') . $first;
    }
    if ($middle !== '') {
        $name .= ' ' . $middle;
    }

    return trim($name) !== '' ? trim($name) : 'Unnamed Student';
}

function yearLevelLabel($level)
{
    $n = (int) $level;
    if ($n <= 0) {
        return 'N/A';
    }

    $suffix = 'th';
    if (($n % 100) < 11 || ($n % 100) > 13) {
        if (($n % 10) === 1) {
            $suffix = 'st';
        } elseif (($n % 10) === 2) {
            $suffix = 'nd';
        } elseif (($n % 10) === 3) {
            $suffix = 'rd';
        }
    }

    return $n . $suffix . ' Year';
}

function redirectStudentsView()
{
    header('Location: admin_dashboard.php?view=students');
    exit;
}

// ── Statistics ──
$totalStudents = 0;
$currentSitIn = 0;
$totalSitIn = 0;

$r = $conn->query("SELECT COUNT(*) AS c FROM users");
if ($r) {
    $totalStudents = (int) $r->fetch_assoc()['c'];
}

$r = $conn->query("SELECT COUNT(*) AS c FROM sit_in_records WHERE status='Active'");
if ($r) {
    $currentSitIn = (int) $r->fetch_assoc()['c'];
}

$r = $conn->query("SELECT COUNT(*) AS c FROM sit_in_records");
if ($r) {
    $totalSitIn = (int) $r->fetch_assoc()['c'];
}

// Purpose breakdown
$purposes = [];
$r = $conn->query("SELECT purpose, COUNT(*) AS c FROM sit_in_records GROUP BY purpose ORDER BY c DESC");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $purposes[$row['purpose']] = (int) $row['c'];
    }
}

// ── Student management actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_all_sessions'])) {
    $conn->query("DELETE FROM sit_in_records");
    redirectStudentsView();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $idNumber = trim($_POST['id_number'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $courseLevel = (int) ($_POST['course_level'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (
        $idNumber !== '' && ctype_digit($idNumber) && $lastName !== '' && $firstName !== '' && $middleName !== '' &&
        $course !== '' && $courseLevel > 0 && $email !== '' && $address !== '' && $password !== ''
    ) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (id_number, last_name, first_name, middle_name, course, course_level, email, address, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('sssssisss', $idNumber, $lastName, $firstName, $middleName, $course, $courseLevel, $email, $address, $passwordHash);
            if (!$stmt->execute()) {
                error_log('Failed to add student: ' . $stmt->error);
                $_SESSION['flash_error'] = 'An internal error occurred. Please try again later.';
            }
            $stmt->close();
        } else {
            error_log('Database error during student add: ' . $conn->error);
            $_SESSION['flash_error'] = 'An internal error occurred. Please try again later.';
        }
    } else {
        $_SESSION['flash_error'] = 'Please fill in all required fields correctly.';
    }

    redirectStudentsView();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $idNumber = trim($_POST['id_number'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $courseLevel = (int) ($_POST['course_level'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (
        $studentId > 0 && $idNumber !== '' && ctype_digit($idNumber) && $lastName !== '' && $firstName !== '' &&
        $middleName !== '' && $course !== '' && $courseLevel > 0 && $email !== '' && $address !== ''
    ) {
        $stmt = $conn->prepare("UPDATE users SET id_number = ?, last_name = ?, first_name = ?, middle_name = ?, course = ?, course_level = ?, email = ?, address = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('sssssissi', $idNumber, $lastName, $firstName, $middleName, $course, $courseLevel, $email, $address, $studentId);
            if (!$stmt->execute()) {
                error_log('Failed to update student: ' . $stmt->error);
                $_SESSION['flash_error'] = 'An internal error occurred. Please try again later.';
            }
            $stmt->close();
        } else {
            error_log('Database error during student update: ' . $conn->error);
            $_SESSION['flash_error'] = 'An internal error occurred. Please try again later.';
        }
    } else {
        $_SESSION['flash_error'] = 'Please fill in all required fields correctly.';
    }

    redirectStudentsView();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
    $studentId = (int) ($_POST['delete_student_id'] ?? 0);
    if ($studentId > 0) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $studentId);
            if (!$stmt->execute()) {
                error_log('Failed to delete student: ' . $stmt->error);
                $_SESSION['flash_error'] = 'An internal error occurred. Please try again later.';
            }
            $stmt->close();
        } else {
            error_log('Database error during student delete: ' . $conn->error);
            $_SESSION['flash_error'] = 'An internal error occurred. Please try again later.';
        }
    } else {
        $_SESSION['flash_error'] = 'Invalid student ID.';
    }

    redirectStudentsView();
}

// ── Handle announcement post ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['announcement_content'])) {
    $content = trim($_POST['announcement_content']);
    if ($content !== '') {
        $stmt = $conn->prepare("INSERT INTO announcements (admin_id, content) VALUES (?, ?)");
        $stmt->bind_param('is', $adminId, $content);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: admin_dashboard.php');
    exit;
}

// ── Handle sit-in submission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sitin_id_number'])) {
    $sitinIdNumber = trim($_POST['sitin_id_number']);
    $sitinPurpose = trim($_POST['sitin_purpose']);
    $sitinLab = trim($_POST['sitin_lab']);

    // Get user details from id_number
    $stmt = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE id_number = ?");
    $stmt->bind_param('s', $sitinIdNumber);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($user = $res->fetch_assoc()) {
        $uid = $user['id'];
        $firstName = $user['first_name'];
        $lastName = $user['last_name'];
        $ins = $conn->prepare("INSERT INTO sit_in_records (user_id, id_number, first_name, last_name, purpose, lab) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->bind_param('isssss', $uid, $sitinIdNumber, $firstName, $lastName, $sitinPurpose, $sitinLab);
        $ins->execute();
        $ins->close();
    }
    $stmt->close();
    header('Location: admin_dashboard.php');
    exit;
}

// ── Fetch announcements ──
$announcements = [];
$r = $conn->query("SELECT a.content, a.created_at, au.display_name FROM announcements a JOIN admin_users au ON a.admin_id = au.id ORDER BY a.created_at DESC LIMIT 20");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $announcements[] = $row;
    }
}

// ── Fetch student information table data ──
$studentRows = [];
$studentSql = "
    SELECT
        u.id,
        u.id_number,
        u.first_name,
        u.last_name,
        u.middle_name,
        u.email,
        u.address,
        u.course,
        u.course_level,
        GREATEST(0, 30 - IFNULL(sr.total_sessions, 0)) AS remaining_sessions
    FROM users u
    LEFT JOIN (
        SELECT user_id, COUNT(*) AS total_sessions
        FROM sit_in_records
        GROUP BY user_id
    ) sr ON sr.user_id = u.id
    ORDER BY u.id ASC
";
$r = $conn->query($studentSql);
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $studentRows[] = $row;
    }
}

// ── Fetch student details endpoint (AJAX) ──
if (isset($_GET['ajax_get_student_details'])) {
    header('Content-Type: application/json');
    
    // Enforce admin authentication
    if (!isset($_SESSION['admin_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $studentId = (int) ($_GET['student_id'] ?? 0);
    if ($studentId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid student ID']);
        exit;
    }
    
    // Fetch student details with proper escaping
    $stmt = $conn->prepare("SELECT id, email, address FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            // Return raw data for JSON - HTML escaping should be done client-side at render time
            echo json_encode([
                'id' => (int) $row['id'],
                'email' => $row['email'],
                'address' => $row['address']
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Student not found']);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// ── Search handling (AJAX) ──
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $q = '%' . trim($_GET['q'] ?? '') . '%';
    $students = [];

    $sql = "
        SELECT
            u.id_number,
            u.first_name,
            u.last_name,
            u.course,
            u.course_level,
            u.email,
            GREATEST(0, 30 - IFNULL(sr.total_sessions, 0)) AS remaining_sessions
        FROM users u
        LEFT JOIN (
            SELECT user_id, COUNT(*) AS total_sessions
            FROM sit_in_records
            GROUP BY user_id
        ) sr ON sr.user_id = u.id
        WHERE u.id_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.course LIKE ?
        ORDER BY u.id ASC
        LIMIT 20
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ssss', $q, $q, $q, $q);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();
    }

    echo json_encode($students);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="icon" type="image/x-icon" href="./images/ccs.png">
    <link
        href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/student_info.css">
    <link rel="stylesheet" href="css/admin_dashboard.css">
</head>

<body>

    <!-- ─── Navbar ─── -->
    <nav class="admin-nav">
        <span class="brand">CCS Sit-in Monitoring System (ADMIN DASHBOARD)</span>
        <ul>
            <li><a href="#" class="nav-active" id="nav-home" onclick="switchView('dashboard')">Home</a></li>
            <li><a href="#" id="nav-students" onclick="switchView('students')">Student Information</a></li>
            <li><button type="button" onclick="openModal('searchModal')">Search</button></li>
            <li><button type="button" onclick="openSitInForm()">Sit-in Form</button></li>
            <li><a href="admin_dashboard.php?logout=1" class="logout-link">Log out</a></li>
        </ul>
    </nav>

    <!-- ─── Dashboard Content ─── -->
    <div id="dashboard-view" class="admin-content">
        <!-- Statistics Card -->
        <div class="card">
            <div class="card-header">
                Statistics
            </div>
            <div class="card-body">
                <div class="stat-row">
                    <span>Students Registered</span>
                    <strong><?= $totalStudents ?></strong>
                </div>
                <div class="stat-row">
                    <span>Currently Sit-in</span>
                    <strong><?= $currentSitIn ?></strong>
                </div>
                <div class="stat-row">
                    <span>Total Sit-in</span>
                    <strong><?= $totalSitIn ?></strong>
                </div>

                <div class="progress-section">
                    <h4>Sit-in by Purpose</h4>
                    <?php
                    $barColors = ['bar-blue', 'bar-red', 'bar-green', 'bar-orange', 'bar-purple', 'bar-teal'];
                    $maxVal = $totalSitIn > 0 ? $totalSitIn : 1;
                    $i = 0;
                    if (empty($purposes)): ?>
                        <p style="color:#999; font-size:14px; text-align:center;">No sit-in data yet.</p>
                    <?php else:
                        foreach ($purposes as $name => $count):
                            $pct = round(($count / $maxVal) * 100);
                            $color = $barColors[$i % count($barColors)];
                            $i++;
                            ?>
                            <div class="progress-item">
                                <div class="progress-label">
                                    <span><?= esc($name) ?></span>
                                    <span><?= $count ?></span>
                                </div>
                                <div class="progress-bar-track">
                                    <div class="progress-bar-fill <?= $color ?>" style="width: <?= $pct ?>%"></div>
                                </div>
                            </div>
                            <?php
                        endforeach;
                    endif;
                    ?>
                </div>
            </div>
        </div>

        <!-- Announcements Card -->
        <div class="card">
            <div class="card-header">
                Announcement
            </div>
            <div class="card-body">
                <form class="announce-form" method="POST" action="admin_dashboard.php">
                    <textarea name="announcement_content" placeholder="Write a new announcement..." required></textarea>
                    <button type="submit">Submit</button>
                </form>

                <div class="announce-list">
                    <h4 style="margin:0 0 10px; font-size:15px; color:#333;">Posted Announcements</h4>
                    <?php if (empty($announcements)): ?>
                        <p style="color:#999; font-size:14px; text-align:center;">No announcements yet.</p>
                    <?php else: ?>
                        <?php foreach ($announcements as $ann): ?>
                            <div class="announce-item">
                                <div class="meta"><?= esc($ann['display_name']) ?> |
                                    <?= date('M d, Y', strtotime($ann['created_at'])) ?>
                                </div>
                                <div class="text"><?= nl2br(esc($ann['content'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── Student Information Content ─── -->
    <div id="students-view" class="student-info-wrapper" style="display: none;">
        <main>
            <section class="page-header">
                <div>
                    <h1>Students Information</h1>
                </div>
                <div class="header-actions">
                    <button type="button" class="btn resetSession-btn" onclick="confirmResetSessions()">
                        <span class="material-symbols-outlined">restart_alt</span>
                        Reset All Session
                    </button>
                    <button type="button" class="btn addStudent-btn" onclick="openAddStudentModal()">
                        <span class="material-symbols-outlined">person_add</span>
                        Add Students
                    </button>
                </div>
            </section>
            <?php if (isset($_SESSION['flash_error'])): ?>
                <div class="flash-message flash-error" style="margin-bottom: 20px; padding: 12px 16px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
                    <span><?= esc($_SESSION['flash_error']) ?></span>
                    <button type="button" class="flash-close" onclick="this.parentElement.style.display='none';" style="background: none; border: none; color: #721c24; cursor: pointer; font-size: 18px; padding: 0; line-height: 1;">×</button>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>
            <section class="ledger-container">
                <div class="table-controls">
                    <div class="entries-select">
                        <span>Show entries:</span>
                        <select>
                            <option>10</option>
                            <option>25</option>
                            <option>50</option>
                        </select>
                    </div>
                    <div class="filter-box">
                        <span class="material-symbols-outlined">filter_list</span>
                        <input id="studentFilterInput" placeholder="Filter by ID, Name or Course..." type="text">
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID Number</th>
                                <th>Name</th>
                                <th>Year&nbsp;Level</th>
                                <th>Course</th>
                                <th style="text-align: center;">Remaining Session</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="studentTableBody">
                            <?php
                            $avatarStyles = [
                                'background-color: var(--primary-fixed); color: var(--on-primary-fixed);',
                                'background-color: var(--tertiary-fixed); color: var(--on-tertiary-fixed);',
                                'background-color: rgb(255, 218, 214); color: rgb(65, 0, 3);',
                                'background-color: var(--surface-container-highest); color: var(--on-surface);',
                                'background-color: rgb(255, 219, 204); color: rgb(53, 16, 0);'
                            ];
                            ?>
                            <?php if (empty($studentRows)): ?>
                                <tr>
                                    <td colspan="6" class="no-results">No student records found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($studentRows as $idx => $student): ?>
                                    <?php
                                    $displayName = studentDisplayName($student);
                                    $remaining = (int) $student['remaining_sessions'];
                                    $searchBlob = strtolower(trim(
                                        ($student['id_number'] ?? '') . ' ' .
                                            $displayName . ' ' .
                                            ($student['course'] ?? '')
                                    ));
                                    ?>
                                    <tr data-student-row="1"
                                        data-search="<?= esc($searchBlob) ?>"
                                        data-student-id="<?= (int) $student['id'] ?>"
                                        data-id-number="<?= esc($student['id_number']) ?>"
                                        data-first-name="<?= esc($student['first_name']) ?>"
                                        data-last-name="<?= esc($student['last_name']) ?>"
                                        data-middle-name="<?= esc($student['middle_name']) ?>"
                                        data-course="<?= esc($student['course']) ?>"
                                        data-course-level="<?= (int) $student['course_level'] ?>"
                                        data-display-name="<?= esc($displayName) ?>">
                                        <td class="id-cell"><?= esc($student['id_number']) ?></td>
                                        <td>
                                            <div class="name-cell">
                                                <div class="avatar" style="<?= esc($avatarStyles[$idx % count($avatarStyles)]) ?>">
                                                    <?= esc(studentInitials($student['first_name'], $student['last_name'])) ?>
                                                </div>
                                                <span><?= esc($displayName) ?></span>
                                            </div>
                                        </td>
                                        <td><span class="tag"><?= esc(yearLevelLabel($student['course_level'])) ?></span></td>
                                        <td><?= esc($student['course']) ?></td>
                                        <td class="session-count<?= $remaining <= 3 ? ' session-low' : '' ?>" style="text-align: center; vertical-align: middle;">
                                            <?= str_pad((string) $remaining, 2, '0', STR_PAD_LEFT) ?>
                                        </td>
                                        <td>
                                            <div class="actions-cell">
                                                <button type="button" class="icon-btn edit editStudent-btn" onclick="openEditStudentModal(this)"><span
                                                        class="material-symbols-outlined">edit_square</span></button>
                                                <button type="button" class="icon-btn delete deleteStudent-btn" onclick="confirmDeleteStudent(this)"><span
                                                        class="material-symbols-outlined">delete_sweep</span></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr id="studentNoMatchRow" style="display:none;">
                                    <td colspan="6" class="no-results">No matching student records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="pagination-footer">
                    <p id="studentCountText">Showing <b><?= !empty($studentRows) ? '1 to ' . count($studentRows) : '0 to 0' ?></b>
                        of <?= count($studentRows) ?> students</p>
                    <div class="pagination-controls">
                        <button class="page-btn"><span class="material-symbols-outlined">chevron_left</span></button>
                        <button class="page-btn active">1</button>
                        <button class="page-btn">2</button>
                        <button class="page-btn">3</button>
                        <span style="padding: 0px 0.5rem;">...</span>
                        <button class="page-btn">50</button>
                        <button class="page-btn"><span class="material-symbols-outlined">chevron_right</span></button>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <form id="resetSessionForm" method="POST" action="admin_dashboard.php" style="display:none;">
        <input type="hidden" name="reset_all_sessions" value="1">
    </form>

    <form id="deleteStudentForm" method="POST" action="admin_dashboard.php" style="display:none;">
        <input type="hidden" name="delete_student" value="1">
        <input type="hidden" name="delete_student_id" id="delete_student_id" value="">
    </form>

    <!-- ─── Add Student Modal ─── -->
    <div class="modal-overlay" id="addStudentModal">
        <div class="modal-box">
            <div class="modal-header">
                <span>Add Student</span>
                <button type="button" class="modal-close" onclick="closeModal('addStudentModal')">×</button>
            </div>
            <form method="POST" action="admin_dashboard.php" id="addStudentForm">
                <input type="hidden" name="add_student" value="1">
                <div class="modal-body">
                    <div class="modal-field">
                        <label for="add_id_number">ID Number</label>
                        <input type="number" id="add_id_number" name="id_number" min="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required title="Please enter a valid numeric ID number">
                    </div>
                    <div class="modal-field">
                        <label for="add_last_name">Last Name</label>
                        <input type="text" id="add_last_name" name="last_name" required>
                    </div>
                    <div class="modal-field">
                        <label for="add_first_name">First Name</label>
                        <input type="text" id="add_first_name" name="first_name" required>
                    </div>
                    <div class="modal-field">
                        <label for="add_middle_name">Middle Name</label>
                        <input type="text" id="add_middle_name" name="middle_name" required>
                    </div>
                    <div class="modal-field">
                        <label for="add_course">Course</label>
                        <select id="add_course" name="course" required>
                            <option value="" disabled selected>Select Course</option>
                            <option value="BSIT">BS Information Technology</option>
                            <option value="BSCS">BS Computer Science</option>
                            <option value="BSIS">BS Information Systems</option>
                        </select>
                    </div>
                    <div class="modal-field">
                        <label for="add_course_level">Year Level</label>
                        <select id="add_course_level" name="course_level" required>
                            <option value="" disabled selected>Select Year</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                    <div class="modal-field">
                        <label for="add_email">Email</label>
                        <input type="email" id="add_email" name="email" required>
                    </div>
                    <div class="modal-field">
                        <label for="add_address">Address</label>
                        <input type="text" id="add_address" name="address" required>
                    </div>
                    <div class="modal-field">
                        <label for="add_password">Password</label>
                        <input type="password" id="add_password" name="password" required>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn btn-cancel" onclick="closeModal('addStudentModal')">Close</button>
                    <button type="submit" class="modal-btn btn-confirm">Add Student</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ─── Edit Student Modal ─── -->
    <div class="modal-overlay" id="editStudentModal">
        <div class="modal-box">
            <div class="modal-header">
                <span>Edit Student</span>
                <button type="button" class="modal-close" onclick="closeModal('editStudentModal')">×</button>
            </div>
            <form method="POST" action="admin_dashboard.php" id="editStudentForm">
                <input type="hidden" name="update_student" value="1">
                <input type="hidden" name="student_id" id="edit_student_id" value="">
                <div class="modal-body">
                    <div class="modal-field">
                        <label for="edit_id_number">ID Number</label>
                        <input type="number" id="edit_id_number" name="id_number" min="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required title="Please enter a valid numeric ID number">
                    </div>
                    <div class="modal-field">
                        <label for="edit_last_name">Last Name</label>
                        <input type="text" id="edit_last_name" name="last_name" required>
                    </div>
                    <div class="modal-field">
                        <label for="edit_first_name">First Name</label>
                        <input type="text" id="edit_first_name" name="first_name" required>
                    </div>
                    <div class="modal-field">
                        <label for="edit_middle_name">Middle Name</label>
                        <input type="text" id="edit_middle_name" name="middle_name" required>
                    </div>
                    <div class="modal-field">
                        <label for="edit_course">Course</label>
                        <select id="edit_course" name="course" required>
                            <option value="" disabled>Select Course</option>
                            <option value="BSIT">BS Information Technology</option>
                            <option value="BSCS">BS Computer Science</option>
                            <option value="BSIS">BS Information Systems</option>
                        </select>
                    </div>
                    <div class="modal-field">
                        <label for="edit_course_level">Year Level</label>
                        <select id="edit_course_level" name="course_level" required>
                            <option value="" disabled>Select Year</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                    <div class="modal-field">
                        <label for="edit_email">Email</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                    <div class="modal-field">
                        <label for="edit_address">Address</label>
                        <input type="text" id="edit_address" name="address" required>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn btn-cancel" onclick="closeModal('editStudentModal')">Close</button>
                    <button type="submit" class="modal-btn btn-confirm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ─── Search Student Modal ─── -->
    <div class="modal-overlay" id="searchModal">
        <div class="modal-box">
            <div class="modal-header">
                <span>Search Student</span>
                <button class="modal-close" onclick="closeModal('searchModal')">×</button>
            </div>
            <div class="modal-body">
                <div class="search-input-row">
                    <input type="text" id="searchInput" placeholder="Search by name or ID number...">
                    <button type="button" onclick="doSearch()">Search</button>
                </div>
                <div class="search-results" id="searchResults">
                    <p class="no-results">Enter a query to search for students.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── Sit-in Form Modal ─── -->
    <div class="modal-overlay" id="sitinModal">
        <div class="modal-box">
            <div class="modal-header">
                <span>Sit In Form</span>
                <button class="modal-close" onclick="closeModal('sitinModal')">×</button>
            </div>
            <form method="POST" action="admin_dashboard.php">
                <div class="modal-body">
                    <div class="modal-field">
                        <label for="sitin_id_number">ID Number</label>
                        <input type="text" id="sitin_id_number" name="sitin_id_number" placeholder="Default" readonly
                            required>
                    </div>
                    <div class="modal-field">
                        <label for="sitin_student_name">Student Name</label>
                        <input type="text" id="sitin_student_name" name="sitin_student_name" placeholder="Default"
                            readonly required>
                    </div>
                    <div class="modal-field">
                        <label for="sitin_purpose">Purpose</label>
                        <select id="sitin_purpose" name="sitin_purpose" required>
                            <option value="" selected disabled>Select purpose</option>
                            <option value="Python">Python</option>
                            <option value="C#">C#</option>
                            <option value="PHP">PHP</option>
                            <option value="Java">Java</option>
                            <option value="C++">C++</option>
                        </select>
                    </div>
                    <div class="modal-field">
                        <label for="sitin_lab">Lab</label>
                        <select id="sitin_lab" name="sitin_lab" required>
                            <option value="" selected disabled>Select lab</option>
                            <option value="524">524</option>
                            <option value="525">526</option>
                            <option value="526">528</option>
                            <option value="527">530</option>
                            <option value="527">542</option>
                            <option value="527">544</option>
                        </select>
                    </div>
                    <div class="modal-field">
                        <label for="sitin_sessions">Remaining Sessions</label>
                        <input type="text" id="sitin_sessions" readonly value="30">
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn btn-cancel" onclick="closeModal('sitinModal')">Close</button>
                    <button type="submit" class="modal-btn btn-confirm">Sit In</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ─── View switching ───
        function switchView(view) {
            if (view === 'students') {
                document.getElementById('dashboard-view').style.display = 'none';
                document.getElementById('students-view').style.display = 'block';
                document.getElementById('nav-home').classList.remove('nav-active');
                document.getElementById('nav-students').classList.add('nav-active');
            } else {
                document.getElementById('dashboard-view').style.display = 'grid'; // admin-content uses grid
                document.getElementById('students-view').style.display = 'none';
                document.getElementById('nav-home').classList.add('nav-active');
                document.getElementById('nav-students').classList.remove('nav-active');
            }
        }

        // ─── Modal management ───
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function openSitInForm() {
            document.getElementById('sitin_id_number').value = '';
            document.getElementById('sitin_student_name').value = '';
            document.getElementById('sitin_purpose').selectedIndex = 0;
            document.getElementById('sitin_lab').selectedIndex = 0;
            document.getElementById('sitin_sessions').value = 30;
            openModal('sitinModal');
        }

        function openAddStudentModal() {
            const form = document.getElementById('addStudentForm');
            if (form) {
                form.reset();
            }
            openModal('addStudentModal');
        }

        function openEditStudentModal(triggerBtn) {
            const row = triggerBtn.closest('tr');
            if (!row) return;

            const studentId = row.dataset.studentId || '';
            if (!studentId) return;

            // Populate non-sensitive fields immediately
            document.getElementById('edit_student_id').value = studentId;
            document.getElementById('edit_id_number').value = row.dataset.idNumber || '';
            document.getElementById('edit_last_name').value = row.dataset.lastName || '';
            document.getElementById('edit_first_name').value = row.dataset.firstName || '';
            document.getElementById('edit_middle_name').value = row.dataset.middleName || '';
            document.getElementById('edit_course').value = row.dataset.course || '';
            document.getElementById('edit_course_level').value = row.dataset.courseLevel || '';

            // Fetch sensitive fields via secure AJAX
            fetch('admin_dashboard.php?ajax_get_student_details=1&student_id=' + encodeURIComponent(studentId))
                .then(response => {
                    if (!response.ok) throw new Error('Failed to fetch student details');
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        console.error('Student details error:', data.error);
                        return;
                    }
                    document.getElementById('edit_email').value = data.email || '';
                    document.getElementById('edit_address').value = data.address || '';
                })
                .catch(error => console.error('Error fetching student details:', error));

            openModal('editStudentModal');
        }

        function confirmDeleteStudent(triggerBtn) {
            const row = triggerBtn.closest('tr');
            if (!row) return;

            const studentId = row.dataset.studentId || '';
            const studentName = row.dataset.displayName || 'this student';
            if (!studentId) return;

            if (confirm('Delete ' + studentName + '? This action cannot be undone.')) {
                document.getElementById('delete_student_id').value = studentId;
                document.getElementById('deleteStudentForm').submit();
            }
        }

        function confirmResetSessions() {
            if (confirm('Reset all session counts for every student? This will clear all sit-in records.')) {
                document.getElementById('resetSessionForm').submit();
            }
        }

        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function (e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        // ─── Search ───
        let latestSearchData = [];

        function selectStudentForSitIn(index) {
            const student = latestSearchData[index];
            if (!student) return;

            document.getElementById('sitin_id_number').value = student.id_number || '';
            document.getElementById('sitin_student_name').value = ((student.first_name || '') + ' ' + (student.last_name || '')).trim();
            document.getElementById('sitin_sessions').value = student.remaining_sessions ?? 30;
            document.getElementById('sitin_purpose').selectedIndex = 0;
            document.getElementById('sitin_lab').selectedIndex = 0;

            closeModal('searchModal');
            openModal('sitinModal');
        }

        function doSearch() {
            const q = document.getElementById('searchInput').value.trim();
            const container = document.getElementById('searchResults');
            if (!q) {
                container.innerHTML = '<p class="no-results">Enter a query to search for students.</p>';
                return;
            }
            container.innerHTML = '<p class="no-results">Searching...</p>';

            fetch('admin_dashboard.php?ajax_search=1&q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    latestSearchData = data;
                    if (!data.length) {
                        container.innerHTML = '<p class="no-results">No students found.</p>';
                        return;
                    }
                    let html = '<table><tr><th>ID</th><th>Name</th><th>Course</th><th>Year</th><th>Sessions</th><th>Action</th></tr>';
                    data.forEach((s, idx) => {
                        html += `<tr>
                            <td>${s.id_number}</td>
                            <td>${s.first_name} ${s.last_name}</td>
                            <td>${s.course}</td>
                            <td>${s.course_level}</td>
                            <td>${s.remaining_sessions}</td>
                            <td><button type="button" class="search-action-btn" onclick="selectStudentForSitIn(${idx})">Use</button></td>
                        </tr>`;
                    });
                    html += '</table>';
                    container.innerHTML = html;
                })
                .catch(() => {
                    container.innerHTML = '<p class="no-results">Error searching. Please try again.</p>';
                });
        }

        // ─── Student table filter (ID, Name, Course) ───
        const studentFilterInput = document.getElementById('studentFilterInput');
        const studentTableBody = document.getElementById('studentTableBody');
        const studentCountText = document.getElementById('studentCountText');
        const studentNoMatchRow = document.getElementById('studentNoMatchRow');
        const studentDataRows = studentTableBody
            ? Array.from(studentTableBody.querySelectorAll('tr[data-student-row="1"]'))
            : [];

        function updateStudentCountText(visibleCount) {
            if (!studentCountText) return;

            const total = studentDataRows.length;
            const rangeText = visibleCount > 0 ? ('1 to ' + visibleCount) : '0 to 0';
            studentCountText.innerHTML = 'Showing <b>' + rangeText + '</b> of ' + total + ' students';
        }

        function applyStudentFilter() {
            if (!studentFilterInput || !studentTableBody) return;

            const keyword = studentFilterInput.value.trim().toLowerCase();
            let visibleCount = 0;

            studentDataRows.forEach((row) => {
                const searchable = row.dataset.search || '';
                const isMatch = keyword === '' || searchable.includes(keyword);
                row.style.display = isMatch ? '' : 'none';
                if (isMatch) {
                    visibleCount++;
                }
            });

            if (studentNoMatchRow) {
                studentNoMatchRow.style.display = visibleCount === 0 ? '' : 'none';
            }

            updateStudentCountText(visibleCount);
        }

        if (studentFilterInput) {
            studentFilterInput.addEventListener('input', applyStudentFilter);
            applyStudentFilter();
        }

        // Keep Student Information view active after student actions.
        if (new URLSearchParams(window.location.search).get('view') === 'students') {
            switchView('students');
        }

        // Search on Enter key
        document.getElementById('searchInput').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') doSearch();
        });

        // ─── Auto-fill sit-in form when ID is entered ───
        let sitinTimeout;
        document.getElementById('sitin_id_number').addEventListener('input', function () {
            clearTimeout(sitinTimeout);
            const idNum = this.value.trim();
            if (idNum.length < 1) {
                document.getElementById('sitin_student_name').value = '';
                document.getElementById('sitin_sessions').value = 30;
                return;
            }
            sitinTimeout = setTimeout(() => {
                fetch('admin_dashboard.php?ajax_search=1&q=' + encodeURIComponent(idNum))
                    .then(r => r.json())
                    .then(data => {
                        const match = data.find(s => s.id_number === idNum);
                        if (match) {
                            document.getElementById('sitin_student_name').value = match.first_name + ' ' + match.last_name;
                            document.getElementById('sitin_sessions').value = match.remaining_sessions ?? 30;
                        } else {
                            document.getElementById('sitin_student_name').value = '';
                            document.getElementById('sitin_sessions').value = 30;
                        }
                    });
            }, 400);
        });
    </script>

</body>

</html>