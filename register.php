<?php
session_start();
require_once 'includes/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_number = trim($_POST['id_number']);
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $course_lvl = trim($_POST['course_level']);
    $password = trim($_POST['password']);
    $repeat_pw = trim($_POST['repeat_password']);
    $email = trim($_POST['email']);
    $course = trim($_POST['course']);
    $address = trim($_POST['address']);

    // Validation
    if (
        empty($id_number) || empty($last_name) || empty($first_name) ||
        empty($course_lvl) || empty($password) || empty($email) || empty($course)
    ) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $repeat_pw) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Hash the password before saving
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            'INSERT INTO users
             (id_number, last_name, first_name, middle_name, course_level, password, email, course, address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'ssssissss',
            $id_number,
            $last_name,
            $first_name,
            $middle_name,
            $course_lvl,
            $hashed,
            $email,
            $course,
            $address
        );

        if ($stmt->execute()) {
            $showRegisterSuccess = true;
            $successName = trim($first_name . ' ' . $last_name);
        } else {
            // Check for duplicate entry
            if ($stmt->errno === 1062) {
                $error = 'ID Number or Email is already registered.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
        $stmt->close();
    }
}

// Helper to re-fill form fields after failed submission
function old($field, $default = '')
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return htmlspecialchars($_POST[$field] ?? $default);
    }
    return $default;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CCS</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/x-icon" href="./images/ccs.png">
</head>

<body class="bg-light">

    <nav class="navbar">
        <h1 class="navbar-title">College of Computer Studies Sit-in Monitoring System</h1>
        <ul class="navbar-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="#community">Community</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="index.php">Login</a></li>
            <li><a href="register.php">Register</a></li>
        </ul>
    </nav>

    <div class="register-container">
        <div class="register-card">
            <button class="back-btn" onclick="window.history.back()">Back</button>
            <h2 class="register-title">Sign up</h2>

            <?php if ($error): ?>
                <p style="color: red; text-align: center; margin-bottom: 12px;">
                    <?= htmlspecialchars($error) ?>
                </p>
            <?php endif; ?>

            <div class="register-content">
                <form class="register-form" method="POST" action="register.php">
                    <div class="input-group">
                        <input type="text" name="id_number" value="<?= old('id_number') ?>" required>
                        <label>ID Number</label>
                    </div>
                    <div class="input-group">
                        <input type="text" name="last_name" value="<?= old('last_name') ?>" required>
                        <label>Last Name</label>
                    </div>
                    <div class="input-group">
                        <input type="text" name="first_name" value="<?= old('first_name') ?>" required>
                        <label>First Name</label>
                    </div>
                    <div class="input-group">
                        <input type="text" name="middle_name" value="<?= old('middle_name') ?>" required>
                        <label>Middle Name</label>
                    </div>
                    <div class="input-group">
                        <select name="course" required>
                            <option value="" disabled selected>Select Course</option>
                            <option value="BSIT" <?= old('course') === 'BSIT' ? 'selected' : '' ?>>Bachelor of Science in
                                Information Technology</option>
                            <option value="BSCS" <?= old('course') === 'BSCS' ? 'selected' : '' ?>>Bachelor of Science in
                                Computer Science</option>
                            <option value="BSIS" <?= old('course') === 'BSIS' ? 'selected' : '' ?>>Bachelor of Science in
                                Information Systems</option>
                        </select>
                        <label>Course</label>
                    </div>
                    <div class="input-group">
                        <select name="course_level" required>
                            <option value="" disabled selected>Select Year level</option>
                            <option value="1" <?= old('course_level') === '1' ? 'selected' : '' ?>>1st Year</option>
                            <option value="2" <?= old('course_level') === '2' ? 'selected' : '' ?>>2nd Year</option>
                            <option value="3" <?= old('course_level') === '3' ? 'selected' : '' ?>>3rd Year</option>
                            <option value="4" <?= old('course_level') === '4' ? 'selected' : '' ?>>4th Year</option>
                        </select>
                        <label>Course Level</label>
                    </div>
                    <div class="input-group">
                        <input type="password" name="password" required>
                        <label>Password</label>
                    </div>
                    <div class="input-group">
                        <input type="password" name="repeat_password" required>
                        <label>Repeat your password</label>
                    </div>
                    <div class="input-group">
                        <input type="email" name="email" value="<?= old('email') ?>" required>
                        <label>Email</label>
                    </div>
                    <div class="input-group">
                        <input type="text" name="address" value="<?= old('address') ?>" required>
                        <label>Address</label>
                    </div>

                    <button type="submit" class="register-btn">Register</button>
                </form>

                <div class="register-image">
                    <img src="./images/signup-img.jpg" alt="Sign up illustration">
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($showRegisterSuccess) && $showRegisterSuccess): ?>
        <div class="login-success-overlay" id="registerSuccessOverlay">
            <div class="login-success-modal">
                <div class="success-icon">✓</div>
                <h2>Registered Successfully!</h2>
                <p>Welcome, <strong><?= htmlspecialchars($successName, ENT_QUOTES, 'UTF-8') ?>!</strong><br><br>
                   Your account has been created successfully. You can now log in with your credentials.</p>
                <a href="index.php" class="modal-link-btn">Go to Login</a>
            </div>
        </div>
    <?php endif; ?>

</body>

</html>