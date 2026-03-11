<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkAuth();

if($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access Denied. Admins only.";
    header("Location: ../index.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_check = mysqli_query($conn, "SELECT * FROM users WHERE id = $id");
$user = mysqli_fetch_assoc($user_check);

if(!$user) {
    $_SESSION['error'] = "User not found.";
    header("Location: index.php");
    exit();
}

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    $name = sanitize($conn, $_POST['name']);
    $username = sanitize($conn, $_POST['username']);
    $role = sanitize($conn, $_POST['role']);
    $password = $_POST['password'];

    $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username' AND id != $id");
    if(mysqli_num_rows($check) > 0) {
        $error = "Username is already in use by another user.";
    } else {
        $sql = "UPDATE users SET name = '$name', username = '$username', role = '$role' WHERE id = $id";
        
        if(!empty($password)) {
            if(strlen($password) < 6) {
                $error = "New Password must be at least 6 characters long.";
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $sql = "UPDATE users SET name = '$name', username = '$username', password = '$hash', role = '$role' WHERE id = $id";
            }
        }
        
        if(!$error) {
            if(mysqli_query($conn, $sql)) {
                // If the admin edits their own account, update session Name
                if($id == $_SESSION['user_id']) {
                    $_SESSION['name'] = $name;
                }
                $_SESSION['success'] = "User profile updated successfully.";
                header("Location: index.php");
                exit();
            } else {
                $error = "Database Error: " . mysqli_error($conn);
            }
        }
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-3xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-user-gear text-indigo-500"></i> Edit Staff Profile
            </h1>
            <p class="text-gray-500 text-sm mt-1">Update details or reset password for <?= htmlspecialchars($user['name']) ?>.</p>
        </div>
        <a href="index.php" class="text-gray-600 hover:text-gray-900 border border-gray-300 hover:bg-gray-50 px-4 py-2 rounded-lg transition text-sm font-medium">
            &larr; Back to List
        </a>
    </div>

    <?php if($error): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-lg mb-6 flex items-start gap-3 border border-red-100"><i class="ph ph-warning-circle text-xl mt-0.5"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 space-y-6">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" required value="<?= htmlspecialchars($user['name']) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none flex-1">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Login Username <span class="text-red-500">*</span></label>
                <input type="text" name="username" required value="<?= htmlspecialchars($user['username']) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none flex-1">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">System Role <span class="text-red-500">*</span></label>
                <select name="role" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none flex-1" <?= $user['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                    <option value="staff" <?= $user['role'] == 'staff' ? 'selected' : '' ?>>Branch Staff (Standard Access)</option>
                    <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Administrator (Full Access)</option>
                </select>
                <?php if($user['id'] == $_SESSION['user_id']): ?>
                    <input type="hidden" name="role" value="<?= $user['role'] ?>">
                    <p class="text-xs text-red-500 mt-1">You cannot change your own role.</p>
                <?php endif; ?>
            </div>
            
            <div class="bg-orange-50 p-4 rounded-xl border border-orange-100">
                <label class="block text-sm font-medium text-orange-800 mb-1">Reset Password (Optional)</label>
                <input type="password" name="password" placeholder="Leave blank to keep current" class="w-full px-4 py-2 border border-orange-200 rounded-lg focus:ring-2 focus:ring-orange-500 outline-none flex-1">
                <p class="text-xs text-orange-600 mt-1">Only fill this if you want to override their password.</p>
            </div>
        </div>

        <div class="pt-6 border-t border-gray-100 flex justify-end">
            <button type="submit" name="edit_user" class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-2.5 rounded-lg font-medium shadow-sm transition-all flex items-center gap-2">
                <i class="ph ph-floppy-disk text-xl"></i> Save Changes
            </button>
        </div>

    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
