<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkAuth();

// Only Advisor or Admin can access
if(!in_array($_SESSION['role'], ['advisor', 'admin'])) {
    $_SESSION['error'] = "Access Denied.";
    header("Location: ../index.php");
    exit();
}

$error = '';
$advisor_id = $_SESSION['user_id'];

// Get current advisor wallet balance
$advisor_res = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $advisor_id");
$advisor_data = mysqli_fetch_assoc($advisor_res);
$wallet_balance = $advisor_data['wallet_balance'];

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['collect_deposit'])) {
    $txn_account_id = (int)$_POST['account_id'];
    $amount = (float)$_POST['amount'];
    $description = sanitize($conn, $_POST['description'] . " (Advisor Collection via Wallet)");
    
    // Fetch account info
    mysqli_query($conn, "START TRANSACTION");
    $acc_res = mysqli_query($conn, "SELECT a.*, s.scheme_type FROM accounts a JOIN schemes s ON a.scheme_id = s.id WHERE a.id = $txn_account_id FOR UPDATE");
    $acc = mysqli_fetch_assoc($acc_res);

    // Locking advisor record as well
    $adv_lock = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $advisor_id FOR UPDATE");
    $advisor_balance = mysqli_fetch_assoc($adv_lock)['wallet_balance'];

    if(!$acc || !in_array($acc['status'], ['active', 'defaulted'])) {
        $error = "Account is inactive or closed.";
        mysqli_query($conn, "ROLLBACK");
    } elseif($amount <= 0) {
        $error = "Amount must be greater than zero.";
        mysqli_query($conn, "ROLLBACK");
    } elseif($amount > $advisor_balance) {
        $error = "Insufficient wallet balance. Please recharge your wallet.";
        mysqli_query($conn, "ROLLBACK");
    } else {
        // Procedure:
        // 1. Update Member Account Balance
        // 2. Insert Core Transaction (TXN-...)
        // 3. Deduct from Advisor Wallet
        // 4. Insert Wallet Transaction
        
        $balance_after = $acc['current_balance'] + $amount;
        $txn_id = 'TXN-' . time() . rand(100,999);
        $now = date('Y-m-d H:i:s');
        $txn_type = ($acc['account_type'] == 'Loan') ? 'EMI' : 'Deposit';

        // Trigger fine update before collection to ensure late fees are captured
        calculateAndUpdateFines($conn, $txn_account_id, date('Y-m-d'));

        // Update Account
        $update_acc = mysqli_query($conn, "UPDATE accounts SET current_balance = current_balance + $amount WHERE id = $txn_account_id");
        
        $txn_id = 'TXN-' . time() . rand(100,999);

        // If it's EMI, we need to update loan schedule as well
        if($txn_type == 'EMI') {
            $alloc_amount = $amount;
            $sch_res = mysqli_query($conn, "SELECT * FROM loan_schedules WHERE account_id = $txn_account_id AND status IN ('Pending', 'Overdue') ORDER BY due_date ASC");
            while($sch = mysqli_fetch_assoc($sch_res)) {
                $due = (float)$sch['emi_amount'] + (float)$sch['fine_amount'];
                if($alloc_amount >= $due) {
                    $alloc_amount -= $due;
                    mysqli_query($conn, "UPDATE loan_schedules SET status = 'Paid', paid_date = CURDATE(), transaction_id = '$txn_id' WHERE id = " . $sch['id']);
                } else {
                    break;
                }
            }
        }

        // Insert Transaction
        $sql_txn = "INSERT INTO transactions (transaction_id, account_id, transaction_type, amount, balance_after, description, transaction_date, created_by) 
                    VALUES ('$txn_id', $txn_account_id, '$txn_type', $amount, $balance_after, '$description', '$now', $advisor_id)";
        $insert_txn = mysqli_query($conn, $sql_txn);
        $core_txn_db_id = mysqli_insert_id($conn);

        // Deduct from Advisor Wallet (Relative Update)
        $update_wallet = mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance - $amount WHERE id = $advisor_id");
        
        // Fetch new balance for tracing
        $adv_bal_res = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $advisor_id");
        $new_wallet_bal = mysqli_fetch_assoc($adv_bal_res)['wallet_balance'];
        
        // Wallet Trace
        $sql_wallet_txn = "INSERT INTO wallet_transactions (user_id, transaction_type, amount, balance_after, reference_id, description, created_by) 
                           VALUES ($advisor_id, 'Collection', -$amount, $new_wallet_bal, '$txn_id', 'Collection for Account: ' . (SELECT account_no FROM accounts WHERE id = $txn_account_id), $advisor_id)";
        // Using a subquery for description or just fetch it earlier. Let's use simpler way.
        $acc_no = $acc['account_no'];
        $wallet_desc = "Collection for account $acc_no";
        $sql_wallet_txn = "INSERT INTO wallet_transactions (user_id, transaction_type, amount, balance_after, reference_id, description, created_by) 
                           VALUES ($advisor_id, 'Collection', -$amount, $new_wallet_bal, '$txn_id', '$wallet_desc', $advisor_id)";
        $insert_wallet_txn = mysqli_query($conn, $sql_wallet_txn);

        if($update_acc && $insert_txn && $update_wallet && $insert_wallet_txn) {
            mysqli_query($conn, "COMMIT");
            $_SESSION['success'] = "Collection Successful! TXN ID: $txn_id. Wallet Balance: " . formatCurrency($new_wallet_bal);
            header("Location: ../transactions/receipt.php?id=" . $core_txn_db_id);
            exit();
        } else {
            mysqli_query($conn, "ROLLBACK");
            $error = "System error: " . mysqli_error($conn);
        }
    }
}

