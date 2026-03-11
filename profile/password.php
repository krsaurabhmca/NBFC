<?php
require_once '../includes/db.php';
checkAuth();

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $user_id = $_SESSION['user_id'];
    
    $sql = "SELECT password FROM users WHERE id = $user_id";
    $result = mysqli_query($conn, $sql);
    $user = mysqli_fetch_assoc($result);

    if(!password_verify($current_password, $user['password'])) {
        $error = "The current password you entered is incorrect.";
    } elseif($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif(strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } else {
        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $update = "UPDATE users SET password = '$new_hash' WHERE id = $user_id";
        if(mysqli_query($conn, $update)) {
            $success = "Password changed successfully.";
        } else {
            $error = "Failed to update password. " . mysqli_error($conn);
        }
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-xl mx-auto">
    <div class="mb-6 text-center">
        <div class="w-16 h-16 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl font-bold">
            <i class="ph ph-lock-key"></i>
        </div>
        <h1 class="text-2xl font-bold text-gray-800">Change Password</h1>
        <p class="text-gray-500 text-sm mt-1">Ensure your account is using a long, secure password.</p>
    </div>

    <?php if($error): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-lg mb-6 flex items-start gap-3 border border-red-100"><i class="ph ph-warning-circle text-xl mt-0.5"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="bg-emerald-50 text-emerald-600 p-4 rounded-lg mb-6 flex items-start gap-3 border border-emerald-100"><i class="ph ph-check-circle text-xl mt-0.5"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 space-y-5">
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Current Password <span class="text-red-500">*</span></label>
            <input type="password" name="current_password" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
        </div>

        <div class="border-t border-gray-100 pt-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">New Password <span class="text-red-500">*</span></label>
            <input type="password" name="new_password" required minlength="6" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            <p class="text-xs text-gray-500 mt-1">Minimum 6 characters required.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password <span class="text-red-500">*</span></label>
            <input type="password" name="confirm_password" required minlength="6" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
        </div>

        <div class="pt-4">
            <button type="submit" name="change_password" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-lg font-medium transition-colors shadow-sm flex items-center justify-center gap-2">
                <i class="ph ph-shield-check text-xl"></i> Update Password
            </button>
        </div>

    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
