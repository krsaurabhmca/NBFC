<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$account_id = isset($_GET['account_id']) ? (int)$_GET['account_id'] : 0;
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_closure'])) {
    $close_account_id = (int)$_POST['account_id'];
    $closure_type = sanitize($conn, $_POST['closure_type']); // 'Matured' or 'Preclosure'
    $final_payout = (float)$_POST['final_payout'];
    $remarks = sanitize($conn, $_POST['remarks']);
    $user_id = $_SESSION['user_id'];
    
    mysqli_query($conn, "START TRANSACTION");
    $acc_res = mysqli_query($conn, "SELECT * FROM accounts WHERE id = $close_account_id FOR UPDATE");
    $acc = mysqli_fetch_assoc($acc_res);
    
    if(!$acc || $acc['status'] == 'closed') {
        $error = "Account is already closed or invalid.";
        mysqli_query($conn, "ROLLBACK");
    } else {
        // Change status to closed, balance to 0
        $sql1 = "UPDATE accounts SET status = 'closed', current_balance = 0 WHERE id = $close_account_id";
        
        $payout_destination = $_POST['payout_destination'] ?? 'external';
        $credit_savings_account_id = (int)($_POST['credit_savings_account_id'] ?? 0);
        $txn_id = 'CLO-' . time() . rand(10,99);
        $now = date('Y-m-d H:i:s');
        $execute_success = false;

        if($payout_destination == 'savings' && $credit_savings_account_id > 0) {
            $desc1 = "Internal Payout ($closure_type) to A/c $credit_savings_account_id - " . $remarks;
            $sql2 = "INSERT INTO transactions (transaction_id, account_id, transaction_type, amount, balance_after, description, transaction_date, created_by) 
                     VALUES ('$txn_id', $close_account_id, 'Withdrawal', $final_payout, 0, '$desc1', '$now', $user_id)";
            
            // Credit savings account
            mysqli_query($conn, "UPDATE accounts SET current_balance = current_balance + $final_payout WHERE id = $credit_savings_account_id");
            $sav_res = mysqli_query($conn, "SELECT current_balance, account_no FROM accounts WHERE id = $credit_savings_account_id");
            $sav_row = mysqli_fetch_assoc($sav_res);
            $new_sav_bal = $sav_row['current_balance'];
            
            $desc2 = "Maturity Credit from A/c $close_account_id ($closure_type)";
            $txn_id2 = 'CRD-' . time() . rand(100,999);
            $sql3 = "INSERT INTO transactions (transaction_id, account_id, transaction_type, amount, balance_after, description, transaction_date, created_by) 
                     VALUES ('$txn_id2', $credit_savings_account_id, 'Deposit', $final_payout, $new_sav_bal, '$desc2', '$now', $user_id)";
                     
            $execute_success = (mysqli_query($conn, $sql1) && mysqli_query($conn, $sql2) && mysqli_query($conn, $sql3));
        } else {
            $desc = "Account Closure Payout ($closure_type) - " . $remarks;
            $sql2 = "INSERT INTO transactions (transaction_id, account_id, transaction_type, amount, balance_after, description, transaction_date, created_by) 
                     VALUES ('$txn_id', $close_account_id, 'Withdrawal', $final_payout, 0, '$desc', '$now', $user_id)";
            
            $execute_success = (mysqli_query($conn, $sql1) && mysqli_query($conn, $sql2));
        }

        if($execute_success) {
            $inserted_txn_id = mysqli_insert_id($conn);
            mysqli_query($conn, "COMMIT");
            $_SESSION['success'] = "Account efficiently closed. Payout processed: " . formatCurrency($final_payout);
            header("Location: ../transactions/receipt.php?id=" . $inserted_txn_id);
            exit();
        } else {
            mysqli_query($conn, "ROLLBACK");
            $error = "Error processing closure: " . mysqli_error($conn);
        }
    }
}

