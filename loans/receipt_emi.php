<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$txn_id = isset($_GET['id']) ? sanitize($conn, $_GET['id']) : '';

// Fetch Transaction
$sql = "SELECT t.*, a.account_no, a.account_type, m.first_name, m.last_name, m.member_no, m.phone, u.name as staff_name 
        FROM transactions t 
        JOIN accounts a ON t.account_id = a.id 
        JOIN members m ON a.member_id = m.id 
        JOIN users u ON t.created_by = u.id 
        WHERE t.transaction_id = '$txn_id' OR t.id = '$txn_id'";
$res = mysqli_query($conn, $sql);
$txn = mysqli_fetch_assoc($res);

if(!$txn) {
    die("Master Transaction Not Found.");
}

// Fetch individual schedules paid in this session
$sch_sql = "SELECT * FROM loan_schedules WHERE transaction_id = '" . $txn['transaction_id'] . "'";
$sch_res = mysqli_query($conn, $sch_sql);
$schedules_paid = [];
while($s = mysqli_fetch_assoc($sch_res)) {
    $schedules_paid[] = $s;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EMI Receipt - <?= $txn['transaction_id'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; padding: 20px; }
        .receipt { background: white; max-width: 480px; margin: 0 auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); border-radius: 12px; }
        .dashed-line { border-top: 2px dashed #e5e7eb; margin: 15px 0; }
        @media print {
            body { background: white; padding: 0; }
            .receipt { box-shadow: none; border: 1px solid #eee; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="receipt overflow-hidden border-t-8 border-emerald-600">
        <!-- Header -->
        <div class="p-6 text-center border-b border-gray-100">
            <h1 class="text-xl font-extrabold text-gray-900 uppercase tracking-tight"><?= htmlspecialchars(getSetting($conn, 'bank_name')) ?></h1>
            <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-widest font-bold"><?= htmlspecialchars(getSetting($conn, 'bank_address')) ?></p>
            <div class="mt-4 inline-block bg-emerald-50 text-emerald-700 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border border-emerald-100">
                Loan Repayment Receipt
            </div>
        </div>

        <!-- Body -->
        <div class="p-6">
            <div class="flex justify-between items-start mb-6">
                <div class="bg-gray-50 border border-gray-100 rounded-lg p-3 w-1/2">
                    <span class="block text-[8px] text-gray-400 uppercase font-black tracking-widest">Customer Details</span>
                    <h2 class="text-xs font-bold text-gray-900"><?= htmlspecialchars($txn['first_name'].' '.$txn['last_name']) ?></h2>
                    <p class="text-[9px] text-gray-500 font-medium">No: <?= $txn['member_no'] ?></p>
                    <p class="text-[9px] text-indigo-600 font-bold mt-1">A/c: <?= $txn['account_no'] ?></p>
                </div>
                <div class="text-right">
                    <span class="block text-[8px] text-gray-400 uppercase font-black tracking-widest">Transaction ID</span>
                    <span class="text-xs font-mono font-bold text-gray-900"><?= $txn['transaction_id'] ?></span>
                    <p class="text-[9px] text-gray-500 mt-1"><?= date('d M Y', strtotime($txn['transaction_date'])) ?></p>
                    <p class="text-[9px] text-gray-400 uppercase"><?= date('h:i A', strtotime($txn['transaction_date'])) ?></p>
                </div>
            </div>

            <div class="border border-gray-100 rounded-xl overflow-hidden">
                <div class="px-4 py-2 bg-gray-50 border-b border-gray-100 mb-2">
                    <span class="text-[9px] text-gray-500 font-black uppercase tracking-widest">Payment Breakdown</span>
                </div>
                
                <div class="px-4 pb-4 space-y-3">
                    <?php 
                    $total_emi = 0;
                    $total_fine = 0;
                    $total_disc = 0;
                    $inst_nos = [];
                    foreach($schedules_paid as $s) {
                        $total_emi += $s['emi_amount'];
                        $total_fine += $s['fine_amount'];
                        $total_disc += $s['discount_amount'];
                        $inst_nos[] = $s['installment_no'];
                    }
                    sort($inst_nos);
                    $inst_range = (count($inst_nos) > 1) ? "#" . $inst_nos[0] . " to #" . end($inst_nos) : "#" . $inst_nos[0];
                    ?>
                    
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-gray-500 font-medium">EMI Installments (<?= $inst_range ?>)</span>
                        <span class="font-bold text-gray-900"><?= formatCurrency($total_emi) ?></span>
                    </div>

                    <?php if($total_fine > 0): ?>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-rose-500 font-medium">+ Cumulative Penalty/Late Fines</span>
                        <span class="font-bold text-rose-500"><?= formatCurrency($total_fine) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if($total_disc > 0): ?>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-emerald-600 font-medium">- Collection Discount/Waiver</span>
                        <span class="font-bold text-emerald-600"><?= formatCurrency($total_disc) ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="dashed-line"></div>
                    
                    <div class="flex justify-between items-end pt-1">
                        <div>
                            <span class="text-gray-900 font-black uppercase text-[10px] block leading-none">Net Amount Collected</span>
                            <span class="text-[8px] text-gray-400 uppercase tracking-tighters">Total In-hand Cash/Transfer</span>
                        </div>
                        <span class="text-3xl font-black text-emerald-700 tracking-tighter"><?= formatCurrency($txn['amount']) ?></span>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-2 gap-4 text-[9px] border-t border-gray-50 pt-4">
                <div>
                    <span class="text-gray-400 font-bold uppercase block mb-1">Receipt Mode</span>
                    <span class="text-gray-900 font-bold"><?= $schedules_paid[0]['payment_mode'] ?? 'System Ledger' ?></span>
                </div>
                <div class="text-right">
                    <span class="text-gray-400 font-bold uppercase block mb-1">Staff / Agent</span>
                    <span class="text-gray-900 font-bold"><?= htmlspecialchars($txn['staff_name']) ?></span>
                </div>
            </div>

            <div class="mt-8 flex justify-between items-center grayscale opacity-60">
                <div class="text-center group">
                    <div class="w-12 h-12 border border-gray-200 rounded flex items-center justify-center text-[8px] text-gray-300 group-hover:border-indigo-400 transition-colors">SEAL</div>
                    <span class="text-[7px] uppercase font-bold text-gray-400 block mt-1">Official Seal</span>
                </div>
                <div class="text-center">
                    <?php $stamp = getSetting($conn, 'bank_stamp'); if($stamp && file_exists('../'.$stamp)): ?>
                        <img src="../<?= $stamp ?>" class="h-16 mx-auto opacity-70 -mb-4" alt="Signature">
                    <?php endif; ?>
                    <div class="w-24 border-b border-gray-300 mt-4 mx-auto"></div>
                    <span class="text-[7px] uppercase font-bold text-gray-400 block mt-1">Authorized Receipt</span>
                </div>
            </div>

            <div class="mt-10 border-t border-gray-50 pt-4 text-center">
                <p class="text-[7px] text-gray-400 font-medium uppercase tracking-[0.2em]">This is an electronically generated valid receipt</p>
                <p class="text-[8px] text-indigo-400 font-black mt-1">NBFC CORE SERVICES &copy; <?= date('Y') ?></p>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="max-w-[480px] mx-auto mt-8 flex flex-col gap-3 no-print">
        <button onclick="window.print()" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-4 rounded-xl shadow-lg transition-all flex items-center justify-center gap-2">
            <i class="ph ph-printer text-2xl"></i> Print Receipt Now
        </button>
        <div class="flex gap-3">
            <a href="pay.php" class="flex-1 bg-white border border-gray-200 text-gray-600 font-bold py-3 rounded-xl text-center text-sm shadow-sm hover:bg-gray-50 transition-all">Next Payment</a>
            <a href="list.php" class="flex-1 bg-white border border-gray-200 text-gray-600 font-bold py-3 rounded-xl text-center text-sm shadow-sm hover:bg-gray-50 transition-all">Loan Book</a>
        </div>
        
        <?php if($_SESSION['role'] == 'admin'): ?>
        <div class="mt-4 pt-4 border-t border-gray-200 border-dashed">
            <a href="cancel_emi.php?txn_id=<?= $txn['transaction_id'] ?>" 
               onclick="return confirm('DANGER: This will permanently VOID this collection, revert the installments to pending, and adjust the ledger. Continue?')"
               class="w-full bg-rose-50 text-rose-600 border border-rose-100 font-black py-3 rounded-xl text-center text-[10px] uppercase tracking-[0.2em] shadow-sm hover:bg-rose-600 hover:text-white transition-all flex items-center justify-center gap-2">
                <i class="ph ph-trash-simple text-base"></i> Void & Cancel Payment
            </a>
        </div>
        <?php endif; ?>
    </div>

</body>
</html>
