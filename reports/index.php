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
            <a href="?type=maturity" class="<?= $type=='maturity'?'bg-indigo-600 text-white':'bg-white text-gray-700' ?> px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                Maturity Report
            </a>
            <button onclick="window.print()" class="bg-gray-800 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm flex items-center gap-1">
                <i class="ph ph-printer"></i> Print
            </button>
        </div>
    </div>

    <?php if($type == 'summary'): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="p-6 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-lg">System-Wide Financial Aggregates</h3>
            <span class="text-xs text-gray-500 px-2 py-1 bg-white border border-gray-200 rounded">As on <?= date('d M Y') ?></span>
        </div>
        <div class="p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Assets -->
                <div class="space-y-4">
                    <h4 class="font-semibold text-gray-500 uppercase tracking-widest text-xs border-b border-gray-100 pb-2">Assets / Outflows</h4>
                    
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Total Loans Disbursed</span>
                        <span class="font-bold text-gray-800"><?= formatCurrency($txn_summary['Account-Open'] ?? 0) ?></span> <!-- Assuming Account Open for loan is disbursal in our basic logic -->
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Current Outstanding Loans</span>
                        <span class="font-bold text-rose-600"><?= formatCurrency(abs($out_loans ?? 0)) ?></span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Total Withdrawals Paid</span>
                        <span class="font-bold text-gray-800"><?= formatCurrency($txn_summary['Withdrawal'] ?? 0) ?></span>
                    </div>
                </div>

                <!-- Liabilities -->
                <div class="space-y-4">
                    <h4 class="font-semibold text-gray-500 uppercase tracking-widest text-xs border-b border-gray-100 pb-2">Liabilities / Inflows</h4>
                    
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Total Deposits Collected</span>
                        <span class="font-bold text-emerald-600"><?= formatCurrency($txn_summary['Deposit'] ?? 0) ?></span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Total EMIs Recovered</span>
                        <span class="font-bold text-blue-600"><?= formatCurrency($txn_summary['EMI'] ?? 0) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 border-t border-gray-100 pt-8">
                <!-- Demographics -->
                <div class="space-y-4">
                    <h4 class="font-semibold text-gray-500 uppercase tracking-widest text-xs border-b border-gray-100 pb-2">System Demographics</h4>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Total Registered Members</span>
                        <span class="font-bold text-gray-800"><?= number_format($members_count) ?></span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Active LEDGER Accounts</span>
                        <span class="font-bold text-gray-800"><?= number_format($ac_counts['active_accounts']) ?></span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Closed / Settled Accounts</span>
                        <span class="font-bold text-gray-800"><?= number_format($ac_counts['closed_accounts']) ?></span>
                    </div>
                </div>

                <!-- Current Portfolio Holdings -->
                <div class="lg:col-span-2 space-y-4">
                    <h4 class="font-semibold text-gray-500 uppercase tracking-widest text-xs border-b border-gray-100 pb-2">Current Active Portfolio Liability</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex justify-between items-center text-sm bg-gray-50 p-3 rounded border border-gray-100">
                            <span class="text-gray-600">Savings Balances</span>
                            <span class="font-bold text-emerald-700"><?= formatCurrency($acc_balances['Savings'] ?? 0) ?></span>
                        </div>
                        <div class="flex justify-between items-center text-sm bg-gray-50 p-3 rounded border border-gray-100">
                            <span class="text-gray-600">Fixed Deposits (FD)</span>
                            <span class="font-bold text-emerald-700"><?= formatCurrency($acc_balances['FD'] ?? 0) ?></span>
                        </div>
                        <div class="flex justify-between items-center text-sm bg-gray-50 p-3 rounded border border-gray-100">
                            <span class="text-gray-600">Recurring Deposits (RD)</span>
                            <span class="font-bold text-emerald-700"><?= formatCurrency($acc_balances['RD'] ?? 0) ?></span>
                        </div>
                        <div class="flex justify-between items-center text-sm bg-gray-50 p-3 rounded border border-gray-100">
                            <span class="text-gray-600">MIS / Daily Deposits</span>
                            <span class="font-bold text-emerald-700"><?= formatCurrency(($acc_balances['MIS'] ?? 0) + ($acc_balances['DD'] ?? 0)) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 pt-6 border-t border-dashed border-gray-200 text-center text-xs text-gray-400">
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
