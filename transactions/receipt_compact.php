<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkAuth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$duplicate = isset($_GET['duplicate']) ? true : false;

$sql = "SELECT t.*, a.account_no, a.account_type, m.first_name, m.last_name, m.member_no 
        FROM transactions t 
        JOIN accounts a ON t.account_id = a.id 
        JOIN members m ON a.member_id = m.id 
        WHERE t.id = $id";

$res = mysqli_query($conn, $sql);
$t = mysqli_fetch_assoc($res);

if(!$t) die("Transaction not found.");

// Bank Details
$bank_name = getSetting($conn, 'bank_name');
$bank_address = getSetting($conn, 'bank_address');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Compact Receipt #<?= $t['transaction_id'] ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; width: 300px; margin: 0 auto; color: #000; font-size: 11px; }
        .header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 5px; margin-bottom: 10px; }
        .duplicate { text-align: center; font-weight: bold; border: 1px solid #000; padding: 2px; margin-bottom: 5px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 3px; }
        .label { font-weight: bold; }
        .amount { font-size: 14px; font-weight: bold; border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 5px 0; margin: 10px 0; text-align: right; }
        .footer { text-align: center; margin-top: 20px; border-top: 1px dashed #000; padding-top: 5px; font-size: 9px; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <strong><?= strtoupper($bank_name) ?></strong><br>
        <small><?= $bank_address ?></small>
    </div>

    <?php if($duplicate): ?>
        <div class="duplicate">DUPLICATE COPY</div>
    <?php endif; ?>

    <div class="row"><span class="label">DATE:</span> <span><?= date('d-m-Y H:i', strtotime($t['transaction_date'])) ?></span></div>
    <div class="row"><span class="label">TXN ID:</span> <span><?= $t['transaction_id'] ?></span></div>
    <div class="row"><span class="label">MEMBER:</span> <span><?= $t['member_no'] ?></span></div>
    <div class="row"><span class="label">NAME:</span> <span><?= strtoupper($t['first_name'].' '.$t['last_name']) ?></span></div>
    <div class="row"><span class="label">A/C NO:</span> <span><?= $t['account_no'] ?></span></div>
    <div class="row"><span class="label">TYPE:</span> <span><?= $t['transaction_type'] ?></span></div>
    
    <div class="amount">
        AMOUNT: ₹ <?= number_format(abs($t['amount']), 2) ?>
    </div>

    <div class="row"><span class="label">REMAINING BAL:</span> <span>₹ <?= number_format(abs($t['balance_after']), 2) ?></span></div>
    <div class="row"><span class="label">STATUS:</span> <span>SUCCESSFUL</span></div>

    <div class="footer">
        THANK YOU FOR BANKING WITH US.<br>
        SYSTEM GENERATED RECEIPT.<br>
        --------------------------------
    </div>

    <div class="no-print" style="margin-top: 20px; text-align: center;">
        <button onclick="window.print()">Print Again</button>
        <button onclick="window.close()">Close Window</button>
    </div>

</body>
</html>
