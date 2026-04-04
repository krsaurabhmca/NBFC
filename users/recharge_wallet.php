<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkAuth();

if($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access Denied. Admins only.";
    header("Location: ../index.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_check = mysqli_query($conn, "SELECT * FROM users WHERE id = $id AND role = 'advisor'");
$user = mysqli_fetch_assoc($user_check);

if(!$user) {
    $_SESSION['error'] = "Advisor not found.";
    header("Location: index.php");
    exit();
}

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['recharge_wallet'])) {
    $amount = (float)$_POST['amount'];
    $description = sanitize($conn, $_POST['description']);
    $admin_id = $_SESSION['user_id'];

    if($amount <= 0) {
        $error = "Amount must be greater than zero.";
    } else {
        mysqli_query($conn, "START TRANSACTION");
        
        // Update user balance
        $update_user = mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance + $amount WHERE id = $id");
        
        // Fetch new balance for ledger
        $bal_res = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $id");
        $new_bal = mysqli_fetch_assoc($bal_res)['wallet_balance'];
        
        // Insert transaction
        $sql = "INSERT INTO wallet_transactions (user_id, transaction_type, amount, balance_after, description, created_by) 
                VALUES ($id, 'Recharge', $amount, $new_bal, '$description', $admin_id)";
        $insert_txn = mysqli_query($conn, $sql);

        if($update_user && $insert_txn) {
            mysqli_query($conn, "COMMIT");
            $_SESSION['success'] = "Wallet recharged successfully. New Balance: " . formatCurrency($new_bal);
            header("Location: index.php");
            exit();
        } else {
            mysqli_query($conn, "ROLLBACK");
            $error = "Database Error: " . mysqli_error($conn);
        }
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-wallet text-indigo-500"></i> Recharge Advisor Wallet
            </h1>
            <p class="text-gray-500 text-sm mt-1">Add prepaid balance to <?= htmlspecialchars($user['name']) ?>'s wallet.</p>
        </div>
        <a href="index.php" class="text-gray-600 hover:text-gray-900 border border-gray-300 hover:bg-gray-50 px-4 py-2 rounded-lg transition text-sm font-medium">
            &larr; Back to List
        </a>
    </div>

    <?php if($error): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-lg mb-6 flex items-start gap-3 border border-red-100"><i class="ph ph-warning-circle text-xl mt-0.5"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
        <div class="flex items-center gap-4 mb-8 p-4 bg-indigo-50 rounded-xl border border-indigo-100">
            <div class="w-12 h-12 bg-indigo-500 text-white rounded-full flex items-center justify-center text-xl font-bold">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <div>
                <h2 class="font-bold text-gray-800"><?= htmlspecialchars($user['name']) ?></h2>
                <p class="text-sm text-indigo-600 font-medium">Current Wallet Balance: <?= formatCurrency($user['wallet_balance']) ?></p>
            </div>
        </div>

        <form method="POST" action="" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Recharge Amount (₹) <span class="text-red-500">*</span></label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-medium">₹</span>
                    <input type="number" step="0.01" min="1" name="amount" required class="w-full pl-10 pr-4 py-3 text-lg border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none font-semibold">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Remarks / Note <span class="text-red-500">*</span></label>
                <textarea name="description" required rows="2" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="e.g. Cash received in office"></textarea>
            </div>

            <div class="pt-4 flex justify-end">
                <button type="submit" name="recharge_wallet" class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3 rounded-xl font-medium shadow-md transition-all flex items-center gap-2 w-full justify-center">
                    <i class="ph ph-plus-circle text-xl"></i> Authorize Recharge
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
