<?php
session_start();
require_once '../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_number = trim($_POST['id_number']);
    $password = trim($_POST['password']);
    $hashed_password = '';

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
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Incorrect password.';
        }
    } else {
        $error = 'ID Number not found.';
    }
    $stmt->close();
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS</title>
    <link rel="stylesheet" href="css/style.css?v=20260319-2">
</head>

<body class="bg-light">

    <nav class="navbar">
        <h1 class="navbar-title">Colleges of Computer Studies Sit-in
            Monitoring System</h1>
        <ul class="navbar-links">
            <li><a href="#home">Home</a></li>
            <li><a href="#community">Community</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#login">Login</a></li>
            <li><a href="register.php">Register</a></li>
        </ul>
    </nav>

    <div class="main-container">
        <div class="logo-section">
            <img src="./images/ccs.png" alt="CCS Logo" class="logo">
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