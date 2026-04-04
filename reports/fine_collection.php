<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$from_date = isset($_GET['from_date']) ? sanitize($conn, $_GET['from_date']) : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? sanitize($conn, $_GET['to_date']) : date('Y-m-d');

// Sum up Paid Fines in Loan Schedules
$sql = "SELECT ls.*, a.account_no, m.first_name, m.last_name 
        FROM loan_schedules ls 
        JOIN accounts a ON ls.account_id = a.id 
        JOIN members m ON a.member_id = m.id 
        WHERE ls.status = 'Paid' 
        AND ls.fine_amount > 0 
        AND ls.paid_date BETWEEN '$from_date' AND '$to_date' 
        ORDER BY ls.paid_date DESC";

$res = mysqli_query($conn, $sql);

// Calculate Totals
$total_fines = 0;
$results = [];
while($row = mysqli_fetch_assoc($res)) {
    $total_fines += $row['fine_amount'];
    $results[] = $row;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-hand-coins text-rose-500 text-3xl"></i> Fine Collection Income Report
            </h1>
            <p class="text-gray-500 text-sm mt-1">Detailed breakdown of revenue generated from late payment penalties.</p>
        </div>
        <div class="flex gap-2 no-print">
            <button onclick="window.print()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium text-sm flex items-center gap-2 hover:bg-gray-200 transition-colors">
                <i class="ph ph-printer"></i> Print Report
            </button>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-6 no-print">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">From Date</label>
                <input type="date" name="from_date" value="<?= $from_date ?>" class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">To Date</label>
                <input type="date" name="to_date" value="<?= $to_date ?>" class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            <div>
                <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-2.5 rounded-xl hover:bg-indigo-700 transition shadow-lg shadow-indigo-100">
                    Generate Analysis
                </button>
            </div>
        </form>
    </div>

    <!-- Summary Widgets -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-gradient-to-br from-rose-500 to-rose-600 p-6 rounded-2xl shadow-lg text-white">
            <span class="text-rose-100 text-xs font-bold uppercase tracking-widest block mb-1">Total Fine Income</span>
            <h2 class="text-4xl font-black tracking-tight"><?= formatCurrency($total_fines) ?></h2>
            <div class="mt-4 pt-4 border-t border-rose-400/30 flex justify-between text-rose-100 text-sm">
                <span>Collections Found</span>
                <span class="font-bold"><?= count($results) ?> Payments</span>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-center">
            <span class="text-gray-400 text-xs font-bold uppercase tracking-widest block mb-1">Avg. Fine per Payer</span>
            <h2 class="text-2xl font-bold text-gray-800">
                <?= count($results) > 0 ? formatCurrency($total_fines / count($results)) : '₹ 0.00' ?>
            </h2>
            <p class="text-[10px] text-gray-500 mt-1 uppercase">Across selected date range</p>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-center border-l-4 border-l-rose-500">
            <span class="text-gray-400 text-xs font-bold uppercase tracking-widest block mb-1">Highest Fine Collected</span>
            <h2 class="text-2xl font-bold text-rose-600">
                <?php 
                    $max = 0; 
                    foreach($results as $r) if($r['fine_amount'] > $max) $max = $r['fine_amount'];
                    echo formatCurrency($max);
                ?>
            </h2>
            <p class="text-[10px] text-gray-500 mt-1 uppercase">Single Transaction Peak</p>
        </div>
    </div>

    <!-- Details Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="py-4 px-6 text-xs font-bold text-gray-500 uppercase tracking-widest">Paid Date</th>
                        <th class="py-4 px-6 text-xs font-bold text-gray-500 uppercase tracking-widest">Account Details</th>
                        <th class="py-4 px-6 text-xs font-bold text-gray-500 uppercase tracking-widest">Due Date</th>
                        <th class="py-4 px-6 text-xs font-bold text-gray-500 uppercase tracking-widest text-right">Fine Amount</th>
                        <th class="py-4 px-6 text-xs font-bold text-gray-500 uppercase tracking-widest text-right no-print">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if(count($results) > 0): ?>
                        <?php foreach($results as $row): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="py-4 px-6 text-sm font-medium text-gray-800 font-mono"><?= date('d M Y', strtotime($row['paid_date'])) ?></td>
                            <td class="py-4 px-6">
                                <span class="block text-sm font-bold text-gray-900"><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></span>
                                <span class="text-[10px] text-indigo-500 font-bold bg-indigo-50 px-1.5 py-0.5 rounded"><?= $row['account_no'] ?></span>
                            </td>
                            <td class="py-4 px-6 text-sm text-gray-500"><?= date('d M Y', strtotime($row['due_date'])) ?></td>
                            <td class="py-4 px-6 text-right">
                                <span class="text-sm font-black text-rose-600"><?= formatCurrency($row['fine_amount']) ?></span>
                            </td>
                            <td class="py-4 px-6 text-right no-print">
                                <a href="../accounts/statement.php?id=<?= $row['account_id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-bold text-xs uppercase tracking-widest underline decoration-2 underline-offset-4">Statement</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="py-20 text-center text-gray-400">
                                <i class="ph ph-desert text-5xl mb-4 block opacity-20"></i>
                                <p class="text-sm">No fine collections recorded in this period.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
