<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$from_date = isset($_GET['from_date']) ? sanitize($conn, $_GET['from_date']) : date('Y-m-d');
$to_date = isset($_GET['to_date']) ? sanitize($conn, $_GET['to_date']) : date('Y-m-d');
$advisor_id = isset($_GET['advisor_id']) ? (int)$_GET['advisor_id'] : 0;
$txn_type = isset($_GET['txn_type']) ? sanitize($conn, $_GET['txn_type']) : '';

$where = "WHERE DATE(t.transaction_date) BETWEEN '$from_date' AND '$to_date'" . getBranchWhere('a', false);
if ($advisor_id > 0) {
    $where .= " AND t.created_by = $advisor_id";
}
// Exclude Cancelled transactions from collection report
$where .= " AND (t.status IS NULL OR t.status != 'Cancelled')";
if ($txn_type) {
    $where .= " AND t.transaction_type = '$txn_type'";
}

$sql = "SELECT t.*, a.account_no, a.account_type, m.first_name, m.last_name, u.name as processed_by 
        FROM transactions t 
        JOIN accounts a ON t.account_id = a.id 
        JOIN members m ON a.member_id = m.id 
        JOIN users u ON t.created_by = u.id 
        $where 
        ORDER BY t.transaction_date DESC";
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
                <i class="ph ph-receipt text-indigo-500 text-3xl"></i> Money Received List
            </h1>
            <p class="text-gray-500 text-sm mt-1">List of all money collected by staff and members.</p>
        </div>
        <button onclick="window.print()" class="bg-gray-800 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm flex items-center gap-1">
            <i class="ph ph-printer"></i> Print Report
        </button>
    </div>

    <!-- Filter Form -->
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 mb-6">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">From Date</label>
                <input type="date" name="from_date" value="<?= $from_date ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">To Date</label>
                <input type="date" name="to_date" value="<?= $to_date ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Advisor</label>
                <select name="advisor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    <option value="0">-- All Advisors --</option>
                    <?php while($adv = mysqli_fetch_assoc($advisors)): ?>
                        <option value="<?= $adv['id'] ?>" <?= $advisor_id == $adv['id'] ? 'selected' : '' ?>><?= htmlspecialchars($adv['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Txn Type</label>
                <select name="txn_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    <option value="">-- All Types --</option>
                    <option value="Deposit" <?= $txn_type == 'Deposit' ? 'selected' : '' ?>>Deposit</option>
                    <option value="EMI" <?= $txn_type == 'EMI' ? 'selected' : '' ?>>EMI Payment</option>
                    <option value="Withdrawal" <?= $txn_type == 'Withdrawal' ? 'selected' : '' ?>>Withdrawal</option>
                    <option value="Fine" <?= $txn_type == 'Fine' ? 'selected' : '' ?>>Fine Collection</option>
                </select>
            </div>
            <div>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 rounded-lg text-sm transition-all shadow-md flex items-center justify-center gap-2">
                    <i class="ph ph-funnel"></i> Search Now
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
                        <th class="px-6 py-4 font-black">Date & Time</th>
                        <th class="px-6 py-4 font-black">Customer / Account</th>
                        <th class="px-6 py-4 font-black">Type</th>
                        <th class="px-6 py-4 font-black">Processed By</th>
                        <th class="px-6 py-4 font-black text-right">Amount</th>
                        <?php if($_SESSION['role'] == 'admin'): ?>
                        <th class="px-6 py-4 font-black text-center">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <?php 
                    $total_amount = 0;
                    if(mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): 
                            $total_amount += $row['amount'];
                        ?>
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="text-gray-800 font-medium"><?= date('d M Y', strtotime($row['transaction_date'])) ?></div>
                                    <div class="text-[10px] text-gray-400"><?= date('h:i A', strtotime($row['transaction_date'])) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-gray-800"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                    <div class="text-xs text-indigo-500 font-mono"><?= $row['account_no'] ?> (<?= $row['account_type'] ?>)</div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                    $color = 'gray';
                                    if(in_array($row['transaction_type'], ['Deposit', 'EMI'])) $color = 'emerald';
                                    if($row['transaction_type'] == 'Withdrawal') $color = 'rose';
                                    if($row['transaction_type'] == 'Fine') $color = 'amber';
                                    ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-<?= $color ?>-100 text-<?= $color ?>-700 border border-<?= $color ?>-200">
                                        <?= $row['transaction_type'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-gray-600"><?= htmlspecialchars($row['processed_by']) ?></div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="font-black text-gray-800 <?= in_array($row['transaction_type'], ['Deposit', 'EMI', 'Fine']) ? 'text-emerald-600' : 'text-rose-600' ?>">
                                        <?= formatCurrency($row['amount']) ?>
                                    </div>
                                </td>
                                <?php if($_SESSION['role'] == 'admin'): ?>
                                <td class="px-6 py-4 text-center">
                                    <?php if($row['transaction_type'] == 'EMI' && $_SESSION['role'] == 'admin'): ?>
                                        <a href="javascript:void(0)" 
                                           onclick="openCancelModal('<?= $row['transaction_id'] ?>', '<?= formatCurrency($row['amount']) ?>')"
                                           class="text-rose-600 hover:bg-rose-50 p-2 rounded-lg transition-colors inline-block" title="Cancel Receipt">
                                            <i class="ph ph-trash text-lg"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-300"><i class="ph ph-prohibit text-lg"></i></span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                        <tr class="bg-gray-900 text-white font-bold">
                            <td colspan="<?= $_SESSION['role'] == 'admin' ? '5' : '4' ?>" class="px-6 py-4 text-right uppercase tracking-widest text-xs">Total Money Received in Period</td>
                            <td class="px-6 py-4 text-right text-lg"><?= formatCurrency($total_amount) ?></td>
                        </tr>
                    <?php else: ?>
                        <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">No transactions found for the selected criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Cancellation Modal -->
<div id="cancelModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all border border-white/20">
        <div class="px-6 py-8">
            <div class="w-16 h-16 bg-rose-50 text-rose-500 rounded-xl flex items-center justify-center text-3xl mx-auto mb-6">
                <i class="ph ph-warning-circle"></i>
            </div>
            <h3 class="text-xl font-black text-center text-gray-800 mb-2">Cancel This Payment?</h3>
            <p class="text-center text-gray-500 text-sm mb-8">This will void transaction <span id="modalTxnId" class="font-bold text-gray-800"></span> for <span id="modalAmount" class="font-bold text-gray-800"></span> and mark EMI as unpaid again.</p>
            
            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Why are you canceling? <span class="text-rose-500">*</span></label>
            <textarea id="cancelReason" placeholder="Enter reason (required)..." class="w-full h-32 px-4 py-3 bg-gray-50 border-0 rounded-xl text-sm focus:ring-2 focus:ring-rose-500 outline-none transition-all resize-none"></textarea>
            
            <div class="grid grid-cols-2 gap-4 mt-8">
                <button onclick="closeCancelModal()" class="px-6 py-3 rounded-xl text-sm font-bold text-gray-400 hover:bg-gray-100 transition-all">Go Back</button>
                <button onclick="submitCancellation()" class="px-6 py-3 rounded-xl text-sm font-bold bg-rose-600 text-white shadow-xl shadow-rose-200 hover:bg-rose-700 active:scale-95 transition-all">Confirm Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentCancelTxnId = null;

function openCancelModal(txnId, amount) {
    currentCancelTxnId = txnId;
    document.getElementById('modalTxnId').innerText = txnId;
    document.getElementById('modalAmount').innerText = amount;
    document.getElementById('cancelModal').classList.remove('hidden');
    document.getElementById('cancelReason').value = '';
    document.getElementById('cancelReason').focus();
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
}

function submitCancellation() {
    const reason = document.getElementById('cancelReason').value.trim();
    if (!reason) {
        alert("Please provide a cancellation reason.");
        return;
    }
    
    // Create a form and submit it to cancel_emi.php
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../loans/cancel_emi.php';
    
    const txnInput = document.createElement('input');
    txnInput.type = 'hidden';
    txnInput.name = 'txn_id';
    txnInput.value = currentCancelTxnId;
    form.appendChild(txnInput);
    
    const reasonInput = document.createElement('input');
    reasonInput.type = 'hidden';
    reasonInput.name = 'cancel_reason';
    reasonInput.value = reason;
    form.appendChild(reasonInput);
    
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require_once '../includes/footer.php'; ?>
