<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

if($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access Denied. Admins only.";
    header("Location: ../index.php");
    exit();
}

$from_date = isset($_GET['from_date']) ? sanitize($conn, $_GET['from_date']) : date('Y-m-d', strtotime('-30 days'));
$to_date = isset($_GET['to_date']) ? sanitize($conn, $_GET['to_date']) : date('Y-m-d');

$where = "WHERE t.status = 'Cancelled' AND DATE(t.cancelled_at) BETWEEN '$from_date' AND '$to_date'";

$sql = "SELECT t.*, a.account_no, m.first_name, m.last_name, u.name as voided_by 
        FROM transactions t 
        JOIN accounts a ON t.account_id = a.id 
        JOIN members m ON a.member_id = m.id 
        LEFT JOIN users u ON t.cancelled_by = u.id 
        $where 
        ORDER BY t.cancelled_at DESC";
$result = mysqli_query($conn, $sql);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-trash text-rose-500 text-3xl"></i> Cancelled Receipts List
            </h1>
            <p class="text-gray-500 text-sm mt-1">Audit log of all voided payments and reverted installments.</p>
        </div>
        <button onclick="window.print()" class="bg-gray-800 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors shadow-sm flex items-center gap-1">
            <i class="ph ph-printer"></i> Print Audit Log
        </button>
    </div>

    <!-- Filter Form -->
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 mb-6 font-bold">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div class="md:col-span-1">
                <label class="block text-[10px] font-black text-gray-400 uppercase mb-1 tracking-widest">Cancelled From</label>
                <input type="date" name="from_date" value="<?= $from_date ?>" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-rose-500 outline-none">
            </div>
            <div class="md:col-span-1">
                <label class="block text-[10px] font-black text-gray-400 uppercase mb-1 tracking-widest">Cancelled To</label>
                <input type="date" name="to_date" value="<?= $to_date ?>" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-rose-500 outline-none">
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="w-full bg-rose-600 hover:bg-rose-700 text-white font-black py-2 rounded-xl text-sm transition-all shadow-md flex items-center justify-center gap-2">
                    <i class="ph ph-magnifying-glass"></i> View Voided Items
                </button>
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 text-[10px] uppercase tracking-widest">
                        <th class="px-6 py-4 font-black">Voided Date & By</th>
                        <th class="px-6 py-4 font-black">Original Details</th>
                        <th class="px-6 py-4 font-black">Amount</th>
                        <th class="px-6 py-4 font-black">Reason for Cancellation</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr class="hover:bg-rose-50/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="text-gray-800 font-bold"><?= date('d M Y, h:i A', strtotime($row['cancelled_at'])) ?></div>
                                    <div class="text-[10px] text-rose-500 uppercase font-black tracking-tighter">By: <?= htmlspecialchars($row['voided_by'] ?? 'System') ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-gray-800"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                    <div class="text-[11px] text-gray-400 font-mono"><?= $row['account_no'] ?> | <?= $row['transaction_id'] ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-black text-rose-600"><?= formatCurrency($row['amount']) ?></div>
                                    <div class="text-[9px] text-gray-400 uppercase font-black">Voided Payment</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-gray-600 italic text-xs leading-relaxed max-w-xs">
                                        "<?= htmlspecialchars($row['cancel_remarks']) ?>"
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-16 text-center text-gray-400 font-bold uppercase tracking-widest text-xs">
                                <i class="ph ph-check-circle text-4xl mb-2 text-emerald-200 block"></i>
                                No cancelled receipts found in this period.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
