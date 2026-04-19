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
        WHERE a.id = $id AND a.account_type IN ('Savings', 'Loan', 'RD', 'FD', 'MIS', 'DD')";
        
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
            <a href="pdf_statement.php?id=<?= $id ?>" target="_blank" class="bg-rose-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-rose-700 transition flex items-center gap-1 shadow-md">
                <i class="ph ph-file-pdf"></i> Download Official PDF
            </a>
            <button onclick="window.print()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition flex items-center gap-1 shadow-md">
                <i class="ph ph-printer"></i> Standard Print
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
            <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Current Balance / Total Liability</p>
            <p class="text-2xl font-bold <?= $acc['current_balance'] < 0 ? 'text-rose-600' : 'text-emerald-600' ?> tracking-tight">
                <?php 
                    $display_bal = abs($acc['current_balance']);
                    $is_loan_debt = ($acc['account_type'] == 'Loan' || $acc['current_balance'] < 0);
                    
                    if($acc['account_type'] == 'Loan') {
                        // For Loans, calculate Total Outstanding (Principal + Interest + Fines)
                        $liability_res = mysqli_query($conn, "SELECT SUM(emi_amount + fine_amount) as total FROM loan_schedules WHERE account_id = $id AND status IN ('Pending', 'Overdue')");
                        $liability = mysqli_fetch_assoc($liability_res)['total'] ?? 0;
                        $display_bal = $liability;
                    }
                    
                    echo formatCurrency($display_bal);
                ?>
                <?= $is_loan_debt ? '<span class="text-xs px-1 bg-red-100 text-red-800 rounded mb-1 inline-block align-top">Dr</span>' : '' ?>
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
                    <th class="py-3 font-semibold w-1/5">Date</th>
                    <th class="py-3 font-semibold w-1/5">TXN ID</th>
                    <th class="py-3 font-semibold pb-3 w-1/3">Particulars</th>
                    <th class="py-3 font-semibold text-right text-rose-600 w-1/6">Debit (Dr)</th>
                    <th class="py-3 font-semibold text-right text-emerald-600 w-1/6">Credit (Cr)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if(mysqli_num_rows($txns) > 0): ?>
                    <?php while($t = mysqli_fetch_assoc($txns)): ?>
                        <?php
                            if ($acc['account_type'] == 'Loan') {
                                // For Loan accounts:
                                // EMI, Deposit, Interest (if it's a refund/reversal?) are credits to the balance (reducing debt)
                                // Account-Open (Disbursal), Withdrawal, Fine are debits (increasing debt)
                                $is_credit = in_array($t['transaction_type'], ['EMI', 'Deposit']);
                            } else {
                                // For Savings, FD, RD, MIS, DD:
                                // Deposit, Interest, Account-Open, EMI (if any) are credits (increasing balance)
                                // Withdrawal, Fine, Pre-Closure are debits (decreasing balance)
                                $is_credit = in_array($t['transaction_type'], ['Deposit', 'Interest', 'Account-Open', 'EMI']);
                            }
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 text-gray-600"><?= date('d M Y, h:i A', strtotime($t['transaction_date'])) ?></td>
                            <td class="py-3 font-mono text-xs">
                                <a href="../transactions/receipt.php?id=<?= $t['id'] ?>&duplicate=1" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-bold underline decoration-dotted underline-offset-4" title="Print Duplicate Receipt">
                                    <?= $t['transaction_id'] ?>
                                </a>
                            </td>
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
    
    <?php if($acc['account_type'] == 'Loan'): ?>
    <div class="p-8 pt-0 mt-8 page-break-before">
        <h3 class="font-semibold text-gray-800 mb-4 pb-2 border-b">EMI Amortization & Clearance Schedule</h3>
        <div class="bg-white border text-sm border-gray-100 rounded-lg overflow-hidden">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 border-b border-gray-100">
                        <th class="py-3 px-4 font-semibold">Inst. No</th>
                        <th class="py-3 px-4 font-semibold">Due Date</th>
                        <th class="py-3 px-4 font-semibold text-right">EMI Amount</th>
                        <th class="py-3 px-4 font-semibold text-right">Principal</th>
                        <th class="py-3 px-4 font-semibold text-right">Interest</th>
                        <th class="py-3 px-4 font-semibold text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php
                    $sch_res = mysqli_query($conn, "SELECT * FROM loan_schedules WHERE account_id = $id ORDER BY installment_no ASC");
                    while($sch = mysqli_fetch_assoc($sch_res)):
                    ?>
                    <tr class="hover:bg-gray-50/50">
                        <td class="py-3 px-4 text-gray-600 font-mono">#<?= str_pad($sch['installment_no'], 2, '0', STR_PAD_LEFT) ?></td>
                        <td class="py-3 px-4 text-gray-800 font-medium"><?= date('d M Y', strtotime($sch['due_date'])) ?></td>
                        <td class="py-3 px-4 text-right font-medium text-gray-800"><?= formatCurrency($sch['emi_amount']) ?></td>
                        <td class="py-3 px-4 text-right text-gray-500"><?= formatCurrency($sch['principal_component']) ?></td>
                        <td class="py-3 px-4 text-right text-gray-500"><?= formatCurrency($sch['interest_component']) ?></td>
                        <td class="py-3 px-4 text-right">
                            <?php if($sch['status'] == 'Paid'): ?>
                                <span class="bg-emerald-100 text-emerald-700 px-2 py-1 rounded text-[10px] font-bold uppercase">PAID</span>
                            <?php elseif($sch['status'] == 'Overdue'): ?>
                                <span class="bg-rose-100 text-rose-700 px-2 py-1 rounded text-[10px] font-bold uppercase">DUES</span>
                            <?php else: ?>
                                <span class="bg-amber-100 text-amber-700 px-2 py-1 rounded text-[10px] font-bold uppercase">UPCOMING</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if($acc['account_type'] == 'RD'): ?>
    <div class="p-8 pt-0 mt-8 page-break-before">
        <h3 class="font-semibold text-gray-800 mb-4 pb-2 border-b">Recurring Deposit Schedule</h3>
        <p class="text-xs text-gray-500 mb-4">Dynamically calculated based on tracked ledger deposits matching the fixed monthly installment commitment.</p>
        <div class="bg-white border text-sm border-gray-100 rounded-lg overflow-hidden">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 border-b border-gray-100">
                        <th class="py-3 px-4 font-semibold">Inst. No</th>
                        <th class="py-3 px-4 font-semibold">Scheduled Date</th>
                        <th class="py-3 px-4 font-semibold text-right">Commitment Amount</th>
                        <th class="py-3 px-4 font-semibold text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php
                    $paid_res = mysqli_query($conn, "SELECT SUM(amount) as tot FROM transactions WHERE account_id = $id AND transaction_type IN ('Deposit', 'Account-Open', 'EMI')");
                    $paid_total = mysqli_fetch_assoc($paid_res)['tot'] ?? 0;
                    $deposit_amt = $acc['installment_amount'];
                    
                    for($i = 0; $i < $acc['tenure_months']; $i++):
                        $due_date = date('Y-m-d', strtotime($acc['opening_date'] . " + $i months"));
                        
                        if($paid_total >= $deposit_amt) {
                            $status = 'PAID';
                            $status_class = 'bg-emerald-100 text-emerald-700';
                            $paid_total -= $deposit_amt;
                        } elseif($due_date < date('Y-m-d')) {
                            $status = 'DUES';
                            $status_class = 'bg-rose-100 text-rose-700';
                        } else {
                            $status = 'UPCOMING';
                            $status_class = 'bg-amber-100 text-amber-700';
                        }
                    ?>
                    <tr class="hover:bg-gray-50/50">
                        <td class="py-3 px-4 text-gray-600 font-mono">#<?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></td>
                        <td class="py-3 px-4 text-gray-800 font-medium"><?= date('d M Y', strtotime($due_date)) ?></td>
                        <td class="py-3 px-4 text-right font-medium text-gray-800"><?= formatCurrency($deposit_amt) ?></td>
                        <td class="py-3 px-4 text-right">
                            <span class="<?= $status_class ?> px-2 py-1 rounded text-[10px] font-bold uppercase"><?= $status ?></span>
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>
<style>
    @media print {
        /* Reset containers to allow full page printing */
        html, body { height: auto !important; overflow: visible !important; }
        .flex-1, .flex-col, main { display: block !important; overflow: visible !important; height: auto !important; }
        aside, header, footer, .no-print { display: none !important; }
        
        body { background: white; color: black; font-size: 12pt; }
        .max-w-5xl { max-width: 100% !important; width: 100% !important; margin: 0 !important; border: none !important; box-shadow: none !important; }
        .p-8 { padding: 0.5in !important; }
        .bg-gray-50 { background: white !important; border-bottom: 2px solid #333 !important; }
        .bg-indigo-50 { background: #f8fafc !important; border: 1px solid #e2e8f0 !important; }
        .page-break-before { page-break-before: always; margin-top: 0.5in; }
        .print-only { display: block !important; }
        table { border-collapse: collapse !important; width: 100% !important; }
        th { border-bottom: 2px solid #000 !important; color: black !important; padding: 10px 5px !important; }
        td { border-bottom: 1px solid #eee !important; padding: 10px 5px !important; }
        .authorized-sign { margin-top: 50px; text-align: right; }
    }
</style>

<div class="authorized-sign hidden print:block pr-8">
    <div class="inline-block text-center">
        <?php $stamp = getSetting($conn, 'bank_stamp'); if($stamp && file_exists('../'.$stamp)): ?>
            <img src="../<?= $stamp ?>" class="h-24 mx-auto mb-2 opacity-80" alt="Authorized Seal">
        <?php else: ?>
            <div class="h-24 w-40 border border-dashed border-gray-300 mx-auto mb-2 rounded flex items-center justify-center text-[10px] text-gray-300 italic">Affix Seal Here</div>
        <?php endif; ?>
        <p class="text-sm font-bold text-gray-800">Authorized Signatory</p>
        <p class="text-[10px] text-gray-400 uppercase tracking-widest"><?= htmlspecialchars(getSetting($conn, 'bank_name')) ?></p>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
