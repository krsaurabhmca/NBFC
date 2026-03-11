<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$sql = "SELECT a.*, m.first_name, m.last_name, m.member_no, m.aadhar_no, m.address, 
        s.scheme_name, s.interest_rate, s.compounding_frequency 
        FROM accounts a 
        JOIN members m ON a.member_id = m.id 
        JOIN schemes s ON a.scheme_id = s.id 
        WHERE a.id = $id AND a.account_type IN ('FD', 'RD', 'MIS')";
        
$res = mysqli_query($conn, $sql);
$acc = mysqli_fetch_assoc($res);

if(!$acc) {
    die("Certificate not available for this account type or account not found.");
}

// Calculate Maturity Value (Basic Compound Interest for demo)
$p = $acc['principal_amount'];
$r = $acc['interest_rate'] / 100;
$t = $acc['tenure_months'] / 12;

$monthly_return = 0;
if($acc['account_type'] == 'FD') {
    // A = P(1 + r/n)^(nt)
    $n = ($acc['compounding_frequency'] == 'Quarterly') ? 4 : (($acc['compounding_frequency'] == 'Monthly') ? 12 : 1);
    $maturity_value = $p * pow((1 + $r/$n), ($n * $t));
} elseif($acc['account_type'] == 'RD') {
    // RD Formula approximation
    $installment = $acc['installment_amount'];
    $months = $acc['tenure_months'];
    $maturity_value = 0;
    for($i = $months; $i > 0; $i--) {
        // Interest on each installment
        $maturity_value += $installment + ($installment * $r * ($i/12)); 
    }
} elseif($acc['account_type'] == 'MIS') {
    $maturity_value = $p; // Principal returned at end
    $monthly_return = ($p * $r) / 12;
}

