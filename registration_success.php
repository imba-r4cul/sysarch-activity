<?php
session_start();

// Check if user has just registered
if (!isset($_SESSION['login_success_name'])) {
    header('Location: index.php');
    exit;
}

$user_name = $_SESSION['login_success_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful - CCS</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/x-icon" href="./images/ccs.png">
    <style>
        .success-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 60px); /* Adjust for navbar height approx */
            background-color: #f0f2f5;
            padding: 20px;
            box-sizing: border-box;
        }

        .success-card {
            background: white;
            border-radius: 12px;
            padding: 40px 30px;
            max-width: 400px; /* Reduced size */
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .success-icon {
            width: 60px; /* Smaller icon */
            height: 60px;
            margin: 0 auto 20px;
            background-color: #d4edda;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
        }

        .success-title {
            font-size: 24px; /* Smaller title */
            color: #28a745;
            margin: 10px 0 10px 0;
            font-weight: 600;
        }

        .success-message {
            font-size: 15px; /* Slightly smaller text */
            color: #666;
            margin: 10px 0 25px 0;
            line-height: 1.5;
        }

        .success-name {
            font-weight: 600;
            color: #333;
        }

        .success-button {
            background-color: #007bff;
            color: white;
            text-decoration: none;
            display: inline-block;
            border: none;
            padding: 10px 30px;
            border-radius: 4px;
            font-size: 15px;
            cursor: pointer;
            transition: box-shadow 0.15s, background-color 0.15s;
            font-weight: 500;
        }

        .success-button:hover {
            background-color: #0056b3;
            box-shadow: 0px 2.5px 8px rgba(0, 0, 0, 0.2);
        }
    </style>
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

<div class="success-container">
    <div class="success-card">
        <div class="success-icon">✓</div>
        <h2 class="success-title">Registered Successfully!</h2>
        <p class="success-message">
            Welcome, <span class="success-name"><?= htmlspecialchars($user_name) ?>!</span><br>
            Your account has been created successfully. You can now log in with your credentials.
        </p>
        <a href="index.php" class="success-button">Go to Login</a>
    </div>
</div>

</body>
</html>
