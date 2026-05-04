<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

redirectIfLoggedIn();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            header('Location: /mangima_resort/index.php');
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mangima Resort</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
        }

        .login-header {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            padding: 40px 30px 30px;
            text-align: center;
        }

        .login-header .resort-icon {
            font-size: 50px;
            margin-bottom: 10px;
        }

        .login-header h1 {
            font-size: 28px;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .login-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .login-body {
            padding: 40px 30px;
        }

        .error-message {
            background: #ffe6e6;
            color: #d63031;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #d63031;
            display: <?php echo $error ? 'block' : 'none'; ?>;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2d3436;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group .input-icon {
            position: relative;
        }

        .form-group .input-icon .icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #b2bec3;
            font-size: 18px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid #dfe6e9;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            outline: none;
        }

        .form-group input:focus {
            border-color: #2ecc71;
            box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(46, 204, 113, 0.3);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .demo-accounts {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            text-align: center;
        }

        .demo-accounts h3 {
            font-size: 13px;
            color: #636e72;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .demo-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .demo-btn {
            flex: 1;
            min-width: 100px;
            padding: 10px 15px;
            border: 2px solid #dfe6e9;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            color: #2d3436;
            transition: all 0.3s;
            text-align: center;
        }

        .demo-btn:hover {
            border-color: #2ecc71;
            background: #f0faf3;
            color: #27ae60;
        }

        .demo-btn .role {
            display: block;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .demo-btn .email {
            display: block;
            font-size: 10px;
            color: #636e72;
        }

        .footer-text {
            text-align: center;
            margin-top: 20px;
            color: #b2bec3;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="resort-icon">🏖️</div>
            <h1>Mangima Resort</h1>
            <p>Welcome back! Please login to continue</p>
        </div>

        <div class="login-body">
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>

            <form method="post" action="">
                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-icon">
                        <span class="icon">📧</span>
                        <input type="email" name="email" placeholder="Enter your email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-icon">
                        <span class="icon">🔒</span>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>

                <button type="submit" class="login-btn">Sign In</button>
            </form>

            <div class="demo-accounts">
                <h3>📋 Demo Accounts (Password: password)</h3>
                <div class="demo-buttons">
                    <button type="button" class="demo-btn" onclick="fillCredentials('admin@resort.com')">
                        <span class="role">Admin</span>
                        <span class="email">admin@resort.com</span>
                    </button>
                    <button type="button" class="demo-btn" onclick="fillCredentials('staff@resort.com')">
                        <span class="role">Staff</span>
                        <span class="email">staff@resort.com</span>
                    </button>
                    <button type="button" class="demo-btn" onclick="fillCredentials('user@resort.com')">
                        <span class="role">User</span>
                        <span class="email">user@resort.com</span>
                    </button>
                </div>
            </div>

            <p class="footer-text">© <?php echo date('Y'); ?> Mangima Resort Management System</p>
        </div>
    </div>

    <script>
        function fillCredentials(email) {
            document.querySelector('input[name="email"]').value = email;
            document.querySelector('input[name="password"]').value = 'password';
        }
    </script>
</body>
</html>