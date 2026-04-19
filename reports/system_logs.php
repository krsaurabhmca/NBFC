<?php
// reports/system_logs.php
require_once '../includes/db.php';
checkAuth();

// Admin only access
if($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Unauthorized Access.";
    header("Location: " . APP_URL . "index.php");
    exit();
}

// Ensure table exists
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `system_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `action` varchar(100) NOT NULL,
    `details` text,
    `ip_address` varchar(45) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Filter handling
$where = "WHERE 1=1";
if(!empty($_GET['user_id'])) {
    $uid = (int)$_GET['user_id'];
    $where .= " AND l.user_id = $uid";
}
if(!empty($_GET['action'])) {
    $act = sanitize($conn, $_GET['action']);
    $where .= " AND l.action LIKE '%$act%'";
}

$sql = "SELECT l.*, u.name as user_name 
        FROM system_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        $where 
        ORDER BY l.id DESC LIMIT 200";
$logs = mysqli_query($conn, $sql);

// Fetch users for filter
$users_res = mysqli_query($conn, "SELECT id, name FROM users ORDER BY name ASC");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">System Usage & Audit Logs</h1>
    <p class="text-gray-500 text-sm">Monitor all administrative and transactional activities across the platform.</p>
</div>

<!-- Filters -->
<div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Filter by Staff</label>
            <select name="user_id" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                <option value="">All Users</option>
                <?php while($u = mysqli_fetch_assoc($users_res)): ?>
                    <option value="<?= $u['id'] ?>" <?= isset($_GET['user_id']) && $_GET['user_id'] == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Filter by Action</label>
            <input type="text" name="action" value="<?= htmlspecialchars($_GET['action'] ?? '') ?>" placeholder="Search action..." class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <div class="md:col-span-2 flex items-end gap-2">
            <button type="submit" class="bg-slate-900 text-white px-6 py-2 rounded-xl text-sm font-bold hover:bg-slate-800 transition-colors">Apply Filters</button>
            <a href="system_logs.php" class="bg-gray-100 text-gray-600 px-6 py-2 rounded-xl text-sm font-bold hover:bg-gray-200 transition-colors">Reset</a>
        </div>
    </form>
</div>

<!-- Logs Table -->
<div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50/50 text-gray-500 text-[10px] font-black uppercase tracking-[0.2em]">
                    <th class="px-6 py-4">Timestamp</th>
                    <th class="px-6 py-4">User</th>
                    <th class="px-6 py-4">Activity</th>
                    <th class="px-6 py-4">Details</th>
                    <th class="px-6 py-4">IP Address</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-[13px]">
                <?php if(mysqli_num_rows($logs) > 0): ?>
                    <?php while($log = mysqli_fetch_assoc($logs)): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4 text-gray-400 font-mono whitespace-nowrap">
                                <?= date('d M, h:i A', strtotime($log['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 font-bold text-gray-700">
                                <?= htmlspecialchars($log['user_name']) ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php
                                    $color = 'indigo';
                                    if(strpos($log['action'], 'Login') !== false) $color = 'emerald';
                                    if(strpos($log['action'], 'Logout') !== false) $color = 'gray';
                                    if(strpos($log['action'], 'Transaction') !== false) $color = 'rose';
                                ?>
                                <span class="bg-<?= $color ?>-50 text-<?= $color ?>-600 px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-tighter">
                                    <?= htmlspecialchars($log['action']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-600 italic">
                                <?= htmlspecialchars($log['details']) ?>
                            </td>
                            <td class="px-6 py-4 text-gray-400 text-[10px] font-mono">
                                <?= $log['ip_address'] ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-2 opacity-30">
                                <i class="ph ph-mask-happy text-6xl"></i>
                                <p class="text-sm font-bold uppercase tracking-widest">No activities recorded yet.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
