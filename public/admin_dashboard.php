<?php
session_start();
require_once '../config/database.php';

// Guard: admin only
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: admin_login.php');
    exit;
}

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminId = (int) $_SESSION['admin_id'];

function esc($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
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
        WHERE u.id_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?
        ORDER BY u.last_name ASC, u.first_name ASC
        LIMIT 20
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('sss', $q, $q, $q);
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
    <link rel="stylesheet" href="css/style.css">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #eff1f3;
            min-height: 100vh;
        }

        /* ─── Navbar ─── */
        .admin-nav {
            background-color: #18539a;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 5px 25px;
            min-height: 66px;
        }

        .admin-nav .brand {
            color: #fff;
            font-size: 20px;
            font-weight: 700;
        }

        .admin-nav ul {
            list-style: none;
            display: flex;
            gap: 5px;
            margin: 0;
            padding: 0;
            font-size: 18px;
            align-items: center;
        }

        .admin-nav ul li a,
        .admin-nav ul li button {
            color: #fff;
            text-decoration: none;
            font-size: 18px;
            padding: 8px 10px;
            border-radius: 6px;
            border: none;
            background: none;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
        }

        .admin-nav ul li a:hover,
        .admin-nav ul li button:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        .admin-nav .logout-link {
            background: #dc3545 !important;
            border-radius: 6px;
            font-weight: 500;
            padding: 7px 18px !important;
        }

        .admin-nav .logout-link:hover {
            background: #bb2d3b !important;
        }

        /* ─── Dashboard Content ─── */
        .admin-content {
            max-width: 1100px;
            margin: 28px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        }

        .card-header {
            background: linear-gradient(160deg, #0b4b8f 0%, #18539a 40%, #2471c9 100%);
            color: #fff;
            padding: 12px 20px;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-body {
            padding: 20px;
        }

        /* ─── Stats ─── */
        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 15px;
            color: #333;
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .stat-row strong {
            color: #0b4b8f;
            font-size: 20px;
        }

        .progress-section {
            margin-top: 18px;
        }

        .progress-section h4 {
            margin: 0 0 12px;
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .progress-item {
            margin-bottom: 12px;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #444;
            margin-bottom: 4px;
            font-weight: 500;
        }

        .progress-bar-track {
            width: 100%;
            height: 10px;
            background: #e9ecef;
            border-radius: 6px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 6px;
            transition: width 0.6s ease;
        }

        /* Color palette for bars */
        .bar-blue {
            background: linear-gradient(90deg, #2471c9, #4a90d9);
        }

        .bar-red {
            background: linear-gradient(90deg, #e74c3c, #f1948a);
        }

        .bar-green {
            background: linear-gradient(90deg, #27ae60, #6fcf97);
        }

        .bar-orange {
            background: linear-gradient(90deg, #f39c12, #f7c948);
        }

        .bar-purple {
            background: linear-gradient(90deg, #8e44ad, #bb6bd9);
        }

        .bar-teal {
            background: linear-gradient(90deg, #1abc9c, #76d7c4);
        }

        /* ─── Announcements ─── */
        .announce-form textarea {
            width: 100%;
            border: 1.5px solid #dde2ea;
            border-radius: 8px;
            padding: 10px 14px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
            min-height: 70px;
            outline: none;
            transition: border-color 0.2s;
        }

        .announce-form textarea:focus {
            border-color: #4a90d9;
        }

        .announce-form button {
            margin-top: 10px;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: #fff;
            border: none;
            padding: 9px 22px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.2s;
        }

        .announce-form button:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(39, 174, 96, 0.3);
        }

        .announce-list {
            margin-top: 18px;
            max-height: 320px;
            overflow-y: auto;
        }

        .announce-item {
            border-left: 3px solid #2471c9;
            padding: 10px 14px;
            margin-bottom: 10px;
            background: #f8f9fb;
            border-radius: 0 8px 8px 0;
        }

        .announce-item .meta {
            font-size: 12px;
            color: #888;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .announce-item .text {
            font-size: 14px;
            color: #333;
            line-height: 1.5;
        }

        /* ─── Modals ─── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #fff;
            border-radius: 14px;
            width: 94%;
            max-width: 520px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: modalSlide 0.25s ease;
        }

        @keyframes modalSlide {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: linear-gradient(160deg, #0b4b8f 0%, #18539a 40%, #2471c9 100%);
            color: #fff;
            padding: 16px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 18px;
            font-weight: 700;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: #fff;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.35);
        }

        .modal-body {
            padding: 24px;
        }

        .modal-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 14px;
        }

        .modal-field label {
            font-size: 13px;
            font-weight: 600;
            color: #444;
        }

        .modal-field input,
        .modal-field select {
            padding: 10px 14px;
            border: 1.5px solid #dde2ea;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            background: #f8f9fb;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .modal-field input:focus,
        .modal-field select:focus {
            border-color: #4a90d9;
            box-shadow: 0 0 0 3px rgba(74, 144, 217, 0.12);
            background: #fff;
        }

        .modal-field input[readonly] {
            background: #eee;
            cursor: not-allowed;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 0 24px 20px;
        }

        .modal-btn {
            padding: 9px 22px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.2s;
        }

        .btn-cancel {
            background: #e9ecef;
            color: #555;
        }

        .btn-cancel:hover {
            background: #dee2e6;
        }

        .btn-confirm {
            background: linear-gradient(160deg, #0b4b8f 0%, #18539a 40%, #2471c9 100%);
            color: #fff;
        }

        .btn-confirm:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(11, 75, 143, 0.3);
        }

        /* Search results table */
        .search-results {
            margin-top: 14px;
            max-height: 280px;
            overflow-y: auto;
        }

        .search-results table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .search-results th {
            background: #0b4b8f;
            color: #fff;
            padding: 8px 10px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
        }

        .search-results td {
            padding: 8px 10px;
            border-bottom: 1px solid #f0f0f0;
            color: #333;
        }

        .search-results tr:hover td {
            background: #f0f5ff;
        }

        .search-action-btn {
            padding: 6px 10px;
            background: #2471c9;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .search-action-btn:hover {
            background: #18539a;
        }

        .search-input-row {
            display: flex;
            gap: 10px;
        }

        .search-input-row input {
            flex: 1;
        }

        .search-input-row button {
            padding: 10px 20px;
            background: linear-gradient(160deg, #0b4b8f 0%, #18539a 40%, #2471c9 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s;
        }

        .search-input-row button:hover {
            transform: translateY(-1px);
        }

        .no-results {
            text-align: center;
            color: #888;
            padding: 20px;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .admin-content {
                grid-template-columns: 1fr;
            }

            .admin-nav {
                flex-direction: column;
                height: auto;
                padding: 12px 20px;
                gap: 10px;
            }

            .admin-nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>

<body>

    <!-- ─── Navbar ─── -->
    <nav class="admin-nav">
        <span class="brand">CCS Sit-in Monitoring System (ADMIN DASHBOARD)</span>
        <ul>
            <li><a href="admin_dashboard.php" class="nav-active">Home</a></li>
            <li><button type="button" onclick="openModal('searchModal')">Search</button></li>
            <li><button type="button" onclick="openSitInForm()">Sit-in Form</button></li>
            <li><a href="admin_dashboard.php?logout=1" class="logout-link">Log out</a></li>
        </ul>
    </nav>

    <!-- ─── Dashboard Content ─── -->
    <div class="admin-content">
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
                        <input type="text" id="sitin_id_number" name="sitin_id_number" placeholder="Default"
                            readonly required>
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
                            <option value="525">525</option>
                            <option value="526">526</option>
                            <option value="527">527</option>
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