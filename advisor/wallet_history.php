<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkAuth();

// Only Advisor or Admin can access
if(!in_array($_SESSION['role'], ['advisor', 'admin'])) {
    $_SESSION['error'] = "Access Denied.";
    header("Location: ../index.php");
    exit();
}

$advisor_id = $_SESSION['user_id'];

// Get wallet balance
$advisor_res = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $advisor_id");
$advisor_data = mysqli_fetch_assoc($advisor_res);
$wallet_balance = $advisor_data['wallet_balance'];

// Get wallet transactions
$sql = "SELECT * FROM wallet_transactions WHERE user_id = $advisor_id ORDER BY id DESC";
$transactions = mysqli_query($conn, $sql);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-wallet-fill text-indigo-500 text-3xl"></i> Wallet History / Ledger
            </h1>
            <p class="text-gray-500 text-sm mt-1">Review all your wallet recharges and collection deductions.</p>
        </div>
        <div class="bg-white px-6 py-3 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-3">
            <span class="text-gray-500 text-sm font-medium">Available Balance</span>
            <span class="text-2xl font-bold text-indigo-600 tracking-tight"><?= formatCurrency($wallet_balance) ?></span>
        </div>
    </div>

    <?= displayAlert() ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-gray-50 text-gray-600 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-4 font-semibold uppercase tracking-wider text-[11px]">Date & Time</th>
                        <th class="px-6 py-4 font-semibold uppercase tracking-wider text-[11px]">Type</th>
                        <th class="px-6 py-4 font-semibold uppercase tracking-wider text-[11px]">Ref / TXN ID</th>
                        <th class="px-6 py-4 font-semibold uppercase tracking-wider text-[11px]">Description</th>
                        <th class="px-6 py-4 font-semibold uppercase tracking-wider text-[11px] text-right">Amount</th>
                        <th class="px-6 py-4 font-semibold uppercase tracking-wider text-[11px] text-right">Balance After</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php while($t = mysqli_fetch_assoc($transactions)): ?>
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4 text-gray-600 font-medium">
                            <span class="block text-gray-800"><?= date('d M Y', strtotime($t['transaction_date'])) ?></span>
                            <span class="text-[10px] text-gray-400"><?= date('H:i A', strtotime($t['transaction_date'])) ?></span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded uppercase tracking-wider <?= $t['transaction_type'] == 'Recharge' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' ?>">
                                <?= $t['transaction_type'] ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-600 font-mono text-xs">
                            <?= $t['reference_id'] ?: '---' ?>
                        </td>
                        <td class="px-6 py-4 text-gray-700">
                            <?= htmlspecialchars($t['description']) ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="font-bold text-lg tracking-tight <?= $t['amount'] > 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                                <?= $t['amount'] > 0 ? '+' : '' ?><?= formatCurrency($t['amount']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="font-bold text-gray-800"><?= formatCurrency($t['balance_after']) ?></span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if(mysqli_num_rows($transactions) == 0): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                            <i class="ph ph-receipt-x text-5xl mb-4 block"></i>
                            No wallet transactions found.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
