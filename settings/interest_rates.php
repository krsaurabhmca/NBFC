<?php
require_once '../includes/db.php';
checkAuth();

// Only admin can access settings
if($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access Denied. Administrator rights required.";
    header("Location: ../index.php");
    exit();
}

require_once '../includes/functions.php';

// Handle Scheme Update
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_scheme'])) {
    $scheme_id = (int)$_POST['scheme_id'];
    $interest_rate = (float)$_POST['interest_rate'];
    $penalty_percent = (float)$_POST['penalty_percent'];
    $pre_closure_penalty = (float)$_POST['pre_closure_penalty_percent'];
    $minimum_amount = (float)$_POST['minimum_amount'];
    
    $sql = "UPDATE schemes SET 
            interest_rate = $interest_rate, 
            penalty_percent = $penalty_percent, 
            pre_closure_penalty_percent = $pre_closure_penalty, 
            minimum_amount = $minimum_amount 
            WHERE id = $scheme_id";
            
    if(mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Scheme configuration updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update scheme.";
    }
    header("Location: interest_rates.php");
    exit();
}

$schemes = mysqli_query($conn, "SELECT * FROM schemes ORDER BY scheme_type");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="ph ph-percent text-indigo-500 text-3xl"></i> Master Configuration
        </h1>
        <p class="text-gray-500 text-sm mt-1">Manage global interest rates, default penalties, and scheme limits.</p>
    </div>

    <?= displayAlert() ?>

    <div class="bg-amber-50 rounded-xl p-4 border border-amber-200 mb-6 flex gap-3 text-amber-800 text-sm">
        <i class="ph ph-warning-circle text-xl mt-0.5"></i>
        <div>
            <strong>Important:</strong> Changing these rates will only affect new accounts or interest calculated from today onwards (based on RBI daily calculation mandates). Existing closed accounts remain unaffected.
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php while($s = mysqli_fetch_assoc($schemes)): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden relative group">
                <!-- Color coding based on type -->
                <?php
                    $color = 'indigo';
                    if($s['scheme_type'] == 'Loan') $color = 'rose';
                    if($s['scheme_type'] == 'Savings') $color = 'emerald';
                ?>
                <div class="h-2 bg-<?= $color ?>-500 w-full absolute top-0 left-0"></div>
                
                <form method="POST" action="" class="p-6 pt-8">
                    <input type="hidden" name="scheme_id" value="<?= $s['id'] ?>">
                    
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-gray-800 text-lg"><?= htmlspecialchars($s['scheme_name']) ?></h3>
                        <span class="text-xs font-bold px-2 py-1 bg-gray-100 text-gray-600 rounded">
                            <?= htmlspecialchars($s['scheme_type']) ?>
                        </span>
                    </div>

                    <div class="space-y-4 text-sm mt-5 border-t border-gray-100 pt-5">
                        <div class="flex justify-between items-center group/field">
                            <label class="font-medium text-gray-600">Base Interest Rate (%)</label>
                            <input type="number" step="0.01" name="interest_rate" value="<?= $s['interest_rate'] ?>" class="w-20 px-2 py-1 text-right border border-gray-300 rounded focus:border-indigo-500 outline-none text-gray-800 font-bold bg-gray-50 group-hover/field:bg-white transition-colors" required>
                        </div>
                        
                        <div class="flex justify-between items-center group/field">
                            <label class="font-medium text-gray-600">Min. Deposit/Loan (₹)</label>
                            <input type="number" step="1" name="minimum_amount" value="<?= $s['minimum_amount'] ?>" class="w-28 px-2 py-1 text-right border border-gray-300 rounded focus:border-indigo-500 outline-none text-gray-800 font-bold bg-gray-50 group-hover/field:bg-white transition-colors" required>
                        </div>

                        <div class="flex justify-between items-center group/field">
                            <label class="font-medium text-gray-600" title="Penalty for delayed payments">Default Penalty (%)</label>
                            <input type="number" step="0.01" name="penalty_percent" value="<?= $s['penalty_percent'] ?>" class="w-20 px-2 py-1 text-right border border-gray-300 rounded focus:border-rose-500 outline-none text-gray-800 font-bold bg-gray-50 group-hover/field:bg-white transition-colors">
                        </div>

                        <div class="flex justify-between items-center group/field">
                            <label class="font-medium text-gray-600" title="Reduction in interest upon pre-closure">Pre-Closure Diff (%)</label>
                            <input type="number" step="0.01" name="pre_closure_penalty_percent" value="<?= $s['pre_closure_penalty_percent'] ?>" class="w-20 px-2 py-1 text-right border border-gray-300 rounded focus:border-blue-500 outline-none text-gray-800 font-bold bg-gray-50 group-hover/field:bg-white transition-colors">
                        </div>
                        
                        <div class="flex justify-between items-center pt-2">
                            <label class="font-medium text-gray-500 text-xs">Compounding</label>
                            <span class="text-xs font-semibold text-gray-400 uppercase"><?= $s['compounding_frequency'] ?></span>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-gray-100 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button type="submit" name="update_scheme" class="w-full bg-<?= $color ?>-50 hover:bg-<?= $color ?>-100 text-<?= $color ?>-700 font-semibold py-2 rounded-lg transition-colors border border-<?= $color ?>-200">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
