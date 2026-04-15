<?php
session_start();
require_once '../../config/database.php';

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

$stmt = $conn->prepare(
    'SELECT id_number, first_name, last_name, course, course_level, email, address, profile_image
     FROM users
     WHERE id = ?
     LIMIT 1'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_unset();
    session_destroy();
    header('Location: ../auth/index.php');
    exit;
}

$showLoginSuccess = isset($_SESSION['login_success_name']);
$successName = $showLoginSuccess
    ? $_SESSION['login_success_name']
    : trim($user['first_name'] . ' ' . $user['last_name']);
unset($_SESSION['login_success_name']);

$defaultProfileImage = '../assets/images/edit-profile.png';
$avatarPath = $defaultProfileImage;

if (!empty($user['profile_image'])) {
    $safeFileName = basename($user['profile_image']);
    $diskPath = __DIR__ . '/../assets/uploads/' . $safeFileName;
    if (is_file($diskPath)) {
        $avatarPath = '../assets/uploads/' . rawurlencode($safeFileName);
    }
}

// ── Remaining Sessions Query ──
$remainingSessions = 30; // default
$sessionStmt = $conn->prepare(
    'SELECT COUNT(*) AS total_sessions FROM sit_in_records WHERE user_id = ?'
);
$sessionStmt->bind_param('i', $userId);
$sessionStmt->execute();
$sessionResult = $sessionStmt->get_result();
if ($sessionRow = $sessionResult->fetch_assoc()) {
    $remainingSessions = max(0, 30 - (int) $sessionRow['total_sessions']);
}
$sessionStmt->close();

$notificationFeatureEnabled = false;
$notificationTableCheck = $conn->query("SHOW TABLES LIKE 'notification_reads'");
if ($notificationTableCheck) {
    $notificationFeatureEnabled = $notificationTableCheck->num_rows > 0;
    $notificationTableCheck->close();
}

function fetchNotificationRows($conn, $userId, $featureEnabled, $limit = 10)
{
    $limit = max(1, (int) $limit);
    $rows = [];

    if ($featureEnabled) {
        $sql = "
            SELECT
                a.id,
                a.content,
                a.created_at,
                au.display_name,
                CASE WHEN nr.id IS NULL THEN 0 ELSE 1 END AS is_read
            FROM announcements a
            JOIN admin_users au ON a.admin_id = au.id
            LEFT JOIN notification_reads nr
                ON nr.announcement_id = a.id
               AND nr.user_id = ?
            ORDER BY a.created_at DESC
            LIMIT {$limit}
        ";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $row['is_read'] = (int) $row['is_read'];
                $rows[] = $row;
            }
            $stmt->close();
        }
    } else {
        $sql = "
            SELECT a.id, a.content, a.created_at, au.display_name
            FROM announcements a
            JOIN admin_users au ON a.admin_id = au.id
            ORDER BY a.created_at DESC
            LIMIT {$limit}
        ";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['is_read'] = strtotime((string) $row['created_at']) >= strtotime('-7 days') ? 0 : 1;
                $rows[] = $row;
            }
        }
    }

    return $rows;
}

function fetchUnreadNotificationCount($conn, $userId, $featureEnabled)
{
    if ($featureEnabled) {
        $sql = "
            SELECT COUNT(*) AS unread_count
            FROM announcements a
            LEFT JOIN notification_reads nr
                ON nr.announcement_id = a.id
               AND nr.user_id = ?
            WHERE nr.id IS NULL
        ";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return (int) ($row['unread_count'] ?? 0);
        }
        return 0;
    }

    $count = 0;
    $result = $conn->query("SELECT created_at FROM announcements ORDER BY created_at DESC LIMIT 10");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (strtotime((string) $row['created_at']) >= strtotime('-7 days')) {
                $count++;
            }
        }
    }
    return $count;
}

// ── Notification fetch endpoint (AJAX) ──
if (isset($_GET['ajax_fetch_notifications'])) {
    header('Content-Type: application/json');

    $notifications = fetchNotificationRows($conn, $userId, $notificationFeatureEnabled, 10);
    $unreadCount = fetchUnreadNotificationCount($conn, $userId, $notificationFeatureEnabled);

    echo json_encode([
        'feature_enabled' => $notificationFeatureEnabled,
        'announcements' => $notifications,
        'unread_count' => $unreadCount,
    ]);
    exit;
}

