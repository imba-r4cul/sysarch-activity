<?php
session_start();
require_once '../../config/database.php';
require_once '../includes/helpers.php';

// Guard: admin only
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ../auth/index.php');
    exit;
}

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminId = (int) $_SESSION['admin_id'];

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
        if ($stmt) {
            $stmt->bind_param('is', $adminId, $content);
            $stmt->execute();
            $stmt->close();
        }
    }
    header('Location: admin_dashboard.php');
    exit;
}

// ── Handle delete announcement ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement_id'])) {
    $delId = (int) $_POST['delete_announcement_id'];
    if ($delId > 0) {
        $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $delId);
            $stmt->execute();
            $stmt->close();
        }
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
    if ($stmt) {
        $stmt->bind_param('s', $sitinIdNumber);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($user = $res->fetch_assoc()) {
            $uid = (int) $user['id'];
            $firstName = (string) $user['first_name'];
            $lastName = (string) $user['last_name'];
            $ins = $conn->prepare("INSERT INTO sit_in_records (user_id, id_number, first_name, last_name, purpose, lab) VALUES (?, ?, ?, ?, ?, ?)");
            if ($ins) {
                $ins->bind_param('isssss', $uid, $sitinIdNumber, $firstName, $lastName, $sitinPurpose, $sitinLab);
                $ins->execute();
                $ins->close();
            }
        }
        $stmt->close();
    }

    header('Location: admin_dashboard.php');
    exit;
}

// ── Fetch announcements ──
$announcements = [];
$r = $conn->query("SELECT a.id, a.content, a.created_at, au.display_name FROM announcements a JOIN admin_users au ON a.admin_id = au.id ORDER BY a.created_at DESC LIMIT 20");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $announcements[] = $row;
    }
}

