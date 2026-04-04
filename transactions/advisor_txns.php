<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkAuth();

if($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access Denied. Admins only.";
    header("Location: ../index.php");
    exit();
}

// Filter by Advisor if requested
$advisor_id = isset($_GET['advisor_id']) ? (int)$_GET['advisor_id'] : 0;
$where = "";
if($advisor_id > 0) {
    $where = "WHERE wt.user_id = $advisor_id";
}

// Fetch Advisor Wallet Transactions
$sql = "SELECT wt.*, u.name as advisor_name 
        FROM wallet_transactions wt 
        JOIN users u ON wt.user_id = u.id 
        $where 
        ORDER BY wt.id DESC";
$transactions = mysqli_query($conn, $sql);

// Fetch Advisors for filter
$advisors_res = mysqli_query($conn, "SELECT id, name FROM users WHERE role = 'advisor'");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-receipt text-indigo-500 text-3xl"></i> Advisor Wallet Transactions
            </h1>
            <p class="text-gray-500 text-sm mt-1">Global audit log of all advisor wallet recharges and collections.</p>
        </div>
        <form action="" method="GET" class="flex items-center gap-3">
            <select name="advisor_id" class="px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                <option value="">-- All Advisors --</option>
                <?php while($adv = mysqli_fetch_assoc($advisors_res)): ?>
                    <option value="<?= $adv['id'] ?>" <?= $advisor_id == $adv['id'] ? 'selected' : '' ?>><?= htmlspecialchars($adv['name']) ?></option>
                <?php endwhile; ?>
            </select>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium shadow-sm transition-all text-sm">Filter</button>
        </form>
    </div>

    <?= displayAlert() ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-gray-50 text-gray-600 border-b border-gray-100 uppercase tracking-widest text-[11px]">
                    <tr>
                        <th class="px-6 py-4 font-bold">Time & Date</th>
                        <th class="px-6 py-4 font-bold">Advisor</th>
                        <th class="px-6 py-4 font-bold">Type</th>
                        <th class="px-6 py-4 font-bold">Ref ID</th>
                        <th class="px-6 py-4 font-bold">Description</th>
                        <th class="px-6 py-4 font-bold text-right">Amount</th>
                        <th class="px-6 py-4 font-bold text-right">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php while($t = mysqli_fetch_assoc($transactions)): ?>
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <span class="block text-gray-800 font-semibold"><?= date('d M Y', strtotime($t['transaction_date'])) ?></span>
                            <span class="text-[10px] text-gray-400 font-mono"><?= date('H:i A', strtotime($t['transaction_date'])) ?></span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <span class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-xs">
                                    <?= strtoupper(substr($t['advisor_name'], 0, 1)) ?>
                                </span>
                                <span class="font-medium text-gray-800"><?= htmlspecialchars($t['advisor_name']) ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?php
                                $badge_class = 'bg-rose-100 text-rose-700';
                                if($t['transaction_type'] == 'Recharge' || $t['transaction_type'] == 'Interest') $badge_class = 'bg-emerald-100 text-emerald-700';
                                if($t['transaction_type'] == 'Interest') $badge_class = 'bg-indigo-100 text-indigo-700';
                            ?>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded uppercase tracking-wider <?= $badge_class ?>">
                                <?= strtoupper($t['transaction_type']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs font-mono text-gray-500">
                            <?= $t['reference_id'] ?: '---' ?>
                        </td>
                        <td class="px-6 py-4 text-gray-700">
                            <?= htmlspecialchars($t['description']) ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                             <span class="font-bold text-lg <?= $t['amount'] > 0 ? 'text-emerald-600' : 'text-rose-500' ?>">
                                <?= $t['amount'] > 0 ? '+' : '' ?><?= formatCurrency($t['amount']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right font-bold text-gray-900">
                            <?= formatCurrency($t['balance_after']) ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if(mysqli_num_rows($transactions) == 0): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-400 border-dashed border-2 border-gray-50">
                            <i class="ph ph-receipt-x text-5xl mb-2 flex justify-center"></i>
                            <p>No transactions found for the selected criteria.</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
