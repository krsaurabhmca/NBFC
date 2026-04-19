<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$error = '';
$account_id = isset($_GET['account_id']) ? (int)$_GET['account_id'] : 0;

// Update Fines first if account selected
if($account_id > 0) {
    calculateAndUpdateFines($conn, $account_id, date('Y-m-d'));
}

// Handle Payment Submission
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pay_emi'])) {
    $p_account_id = (int)$_POST['account_id'];
    $selected_schedules = isset($_POST['schedules']) ? $_POST['schedules'] : [];
    $payment_mode = sanitize($conn, $_POST['payment_mode']);
    $received_by = !empty($_POST['received_by']) ? (int)$_POST['received_by'] : $_SESSION['user_id'];
    $total_discount = (float)$_POST['total_discount'];
    $payment_date = !empty($_POST['payment_date']) ? sanitize($conn, $_POST['payment_date']) : date('Y-m-d');
    
    if(empty($selected_schedules)) {
        $error = "Please select at least one EMI to pay.";
    } else {
        mysqli_query($conn, "START TRANSACTION");
        
        $total_collected = 0;
        $total_principal = 0;
        $total_interest = 0;
        $total_fine = 0;
        
        $acc_res = mysqli_query($conn, "SELECT * FROM accounts WHERE id = $p_account_id FOR UPDATE");
        $acc = mysqli_fetch_assoc($acc_res);
        $comm_pct = (float)$acc['collection_commission_percent'];
        $referred_by = $acc['referred_by'];

        $txn_id = 'PAY-' . time() . rand(10,99);
        foreach($selected_schedules as $sch_id) {
            $sch_id = (int)$sch_id;
            $sch_res = mysqli_query($conn, "SELECT * FROM loan_schedules WHERE id = $sch_id FOR UPDATE");
            $sch = mysqli_fetch_assoc($sch_res);
            
            if($sch['status'] != 'Paid') {
                $emi_base = (float)$sch['emi_amount'];
                $fine = (float)$sch['fine_amount'];
                
                // Proportionally apply discount to fine first, then emi
                $discount_to_apply = min($total_discount, $fine + $emi_base);
                $total_discount -= $discount_to_apply;
                
                $net_paid = ($emi_base + $fine) - $discount_to_apply;
                $total_collected += $net_paid;
                
                $total_principal += $sch['principal_component'];
                $total_interest += $sch['interest_component'];
                $total_fine += $fine;

                // Calculate Comm for THIS EMI
                $comm_amt = 0;
                if($comm_pct > 0) {
                    $comm_amt = round($net_paid * ($comm_pct / 100), 2);
                }

                // Update Schedule
                mysqli_query($conn, "UPDATE loan_schedules SET 
                                     status = 'Paid', 
                                     paid_date = '$payment_date', 
                                     payment_mode = '$payment_mode', 
                                     received_by = $received_by,
                                     discount_amount = $discount_to_apply,
                                     commission_amount = $comm_amt,
                                     transaction_id = '$txn_id',
                                     received_date = NOW()
                                     WHERE id = $sch_id");
                                     
                // Log Commission if applicable
                if($referred_by && $comm_amt > 0) {
                    mysqli_query($conn, "INSERT INTO commissions (user_id, account_id, type, amount, status, reference_id) 
                                         VALUES ($referred_by, $p_account_id, 'Collection', $comm_amt, 'Pending', '$sch_id')");
                }
            }
        }
        // Create Transaction Record
        if($total_collected > 0) {
            // Final balance update (Only subtract the principal/interest part, fines are separate income)
            $capital_reduction = $total_collected - $total_fine;
            mysqli_query($conn, "UPDATE accounts SET current_balance = current_balance + $capital_reduction WHERE id = $p_account_id");
            
            // Fetch Updated Balance for Ledger
            $new_bal_res = mysqli_query($conn, "SELECT current_balance FROM accounts WHERE id = $p_account_id");
            $new_bal = mysqli_fetch_assoc($new_bal_res)['current_balance'] ?? 0;

            $desc = "EMI Payment for " . count($selected_schedules) . " installments.";
            if ($total_fine > 0) $desc .= " (Includes ".formatCurrency($total_fine)." late fine)";
            
            mysqli_query($conn, "INSERT INTO transactions (transaction_id, account_id, transaction_type, amount, balance_after, description, transaction_date, created_by) 
                                 VALUES ('$txn_id', $p_account_id, 'EMI', $total_collected, $new_bal, '$desc', '$payment_date 12:00:00', {$_SESSION['user_id']})");
        }
        
        mysqli_query($conn, "COMMIT");
        $_SESSION['success'] = "Payment Successful! Collected: " . formatCurrency($total_collected);
        header("Location: receipt_emi.php?id=$txn_id");
        exit();
    }
}

// Fetch Loan Accounts
$loans = mysqli_query($conn, "SELECT a.id, a.account_no, m.first_name, m.last_name 
                             FROM accounts a 
                             JOIN members m ON a.member_id = m.id 
                             WHERE a.account_type = 'Loan' AND a.status = 'active'
                             ORDER BY m.first_name ASC");

// Details for selected account
$selected_loan = null;
$schedules = [];
if($account_id > 0) {
    $res = mysqli_query($conn, "SELECT a.*, m.first_name, m.last_name, m.member_no FROM accounts a JOIN members m ON a.member_id = m.id WHERE a.id = $account_id");
    $selected_loan = mysqli_fetch_assoc($res);
    
    $sch_res = mysqli_query($conn, "SELECT * FROM loan_schedules WHERE account_id = $account_id ORDER BY installment_no ASC");
    while($s = mysqli_fetch_assoc($sch_res)) {
        $schedules[] = $s;
    }
}

// Fetch Employees for "Received By"
$employees = mysqli_query($conn, "SELECT id, name FROM users WHERE status = 'active'");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-800">EMI Collection Center</h1>
            <p class="text-gray-500 text-xs">Manage loan repayments, apply discounts, and clear dues.</p>
        </div>
        <form action="" method="GET" class="flex gap-2">
            <select name="account_id" class="select2-init w-64 border rounded-lg px-3 py-2 text-sm" onchange="this.form.submit()">
                <option value="">-- Select Loan Account --</option>
                <?php while($l = mysqli_fetch_assoc($loans)): ?>
                    <option value="<?= $l['id'] ?>" <?= $account_id == $l['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($l['first_name'].' '.$l['last_name']) ?> (<?= $l['account_no'] ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </form>
    </div>

    <?= displayAlert() ?>

    <?php if($selected_loan): ?>
        <form method="POST" action="" id="payForm">
            <input type="hidden" name="account_id" value="<?= $account_id ?>">
            
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Schedule Selection (Main Content) -->
                <div class="lg:col-span-3">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="p-4 bg-gray-50 border-b border-gray-100 flex justify-between items-center">
                            <h3 class="font-bold text-sm uppercase tracking-wider text-gray-600">Loan Schedule & Repayment Status</h3>
                            <div class="text-[10px] space-x-4">
                                <span class="text-rose-600 font-bold"><i class="ph ph-warning-octagon"></i> OVERDUE</span>
                                <span class="text-amber-600 font-bold"><i class="ph ph-clock"></i> PENDING</span>
                                <span class="text-emerald-600 font-bold"><i class="ph ph-check-circle"></i> PAID</span>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-gray-500 text-left font-bold border-b border-gray-100">
                                    <tr>
                                        <th class="px-4 py-2 w-10 text-center">
                                            <input type="checkbox" id="selectAll" class="rounded border-gray-300">
                                        </th>
                                        <th class="px-4 py-2 w-16">#</th>
                                        <th class="px-4 py-2">Due Date</th>
                                        <th class="px-4 py-2 text-right">Base EMI</th>
                                        <th class="px-4 py-2 text-right">Fine</th>
                                        <th class="px-4 py-2 text-right">Total Due</th>
                                        <th class="px-4 py-2 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <?php foreach($schedules as $s): ?>
                                        <tr class="<?= $s['status'] == 'Overdue' ? 'bg-red-50/30' : ($s['status'] == 'Paid' ? 'bg-emerald-50/10' : '') ?>">
                                            <td class="px-4 py-2 text-center">
                                                <?php if($s['status'] != 'Paid'): ?>
                                                    <input type="checkbox" name="schedules[]" value="<?= $s['id'] ?>" 
                                                            data-emi="<?= $s['emi_amount'] ?>"
                                                            data-fine="<?= $s['fine_amount'] ?>"
                                                            class="emi-check rounded border-gray-300 text-indigo-600">
                                                <?php else: ?>
                                                    <i class="ph ph-check-circle text-emerald-500"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-2 font-mono text-gray-400"><?= $s['installment_no'] ?></td>
                                            <td class="px-4 py-2">
                                                <div class="font-medium"><?= date('d M Y', strtotime($s['due_date'])) ?></div>
                                                <div class="text-[10px] text-gray-400 capitalize"><?= date('l', strtotime($s['due_date'])) ?></div>
                                            </td>
                                            <td class="px-4 py-2 text-right font-medium"><?= formatCurrency($s['emi_amount']) ?></td>
                                            <td class="px-4 py-2 text-right text-rose-600 font-bold">
                                                <?= $s['fine_amount'] > 0 ? '+'.formatCurrency($s['fine_amount']) : '-' ?>
                                                <?php if($s['status'] == 'Overdue'): ?>
                                                    <span class="block text-[8px] uppercase tracking-tighter">Late Penalty</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-2 text-right font-black text-indigo-900">
                                                <?= formatCurrency($s['emi_amount'] + $s['fine_amount']) ?>
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                <?php
                                                    $status_cls = 'bg-gray-100 text-gray-600';
                                                    if($s['status'] == 'Paid') $status_cls = 'bg-emerald-100 text-emerald-700';
                                                    if($s['status'] == 'Overdue') $status_cls = 'bg-rose-100 text-rose-700';
                                                    if($s['status'] == 'Pending') $status_cls = 'bg-amber-100 text-amber-700';
                                                ?>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-widest whitespace-nowrap <?= $status_cls ?>">
                                                    <?= $s['status'] ?>
                                                </span>
                                                <?php if($s['status'] == 'Paid'): ?>
                                                    <div class="text-[9px] text-gray-400 mt-0.5"><?= date('d/m/y', strtotime($s['paid_date'])) ?></div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Payment Summary (Sidebar) -->
                <div class="lg:col-span-1 space-y-4">
                    <div class="bg-indigo-900 rounded-xl p-5 text-white shadow-xl">
                        <h3 class="font-bold text-xs uppercase tracking-[0.2em] mb-4 text-indigo-300">Payment Summary</h3>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-indigo-300">Selected Count</span>
                                <span id="summaryCount" class="font-bold">0 EMI(s)</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-indigo-300">Total Base EMI</span>
                                <span id="summaryBase" class="font-bold">₹ 0.00</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-rose-400 font-bold uppercase text-[10px]">Accumulated Fines</span>
                                <span id="summaryFine" class="font-bold text-rose-400">₹ 0.00</span>
                            </div>
                            
                            <div class="pt-3 border-t border-indigo-800">
                                <label class="block text-[10px] font-bold text-indigo-400 uppercase mb-2">Discount / Waiver (₹)</label>
                                <input type="number" step="0.01" name="total_discount" id="discountInput" value="0.00" class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white outline-none focus:ring-2 focus:ring-indigo-400">
                            </div>
                            
                            <div class="flex justify-between items-center pt-3 mt-3 border-t border-indigo-400/30">
                                <div>
                                    <span class="text-indigo-100 font-bold block leading-tight">NET PAYABLE</span>
                                    <span class="text-[9px] text-indigo-400 uppercase">Inclusive of penalties</span>
                                </div>
                                <span id="summaryNet" class="text-2xl font-black text-white">₹ 0.00</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Payment Mode</label>
                            <select name="payment_mode" class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50 focus:bg-white outline-none">
                                <option value="Cash">Cash / Ledger</option>
                                <option value="GPay/UPI">GPay / UPI / PhonePe</option>
                                <option value="Bank Transfer">Bank NEFT/RTGS</option>
                                <option value="Cheque">Cheque Payment</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Received By</label>
                            <select name="received_by" class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50 focus:bg-white outline-none">
                                <?php mysqli_data_seek($employees, 0); while($e = mysqli_fetch_assoc($employees)): ?>
                                    <option value="<?= $e['id'] ?>" <?= $e['id'] == $_SESSION['user_id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Payment Date</label>
                            <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50 focus:bg-white outline-none">
                        </div>

                        <button type="submit" name="pay_emi" id="payBtn" disabled class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl shadow-lg shadow-emerald-50 transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:grayscale">
                            <i class="ph ph-check-circle text-xl"></i>
                            Confirm Payment
                        </button>
                    </div>
                </div>
            </div>
        </form>
    <?php else: ?>
        <div class="bg-white rounded-2xl p-16 border border-gray-100 shadow-sm border-dashed flex flex-col items-center justify-center text-center">
            <div class="w-20 h-20 bg-indigo-50 text-indigo-500 rounded-full flex items-center justify-center text-3xl mb-4">
                <i class="ph ph-magnifying-glass"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-800">No Loan Account Selected</h3>
            <p class="text-gray-500 text-sm max-w-sm mt-1">Please use the account selector at the top right to start processing collections.</p>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checks = document.querySelectorAll('.emi-check');
    const selectAll = document.getElementById('selectAll');
    const summaryCount = document.getElementById('summaryCount');
    const summaryGross = document.getElementById('summaryGross');
    const summaryNet = document.getElementById('summaryNet');
    const discountInput = document.getElementById('discountInput');
    const payBtn = document.getElementById('payBtn');

    function calculate() {
        let count = 0;
        let base = 0;
        let fine = 0;
        checks.forEach(c => {
            if(c.checked) {
                count++;
                base += parseFloat(c.getAttribute('data-emi')) || 0;
                fine += parseFloat(c.getAttribute('data-fine')) || 0;
            }
        });

        const gross = base + fine;
        const discount = parseFloat(discountInput.value) || 0;
        const net = Math.max(0, gross - discount);

        summaryCount.innerText = count + ' EMI(s)';
        document.getElementById('summaryBase').innerText = '₹ ' + base.toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('summaryFine').innerText = '₹ ' + fine.toLocaleString('en-IN', {minimumFractionDigits: 2});
        summaryNet.innerText = '₹ ' + net.toLocaleString('en-IN', {minimumFractionDigits: 2});

        payBtn.disabled = count === 0;
    }

    if(selectAll) {
        selectAll.addEventListener('change', function() {
            checks.forEach(c => c.checked = this.checked);
            calculate();
        });
    }

    checks.forEach(c => c.addEventListener('change', calculate));
    if(discountInput) discountInput.addEventListener('input', calculate);

    // Initial check for params
    calculate();
});
</script>

<?php require_once '../includes/footer.php'; ?>