$purposeTotal = array_sum($purposes);
$purposePalette = ['#002a5c', '#0c458b', '#84aefa', '#d7e3ff', '#004085', '#722b00', '#b6171e', '#737782'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/ccs.png">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/shared/navbar.css">
    <link rel="stylesheet" href="../assets/css/admin/admin_dashboard.css">
</head>

<body>

    <nav class="academic-ledger-navbar">
        <div class="nav-container">
            <div class="brand">
                <h1 class="brand-title">CCS Sit-in Monitoring System (ADMIN DASHBOARD)</h1>
            </div>
            <div class="nav-links">
                <a class="nav-link active" href="admin_dashboard.php">Home</a>
                <button class="nav-link" type="button" onclick="openModal('searchModal')">Search</button>
                <a class="nav-link" href="student_information.php">Student Information</a>
                <a class="nav-link" href="active_sessions.php">Active Sessions</a>
                <a class="nav-link" href="sit_in_history_admin.php">Sit-in History</a>
                <a class="nav-logout" href="admin_dashboard.php?logout=1">Logout</a>
            </div>
        </div>
    </nav>

    <main class="ledger-main">
        <div class="container">
            <header class="page-header">
                <div>
                    <h1 class="dashboard-title">Admin Dashboard</h1>
                </div>
                <div class="header-actions">
                    <button type="button" class="btn-tertiary" onclick="openModal('searchModal')">
                        <span class="material-symbols-outlined" aria-hidden="true">person_search</span>
                        New Sit-in
                    </button>
                    <a class="btn-primary" href="student_information.php">
                        <span class="material-symbols-outlined" aria-hidden="true">school</span>
                        Students
                    </a>
                </div>
            </header>

            <div class="grid-layout">
                <section class="col-span-7">
                    <div class="card-outer">
                        <div class="card-inner">
                            <div class="card-header">
                                <h3 class="card-title">Laboratory Statistics</h3>
                                <span class="material-symbols-outlined card-icon" aria-hidden="true">insert_chart</span>
                            </div>

                            <div class="scrollable-content">
                                <div class="stats-grid" aria-label="Key statistics">
                                    <div class="stat-item">
                                        <div class="stat-label">Students Registered</div>
                                        <div class="stat-value"><?= number_format($totalStudents) ?></div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-label">Currently Sit-in</div>
                                        <div class="stat-value"><?= number_format($currentSitIn) ?></div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-label">Total Sit-in</div>
                                        <div class="stat-value"><?= number_format($totalSitIn) ?></div>
                                    </div>
                                </div>

                                <div class="chart-section">
                                    <div class="chart-label">Sit-in by purpose</div>

                                    <?php if (empty($purposes)): ?>
                                        <p class="empty-state">No sit-in data yet.</p>
                                    <?php else: ?>
                                        <div class="chart-container">
                                            <div class="pie-wrapper">
                                                <canvas id="purposePieChart" aria-label="Sit-in by purpose" role="img"></canvas>
                                                <div class="pie-center" aria-hidden="true">Live</div>
                                            </div>

                                            <ul class="purpose-legend" aria-label="Purpose breakdown">
                                                <?php $i = 0; ?>
                                                <?php foreach ($purposes as $purpose => $count): ?>
                                                    <?php
                                                    $pct = $purposeTotal > 0 ? (int) round(($count / $purposeTotal) * 100) : 0;
                                                    $color = $purposePalette[$i % count($purposePalette)];
                                                    $i++;
                                                    ?>
                                                    <li class="legend-item">
                                                        <span class="legend-dot" style="background-color: <?= esc($color) ?>"></span>
                                                        <span class="legend-label"><?= esc($purpose) ?></span>
                                                        <span class="legend-value"><?= $pct ?>%</span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="col-span-5">
                    <div class="card-outer">
                        <div class="card-inner">
                            <div class="card-header">
                                <h3 class="card-title">Announcement</h3>
                                <span class="material-symbols-outlined card-icon" aria-hidden="true">campaign</span>
                            </div>

                            <div class="scrollable-content">
                                <div class="form-section">
                                    <label class="form-label" for="announcement_content">New announcement</label>
                                    <form class="announce-form" method="POST" action="admin_dashboard.php">
                                        <textarea id="announcement_content" class="announcement-textarea" name="announcement_content" placeholder="Write a new announcement..." required></textarea>
                                        <button type="submit" class="submit-btn" id="submitBtn">
                                            Submit
                                            <span class="material-symbols-outlined" aria-hidden="true">send</span>
                                        </button>
                                    </form>
                                </div>

                                <div class="announcement-list" aria-label="Posted announcements">
                                    <?php if (empty($announcements)): ?>
                                        <p class="empty-state">No announcements yet.</p>
                                    <?php else: ?>
                                        <?php foreach ($announcements as $ann): 
                                            // Optional: Extract a short title like the first sentence if desired.
                                            // For now we'll display the start of the content as title if we need it,
                                            // or just show Admin in meta and full excerpt in body.
                                            // code.html has a title, meta, and excerpt.
                                            // Let's create a title from the first N words.
                                            $words = explode(' ', trim($ann['content']));
                                            $title = implode(' ', array_slice($words, 0, 5)) . (count($words) > 5 ? '...' : '');
                                        ?>
                                            <div class="announcement-card" id="ann-<?= (int)$ann['id'] ?>">
                                                <button type="button" class="announcement-delete-btn" onclick="confirmDeleteAnnouncement(<?= (int)$ann['id'] ?>)" title="Delete Announcement">
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M3 6h18"></path>
                                                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                                        <line x1="10" y1="11" x2="10" y2="17"></line>
                                                        <line x1="14" y1="11" x2="14" y2="17"></line>
                                                    </svg>
                                                </button>
                                                <h4><?= esc($title) ?></h4>
                                                <div class="announcement-meta">
                                                    <span style="font-weight: 600">Admin: <?= esc($ann['display_name']) ?></span>
                                                    <span class="meta-dot" aria-hidden="true"></span>
                                                    <time datetime="<?= esc($ann['created_at']) ?>"><?= date('M d, Y', strtotime($ann['created_at'])) ?></time>
                                                </div>
                                                <p class="announcement-excerpt"><?= esc($ann['content']) ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <?php include 'search_student_modal.php'; ?>
    <?php include 'sitin_form_modal.php'; ?>

    <form id="deleteAnnouncementForm" method="POST" action="admin_dashboard.php" style="display:none;">
        <input type="hidden" name="delete_announcement_id" id="delete_announcement_id" value="">
    </form>

    <div class="modal-overlay" id="confirmDeleteModal">
        <div class="modal-box confirmation-box" style="max-width: 400px; text-align: center;">
            <div class="modal-header" style="flex-direction: column; justify-content: center; align-items: center; border-bottom: none; padding: 8px;">
                <span class="material-symbols-outlined" style="font-size: 50px; color: #d32f2f;">warning</span>
            </div>
            <div class="modal-body" style="padding: 5px 15px 15px;">
                <h3 style="margin: 0 0 5px; color: #333; font-size: 18px;">Delete Announcement</h3>
                <p style="margin: 0; color: #666; font-size: 14px;">Are you sure you want to delete this announcement? This action cannot be undone.</p>
            </div>
            <div class="modal-actions" style="justify-content: center; background: #f9f9f9; padding: 12px;">
                <button type="button" class="modal-btn btn-cancel" onclick="closeModal('confirmDeleteModal')">Cancel</button>
                <button type="button" class="modal-btn btn-confirm" id="confirmDeleteBtn" style="background-color: #d32f2f; color: white;">Delete</button>
            </div>
        </div>
    </div>

    <script>
        function openModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('active');
        }

        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('active');
        }

        function confirmDeleteAnnouncement(id) {
            document.getElementById('delete_announcement_id').value = id;
            document.getElementById('confirmDeleteBtn').onclick = function() {
                document.getElementById('deleteAnnouncementForm').submit();
            };
            openModal('confirmDeleteModal');
        }

        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('purposePieChart');
            if (!canvas) return;

            const purposeData = <?= json_encode($purposes) ?>;
            const labels = Object.keys(purposeData);
            const data = Object.values(purposeData);

            const palette = <?= json_encode($purposePalette) ?>;
            const bg = labels.map((_, i) => palette[i % palette.length]);

            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: bg,
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(25, 28, 29, 0.92)',
                            padding: 12,
                            titleFont: { size: 13, family: "'Inter', sans-serif", weight: '600' },
                            bodyFont: { size: 13, family: "'Inter', sans-serif" },
                            displayColors: true
                        }
                    }
                }
            });
        });
    </script>

</body>

</html>
