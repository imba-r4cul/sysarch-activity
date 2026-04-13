<?php
session_start();
require_once '../../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_number = trim($_POST['id_number']);
    $password = trim($_POST['password']);
    $hashed_password = '';

    // Unified admin login via this page
    if ($id_number === 'admin') {
        $stmt = $conn->prepare(
            'SELECT id, password, display_name FROM admin_users WHERE username = ?'
        );
        $adminUsername = 'admin';
        $stmt->bind_param('s', $adminUsername);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($admin_id, $admin_hashed_password, $admin_display_name);
            $stmt->fetch();

            if (password_verify($password, $admin_hashed_password)) {
                $_SESSION['admin_id'] = $admin_id;
                $_SESSION['admin_name'] = $admin_display_name;
                header('Location: ../admin/admin_dashboard.php');
                exit;
            } else {
                $error = 'Invalid admin credentials.';
            }
        } else {
            $error = 'Admin account not found.';
        }

        $stmt->close();
    }

    if ($error !== '') {
        // Skip student login when admin login attempt already failed
    } else {

        $stmt = $conn->prepare(
            'SELECT id, first_name, last_name, password FROM users WHERE id_number = ?'
        );
        $stmt->bind_param('s', $id_number);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $first_name, $last_name, $hashed_password);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                $_SESSION['user_id'] = $user_id;
                $_SESSION['id_number'] = $id_number;
                $_SESSION['login_success_name'] = trim($first_name . ' ' . $last_name);
                header('Location: ../student/student_dashboard.php');
                exit;
            } else {
                $error = 'Incorrect password.';
            }
        } else {
            $error = 'ID Number not found.';
        }

        $stmt->close();
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - CCS</title>
    <link rel="stylesheet" href="../assets/css/shared/global.css">
    <link rel="stylesheet" href="../assets/css/shared/navbar.css">
    <link rel="stylesheet" href="../assets/css/auth/auth.css">
    <link rel="icon" type="image/x-icon" href="../assets/images/ccs.png">
</head>

<body class="bg-light">

    <nav class="academic-ledger-navbar">
        <div class="nav-container">
            <div class="brand">
                <h1 class="brand-title">College of Computer Studies Sit-in Monitoring System</h1>
            </div>
            <div class="nav-links">
                <a class="nav-link active" href="#home">Home</a>
                <a class="nav-link" href="#community">Community</a>
                <a class="nav-link" href="#about">About</a>
                <a class="nav-link" href="#login">Login</a>
                <a class="nav-link" href="register.php">Register</a>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="logo-section">
            <!-- <img src="./images/ccs.png" alt="CCS Logo" class="logo"> -->
            <div class="logo-box"></div>
        </div>
        <div class="login-card" id="login">
            <form class="login-form" method="POST" action="index.php">
                <input type="text" name="id_number" placeholder="Enter a valid ID number">
                <label>ID Number</label>
                <input type="password" name="password" placeholder="Enter your password">
                <label>Password</label>
                <div class="form-options">
                    <label><input type="checkbox"> Remember me</label>
                    <span class="forgot-form"><a href="#">Forgot password?</a></span>
                </div>
                <?php if ($error): ?>
                    <p class="login-error"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
                <button type="submit" class="login-btn">Login</button>
                <div class="register-options">
                    <span>Don't have an account? <a href="register.php" class="register-link">Register</a></span>
                </div>
            </form>
        </div>
    </div>

</body>

</html>