<?php
session_start();
require_once '../config/database.php';

// Redirect if already logged in as admin
if (isset($_SESSION['admin_id'])) {
    header('Location: admin_dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare('SELECT id, password, display_name FROM admin_users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($admin_id, $hashed_password, $display_name);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            $_SESSION['admin_id'] = $admin_id;
            $_SESSION['admin_name'] = $display_name;
            header('Location: admin_dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Invalid username or password.';
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - CCS</title>
    <link rel="icon" type="image/x-icon" href="./images/ccs.png">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .admin-login-wrapper {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #0b3d6e 0%, #18539a 50%, #1a6fd4 100%);
            padding: 20px;
        }

        .admin-login-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 420px;
            padding: 48px 40px;
            text-align: center;
        }

        .admin-login-card .admin-badge {
            width: 72px;
            height: 72px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #0b4b8f, #2471c9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 18px rgba(11, 75, 143, 0.3);
        }

        .admin-badge svg {
            width: 36px;
            height: 36px;
            fill: #fff;
        }

        .admin-login-card h2 {
            margin: 0 0 6px;
            font-size: 24px;
            color: #1a1a2e;
        }

        .admin-login-card .sub {
            color: #888;
            font-size: 14px;
            margin-bottom: 28px;
        }

        .admin-field {
            display: flex;
            flex-direction: column;
            text-align: left;
            margin-bottom: 16px;
            gap: 5px;
        }

        .admin-field label {
            font-size: 13px;
            font-weight: 600;
            color: #444;
        }

        .admin-field input {
            padding: 11px 14px;
            border: 1.5px solid #dde2ea;
            border-radius: 8px;
            font-size: 15px;
            color: #333;
            background: #f8f9fb;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        .admin-field input:focus {
            border-color: #4a90d9;
            box-shadow: 0 0 0 3px rgba(74, 144, 217, 0.12);
            background: #fff;
        }

        .admin-login-btn {
            width: 100%;
            background: linear-gradient(135deg, #0b4b8f, #2471c9);
            color: #fff;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: transform 0.15s, box-shadow 0.2s;
            letter-spacing: 0.3px;
        }

        .admin-login-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(11, 75, 143, 0.35);
        }

        .admin-error {
            background: #fff0f0;
            color: #c0392b;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 14px;
            margin-bottom: 16px;
        }

        .admin-back-link {
            margin-top: 20px;
            font-size: 13px;
            color: #888;
        }

        .admin-back-link a {
            color: #2471c9;
            text-decoration: none;
            font-weight: 600;
        }

        .admin-back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="admin-login-wrapper">
        <div class="admin-login-card">
            <div class="admin-badge">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 6c1.4 0 2.5 1.1 2.5 2.5S13.4 12 12 12s-2.5-1.1-2.5-2.5S10.6 7 12 7zm5 10H7v-1c0-1.67 3.33-2.5 5-2.5s5 .83 5 2.5v1z"/>
                </svg>
            </div>
            <h2>Admin Portal</h2>
            <p class="sub">CCS Sit-in Monitoring System</p>

            <?php if ($error): ?>
                <div class="admin-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="admin_login.php">
                <div class="admin-field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter admin username" required>
                </div>
                <div class="admin-field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter password" required>
                </div>
                <button type="submit" class="admin-login-btn">Sign In</button>
            </form>

            <div class="admin-back-link">
                <a href="index.php">← Back to Student Login</a>
            </div>
        </div>
    </div>
</body>

</html>
