<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

if($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access Denied. Admins only.";
    header("Location: ../index.php");
    exit();
}

$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_general'])) {
    $updates = [
        'bank_name' => sanitize($conn, $_POST['bank_name']),
        'bank_address' => sanitize($conn, $_POST['bank_address']),
        'prefix_member' => sanitize($conn, $_POST['prefix_member']),
        'prefix_savings' => sanitize($conn, $_POST['prefix_savings']),
        'prefix_loan' => sanitize($conn, $_POST['prefix_loan']),
        'prefix_fd' => sanitize($conn, $_POST['prefix_fd']),
        'prefix_rd' => sanitize($conn, $_POST['prefix_rd']),
        'prefix_mis' => sanitize($conn, $_POST['prefix_mis']),
        'prefix_dd' => sanitize($conn, $_POST['prefix_dd']),
    ];
    
    // Handle File Uploads
    $upload_dir = '../uploads/';
    if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    if(isset($_FILES['bank_logo']) && $_FILES['bank_logo']['error'] == 0) {
        $ext = pathinfo($_FILES['bank_logo']['name'], PATHINFO_EXTENSION);
        $file_name = 'logo_' . time() . '.' . $ext;
        if(move_uploaded_file($_FILES['bank_logo']['tmp_name'], $upload_dir . $file_name)) {
            $updates['bank_logo'] = 'uploads/' . $file_name;
        }
    }
    
    if(isset($_FILES['bank_stamp']) && $_FILES['bank_stamp']['error'] == 0) {
        $ext = pathinfo($_FILES['bank_stamp']['name'], PATHINFO_EXTENSION);
        $file_name = 'stamp_' . time() . '.' . $ext;
        if(move_uploaded_file($_FILES['bank_stamp']['tmp_name'], $upload_dir . $file_name)) {
            $updates['bank_stamp'] = 'uploads/' . $file_name;
        }
    }

    $success = true;
    foreach($updates as $key => $val) {
        $sql = "UPDATE settings SET setting_value = '$val' WHERE setting_key = '$key'";
        if(!mysqli_query($conn, $sql)) $success = false;
    }

    if($success) {
        $_SESSION['success'] = "General Settings updated successfully.";
        header("Location: general.php");
        exit();
    } else {
        $error = "Failed to update some settings: " . mysqli_error($conn);
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-buildings text-indigo-500 text-3xl"></i> General Bank Settings
            </h1>
            <p class="text-gray-500 text-sm mt-1">Configure your NBFC identity and core printing details.</p>
        </div>
        <a href="templates.php" class="text-indigo-600 hover:bg-indigo-50 px-4 py-2 rounded-lg font-medium text-sm transition transition-all border border-indigo-200">
            Edit Email/SMS Templates &rarr;
        </a>
    </div>

    <?= displayAlert() ?>
    <?php if($error): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-lg mb-6 flex items-start gap-3 border border-red-100"><i class="ph ph-warning-circle text-xl mt-0.5"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Settings Nav -->
        <div class="md:col-span-1 space-y-2">
            <a href="general.php" class="block px-4 py-3 bg-indigo-50 text-indigo-700 font-semibold rounded-xl border border-indigo-100 flex items-center gap-3">
                <i class="ph ph-buildings text-xl"></i> Bank Profile
            </a>
            <a href="interest_rates.php" class="block px-4 py-3 text-gray-600 hover:bg-gray-50 font-medium rounded-xl transition-colors flex items-center gap-3 border border-transparent">
                <i class="ph ph-percent text-xl"></i> Interest Rates
            </a>
            <a href="templates.php" class="block px-4 py-3 text-gray-600 hover:bg-gray-50 font-medium rounded-xl transition-colors flex items-center gap-3 border border-transparent">
                <i class="ph ph-envelope-simple text-xl"></i> Notifications
            </a>
        </div>

        <div class="md:col-span-3">
            <form method="POST" action="" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-8 space-y-8">
                    
                    <!-- Basic Info -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Official Bank Identity</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Company / Bank Name <span class="text-red-500">*</span></label>
                                <input type="text" name="bank_name" value="<?= htmlspecialchars(getSetting($conn, 'bank_name')) ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Registered Address <span class="text-red-500">*</span></label>
                                <textarea name="bank_address" rows="3" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"><?= htmlspecialchars(getSetting($conn, 'bank_address')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Media Uploads -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Branding & Print Media</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Company Logo (Optional)</label>
                                <?php $logo = getSetting($conn, 'bank_logo'); if($logo && file_exists('../'.$logo)): ?>
                                    <img src="../<?= $logo ?>" class="h-12 object-contain mb-3 bg-gray-50 p-1 border rounded" alt="Logo">
                                <?php endif; ?>
                                <input type="file" name="bank_logo" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Authorized Seal/Stamp (For Printouts)</label>
                                <?php $stamp = getSetting($conn, 'bank_stamp'); if($stamp && file_exists('../'.$stamp)): ?>
                                    <img src="../<?= $stamp ?>" class="h-20 object-contain mb-3 bg-gray-50 p-2 border rounded border-dashed" alt="Stamp">
                                <?php endif; ?>
                                <input type="file" name="bank_stamp" accept="image/png, image/jpeg" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                                <p class="text-xs text-gray-400 mt-2">Recommended: Transparent PNG, 300x300px, Blue/Purple ink tone.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Sequence Prefixes -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Account Number Prefixes</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Members</label>
                                <input type="text" name="prefix_member" value="<?= htmlspecialchars(getSetting($conn, 'prefix_member')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 uppercase">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Savings</label>
                                <input type="text" name="prefix_savings" value="<?= htmlspecialchars(getSetting($conn, 'prefix_savings')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 uppercase">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Loans</label>
                                <input type="text" name="prefix_loan" value="<?= htmlspecialchars(getSetting($conn, 'prefix_loan')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 uppercase">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Fixed Deposit</label>
                                <input type="text" name="prefix_fd" value="<?= htmlspecialchars(getSetting($conn, 'prefix_fd')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 uppercase">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Rec. Deposit</label>
                                <input type="text" name="prefix_rd" value="<?= htmlspecialchars(getSetting($conn, 'prefix_rd')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 uppercase">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">MIS</label>
                                <input type="text" name="prefix_mis" value="<?= htmlspecialchars(getSetting($conn, 'prefix_mis')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 uppercase">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Daily Deposit</label>
                                <input type="text" name="prefix_dd" value="<?= htmlspecialchars(getSetting($conn, 'prefix_dd')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 uppercase">
                            </div>
                        </div>
                        <p class="text-xs text-amber-600 mt-2 bg-amber-50 p-2 rounded border border-amber-100 inline-block">Note: Changing prefixes only applies to new accounts created from now on.</p>
                    </div>

                </div>

                <div class="px-8 py-4 bg-gray-50 border-t border-gray-100 flex justify-end">
                    <button type="submit" name="save_general" class="px-6 py-2 bg-indigo-600 text-white rounded-lg font-medium shadow hover:bg-indigo-700 transition flex items-center gap-2">
                        <i class="ph ph-floppy-disk"></i> Save Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
