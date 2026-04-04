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
$txn_where = "";
if($_SESSION['role'] == 'advisor') {
    $txn_where = " WHERE t.created_by = {$_SESSION['user_id']}";
}
$txn_sql = "SELECT t.*, a.account_no, m.first_name, m.last_name 
            FROM transactions t 
            JOIN accounts a ON t.account_id = a.id 
            JOIN members m ON a.member_id = m.id 
            $txn_where
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

// Advisor Specific Stats
$advisor_wallet = 0;
$advisor_today_collection = 0;
if($_SESSION['role'] == 'advisor') {
    $adv_id = $_SESSION['user_id'];
    $adv_res = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $adv_id");
    $advisor_wallet = mysqli_fetch_assoc($adv_res)['wallet_balance'] ?? 0;
    
    $adv_coll_res = mysqli_query($conn, "SELECT SUM(ABS(amount)) as s FROM wallet_transactions WHERE user_id = $adv_id AND transaction_type = 'Collection' AND DATE(transaction_date) = '$today'");
    $advisor_today_collection = mysqli_fetch_assoc($adv_coll_res)['s'] ?? 0;
}
?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Stat Cards (Enhanced) -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex flex-col justify-between hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl">
                <i class="ph ph-users-three"></i>
            </div>
            <span class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full uppercase tracking-widest">+12% New</span>
        </div>
        <div>
            <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mb-1">Active Membership</p>
            <h3 class="text-3xl font-black text-gray-800 tracking-tight"><?= number_format($stats['total_members']) ?></h3>
            <p class="text-[10px] text-gray-400 mt-2 font-medium">Verified KYC Customers</p>
        </div>
    </div>
    
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex flex-col justify-between hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl">
                <i class="ph ph-folders"></i>
            </div>
            <span class="text-[10px] font-black text-blue-600 bg-blue-50 px-2 py-1 rounded-full uppercase tracking-widest">Live Book</span>
        </div>
        <div>
            <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mb-1">Portfolio Accounts</p>
            <h3 class="text-3xl font-black text-gray-800 tracking-tight"><?= number_format($stats['active_accounts']) ?></h3>
            <p class="text-[10px] text-gray-400 mt-2 font-medium">RD, FD, Savings & Loans</p>
        </div>
    </div>

    <?php if($_SESSION['role'] == 'admin'): ?>
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex flex-col justify-between hover:shadow-md transition-shadow lg:col-span-1">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl">
                <i class="ph ph-hand-coins"></i>
            </div>
            <span class="text-[10px] font-black text-indigo-600 bg-indigo-50 px-2 py-1 rounded-full uppercase tracking-widest">Total Inward</span>
        </div>
        <div>
            <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mb-1">Liability Capital</p>
            <h3 class="text-2xl font-black text-emerald-600 tracking-tighter truncate"><?= formatCurrency($stats['total_deposits']) ?></h3>
            <p class="text-[10px] text-gray-400 mt-2 font-medium">Customer Deposits & Savings</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex flex-col justify-between hover:shadow-md transition-shadow lg:col-span-1">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-2xl">
                <i class="ph ph-briefcase"></i>
            </div>
            <span class="text-[10px] font-black text-rose-600 bg-rose-50 px-2 py-1 rounded-full uppercase tracking-widest">Active Assets</span>
        </div>
        <div>
            <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mb-1">Outstanding Loans</p>
            <h3 class="text-2xl font-black text-rose-600 tracking-tighter truncate"><?= formatCurrency($stats['total_loans']) ?></h3>
            <p class="text-[10px] text-gray-400 mt-2 font-medium">Capital in Field / Recov. Due</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <div class="bg-gradient-to-br from-emerald-600 via-emerald-600 to-emerald-700 rounded-3xl p-8 text-white shadow-xl shadow-emerald-100 relative overflow-hidden group">
        <div class="relative z-10">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-2 h-2 bg-white rounded-full animate-ping"></div>
                <p class="text-emerald-100 text-[10px] font-black uppercase tracking-[0.2em] opacity-80"><?= $_SESSION['role'] == 'admin' ? "Live System Inflow (Today)" : "Your Collection Target" ?></p>
            </div>
            <h3 class="text-4xl font-black tracking-tight mb-2"><?= formatCurrency($_SESSION['role'] == 'admin' ? $today_deposits : $advisor_today_collection) ?></h3>
            <p class="text-emerald-100/60 text-xs font-medium">Total collections processed through verified channels today.</p>
        </div>
        <i class="ph ph-arrow-circle-down-right text-[120px] text-white/10 absolute -right-4 -bottom-4 group-hover:scale-110 transition-transform duration-500"></i>
    </div>
    
    <?php if($_SESSION['role'] == 'admin'): ?>
    <div class="bg-gradient-to-br from-indigo-700 via-indigo-800 to-indigo-900 rounded-3xl p-8 text-white shadow-xl shadow-indigo-100 relative overflow-hidden group">
        <div class="relative z-10">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-2 h-2 bg-rose-400 rounded-full"></div>
                <p class="text-indigo-200 text-[10px] font-black uppercase tracking-[0.2em] opacity-80">Outward Disbursement (Today)</p>
            </div>
            <h3 class="text-4xl font-black tracking-tight mb-2"><?= formatCurrency($today_debts) ?></h3>
            <p class="text-indigo-200/60 text-xs font-medium">Total withdrawals and loan disbursals settled in current session.</p>
        </div>
        <i class="ph ph-receipt text-[120px] text-white/10 absolute -right-4 -bottom-4 group-hover:scale-110 transition-transform duration-500"></i>
    </div>
    <?php endif; ?>
