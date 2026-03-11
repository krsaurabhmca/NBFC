<?php
// index.php Dashboard
require_once 'includes/db.php';
checkAuth();

require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Fetch summary stats
$stats = [
    'total_members' => 0,
    'total_deposits' => 0,
    'total_loans' => 0,
    'active_accounts' => 0
];

// Members
$res = mysqli_query($conn, "SELECT COUNT(*) as c FROM members WHERE status = 'active'");
$stats['total_members'] = mysqli_fetch_assoc($res)['c'];

// Active Accounts
$res = mysqli_query($conn, "SELECT COUNT(*) as c FROM accounts WHERE status = 'active'");
$stats['active_accounts'] = mysqli_fetch_assoc($res)['c'];

// Deposits (Sum of balance for Savings, FD, RD, MIS, DD)
$res = mysqli_query($conn, "SELECT SUM(current_balance) as s FROM accounts WHERE account_type != 'Loan' AND status = 'active'");
$stats['total_deposits'] = mysqli_fetch_assoc($res)['s'] ?? 0;

// Loans Disbursed (Sum of principal amount for Loans)
$res = mysqli_query($conn, "SELECT SUM(principal_amount) as s FROM accounts WHERE account_type = 'Loan' AND status IN ('active','defaulted')");
$stats['total_loans'] = mysqli_fetch_assoc($res)['s'] ?? 0;

// Recent Transactions
$txn_sql = "SELECT t.*, a.account_no, m.first_name, m.last_name 
            FROM transactions t 
            JOIN accounts a ON t.account_id = a.id 
            JOIN members m ON a.member_id = m.id 
            ORDER BY t.id DESC LIMIT 5";
$recent_txns = mysqli_query($conn, $txn_sql);

// Current Date Totals (Debt/Asset)
$today = date('Y-m-d');
$today_deposits = 0; // Asset/Cash In (Deposits, EMIs paid)
$today_debts = 0; // Debt/Cash Out (Withdrawals, Loan Disbursals)

$res_today = mysqli_query($conn, "SELECT transaction_type, SUM(amount) as s FROM transactions WHERE DATE(transaction_date) = '$today' GROUP BY transaction_type");
while($rt = mysqli_fetch_assoc($res_today)) {
    if(in_array($rt['transaction_type'], ['Deposit', 'EMI'])) {
        $today_deposits += $rt['s'];
    } elseif(in_array($rt['transaction_type'], ['Withdrawal', 'Account-Open'])) { // Assuming Account-Open for Loan is outflow (handled generically here, but conceptually correct for simple model)
        $today_debts += $rt['s']; 
    }
}

