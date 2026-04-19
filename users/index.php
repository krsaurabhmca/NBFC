<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkAuth();

if($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access Denied. Admins only.";
    header("Location: ../index.php");
    exit();
}

// Handle User Deletion/Status toggle if needed
if(isset($_GET['delete']) && $_GET['delete'] != $_SESSION['user_id']) {
    $del_id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM users WHERE id = $del_id");
    $_SESSION['success'] = "User removed successfully.";
    header("Location: index.php");
    exit();
}

$sql = "SELECT * FROM users ORDER BY id DESC";
$users = mysqli_query($conn, $sql);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-shield-check text-indigo-500 text-3xl"></i> Staff & Users Management
            </h1>
            <p class="text-gray-500 text-sm mt-1">Manage system administrators and branch staff.</p>
        </div>
        <a href="add.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-medium shadow-sm transition-all flex items-center gap-2">
            <i class="ph ph-plus-circle text-lg"></i> Add New Staff
        </a>
    </div>

    <?= displayAlert() ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-gray-50 text-gray-600 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-4 font-semibold">User</th>
                        <th class="px-6 py-4 font-semibold">Email / Login</th>
                        <th class="px-6 py-4 font-semibold">System Role</th>
                        <th class="px-6 py-4 font-semibold">Created On</th>
                        <th class="px-6 py-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php while($u = mysqli_fetch_assoc($users)): ?>
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold">
                                    <?= substr($u['name'], 0, 1) ?>
                                </div>
                                <div class="font-semibold text-gray-800"><?= htmlspecialchars($u['name']) ?></div>
                                <?php if($u['role'] == 'advisor'): ?>
                                    <div class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded flex items-center gap-1 mt-0.5">
                                        <i class="ph ph-wallet-fill"></i> <?= formatCurrency($u['wallet_balance']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-gray-600">
                            <?= htmlspecialchars($u['username']) ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 text-xs font-semibold rounded-full <?= $u['role'] == 'admin' ? 'bg-purple-100 text-purple-700' : ($u['role'] == 'advisor' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700') ?>">
                                <?= strtoupper($u['role']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-500">
                            <?= date('d M Y', strtotime($u['created_at'])) ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                <a href="impersonate.php?id=<?= $u['id'] ?>" class="text-amber-600 hover:text-amber-900 bg-amber-50 hover:bg-amber-100 p-2 rounded inline-block transition" title="Login As This User">
                                    <i class="ph ph-user-switch text-lg"></i>
                                </a>
                                <?php endif; ?>

                                <?php if($u['role'] == 'advisor'): ?>
                                <a href="recharge_wallet.php?id=<?= $u['id'] ?>" class="text-emerald-600 hover:text-emerald-900 bg-emerald-50 hover:bg-emerald-100 p-2 rounded inline-block transition" title="Recharge Wallet">
                                    <i class="ph ph-wallet text-lg"></i>
                                </a>
                                <?php endif; ?>
                                <a href="edit.php?id=<?= $u['id'] ?>" class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 p-2 rounded inline-block transition">
                                    <i class="ph ph-pencil-simple text-lg"></i>
                                </a>
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                <a href="index.php?delete=<?= $u['id'] ?>" onclick="return confirm('Are you sure you want to remove this staff access?')" class="text-rose-600 hover:text-rose-900 bg-rose-50 hover:bg-rose-100 p-2 rounded inline-block transition">
                                    <i class="ph ph-trash text-lg"></i>
                                </a>
                                <?php else: ?>
                                <button disabled class="text-gray-400 bg-gray-50 p-2 rounded inline-block cursor-not-allowed" title="Cannot delete yourself">
                                    <i class="ph ph-trash text-lg"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if(mysqli_num_rows($users) == 0): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">No users found in the system.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
