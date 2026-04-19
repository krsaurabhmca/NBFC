<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$advisor_id = isset($_GET['advisor_id']) ? (int)$_GET['advisor_id'] : 0;

$where = "WHERE ls.status = 'Overdue'";
if ($advisor_id > 0) {
    $where .= " AND u.id = $advisor_id";
}

$sql = "SELECT m.first_name, m.last_name, m.phone, m.member_no, a.account_no, 
               COUNT(ls.id) as overdue_installments, 
               SUM(ls.emi_amount) as total_overdue_emi, 
               SUM(ls.fine_amount) as total_fine,
               u.name as advisor_name
        FROM loan_schedules ls
        JOIN accounts a ON ls.account_id = a.id
        JOIN members m ON a.member_id = m.id
        LEFT JOIN users u ON a.referred_by = u.id
        $where
        GROUP BY ls.account_id
        ORDER BY total_overdue_emi DESC";
$result = mysqli_query($conn, $sql);

// Fetch Advisors for filter
$advisors = mysqli_query($conn, "SELECT id, name FROM users WHERE role = 'advisor' AND status = 'active'");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-warning-octagon text-rose-500 text-3xl"></i> Loan Defaulters Report
            </h1>
            <p class="text-gray-500 text-sm mt-1">List of members with overdue EMI payments and accumulated fines.</p>
        </div>
        <button onclick="window.print()" class="bg-gray-800 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm flex items-center gap-1">
            <i class="ph ph-printer"></i> Print Defaulter List
        </button>
    </div>

    <!-- Filter Form -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-6 font-medium">
        <form method="GET" action="" class="flex items-end gap-4">
            <div class="flex-1">
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Filter by Advisor (Collector)</label>
                <select name="advisor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-rose-500 outline-none">
                    <option value="0">-- All Field Advisors --</option>
                    <?php while($adv = mysqli_fetch_assoc($advisors)): ?>
                        <option value="<?= $adv['id'] ?>" <?= $advisor_id == $adv['id'] ? 'selected' : '' ?>><?= htmlspecialchars($adv['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white font-bold px-6 py-2 rounded-lg text-sm transition-all shadow-md flex items-center gap-2">
                 <i class="ph ph-magnifying-glass"></i> Filter
            </button>
        </form>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-rose-50 text-rose-700 text-[10px] uppercase tracking-widest">
                        <th class="px-6 py-4 font-black">Customer Details</th>
                        <th class="px-6 py-4 font-black">Account / Advisor</th>
                        <th class="px-6 py-4 font-black text-center">Overdue Count</th>
                        <th class="px-6 py-4 font-black text-right">Overdue EMI</th>
                        <th class="px-6 py-4 font-black text-right">Fine</th>
                        <th class="px-6 py-4 font-black text-right print:hidden">Total Due</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <?php 
                    $total_overdue = 0;
                    $total_fine = 0;
                    if(mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): 
                            $total_overdue += $row['total_overdue_emi'];
                            $total_fine += $row['total_fine'];
                            $total_row = $row['total_overdue_emi'] + $row['total_fine'];
                        ?>
                            <tr class="hover:bg-rose-50/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-gray-800"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                    <div class="text-[10px] text-gray-400 uppercase tracking-tighter">ID: <?= htmlspecialchars($row['member_no']) ?> | Mob: <?= htmlspecialchars($row['phone']) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-indigo-600 font-mono font-medium"><?= htmlspecialchars($row['account_no']) ?></div>
                                    <div class="text-xs text-gray-500 italic">Collector: <?= htmlspecialchars($row['advisor_name'] ?? 'Self') ?></div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="bg-rose-100 text-rose-700 px-2 py-0.5 rounded-full text-xs font-bold"><?= $row['overdue_installments'] ?> EMIs</span>
                                </td>
                                <td class="px-6 py-4 text-right font-bold text-gray-700">
                                    <?= formatCurrency($row['total_overdue_emi']) ?>
                                </td>
                                <td class="px-6 py-4 text-right font-bold text-amber-600">
                                    <?= formatCurrency($row['total_fine']) ?>
                                </td>
                                <td class="px-6 py-4 text-right font-black text-rose-600 text-lg print:hidden">
                                    <?= formatCurrency($total_row) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <tr class="bg-rose-900 text-white font-black">
                            <td colspan="3" class="px-6 py-4 text-right uppercase tracking-[0.2em] text-xs">Gross NPA / Total Overdue Portfolio</td>
                            <td class="px-6 py-4 text-right"><?= formatCurrency($total_overdue) ?></td>
                            <td class="px-6 py-4 text-right"><?= formatCurrency($total_fine) ?></td>
                            <td class="px-6 py-4 text-right text-xl print:hidden"><?= formatCurrency($total_overdue + $total_fine) ?></td>
                        </tr>
                    <?php else: ?>
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <i class="ph ph-check-circle text-4xl text-emerald-500 mb-2 block"></i>
                            Excellent! No defaulters found in the current filtering.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
