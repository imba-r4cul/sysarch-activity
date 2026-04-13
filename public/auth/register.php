<?php
session_start();
require_once '../../config/database.php';

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
    <title>Register</title>
    <link rel="stylesheet" href="../assets/css/shared/global.css">
    <link rel="stylesheet" href="../assets/css/shared/navbar.css">
    <link rel="stylesheet" href="../assets/css/auth/auth.css">
    <link rel="icon" type="image/x-icon" href="../assets/images/ccs.png">
</head>

<body class="bg-light register-page">

    <nav class="academic-ledger-navbar">
        <div class="nav-container">
            <div class="brand">
                <h1 class="brand-title">College of Computer Studies Sit-in Monitoring System</h1>
            </div>
            <div class="nav-links">
                <a class="nav-link" href="index.php">Home</a>
                <a class="nav-link" href="#community">Community</a>
                <a class="nav-link" href="#about">About</a>
                <a class="nav-link" href="index.php">Login</a>
                <a class="nav-link active" href="register.php">Register</a>
            </div>
        </div>
    </nav>

    <div class="signup-wrapper">
        <div class="signup-card">
            <!-- Sidebar -->
            <div class="signup-sidebar">
                <div class="login-logo"></div>
                <div class="login-link-sidebar">
                    Already have an account?<br><a href="index.php">Sign In →</a>
                </div>
            </div>

            <!-- Form Area -->
            <div class="signup-form-area">
                <h3>Personal Information</h3>
                <p class="subtitle">Fill in your details to create a student account.</p>

                <?php if ($error): ?>
                    <div class="signup-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="register.php">
                    <div class="signup-grid">
                        <div class="field-group">
                            <label for="id_number">ID Number</label>
                            <input type="text" id="id_number" name="id_number" value="<?= old('id_number') ?>"
                                placeholder="e.g. 23764129" required>
                        </div>
                        <div class="field-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?= old('email') ?>"
                                placeholder="raculxd@gmail.com" required>
                        </div>
                        <div class="field-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?= old('last_name') ?>"
                                placeholder="Estrera" required>
                        </div>
                        <div class="field-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?= old('first_name') ?>"
                                placeholder="Rico" required>
                        </div>
                        <div class="field-group">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" value="<?= old('middle_name') ?>"
                                placeholder="Luchavez">
                        </div>
                        <div class="field-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" value="<?= old('address') ?>"
                                placeholder="Cebu City" required>
                        </div>
                        <div class="field-group">
                            <label for="course">Course</label>
                            <select id="course" name="course" required>
                                <option value="" disabled selected>Select Course</option>
                                <option value="BSIT" <?= old('course') === 'BSIT' ? 'selected' : '' ?>>BS Information
                                    Technology</option>
                                <option value="BSCS" <?= old('course') === 'BSCS' ? 'selected' : '' ?>>BS Computer Science
                                </option>
                                <option value="BSIS" <?= old('course') === 'BSIS' ? 'selected' : '' ?>>BS Information
                                    Systems</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="course_level">Year Level</label>
                            <select id="course_level" name="course_level" required>
                                <option value="" disabled selected>Select Year</option>
                                <option value="1" <?= old('course_level') === '1' ? 'selected' : '' ?>>1st Year</option>
                                <option value="2" <?= old('course_level') === '2' ? 'selected' : '' ?>>2nd Year</option>
                                <option value="3" <?= old('course_level') === '3' ? 'selected' : '' ?>>3rd Year</option>
                                <option value="4" <?= old('course_level') === '4' ? 'selected' : '' ?>>4th Year</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" placeholder="Min. 6 characters"
                                required>
                        </div>
                        <div class="field-group">
                            <label for="repeat_password">Confirm Password</label>
                            <input type="password" id="repeat_password" name="repeat_password"
                                placeholder="Re-enter password" required>
                        </div>
                    </div>
                    <div class="signup-actions">
                        <a href="index.php" style="color:#666; text-decoration:none; font-size:14px;">← Back to
                            Login</a>
                        <button type="submit" class="signup-submit-btn">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (isset($showRegisterSuccess) && $showRegisterSuccess): ?>
        <div class="login-success-overlay" id="registerSuccessOverlay">
            <div class="login-success-modal">
                <div class="success-icon">✓</div>
                <h2>Registered Successfully!</h2>
                <p> Your account has been created successfully. You can now log in with your credentials.</p>
                <a href="index.php" class="modal-link-btn">Go to Login</a>
            </div>
        </div>
    <?php endif; ?>

</body>

</html>