$maturity_value = round($maturity_value, 2);
$monthly_return = round($monthly_return, 2);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $acc['account_type'] ?> Certificate - <?= $acc['account_no'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .no-print { display: none !important; }
        }
        .cert-border {
            border: 8px solid #4f46e5;
            padding: 4px;
            outline: 2px solid #c7d2fe;
            outline-offset: -12px;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans p-8 flex justify-center min-h-screen">

    <div class="max-w-4xl w-full">
        <!-- Action bar -->
        <div class="mb-4 flex justify-between no-print">
            <a href="view.php" class="text-indigo-600 hover:underline font-medium">&larr; Back to Accounts</a>
            <button onclick="window.print()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">Print Certificate</button>
        </div>

        <!-- Certificate Paper -->
        <div class="bg-white p-1 cert-border shadow-xl">
            <div class="border border-indigo-100 p-10 relative overflow-hidden bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4MCIgaGVpZ2h0PSI4MCIgdmlld0JveD0iMCAwIDgwIDgwIj4NCjxnIGZpbGw9IiNmOGZhZmMiIGZpbGwtb3BhY2l0eT0iMC40Ij4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgZD0iTTAgMGg0MHY0MEgwVjB6bTQwIDQwaDQwdjQwSDQwdjQweiIvPg0KPC9nPg0KPC9zdmc+')]">
                
                <!-- Watermark -->
                <div class="absolute inset-0 flex items-center justify-center opacity-[0.03] pointer-events-none">
                    <span class="text-9xl font-bold uppercase tracking-tighter text-indigo-900 transform -rotate-45"><?= $acc['account_type'] ?></span>
                </div>

                <!-- Header -->
                <div class="text-center mb-10 relative z-10">
                    <?php $logo = getSetting($conn, 'bank_logo'); if($logo && file_exists('../'.$logo)): ?>
                        <div class="inline-flex items-center justify-center h-20 mb-4">
                            <img src="../<?= $logo ?>" class="h-full object-contain">
                        </div>
                    <?php else: ?>
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-indigo-50 border border-indigo-100 mb-4">
                            <svg class="w-10 h-10 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        </div>
                    <?php endif; ?>
                    <h1 class="text-4xl font-serif font-bold text-gray-900 tracking-tight uppercase">Certificate of Deposit</h1>
                    <p class="text-gray-500 mt-2 font-medium tracking-wide uppercase"><?= htmlspecialchars(getSetting($conn, 'bank_name')) ?></p>
                    <p class="text-xs text-gray-400 mt-1 whitespace-pre-wrap"><?= htmlspecialchars(getSetting($conn, 'bank_address')) ?></p>
                </div>

                <div class="flex justify-between items-start mb-10 relative z-10 border-b-2 border-dashed border-gray-200 pb-8 mt-12">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-1">Certificate No.</p>
                        <p class="text-xl font-bold font-mono text-indigo-700"><?= $acc['account_no'] ?></p>
                        <p class="text-xs text-gray-400 mt-1">Date of Issue: <?= date('d M Y', strtotime($acc['opening_date'])) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-1">Scheme Type</p>
                        <p class="text-xl font-bold text-gray-800"><?= htmlspecialchars($acc['scheme_name']) ?></p>
                    </div>
                </div>

                <div class="text-lg text-gray-700 leading-relaxed text-left relative z-10 space-y-4 px-4 font-serif">
                    <p>This is to certify that we have received a sum of <strong class="text-xl font-bold font-mono text-gray-900 border-b border-gray-400 mx-1 px-2"><?= formatCurrency($acc['principal_amount'] ?: $acc['installment_amount']) ?></strong> 
                    from Mr./Ms. <strong class="text-xl text-gray-900 border-b border-gray-400 mx-1 px-2 uppercase tracking-wide"><?= htmlspecialchars($acc['first_name'].' '.$acc['last_name']) ?></strong> 
                    (Member No: <?= $acc['member_no'] ?>, Aadhar: <?= $acc['aadhar_no'] ?>) residing at <?= htmlspecialchars($acc['address']) ?>.</p>

                    <p>This deposit is placed under the <strong><?= $acc['account_type'] ?></strong> scheme for a tenure of <strong><?= $acc['tenure_months'] ?> Months</strong> 
                    bearing an interest rate of <strong><?= $acc['interest_rate'] ?>%</strong> p.a.</p>

                    <div class="bg-gray-50/80 border border-gray-200 rounded-lg p-6 my-8 grid <?= $acc['account_type'] == 'MIS' ? 'grid-cols-3' : 'grid-cols-2' ?> gap-y-4">
                        <div>
                            <span class="block text-xs uppercase tracking-wider text-gray-500 font-sans font-bold mb-1">Date of Maturity</span>
                            <span class="text-xl font-bold text-gray-800 font-mono"><?= date('d F Y', strtotime($acc['maturity_date'])) ?></span>
                        </div>
                        <?php if($acc['account_type'] == 'MIS'): ?>
                        <div class="text-center border-l border-r border-gray-200 px-4">
                            <span class="block text-xs uppercase tracking-wider text-emerald-600 font-sans font-bold mb-1">Monthly Return</span>
                            <span class="text-xl font-bold text-emerald-700 tracking-tight font-mono"><?= formatCurrency($monthly_return) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="text-right">
                            <span class="block text-xs uppercase tracking-wider text-gray-500 font-sans font-bold mb-1">Maturity Value</span>
                            <span class="text-2xl font-bold text-indigo-700 tracking-tight font-mono"><?= formatCurrency($maturity_value) ?></span>
                        </div>
                    </div>
                </div>

                <div class="text-[10px] text-gray-400 mt-2 px-4 leading-tight font-sans">
                    * Calculation is provisional. For MIS, the return is credited monthly to the linked savings account. FDs compound according to the term's frequency. RDs depend on timely installment schedules. Pre-closure rules apply.
                </div>

                <!-- Signatures -->
                <div class="mt-24 pt-8 border-t border-gray-200 flex justify-between items-end relative z-10 px-8">
                    <div class="text-center">
                        <div class="w-48 h-12 border-b border-gray-400 mb-2"></div>
                        <p class="text-sm font-semibold text-gray-600 uppercase">Customer Signature</p>
                    </div>
                    
                    <div class="text-center relative">
                        <?php $stamp = getSetting($conn, 'bank_stamp'); if($stamp && file_exists('../'.$stamp)): ?>
                            <img src="../<?= $stamp ?>" class="w-28 h-28 object-contain mx-auto -translate-y-6 opacity-60 mix-blend-multiply">
                        <?php else: ?>
                            <div class="w-32 h-32 border-2 border-indigo-200 rounded flex items-center justify-center mx-auto -translate-y-6 opacity-80 rotate-12 bg-white">
                                <p class="text-indigo-600 font-bold text-xs uppercase text-center font-serif">Official<br>Bank<br>Seal</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="text-center">
                        <div class="w-48 h-12 border-b border-gray-400 mb-2 mt-auto"></div>
                        <p class="text-sm font-semibold text-gray-600 uppercase">Authorized Signatory</p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
