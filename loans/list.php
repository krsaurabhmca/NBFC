<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$where = "WHERE a.account_type = 'Loan'";
$search = isset($_GET['search']) ? sanitize($conn, $_GET['search']) : '';
if($search) {
    $where .= " AND (a.account_no LIKE '%$search%' OR m.first_name LIKE '%$search%' OR m.last_name LIKE '%$search%')";
}
$status_filter = isset($_GET['status']) ? sanitize($conn, $_GET['status']) : '';
if($status_filter) {
    $where .= " AND LOWER(a.status) = LOWER('$status_filter')";
} else {
    // Default: Show Only Active/Operational Loans
    $where .= " AND LOWER(a.status) = 'active'";
}

$sql = "SELECT a.*, m.first_name, m.last_name, m.member_no, 
        (SELECT SUM(emi_amount + fine_amount) FROM loan_schedules WHERE account_id = a.id AND status IN ('Pending', 'Overdue')) as total_due,
        (SELECT COUNT(*) FROM loan_schedules WHERE account_id = a.id AND status = 'Overdue') as overdue_count
        FROM accounts a 
        LEFT JOIN members m ON a.member_id = m.id 
        $where 
        ORDER BY a.id DESC";
$result = mysqli_query($conn, $sql);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-slate-800 tracking-tight">
                <?= $status_filter == 'pending_approval' ? 'Loan Sanction Queue' : 'Operational Loan Book' ?>
            </h1>
            <p class="text-slate-500 text-[10px] font-bold uppercase tracking-widest mt-0.5">
                <?= $status_filter == 'pending_approval' ? 'Pending Disbursal & Credit Review List' : 'Real-time monitoring of active credit portfolio' ?>
            </p>
        </div>
        
        <div class="flex items-center gap-2">
            <form action="" method="GET" class="relative group">
                <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-indigo-500 transition-colors"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search Loan No / Name..." class="pl-10 pr-4 py-2 border rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none w-64 bg-white transition-all shadow-sm">
            </form>
            <a href="disburse.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-sm font-bold transition-all shadow-lg shadow-indigo-100 flex items-center gap-2">
                <i class="ph ph-plus-circle text-lg"></i> New Disbursal
            </a>
        </div>
    </div>

    <?= displayAlert() ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-left font-bold border-b border-gray-100">
                    <th class="px-6 py-3">Loan Details</th>
                    <th class="px-6 py-3">Customer</th>
                    <th class="px-6 py-3 text-right">Sanctioned</th>
                    <th class="px-6 py-3 text-right">Balance Due</th>
                    <th class="px-6 py-3 text-center">Status</th>
                    <th class="px-6 py-3 text-right">Control</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if(mysqli_num_rows($result) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr class="hover:bg-indigo-50/20 transition-colors group">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-gray-50 text-gray-400 flex items-center justify-center text-xl group-hover:bg-white group-hover:text-indigo-500 transition-all border border-transparent group-hover:border-indigo-100 shadow-sm">
                                    <i class="ph ph-briefcase"></i>
                                </div>
                                <div>
                                    <span class="font-mono font-bold text-gray-900"><?= $row['account_no'] ?></span>
                                    <span class="block text-[10px] text-gray-400 uppercase tracking-widest"><?= date('d M Y', strtotime($row['opening_date'])) ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-gray-800"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                            <div class="text-[10px] text-gray-400 uppercase font-medium"><?= $row['member_no'] ?></div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="font-bold text-gray-900"><?= formatCurrency($row['principal_amount']) ?></div>
                            <div class="text-[10px] text-gray-400"><?= $row['tenure_months'] ?> Months (<?= $row['interest_rate'] ?>%)</div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="font-black text-rose-600"><?= formatCurrency($row['total_due']) ?></div>
                            <?php if($row['overdue_count'] > 0): ?>
                                <span class="bg-rose-100 text-rose-700 text-[8px] font-black px-1.5 py-0.5 rounded uppercase tracking-tighter"><?= $row['overdue_count'] ?> EMI Late</span>
                            <?php else: ?>
                                <span class="text-[9px] text-emerald-500 font-bold uppercase tracking-widest">Healthy A/c</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <?php
                                $cls = 'bg-gray-100 text-gray-600';
                                if($row['status'] == 'active') $cls = 'bg-emerald-100 text-emerald-700';
                                if($row['status'] == 'pending_approval') $cls = 'bg-amber-100 text-amber-700';
                                if($row['status'] == 'defaulted') $cls = 'bg-rose-100 text-rose-700';
                            ?>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-[0.15em] <?= $cls ?>">
                                <?= str_replace('_', ' ', $row['status']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <?php if($row['status'] == 'pending_approval' && $_SESSION['role'] == 'admin'): ?>
                                    <a href="review.php?id=<?= $row['id'] ?>" class="px-3 py-1.5 bg-indigo-600 text-white rounded-xl flex items-center gap-2 shadow-lg shadow-indigo-100 hover:bg-slate-900 transition-all font-black text-[10px] uppercase tracking-widest" title="Review Complete Dossier">
                                        <i class="ph ph-shield-check text-base"></i> Review & Sanction
                                    </a>
                                <?php else: ?>
                                    <a href="../members/view.php?id=<?= $row['member_id'] ?>" class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg group/btn" title="View Member Profile">
                                        <i class="ph ph-user text-xl group-hover/btn:scale-110 transition-transform"></i>
                                    </a>

                                    <?php if($row['status'] == 'active'): ?>
                                    <a href="pay.php?account_id=<?= $row['id'] ?>" class="p-2 text-emerald-600 hover:bg-emerald-50 rounded-lg group/btn" title="Collect EMI">
                                        <i class="ph ph-hand-coins text-xl group-hover/btn:scale-110 transition-transform"></i>
                                    </a>
                                    <?php endif; ?>

                                    <a href="../accounts/view_details.php?id=<?= $row['id'] ?>" class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg group/btn" title="Loan Particulars">
                                        <i class="ph ph-info text-xl group-hover/btn:scale-110 transition-transform"></i>
                                    </a>
                                    <a href="../accounts/statement.php?id=<?= $row['id'] ?>" target="_blank" class="p-2 text-slate-400 hover:bg-slate-100 rounded-lg" title="Ledger Statement">
                                        <i class="ph ph-file-text text-xl"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                            <i class="ph ph-folder-simple-dashed text-4xl mb-2"></i>
                            <p>No loan accounts found matching your query.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
