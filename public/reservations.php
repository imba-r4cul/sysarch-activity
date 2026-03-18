<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations - CCS</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css?v=20260319">
    <style>
        .reservations-container {
            background: #fff;
            border-radius: 8px;
            padding: 30px;
            max-width: 800px;
            margin: 40px auto;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .placeholder-box {
            border: 2px dashed #ccc;
            padding: 40px;
            text-align: center;
            color: #777;
            border-radius: 8px;
            margin-top: 20px;
        }
    </style>
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
        <div class="reservations-container">
            <h2>Your Lab Reservations</h2>
            <div class="placeholder-box">
                <p>You currently have no active reservations.</p>
                <button class="logout-btn" style="background: #28a745; margin-top:15px;">Make a Reservation</button>
            </div>
        </div>
    </main>

</body>

</html>