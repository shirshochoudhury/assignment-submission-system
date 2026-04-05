<?php
require_once 'includes/config.php';

// Rate limiting
session_start();
$ip = $_SERVER['REMOTE_ADDR'];
$attempt_key = "login_attempts_$ip";
if(!isset($_SESSION[$attempt_key])) $_SESSION[$attempt_key] = 0;
if($_SESSION[$attempt_key] >= 5) {
    die("Too many login attempts. Please try after 15 minutes.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $remember = isset($_POST['remember']);
    
    // Use password_hash instead of MD5 for production
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch();
    
    // Verify password (supports both MD5 and new hash)
    $password_valid = false;
    if($user) {
        if(strlen($user['password']) == 32) {
            $password_valid = ($user['password'] == md5($password));
            if($password_valid) {
                // Upgrade to new hash
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $upgrade = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upgrade->execute([$new_hash, $user['id']]);
            }
        } else {
            $password_valid = password_verify($password, $user['password']);
        }
    }
    
    if ($user && $password_valid) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        if($remember) {
            $token = bin2hex(random_bytes(32));
            setcookie('remember_token', $token, time() + 86400 * 30, '/');
            $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $stmt->execute([$token, $user['id']]);
        }
        
        // Log activity
        $log = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address, user_agent) VALUES (?, 'login', ?, ?)");
        $log->execute([$user['id'], $ip, $_SERVER['HTTP_USER_AGENT']]);
        
        $_SESSION[$attempt_key] = 0;
        
        if ($role == 'teacher') {
            header("Location: teacher/dashboard.php");
        } else {
            header("Location: student/dashboard.php");
        }
        exit();
    } else {
        $_SESSION[$attempt_key]++;
        $error = "Invalid credentials! Attempts: " . $_SESSION[$attempt_key] . "/5";
    }
}

// Check remember me cookie
if(!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->execute([$_COOKIE['remember_token']]);
    $user = $stmt->fetch();
    if($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        header("Location: " . ($user['role'] == 'teacher' ? 'teacher/dashboard.php' : 'student/dashboard.php'));
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>PlusWork - Smart Assignment Submission System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Poppins', sans-serif;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            width: 450px;
            backdrop-filter: blur(10px);
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }
        .login-header h3 { font-size: 32px; margin-bottom: 5px; font-weight: bold; }
        .login-header small { font-size: 14px; opacity: 0.9; }
        .login-body { padding: 40px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #333; }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn-login:hover { transform: translateY(-2px); }
        .feature-badge {
            display: inline-block;
            margin: 5px;
            padding: 5px 10px;
            background: #f0f0f0;
            border-radius: 20px;
            font-size: 12px;
        }
        .demo-credentials {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 10px;
            margin-top: 15px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="fas fa-graduation-cap fa-3x mb-3"></i>
                <h3 class="mb-0">📚 PlusWork</h3>
                <small>Smart Assignment Submission System</small>
            </div>
            <div class="login-body">
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" name="email" class="form-control" required placeholder="teacher@university.com">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="••••••••">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user-tag"></i> Role</label>
                        <select name="role" class="form-control" required>
                            <option value="student">🎓 Student Portal</option>
                            <option value="teacher">👨‍🏫 Faculty Portal</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-inline">
                            <input type="checkbox" name="remember"> Remember Me
                        </label>
                    </div>
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Login to PlusWork
                    </button>
                </form>
                
                <div class="demo-credentials">
                    <strong><i class="fas fa-key"></i> Demo Access:</strong><br>
                    <span class="badge bg-primary">Teacher</span> teacher@university.com / teacher123<br>
                    <span class="badge bg-success">Student</span> student@university.com / student123
                </div>
                
                <hr>
                <div class="text-center">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt"></i> Enterprise Security | 
                        <i class="fas fa-chart-line"></i> Real-time Analytics | 
                        <i class="fas fa-robot"></i> AI-Powered
                    </small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>