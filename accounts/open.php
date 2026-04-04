<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$error = '';
$pre_select_member = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['open_account'])) {
    $member_id = (int)$_POST['member_id'];
    $scheme_id = (int)$_POST['scheme_id'];
    $amount = (float)$_POST['amount'];
    $tenure = isset($_POST['tenure']) && $_POST['tenure'] !== '' ? (int)$_POST['tenure'] : 0;
    
    // Backdate logic
    $opening_date = date('Y-m-d');
    if($_SESSION['role'] == 'admin' && !empty($_POST['backdate'])) {
        $opening_date = sanitize($conn, $_POST['backdate']);
    }

    // Fetch Scheme Details
    $scheme_res = mysqli_query($conn, "SELECT * FROM schemes WHERE id = $scheme_id");
    if($scheme = mysqli_fetch_assoc($scheme_res)) {
        if($amount < $scheme['minimum_amount'] && in_array($scheme['scheme_type'], ['Savings', 'FD', 'MIS', 'Loan'])) {
            $error = "Minimum amount required for this scheme is " . formatCurrency($scheme['minimum_amount']);
        } else {
            $account_type = $scheme['scheme_type'];
            $account_no = generateSequenceNo($conn, strtoupper($account_type), 'accounts', 'account_no');
            $maturity_date = null;
            
            $opening_balance = 0;
            $principal_amount = 0;
            $installment_amount = 0;

            if($account_type == 'Savings') {
                $opening_balance = $amount;
            } elseif(in_array($account_type, ['FD', 'MIS', 'Loan'])) {
                $principal_amount = $amount;
                $opening_balance = ($account_type == 'Loan') ? -$amount : $amount; // Loan is a debit balance
            } elseif(in_array($account_type, ['RD', 'DD'])) {
                $installment_amount = $amount; // Monthly/Daily commitment
                $opening_balance = 0; // Starts at 0
            }

            if($tenure > 0) {
                // Calculate maturity date from opening date
                $maturity_date = date('Y-m-d', strtotime($opening_date . " +$tenure months"));
            }

            // Loan Specific Details
            $g_name = isset($_POST['guarantor_name']) ? sanitize($conn, $_POST['guarantor_name']) : null;
            $g_phone = isset($_POST['guarantor_phone']) ? sanitize($conn, $_POST['guarantor_phone']) : null;
            $photo_path = null;
            $doc_path = null;

            if($account_type == 'Loan' && isset($_FILES['photo_path']) && $_FILES['photo_path']['error'] == 0) {
                $ext = pathinfo($_FILES['photo_path']['name'], PATHINFO_EXTENSION);
                $photo_path = 'uploads/photo_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['photo_path']['tmp_name'], '../' . $photo_path);
            }
            if($account_type == 'Loan' && isset($_FILES['document_path']) && $_FILES['document_path']['error'] == 0) {
                $ext = pathinfo($_FILES['document_path']['name'], PATHINFO_EXTENSION);
                $doc_path = 'uploads/doc_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['document_path']['tmp_name'], '../' . $doc_path);
            }

            $loan_interest_type = isset($_POST['loan_interest_type']) ? sanitize($conn, $_POST['loan_interest_type']) : 'Flat';
            $linked_savings_account = (isset($_POST['linked_savings_account']) && $_POST['linked_savings_account'] !== '') ? (int)$_POST['linked_savings_account'] : 'NULL';

            // ADVISOR WALLET CHECK (Proactive)
            if($_SESSION['role'] == 'advisor' && $amount > 0 && in_array($account_type, ['Savings', 'FD', 'RD', 'DD', 'MIS'])) {
                $adv_id = $_SESSION['user_id'];
                $adv_check = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $adv_id FOR UPDATE");
                $adv_bal = mysqli_fetch_assoc($adv_check)['wallet_balance'];
                if($amount > $adv_bal) {
                    $error = "Insufficient wallet balance to process initial deposit. Available: " . formatCurrency($adv_bal);
                }
            }

            if(!$error) {
                mysqli_query($conn, "START TRANSACTION");
                $sql = "INSERT INTO accounts (account_no, member_id, scheme_id, account_type, opening_balance, current_balance, principal_amount, installment_amount, tenure_months, opening_date, maturity_date, guarantor_name, guarantor_phone, photo_path, document_path, loan_interest_type, linked_savings_account) 
                        VALUES ('$account_no', $member_id, $scheme_id, '$account_type', $opening_balance, $opening_balance, $principal_amount, $installment_amount, $tenure, '$opening_date', " . ($maturity_date ? "'$maturity_date'" : "NULL") . ", " . ($g_name ? "'$g_name'" : "NULL") . ", " . ($g_phone ? "'$g_phone'" : "NULL") . ", " . ($photo_path ? "'$photo_path'" : "NULL") . ", " . ($doc_path ? "'$doc_path'" : "NULL") . ", '$loan_interest_type', $linked_savings_account)";
                
                if(mysqli_query($conn, $sql)) {
                $account_id = mysqli_insert_id($conn);
                
                // If it's a loan, we should generate an EMI Schedule
                if($account_type == 'Loan' && $tenure > 0) {
                    $rate_monthly = ($scheme['interest_rate'] / 100) / 12;
                    if($loan_interest_type == 'Reducing') {
                        $emi = ($principal_amount * $rate_monthly * pow(1 + $rate_monthly, $tenure)) / (pow(1 + $rate_monthly, $tenure) - 1);
                    } else {
                        // Flat
                        $total_int = $principal_amount * ($scheme['interest_rate'] / 100) * ($tenure / 12);
                        $emi = ($principal_amount + $total_int) / $tenure;
                    }
                    $emi = round($emi, 2);
                    
                    mysqli_query($conn, "UPDATE accounts SET installment_amount = $emi WHERE id = $account_id");
                    
                    // Generate Schedule
                    $rem_principal = $principal_amount;
                    for($i = 1; $i <= $tenure; $i++) {
                        $due_date = date('Y-m-d', strtotime($opening_date . " + $i months"));
                        if($loan_interest_type == 'Reducing') {
                            $int_comp = round($rem_principal * $rate_monthly, 2);
                            $prin_comp = $emi - $int_comp;
                            $rem_principal -= $prin_comp;
                        } else {
                            $prin_comp = round($principal_amount / $tenure, 2);
                            $int_comp = $emi - $prin_comp;
                        }
                        mysqli_query($conn, "INSERT INTO loan_schedules (account_id, installment_no, due_date, emi_amount, principal_component, interest_component, status) 
                                             VALUES ($account_id, $i, '$due_date', $emi, $prin_comp, $int_comp, 'Pending')");
                    }
                }

                $_SESSION['success'] = "Account opened successfully! Account No: $account_no";
                
                // If initial deposit/disbursal, create a transaction record
                if($amount > 0 && in_array($account_type, ['Savings', 'FD', 'MIS', 'RD', 'DD'])) {
                    $txn_type = 'Account-Open';
                    $desc = 'Account Opening Deposit';
                    $txn_id = 'TXN-' . time() . rand(10,99);
                    $user_id = $_SESSION['user_id'];
                    $now = date('Y-m-d H:i:s');
                    
                    // ADVISOR WALLET DEDUCTION
                    if($_SESSION['role'] == 'advisor') {
                        // Lock and verify wallet balance
                        $adv_res = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $user_id FOR UPDATE");
                        $adv_bal = mysqli_fetch_assoc($adv_res)['wallet_balance'];
                        
                        if($amount > $adv_bal) {
                            // If insufficient, we should ideally rollback the account creation too
                            // Since we are already inside the success block of accounts INSERT, we need logic to handle this
                            // Better: Check this BEFORE the account INSERT. 
                            // For now, I'll add the check before the actual INSERT at line 70.
                        }
                    }
                    
                    $balance_after = $opening_balance + (in_array($account_type, ['Loan']) ? 0 : $amount);
                    if($account_type == 'RD' || $account_type == 'DD') {
                         $balance_after = $amount; // Set balance to the first installment paid
                    }

                    mysqli_query($conn, "INSERT INTO transactions (transaction_id, account_id, transaction_type, amount, balance_after, description, transaction_date, created_by) 
                                         VALUES ('$txn_id', $account_id, 'Account-Open', $amount, $balance_after, '$desc', '$now', $user_id)");
                    
                    // Update master account balance
                    mysqli_query($conn, "UPDATE accounts SET current_balance = $balance_after WHERE id = $account_id");

                    if($_SESSION['role'] == 'advisor') {
                        $new_wallet_bal = $adv_bal - $amount;
                        mysqli_query($conn, "UPDATE users SET wallet_balance = $new_wallet_bal WHERE id = $user_id");
                        $wallet_desc = "Initial Deposit for A/c $account_no";
                        mysqli_query($conn, "INSERT INTO wallet_transactions (user_id, transaction_type, amount, balance_after, reference_id, description, created_by) 
                                           VALUES ($user_id, 'Collection', -$amount, $new_wallet_bal, '$txn_id', '$wallet_desc', $user_id)");
                    }
                } elseif($amount > 0 && $account_type == 'Loan') {
                    // Loan disbursal (system pays out, doesn't affect advisor wallet)
                    $txn_id = 'TXN-' . time() . rand(10,99);
                    $now = date('Y-m-d H:i:s');
                    mysqli_query($conn, "INSERT INTO transactions (transaction_id, account_id, transaction_type, amount, balance_after, description, transaction_date, created_by) 
                                         VALUES ('$txn_id', $account_id, 'Account-Open', $amount, $opening_balance, 'Loan Disbursal', '$now', {$_SESSION['user_id']})");
                }
                mysqli_query($conn, "COMMIT");
                header("Location: view.php");
                exit();
            } else {
                mysqli_query($conn, "ROLLBACK");
                $error = "Database Error: " . mysqli_error($conn);
            }
        }
    }
} else {
    $error = "Invalid Scheme Selected.";
}
}

// Fetch Members
$members = mysqli_query($conn, "SELECT id, member_no, first_name, last_name, aadhar_no FROM members WHERE status = 'active' ORDER BY first_name ASC");

// Fetch Schemes
$schemes = mysqli_query($conn, "SELECT * FROM schemes WHERE status = 'active' ORDER BY scheme_type ASC");
$schemes_json = [];
while($s = mysqli_fetch_assoc($schemes)) {
    $schemes_json[$s['id']] = $s;
}
mysqli_data_seek($schemes, 0); // reset pointer for select dropdown

// Fetch Savings Accounts for Linkage
$savings_query = mysqli_query($conn, "SELECT id, member_id, account_no, current_balance FROM accounts WHERE account_type = 'Savings' AND status = 'active'");
$savings_json = [];
while($sav = mysqli_fetch_assoc($savings_query)) {
    if(!isset($savings_json[$sav['member_id']])) $savings_json[$sav['member_id']] = [];
    $savings_json[$sav['member_id']][] = $sav;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="ph ph-folder-plus text-indigo-500"></i> Open New Account
        </h1>
        <p class="text-gray-500 text-sm mt-1">Configure and open Savings, Loan, FD, RD, MIS, or DD accounts</p>
    </div>

    <?= displayAlert() ?>
    <?php if($error): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-lg mb-6 text-sm border border-red-100 flex items-center gap-2"><i class="ph ph-warning-circle text-lg"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" id="accountForm">
        <div class="p-8 space-y-6">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Member Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Member <span class="text-red-500">*</span></label>
                    <select name="member_id" id="memberSelect" required class="select2-init w-full px-3 py-2.5 bg-gray-50 border border-gray-300 rounded-lg focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                        <option value="">-- Choose Member --</option>
                        <?php while($m = mysqli_fetch_assoc($members)): ?>
                            <option value="<?= $m['id'] ?>" <?= $pre_select_member == $m['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?> (<?= htmlspecialchars($m['member_no']) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Scheme Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account Scheme <span class="text-red-500">*</span></label>
                    <select name="scheme_id" id="schemeSelect" required class="w-full px-3 py-2.5 bg-gray-50 border border-gray-300 rounded-lg focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                        <option value="">-- Choose Scheme --</option>
                        <?php while($s = mysqli_fetch_assoc($schemes)): ?>
                            <option value="<?= $s['id'] ?>">[<?= $s['scheme_type'] ?>] <?= htmlspecialchars($s['scheme_name']) ?> - <?= $s['interest_rate'] ?>%</option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <!-- Dynamic Fields Container -->
            <div id="dynamicFields" class="hidden bg-indigo-50/50 p-5 rounded-xl border border-indigo-100 mt-6 space-y-4">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-medium text-indigo-900 flex items-center gap-2" id="schemeTitle">
                        <i class="ph ph-info"></i> Scheme Details
                    </h3>
                    <span class="text-xs font-semibold bg-white text-indigo-600 px-2.5 py-1 rounded-md border border-indigo-200" id="schemeInterest">0%</span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" id="amountLabel">Amount (₹) <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" min="0" name="amount" id="amountInput" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                        <p class="text-xs text-gray-500 mt-1" id="minAmountHint"></p>
                    </div>
                    
                    <div id="tenureContainer">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tenure (Months) <span class="text-red-500">*</span></label>
                        <input type="number" min="1" max="120" name="tenure" id="tenureInput" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                    </div>
                </div>

                <!-- Admin Backdate Entry -->
                <?php if($_SESSION['role'] == 'admin'): ?>
                <div class="border-t border-indigo-200 pt-4 mt-2">
                    <label class="block text-sm font-medium text-indigo-900 mb-1">Retroactive Opening Date (Admin Only)</label>
                    <input type="date" name="backdate" max="<?= date('Y-m-d') ?>" class="w-full md:w-1/2 px-3 py-2.5 border border-indigo-300 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white outline-none">
                    <p class="text-xs text-indigo-500 mt-1">Leave blank for today's date. Affects maturity calculation.</p>
                </div>
                <?php endif; ?>

                <!-- Savings Linkage -->
                <div id="savingsLinkageSection" class="hidden border-t border-indigo-200 pt-4 mt-2">
                    <label class="block text-sm font-medium text-indigo-900 mb-1">Link Savings Account (Optional)</label>
                    <select name="linked_savings_account" id="linkedSavingsAccount" class="w-full md:w-1/2 px-3 py-2.5 border border-indigo-300 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white outline-none">
                        <option value="">-- No Linked Savings --</option>
                    </select>
                    <p class="text-xs text-indigo-500 mt-1">Link to automatically route maturity payouts or fund opening.</p>
                </div>

                <!-- Loan Specific Section -->
                <div id="loanSection" class="hidden border-t border-indigo-200 pt-5 mt-4 space-y-4">
                    <h4 class="font-medium text-indigo-900 text-sm flex items-center gap-2 mb-3">
                        <i class="ph ph-file-text"></i> Loan Processing Documents & Guarantor
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Guarantor Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="guarantor_name" id="gName" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Guarantor Contact <span class="text-red-500">*</span></label>
                            <input type="text" name="guarantor_phone" id="gPhone" pattern="[0-9]{10}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Applicant Photo (Image) <span class="text-red-500">*</span></label>
                            <input type="file" name="photo_path" id="gPhoto" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Signed Document (PDF/Image) <span class="text-red-500">*</span></label>
                            <input type="file" name="document_path" id="gDoc" accept="image/*,.pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        </div>
                    </div>
                    
                    <!-- Loan Modifiers -->
                    <div class="mt-4 p-4 bg-white rounded-lg border border-indigo-100">
                        <label class="block text-sm font-medium text-indigo-900 mb-2">Interest Method <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-6">
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" name="loan_interest_type" value="Flat" checked class="text-indigo-600 focus:ring-indigo-500">
                                <span>Flat Rate</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" name="loan_interest_type" value="Reducing" class="text-indigo-600 focus:ring-indigo-500">
                                <span>Reducing Balance</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="text-xs text-emerald-700 bg-emerald-50 p-3 rounded-lg border border-emerald-100 hidden mt-4" id="projectionBox">
                    <strong>Projection:</strong> <span id="projectionText"></span>
                </div>
            </div>

        </div>

        <div class="p-6 border-t border-gray-100 bg-gray-50 flex justify-end">
            <button type="submit" name="open_account" id="submitBtn" disabled class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg text-sm font-medium shadow-sm transition-colors flex items-center gap-2">
                <i class="ph ph-check-circle text-lg"></i> Open Account
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
const schemesData = <?= json_encode($schemes_json) ?>;
const savingsData = <?= json_encode($savings_json) ?>;
const select = document.getElementById('schemeSelect');
const memberSelect = document.getElementById('memberSelect');
const linkedSavingsAccount = document.getElementById('linkedSavingsAccount');
const savingsLinkageSection = document.getElementById('savingsLinkageSection');
const dynamicFields = document.getElementById('dynamicFields');
const title = document.getElementById('schemeTitle');
const interest = document.getElementById('schemeInterest');
const amtLabel = document.getElementById('amountLabel');
const amtInput = document.getElementById('amountInput');
const minHint = document.getElementById('minAmountHint');
const tenureCont = document.getElementById('tenureContainer');
const tenureInput = document.getElementById('tenureInput');
const submitBtn = document.getElementById('submitBtn');

// Member Select Change (Populate Savings Linkage)
$('#memberSelect').on('change', function() {
    const memId = $(this).val();
    linkedSavingsAccount.innerHTML = '<option value="">-- No Linked Savings --</option>';
    if(memId && savingsData[memId]) {
        savingsData[memId].forEach(acc => {
            linkedSavingsAccount.innerHTML += `<option value="${acc.id}">${acc.account_no} (Bal: ₹${parseFloat(acc.current_balance).toFixed(2)})</option>`;
        });
    }
});

$('#schemeSelect').on('change', function() {
    const id = $(this).val();
    if(!id) {
        dynamicFields.classList.add('hidden');
        submitBtn.disabled = true;
        return;
    }
    
    const scheme = schemesData[id];
    dynamicFields.classList.remove('hidden');
    submitBtn.disabled = false;
    
    title.innerHTML = `<i class="ph ph-info"></i> ${scheme.scheme_type} Account Details`;
    interest.innerText = `${scheme.interest_rate}% per annum`;
    minHint.innerText = `Minimum allowed: ₹${scheme.minimum_amount}`;
    amtInput.min = scheme.minimum_amount;
    
    // Logic based on types
    const loanSection = document.getElementById('loanSection');
    const loanReqs = [document.getElementById('gName'), document.getElementById('gPhone'), document.getElementById('gPhoto'), document.getElementById('gDoc')];
    
    // Reset Loan fields
    loanSection.classList.add('hidden');
    loanReqs.forEach(el => el.required = false);

    if(scheme.scheme_type === 'Savings') {
        amtLabel.innerHTML = 'Initial Opening Balance (₹) <span class="text-red-500">*</span>';
        tenureCont.classList.add('hidden');
        tenureInput.required = false;
        tenureInput.value = '';
        savingsLinkageSection.classList.add('hidden');
    } 
    else if(scheme.scheme_type === 'Loan') {
        savingsLinkageSection.classList.remove('hidden');
        amtLabel.innerHTML = 'Loan Principal Amount (₹) <span class="text-red-500">*</span>';
        tenureCont.classList.remove('hidden');
        tenureInput.required = true;
        
        loanSection.classList.remove('hidden');
        loanReqs.forEach(el => el.required = true);
    }
    else if(scheme.scheme_type === 'RD' || scheme.scheme_type === 'DD') {
        savingsLinkageSection.classList.remove('hidden');
        amtLabel.innerHTML = 'Installment Amount Setup (₹) <span class="text-red-500">*</span>';
        tenureCont.classList.remove('hidden');
        tenureInput.required = true;
    }
    else {
        // FD, MIS
        savingsLinkageSection.classList.remove('hidden');
        amtLabel.innerHTML = 'Principal Deposit Amount (₹) <span class="text-red-500">*</span>';
        tenureCont.classList.remove('hidden');
        tenureInput.required = true;
    }
});

});
</script>

<?php require_once '../includes/footer.php'; ?>
