<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkAuth();

if($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access Denied. Admins only.";
    header("Location: ../index.php");
    exit();
}

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $name = sanitize($conn, $_POST['name']);
    $username = sanitize($conn, $_POST['username']);
    $role = sanitize($conn, $_POST['role']);
    $branch_id = (int)$_POST['branch_id'];
    $password = $_POST['password'];

    $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
    if(mysqli_num_rows($check) > 0) {
        $error = "Username is already in use by another user.";
    } elseif(strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $sql = "INSERT INTO users (name, username, password, role, branch_id) VALUES ('$name', '$username', '$hash', '$role', $branch_id)";
        
        if(mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "New staff user added successfully.";
            header("Location: index.php");
            exit();
        } else {
            $error = "Database Error: " . mysqli_error($conn);
        }
    }
}

$branches = mysqli_query($conn, "SELECT id, branch_name FROM branches ORDER BY branch_name ASC");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-3xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-user-plus text-indigo-500"></i> Register New Staff
            </h1>
            <p class="text-gray-500 text-sm mt-1">Add a new admin or branch staff to the system.</p>
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
                <input type="text" name="name" required value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Login Username <span class="text-red-500">*</span></label>
                <input type="text" name="username" required value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Assigned Branch <span class="text-red-500">*</span></label>
                <select name="branch_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                    <?php while($b = mysqli_fetch_assoc($branches)): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Staff will be linked to this regional operational hub.</p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">System Role <span class="text-red-500">*</span></label>
                <select name="role" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                    <option value="staff" <?= (!isset($_POST['role']) || $_POST['role'] == 'staff') ? 'selected' : '' ?>>Branch Staff (Standard Access)</option>
                    <option value="admin" <?= isset($_POST['role']) && $_POST['role'] == 'admin' ? 'selected' : '' ?>>Administrator (Full Access)</option>
                    <option value="advisor" <?= isset($_POST['role']) && $_POST['role'] == 'advisor' ? 'selected' : '' ?>>Field Advisor / Staff (Wallet Access)</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Temporary Password <span class="text-red-500">*</span></label>
                <input type="password" name="password" required minlength="6" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                <p class="text-xs text-gray-500 mt-1">Supply this password to the new user securely.</p>
            </div>
        </div>

        <div class="pt-6 border-t border-gray-100 flex justify-end">
            <button type="submit" name="add_user" class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-2.5 rounded-lg font-medium shadow-sm transition-all flex items-center gap-2">
                <i class="ph ph-check-circle text-xl"></i> Create Account
            </button>
        </div>

    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
