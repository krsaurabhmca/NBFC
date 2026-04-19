<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$sql = "SELECT a.*, s.scheme_name, m.first_name, m.last_name, m.member_no, m.phone, m.address, m.aadhar_no, u.name as advisor_name
        FROM accounts a 
        JOIN members m ON a.member_id = m.id 
        JOIN schemes s ON a.scheme_id = s.id
        LEFT JOIN users u ON a.referred_by = u.id
        WHERE a.id = $id";
$res = mysqli_query($conn, $sql);
$acc = mysqli_fetch_assoc($res);

if(!$acc) {
    die("Account record not found.");
}

// Fetch Schedules if Loan
$schedules = [];
if($acc['account_type'] == 'Loan') {
    $sch_res = mysqli_query($conn, "SELECT * FROM loan_schedules WHERE account_id = $id ORDER BY installment_no ASC");
    while($s = mysqli_fetch_assoc($sch_res)) {
        $schedules[] = $s;
    }
}

// Recent Ledger
$txns = mysqli_query($conn, "SELECT * FROM transactions WHERE account_id = $id AND (status IS NULL OR status != 'Cancelled') ORDER BY transaction_date DESC LIMIT 10");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto space-y-6">
    <!-- Account Header Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 md:p-8 bg-gradient-to-r from-slate-800 to-slate-900 text-white">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="flex items-center gap-5">
                    <div class="w-16 h-16 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center text-3xl">
                        <i class="ph ph-bank"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-black tracking-tight"><?= htmlspecialchars($acc['account_no']) ?></h1>
                            <span class="bg-indigo-500 text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded"><?= $acc['account_type'] ?> Management</span>
                        </div>
                        <p class="text-slate-400 font-medium mt-1">
                            Issued to <span class="text-white"><?= htmlspecialchars($acc['first_name'].' '.$acc['last_name']) ?></span> 
                            (<?= $acc['member_no'] ?>)
                        </p>
                    </div>
                </div>
                <div class="text-left md:text-right">
                    <p class="text-slate-400 text-[10px] uppercase font-bold tracking-widest mb-1">Current Ledger Balance</p>
                    <h2 class="text-4xl font-black tracking-tighter">
                        <?= formatCurrency(abs($acc['current_balance'])) ?>
                        <span class="text-sm font-normal text-slate-500 ml-1"><?= $acc['current_balance'] < 0 ? 'DUE' : 'CR' ?></span>
                    </h2>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-gray-100 border-b">
            <div class="p-5 text-center">
                <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">Opening Date</p>
                <p class="text-sm font-bold text-gray-800"><?= date('d M Y', strtotime($acc['opening_date'])) ?></p>
            </div>
            <div class="p-5 text-center">
                <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">Interest Rate</p>
                <p class="text-sm font-bold text-gray-800"><?= $acc['interest_rate'] ?>% P.A.</p>
            </div>
            <div class="p-5 text-center">
                <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">Scheme Name</p>
                <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($acc['scheme_name']) ?></p>
            </div>
            <div class="p-5 text-center">
                <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">Status</p>
                <span class="inline-block px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest <?= $acc['status'] == 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>">
                    <?= $acc['status'] ?>
                </span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Details & Customer Sidebar -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h3 class="font-black text-gray-800 text-[11px] uppercase tracking-widest mb-4 border-b border-gray-50 pb-3 flex items-center gap-2">
                    <i class="ph ph-user-focus text-indigo-500"></i> Customer Profile
                </h3>
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400"><i class="ph ph-phone"></i></div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold uppercase mb-0.5">Contact No</p>
                            <p class="text-sm font-bold text-gray-800"><?= $acc['phone'] ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400"><i class="ph ph-identification-card"></i></div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold uppercase mb-0.5">KYC Verified ID (Aadhar)</p>
                            <p class="text-sm font-bold text-gray-800 font-mono"><?= $acc['aadhar_no'] ?></p>
                        </div>
                    </div>
                    <?php if($acc['advisor_name']): ?>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-500"><i class="ph ph-user-gear"></i></div>
                        <div>
                            <p class="text-[10px] text-indigo-400 font-bold uppercase mb-0.5">Responsible Advisor</p>
                            <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($acc['advisor_name']) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if($acc['account_type'] == 'Loan'): 
                $total_interest = (float)$acc['principal_amount'] * ($acc['interest_rate']/100) * ($acc['tenure_months']/12);
                $total_payable = $acc['principal_amount'] + $total_interest;
            ?>
            <div class="bg-slate-900 rounded-2xl shadow-xl border border-slate-700 p-6 text-white relative overflow-hidden group">
                <div class="relative z-10">
                    <h3 class="font-black text-slate-400 text-[10px] uppercase tracking-widest mb-4 border-b border-slate-700 pb-3 flex items-center gap-2">
                        <i class="ph ph-lightning text-amber-500"></i> Financial Breakdown
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-500 font-bold uppercase">Principal Amount</span>
                            <span class="font-black"><?= formatCurrency($acc['principal_amount']) ?></span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-500 font-bold uppercase">Component Interest</span>
                            <span class="font-black text-amber-400">+ <?= formatCurrency($total_interest) ?></span>
                        </div>
                        <div class="pt-2 mt-2 border-t border-slate-800 flex justify-between items-center">
                            <span class="text-[10px] font-black uppercase text-indigo-400">Total Contract Value</span>
                            <span class="text-lg font-black tracking-tight"><?= formatCurrency($total_payable) ?></span>
                        </div>
                    </div>
                </div>
                <i class="ph ph-chart-pie text-6xl text-white/5 absolute -right-4 -bottom-4 group-hover:scale-110 transition-transform"></i>
            </div>
            <?php endif; ?>

            <?php if($acc['account_type'] == 'Loan' && $acc['guarantor_name']): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h3 class="font-black text-gray-800 text-[11px] uppercase tracking-widest mb-4 border-b border-gray-50 pb-3 flex items-center gap-2">
                    <i class="ph ph-shield-check text-rose-500"></i> Guarantee Info
                </h3>
                <div class="space-y-3">
                    <p class="text-sm"><strong>Name:</strong> <?= htmlspecialchars($acc['guarantor_name']) ?></p>
                    <p class="text-sm"><strong>Phone:</strong> <?= htmlspecialchars($acc['guarantor_phone']) ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Main Content (Schedules or Transaction History) -->
        <div class="lg:col-span-2 space-y-6">
            <?php if($acc['account_type'] == 'Loan'): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-50 flex items-center justify-between">
                    <h3 class="font-black text-gray-800 text-[11px] uppercase tracking-widest flex items-center gap-2">
                         <i class="ph ph-calendar-check text-emerald-500"></i> Repayment Schedule
                    </h3>
                    <div class="flex gap-2">
                        <a href="../loans/pay.php?account_id=<?= $id ?>" class="bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-lg shadow-emerald-50 transition-all hover:bg-emerald-700">Quick Pay EMI</a>
                    </div>
                </div>
                <div class="max-h-[400px] overflow-y-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-gray-50/50 text-[10px] text-gray-400 uppercase tracking-widest">
                                <th class="px-6 py-3 font-bold">Inst #</th>
                                <th class="px-6 py-3 font-bold">Due Date</th>
                                <th class="px-6 py-3 text-right font-bold">Amount</th>
                                <th class="px-6 py-3 text-center font-bold">Status</th>
                                <th class="px-6 py-3 text-right font-bold">Paid On</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 text-xs">
                            <?php foreach($schedules as $s): ?>
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-3 font-bold text-gray-400"><?= $s['installment_no'] ?></td>
                                    <td class="px-6 py-3 font-medium text-gray-800"><?= date('d M Y', strtotime($s['due_date'])) ?></td>
                                    <td class="px-6 py-3 text-right font-black text-indigo-900"><?= formatCurrency($s['emi_amount'] + $s['fine_amount']) ?></td>
                                    <td class="px-6 py-3 text-center">
                                        <?php if($s['status'] == 'Paid'): ?>
                                            <span class="text-emerald-500 font-black uppercase text-[9px] tracking-widest">CLEARED</span>
                                        <?php elseif($s['status'] == 'Overdue'): ?>
                                            <span class="text-rose-600 font-black uppercase text-[9px] tracking-widest">OVERDUE</span>
                                        <?php else: ?>
                                            <span class="text-amber-500 font-black uppercase text-[9px] tracking-widest">PENDING</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-3 text-right text-gray-400">
                                        <?= $s['paid_date'] ? date('d/m/y', strtotime($s['paid_date'])) : '-' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Ledger -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-50 flex items-center justify-between">
                    <h3 class="font-black text-gray-800 text-[11px] uppercase tracking-widest flex items-center gap-2">
                         <i class="ph ph-article text-indigo-500"></i> Detailed Audit Ledger
                    </h3>
                    <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Last 10 Transactions</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-gray-50/50 text-[10px] text-gray-400 uppercase tracking-widest">
                                <th class="px-6 py-3 font-bold">Transaction ID / Date</th>
                                <th class="px-6 py-3 font-bold">Type</th>
                                <th class="px-6 py-3 text-right font-bold">Flow</th>
                                <th class="px-6 py-3 text-right font-bold">Ledger Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 text-xs text-slate-700">
                            <?php while($t = mysqli_fetch_assoc($txns)): ?>
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-800"><?= htmlspecialchars($t['transaction_id']) ?></div>
                                        <div class="text-[9px] text-gray-400 mt-1"><?= date('d M Y h:i A', strtotime($t['transaction_date'])) ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-[9px] font-black uppercase tracking-widest text-indigo-500 bg-indigo-50 px-1.5 py-0.5 rounded"><?= $t['transaction_type'] ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="font-bold <?= in_array($t['transaction_type'], ['EMI','Deposit','Interest']) ? 'text-emerald-600' : 'text-rose-600' ?>">
                                            <?= in_array($t['transaction_type'], ['EMI','Deposit','Interest']) ? '+' : '-' ?><?= formatCurrency($t['amount']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right font-black text-slate-900">
                                        <div class="flex items-center justify-end gap-3">
                                            <span><?= formatCurrency(abs($t['balance_after'])) ?></span>
                                            <?php if(($t['status'] ?? '') != 'Cancelled' && $t['transaction_type'] == 'EMI' && $_SESSION['role'] == 'admin'): ?>
                                                <a href="javascript:void(0)" 
                                                   onclick="openCancelModal('<?= $t['transaction_id'] ?>', '<?= formatCurrency($t['amount']) ?>')"
                                                   class="text-rose-400 hover:text-rose-600 transition-colors" title="Cancel/Void Payment">
                                                    <i class="ph ph-trash"></i>
                                                </a>
                                            <?php elseif(($t['status'] ?? '') == 'Cancelled'): ?>
                                                <i class="ph ph-prohibit text-rose-300" title="VOIDED: <?= htmlspecialchars($t['cancel_remarks'] ?? 'No remarks') ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancellation Modal -->
<div id="cancelModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all border border-white/20">
        <div class="px-6 py-8">
            <div class="w-16 h-16 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-6">
                <i class="ph ph-warning-circle"></i>
            </div>
            <h3 class="text-xl font-black text-center text-gray-800 mb-2">Cancel EMI Receipt?</h3>
            <p class="text-center text-gray-500 text-sm mb-8">This will void transaction <span id="modalTxnId" class="font-bold text-gray-800"></span> for <span id="modalAmount" class="font-bold text-gray-800"></span> and revert the installment to pending.</p>
            
            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Cancellation Reason <span class="text-rose-500">*</span></label>
            <textarea id="cancelReason" placeholder="Enter reason for cancellation (required)..." class="w-full h-32 px-4 py-3 bg-gray-50 border-0 rounded-2xl text-sm focus:ring-2 focus:ring-rose-500 outline-none transition-all resize-none"></textarea>
            
            <div class="grid grid-cols-2 gap-4 mt-8">
                <button onclick="closeCancelModal()" class="px-6 py-3 rounded-2xl text-sm font-bold text-gray-400 hover:bg-gray-100 transition-all">Go Back</button>
                <button onclick="submitCancellation()" class="px-6 py-3 rounded-2xl text-sm font-bold bg-rose-600 text-white shadow-xl shadow-rose-200 hover:bg-rose-700 active:scale-95 transition-all">Confirm Cancel</button>
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
