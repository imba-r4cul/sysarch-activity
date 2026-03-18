<?php
session_start();
require_once 'includes/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $course = trim($_POST['course']);
    $course_lvl = (int) $_POST['course_level'];
    $address = trim($_POST['address']);

    $stmt = $conn->prepare(
        'UPDATE users SET first_name = ?, last_name = ?, email = ?, course = ?, course_level = ?, address = ? WHERE id = ?'
    );
    $stmt->bind_param('ssssisi', $first_name, $last_name, $email, $course, $course_lvl, $address, $userId);

    if ($stmt->execute()) {
        $success = 'Profile updated successfully!';
    } else {
        $error = 'Failed to update profile.';
    }
    $stmt->close();
}

$stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

function esc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - CCS</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css?v=20260319">
    <!-- FontAwesome CDN for standard icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="dashboard-body">

    <nav class="navbar dashboard-nav">
        <h1 class="navbar-title">College of Computer Studies Sit-in
            Monitoring System</h1>
        <ul class="navbar-links dashboard-links">
            <li><a href="dashboard.php">Home</a></li>
            <li><a href="edit_profile.php">Edit Profile</a></li>
            <li><a href="reservations.php">Reservations</a></li>
            <li><a href="dashboard.php?logout=1" class="logout-btn">Log out</a></li>
        </ul>
    </nav>

    <main class="dashboard-container">
        <div class="edit-profile-card">

            <form method="POST" style="display: flex; flex-direction: column; height: 100%;">

                <div class="profile-header">
                    <h2>Your Profile</h2>
                    <div class="profile-actions">
                        <button type="button" class="btn-secondary"
                            onclick="window.location.href='dashboard.php'">Discard</button>
                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i> Save
                        </button>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div id="success-message"
                        style="background:#d4edda; color:#155724; padding:10px; border-radius:6px; margin-bottom:15px; flex-shrink:0;">
                        <?= esc($success) ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div
                        style="background:#f8d7da; color:#721c24; padding:10px; border-radius:6px; margin-bottom:15px; flex-shrink:0;">
                        <?= esc($error) ?>
                    </div>
                <?php endif; ?>

                <div class="profile-section">
                    <div class="section-title">
                        <span class="section-title-icon">
                            <i class="fa-regular fa-image"></i>
                        </span>
                        Profile picture
                    </div>
                    <div class="profile-picture-row">
                        <img src="./images/ccs.png" alt="Profile" class="profile-pic-placeholder">
                        <button type="button" class="btn-primary-outline">Change picture</button>
                        <button type="button" class="btn-danger-outline">Delete picture</button>
                    </div>
                </div>

                <div class="personal-info-container">
                    <div class="section-title" style="margin-top: 10px;">
                        <span class="section-title-icon">
                            <i class="fa-regular fa-user"></i>
                        </span>
                        Personal Information
                    </div>

                    <div class="grid-2-col">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" value="<?= esc($user['first_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" value="<?= esc($user['last_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Course</label>
                            <select name="course" required>
                                <option value="BSIT" <?= $user['course'] === 'BSIT' ? 'selected' : '' ?>>BSIT</option>
                                <option value="BSCS" <?= $user['course'] === 'BSCS' ? 'selected' : '' ?>>BSCS</option>
                                <option value="BSIS" <?= $user['course'] === 'BSIS' ? 'selected' : '' ?>>BSIS</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Year Level</label>
                            <select name="course_level" required>
                                <option value="1" <?= $user['course_level'] == 1 ? 'selected' : '' ?>>1st Year</option>
                                <option value="2" <?= $user['course_level'] == 2 ? 'selected' : '' ?>>2nd Year</option>
                                <option value="3" <?= $user['course_level'] == 3 ? 'selected' : '' ?>>3rd Year</option>
                                <option value="4" <?= $user['course_level'] == 4 ? 'selected' : '' ?>>4th Year</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= esc($user['email']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Address</label>
                            <input type="text" name="address" value="<?= esc($user['address']) ?>" required>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </main>

    <script>
        const successMessage = document.getElementById('success-message');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 3000);
        }
    </script>
</body>

</html>