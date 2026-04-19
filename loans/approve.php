<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

if($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Unauthorized access. Only admins can approve loans.";
    header("Location: list.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if($id > 0 && $action == 'approve') {
    mysqli_query($conn, "START TRANSACTION");
    
    $res = mysqli_query($conn, "SELECT * FROM accounts WHERE id = $id AND status = 'pending_approval' FOR UPDATE");
    $acc = mysqli_fetch_assoc($res);
    
    if($acc) {
        if($action == 'approve') {
            $update = mysqli_query($conn, "UPDATE accounts SET status = 'active' WHERE id = $id");
            if($update) {
                // FINALIZE DISBURSAL (Money Moves Now)
                $principal = $acc['principal_amount'];
                $total_bal = $acc['current_balance'];
                $referred_by = $acc['referred_by'];
                $disbursal_comm_pct = $acc['disbursal_commission_percent'];
                
                // 1. Create Disbursal Transaction
                $txn_id = 'DSB-' . time() . rand(10,99);
                mysqli_query($conn, "INSERT INTO transactions (transaction_id, account_id, transaction_type, amount, balance_after, description, transaction_date, created_by) 
                                     VALUES ('$txn_id', $id, 'Loan', $principal, $total_bal, 'Loan Disbursed & Activated', NOW(), {$_SESSION['user_id']})");
                
                // 2. Log Disbursal Commission
                if($referred_by && $disbursal_comm_pct > 0) {
                    $comm_amt = round($principal * ($disbursal_comm_pct / 100), 2);
                    mysqli_query($conn, "INSERT INTO commissions (user_id, account_id, type, amount, status, reference_id) 
                                         VALUES ($referred_by, $id, 'Disbursal', $comm_amt, 'Pending', '$txn_id')");
                }

                mysqli_query($conn, "COMMIT");
                $_SESSION['success'] = "Loan Account {$acc['account_no']} Approved & Disbursed!";
            }
        } elseif($action == 'reject') {
            // Delete Pending Schedules & Account
            mysqli_query($conn, "DELETE FROM loan_schedules WHERE account_id = $id");
            mysqli_query($conn, "DELETE FROM accounts WHERE id = $id");
            mysqli_query($conn, "COMMIT");
            $_SESSION['success'] = "Loan Application Rejected & Removed.";
        }
    } else {
        $_SESSION['error'] = "Loan account not found or already processed.";
    }
}

header("Location: list.php");
exit();
