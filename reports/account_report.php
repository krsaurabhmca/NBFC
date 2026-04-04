<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$range = isset($_GET['range']) ? sanitize($conn, $_GET['range']) : 'today';
$start_date = isset($_GET['start_date']) ? sanitize($conn, $_GET['start_date']) : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? sanitize($conn, $_GET['end_date']) : date('Y-m-d');

if ($range == 'today') {
    $start_date = $end_date = date('Y-m-d');
} elseif ($range == 'week') {
    $start_date = date('Y-m-d', strtotime('-7 days'));
    $end_date = date('Y-m-d');
} elseif ($range == 'month') {
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $end_date = date('Y-m-d');
} elseif ($range == 'year') {
    $start_date = date('Y-m-d', strtotime('-365 days'));
    $end_date = date('Y-m-d');
}

$where = "WHERE DATE(a.opening_date) BETWEEN '$start_date' AND '$end_date'";

// Summary Data
$summary_sql = "SELECT account_type, COUNT(*) as count, SUM(opening_balance) as total_opening 
                FROM accounts a 
                $where 
                GROUP BY account_type";
$summary_res = mysqli_query($conn, $summary_sql);
$summary_data = [];
$grand_total_count = 0;
$grand_total_opening = 0;
while($row = mysqli_fetch_assoc($summary_res)) {
    $summary_data[$row['account_type']] = $row;
    $grand_total_count += $row['count'];
    $grand_total_opening += $row['total_opening'];
}

// Detailed List
$list_sql = "SELECT a.*, m.first_name, m.last_name, m.member_no, s.scheme_name 
             FROM accounts a 
             JOIN members m ON a.member_id = m.id 
             JOIN schemes s ON a.scheme_id = s.id 
             $where 
             ORDER BY a.opening_date DESC";
$list_res = mysqli_query($conn, $list_sql);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-folder-open text-indigo-500 text-3xl"></i> Account Opening Report
            </h1>
            <p class="text-gray-500 text-sm mt-1">Detailed analysis of new accounts opened within the selected timeframe.</p>
        </div>
        
        <div class="flex items-center gap-2 print:hidden">
            <button onclick="window.print()" class="bg-gray-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-700 transition-colors shadow-sm flex items-center gap-1.5">
                <i class="ph ph-printer"></i> Print Report
            </button>
            <a href="index.php" class="bg-white border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors shadow-sm">
                 Main Reports
            </a>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6 print:hidden">
        <form method="GET" action="" class="flex flex-wrap items-end gap-4">
            <input type="hidden" name="range" value="custom">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5 text-center">Quick Filters</label>
                <div class="flex bg-gray-50 p-1 rounded-xl border border-gray-100">
                    <a href="?range=today" class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all <?= $range=='today' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>">Today</a>
                    <a href="?range=week" class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all <?= $range=='week' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>">Week</a>
                    <a href="?range=month" class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all <?= $range=='month' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>">Month</a>
                    <a href="?range=year" class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all <?= $range=='year' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>">Year</a>
                </div>
            </div>
            
            <div class="h-10 w-px bg-gray-100 mb-1 mx-2"></div>
            
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5">Start Date</label>
                <input type="date" name="start_date" value="<?= $start_date ?>" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5">End Date</label>
                <input type="date" name="end_date" value="<?= $end_date ?>" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
            </div>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-lg text-sm font-bold transition-colors shadow-lg shadow-indigo-100 flex items-center gap-2">
                <i class="ph ph-funnel"></i> Apply Filter
            </button>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-gradient-to-br from-indigo-600 to-indigo-700 rounded-2xl p-6 text-white shadow-xl shadow-indigo-100">
            <p class="text-indigo-100 text-[10px] font-bold uppercase tracking-widest mb-2">Total Accounts Opened</p>
            <h3 class="text-3xl font-black tracking-tight"><?= number_format($grand_total_count) ?></h3>
            <p class="text-indigo-100 text-xs mt-3 opacity-80 flex items-center gap-1"><i class="ph ph-calendar"></i> <?= date('d M', strtotime($start_date)) ?> - <?= date('d M, Y', strtotime($end_date)) ?></p>
        </div>
        
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 border-l-4 border-l-emerald-500">
            <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest mb-2">Total Opening Capital</p>
            <h3 class="text-3xl font-black text-gray-800 tracking-tight"><?= formatCurrency($grand_total_opening) ?></h3>
            <p class="text-emerald-600 text-xs mt-3 font-bold flex items-center gap-1"><i class="ph ph-arrow-circle-down"></i> New Inward Capital</p>
        </div>

        <div class="md:col-span-2 bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest mb-4">Product Distribution</p>
            <div class="flex items-center gap-2 flex-wrap">
                <?php foreach(['Savings','Loan','FD','RD','MIS','DD'] as $type): ?>
                    <?php if(isset($summary_data[$type])): ?>
                        <div class="bg-gray-50 px-3 py-2 rounded-xl flex items-center gap-3 border border-gray-100">
                            <div class="text-xs font-bold text-gray-800"><?= $type ?></div>
                            <div class="text-lg font-black text-indigo-600"><?= $summary_data[$type]['count'] ?></div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-12">
        <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-bold text-gray-800"><i class="ph ph-list-dashes text-indigo-500 mr-1"></i> Despatch List / Log Details</h3>
            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-tighter">Report generated at <?= date('h:i A, d M Y') ?></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-gray-400 text-[10px] uppercase tracking-widest font-black border-b border-gray-50">
                        <th class="px-6 py-4">Account Details</th>
                        <th class="px-6 py-4">Member Name</th>
                        <th class="px-6 py-4">Product Category</th>
                        <th class="px-6 py-4 text-right">Opening Balance</th>
                        <th class="px-6 py-4 text-right">Effective Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 text-sm">
                    <?php if(mysqli_num_rows($list_res) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($list_res)): ?>
                            <tr class="hover:bg-indigo-50/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-gray-800 font-mono"><?= $row['account_no'] ?></div>
                                    <div class="text-[10px] text-gray-400"><?= $row['scheme_name'] ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-gray-700"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                    <div class="text-[10px] text-gray-400 tracking-wider"><?= $row['member_no'] ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-indigo-50 text-indigo-600 border border-indigo-100">
                                        <?= $row['account_type'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right font-black text-gray-800 tracking-tight">
                                    <?= formatCurrency($row['opening_balance']) ?>
                                </td>
                                <td class="px-6 py-4 text-right font-medium text-gray-500">
                                    <?= date('d M, Y', strtotime($row['opening_date'])) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400">
                            <i class="ph ph-mask-sad text-5xl mb-4 block opacity-20"></i>
                            <p class="font-bold">Zero records match the applied date criteria.</p>
                            <p class="text-xs">Try selecting a broader range or checking quick filters.</p>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