// Adjust account-open specifically for loans
$res_loan_out = mysqli_query($conn, "SELECT SUM(t.amount) as s FROM transactions t JOIN accounts a ON t.account_id = a.id WHERE a.account_type = 'Loan' AND t.transaction_type = 'Account-Open' AND DATE(t.transaction_date) = '$today'");
$loan_out = mysqli_fetch_assoc($res_loan_out)['s'] ?? 0;
$today_debts = $loan_out + (mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as s FROM transactions WHERE transaction_type = 'Withdrawal' AND DATE(transaction_date) = '$today'"))['s'] ?? 0);

?>

<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
    <!-- Stat Cards (Compact) -->
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-200">
        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1 flex items-center gap-1"><i class="ph ph-users text-blue-500"></i> Members</p>
        <h3 class="text-xl font-bold text-gray-800 tracking-tight"><?= number_format($stats['total_members']) ?></h3>
    </div>
    
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-200">
        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1 flex items-center gap-1"><i class="ph ph-folders text-emerald-500"></i> Active A/cs</p>
        <h3 class="text-xl font-bold text-gray-800 tracking-tight"><?= number_format($stats['active_accounts']) ?></h3>
    </div>

    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-200 col-span-2 md:col-span-2">
        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1 flex items-center gap-1"><i class="ph ph-arrow-down-left text-indigo-500"></i> System Deposits</p>
        <h3 class="text-xl font-bold text-gray-800 tracking-tight"><?= formatCurrency($stats['total_deposits']) ?></h3>
    </div>

    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-200 col-span-2 md:col-span-2">
        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-1 flex items-center gap-1"><i class="ph ph-arrow-up-right text-rose-500"></i> Loans Disbursed</p>
        <h3 class="text-xl font-bold text-gray-800 tracking-tight"><?= formatCurrency($stats['total_loans']) ?></h3>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div class="bg-gradient-to-r from-emerald-600 to-emerald-500 rounded-xl p-5 text-white shadow flex justify-between items-center bg-opacity-90">
        <div>
            <p class="text-emerald-100 text-xs font-semibold uppercase tracking-widest mb-1 flex items-center gap-1.5"><div class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></div> Today's Inward Returns</p>
            <h3 class="text-2xl font-bold tracking-tight"><?= formatCurrency($today_deposits) ?></h3>
        </div>
        <i class="ph ph-trend-up text-4xl opacity-50"></i>
    </div>
    
    <div class="bg-gradient-to-r from-rose-600 to-rose-500 rounded-xl p-5 text-white shadow flex justify-between items-center bg-opacity-90">
        <div>
            <p class="text-rose-100 text-xs font-semibold uppercase tracking-widest mb-1 flex items-center gap-1.5"><div class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></div> Today's Outward Debits</p>
            <h3 class="text-2xl font-bold tracking-tight"><?= formatCurrency($today_debts) ?></h3>
        </div>
        <i class="ph ph-trend-down text-4xl opacity-50"></i>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Content Left (Recent Txns) -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-lg">Recent Transactions</h3>
            <a href="transactions/process.php" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">View All <i class="ph ph-arrow-right"></i></a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 text-gray-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-4 font-medium">Transaction ID</th>
                        <th class="px-6 py-4 font-medium">Member & A/C</th>
                        <th class="px-6 py-4 font-medium">Type</th>
                        <th class="px-6 py-4 font-medium">Amount</th>
                        <th class="px-6 py-4 font-medium text-right">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <?php if(mysqli_num_rows($recent_txns) > 0): ?>
                        <?php while($txn = mysqli_fetch_assoc($recent_txns)): ?>
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4 font-medium text-gray-700"><?= htmlspecialchars($txn['transaction_id']) ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-800"><?= htmlspecialchars($txn['first_name'] . ' ' . $txn['last_name']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($txn['account_no']) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                        $color = 'gray';
                                        if($txn['transaction_type'] == 'Deposit') $color = 'emerald';
                                        if($txn['transaction_type'] == 'Withdrawal' || $txn['transaction_type'] == 'Loan') $color = 'rose';
                                        if($txn['transaction_type'] == 'Interest') $color = 'blue';
                                    ?>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-<?= $color ?>-100 text-<?= $color ?>-700">
                                        <?= htmlspecialchars($txn['transaction_type']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-medium <?= in_array($txn['transaction_type'], ['Deposit','Interest']) ? 'text-emerald-600' : 'text-rose-600' ?>">
                                    <?= in_array($txn['transaction_type'], ['Deposit','Interest']) ? '+' : '-' ?><?= formatCurrency($txn['amount']) ?>
                                </td>
                                <td class="px-6 py-4 text-right text-gray-500">
                                    <?= date('d M Y, h:i A', strtotime($txn['transaction_date'])) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No recent transactions found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Sidebar (Quick Actions) -->
    <div class="space-y-6">
        <div class="bg-indigo-600 rounded-2xl p-6 text-white shadow-lg shadow-indigo-200">
            <h3 class="font-semibold text-lg mb-2">Quick Actions</h3>
            <p class="text-indigo-100 text-sm mb-6">Perform frequent banking operations instantly.</p>
            <div class="grid grid-cols-2 gap-3">
                <a href="members/add.php" class="bg-indigo-500 hover:bg-indigo-400 p-3 rounded-xl transition-colors text-center text-sm font-medium">
                    <i class="ph ph-user-plus text-2xl mb-1 block"></i> Add Member
                </a>
                <a href="accounts/open.php" class="bg-indigo-500 hover:bg-indigo-400 p-3 rounded-xl transition-colors text-center text-sm font-medium">
                    <i class="ph ph-folder-plus text-2xl mb-1 block"></i> Open A/C
                </a>
                <a href="transactions/process.php" class="bg-indigo-500 hover:bg-indigo-400 p-3 rounded-xl transition-colors text-center text-sm font-medium col-span-2">
                    <i class="ph ph-arrows-left-right text-2xl mb-1 block"></i> Process Transaction
                </a>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <h3 class="font-semibold text-gray-800 text-lg mb-4 flex items-center justify-between">
                System Status
                <span class="flex items-center gap-1 text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full"><div class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></div> DB Connected</span>
            </h3>
            <ul class="space-y-3 text-sm">
                <li class="flex items-center justify-between text-gray-600 border-b border-gray-50 pb-2">
                    <span>Cron Job (Interest)</span>
                    <span class="text-rose-500 font-medium text-xs">Pending configuration</span>
                </li>
                <li class="flex items-center justify-between text-gray-600 border-b border-gray-50 pb-2">
                    <span>Server Time</span>
                    <span class="font-medium"><?= date('h:i A') ?></span>
                </li>
                <li class="flex items-center justify-between text-gray-600">
                    <span>RBI Compliance Mode</span>
                    <span class="text-emerald-500 font-medium">Active</span>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
