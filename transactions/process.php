<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$error = '';
$account_id = isset($_GET['account_id']) ? (int)$_GET['account_id'] : 0;

// Handle Transaction Submission
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_txn'])) {
    $txn_account_id = (int)$_POST['account_id'];
    $txn_type = sanitize($conn, $_POST['txn_type']);
    $amount = (float)$_POST['amount'];
    $description = sanitize($conn, $_POST['description']);
    $user_id = $_SESSION['user_id'];
    
    // Fetch account info and lock it to prevent race conditions
    mysqli_query($conn, "START TRANSACTION");
    $acc_res = mysqli_query($conn, "SELECT * FROM accounts WHERE id = $txn_account_id FOR UPDATE");
    $acc = mysqli_fetch_assoc($acc_res);
    
    if(!$acc || !in_array($acc['status'], ['active', 'defaulted'])) {
        $error = "Account is inactive or closed.";
        mysqli_query($conn, "ROLLBACK");
    } elseif($amount <= 0) {
        $error = "Amount must be greater than zero.";
        mysqli_query($conn, "ROLLBACK");
    } else {
        $balance_after = $acc['current_balance'];
        $allow = true;
        
        if($txn_type == 'Deposit') {
            if($acc['account_type'] == 'Loan') {
                $error = "Deposits to Loan accounts should be processed as 'EMI' or 'Principal Repayment'.";
                $allow = false;
            } else {
                $balance_after += $amount;
            }
        } elseif($txn_type == 'Withdrawal') {
            if($acc['account_type'] == 'Loan') {
                $error = "Cannot withdraw from a loan account after initial disbursal.";
                $allow = false;
            } elseif(in_array($acc['account_type'], ['FD','RD','MIS','DD'])) {
                $error = "Cannot do partial withdrawal from Term Deposits. Use Pre-Closure instead.";
                $allow = false;
            } else {
                // Savings Withdrawal check
                if($amount > $acc['current_balance']) {
                    $error = "Insufficient funds. Current balance: " . formatCurrency($acc['current_balance']);
                    $allow = false;
                } else {
                    $balance_after -= $amount;
                }
            }
        } elseif($txn_type == 'EMI') {
            if($acc['account_type'] != 'Loan') {
                $error = "EMI option is only for Loan accounts.";
                $allow = false;
            } else {
                $balance_after += $amount; // Paying off negative balance
                // If balance goes over 0, then we overpaid the loan
                if($balance_after > 0) {
                    $error = "Payment exceeds total due balance.";
                    $allow = false;
                } else {
                    // Update loan_schedules automatically based on payment received
                    $alloc_amount = $amount;
                    $sch_res = mysqli_query($conn, "SELECT * FROM loan_schedules WHERE account_id = $txn_account_id AND status IN ('Pending', 'Overdue') ORDER BY due_date ASC");
                    while($sch = mysqli_fetch_assoc($sch_res)) {
                        $due = $sch['emi_amount'] + $sch['fine_amount'];
                        if($alloc_amount >= $due) {
                            $alloc_amount -= $due;
                            mysqli_query($conn, "UPDATE loan_schedules SET status = 'Paid', paid_date = CURDATE() WHERE id = " . $sch['id']);
                        } else {
                            break; // simple logic: requires full exact EMI amount to mark a schedule line cleared
                        }
                    }
                }
            }
        } elseif($txn_type == 'Pre-Closure') {
             // For simplicity, just marks as pre-closed and updates balance to 0 (Customer withdrawn maturity)
             $allow = false;
             $error = "Pre-closures require specialized recalculation. Not implemented in this basic transaction flow.";
        }

        if($allow) {
            // ADVISOR WALLET INTEGRATION
            if($_SESSION['role'] == 'advisor' && in_array($txn_type, ['Deposit', 'EMI'])) {
                $advisor_id = $_SESSION['user_id'];
                // Lock advisor balance
                $adv_res = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $advisor_id FOR UPDATE");
                $adv_bal = mysqli_fetch_assoc($adv_res)['wallet_balance'];
                
                if($amount > $adv_bal) {
                    $error = "Insufficient wallet balance. Please recharge your wallet. (Available: " . formatCurrency($adv_bal) . ")";
                    mysqli_query($conn, "ROLLBACK");
                    $allow = false;
                } else {
                    $new_wallet_bal = $adv_bal - $amount;
                    mysqli_query($conn, "UPDATE users SET wallet_balance = $new_wallet_bal WHERE id = $advisor_id");
                    // We'll insert wallet txn after we have the TXN ID below
                }
            }
        }

        if($allow) {
            // Adjust balance
            mysqli_query($conn, "UPDATE accounts 
                                 SET current_balance = $balance_after 
                                 WHERE id = $txn_account_id");
            
            // Insert TXN
            $txn_id = 'TXN-' . time() . rand(100,999);
            $now = date('Y-m-d H:i:s');
            if($_SESSION['role'] === 'admin' && !empty($_POST['transaction_date'])) {
                $now = sanitize($conn, $_POST['transaction_date']) . ' ' . date('H:i:s');
            }
            
            $sql = "INSERT INTO transactions (transaction_id, account_id, transaction_type, amount, balance_after, description, transaction_date, created_by) 
                    VALUES ('$txn_id', $txn_account_id, '$txn_type', $amount, $balance_after, '$description', '$now', $user_id)";
            
            if(mysqli_query($conn, $sql)) {
                $inserted_txn_id = mysqli_insert_id($conn);
                
                // Finalize Wallet Transaction for Advisor
                if($_SESSION['role'] == 'advisor' && in_array($txn_type, ['Deposit', 'EMI'])) {
                    $acc_no = $acc['account_no'];
                    $wallet_desc = "$txn_type for account $acc_no";
                    // balance_after logic: we already updated the user's wallet_balance in DB, so fetch it again or just calc
                    // since we are still in transaction, we can just use $new_wallet_bal
                    $sql_wallet_txn = "INSERT INTO wallet_transactions (user_id, transaction_type, amount, balance_after, reference_id, description, created_by) 
                                       VALUES ($user_id, 'Collection', -$amount, $new_wallet_bal, '$txn_id', '$wallet_desc', $user_id)";
                    mysqli_query($conn, $sql_wallet_txn);
                }

                mysqli_query($conn, "COMMIT");
                logAction($conn, $_SESSION['user_id'], 'Transaction Processed', "Successful $txn_type of " . formatCurrency($amount) . " with TXN ID: $txn_id");
                $_SESSION['success'] = "Transaction Processed Successfully! TXN ID: $txn_id";
                // Redirect to receipt
                header("Location: receipt.php?id=" . $inserted_txn_id);
                exit();
            } else {
                mysqli_query($conn, "ROLLBACK");
                $error = "System error during transaction logging.";
            }
        } else {
            mysqli_query($conn, "ROLLBACK");
        }
    }
}

