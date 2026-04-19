<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$today = date('Y-m-d');
$branch_id = $_SESSION['branch_id'] ?? 1;

// Base query for today's dues and overdue dues
$sql = "SELECT s.*, a.account_no, m.first_name, m.last_name, m.phone, m.member_no 
        FROM loan_schedules s
        JOIN accounts a ON s.account_id = a.id
        JOIN members m ON a.member_id = m.id
        WHERE s.status IN ('Pending', 'Overdue') 
        AND s.due_date <= '$today' 
        AND a.status = 'active'
        " . getBranchWhere('a', false) . "
        ORDER BY s.due_date ASC, m.first_name ASC";
$result = mysqli_query($conn, $sql);

// Summary Stats
$summary = mysqli_fetch_assoc(mysqli_query($conn, "SELECT 
    COUNT(CASE WHEN due_date = '$today' THEN 1 END) as due_today_count,
    SUM(CASE WHEN due_date = '$today' THEN emi_amount ELSE 0 END) as due_today_amount,
    COUNT(CASE WHEN due_date < '$today' THEN 1 END) as overdue_count,
    SUM(CASE WHEN due_date < '$today' THEN emi_amount + fine_amount ELSE 0 END) as overdue_amount
    FROM loan_schedules s JOIN accounts a ON s.account_id = a.id
    WHERE s.status IN ('Pending', 'Overdue') AND a.status = 'active' AND s.due_date <= '$today' " . getBranchWhere('a', false)));

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 bg-white p-8 rounded-xl border border-slate-100 shadow-sm">
        <div class="flex items-center gap-5">
            <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center border border-slate-100 shadow-sm relative">
                <i class="ph ph-calendar-check text-4xl"></i>
                <span class="absolute -top-2 -right-2 w-7 h-7 bg-indigo-600 text-white text-[10px] font-black rounded-full flex items-center justify-center border-4 border-white shadow-lg"><?= $summary['due_today_count'] + $summary['overdue_count'] ?></span>
            </div>
            <div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight">Today's Recovery List</h1>
                <p class="text-slate-500 text-sm font-medium mt-1">Pending payments and fines for: <span class="font-black text-slate-700"><?= date('l, d M Y') ?></span></p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.print()" class="px-5 py-3 bg-white border border-slate-200 text-slate-700 rounded-xl text-xs font-black uppercase hover:bg-slate-50 transition-all flex items-center gap-2">
                <i class="ph ph-list-bullets text-lg"></i> Print Field List
            </button>
        </div>
    </div>

    <!-- Collection Targets -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-indigo-600 rounded-xl p-8 text-white shadow-xl shadow-indigo-100 flex justify-between items-center relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-full -mr-16 -mt-16 group-hover:scale-110 transition-transform"></div>
            <div>
                <p class="text-indigo-200 text-[10px] font-black uppercase tracking-[0.2em] mb-2 leading-none">Fresh Money Today</p>
                <h4 class="text-4xl font-black tracking-tighter"><?= formatCurrency($summary['due_today_amount']) ?></h4>
                <p class="text-xs font-bold text-indigo-200/60 mt-2 uppercase tracking-widest"><?= $summary['due_today_count'] ?> People Pending</p>
            </div>
            <i class="ph ph-clock-counter-clockwise text-5xl opacity-20"></i>
        </div>
        <div class="bg-rose-500 rounded-xl p-8 text-white shadow-xl shadow-rose-100 flex justify-between items-center relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-full -mr-16 -mt-16 group-hover:scale-110 transition-transform"></div>
            <div>
                <p class="text-rose-100 text-[10px] font-black uppercase tracking-[0.2em] mb-2 leading-none">Old Pending & Fine</p>
                <h4 class="text-4xl font-black tracking-tighter"><?= formatCurrency($summary['overdue_amount']) ?></h4>
                 <p class="text-xs font-bold text-rose-100/60 mt-2 uppercase tracking-widest"><?= $summary['overdue_count'] ?> Late Payments</p>
            </div>
            <i class="ph ph-warning-octagon text-5xl opacity-20"></i>
        </div>
    </div>

    <!-- Active Collection List -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-8 border-b border-slate-50 flex items-center justify-between">
            <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                <i class="ph ph-users-three text-indigo-500"></i> Local Collection List
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="text-slate-400 font-bold uppercase border-b border-slate-50 bg-slate-50/50">
                        <th class="px-8 py-4">Customer Details</th>
                        <th class="px-6 py-4">Account No</th>
                        <th class="px-6 py-4">Due Date</th>
                        <th class="px-6 py-4 text-right">Total Due</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-8 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): 
                            $is_overdue = $row['due_date'] < $today;
                             ?>
                        <tr class="hover:bg-slate-50/50 transition-colors <?= $is_overdue ? 'bg-rose-50/20' : '' ?>">
                            <td class="px-8 py-5">
                                <div class="font-black text-slate-800 text-sm"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[9px] font-black text-indigo-500 bg-indigo-50 px-2 py-0.5 rounded"><?= $row['member_no'] ?></span>
                                    <a href="tel:<?= $row['phone'] ?>" class="text-[10px] text-slate-400 font-bold hover:text-indigo-600 flex items-center gap-1">
                                        <i class="ph ph-phone"></i> <?= $row['phone'] ?>
                                    </a>
                                </div>
                            </td>
                            <td class="px-6 py-5 font-mono font-bold text-slate-800"><?= $row['account_no'] ?></td>
                            <td class="px-6 py-5">
                                <div class="font-bold <?= $is_overdue ? 'text-rose-600' : 'text-slate-600' ?>"><?= date('d M, Y', strtotime($row['due_date'])) ?></div>
                                <div class="text-[9px] font-bold text-slate-400 truncate"><?= date('l', strtotime($row['due_date'])) ?></div>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <div class="font-black text-slate-800 text-sm"><?= formatCurrency($row['emi_amount'] + $row['fine_amount']) ?></div>
                                <?php if($row['fine_amount'] > 0): ?>
                                    <div class="text-[9px] text-rose-500 font-bold uppercase tracking-widest">+ Late Fine Added</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <span class="px-2.5 py-1 rounded text-[9px] font-black uppercase tracking-[0.1em] <?= $is_overdue ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700' ?>">
                                    <?= $row['status'] ?>
                                </span>
                            </td>
                            <td class="px-8 py-5 text-right space-x-2 whitespace-nowrap">
                                <a href="tel:<?= $row['phone'] ?>" class="bg-indigo-600 text-white px-4 py-2 rounded-xl font-black uppercase text-[9px] shadow-lg shadow-indigo-100 hover:bg-slate-900 transition-all inline-flex items-center gap-2">
                                    <i class="ph ph-phone-call text-xs"></i> Call Now
                                </a>
                                <a href="pay.php?account_id=<?= $row['account_id'] ?>" class="bg-emerald-600 text-white px-4 py-2 rounded-xl font-black uppercase text-[9px] shadow-lg shadow-emerald-100 hover:bg-slate-900 transition-all inline-flex items-center gap-2">
                                    <i class="ph ph-check-circle text-xs"></i> Receive Money
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="px-8 py-20 text-center">
                            <i class="ph ph-check-circle text-6xl text-emerald-200 mb-4 block"></i>
                            <p class="text-slate-400 font-bold uppercase text-xs tracking-[0.2em]">No Pending Money Found Today!</p>
                            <p class="text-slate-500 text-xs mt-2">All targets reached or no one has a due today.</p>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
