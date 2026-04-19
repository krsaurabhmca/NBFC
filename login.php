<?php
if(session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if(isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'includes/db.php';
require_once 'includes/functions.php';

$bank_name = getSetting($conn, 'bank_name') ?: 'NBFC Core';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = trim($_POST['password']);
    
    if(empty($username) || empty($password)) {
        $error = "Please enter username and password.";
    } else {
        $sql = "SELECT * FROM users WHERE username = '$username' AND status = 'active'";
        $result = mysqli_query($conn, $sql);
        
        if($row = mysqli_fetch_assoc($result)) {
            if(password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['name'] = $row['name'];
                $_SESSION['branch_id'] = $row['branch_id'];
                logAction($conn, $_SESSION['user_id'], 'Login Success', 'User signed into the system.');
                header("Location: index.php");
                exit();
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "Invalid username or inactive account.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($bank_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-slate-900 p-8 text-center">
            <h1 class="text-2xl font-bold tracking-wider text-white mb-2 flex items-center justify-center gap-2 uppercase">
                <i class="ph ph-bank text-indigo-400"></i> <?= htmlspecialchars($bank_name) ?>
            </h1>
            <p class="text-slate-400 text-sm">Sign in to manage banking operations</p>
        </div>
        
        <div class="p-8">
            <?php if($error): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-lg text-sm mb-6 flex items-start gap-3 border border-red-100">
                    <i class="ph ph-warning-circle text-lg mt-0.5"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <div class="relative">
                            <i class="ph ph-user absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg"></i>
                            <input type="text" name="username" class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors" placeholder="Enter username" required value="admin">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <div class="relative">
                            <i class="ph ph-lock-key absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg"></i>
                            <input type="password" name="password" class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors" placeholder="Enter password" required value="admin123">
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" class="rounded text-indigo-600 border-gray-300 focus:ring-indigo-500">
                            <span class="text-gray-600">Remember me</span>
                        </label>
                    </div>

                    <button type="submit" name="login" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg transition-colors shadow-sm shadow-indigo-200">
                        Sign In
                    </button>
                    
                    <p class="text-xs text-center text-gray-500 mt-4">Demo Credentials: admin / admin123</p>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