// ── Notification read endpoint (AJAX) ──
if (isset($_GET['ajax_mark_notification_read'])) {
    header('Content-Type: application/json');

    if (!$notificationFeatureEnabled) {
        http_response_code(503);
        echo json_encode(['error' => 'Notification tracking is unavailable until the latest database schema is applied.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $markAll = isset($_POST['mark_all']) && $_POST['mark_all'] === '1';

    if ($markAll) {
        $sql = "
            INSERT INTO notification_reads (user_id, announcement_id, read_at)
            SELECT ?, a.id, NOW()
            FROM announcements a
            LEFT JOIN notification_reads nr
                ON nr.announcement_id = a.id
               AND nr.user_id = ?
            WHERE nr.id IS NULL
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to prepare mark-all query']);
            exit;
        }
        $stmt->bind_param('ii', $userId, $userId);
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to mark notifications as read']);
            $stmt->close();
            exit;
        }
        $stmt->close();
    } else {
        $announcementId = (int) ($_POST['announcement_id'] ?? 0);
        if ($announcementId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid announcement ID']);
            exit;
        }

        $stmt = $conn->prepare(
            'INSERT INTO notification_reads (user_id, announcement_id, read_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)'
        );
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to prepare read query']);
            exit;
        }
        $stmt->bind_param('ii', $userId, $announcementId);
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to mark notification as read']);
            $stmt->close();
            exit;
        }
        $stmt->close();
    }

    $unreadCount = fetchUnreadNotificationCount($conn, $userId, true);
    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount,
    ]);
    exit;
}

// ── Fetch Announcements ──
$announcements = fetchNotificationRows($conn, $userId, $notificationFeatureEnabled, 10);
$newAnnCount = fetchUnreadNotificationCount($conn, $userId, $notificationFeatureEnabled);