// Service-based filtering
$svc_map = [
    'Savings' => 'service_savings_enabled',
    'Loan' => 'service_loan_enabled',
    'FD' => 'service_fd_enabled',
    'RD' => 'service_rd_enabled',
    'MIS' => 'service_mis_enabled',
    'DD' => 'service_dd_enabled'
];
$loan_only = getSetting($conn, 'loan_only_mode') == '1';
$enabled_types = [];
foreach($svc_map as $type => $setting) {
    if(getSetting($conn, $setting) == '1') {
        if($loan_only && $type != 'Loan') continue;
        $enabled_types[] = "'$type'";
    }
}
if(empty($enabled_types)) $enabled_types = ["'NONE'"];
$enabled_clause = implode(',', $enabled_types);

// Fetch Accounts for dropdown
$accounts_list = mysqli_query($conn, "SELECT a.id, a.account_no, a.account_type, m.first_name, m.last_name 
                                     FROM accounts a 
                                     JOIN members m ON a.member_id = m.id 
                                     JOIN schemes s ON a.scheme_id = s.id
                                     WHERE a.status IN ('active', 'defaulted') 
                                     AND s.scheme_type IN ($enabled_clause)
                                     ORDER BY m.first_name");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="ph ph-hand-coins text-indigo-500 text-3xl"></i> Field Collection (Wallet)
        </h1>
        <p class="text-gray-500 text-sm mt-1">Collect DD/RD payments using your prepaid wallet balance.</p>
    </div>

    <?= displayAlert() ?>

    <?php if($error): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-lg mb-6 border border-red-100 flex items-center gap-3">
            <i class="ph ph-warning-circle text-xl"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="bg-slate-900 rounded-2xl p-6 shadow-lg text-white">
                <div class="flex items-center justify-between mb-6">
                    <p class="text-slate-400 text-xs font-semibold uppercase tracking-widest">Your Wallet</p>
                    <i class="ph ph-wallet text-2xl text-indigo-400"></i>
                </div>
                <h2 class="text-3xl font-bold mb-1 tracking-tight"><?= formatCurrency($wallet_balance) ?></h2>
                <p class="text-xs text-slate-400">Available Balance</p>
                
                <div class="mt-8 pt-6 border-t border-slate-800 flex flex-col gap-3">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-slate-400">Advisor</span>
                        <span class="font-medium"><?= htmlspecialchars($_SESSION['name']) ?></span>
                    </div>
                </div>
            </div>

            <div class="mt-6 bg-indigo-50 border border-indigo-100 rounded-2xl p-5">
                <h3 class="font-bold text-indigo-900 flex items-center gap-2 mb-2">
                    <i class="ph ph-info"></i> How it works?
                </h3>
                <ul class="text-xs text-indigo-700 space-y-2 list-disc pl-4">
                    <li>Select the customer's account.</li>
                    <li>Enter the amount collected.</li>
                    <li>The amount will be deducted from your wallet instantly.</li>
                    <li>Member's account will be credited.</li>
                    <li>You can print a receipt for the customer.</li>
                </ul>
            </div>
        </div>

        <div class="lg:col-span-2">
            <form method="POST" action="" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Customer Account <span class="text-red-500">*</span></label>
                    <select name="account_id" required class="select2-init w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="">-- Start typing name or A/c No --</option>
                        <?php while($a = mysqli_fetch_assoc($accounts_list)): ?>
                            <option value="<?= $a['id'] ?>">
                                <?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?> (<?= $a['account_no'] ?>) - <?= $a['account_type'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Collection Amount (₹) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-medium">₹</span>
                            <input type="number" step="0.01" min="1" name="amount" required class="w-full pl-10 pr-4 py-3 text-lg border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-gray-800">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Narration / Remarks</label>
                    <input type="text" name="description" placeholder="e.g. DD Collection from field" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>

                <div class="pt-4">
                    <button type="submit" onclick="return confirm('Amount will be deducted from your wallet. Proceed?')" name="collect_deposit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-4 rounded-xl font-bold shadow-lg shadow-indigo-100 transition-all flex items-center justify-center gap-3">
                        <i class="ph ph-shield-check text-2xl"></i> CONFIRM COLLECTION
                    </button>
                    <p class="text-center text-gray-400 text-xs mt-3">This transaction will generate an official receipt for the member.</p>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