// Fetch Accounts for dropdown
$accounts_list = mysqli_query($conn, "SELECT a.id, a.account_no, a.account_type, m.first_name, m.last_name 
                                     FROM accounts a 
                                     JOIN members m ON a.member_id = m.id 
                                     WHERE a.status IN ('active', 'defaulted') ORDER BY m.first_name");

$selected_acc = null;
$loan_details = null;
if($account_id > 0) {
    $res = mysqli_query($conn, "SELECT a.*, m.first_name, m.last_name, m.member_no, s.scheme_name, s.scheme_type 
                                FROM accounts a 
                                JOIN members m ON a.member_id = m.id 
                                JOIN schemes s ON a.scheme_id = s.id 
                                WHERE a.id = $account_id");
    if(mysqli_num_rows($res) > 0) {
        $selected_acc = mysqli_fetch_assoc($res);
        // Recalculate fines if it's a loan
        if($selected_acc['account_type'] == 'Loan') {
            $calc_date = isset($_GET['transaction_date']) ? sanitize($conn, $_GET['transaction_date']) : date('Y-m-d');
            calculateAndUpdateFines($conn, $account_id, $calc_date);
        }
        
        // Fetch Loan Schedule Overdues if Loan Account
        if($selected_acc['account_type'] == 'Loan') {
            $l_res = mysqli_query($conn, "SELECT COUNT(id) as overdue_count, SUM(emi_amount) as total_overdue_emi, SUM(fine_amount) as total_fine 
                                          FROM loan_schedules 
                                          WHERE account_id = $account_id AND status = 'Overdue'");
            if($l_res) {
                $loan_details = mysqli_fetch_assoc($l_res);
            }
        }
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="ph ph-arrows-left-right text-indigo-500 text-3xl"></i> Process Transaction
        </h1>
        <p class="text-gray-500 text-sm mt-1">Accept deposits, formulate withdrawals, and collect EMIs.</p>
    </div>

    <?= displayAlert() ?>

    <?php if($error): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-lg mb-6 flex items-start gap-3 border border-red-100">
            <i class="ph ph-warning-circle text-xl text-red-500 mt-0.5"></i>
            <div>
                <h3 class="font-medium text-red-800">Transaction Failed</h3>
                <p class="text-sm mt-0.5"><?= htmlspecialchars($error) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Selection Sidebar -->
        <div class="lg:col-span-1">
            <form action="" method="GET" class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 mb-6">
                <label class="block text-sm font-semibold text-gray-800 mb-3">Lookup Account</label>
                <div class="flex flex-col gap-3">
                    <select name="account_id" required class="select2-init w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        <option value="">-- Select Account --</option>
                        <?php while($a = mysqli_fetch_assoc($accounts_list)): ?>
                            <option value="<?= $a['id'] ?>" <?= $account_id == $a['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['first_name'].' '.$a['last_name']) ?> (<?= $a['account_no'] ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-medium py-2 rounded-lg transition-colors text-sm">
                        Load Details
                    </button>
                </div>
            </form>

            <?php if($selected_acc): ?>
                <div class="bg-gradient-to-br from-indigo-500 to-indigo-700 rounded-2xl p-5 shadow-lg text-white">
                    <div class="flex items-center justify-between mb-4 pb-4 border-b border-indigo-400/30">
                        <div>
                            <p class="text-indigo-200 text-xs font-medium uppercase tracking-wider mb-1">Current Balance</p>
                            <h2 class="text-2xl font-bold tracking-tight">
                                <?php
                                    $display_bal = abs($selected_acc['current_balance']);
                                    if($selected_acc['account_type'] == 'Loan') {
                                        // Total Liability (Principal + Interest + Fines)
                                        $liability_res = mysqli_query($conn, "SELECT SUM(emi_amount + fine_amount) as total FROM loan_schedules WHERE account_id = " . $selected_acc['id'] . " AND status IN ('Pending', 'Overdue')");
                                        $liability = mysqli_fetch_assoc($liability_res)['total'] ?? 0;
                                        $display_bal = $liability;
                                    }
                                    echo formatCurrency($display_bal);
                                ?>
                                <?php if($selected_acc['account_type'] == 'Loan' || $selected_acc['current_balance'] < 0): ?>
                                    <span class="text-xs text-rose-300 ml-1 font-medium bg-rose-500/20 px-1.5 py-0.5 rounded">DUE</span>
                                <?php endif; ?>
                            </h2>
                        </div>
                        <i class="ph ph-wallet text-4xl text-indigo-300 opacity-50"></i>
                    </div>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-indigo-200">Customer</span>
                            <span class="font-medium text-right"><?= htmlspecialchars($selected_acc['first_name'].' '.$selected_acc['last_name']) ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-indigo-200">A/c No.</span>
                            <span class="font-medium font-mono"><?= htmlspecialchars($selected_acc['account_no']) ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-indigo-200">Scheme</span>
                            <span class="font-medium text-right capitalize"><?= htmlspecialchars($selected_acc['scheme_type']) ?> Account</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-indigo-200">Opened On</span>
                            <span class="font-medium font-mono text-sm"><?= date('d M Y', strtotime($selected_acc['opening_date'])) ?></span>
                        </div>
                        <?php if($selected_acc['installment_amount'] > 0): ?>
                        <div class="flex justify-between items-center">
                            <span class="text-indigo-200">Commitment/EMI</span>
                            <span class="font-medium"><?= formatCurrency($selected_acc['installment_amount']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-5 pt-4 border-t border-indigo-400/30 text-center">
                        <a href="../accounts/statement.php?id=<?= $selected_acc['id'] ?>" target="_blank" class="inline-flex items-center gap-2 text-indigo-100 hover:text-white font-medium text-sm transition-colors">
                            <i class="ph ph-file-text text-lg"></i> View Full Statement
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-indigo-50 rounded-2xl p-8 border border-indigo-100 flex flex-col items-center justify-center text-center text-indigo-400 h-64 border-dashed">
                    <i class="ph ph-magnifying-glass text-4xl mb-2"></i>
                    <p class="text-sm">Select an account from the dropdown to load ledger details.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Transaction Form -->
        <div class="lg:col-span-2">
            
            <?php if($loan_details && $loan_details['overdue_count'] > 0): ?>
            <div class="bg-red-50 border border-red-200 rounded-2xl p-5 mb-6 flex items-start gap-4 shadow-sm relative overflow-hidden">
                <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4MCIgaGVpZ2h0PSI4MCIgdmlld0JveD0iMCAwIDgwIDgwIj4NCjxnIGZpbGw9IiNmZWMxY2MiIGZpbGwtb3BhY2l0eT0iMC4yIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgZD0iTTAgMGg0MHY0MEgwVjB6bTQwIDQwaDQwdjQwSDQwdjQweiIvPg0KPC9nPg0KPC9zdmc+')] pointer-events-none"></div>
                <i class="ph ph-warning-octagon text-3xl text-red-500 mt-1 relative z-10"></i>
                <div class="w-full relative z-10">
                    <h3 class="text-red-800 font-bold uppercase tracking-wider text-sm flex items-center gap-3">
                        Defaulter Penalty Alert
                        <span class="bg-red-600 text-white px-2 py-0.5 rounded text-[10px] tracking-widest"><?= $loan_details['overdue_count'] ?> EMI(s) PENDING</span>
                    </h3>
                    <div class="mt-3 flex gap-6 text-sm">
                        <div>
                            <span class="block text-red-400 font-semibold mb-0.5 uppercase tracking-wider text-[10px]">Overdue EMI</span>
                            <span class="font-bold text-red-900 text-lg"><?= formatCurrency((float)$loan_details['total_overdue_emi']) ?></span>
                        </div>
                        <div>
                            <span class="block text-red-400 font-semibold mb-0.5 uppercase tracking-wider text-[10px]">+ Fine Imposed</span>
                            <span class="font-bold text-red-900 text-lg"><?= formatCurrency((float)$loan_details['total_fine']) ?></span>
                        </div>
                        <div class="border-l pl-6 border-red-200">
                            <span class="block text-red-700 font-bold mb-0.5 uppercase tracking-wider text-xs">Clearance Required</span>
                            <span class="font-bold text-red-900 text-xl tracking-tight leading-none"><?= formatCurrency((float)$loan_details['total_overdue_emi'] + (float)$loan_details['total_fine']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden relative">
                
                <?php if(!$selected_acc): ?>
                    <div class="absolute inset-0 bg-white/70 backdrop-blur-sm z-10 flex border border-transparent items-center justify-center flex-col">
                        <i class="ph ph-lock-key text-4xl text-gray-300 mb-2"></i>
                        <p class="text-gray-500 font-medium">Load an account to transact</p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="p-8">
                    <input type="hidden" name="account_id" value="<?= $account_id ?>">
                    
                    <h3 class="font-semibold text-lg text-gray-800 mb-6 flex items-center gap-2 border-b border-gray-50 pb-4">
                        <i class="ph ph-receipt text-emerald-500"></i> Transaction Entry Form
                    </h3>

                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-800 mb-2">Transaction Type <span class="text-red-500">*</span></label>
                            
                            <div class="grid grid-cols-3 gap-3">
                                <?php
                                $type = $selected_acc ? $selected_acc['scheme_type'] : '';
                                ?>
                                <?php if($type != 'Loan'): ?>
                                <label class="cursor-pointer relative">
                                    <input type="radio" name="txn_type" value="Deposit" class="peer sr-only" required>
                                    <div class="w-full text-center px-4 py-3 border border-gray-200 rounded-xl peer-checked:bg-emerald-50 peer-checked:border-emerald-500 peer-checked:text-emerald-700 hover:border-emerald-200 transition-all font-medium text-sm flex flex-col items-center gap-1 text-gray-600">
                                        <i class="ph ph-arrow-down-left text-xl"></i> Deposit
                                    </div>
                                    <div class="absolute right-2 top-2 hidden peer-checked:block text-emerald-500"><i class="ph ph-check-circle-fill"></i></div>
                                </label>
                                <?php endif; ?>

                                <?php if($type == 'Savings'): ?>
                                <label class="cursor-pointer relative">
                                    <input type="radio" name="txn_type" value="Withdrawal" class="peer sr-only" required>
                                    <div class="w-full text-center px-4 py-3 border border-gray-200 rounded-xl peer-checked:bg-rose-50 peer-checked:border-rose-500 peer-checked:text-rose-700 hover:border-rose-200 transition-all font-medium text-sm flex flex-col items-center gap-1 text-gray-600">
                                        <i class="ph ph-arrow-up-right text-xl"></i> Withdraw
                                    </div>
                                    <div class="absolute right-2 top-2 hidden peer-checked:block text-rose-500"><i class="ph ph-check-circle-fill"></i></div>
                                </label>
                                <?php endif; ?>

                                <?php if($type == 'Loan'): ?>
                                <label class="cursor-pointer relative">
                                    <input type="radio" name="txn_type" value="EMI" class="peer sr-only" required>
                                    <div class="w-full text-center px-4 py-3 border border-gray-200 rounded-xl peer-checked:bg-blue-50 peer-checked:border-blue-500 peer-checked:text-blue-700 hover:border-blue-200 transition-all font-medium text-sm flex flex-col items-center gap-1 text-gray-600">
                                        <i class="ph ph-hand-coins text-xl"></i> EMI Pay / Repay
                                    </div>
                                    <div class="absolute right-2 top-2 hidden peer-checked:block text-blue-500"><i class="ph ph-check-circle-fill"></i></div>
                                </label>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₹) <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-medium">₹</span>
                                <input type="number" step="0.01" min="1" name="amount" id="txnAmount" required class="w-full pl-10 pr-4 py-3 text-lg border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all font-semibold text-gray-800 tracking-wider">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Narration / Description <span class="text-red-500">*</span></label>
                            <input type="text" name="description" required placeholder="e.g. Cash Deposit via Branch" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                        </div>
                        
                        <?php if($_SESSION['role'] === 'admin'): ?>
                        <div class="bg-gray-50 border border-gray-200 p-4 rounded-xl">
                            <label class="block text-sm font-medium text-gray-800 mb-1 flex items-center gap-2">
                                <i class="ph ph-calendar-blank text-indigo-600"></i>
                                Admin Feature: Custom Transaction Date
                            </label>
                            <input type="date" name="transaction_date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all" max="<?= date('Y-m-d') ?>">
                            <p class="text-xs text-gray-500 mt-1">Leave empty to use current system time. Do not set future dates.</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-8 pt-6 border-t border-gray-100 flex justify-end">
                        <button type="submit" name="process_txn" class="px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-medium shadow-md shadow-indigo-200 transition-colors flex items-center gap-2">
                            <i class="ph ph-shield-check text-xl"></i> Process & Generate Receipt
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
<?php if($selected_acc && $selected_acc['account_type'] == 'Loan'): ?>
    <?php
    $base_emi = $selected_acc['installment_amount'];
    $clearance_req = ($loan_details && $loan_details['overdue_count'] > 0) ? ($loan_details['total_overdue_emi'] + $loan_details['total_fine']) : $base_emi;
    ?>
    const clearanceAmount = <?= (float)$clearance_req ?>;
    
    // Auto-fill amount when EMI radio is clicked/selected
    $('input[name="txn_type"]').on('change', function() {
        if($(this).val() === 'EMI') {
            $('#txnAmount').val(clearanceAmount);
        } else {
            $('#txnAmount').val('');
        }
    });
    
    // trigger check on load
    if($('input[name="txn_type"]:checked').val() === 'EMI') {
        $('#txnAmount').val(clearanceAmount);
    }
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
