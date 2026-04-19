<?php
// index.php - Loan System Dashboard
require_once 'includes/db.php';
checkAuth();

require_once 'includes/functions.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Sync Overdue Status & Fines
mysqli_query($conn, "UPDATE loan_schedules ls JOIN accounts a ON ls.account_id = a.id SET ls.status = 'Overdue' WHERE ls.status = 'Pending' AND ls.due_date < CURDATE()");
$late_fine = (float)getSetting($conn, 'loan_late_fine_fixed') ?: 50.00;
$grace = (int)getSetting($conn, 'loan_grace_days') ?: 3;
mysqli_query($conn, "UPDATE loan_schedules ls SET ls.fine_amount = $late_fine WHERE ls.status = 'Overdue' AND ls.fine_amount <= 0 AND DATEDIFF(CURDATE(), ls.due_date) > $grace");

// Handle Period Filtering
$period = isset($_GET['period']) ? sanitize($conn, $_GET['period']) : 'today';
$from_date = isset($_GET['from_date']) ? sanitize($conn, $_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? sanitize($conn, $_GET['to_date']) : '';

$date_where = "DATE(transaction_date) = CURDATE()";
$sched_where = "due_date = CURDATE()";

if ($period == 'week') {
    $date_where = "DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $sched_where = "due_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($period == 'month') {
    $date_where = "DATE(transaction_date) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
    $sched_where = "due_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
} elseif ($period == 'all') {
    $date_where = "1=1";
    $sched_where = "1=1";
} elseif ($period == 'custom' && $from_date && $to_date) {
    $date_where = "DATE(transaction_date) BETWEEN '$from_date' AND '$to_date'";
    $sched_where = "due_date BETWEEN '$from_date' AND '$to_date'";
}

// 1. Portfolio Stats
$total_members = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM members WHERE status = 'active'"))['c'];
$active_loans = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM accounts WHERE status = 'active' AND account_type = 'Loan'"))['c'];
$market_capital = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(principal_amount) as s FROM accounts WHERE status IN ('active','defaulted') AND account_type = 'Loan'"))['s'] ?? 0;
$pending_approvals = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM accounts WHERE status = 'pending_approval'"))['c'];

// 2. Performance Stats (Period Based)
$disbursements = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as s FROM transactions WHERE transaction_type = 'Loan' AND $date_where"))['s'] ?? 0;
$collections = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as s FROM transactions WHERE transaction_type IN ('EMI', 'Fine') AND $date_where"))['s'] ?? 0;
$target_recovery = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(emi_amount + fine_amount) as s FROM loan_schedules WHERE $sched_where"))['s'] ?? 0;
$npa_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT account_id) as c FROM loan_schedules WHERE status = 'Overdue'"))['c'];

// 3. Recent Activity
$txn_where = " WHERE $date_where";
if($_SESSION['role'] == 'advisor') {
    $txn_where .= " AND t.created_by = {$_SESSION['user_id']}";
}
$recent_txns = mysqli_query($conn, "SELECT t.*, a.account_no, m.first_name, m.last_name 
                                     FROM transactions t 
                                     JOIN accounts a ON t.account_id = a.id 
                                     JOIN members m ON a.member_id = m.id 
                                     $txn_where ORDER BY t.id DESC LIMIT 6");

$user_name = $_SESSION['name'] ?? 'Officer';
?>

<div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight">Loan Intelligence Dashboard</h1>
        <p class="text-slate-500 font-medium">Monitoring portfolio health and regional debt recovery.</p>
    </div>
    <div class="flex items-center gap-2 bg-white p-2 rounded-2xl shadow-sm border border-slate-100">
        <?php foreach(['today' => 'Today', 'month' => 'Month', 'all' => 'All'] as $key => $label): ?>
            <a href="?period=<?= $key ?>" class="px-4 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all <?= $period == $key ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-50' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if($_SESSION['role'] == 'admin' && $pending_approvals > 0): ?>
<div class="mb-8 bg-amber-50 border border-amber-200 p-5 rounded-3xl flex items-center justify-between shadow-sm shadow-amber-100">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-amber-500 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-amber-200">
            <i class="ph ph-shield-warning text-2xl font-black"></i>
        </div>
        <div>
            <h3 class="font-black text-amber-900 text-sm uppercase tracking-widest">Sanction Queue Active</h3>
            <p class="text-amber-700 text-xs">There are <span class="font-black"><?= $pending_approvals ?> loan applications</span> awaiting administrative review and disbursement.</p>
        </div>
    </div>
    <a href="loans/list.php?status=pending_approval" class="px-6 py-2 bg-amber-600 hover:bg-amber-700 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all">Review Queue</a>
</div>
<?php endif; ?>

<!-- Primary KPIs -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 group relative overflow-hidden">
        <div class="relative z-10">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Market Capital</p>
            <h3 class="text-2xl font-black text-slate-800 tracking-tight"><?= formatCurrency($market_capital) ?></h3>
            <div class="mt-4 flex items-center gap-2">
                <span class="bg-emerald-100 text-emerald-700 text-[9px] font-black px-2 py-0.5 rounded-full uppercase">Active Assets</span>
            </div>
        </div>
        <i class="ph ph-hand-coins text-8xl text-slate-50 absolute -right-4 -bottom-4 rotate-12 group-hover:rotate-0 transition-transform duration-500"></i>
    </div>

    <div class="bg-indigo-600 p-6 rounded-3xl shadow-xl shadow-indigo-100 text-white group relative overflow-hidden">
        <div class="relative z-10">
            <p class="text-[10px] font-black text-white/60 uppercase tracking-widest mb-1">Target Recovery</p>
            <h3 class="text-2xl font-black tracking-tight"><?= formatCurrency($target_recovery) ?></h3>
            <div class="mt-4 flex items-center gap-2">
                <span class="bg-white/20 text-white text-[9px] font-black px-2 py-0.5 rounded-full uppercase"><?= ucfirst($period) ?> Goal</span>
            </div>
        </div>
        <i class="ph ph-calendar-check text-8xl text-white/10 absolute -right-4 -bottom-4 rotate-12 group-hover:rotate-0 transition-transform duration-500"></i>
    </div>

    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 group relative overflow-hidden">
        <div class="relative z-10">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Realized Collection</p>
            <h3 class="text-2xl font-black text-emerald-600 tracking-tight"><?= formatCurrency($collections) ?></h3>
            <div class="mt-4 flex items-center gap-2">
                <?php $eff = ($target_recovery > 0) ? round(($collections/$target_recovery)*100) : 0; ?>
                <span class="bg-slate-100 text-slate-600 text-[9px] font-black px-2 py-0.5 rounded-full uppercase"><?= $eff ?>% Efficiency</span>
            </div>
        </div>
        <i class="ph ph-chart-line-up text-8xl text-slate-50 absolute -right-4 -bottom-4 rotate-12 group-hover:rotate-0 transition-transform duration-500"></i>
    </div>

    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 group relative overflow-hidden">
        <div class="relative z-10">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Delinquent Units (NPA)</p>
            <h3 class="text-2xl font-black text-rose-600 tracking-tight"><?= $npa_count ?> <span class="text-xs text-slate-400 font-bold uppercase">Accounts</span></h3>
            <div class="mt-4 flex items-center gap-2">
                <span class="bg-rose-100 text-rose-700 text-[9px] font-black px-2 py-0.5 rounded-full uppercase">Action Required</span>
            </div>
        </div>
        <i class="ph ph-warning-octagon text-8xl text-slate-50 absolute -right-4 -bottom-4 rotate-12 group-hover:rotate-0 transition-transform duration-500"></i>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
    <!-- Transactions Flow -->
    <div class="lg:col-span-8 space-y-6">
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-50 flex items-center justify-between">
                <h3 class="font-black text-slate-800 text-xs uppercase tracking-widest">Recent Credit Movements</h3>
                <a href="reports/collection_report.php" class="text-[10px] font-black text-indigo-600 uppercase tracking-widest hover:underline">Full Ledger</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50/50 text-slate-400 font-black uppercase tracking-widest text-[9px]">
                        <tr>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Beneficiary</th>
                            <th class="px-6 py-4">Type</th>
                            <th class="px-6 py-4 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php while($txn = mysqli_fetch_assoc($recent_txns)): ?>
                        <tr class="hover:bg-slate-50/50 transition-all">
                            <td class="px-6 py-4">
                                <div class="w-2 h-2 rounded-full <?= in_array($txn['transaction_type'], ['EMI', 'Fine']) ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500' ?>"></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800"><?= $txn['first_name'] ?> <?= $txn['last_name'] ?></div>
                                <div class="text-[10px] text-slate-400 font-mono"><?= $txn['account_no'] ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="bg-slate-100 text-slate-600 text-[9px] font-black px-2 py-0.5 rounded uppercase"><?= $txn['transaction_type'] ?></span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="font-black <?= in_array($txn['transaction_type'], ['EMI', 'Fine']) ? 'text-emerald-600' : 'text-rose-600' ?>">
                                    <?= in_array($txn['transaction_type'], ['EMI', 'Fine']) ? '+' : '-' ?><?= formatCurrency($txn['amount']) ?>
                                </div>
                                <div class="text-[9px] text-slate-400"><?= date('h:i A', strtotime($txn['transaction_date'])) ?></div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if(mysqli_num_rows($recent_txns) == 0): ?>
                        <tr><td colspan="4" class="px-6 py-12 text-center text-slate-400 font-bold uppercase tracking-widest">No credit activity in this period</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Portfolio & Sidebar -->
    <div class="lg:col-span-4 space-y-6">
        <div class="bg-slate-900 rounded-3xl p-6 text-white shadow-xl shadow-slate-200 border-b-8 border-b-indigo-600">
            <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-6">Regional Distribution</h3>
            <div class="space-y-6">
                <div>
                    <div class="flex justify-between text-[10px] font-black uppercase tracking-widest mb-2 opacity-60">
                        <span>Sanctioned Capital</span>
                        <span><?= formatCurrency($market_capital) ?></span>
                    </div>
                    <div class="w-full bg-slate-800 h-1 rounded-full overflow-hidden">
                        <div class="bg-indigo-400 h-full w-[100%]"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-[10px] font-black uppercase tracking-widest mb-2 opacity-60">
                        <span>Principal Collected</span>
                        <?php 
                        $rec_p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(principal_component) as s FROM loan_schedules WHERE status = 'Paid'"))['s'] ?? 0; 
                        $p_per = ($market_capital > 0) ? round(($rec_p/$market_capital)*100) : 0;
                        ?>
                        <span><?= $p_per ?>%</span>
                    </div>
                    <div class="w-full bg-slate-800 h-1 rounded-full overflow-hidden">
                        <div class="bg-emerald-400 h-full shadow-[0_0_10px_rgba(52,211,153,0.5)]" style="width: <?= $p_per ?>%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-[10px] font-black uppercase tracking-widest mb-2 opacity-60">
                        <span>Interest Realized</span>
                        <?php $rec_i = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(interest_component) as s FROM loan_schedules WHERE status = 'Paid'"))['s'] ?? 0; ?>
                        <span><?= formatCurrency($rec_i) ?></span>
                    </div>
                    <div class="w-full bg-slate-800 h-1 rounded-full overflow-hidden">
                        <div class="bg-amber-400 h-full" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm">
            <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-4">Command Center</h3>
            <div class="grid grid-cols-2 gap-3">
                <a href="loans/disburse.php" class="p-4 bg-indigo-50 text-indigo-600 rounded-2xl flex flex-col items-center justify-center gap-2 hover:bg-indigo-600 hover:text-white transition-all group">
                    <i class="ph ph-plus-circle text-2xl group-hover:scale-110 transition-transform"></i>
                    <span class="text-[9px] font-black uppercase tracking-widest">Apply</span>
                </a>
                <a href="loans/pay.php" class="p-4 bg-emerald-50 text-emerald-600 rounded-2xl flex flex-col items-center justify-center gap-2 hover:bg-emerald-600 hover:text-white transition-all group">
                    <i class="ph ph-hand-coins text-2xl group-hover:scale-110 transition-transform"></i>
                    <span class="text-[9px] font-black uppercase tracking-widest">Collect</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
