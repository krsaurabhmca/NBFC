<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

if($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Unauthorized access. Only admins can approve loans.";
    header("Location: list.php");
    exit();
}

$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

if($id > 0 && $action == 'approve') {
    mysqli_query($conn, "START TRANSACTION");
    
    $res = mysqli_query($conn, "SELECT * FROM accounts WHERE id = $id AND status = 'pending_approval' FOR UPDATE");
    $acc = mysqli_fetch_assoc($res);
    
    if($acc) {
        if($action == 'approve') {
            // ADMIN OVERRIDES (If coming from POST form)
            $principal = isset($_POST['principal']) ? (float)$_POST['principal'] : $acc['principal_amount'];
            $interest_rate = isset($_POST['interest_rate']) ? (float)$_POST['interest_rate'] : $acc['interest_rate'];
            $tenure = isset($_POST['tenure']) ? (int)$_POST['tenure'] : $acc['tenure_months'];
            $interest_type = isset($_POST['loan_interest_type']) ? sanitize($conn, $_POST['loan_interest_type']) : $acc['loan_interest_type'];
            $disbursal_date = isset($_POST['disbursal_date']) ? sanitize($conn, $_POST['disbursal_date']) : date('Y-m-d');
            $disbursal_comm_pct = isset($_POST['disbursal_comm_pct']) ? (float)$_POST['disbursal_comm_pct'] : $acc['disbursal_commission_percent'];
            $collection_comm_pct = isset($_POST['collection_comm_pct']) ? (float)$_POST['collection_comm_pct'] : $acc['collection_commission_percent'];
            
            $frequency = isset($_POST['repayment_frequency']) ? sanitize($conn, $_POST['repayment_frequency']) : $acc['repayment_frequency'];
            $day1 = isset($_POST['repayment_day_1']) ? (int)$_POST['repayment_day_1'] : $acc['repayment_day_1'];
            $day2 = isset($_POST['repayment_day_2']) ? (int)$_POST['repayment_day_2'] : $acc['repayment_day_2'];

            // 1. Re-generate schedules if values changed or just to be safe
            $totals = generateLoanSchedules($conn, $id, $principal, $interest_rate, $tenure, $disbursal_date, $frequency, $day1, $day2, $interest_type);
            
            // 2. Update Account with overrides and status
            $sql_upd = "UPDATE accounts SET 
                        status = 'active', 
                        opening_date = '$disbursal_date',
                        disbursal_commission_percent = $disbursal_comm_pct,
                        collection_commission_percent = $collection_comm_pct
                        WHERE id = $id";
            mysqli_query($conn, $sql_upd);

            // 3. Create Disbursal Transaction
            $txn_id = 'DSB-' . time() . rand(10,99);
            $total_payable = $totals['total_payable'];
            mysqli_query($conn, "INSERT INTO transactions (transaction_id, account_id, transaction_type, amount, balance_after, description, transaction_date, created_by) 
                                 VALUES ('$txn_id', $id, 'Loan', $principal, -$total_payable, 'Loan Disbursed & Activated', '$disbursal_date', {$_SESSION['user_id']})");
            
            // 4. Log Disbursal Commission
            $referred_by = $acc['referred_by'];
            if($referred_by && $disbursal_comm_pct > 0) {
                $comm_amt = round($principal * ($disbursal_comm_pct / 100), 2);
                mysqli_query($conn, "INSERT INTO commissions (user_id, account_id, type, amount, status, reference_id) 
                                     VALUES ($referred_by, $id, 'Disbursal', $comm_amt, 'Pending', '$txn_id')");
            }

            mysqli_query($conn, "COMMIT");
            $_SESSION['success'] = "Loan Account {$acc['account_no']} Sanctioned, Adjusted & Activated Successfully!";
        }
    } else {
        $_SESSION['error'] = "Loan account not found or already processed.";
    }
} elseif($id > 0 && $action == 'reject') {
    mysqli_query($conn, "START TRANSACTION");
    $res = mysqli_query($conn, "SELECT * FROM accounts WHERE id = $id AND status = 'pending_approval' FOR UPDATE");
    if(mysqli_num_rows($res) > 0) {
        mysqli_query($conn, "DELETE FROM loan_schedules WHERE account_id = $id");
        mysqli_query($conn, "DELETE FROM accounts WHERE id = $id");
        mysqli_query($conn, "COMMIT");
        $_SESSION['success'] = "Loan Application Rejected & Removed.";
    } else {
        $_SESSION['error'] = "Loan application not found or already processed.";
    }
}

header("Location: list.php");
exit();
