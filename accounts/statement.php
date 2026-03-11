<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$sql = "SELECT a.*, m.first_name, m.last_name, m.member_no, m.address, 
        s.scheme_name, s.interest_rate 
        FROM accounts a 
        JOIN members m ON a.member_id = m.id 
        JOIN schemes s ON a.scheme_id = s.id 
        WHERE a.id = $id AND a.account_type IN ('Savings', 'Loan')";
        
$res = mysqli_query($conn, $sql);
$acc = mysqli_fetch_assoc($res);

if(!$acc) {
    die("Statement not available for this account type or account not found.");
}

$txns = mysqli_query($conn, "SELECT * FROM transactions WHERE account_id = $id ORDER BY transaction_date ASC");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-5xl mx-auto no-print">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-file-text text-indigo-500 text-3xl"></i> Account Statement
            </h1>
            <p class="text-gray-500 text-sm mt-1">Detailed transaction history and balance sheet.</p>
        </div>
        <div class="flex gap-2">
            <a href="view.php" class="bg-white border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition">Back</a>
            <button onclick="window.print()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition flex items-center gap-1">
                <i class="ph ph-printer"></i> Print Statement
            </button>
        </div>
    </div>
</div>

<div class="max-w-5xl mx-auto bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden print:shadow-none print:border-none print:max-w-full">
    
    <!-- Bank Header for Print -->
    <div class="p-8 border-b border-gray-100 hidden print:block text-center">
        <?php $logo = getSetting($conn, 'bank_logo'); if($logo && file_exists('../'.$logo)): ?>
            <img src="../<?= $logo ?>" alt="Bank Logo" class="h-12 mx-auto mb-2 object-contain">
        <?php else: ?>
            <h1 class="text-2xl font-bold text-gray-900 pb-1 mb-1 inline-block uppercase"><?= htmlspecialchars(getSetting($conn, 'bank_name')) ?></h1>
        <?php endif; ?>
        <p class="text-xs text-gray-500 whitespace-pre-wrap mb-2"><?= htmlspecialchars(getSetting($conn, 'bank_address')) ?></p>
        <p class="text-sm border-t border-gray-200 pt-2 pb-2 mt-2 font-bold text-gray-600 uppercase tracking-wider inline-block">Official Account Statement</p>
    </div>

    <!-- Account Details -->
    <div class="p-8 grid grid-cols-2 lg:grid-cols-4 gap-6 bg-gray-50">
        <div>
            <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Customer</p>
            <p class="text-md font-bold text-gray-800"><?= htmlspecialchars($acc['first_name'].' '.$acc['last_name']) ?></p>
            <p class="text-sm text-gray-600"><?= htmlspecialchars($acc['member_no']) ?></p>
        </div>
        <div>
            <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Account Number</p>
            <p class="text-md font-bold text-indigo-700 font-mono"><?= htmlspecialchars($acc['account_no']) ?></p>
            <p class="text-sm text-gray-600"><?= $acc['scheme_name'] ?></p>
        </div>
        <div>
            <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Interest Rate / Type</p>
            <p class="text-md font-bold text-gray-800"><?= $acc['interest_rate'] ?>% p.a. <?= $acc['account_type'] == 'Loan' ? '<span class="text-xs font-normal text-indigo-500">('.$acc['loan_interest_type'].' Rate)</span>' : '' ?></p>
            <p class="text-sm text-gray-600 bg-gray-200 px-2 py-0.5 rounded inline-block mt-1"><?= $acc['account_type'] ?></p>
        </div>
        <div class="text-right border-l pl-6 border-gray-200">
            <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Current Balance</p>
            <p class="text-2xl font-bold <?= $acc['current_balance'] < 0 ? 'text-rose-600' : 'text-emerald-600' ?> tracking-tight">
                <?= formatCurrency(abs($acc['current_balance'])) ?>
                <?= $acc['current_balance'] < 0 ? '<span class="text-xs px-1 bg-red-100 text-red-800 rounded mb-1 inline-block align-top">Dr</span>' : '' ?>
            </p>
        </div>
    </div>

    <?php if($acc['account_type'] == 'Loan'): ?>
    <div class="px-8 pb-8 pt-0 bg-gray-50 text-sm flex gap-4">
        <div class="bg-indigo-50 border border-indigo-100 rounded-lg p-4 flex-1 flex justify-between items-center text-indigo-800">
            <span class="font-semibold uppercase tracking-wider text-xs">Standard Monthly EMI</span>
            <span class="font-bold font-mono text-xl"><?= formatCurrency($acc['installment_amount']) ?></span>
        </div>
        <div class="bg-indigo-50 border border-indigo-100 rounded-lg p-4 flex-1 flex justify-between items-center text-indigo-800">
            <span class="font-semibold uppercase tracking-wider text-xs">Loan Tenure</span>
            <span class="font-bold font-mono text-xl"><?= $acc['tenure_months'] ?> Months</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statement Table -->
    <div class="p-8">
        <h3 class="font-semibold text-gray-800 mb-4 border-b pb-2">Transaction History</h3>
        
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="text-gray-500 border-b-2 border-gray-200">
                    <th class="py-3 font-semibold w-1/6">Date</th>
                    <th class="py-3 font-semibold w-1/6">TXN ID</th>
                    <th class="py-3 font-semibold pb-3 w-1/4">Particulars</th>
                    <th class="py-3 font-semibold text-right text-rose-600 w-1/6">Debit (Dr)</th>
                    <th class="py-3 font-semibold text-right text-emerald-600 w-1/6">Credit (Cr)</th>
                    <th class="py-3 font-semibold text-right w-1/6">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if(mysqli_num_rows($txns) > 0): ?>
                    <?php while($t = mysqli_fetch_assoc($txns)): ?>
                        <?php
                            $is_credit = in_array($t['transaction_type'], ['Deposit', 'EMI']);
                            // Loan disbursals are technically debits to the bank but credit to the customer loan account balance conceptually in this basic db schema 
                            // as we track negative balance. However, visually in a statement, Loan Disbursal is a Debit from the customer's standing.
                            if($acc['account_type'] == 'Loan' && $t['transaction_type'] == 'Account-Open') {
                                $is_credit = false; // Disbursal is a debit to their required payout
                            }
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 text-gray-600"><?= date('d M Y, h:i A', strtotime($t['transaction_date'])) ?></td>
                            <td class="py-3 font-mono text-xs text-gray-500"><?= $t['transaction_id'] ?></td>
                            <td class="py-3">
                                <span class="block font-medium text-gray-800"><?= htmlspecialchars($t['description']) ?></span>
                                <span class="text-xs text-gray-500"><?= $t['transaction_type'] ?></span>
                            </td>
                            <td class="py-3 text-right font-medium text-rose-600">
                                <?= !$is_credit ? formatCurrency(abs($t['amount'])) : '-' ?>
                            </td>
                            <td class="py-3 text-right font-medium text-emerald-600">
                                <?= $is_credit ? formatCurrency(abs($t['amount'])) : '-' ?>
                            </td>
                            <td class="py-3 text-right font-bold text-gray-800 font-mono tracking-tight text-sm">
                                <?= formatCurrency(abs($t['balance_after'])) ?>
                                <?= $t['balance_after'] < 0 ? '<span class="text-[10px] text-rose-500 ml-0.5">Dr</span>' : '<span class="text-[10px] text-emerald-500 ml-0.5">Cr</span>' ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="py-8 text-center text-gray-500">No transactions found for this account.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="mt-8 text-center text-xs text-gray-400 print:block hidden">
            <p>*** End of Statement ***</p>
            <p class="mt-2">Generated on <?= date('d M Y, H:i:s') ?>. This is a computer generated statement and requires no signature.</p>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
