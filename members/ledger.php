<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$m_res = mysqli_query($conn, "SELECT * FROM members WHERE id = $member_id");
if(!$member = mysqli_fetch_assoc($m_res)) {
    die("Member not found.");
}

// Stats for Ledger
$acc_res = mysqli_query($conn, "SELECT * FROM accounts WHERE member_id = $member_id");
$total_deposits = 0;
$total_loans = 0;
while($a = mysqli_fetch_assoc($acc_res)) {
    if($a['account_type'] == 'Loan' && $a['status'] != 'closed') {
        $total_loans += abs($a['current_balance']);
    } elseif($a['account_type'] != 'Loan' && in_array($a['status'], ['active','matured'])) {
        $total_deposits += $a['current_balance'];
    }
}
mysqli_data_seek($acc_res, 0); // reset for accounts table

// Fetch Full Transaction History for this member
$txn_sql = "SELECT t.*, a.account_no, a.account_type 
            FROM transactions t 
            JOIN accounts a ON t.account_id = a.id 
            WHERE a.member_id = $member_id 
            ORDER BY t.transaction_date DESC";
$txns = mysqli_query($conn, $txn_sql);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-user-focus text-indigo-500 text-3xl"></i> Customer Ledger
            </h1>
            <p class="text-gray-500 text-sm mt-1">Complete portfolio and history for <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></p>
        </div>
        <a href="../members/list.php" class="bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            <i class="ph ph-arrow-left mr-1"></i> Directory
        </a>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center gap-4 border-l-4 border-l-indigo-500">
            <div class="w-12 h-12 rounded-full bg-gray-50 text-gray-500 flex items-center justify-center text-2xl">
                <i class="ph ph-identification-card"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">KYC Info</p>
                <div class="font-bold text-gray-800"><?= htmlspecialchars($member['member_no']) ?></div>
                <div class="text-xs text-emerald-600 font-medium">Verified Aadhar</div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center gap-4 border-l-4 border-l-emerald-500">
            <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl">
                <i class="ph ph-piggy-bank"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">Total Assets (Deposits)</p>
                <div class="text-xl font-bold text-emerald-600 tracking-tight"><?= formatCurrency($total_deposits) ?></div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center gap-4 border-l-4 border-l-rose-500">
            <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center text-2xl">
                <i class="ph ph-hand-coins"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">Total Liabilities (Loans)</p>
                <div class="text-xl font-bold text-rose-600 tracking-tight"><?= formatCurrency($total_loans) ?></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Accounts Summary -->
        <div class="lg:col-span-1">
            <div class="bg-indigo-900 rounded-2xl shadow-sm border border-indigo-800 overflow-hidden mb-6">
                <div class="p-5 border-b border-indigo-800 flex items-center justify-between">
                    <h3 class="font-semibold text-white">Active Product Summary</h3>
                </div>
                <div class="divide-y divide-indigo-800">
                    <?php if(mysqli_num_rows($acc_res) > 0): ?>
                        <?php while($a = mysqli_fetch_assoc($acc_res)): ?>
                            <div class="p-4 hover:bg-indigo-800/50 transition-colors">
                                <div class="flex justify-between items-start mb-1">
                                    <div class="font-medium text-indigo-50 font-mono text-sm"><?= $a['account_no'] ?></div>
                                    <div class="text-xs px-2 py-0.5 rounded bg-indigo-800 text-indigo-200">
                                        <?= $a['status'] ?>
                                    </div>
                                </div>
                                <div class="flex justify-between items-end">
                                    <div class="text-xs text-indigo-300 uppercase tracking-widest font-bold"><?= $a['account_type'] ?></div>
                                    <div class="font-bold text-lg <?= $a['account_type']=='Loan' ? 'text-rose-400' : 'text-emerald-400' ?>">
                                        <?= formatCurrency(abs($a['current_balance'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-6 text-center text-indigo-300 text-sm">No accounts found.</div>
                    <?php endif; ?>
                </div>
                <div class="p-4 bg-indigo-950">
                    <a href="../accounts/open.php?member_id=<?= $member_id ?>" class="block w-full text-center text-sm font-medium text-indigo-300 hover:text-white transition-colors">
                        <i class="ph ph-plus-circle"></i> Open New Account
                    </a>
                </div>
            </div>
        </div>

        <!-- Ledger History -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800 text-lg"><i class="ph ph-list-numbers text-indigo-500 mr-2"></i> Transaction History (Passbook View)</h3>
                </div>
                <div class="overflow-x-auto h-[600px] overflow-y-auto relative">
                    <table class="w-full text-left border-collapse">
                        <thead class="sticky top-0 sticky-header bg-gray-50/95 backdrop-blur-sm z-10 shadow-sm border-b border-gray-200">
                            <tr class="text-gray-500 text-xs uppercase tracking-wider">
                                <th class="px-6 py-4 font-bold">Date & TXN ID</th>
                                <th class="px-6 py-4 font-bold">Details & A/C</th>
                                <th class="px-6 py-4 font-bold text-emerald-600 text-right w-32 border-l border-gray-200 bg-emerald-50/30">Credit (Cr)</th>
                                <th class="px-6 py-4 font-bold text-rose-600 text-right w-32 border-x border-gray-200 bg-rose-50/30">Debit (Dr)</th>
                                <th class="px-6 py-4 font-bold text-right text-gray-700 w-32">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <?php if(mysqli_num_rows($txns) > 0): ?>
                                <?php while($t = mysqli_fetch_assoc($txns)): ?>
                                    <tr class="hover:bg-indigo-50/30 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-gray-800"><?= date('d M Y, h:i A', strtotime($t['transaction_date'])) ?></div>
                                            <div class="text-xs font-mono text-gray-400 mt-0.5"><?= $t['transaction_id'] ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-gray-700"><?= htmlspecialchars($t['description']) ?></div>
                                            <div class="text-[11px] text-gray-500 mt-0.5 uppercase tracking-wider">
                                                <span class="font-mono text-indigo-600 mr-1"><?= $t['account_no'] ?></span> (<?= $t['account_type'] ?>)
                                            </div>
                                        </td>
                                        
                                        <!-- Determine Credit/Debit presentation logic based on account type and transaction type -->
                                        <?php
                                            $cr = '-'; $dr = '-';
                                            // Simplistic Ledger Logging
                                            if($t['account_type'] == 'Loan') {
                                                if($t['transaction_type'] == 'Transaction' || $t['transaction_type'] == 'Account-Open') {
                                                    $dr = formatCurrency($t['amount']); // Disbursal is debit (money out of NBFC/increases loan balance)
                                                } else {
                                                    $cr = formatCurrency($t['amount']); // Repayment/EMI is credit
                                                }
                                            } else {
                                                if(in_array($t['transaction_type'], ['Deposit', 'Interest', 'Account-Open'])) {
                                                    $cr = formatCurrency($t['amount']); // Money entering customer account
                                                } else {
                                                    $dr = formatCurrency($t['amount']); // Money leaving (withdrawal)
                                                }
                                            }
                                        ?>
                                        <td class="px-6 py-4 font-semibold text-emerald-600 text-right border-l border-gray-100"><?= $cr ?></td>
                                        <td class="px-6 py-4 font-semibold text-rose-600 text-right border-x border-gray-100"><?= $dr ?></td>
                                        <td class="px-6 py-4 font-bold text-gray-800 text-right">
                                            <?= formatCurrency(abs($t['balance_after'])) ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    <i class="ph ph-receipt text-4xl text-gray-300 mb-3 block"></i>
                                    No transactions recorded for this customer.
                                </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="bg-gray-50 border-t border-gray-100 p-4 text-center">
                    <button class="bg-white border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-100 px-4 py-2 rounded shadow-sm">
                        <i class="ph ph-download mr-1"></i> Download Passbook PDF
                    </button>
                    <span class="text-xs text-gray-400 block mt-2">To download standard RBI compliant passbook, click above.</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