// Fetch Accounts for dropdown (Active or Matured Term Deposits)
$accounts_list = mysqli_query($conn, "SELECT a.id, a.account_no, a.account_type, a.status, m.first_name, m.last_name 
                                     FROM accounts a 
                                     JOIN members m ON a.member_id = m.id 
                                     WHERE a.status IN ('active', 'matured') AND a.account_type IN ('FD', 'RD', 'MIS') 
                                     ORDER BY m.first_name");

$selected_acc = null;
$calc = [];

if($account_id > 0) {
    $res = mysqli_query($conn, "SELECT a.*, m.first_name, m.last_name, m.member_no, s.scheme_name, s.scheme_type, s.interest_rate, s.pre_closure_penalty_percent, s.compounding_frequency 
                                FROM accounts a 
                                JOIN members m ON a.member_id = m.id 
                                JOIN schemes s ON a.scheme_id = s.id 
                                WHERE a.id = $account_id");
    if(mysqli_num_rows($res) > 0) {
        $selected_acc = mysqli_fetch_assoc($res);
        
        // Complex Payout Calculation Logic
        $calc['principal'] = $selected_acc['principal_amount'];
        if(in_array($selected_acc['account_type'], ['RD', 'DD'])) {
            $calc['principal'] = abs($selected_acc['current_balance']); // Actual collected so far via transactions
        }
        
        $calc['is_matured'] = (strtotime($selected_acc['maturity_date']) <= time());
        $calc['closure_type'] = $calc['is_matured'] ? 'Matured Payout' : 'Pre-Closure';
        
        // Months Elapsed
        $d1 = new DateTime($selected_acc['opening_date']);
        $d2 = new DateTime();
        $calc['months_elapsed'] = ($d2->diff($d1)->y * 12) + $d2->diff($d1)->m;
        
        // Rate Applied
        $calc['base_rate'] = $selected_acc['interest_rate'];
        $calc['penalty_deduction'] = 0;
        
        if(!$calc['is_matured']) {
            $calc['penalty_deduction'] = $selected_acc['pre_closure_penalty_percent'];
        }
        
        $calc['applied_rate'] = max(0, $calc['base_rate'] - $calc['penalty_deduction']);
        
        // Calculate accrued interest roughly based on elapsed months
        $r = $calc['applied_rate'] / 100;
        $t = $calc['months_elapsed'] / 12;
        $p = $calc['principal'];
        
        if($selected_acc['account_type'] == 'FD') {
            $calc['interest_accrued'] = $p * pow((1 + $r), $t) - $p; // simplified annual compounding for pre-closure calc
        } elseif($selected_acc['account_type'] == 'RD') {
            // SAFE RD CALCULATION: Sum interest on each deposit from its actual date
            // This prevents bank loss when multiple EMIs are paid in a lump sum at the end
            $txn_q = mysqli_query($conn, "SELECT amount, transaction_date FROM transactions 
                                         WHERE account_id = $account_id AND transaction_type = 'Deposit' 
                                         ORDER BY transaction_date ASC");
            $calc['interest_accrued'] = 0;
            $today_dt = new DateTime();
            
            while($t_row = mysqli_fetch_assoc($txn_q)) {
                $dep_date = new DateTime($t_row['transaction_date']);
                $diff = $today_dt->diff($dep_date);
                $days_held = $diff->days;
                
                // Interest = (Amount * Rate * Days) / 36500
                $calc['interest_accrued'] += ($t_row['amount'] * $calc['applied_rate'] * $days_held) / 36500;
            }
        } else {
            $calc['interest_accrued'] = ($p * $r * $t); // MIS / Simple
        }
        
        $calc['total_payout'] = $p + $calc['interest_accrued'];
        
        // If matured, we use standard maturity norms (assumes on-time payments)
        if($calc['is_matured']) {
            $r_base = $selected_acc['interest_rate'] / 100;
            if($selected_acc['account_type'] == 'FD') {
                $n = ($selected_acc['compounding_frequency'] == 'Quarterly') ? 4 : 1;
                $t_full = $selected_acc['tenure_months'] / 12;
                $calc['total_payout'] = $p * pow((1 + $r_base/$n), ($n * $t_full));
            } elseif($selected_acc['account_type'] == 'RD') {
                 // Standard formula for RD maturity: P * [((1+r)^n - 1) / (1-(1+r)^-1/3)] ... simplified:
                 $n_months = $selected_acc['tenure_months'];
                 $inst = $selected_acc['installment_amount'];
                 $calc['total_payout'] = ($inst * $n_months) + ($inst * ($n_months * ($n_months + 1)) / 2 * ($r_base / 12));
            } else {
                 $calc['total_payout'] = $p; // MIS principal return
            }
            $calc['interest_accrued'] = $calc['total_payout'] - $p;
        }

        // Fetch member savings accounts for linkage
        $calc['member_id'] = $selected_acc['member_id'];
        $mem_savings = mysqli_query($conn, "SELECT id, account_no, current_balance FROM accounts WHERE member_id = {$calc['member_id']} AND account_type = 'Savings' AND status = 'active'");
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="ph ph-lock-key-open text-rose-500 text-3xl"></i> Account Closure Manager
        </h1>
        <p class="text-gray-500 text-sm mt-1">Process maturity payouts and calculate penal interest for term pre-closures.</p>
    </div>

    <?= displayAlert() ?>

    <?php if($error): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-lg mb-6 flex items-start gap-3 border border-red-100">
            <i class="ph ph-warning-circle text-xl text-red-500 mt-0.5"></i>
            <div>
                <h3 class="font-medium text-red-800">Processing Failed</h3>
                <p class="text-sm mt-0.5"><?= htmlspecialchars($error) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Selection Sidebar -->
        <div class="lg:col-span-1">
            <form action="" method="GET" class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 mb-6">
                <label class="block text-sm font-semibold text-gray-800 mb-3">Lookup Term Deposit</label>
                <div class="flex flex-col gap-3">
                    <select name="account_id" required class="select2-init w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-rose-500 focus:border-rose-500 text-sm">
                        <option value="">-- Select Account --</option>
                        <?php while($a = mysqli_fetch_assoc($accounts_list)): ?>
                            <option value="<?= $a['id'] ?>" <?= $account_id == $a['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['first_name'].' '.$a['last_name']) ?> (<?= $a['account_no'] ?>) <?= $a['status'] == 'matured' ? '[MAT]' : '' ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-medium py-2 rounded-lg transition-colors text-sm">
                        Load Calculation
                    </button>
                </div>
            </form>
        </div>

        <!-- Processing Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden relative">
                
                <?php if(!$selected_acc): ?>
                    <div class="absolute inset-0 bg-white/70 backdrop-blur-sm z-10 flex border border-transparent items-center justify-center flex-col">
                        <i class="ph ph-calculator text-4xl text-gray-300 mb-2"></i>
                        <p class="text-gray-500 font-medium">Load an account to calculate settlement</p>
                    </div>
                <?php else: ?>
                    <form method="POST" action="" class="p-8">
                        <input type="hidden" name="account_id" value="<?= $account_id ?>">
                        <input type="hidden" name="closure_type" value="<?= $calc['closure_type'] ?>">
                        <input type="hidden" name="final_payout" value="<?= round($calc['total_payout'], 2) ?>">
                        
                        <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
                            <div>
                                <h3 class="font-semibold text-lg text-gray-800 flex items-center gap-2">
                                    Final Settlement Sheet
                                </h3>
                                <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($selected_acc['first_name'] . ' ' . $selected_acc['last_name']) ?> | <?= $selected_acc['account_no'] ?> (<?= $selected_acc['scheme_type'] ?>) | Opened: <strong class="text-indigo-600"><?= date('d M Y', strtotime($selected_acc['opening_date'])) ?></strong></p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?= $calc['is_matured'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                                <?= $calc['closure_type'] ?>
                            </span>
                        </div>

                        <div class="space-y-4 text-sm mb-8">
                            <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-gray-600 font-medium">Principal Collected</span>
                                <span class="font-bold text-gray-900"><?= formatCurrency($calc['principal']) ?></span>
                            </div>
                            
                            <div class="flex justify-between p-3 border border-gray-100 rounded-lg items-center">
                                <div>
                                    <span class="block text-gray-600 font-medium">Interest Breakdown</span>
                                    <span class="text-xs text-gray-400 mt-0.5 block">Base: <?= $calc['base_rate'] ?>% <?= !$calc['is_matured'] ? '| Penalty Ded: ' . $calc['penalty_deduction'] . '%' : '' ?> | Applied: <strong class="text-indigo-600"><?= $calc['applied_rate'] ?>%</strong></span>
                                </div>
                                <span class="font-bold text-emerald-600">+ <?= formatCurrency($calc['interest_accrued']) ?></span>
                            </div>

                            <div class="flex justify-between p-4 bg-rose-50 border border-rose-100 rounded-xl items-center mt-2">
                                <span class="text-rose-900 font-bold uppercase tracking-wider text-xs">Total Customer Payout</span>
                                <span class="text-2xl font-black text-rose-700 tracking-tight cursor-default" title="Will be marked as withdrawal"><?= formatCurrency($calc['total_payout']) ?></span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Authorization Remarks / Cheque No. <span class="text-red-500">*</span></label>
                            <input type="text" name="remarks" required placeholder="e.g. Paid via RTGS 19992003" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-rose-500 focus:border-rose-500 outline-none transition-all">
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payout Destination</label>
                            <select name="payout_destination" onchange="document.getElementById('savingsCreditBox').classList.toggle('hidden', this.value !== 'savings')" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-rose-500 focus:border-rose-500 outline-none transition-all text-sm">
                                <option value="external">Issue Cheque / Cash / External Transfer</option>
                                <option value="savings">Credit to Linked Savings Account</option>
                            </select>
                        </div>

                        <div id="savingsCreditBox" class="mb-6 hidden p-4 bg-indigo-50 border border-indigo-100 rounded-xl">
                            <label class="block text-sm font-medium text-indigo-900 mb-1">Select Savings Account</label>
                            <select name="credit_savings_account_id" class="w-full px-3 py-2.5 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white outline-none">
                                <option value="">-- Choose Account --</option>
                                <?php while($sv = mysqli_fetch_assoc($mem_savings)): ?>
                                    <option value="<?= $sv['id'] ?>"><?= $sv['account_no'] ?> (Bal: <?= formatCurrency($sv['current_balance']) ?>)</option>
                                <?php endwhile; ?>
                            </select>
                            <p class="text-xs text-indigo-600 mt-2">The maturity amount will be transferred immediately.</p>
                        </div>

                        <div class="flex items-start gap-3 mb-6 p-4 bg-amber-50 rounded-lg border border-amber-200">
                            <i class="ph ph-warning-circle text-amber-500 text-xl mt-0.5"></i>
                            <p class="text-xs text-amber-800 leading-relaxed font-medium">Verify the payout amount and authorization remarks carefully. Processing this closure is irreversible. The customer's account will be permanently closed, and the payout amount will be recorded as an outgoing transaction.</p>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" name="process_closure" class="px-8 py-3 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-medium shadow-md shadow-rose-200 transition-colors flex items-center gap-2">
                                <i class="ph ph-check-shield text-xl"></i> Authorize & Close Account
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