function esc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Year level label helper
function yearLabel($level) {
    $n = (int) $level;
    if ($n <= 0) return 'N/A';
    $suffix = 'th';
    if (($n % 100) < 11 || ($n % 100) > 13) {
        if (($n % 10) === 1) $suffix = 'st';
        elseif (($n % 10) === 2) $suffix = 'nd';
        elseif (($n % 10) === 3) $suffix = 'rd';
    }
    return $n . $suffix . ' Year';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="../assets/css/shared/global.css">
    <link rel="stylesheet" href="../assets/css/shared/navbar.css">
    <link rel="stylesheet" href="../assets/css/student/student_dashboard.css">
    <!-- FontAwesome CDN for standard icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Material Symbols for Close Icon -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../assets/images/ccs.png">
</head>

<body class="dashboard-body">

    <nav class="academic-ledger-navbar">
        <div class="nav-container">
            <div class="brand">
                <h1 class="brand-title">College of Computer Studies Sit-in Monitoring System</h1>
            </div>
            <div class="nav-links">
                <div class="student-notification" id="studentNotificationRoot">
                    <button
                        type="button"
                        class="student-notification-trigger"
                        id="studentNotifTrigger"
                        aria-label="Open notifications"
                        aria-expanded="false"
                        aria-haspopup="true"
                        aria-controls="studentNotifDropdown">
                        <span class="material-symbols-outlined">notifications</span>
                        <span class="student-notification-badge<?= $newAnnCount > 0 ? '' : ' is-hidden' ?>" id="studentNotifBadge"><?= $newAnnCount > 99 ? '99+' : $newAnnCount ?></span>
                    </button>

                    <div class="student-notification-dropdown" id="studentNotifDropdown" role="menu" aria-label="Announcement notifications">
                        <div class="student-notification-header">
                            <h3>Announcements</h3>
                            <span class="student-notification-chip" id="studentNotifUnreadChip"><?= $newAnnCount ?> unread</span>
                        </div>

                        <div class="student-notification-list" id="studentNotifList"></div>

                        <div class="student-notification-footer">
                            <button type="button" class="student-notification-mark-all" id="studentNotifMarkAll">Mark all as read</button>
                        </div>
                    </div>
                </div>

                <a class="nav-link active" href="student_dashboard.php">Home</a>
                <a class="nav-link" href="edit_profile.php">Edit Profile</a>
                <a class="nav-link" href="reservations.php">Reservations</a>
                <a class="nav-link" href="sit_in_history_student.php">Sit-in History</a>
                <a class="nav-logout" href="student_dashboard.php?logout=1">Log out</a>
            </div>
        </div>
    </nav>

    <main class="dashboard-home-container">
        <div class="dashboard-home-grid">
            <!-- Card 1: Student Information -->
            <section class="dh-col-4">
                <div class="dh-card">
                    <div class="dh-header-flex dh-header-center">
                        <h2 style="display: flex; align-items: center; gap: 0.5rem">
                            <span class="material-symbols-outlined" style="color: var(--dh-primary)">person</span>
                            Student Information
                        </h2>
                    </div>
                    <div class="dh-profile-header">
                        <a href="edit_profile.php" class="dh-avatar-wrapper" title="Click to upload or change profile picture">
                            <img src="./<?= esc($avatarPath) ?>" alt="Student avatar">
                        </a>
                        <h2><?= esc($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                        <p class="dh-text-xs" style="margin-top: 0.5rem; font-weight: 500">
                            ID: <?= esc($user['id_number']) ?>
                        </p>
                    </div>
                    <div class="dh-info-group">
                        <span class="dh-label-tiny">Course &amp; Department</span>
                        <p class="dh-text-sm"><?= esc($user['course']) ?> - College of Computer Studies</p>
                    </div>
                    <div class="dh-info-row">
                        <div class="dh-info-group">
                            <span class="dh-label-tiny">Year Level</span>
                            <p class="dh-text-sm"><?= esc(yearLabel($user['course_level'])) ?></p>
                        </div>
                        <div class="dh-info-group">
                            <span class="dh-label-tiny">Remaining Sessions</span>
                            <p class="dh-text-sm"><?= $remainingSessions ?></p>
                        </div>
                    </div>
                    <div class="dh-info-group">
                        <span class="dh-label-tiny">Email Address</span>
                        <p class="dh-text-sm"><?= esc($user['email']) ?></p>
                    </div>
                    <div class="dh-info-group" style="border: none; margin-bottom: 0">
                        <span class="dh-label-tiny">Home Address</span>
                        <p class="dh-text-sm"><?= esc($user['address']) ?></p>
                    </div>
                </div>
            </section>

            <!-- Card 2: Announcement Feed -->
            <section class="dh-col-4">
                <div class="dh-card dh-card-low">
                    <div class="dh-header-flex dh-header-center">
                        <h2 style="display: flex; align-items: center; gap: 0.5rem">
                            <span class="material-symbols-outlined" style="color: var(--dh-secondary)">campaign</span>
                            Announcements
                        </h2>
                        <?php if ($newAnnCount > 0): ?>
                            <span class="dh-badge-overlap"><?= $newAnnCount ?> NEW</span>
                        <?php endif; ?>
                    </div>
                    <div class="dh-announcement-list">
                        <?php if (empty($announcements)): ?>
                            <div class="dh-empty-state">
                                <span class="material-symbols-outlined">campaign</span>
                                <p>No announcements yet.</p>
                            </div>
                        <?php else: ?>
                            <?php
                            foreach ($announcements as $idx => $ann):
                                $colorClass = '';
                                if ($idx === 0) $colorClass = '';
                                elseif ($idx === 1) $colorClass = 'dh-primary';
                                else $colorClass = 'dh-muted';

                                $isUnread = ((int) ($ann['is_read'] ?? 1)) === 0;
                            ?>
                                <article
                                    id="announcement-<?= (int) $ann['id'] ?>"
                                    data-announcement-id="<?= (int) $ann['id'] ?>"
                                    class="dh-announcement-item <?= $colorClass ?><?= $isUnread ? ' dh-announcement-unread' : '' ?>">
                                    <div class="dh-item-meta">
                                        <span class="dh-label-tiny" style="color: <?= $idx === 0 ? 'var(--dh-secondary)' : ($idx === 1 ? 'var(--dh-primary)' : 'var(--dh-outline)') ?>; margin: 0">
                                            <?= esc($ann['display_name']) ?>
                                        </span>
                                        <span class="dh-label-tiny" style="margin: 0">
                                            <?= date('Y-M-d', strtotime($ann['created_at'])) ?>
                                        </span>
                                    </div>
                                    <p class="dh-text-xs dh-announcement-content">
                                        <?= nl2br(esc($ann['content'])) ?>
                                    </p>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Card 3: Laboratory Policies -->
            <section class="dh-col-4">
                <div class="dh-card">
                    <div class="dh-header-flex dh-header-center">
                        <h2 style="display: flex; align-items: center; gap: 0.5rem">
                            <span class="material-symbols-outlined" style="color: var(--dh-tertiary)">gavel</span>
                            Rules and Regulations
                        </h2>
                    </div>
                    <ol class="dh-policy-list">
                        <li class="dh-policy-item">
                            <span class="dh-policy-number">01</span>
                            <div>
                                <p class="dh-text-xs">
                                    Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones, walkmans and other personal pieces of equipment must be switched off.
                                </p>
                            </div>
                        </li>
                        <li class="dh-policy-item">
                            <span class="dh-policy-number">02</span>
                            <div>
                                <p class="dh-text-xs">
                                    Games are not allowed inside the lab. This includes computer-related games, card games and other games that may disturb the operation of the lab.
                                </p>
                            </div>
                        </li>
                        <li class="dh-policy-item">
                            <span class="dh-policy-number">03</span>
                            <div>
                                <p class="dh-text-xs">
                                    Surfing the Internet is allowed only with the permission of the instructor. Downloading and installing of software are strictly prohibited.
                                </p>
                            </div>
                        </li>
                        <li class="dh-policy-item">
                            <span class="dh-policy-number">04</span>
                            <div>
                                <p class="dh-text-xs">
                                    Getting inside the laboratory requires the student to log in the logbook. No sitting-in without the permission of the instructor.
                                </p>
                            </div>
                        </li>
                        <li class="dh-policy-item">
                            <span class="dh-policy-number">05</span>
                            <div>
                                <p class="dh-text-xs">
                                    Students are not allowed to transfer from one laboratory to another without permission.
                                </p>
                            </div>
                        </li>
                        <li class="dh-policy-item">
                            <span class="dh-policy-number">06</span>
                            <div>
                                <p class="dh-text-xs">
                                    Deleting and changing of computer settings is strictly prohibited.
                                </p>
                            </div>
                        </li>
                        <li class="dh-policy-item">
                            <span class="dh-policy-number">07</span>
                            <div>
                                <p class="dh-text-xs">
                                    Bringing of foods, drinks, and other forms of refreshments inside the laboratory is not allowed.
                                </p>
                            </div>
                        </li>
                        <li class="dh-policy-item">
                            <span class="dh-policy-number">08</span>
                            <div>
                                <p class="dh-text-xs">
                                    Students must clean up after using the laboratory. Chair and tables should be arranged properly before leaving.
                                </p>
                            </div>
                        </li>
                        <li class="dh-policy-item">
                            <span class="dh-policy-number">09</span>
                            <div>
                                <p class="dh-text-xs">
                                    Students who damage equipment will be held responsible for the repair or replacement of the damaged item.
                                </p>
                            </div>
                        </li>
                        <li class="dh-policy-item">
                            <span class="dh-policy-number">10</span>
                            <div>
                                <p class="dh-text-xs">
                                    Only authorized personnel are allowed to use the printer and other peripherals.
                                </p>
                            </div>
                        </li>
                        <li class="dh-policy-item">
                            <span class="dh-policy-number">11</span>
                            <div>
                                <p class="dh-text-xs">
                                    Any violation of the rules and regulations will be subject to disciplinary action in accordance with the University's student handbook.
                                </p>
                            </div>
                        </li>
                    </ol>
                    <div class="dh-warning-footer">
                        <span class="material-symbols-outlined dh-icon-small" style="color: var(--dh-tertiary)">gavel</span>
                        <p>
                            Non-compliance with policies may result in temporary suspension
                            of laboratory privileges.
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <?php if ($showLoginSuccess): ?>
        <div class="login-success-overlay" id="loginSuccessOverlay">
            <div class="login-success-modal">
                <div class="success-icon">✓</div>
                <h2>Successful Login!</h2>
                <p>Welcome, <?= esc($successName) ?>!</p>
                <button type="button" id="closeSuccessModal">OK</button>
            </div>
        </div>

        <script>
            (function () {
                const overlay = document.getElementById('loginSuccessOverlay');
                const closeBtn = document.getElementById('closeSuccessModal');

                if (closeBtn && overlay) {
                    closeBtn.addEventListener('click', function () {
                        overlay.style.display = 'none';
                    });
                }
            })();
        </script>
    <?php endif; ?>

    <script>
        (function () {
            const notificationFeatureEnabled = <?= $notificationFeatureEnabled ? 'true' : 'false' ?>;
            const root = document.getElementById('studentNotificationRoot');
            const trigger = document.getElementById('studentNotifTrigger');
            const dropdown = document.getElementById('studentNotifDropdown');
            const badge = document.getElementById('studentNotifBadge');
            const unreadChip = document.getElementById('studentNotifUnreadChip');
            const list = document.getElementById('studentNotifList');
            const markAllBtn = document.getElementById('studentNotifMarkAll');

            if (!root || !trigger || !dropdown || !badge || !unreadChip || !list || !markAllBtn) {
                return;
            }

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function formatDate(value) {
                const date = new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return value;
                }

                return new Intl.DateTimeFormat('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit'
                }).format(date);
            }

            function setBadgeCount(count) {
                const safeCount = Math.max(0, Number(count) || 0);
                unreadChip.textContent = safeCount + ' unread';

                if (safeCount > 0) {
                    badge.textContent = safeCount > 99 ? '99+' : String(safeCount);
                    badge.classList.remove('is-hidden');
                    trigger.classList.add('has-unread');
                } else {
                    badge.textContent = '0';
                    badge.classList.add('is-hidden');
                    trigger.classList.remove('has-unread');
                }
            }

            function highlightAnnouncement(announcementId) {
                const card = document.querySelector('.dh-announcement-item[data-announcement-id="' + announcementId + '"]');
                if (!card) {
                    return;
                }

                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                card.classList.add('dh-announcement-item-highlight');
                window.setTimeout(function () {
                    card.classList.remove('dh-announcement-item-highlight');
                }, 1800);
            }

            function closeDropdown() {
                dropdown.classList.remove('active');
                trigger.setAttribute('aria-expanded', 'false');
            }

            function openDropdown() {
                dropdown.classList.add('active');
                trigger.setAttribute('aria-expanded', 'true');
            }

            function renderNotifications(items) {
                if (!Array.isArray(items) || items.length === 0) {
                    list.innerHTML = '<p class="student-notification-empty">No announcements yet.</p>';
                    markAllBtn.disabled = true;
                    return;
                }

                list.innerHTML = items.map(function (item) {
                    const id = Number(item.id) || 0;
                    const isUnread = Number(item.is_read) === 0;
                    const preview = String(item.content || '').trim();

                    return (
                        '<button type="button" class="student-notification-item ' + (isUnread ? 'unread' : '') + '" data-announcement-id="' + id + '">' +
                            '<div class="student-notification-item-top">' +
                                '<span class="student-notification-author">' + escapeHtml(item.display_name || 'Admin') + '</span>' +
                                '<span class="student-notification-date">' + escapeHtml(formatDate(item.created_at || '')) + '</span>' +
                            '</div>' +
                            '<p class="student-notification-message">' + escapeHtml(preview) + '</p>' +
                        '</button>'
                    );
                }).join('');

                markAllBtn.disabled = items.every(function (item) {
                    return Number(item.is_read) !== 0;
                });
            }

            async function loadNotifications() {
                try {
                    const response = await fetch('student_dashboard.php?ajax_fetch_notifications=1', {
                        headers: { 'Accept': 'application/json' }
                    });

                    if (!response.ok) {
                        throw new Error('Unable to fetch notifications');
                    }

                    const payload = await response.json();
                    setBadgeCount(payload.unread_count || 0);
                    renderNotifications(payload.announcements || []);

                    if (!payload.feature_enabled) {
                        markAllBtn.disabled = true;
                        markAllBtn.textContent = 'Read tracking unavailable';
                    }
                } catch (error) {
                    list.innerHTML = '<p class="student-notification-empty">Unable to load notifications right now.</p>';
                    markAllBtn.disabled = true;
                }
            }

            async function markOneAsRead(announcementId) {
                if (!notificationFeatureEnabled || announcementId <= 0) {
                    return;
                }

                const body = new URLSearchParams();
                body.set('announcement_id', String(announcementId));

                const response = await fetch('student_dashboard.php?ajax_mark_notification_read=1', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json'
                    },
                    body: body.toString()
                });

                if (!response.ok) {
                    throw new Error('Unable to update notification');
                }
            }

            async function markAllAsRead() {
                if (!notificationFeatureEnabled) {
                    return;
                }

                const body = new URLSearchParams();
                body.set('mark_all', '1');

                const response = await fetch('student_dashboard.php?ajax_mark_notification_read=1', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json'
                    },
                    body: body.toString()
                });

                if (!response.ok) {
                    throw new Error('Unable to update notifications');
                }
            }

            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                if (dropdown.classList.contains('active')) {
                    closeDropdown();
                } else {
                    openDropdown();
                }
            });

            document.addEventListener('click', function (event) {
                if (!root.contains(event.target)) {
                    closeDropdown();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeDropdown();
                }
            });

            list.addEventListener('click', async function (event) {
                const row = event.target.closest('.student-notification-item');
                if (!row) {
                    return;
                }

                const announcementId = Number(row.dataset.announcementId || '0');

                try {
                    await markOneAsRead(announcementId);
                    closeDropdown();
                    highlightAnnouncement(announcementId);
                    await loadNotifications();
                } catch (error) {
                    closeDropdown();
                }
            });

            markAllBtn.addEventListener('click', async function () {
                try {
                    await markAllAsRead();
                    closeDropdown();
                    await loadNotifications();
                } catch (error) {
                    // no-op: UI remains usable and will refresh on next fetch
                }
            });

            loadNotifications();
            if (notificationFeatureEnabled) {
                window.setInterval(loadNotifications, 60000);
            }
        })();
    </script>

</body>

</html>