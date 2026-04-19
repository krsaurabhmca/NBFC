<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

if($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access Denied. Admins only.";
    header("Location: ../index.php");
    exit();
}

// Fetch all branches
$branches_res = mysqli_query($conn, "SELECT * FROM branches WHERE status = 'active' ORDER BY branch_name ASC");
$report_data = [];

while($branch = mysqli_fetch_assoc($branches_res)) {
    $bid = $branch['id'];
    
    // 1. Get Loan Frequency Breakdown
    $freq_sql = "SELECT repayment_frequency, COUNT(*) as cnt, SUM(ABS(current_balance)) as balance 
                 FROM accounts 
                 WHERE branch_id = $bid AND account_type = 'Loan' AND status IN ('active', 'defaulted')
                 GROUP BY repayment_frequency";
    $freq_res = mysqli_query($conn, $freq_sql);
    $frequencies = ['Weekly' => 0, 'Bi-Weekly' => 0, 'Monthly' => 0];
    $total_balance = 0;
    
    while($f = mysqli_fetch_assoc($freq_res)) {
        $frequencies[$f['repayment_frequency']] = (int)$f['cnt'];
        $total_balance += (float)$f['balance'];
    }
    
    // 2. Get Current Month Collection
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    $coll_sql = "SELECT SUM(amount) as coll 
                 FROM transactions t
                 JOIN accounts a ON t.account_id = a.id
                 WHERE a.branch_id = $bid AND t.transaction_type = 'EMI' 
                 AND t.transaction_date BETWEEN '$month_start 00:00:00' AND '$month_end 23:59:59'
                 AND (t.status IS NULL OR t.status != 'Cancelled')";
    $coll_res = mysqli_fetch_assoc(mysqli_query($conn, $coll_sql));
    
    $report_data[] = [
        'name' => $branch['branch_name'],
        'code' => $branch['branch_code'],
        'freqs' => $frequencies,
        'total_loans' => array_sum($frequencies),
        'outstanding' => $total_balance,
        'collection' => (float)$coll_res['coll']
    ];
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight flex items-center gap-3">
                <i class="ph ph-buildings text-indigo-600"></i> Branch Wise Portfolio Report
            </h1>
            <p class="text-slate-500 text-sm font-medium mt-1">Operational breakdown by repayment frequency and branch performance.</p>
        </div>
        <div class="flex items-center gap-3 print:hidden">
            <button onclick="window.print()" class="px-6 py-3 bg-slate-900 text-white rounded-lg text-xs font-black uppercase shadow-xl shadow-slate-200 transition-all flex items-center gap-2">
                <i class="ph ph-printer text-lg"></i> Print Report
            </button>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <?php
        $grand_outstanding = array_sum(array_column($report_data, 'outstanding'));
        $grand_collection = array_sum(array_column($report_data, 'collection'));
        $total_weekly = 0;
        $total_biweekly = 0;
        foreach($report_data as $b) {
            $total_weekly += $b['freqs']['Weekly'];
            $total_biweekly += $b['freqs']['Bi-Weekly'];
        }
        ?>
        <div class="bg-indigo-600 rounded-xl p-6 text-white shadow-xl shadow-indigo-100">
            <p class="text-indigo-200 text-[10px] font-black uppercase tracking-widest mb-1">Total Loan Book</p>
            <h4 class="text-2xl font-black"><?= formatCurrency($grand_outstanding) ?></h4>
        </div>
        <div class="bg-white rounded-xl p-6 border border-slate-100 shadow-sm">
            <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">This Month Collection</p>
            <h4 class="text-2xl font-black text-emerald-600"><?= formatCurrency($grand_collection) ?></h4>
        </div>
        <div class="bg-white rounded-xl p-6 border border-slate-100 shadow-sm">
            <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Weekly Loan Clients</p>
            <h4 class="text-2xl font-black text-slate-800"><?= $total_weekly ?></h4>
        </div>
        <div class="bg-white rounded-xl p-6 border border-slate-100 shadow-sm">
            <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Bi-Weekly Clients</p>
            <h4 class="text-2xl font-black text-slate-800"><?= $total_biweekly ?></h4>
        </div>
    </div>

    <!-- Main Table -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-8 border-b border-slate-50 flex items-center justify-between">
            <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em]">Branch Performance Matrix</h3>
            <span class="text-[10px] font-black text-indigo-500 bg-indigo-50 px-3 py-1 rounded-full uppercase">Live Data</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-slate-400 text-[10px] uppercase font-black tracking-widest bg-slate-50/50">
                        <th class="px-8 py-5">Branch Name</th>
                        <th class="px-6 py-5 text-center">Weekly</th>
                        <th class="px-6 py-5 text-center">Bi-Weekly</th>
                        <th class="px-6 py-5 text-center">Monthly</th>
                        <th class="px-6 py-5 text-right">Running Loan Amount</th>
                        <th class="px-8 py-5 text-right">Month Collection</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-sm">
                    <?php if(!empty($report_data)): ?>
                        <?php foreach($report_data as $row): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-8 py-5">
                                    <div class="font-black text-slate-800"><?= htmlspecialchars($row['name']) ?></div>
                                    <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider"><?= $row['code'] ?></div>
                                </td>
                                <td class="px-6 py-5 text-center font-mono font-bold text-slate-500">
                                    <span class="<?= $row['freqs']['Weekly'] > 0 ? 'text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded' : '' ?>"><?= $row['freqs']['Weekly'] ?></span>
                                </td>
                                <td class="px-6 py-5 text-center font-mono font-bold text-slate-500">
                                    <span class="<?= $row['freqs']['Bi-Weekly'] > 0 ? 'text-amber-600 bg-amber-50 px-2 py-0.5 rounded' : '' ?>"><?= $row['freqs']['Bi-Weekly'] ?></span>
                                </td>
                                <td class="px-6 py-5 text-center font-mono font-bold text-slate-500">
                                    <?= $row['freqs']['Monthly'] ?>
                                </td>
                                <td class="px-6 py-5 text-right font-black text-slate-800">
                                    <?= formatCurrency($row['outstanding']) ?>
                                </td>
                                <td class="px-8 py-5 text-right">
                                    <span class="font-black text-emerald-600"><?= formatCurrency($row['collection']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Grand Total Row -->
                        <tr class="bg-slate-900 text-white font-black">
                            <td class="px-8 py-6 uppercase text-[10px] tracking-widest">Grand Total Consolidated</td>
                            <td class="px-6 py-6 text-center font-mono"><?= $total_weekly ?></td>
                            <td class="px-6 py-6 text-center font-mono"><?= $total_biweekly ?></td>
                            <td class="px-6 py-6 text-center font-mono"><?= array_sum(array_column($report_data, 'freqs')) - ($total_weekly + $total_biweekly) ?></td>
                            <td class="px-6 py-6 text-right text-lg"><?= formatCurrency($grand_outstanding) ?></td>
                            <td class="px-8 py-6 text-right text-lg text-emerald-400"><?= formatCurrency($grand_collection) ?></td>
                        </tr>
                    <?php else: ?>
                        <tr><td colspan="6" class="px-8 py-20 text-center text-slate-400 font-bold uppercase text-xs tracking-widest">No branch data available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
