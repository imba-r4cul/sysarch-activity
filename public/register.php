<?php
session_start();
require_once '../config/database.php';

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
    <title>Register - CCS</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/x-icon" href="./images/ccs.png">
    <style>
        .signup-wrapper {
            min-height: calc(100vh - 66px);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .signup-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(24, 83, 154, 0.12), 0 1.5px 6px rgba(0, 0, 0, 0.04);
            width: 100%;
            max-width: 920px;
            overflow: hidden;
            display: flex;
            flex-direction: row;
        }

        .signup-sidebar {
            background: linear-gradient(160deg, #0b4b8f 0%, #18539a 40%, #2471c9 100%);
            color: #fff;
            padding: 48px 36px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-width: 260px;
            max-width: 300px;
            text-align: center;
            gap: 18px;
        }

        .signup-sidebar .sidebar-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }

        .signup-sidebar .sidebar-icon svg {
            width: 44px;
            height: 44px;
            fill: #fff;
        }

        .signup-sidebar h2 {
            margin: 0;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .signup-sidebar p {
            margin: 0;
            font-size: 15px;
            opacity: 0.85;
            line-height: 1.5;
        }

        .signup-sidebar .login-link-sidebar {
            margin-top: 16px;
            color: #fff;
            font-size: 14px;
        }

        .signup-sidebar .login-link-sidebar a {
            color: #ffd966;
            text-decoration: none;
            font-weight: 600;
        }

        .signup-sidebar .login-link-sidebar a:hover {
            text-decoration: underline;
        }

        .signup-form-area {
            flex: 1;
            padding: 40px 44px;
        }

        .signup-form-area h3 {
            margin: 0 0 6px;
            font-size: 22px;
            color: #1a1a2e;
        }

        .signup-form-area .subtitle {
            color: #777;
            font-size: 14px;
            margin-bottom: 24px;
        }

        .signup-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 22px;
        }

        .signup-grid .full-width {
            grid-column: span 2;
        }

        .field-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .field-group label {
            font-size: 13px;
            font-weight: 600;
            color: #444;
        }

        .field-group input,
        .field-group select {
            padding: 10px 14px;
            border: 1.5px solid #dde2ea;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
            background: #f8f9fb;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        .field-group input:focus,
        .field-group select:focus {
            border-color: #4a90d9;
            box-shadow: 0 0 0 3px rgba(74, 144, 217, 0.12);
            background: #fff;
        }

        .signup-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-top: 22px;
            gap: 14px;
        }

        .signup-submit-btn {
            background: linear-gradient(135deg, #0b4b8f, #2471c9);
            color: #fff;
            border: none;
            padding: 11px 38px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.2s;
            letter-spacing: 0.4px;
        }

        .signup-submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(11, 75, 143, 0.35);
        }

        .signup-error {
            background: #fff0f0;
            color: #c0392b;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 14px;
            margin-bottom: 16px;
            text-align: center;
        }

        @media (max-width: 800px) {
            .signup-card {
                flex-direction: column;
            }

            .signup-sidebar {
                max-width: 100%;
                min-width: 100%;
                padding: 28px 20px;
                flex-direction: row;
                gap: 16px;
            }

            .signup-sidebar .sidebar-icon {
                width: 52px;
                height: 52px;
                margin-bottom: 0;
            }

            .signup-sidebar .sidebar-icon svg {
                width: 28px;
                height: 28px;
            }

            .signup-form-area {
                padding: 28px 20px;
            }

            .signup-grid {
                grid-template-columns: 1fr;
            }

            .signup-grid .full-width {
                grid-column: span 1;
            }
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

    <div class="signup-wrapper">
        <div class="signup-card">
            <!-- Sidebar -->
            <div class="signup-sidebar">
                <div class="sidebar-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path
                            d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z" />
                    </svg>
                </div>
                <h2>Create Account</h2>
                <p>Join the CCS Sit-in Monitoring System and manage your lab sessions with ease.</p>
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