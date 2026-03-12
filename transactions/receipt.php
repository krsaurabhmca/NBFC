<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$txn_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$sql = "SELECT t.*, a.account_no, a.account_type, m.first_name, m.last_name, m.member_no, m.phone, u.name as staff_name 
        FROM transactions t 
        JOIN accounts a ON t.account_id = a.id 
        JOIN members m ON a.member_id = m.id 
        JOIN users u ON t.created_by = u.id 
        WHERE t.id = $txn_id";
$res = mysqli_query($conn, $sql);
$txn = mysqli_fetch_assoc($res);

if(!$txn) {
    die("Transaction Not Found.");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt - <?= $txn['transaction_id'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #e5e7eb; padding: 2rem; }
        .receipt-container { background: white; max-width: 450px; margin: 0 auto; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        @media print {
            body { background: white; padding: 0; }
            .receipt-container { box-shadow: none; border: 1px solid #ddd; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="receipt-container border-t-8 border-indigo-600 rounded-b-xl relative">
        <div class="px-8 py-8 border-b border-gray-200 text-center">
            <?php $logo = getSetting($conn, 'bank_logo'); if($logo && file_exists('../'.$logo)): ?>
                <img src="../<?= $logo ?>" alt="Bank Logo" class="h-12 mx-auto mb-3 object-contain">
            <?php else: ?>
                <h1 class="text-2xl font-bold tracking-wider mb-1 flex items-center justify-center gap-2">
                    <i class="ph ph-bank text-indigo-600"></i> <?= htmlspecialchars(getSetting($conn, 'bank_name')) ?>
                </h1>
            <?php endif; ?>
            <p class="text-xs text-gray-400 mt-2 whitespace-pre-wrap"><?= htmlspecialchars(getSetting($conn, 'bank_address')) ?></p>
        </div>

        <div class="px-8 py-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="font-bold text-gray-800 text-lg uppercase tracking-wider">Transaction Receipt</h3>
                <span class="bg-gray-100 text-gray-800 font-mono text-xs px-2 py-1 rounded font-bold border border-gray-200"><?= $txn['transaction_id'] ?></span>
            </div>

            <div class="space-y-4 text-sm mt-4">
                <div class="grid grid-cols-2">
                    <span class="text-gray-500 font-medium">Date & Time</span>
                    <span class="text-right text-gray-800 font-semibold"><?= date('d M Y, h:i A', strtotime($txn['transaction_date'])) ?></span>
                </div>
                <div class="grid grid-cols-2">
                    <span class="text-gray-500 font-medium">Customer Name</span>
                    <span class="text-right text-gray-800 font-semibold"><?= htmlspecialchars($txn['first_name'].' '.$txn['last_name']) ?></span>
                </div>
                <div class="grid grid-cols-2">
                    <span class="text-gray-500 font-medium">Member ID</span>
                    <span class="text-right text-gray-800 font-mono"><?= htmlspecialchars($txn['member_no']) ?></span>
                </div>
                <div class="grid grid-cols-2">
                    <span class="text-gray-500 font-medium">Account No</span>
                    <span class="text-right text-indigo-700 font-mono font-bold bg-indigo-50 px-1 rounded inline-block w-max ml-auto">
                        <?= htmlspecialchars($txn['account_no']) ?>
                    </span>
                </div>
                <div class="grid grid-cols-2">
                    <span class="text-gray-500 font-medium">Account Type</span>
                    <span class="text-right text-gray-800 font-semibold"><?= htmlspecialchars($txn['account_type']) ?> Account</span>
                </div>
                
                <div class="py-4 border-y border-gray-100 my-2">
                    <div class="grid grid-cols-2 mb-2 items-center">
                        <span class="text-gray-700 font-bold">Transaction Type</span>
                        <?php
                            $is_credit = ($txn['account_type'] == 'Loan') 
                                ? in_array($txn['transaction_type'], ['EMI', 'Deposit']) 
                                : in_array($txn['transaction_type'], ['Deposit', 'Interest', 'Account-Open', 'EMI']);
                        ?>
                        <span class="text-right font-bold uppercase tracking-wider text-xs px-2 py-1 rounded w-max ml-auto
                            <?= $is_credit ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>">
                            <?= htmlspecialchars($txn['transaction_type']) ?>
                        </span>
                    </div>
                    <div class="grid grid-cols-2 items-end">
                        <span class="text-gray-600 font-bold uppercase tracking-wider text-xs">Amount</span>
                        <span class="text-right text-3xl font-bold tracking-tighter text-gray-900"><?= formatCurrency($txn['amount']) ?></span>
                    </div>
                </div>

                <div class="grid grid-cols-2">
                    <span class="text-gray-500 font-medium">Closing Balance</span>
                    <span class="text-right text-gray-800 font-semibold"><?= formatCurrency(abs($txn['balance_after'])) ?> <?= $txn['balance_after'] < 0 ? '(Due)' : '(Cr)' ?></span>
                </div>
                <div class="grid grid-cols-2">
                    <span class="text-gray-500 font-medium">Narration</span>
                    <span class="text-right text-gray-600 italic text-xs max-w-[200px] ml-auto">"<?= htmlspecialchars($txn['description']) ?>"</span>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-dashed border-gray-300">
                <div class="flex justify-between items-end relative">
                    <div class="text-xs text-gray-400 text-center">
                        <div class="border-b border-gray-300 w-24 mx-auto mb-1 pb-4"></div>
                        Customer Sign
                    </div>
                    
                    <?php $stamp = getSetting($conn, 'bank_stamp'); if($stamp && file_exists('../'.$stamp)): ?>
                        <div class="absolute left-1/2 -translate-x-1/2 bottom-0 opacity-40 mix-blend-multiply pointer-events-none">
                            <img src="../<?= $stamp ?>" class="h-20 object-contain max-w-[120px]">
                        </div>
                    <?php endif; ?>

                    <div class="text-xs text-gray-400 text-center relative z-10">
                        <div class="font-medium text-gray-800 mb-1"><?= htmlspecialchars($txn['staff_name']) ?></div>
                        Processed By / Cashier
                    </div>
                </div>
            </div>
            
            <p class="text-[10px] text-center text-gray-400 mt-6 leading-tight">This is an auto-generated receipt. Please retain it for future reference.</p>
        </div>
    </div>

    <div class="max-w-[450px] mx-auto mt-6 flex gap-4 no-print">
        <button onclick="window.print()" class="flex-1 bg-gray-800 hover:bg-gray-900 text-white py-3 rounded-lg font-medium transition-colors shadow-lg flex items-center justify-center gap-2">
            <i class="ph ph-printer text-xl"></i> Print Receipt
        </button>
        <a href="process.php" class="flex-1 bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 py-3 rounded-lg font-medium transition-colors shadow-sm flex items-center justify-center gap-2 text-center">
            <i class="ph ph-arrow-left text-xl"></i> Back to Process
        </a>
    </div>

</body>
</html>
