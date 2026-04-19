<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

if($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Unauthorized. Only admins can cancel payments.";
    header("Location: ../index.php");
    exit();
}

// Ensure necessary columns exist in transactions table
// We do this lazily to ensure the system doesn't break
$cols_res = mysqli_query($conn, "SHOW COLUMNS FROM transactions LIKE 'status'");
if(mysqli_num_rows($cols_res) == 0) {
    mysqli_query($conn, "ALTER TABLE transactions ADD COLUMN status ENUM('Success', 'Cancelled') DEFAULT 'Success' AFTER amount");
    mysqli_query($conn, "ALTER TABLE transactions ADD COLUMN cancel_remarks TEXT NULL AFTER description");
    mysqli_query($conn, "ALTER TABLE transactions ADD COLUMN cancelled_at DATETIME NULL AFTER cancel_remarks");
    mysqli_query($conn, "ALTER TABLE transactions ADD COLUMN cancelled_by INT NULL AFTER cancelled_at");
}

$txn_id_str = isset($_REQUEST['txn_id']) ? sanitize($conn, $_REQUEST['txn_id']) : '';
$cancel_reason = isset($_REQUEST['cancel_reason']) ? sanitize($conn, $_REQUEST['cancel_reason']) : 'No reason provided';

if($txn_id_str) {
    mysqli_query($conn, "START TRANSACTION");
    
    // 1. Fetch Transaction details
    $txn_res = mysqli_query($conn, "SELECT * FROM transactions WHERE transaction_id = '$txn_id_str' AND transaction_type = 'EMI' FOR UPDATE");
    $txn = mysqli_fetch_assoc($txn_res);
    
    if(!$txn) {
        $_SESSION['error'] = "Transaction not found or non-cancellable type.";
        mysqli_query($conn, "ROLLBACK");
        header("Location: ../index.php");
        exit();
    }

    if(isset($txn['status']) && $txn['status'] == 'Cancelled') {
        $_SESSION['error'] = "This transaction is already cancelled.";
        mysqli_query($conn, "ROLLBACK");
        header("Location: ../accounts/view_details.php?id=" . $txn['account_id']);
        exit();
    }
    
    $account_id = $txn['account_id'];
    $collected_amt = (float)$txn['amount'];
    
    // 2. Fetch all schedules linked to this transaction
    $sch_res = mysqli_query($conn, "SELECT id, fine_amount FROM loan_schedules WHERE transaction_id = '$txn_id_str'");
    $fine_total = 0;
    $sch_ids = [];
    while($s = mysqli_fetch_assoc($sch_res)) {
        $fine_total += (float)$s['fine_amount'];
        $sch_ids[] = $s['id'];
    }
    
    if(empty($sch_ids)) {
        $_SESSION['error'] = "No linked loan schedules found for this payment.";
        mysqli_query($conn, "ROLLBACK");
        header("Location: ../accounts/view_details.php?id=$account_id");
        exit();
    }
    
    // 3. Revert Schedules
    mysqli_query($conn, "UPDATE loan_schedules SET 
                         status = 'Pending', 
                         paid_date = NULL, 
                         payment_mode = NULL, 
                         transaction_id = NULL,
                         discount_amount = 0,
                         commission_amount = 0
                         WHERE transaction_id = '$txn_id_str'");
                         
    // 4. Revert Account Balance
    $capital_adjustment = $collected_amt - $fine_total;
    mysqli_query($conn, "UPDATE accounts SET current_balance = current_balance - $capital_adjustment WHERE id = $account_id");
    
    // 5. Revert Commissions
    mysqli_query($conn, "DELETE FROM commissions WHERE reference_id IN (".implode(',',$sch_ids).") AND type = 'Collection'");
    
    // 6. Update the transaction as Cancelled (SOFT DELETE / VOID)
    $curr_usr = $_SESSION['user_id'];
    $sql_update = "UPDATE transactions SET 
                   status = 'Cancelled', 
                   cancel_remarks = '$cancel_reason', 
                   cancelled_at = NOW(), 
                   cancelled_by = $curr_usr 
                   WHERE transaction_id = '$txn_id_str'";
    
    if(!mysqli_query($conn, $sql_update)) {
         // If for some reason update fails (maybe column adding failed silently), fallback to delete to avoid double balance issue
         // but we prefer SOFT DELETE.
         $_SESSION['error'] = "Failed to update transaction status: " . mysqli_error($conn);
         mysqli_query($conn, "ROLLBACK");
         header("Location: ../accounts/view_details.php?id=$account_id");
         exit();
    }
    
    mysqli_query($conn, "COMMIT");
    $_SESSION['success'] = "Payment $txn_id_str Cancelled & Reverted Successfully.";
    header("Location: ../accounts/view_details.php?id=$account_id");
} else {
    header("Location: ../index.php");
}
exit();
