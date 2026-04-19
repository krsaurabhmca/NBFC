<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

if($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access Denied. Admins only.";
    header("Location: ../index.php");
    exit();
}

$from_date = isset($_GET['from_date']) ? sanitize($conn, $_GET['from_date']) : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? sanitize($conn, $_GET['to_date']) : date('Y-m-d');
$staff_id = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;

$where = "WHERE c.created_at BETWEEN '$from_date 00:00:00' AND '$to_date 23:59:59'";
if($staff_id > 0) $where .= " AND c.user_id = $staff_id";

$sql = "SELECT c.*, u.name as staff_name, u.role as staff_role, a.account_no, m.first_name, m.last_name 
        FROM commissions c
        JOIN users u ON c.user_id = u.id
        JOIN accounts a ON c.account_id = a.id
        JOIN members m ON a.member_id = m.id
        $where
        ORDER BY c.created_at DESC";
$result = mysqli_query($conn, $sql);

// Summary by Staff
$summary_sql = "SELECT u.name, SUM(CASE WHEN c.type='Disbursal' THEN c.amount ELSE 0 END) as disb_total,
                       SUM(CASE WHEN c.type='Collection' THEN c.amount ELSE 0 END) as coll_total,
                       SUM(c.amount) as grand_total
                FROM commissions c
                JOIN users u ON c.user_id = u.id
                $where
                GROUP BY c.user_id
                ORDER BY grand_total DESC";
$summary_res = mysqli_query($conn, $summary_sql);

$staff_list = mysqli_query($conn, "SELECT id, name FROM users WHERE status = 'active' ORDER BY name ASC");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight flex items-center gap-3">
                <i class="ph ph-hand-coins text-amber-500"></i> Staff Commission Report
            </h1>
            <p class="text-slate-500 text-sm font-medium mt-1">Track earnings for advisors and collectors based on disbursals and recovery.</p>
        </div>
        <button onclick="window.print()" class="px-6 py-3 bg-slate-900 text-white rounded-2xl text-xs font-black uppercase shadow-xl transition-all flex items-center gap-2 print:hidden">
            <i class="ph ph-printer text-lg"></i> Print Statement
        </button>
    </div>

    <!-- Filters -->
    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 print:hidden">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">From Date</label>
                <input type="date" name="from_date" value="<?= $from_date ?>" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-2.5 font-bold text-slate-800 outline-none focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all">
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">To Date</label>
                <input type="date" name="to_date" value="<?= $to_date ?>" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-2.5 font-bold text-slate-800 outline-none focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all">
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Staff Member</label>
                <select name="staff_id" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-2.5 font-bold text-slate-800 outline-none focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all">
                    <option value="0">-- All Staff --</option>
                    <?php while($s = mysqli_fetch_assoc($staff_list)): ?>
                        <option value="<?= $s['id'] ?>" <?= $staff_id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="h-[46px] bg-indigo-600 hover:bg-slate-900 text-white rounded-xl font-black text-xs uppercase transition-all shadow-lg shadow-indigo-100 flex items-center justify-center gap-2">
                <i class="ph ph-funnel text-lg"></i> Filter Results
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Summary Table -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-slate-900 rounded-[2rem] p-8 text-white shadow-2xl">
                <h3 class="text-xs font-black text-indigo-300 uppercase tracking-[0.2em] mb-6">Staff Earnings Summary</h3>
                <div class="space-y-4">
                    <?php 
                    $grand_total = 0;
                    while($sum = mysqli_fetch_assoc($summary_res)): 
                        $grand_total += $sum['grand_total'];
                    ?>
                    <div class="flex justify-between items-center pb-4 border-b border-white/5 last:border-0 last:pb-0">
                        <div>
                            <p class="font-black text-sm"><?= htmlspecialchars($sum['name']) ?></p>
                            <p class="text-[10px] text-indigo-400 font-bold uppercase tracking-widest mt-0.5">
                                Disb: <?= formatCurrency($sum['disb_total']) ?> | Coll: <?= formatCurrency($sum['coll_total']) ?>
                            </p>
                        </div>
                        <span class="text-sm font-black text-indigo-200"><?= formatCurrency($sum['grand_total']) ?></span>
                    </div>
                    <?php endwhile; ?>
                    
                    <div class="pt-6 mt-6 border-t border-indigo-500/30 flex justify-between items-center">
                        <span class="text-xs font-black uppercase tracking-widest text-indigo-400">Total Payouts</span>
                        <span class="text-xl font-black text-white"><?= formatCurrency($grand_total) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Log -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-8 py-5 border-b border-slate-50 flex items-center justify-between">
                    <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                        <i class="ph ph-list-numbers text-indigo-500"></i> Commission Audit Log
                    </h3>
                </div>
                <div class="overflow-x-auto max-h-[600px]">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="text-slate-400 font-bold uppercase border-b border-slate-50 bg-slate-50/50 sticky top-0 z-10">
                                <th class="px-8 py-4">Staff & Date</th>
                                <th class="px-8 py-4">Customer / Loan</th>
                                <th class="px-8 py-4">Type</th>
                                <th class="px-8 py-4 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php if(mysqli_num_rows($result) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($result)): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-8 py-5">
                                        <div class="font-black text-slate-800"><?= htmlspecialchars($row['staff_name']) ?></div>
                                        <div class="text-[9px] text-slate-400 font-bold mt-1"><?= date('d M, Y h:i A', strtotime($row['created_at'])) ?></div>
                                    </td>
                                    <td class="px-8 py-5">
                                        <div class="font-bold text-slate-700"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                        <div class="text-[10px] text-indigo-500 font-mono"><?= $row['account_no'] ?></div>
                                    </td>
                                    <td class="px-8 py-5">
                                        <?php $is_coll = $row['type'] == 'Collection'; ?>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest <?= $is_coll ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-indigo-50 text-indigo-600 border border-indigo-100' ?>">
                                            <?= $row['type'] ?>
                                        </span>
                                    </td>
                                    <td class="px-8 py-5 text-right font-black text-slate-800 text-sm">
                                        <?= formatCurrency($row['amount']) ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="px-8 py-10 text-center text-slate-400 font-bold uppercase">No commission records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
