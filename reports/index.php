<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$type = isset($_GET['type']) ? sanitize($conn, $_GET['type']) : 'summary';

// Master data fetch
$sql_maturity = "SELECT a.*, m.first_name, m.last_name, m.member_no 
                 FROM accounts a 
                 JOIN members m ON a.member_id = m.id 
                 WHERE a.status = 'matured' ORDER BY a.maturity_date DESC";
$mat_accs = mysqli_query($conn, $sql_maturity);

$sql_txns = "SELECT transaction_type, SUM(amount) as total FROM transactions GROUP BY transaction_type";
$txn_res = mysqli_query($conn, $sql_txns);
$txn_summary = [];
while($r = mysqli_fetch_assoc($txn_res)) {
    $txn_summary[$r['transaction_type']] = $r['total'];
}

$loan_sql = "SELECT SUM(current_balance) as outstanding FROM accounts WHERE account_type = 'Loan' AND status IN ('active','defaulted')";
$out_loans = mysqli_fetch_assoc(mysqli_query($conn, $loan_sql))['outstanding'];

// Additional detailed data for Summary
$counts_res = mysqli_query($conn, "SELECT 
    COUNT(CASE WHEN status IN ('active', 'defaulted') THEN 1 END) as active_accounts,
    COUNT(CASE WHEN status='closed' THEN 1 END) as closed_accounts
    FROM accounts");
$ac_counts = mysqli_fetch_assoc($counts_res);

$members_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM members"))['cnt'];

$balances = mysqli_query($conn, "SELECT account_type, SUM(current_balance) as bal FROM accounts WHERE status IN ('active','defaulted') GROUP BY account_type");
$acc_balances = [];
while($b = mysqli_fetch_assoc($balances)) {
    $acc_balances[$b['account_type']] = $b['bal'] ?? 0;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-chart-line-up text-indigo-500 text-3xl"></i> Compliance Reports
            </h1>
            <p class="text-gray-500 text-sm mt-1">Generate system-wide financial and statistical analysis for filing.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <a href="?type=summary" class="<?= $type=='summary'?'bg-indigo-600 text-white':'bg-white text-gray-700' ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                Financial Summary
            </a>
            <a href="account_report.php" class="bg-white text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm flex items-center gap-1.5 hover:bg-gray-50">
                <i class="ph ph-folder-open text-indigo-500"></i> Account Open Report
            </a>
            <a href="branch_report.php" class="bg-indigo-50 text-indigo-700 px-4 py-2 rounded-lg text-sm font-bold transition-colors shadow-sm flex items-center gap-1.5 hover:bg-indigo-100">
                <i class="ph ph-buildings"></i> Branch Report
            </a>
            <button onclick="window.print()" class="bg-gray-800 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm flex items-center gap-1">
                <i class="ph ph-printer"></i> Print
            </button>
        </div>
    </div>

    <?php if($type == 'summary'): ?>
    <?php
    // Velocity Data (Last 30 Days)
    $last_30 = date('Y-m-d', strtotime('-30 days'));
    $vel_res = mysqli_query($conn, "SELECT 
        SUM(CASE WHEN transaction_type IN ('Deposit', 'EMI', 'Account-Open') THEN amount ELSE 0 END) as inflows,
        SUM(CASE WHEN transaction_type IN ('Withdrawal', 'Pre-Closure') THEN amount ELSE 0 END) as outflows
        FROM transactions WHERE DATE(transaction_date) >= '$last_30'");
    $velocity = mysqli_fetch_assoc($vel_res);
    ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 border-l-4 border-l-indigo-600">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-1">Cash Flow Velocity</p>
                    <h3 class="text-lg font-bold text-gray-800">Inflows (30 Days)</h3>
                </div>
                <span class="text-emerald-500 font-bold text-sm bg-emerald-50 px-2 py-1 rounded">+<?= formatCurrency($velocity['inflows'] ?? 0) ?></span>
            </div>
            <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                <div class="bg-indigo-600 h-full w-[<?= min(100, (($velocity['inflows'] ?? 1) / max(1, ($velocity['inflows']+$velocity['outflows']))) * 100) ?>%]"></div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 border-l-4 border-l-rose-600">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-1">Cash Flow Velocity</p>
                    <h3 class="text-lg font-bold text-gray-800">Outflows (30 Days)</h3>
                </div>
                <span class="text-rose-500 font-bold text-sm bg-rose-50 px-2 py-1 rounded">-<?= formatCurrency(abs($velocity['outflows'] ?? 0)) ?></span>
            </div>
            <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                <div class="bg-rose-500 h-full w-[<?= min(100, ((abs($velocity['outflows']) ?? 1) / max(1, ($velocity['inflows']+$velocity['outflows']))) * 100) ?>%]"></div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="p-6 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-lg">System-Wide Financial Aggregates</h3>
            <span class="text-xs text-gray-500 px-2 py-1 bg-white border border-gray-200 rounded">As on <?= date('d M Y') ?></span>
        </div>
        <div class="p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Assets -->
                <div class="space-y-4">
                    <h4 class="font-semibold text-gray-500 uppercase tracking-widest text-xs border-b border-gray-100 pb-2 flex items-center gap-2">
                        <i class="ph ph-trend-up text-rose-500"></i> Assets / Outflows
                    </h4>
                    
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Total Loans Disbursed</span>
                        <span class="font-bold text-gray-800"><?= formatCurrency($txn_summary['Account-Open'] ?? 0) ?></span>
                    </div>
                    <div class="flex justify-between items-center text-sm p-2 bg-rose-50/50 rounded">
                        <span class="text-gray-600">Active Book Value (Loans)</span>
                        <span class="font-bold text-rose-600"><?= formatCurrency(abs($out_loans ?? 0)) ?></span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Total Withdrawals Paid</span>
                        <span class="font-bold text-gray-800 text-xs tracking-tight"><?= formatCurrency($txn_summary['Withdrawal'] ?? 0) ?></span>
                    </div>
                </div>

                <!-- Liabilities -->
                <div class="space-y-4">
                    <h4 class="font-semibold text-gray-500 uppercase tracking-widest text-xs border-b border-gray-100 pb-2 flex items-center gap-2">
                        <i class="ph ph-trend-down text-emerald-500"></i> Liabilities / Inflows
                    </h4>
                    
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Savings & RD Deposits</span>
                        <span class="font-bold text-emerald-600"><?= formatCurrency($txn_summary['Deposit'] ?? 0) ?></span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Loan EMIs Recovered</span>
                        <span class="font-bold text-blue-600"><?= formatCurrency($txn_summary['EMI'] ?? 0) ?></span>
                    </div>
                    <div class="flex justify-between items-center text-sm border-t border-gray-50 pt-2 font-medium">
                        <span class="text-gray-500">Other Incomes (Fines)</span>
                        <span class="text-amber-600"><?= formatCurrency($txn_summary['Fine'] ?? 0) ?></span>
                    </div>
                </div>

                 <!-- Demographics -->
                 <div class="space-y-4">
                    <h4 class="font-semibold text-gray-500 uppercase tracking-widest text-xs border-b border-gray-100 pb-2">Operational Health</h4>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Total Members</span>
                        <span class="font-bold text-gray-800"><?= number_format($members_count) ?></span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Active Accounts Ratio</span>
                        <span class="font-bold text-indigo-600"><?= number_format(($ac_counts['active_accounts'] / max(1, $ac_counts['active_accounts'] + $ac_counts['closed_accounts'])) * 100, 1) ?>%</span>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-8 border-t border-gray-100 pt-8">
                <!-- Current Portfolio Holdings -->
                <div class="lg:col-span-3 space-y-4">
                    <h4 class="font-semibold text-gray-500 uppercase tracking-widest text-xs border-b border-gray-100 pb-2">Portfolio Liability Distribution (Current Balances)</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                        <?php 
                        $loan_only = getSetting($conn, 'loan_only_mode') == '1';
                        if(!$loan_only): 
                        ?>
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                             <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Savings</p>
                             <p class="font-bold text-gray-800"><?= formatCurrency($acc_balances['Savings'] ?? 0) ?></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                             <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Recurring (RD)</p>
                             <p class="font-bold text-gray-800"><?= formatCurrency($acc_balances['RD'] ?? 0) ?></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                             <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Fixed Deposits</p>
                             <p class="font-bold text-gray-800"><?= formatCurrency($acc_balances['FD'] ?? 0) ?></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                             <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Monthly (MIS)</p>
                             <p class="font-bold text-gray-800"><?= formatCurrency($acc_balances['MIS'] ?? 0) ?></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                             <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Daily (DD)</p>
                             <p class="font-bold text-gray-800"><?= formatCurrency($acc_balances['DD'] ?? 0) ?></p>
                        </div>
                        <?php endif; ?>
                        <div class="bg-rose-50 p-4 rounded-xl border border-rose-100">
                             <p class="text-[10px] text-rose-400 font-bold uppercase mb-1">Total Loan Book</p>
                             <p class="font-bold text-rose-800"><?= formatCurrency(abs($acc_balances['Loan'] ?? 0)) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 pt-6 border-t border-dashed border-gray-200 text-center text-xs text-gray-400 flex items-center justify-center gap-2">
                <i class="ph ph-info text-lg"></i>
                This is a summarized aggregation based on the internal ledger mapping. For detailed balance sheet, please export ledger data to tally.
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if($type == 'maturity'): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 bg-gray-50">
            <h3 class="font-semibold text-gray-800 text-lg">Matured Term Deposits (FD/RD/MIS)</h3>
            <p class="text-xs text-gray-500 mt-1">List of fully matured accounts pending customer payout or renewal.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 text-gray-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-4 font-medium">Customer Details</th>
                        <th class="px-6 py-4 font-medium">Account No</th>
                        <th class="px-6 py-4 font-medium">Type</th>
                        <th class="px-6 py-4 font-medium">Maturity Date</th>
                        <th class="px-6 py-4 font-medium text-right">Maturity Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <?php if(mysqli_num_rows($mat_accs) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($mat_accs)): ?>
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-800"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                    <div class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($row['member_no']) ?></div>
                                </td>
                                <td class="px-6 py-4 font-mono font-medium text-indigo-600">
                                    <?= htmlspecialchars($row['account_no']) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="bg-blue-50 text-blue-700 px-2.5 py-1 rounded text-xs font-bold"><?= $row['account_type'] ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-rose-600 font-medium font-mono"><?= date('d M Y', strtotime($row['maturity_date'])) ?></div>
                                </td>
                                <td class="px-6 py-4 font-bold text-gray-800 text-right text-lg tracking-tight">
                                    <?= formatCurrency($row['current_balance']) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500 filter drop-shadow opacity-60">
                            <i class="ph ph-calendar-check text-5xl text-gray-300 mb-3 block"></i>
                            No matured accounts pending clearance.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once '../includes/footer.php'; ?>