</div>

<?php if($_SESSION['role'] == 'advisor'): ?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100 flex items-center justify-between group hover:border-indigo-200 transition-all border-b-8 border-b-indigo-600">
        <div>
            <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-4">Current Prepaid Wallet</p>
            <h3 class="text-4xl font-black text-gray-800 tracking-tighter mb-2"><?= formatCurrency($advisor_wallet) ?></h3>
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                <p class="text-[10px] text-emerald-600 font-bold uppercase tracking-widest">Active for Collections</p>
            </div>
        </div>
        <div class="w-16 h-16 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-3xl group-hover:bg-indigo-600 group-hover:text-white transition-all">
            <i class="ph ph-wallet-bold"></i>
        </div>
    </div>
    
    <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100 flex items-center justify-between group hover:border-emerald-200 transition-all border-b-8 border-b-emerald-600">
        <div>
            <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-4">Today's Wallet Performance</p>
            <h3 class="text-4xl font-black text-gray-800 tracking-tighter mb-2"><?= formatCurrency($advisor_today_collection) ?></h3>
            <div class="flex items-center gap-2">
                 <i class="ph ph-calendar-check text-emerald-600"></i>
                 <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Updated for Session: <?= date('d M') ?></p>
            </div>
        </div>
        <div class="w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-3xl group-hover:bg-emerald-600 group-hover:text-white transition-all">
            <i class="ph ph-chart-bar-bold"></i>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Content Left (Recent Txns) -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-lg">Recent Transactions</h3>
            <a href="<?= APP_URL ?>transactions/process.php" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">View All <i class="ph ph-arrow-right"></i></a>
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

    <!-- Right Sidebar (Quick Actions & Portfolio) -->
    <div class="space-y-6">
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
            <h3 class="font-black text-gray-800 text-sm uppercase tracking-widest mb-6 flex items-center gap-2">
                 <i class="ph ph-lightning text-amber-500"></i> Operational Shortcuts
            </h3>
            <div class="grid grid-cols-2 gap-4">
                <?php if($_SESSION['role'] == 'admin'): ?>
                <a href="<?= APP_URL ?>members/add.php" class="flex flex-col items-center justify-center p-4 rounded-2xl bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white transition-all group border border-emerald-100">
                    <i class="ph ph-user-plus text-2xl mb-2 group-hover:scale-110 transition-transform"></i>
                    <span class="text-[10px] font-black uppercase tracking-tighter text-center leading-tight">New<br>Member</span>
                </a>
                <a href="<?= APP_URL ?>accounts/open.php" class="flex flex-col items-center justify-center p-4 rounded-2xl bg-blue-50 text-blue-700 hover:bg-blue-600 hover:text-white transition-all group border border-blue-100">
                    <i class="ph ph-folder-plus text-2xl mb-2 group-hover:scale-110 transition-transform"></i>
                    <span class="text-[10px] font-black uppercase tracking-tighter text-center leading-tight">Open<br>Account</span>
                </a>
                <a href="<?= APP_URL ?>transactions/process.php" class="flex flex-col items-center justify-center p-4 rounded-2xl bg-rose-50 text-rose-700 hover:bg-rose-600 hover:text-white transition-all group col-span-2 border border-rose-100">
                    <i class="ph ph-arrows-left-right text-2xl mb-2 group-hover:scale-110 transition-transform"></i>
                    <span class="text-[10px] font-black uppercase tracking-widest text-center">Process Transaction Entry</span>
                </a>
                <?php else: ?>
                <a href="<?= APP_URL ?>advisor/collect.php" class="flex flex-col items-center justify-center p-4 rounded-2xl bg-amber-50 text-amber-700 hover:bg-amber-600 hover:text-white transition-all group border border-amber-100">
                    <i class="ph ph-hand-coins text-2xl mb-2 group-hover:scale-110 transition-transform"></i>
                    <span class="text-[10px] font-black uppercase tracking-tighter text-center leading-tight">Collect<br>Deposit</span>
                </a>
                <a href="<?= APP_URL ?>advisor/wallet_history.php" class="flex flex-col items-center justify-center p-4 rounded-2xl bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white transition-all group border border-indigo-100">
                    <i class="ph ph-wallet text-2xl mb-2 group-hover:scale-110 transition-transform"></i>
                    <span class="text-[10px] font-black uppercase tracking-tighter text-center leading-tight">Wallet<br>History</span>
                </a>
                <a href="<?= APP_URL ?>help/calculations.php" class="flex flex-col items-center justify-center p-4 rounded-2xl bg-sky-50 text-sky-700 hover:bg-sky-600 hover:text-white transition-all group col-span-2 border border-sky-100">
                    <i class="ph ph-calculator text-2xl mb-2 group-hover:scale-110 transition-transform"></i>
                    <span class="text-[10px] font-black uppercase tracking-widest text-center">Open Calculation Helper</span>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if($_SESSION['role'] == 'admin'): ?>
        <?php
            // Simple product mix calculation
            $mix_res = mysqli_query($conn, "SELECT account_type, COUNT(*) as c FROM accounts GROUP BY account_type");
            $mix = []; $total_ac = 0;
            while($m = mysqli_fetch_assoc($mix_res)) { $mix[$m['account_type']] = $m['c']; $total_ac += $m['c']; }
        ?>
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
            <h3 class="font-black text-gray-800 text-sm uppercase tracking-widest mb-6">Portfolio Pulse</h3>
            <div class="space-y-4">
                <?php foreach(['Savings' => 'emerald', 'Loan' => 'rose', 'FD' => 'blue', 'RD' => 'indigo'] as $ptype => $pcolor): ?>
                <div>
                    <div class="flex justify-between text-[10px] font-black uppercase tracking-widest mb-1.5 grayscale opacity-60">
                        <span><?= $ptype ?></span>
                        <span><?= isset($mix[$ptype]) ? round(($mix[$ptype]/$total_ac)*100) : 0 ?>%</span>
                    </div>
                    <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-<?= $pcolor ?>-500 h-full w-[<?= isset($mix[$ptype]) ? ($mix[$ptype]/$total_ac)*100 : 0 ?>%]"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-slate-900 rounded-3xl p-6 shadow-xl text-white">
            <h3 class="font-black text-[10px] uppercase tracking-[0.2em] mb-4 text-slate-500">System Core V1.2</h3>
            <ul class="space-y-3">
                <li class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></div>
                        <span class="text-xs font-semibold text-slate-300">Environment</span>
                    </div>
                    <span class="text-[10px] font-black text-slate-500 uppercase">Production</span>
                </li>
                <li class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="ph ph-timer text-indigo-400"></i>
                        <span class="text-xs font-semibold text-slate-300">Cron Status</span>
                    </div>
                    <span class="text-[10px] font-black text-rose-500 uppercase">Idle</span>
                </li>
                <li class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="ph ph-shield-check text-blue-400"></i>
                        <span class="text-xs font-semibold text-slate-300">Encryption</span>
                    </div>
                    <span class="text-[10px] font-black text-slate-500 uppercase">Active</span>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
