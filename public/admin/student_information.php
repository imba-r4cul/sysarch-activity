<?php
session_start();
require_once '../../config/database.php';
require_once '../includes/helpers.php';

// Guard: admin only
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminId = (int) $_SESSION['admin_id'];

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

function redirectStudentInformation()
{
    header('Location: student_information.php');
    exit;
}

// ── Student management actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_all_sessions'])) {
    $conn->query("DELETE FROM sit_in_records");
    redirectStudentInformation();
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

    redirectStudentInformation();
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

    redirectStudentInformation();
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

    redirectStudentInformation();
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

    $stmt = $conn->prepare("SELECT id, email, address FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Information</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/ccs.png">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/shared/navbar.css">
    <link rel="stylesheet" href="../assets/css/admin/student_information.css">
</head>

<body>

    <nav class="academic-ledger-navbar">
        <div class="nav-container">
            <div class="brand">
                <h1 class="brand-title">CCS Sit-in Monitoring System (ADMIN DASHBOARD)</h1>
            </div>
            <div class="nav-links">
                <a class="nav-link" href="admin_dashboard.php">Home</a>
                <button class="nav-link" type="button" onclick="openModal('searchModal')">Search</button>
                <a class="nav-link active" href="student_information.php">Student Information</a>
                <a class="nav-link" href="active_sessions.php">Active Sessions</a>
                <a class="nav-link" href="sit_in_history_admin.php">Sit-in History</a>
                <a class="nav-logout" href="admin_dashboard.php?logout=1">Logout</a>
            </div>
        </div>
    </nav>

    <div class="student-info-wrapper">
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
                    <div class="entries-control">
                        <label class="label-uppercase" for="entriesPerPage">Show entries</label>
                        <div class="select-wrapper">
                            <select id="entriesPerPage" aria-label="Entries per page">
                                <option value="5" selected>5</option>
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                            <span class="material-symbols-outlined">expand_more</span>
                        </div>
                    </div>
                    <div class="search-wrapper">
                        <span class="material-symbols-outlined">search</span>
                        <input type="text" id="studentFilterInput" aria-label="Filter students" placeholder="Filter by ID, Name or Course...">
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
                                                <?php $style = [['bg' => 'var(--primary-fixed)', 'fg' => 'var(--on-primary-fixed)'], ['bg' => 'var(--tertiary-fixed)', 'fg' => 'var(--on-tertiary-fixed)']][$idx % 2]; ?>
                                                <div class="avatar" style="background-color: <?= $style['bg'] ?>; color: <?= $style['fg'] ?>;">
                                                    <?= esc(studentInitials($student['first_name'], $student['last_name'])) ?>
                                                </div>
                                                <span><?= esc($displayName) ?></span>
                                            </div>
                                        </td>
                                        <td><?= esc(yearLevelLabel($student['course_level'])) ?></td>
                                        <td><?= esc($student['course']) ?></td>
                                        <td class="session-count<?= $remaining <= 3 ? ' session-low' : '' ?>" style="text-align: center; vertical-align: middle;">
                                            <?= str_pad((string) $remaining, 2, '0', STR_PAD_LEFT) ?>
                                        </td>
                                        <td>
                                            <div class="actions-cell">
                                                <button type="button" class="icon-btn edit editStudent-btn" onclick="openEditStudentModal(this)"><span class="material-symbols-outlined">edit_square</span></button>
                                                <button type="button" class="icon-btn delete deleteStudent-btn" onclick="confirmDeleteStudent(this)"><span class="material-symbols-outlined">delete_sweep</span></button>
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
                        <button class="page-btn" type="button" id="studentFirstBtn"><span class="material-symbols-outlined">keyboard_double_arrow_left</span></button>
                        <button class="page-btn" type="button" id="studentPrevBtn"><span class="material-symbols-outlined">chevron_left</span></button>
                        <button class="page-btn active" type="button" id="studentPageBtn">1</button>
                        <button class="page-btn" type="button" id="studentNextBtn"><span class="material-symbols-outlined">chevron_right</span></button>
                        <button class="page-btn" type="button" id="studentLastBtn"><span class="material-symbols-outlined">keyboard_double_arrow_right</span></button>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <form id="resetSessionForm" method="POST" action="student_information.php" style="display:none;">
        <input type="hidden" name="reset_all_sessions" value="1">
    </form>

    <form id="deleteStudentForm" method="POST" action="student_information.php" style="display:none;">
        <input type="hidden" name="delete_student" value="1">
        <input type="hidden" name="delete_student_id" id="delete_student_id" value="">
    </form>

    <div class="modal-overlay" id="confirmActionModal">
        <div class="modal-box confirmation-box" style="max-width: 400px; text-align: center;">
            <div class="modal-header" style="flex-direction: column; justify-content: center; align-items: center; border-bottom: none; padding: 8px;">
                <span class="material-symbols-outlined" style="font-size: 50px; color: #d32f2f;">warning</span>
            </div>
            <div class="modal-body" style="padding: 5px 15px 15px;">
                <h3 id="confirmActionTitle" style="margin: 0 0 5px; color: #333; font-size: 18px;">Confirm Action</h3>
                <p id="confirmActionMessage" style="margin: 0; color: #666; font-size: 14px;">Are you sure?</p>
            </div>
            <div class="modal-actions" style="justify-content: center; background: #f9f9f9; padding: 12px;">
                <button type="button" class="modal-btn btn-cancel" onclick="closeModal('confirmActionModal')">Cancel</button>
                <button type="button" class="modal-btn btn-confirm" id="confirmActionBtn" style="background-color: #d32f2f; color: white;">Confirm</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="addStudentModal">
        <div class="modal-box">
            <div class="modal-header">
                <span>Add Student</span>
                <button type="button" class="modal-close" onclick="closeModal('addStudentModal')">×</button>
            </div>
            <form method="POST" action="student_information.php" id="addStudentForm">
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

    <div class="modal-overlay" id="editStudentModal">
        <div class="modal-box">
            <div class="modal-header">
                <span>Edit Student</span>
                <button type="button" class="modal-close" onclick="closeModal('editStudentModal')">×</button>
            </div>
            <form method="POST" action="student_information.php" id="editStudentForm">
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

    <?php include 'search_student_modal.php'; ?>
    <?php include 'sitin_form_modal.php'; ?>

    <script>
        // ─── Modal management ───
        function openModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('active');
        }
        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('active');
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
            fetch('student_information.php?ajax_get_student_details=1&student_id=' + encodeURIComponent(studentId))
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

            document.getElementById('confirmActionTitle').textContent = 'Delete Student';
            document.getElementById('confirmActionMessage').textContent = 'Delete ' + studentName + '? This action cannot be undone.';
            document.getElementById('confirmActionBtn').onclick = function() {
                document.getElementById('delete_student_id').value = studentId;
                document.getElementById('deleteStudentForm').submit();
            };
            openModal('confirmActionModal');
        }

        function confirmResetSessions() {
            document.getElementById('confirmActionTitle').textContent = 'Reset Sessions';
            document.getElementById('confirmActionMessage').textContent = 'Reset all session counts for every student? This will clear all sit-in records.';
            document.getElementById('confirmActionBtn').onclick = function() {
                document.getElementById('resetSessionForm').submit();
            };
            openModal('confirmActionModal');
        }

        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function (e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        // ─── Student table filter & pagination ───
        const studentFilterInput = document.getElementById('studentFilterInput');
        const studentTableBody = document.getElementById('studentTableBody');
        const studentCountText = document.getElementById('studentCountText');
        const studentNoMatchRow = document.getElementById('studentNoMatchRow');
        const entriesPerPageSelect = document.getElementById('entriesPerPage');
        const studentFirstBtn = document.getElementById('studentFirstBtn');
        const studentPrevBtn = document.getElementById('studentPrevBtn');
        const studentPageBtn = document.getElementById('studentPageBtn');
        const studentNextBtn = document.getElementById('studentNextBtn');
        const studentLastBtn = document.getElementById('studentLastBtn');
        const studentDataRows = studentTableBody
            ? Array.from(studentTableBody.querySelectorAll('tr[data-student-row="1"]'))
            : [];

        let studentFilteredRows = studentDataRows.slice();
        let studentCurrentPage = 1;

        function getStudentPageSize() {
            if (!entriesPerPageSelect) return 10;
            const size = parseInt(entriesPerPageSelect.value, 10);
            return isNaN(size) || size <= 0 ? 10 : size;
        }

        function getStudentPageCount() {
            const total = studentFilteredRows.length;
            const size = getStudentPageSize();
            return Math.max(1, Math.ceil(total / size));
        }

        function renderStudentRows() {
            if (!studentTableBody) return;

            // Hide all first
            studentDataRows.forEach(row => row.style.display = 'none');

            if (studentFilteredRows.length === 0) {
                if (studentNoMatchRow) studentNoMatchRow.style.display = '';
                if (studentCountText) studentCountText.innerHTML = 'Showing <b>0 to 0</b> of 0 students';
                if (studentPageBtn) studentPageBtn.textContent = '0';
                if (studentPrevBtn) studentPrevBtn.disabled = true;
                if (studentNextBtn) studentNextBtn.disabled = true;
                return;
            }

            if (studentNoMatchRow) studentNoMatchRow.style.display = 'none';

            const size = getStudentPageSize();
            const start = (studentCurrentPage - 1) * size;
            const end = Math.min(start + size, studentFilteredRows.length);

            const rowsToShow = studentFilteredRows.slice(start, end);
            rowsToShow.forEach(row => row.style.display = '');

            if (studentCountText) {
                studentCountText.innerHTML = 'Showing <b>' + (start + 1) + ' to ' + end + '</b> of ' + studentFilteredRows.length + ' students';
            }

            const totalPages = getStudentPageCount();
            if (studentPageBtn) studentPageBtn.textContent = studentCurrentPage;
            if (studentFirstBtn) studentFirstBtn.disabled = studentCurrentPage <= 1;
            if (studentPrevBtn) studentPrevBtn.disabled = studentCurrentPage <= 1;
            if (studentNextBtn) studentNextBtn.disabled = studentCurrentPage >= totalPages;
            if (studentLastBtn) studentLastBtn.disabled = studentCurrentPage >= totalPages;
        }

        function applyStudentFilter() {
            if (!studentFilterInput || !studentTableBody) return;

            const keyword = studentFilterInput.value.trim().toLowerCase();

            studentFilteredRows = studentDataRows.filter(row => {
                const searchable = row.dataset.search || '';
                return keyword === '' || searchable.includes(keyword);
            });

            studentCurrentPage = 1;
            renderStudentRows();
        }

        if (studentFilterInput) {
            studentFilterInput.addEventListener('input', applyStudentFilter);
        }

        if (entriesPerPageSelect) {
            entriesPerPageSelect.addEventListener('change', () => {
                studentCurrentPage = 1;
                renderStudentRows();
            });
        }

        if (studentFirstBtn) {
            studentFirstBtn.addEventListener('click', () => {
                studentCurrentPage = 1;
                renderStudentRows();
            });
        }

        if (studentPrevBtn) {
            studentPrevBtn.addEventListener('click', () => {
                if (studentCurrentPage > 1) {
                    studentCurrentPage--;
                    renderStudentRows();
                }
            });
        }

        if (studentNextBtn) {
            studentNextBtn.addEventListener('click', () => {
                const totalPages = getStudentPageCount();
                if (studentCurrentPage < totalPages) {
                    studentCurrentPage++;
                    renderStudentRows();
                }
            });
        }

        if (studentLastBtn) {
            studentLastBtn.addEventListener('click', () => {
                studentCurrentPage = getStudentPageCount();
                renderStudentRows();
            });
        }

        // Initial render
        renderStudentRows();

    </script>

</body>

</html>
