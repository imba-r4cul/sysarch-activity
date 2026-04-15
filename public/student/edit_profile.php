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
$success = $_SESSION['profile_success'] ?? '';
unset($_SESSION['profile_success']);
$error = '';

$notificationFeatureEnabled = studentNotificationFeatureEnabled($conn);
studentHandleNotificationAjax($conn, $userId, $notificationFeatureEnabled);

$defaultProfileImage = '../assets/images/edit-profile.png';
$uploadsDir = __DIR__ . '/../assets/uploads/';
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
    header('Location: ../auth/index.php');
    exit;
}

$newAnnCount = studentFetchUnreadNotificationCount($conn, $userId, $notificationFeatureEnabled);

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

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } else {
            // Check for email uniqueness (excluding current user)
            $checkEmailStmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $checkEmailStmt->bind_param('si', $email, $userId);
            $checkEmailStmt->execute();
            if ($checkEmailStmt->get_result()->num_rows > 0) {
                $error = 'Email is already taken by another user.';
            }
            $checkEmailStmt->close();
        }
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
        $profileImagePath = '../assets/uploads/' . rawurlencode($safeFileName);
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="../assets/css/shared/global.css">
    <link rel="stylesheet" href="../assets/css/shared/navbar.css">
    <link rel="stylesheet" href="../assets/css/student/student_dashboard.css">
    <!-- FontAwesome CDN for standard icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="../assets/images/ccs.png">
</head>

<body class="dashboard-body">
    <?php renderStudentNavbar('profile', $newAnnCount); ?>

    <main class="dashboard-container">
        <div class="edit-profile-card">

            <form method="POST" enctype="multipart/form-data"
                style="display: flex; flex-direction: column; height: 100%;">

                <div class="profile-header">
                    <h2>Your Profile</h2>
                    <div class="profile-actions">
                        <button type="button" class="btn-secondary"
                            onclick="window.location.href='student_dashboard.php'">Discard</button>
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
                        <img src="./<?= esc($profileImagePath) ?>" alt="Profile" class="profile-pic-placeholder"
                            id="profile-image-preview">
                        <input type="file" id="profile_image" name="profile_image"
                            accept="image/jpeg,image/png,image/gif" hidden>
                        <button type="button" class="btn-primary-outline" id="upload-picture-btn">Upload
                            picture</button>
                        <button type="button" class="btn-danger-outline" onclick="confirmDeletePicture()">Delete picture</button>
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

    <!-- Hidden form for deleting profile picture -->
    <form id="deletePictureForm" method="POST" action="edit_profile.php" style="display:none;">
        <input type="hidden" name="delete_picture" value="1">
    </form>

    <!-- Confirmation Modal (Synced with Admin) -->
    <div class="modal-overlay" id="confirmActionModal">
        <div class="modal-box confirmation-box">
            <div class="modal-header">
            </div>
            <div class="modal-body">
                <h3 id="confirmActionTitle">Confirm Action</h3>
                <p id="confirmActionMessage">Are you sure?</p>
            </div>
            <div class="modal-actions">
                <button type="button" class="modal-btn btn-cancel" onclick="closeModal('confirmActionModal')">Cancel</button>
                <button type="button" class="modal-btn btn-confirm" id="confirmActionBtn">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        const uploadBtn = document.getElementById('upload-picture-btn');
        const profileImageInput = document.getElementById('profile_image');
        const profileImagePreview = document.getElementById('profile-image-preview');
        const profilePictureHelp = document.querySelector('.profile-picture-help');
        let previewObjectUrl = '';

        if (uploadBtn && profileImageInput) {
            uploadBtn.addEventListener('click', () => {
                profileImageInput.click();
            });

            profileImageInput.addEventListener('change', (event) => {
                const selectedFile = event.target.files && event.target.files[0] ? event.target.files[0] : null;
                if (!selectedFile || !profileImagePreview) {
                    return;
                }

                if (previewObjectUrl !== '') {
                    URL.revokeObjectURL(previewObjectUrl);
                    previewObjectUrl = '';
                }

                previewObjectUrl = URL.createObjectURL(selectedFile);
                profileImagePreview.src = previewObjectUrl;

                if (profilePictureHelp) {
                    profilePictureHelp.textContent = 'Selected image preview shown. Click Save to apply changes.';
                }
            });
        }

        const successMessage = document.getElementById('success-message');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 3000);
        }

        // Modal Logic
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function confirmDeletePicture() {
            document.getElementById('confirmActionTitle').textContent = 'Delete Profile Picture';
            document.getElementById('confirmActionMessage').textContent = 'Are you sure you want to delete your profile picture? This action cannot be undone.';
            
            const confirmBtn = document.getElementById('confirmActionBtn');
            confirmBtn.onclick = function() {
                document.getElementById('deletePictureForm').submit();
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
    </script>
    <?php renderStudentNotificationScript($notificationFeatureEnabled); ?>
</body>

</html>