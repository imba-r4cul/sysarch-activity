<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$success = $_SESSION['profile_success'] ?? '';
unset($_SESSION['profile_success']);
$error = '';

$defaultProfileImage = 'images/edit-profile.png';
$uploadsDir = __DIR__ . '/uploads/';
$maxUploadSize = 2 * 1024 * 1024;
$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
];

$stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_picture'])) {
        $oldFileNameToDelete = !empty($user['profile_image']) ? basename($user['profile_image']) : '';

        $stmt = $conn->prepare('UPDATE users SET profile_image = NULL WHERE id = ?');
        $stmt->bind_param('i', $userId);

        if ($stmt->execute()) {
            if ($oldFileNameToDelete !== '') {
                $oldPath = $uploadsDir . $oldFileNameToDelete;
                if (is_file($oldPath)) {
                    unlink($oldPath);
                }
            }

            $_SESSION['profile_success'] = 'Profile picture deleted successfully.';
            $stmt->close();
            header('Location: edit_profile.php');
            exit;
        }

        $error = 'Failed to delete profile picture.';
        $stmt->close();
    } else {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $course = trim($_POST['course']);
        $course_lvl = (int) $_POST['course_level'];
        $address = trim($_POST['address']);
        $profileImageFileName = !empty($user['profile_image']) ? basename($user['profile_image']) : null;
        $newlyUploadedFile = '';

        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Upload failed. Please try again.';
            } elseif ($_FILES['profile_image']['size'] > $maxUploadSize) {
                $error = 'Image is too large. Maximum size is 2MB.';
            } else {
                $tmpPath = $_FILES['profile_image']['tmp_name'];
                $imageInfo = @getimagesize($tmpPath);

                if ($imageInfo === false) {
                    $error = 'Invalid image file.';
                } else {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo ? finfo_file($finfo, $tmpPath) : '';

                    if (!isset($allowedMimeTypes[$mimeType])) {
                        $error = 'Only JPG, PNG, and GIF files are allowed.';
                    } else {
                        if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0755, true) && !is_dir($uploadsDir)) {
                            $error = 'Unable to prepare upload directory.';
                        } else {
                            $extension = $allowedMimeTypes[$mimeType];
                            $uniquePart = bin2hex(random_bytes(8));
                            $newFileName = 'profile_user_' . $userId . '_' . $uniquePart . '.' . $extension;
                            $destination = $uploadsDir . $newFileName;

                            if (!move_uploaded_file($tmpPath, $destination)) {
                                $error = 'Failed to save uploaded image.';
                            } else {
                                $newlyUploadedFile = $newFileName;
                                $profileImageFileName = $newFileName;
                            }
                        }
                    }
                }
            }
        }

        if ($error === '') {
            $stmt = $conn->prepare(
                'UPDATE users SET first_name = ?, last_name = ?, email = ?, course = ?, course_level = ?, address = ?, profile_image = ? WHERE id = ?'
            );
            $stmt->bind_param('ssssissi', $first_name, $last_name, $email, $course, $course_lvl, $address, $profileImageFileName, $userId);

            if ($stmt->execute()) {
                if ($newlyUploadedFile !== '' && !empty($user['profile_image'])) {
                    $oldPath = $uploadsDir . basename($user['profile_image']);
                    if (is_file($oldPath)) {
                        unlink($oldPath);
                    }
                }

                $_SESSION['profile_success'] = 'Profile updated successfully!';
                $stmt->close();
                header('Location: edit_profile.php');
                exit;
            }

            if ($newlyUploadedFile !== '') {
                $newPath = $uploadsDir . $newlyUploadedFile;
                if (is_file($newPath)) {
                    unlink($newPath);
                }
            }

            $error = 'Failed to update profile.';
            $stmt->close();
        }
    }
}

$profileImagePath = $defaultProfileImage;
if (!empty($user['profile_image'])) {
    $safeFileName = basename($user['profile_image']);
    $diskPath = $uploadsDir . $safeFileName;
    if (is_file($diskPath)) {
        $profileImagePath = 'uploads/' . rawurlencode($safeFileName);
    }
}

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
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="./css/dashboard.css">
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

            <form method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; height: 100%;">

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
                        <img src="./<?= esc($profileImagePath) ?>" alt="Profile" class="profile-pic-placeholder">
                        <input type="file" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/gif" hidden>
                        <button type="button" class="btn-primary-outline" id="upload-picture-btn">Upload picture</button>
                        <button type="submit" name="delete_picture" value="1" class="btn-danger-outline" formnovalidate
                            onclick="return confirm('Delete your profile picture?');">Delete picture</button>
                    </div>
                    <p class="profile-picture-help">
                        <?= empty($user['profile_image']) ? 'No profile picture yet. Upload one now.' : 'Click Upload picture to replace your current profile picture.' ?>
                    </p>
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
        const uploadBtn = document.getElementById('upload-picture-btn');
        const profileImageInput = document.getElementById('profile_image');

        if (uploadBtn && profileImageInput) {
            uploadBtn.addEventListener('click', () => {
                profileImageInput.click();
            });
        }

        const successMessage = document.getElementById('success-message');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 3000);
        }
    </script>
</body>

</html>