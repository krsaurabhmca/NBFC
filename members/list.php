<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle pagination and search
$search = isset($_GET['search']) ? sanitize($conn, $_GET['search']) : '';
$where = "WHERE 1=1";
if($search) {
    $where .= " AND (member_no LIKE '%$search%' OR first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR aadhar_no LIKE '%$search%' OR phone LIKE '%$search%')";
}

$sql = "SELECT m.*, 
        (SELECT COUNT(*) FROM accounts WHERE member_id = m.id AND status != 'closed') as active_accounts 
        FROM members m $where ORDER BY id DESC";
$result = mysqli_query($conn, $sql);
?>

<div class="max-w-6xl mx-auto">
    
    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Member Directory</h1>
            <p class="text-gray-500 text-sm mt-1">Manage and view all registered customers</p>
        </div>
        
        <div class="flex items-center gap-3">
            <form action="" method="GET" class="relative">
                <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by Name/Aadhar/No..." class="w-64 pl-10 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                <?php if($search): ?>
                    <a href="list.php" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-rose-500"><i class="ph ph-x"></i></a>
                <?php endif; ?>
            </form>
            
            <a href="add.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm flex items-center gap-2">
                <i class="ph ph-plus"></i> New Member
            </a>
        </div>
    </div>

    <?= displayAlert() ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/80 text-gray-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-4 font-medium">Member ID</th>
                        <th class="px-6 py-4 font-medium">Customer Details</th>
                        <th class="px-6 py-4 font-medium">Contact</th>
                        <th class="px-6 py-4 font-medium">KYC Status</th>
                        <th class="px-6 py-4 font-medium text-center">Accounts</th>
                        <th class="px-6 py-4 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <span class="font-semibold text-gray-800 bg-gray-100 px-2.5 py-1 rounded-md text-xs"><?= htmlspecialchars($row['member_no']) ?></span>
                                    <div class="text-xs text-gray-400 mt-1">Joined: <?= date('M Y', strtotime($row['created_at'])) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-800 flex items-center gap-2">
                                        <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>
                                        <?php if($row['status'] == 'active'): ?>
                                            <div class="w-2 h-2 rounded-full bg-emerald-500" title="Active"></div>
                                        <?php else: ?>
                                            <div class="w-2 h-2 rounded-full bg-rose-500" title="<?= htmlspecialchars($row['status']) ?>"></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-0.5">DOB: <?= date('d/m/Y', strtotime($row['dob'])) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-gray-700"><i class="ph ph-phone text-gray-400 mr-1"></i> <?= htmlspecialchars($row['phone']) ?></div>
                                    <div class="text-xs text-gray-500 mt-0.5 truncate max-w-[150px]" title="<?= htmlspecialchars($row['address']) ?>">
                                        <?= htmlspecialchars($substr = substr($row['address'], 0, 25)) . (strlen($row['address']) > 25 ? '...' : '') ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs font-mono text-gray-600 bg-gray-100 px-2 py-1 rounded w-max">Aadhar Verified</div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if($row['active_accounts'] > 0): ?>
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 font-bold border border-indigo-100">
                                            <?= $row['active_accounts'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="ledger.php?id=<?= $row['id'] ?>" class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="View Ledger and Accounts">
                                            <i class="ph ph-file-text text-xl"></i>
                                        </a>
                                        <a href="../accounts/open.php?member_id=<?= $row['id'] ?>" class="p-2 text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" title="Open New Account">
                                            <i class="ph ph-folder-plus text-xl"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <i class="ph ph-users-three text-4xl text-gray-300 mb-3 block"></i>
                            No members found.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination controls could go here -->
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
