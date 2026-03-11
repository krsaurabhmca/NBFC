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
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_templates'])) {
    $updates = [
        'sms_api_key' => sanitize($conn, $_POST['sms_api_key']),
        'sms_template_open' => sanitize($conn, $_POST['sms_template_open']),
        'sms_template_txn' => sanitize($conn, $_POST['sms_template_txn']),
        'email_smtp_host' => sanitize($conn, $_POST['email_smtp_host']),
        'email_smtp_user' => sanitize($conn, $_POST['email_smtp_user']),
        'email_smtp_pass' => sanitize($conn, $_POST['email_smtp_pass']),
        'email_from' => sanitize($conn, $_POST['email_from']),
    ];
    
    $success = true;
    foreach($updates as $key => $val) {
        $sql = "UPDATE settings SET setting_value = '$val' WHERE setting_key = '$key'";
        if(!mysqli_query($conn, $sql)) $success = false;
    }

    if($success) {
        $_SESSION['success'] = "Notification Templates & Keys updated successfully.";
        header("Location: templates.php");
        exit();
    } else {
        $error = "Failed to update templates: " . mysqli_error($conn);
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="ph ph-envelope-simple text-indigo-500 text-3xl"></i> Notification Settings
        </h1>
        <p class="text-gray-500 text-sm mt-1">Configure SMTP, SMS APIs, and customer messaging templates.</p>
    </div>

    <?= displayAlert() ?>
    <?php if($error): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-lg mb-6 flex items-start gap-3 border border-red-100"><i class="ph ph-warning-circle text-xl mt-0.5"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Settings Nav -->
        <div class="md:col-span-1 space-y-2">
            <a href="general.php" class="block px-4 py-3 text-gray-600 hover:bg-gray-50 font-medium rounded-xl transition-colors flex items-center gap-3 border border-transparent">
                <i class="ph ph-buildings text-xl"></i> Bank Profile
            </a>
            <a href="interest_rates.php" class="block px-4 py-3 text-gray-600 hover:bg-gray-50 font-medium rounded-xl transition-colors flex items-center gap-3 border border-transparent">
                <i class="ph ph-percent text-xl"></i> Interest Rates
            </a>
            <a href="templates.php" class="block px-4 py-3 bg-indigo-50 text-indigo-700 font-semibold rounded-xl border border-indigo-100 flex items-center gap-3">
                <i class="ph ph-envelope-simple text-xl"></i> Notifications
            </a>
        </div>

        <div class="md:col-span-3">
            <form method="POST" action="" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-8 space-y-8">
                    
                    <!-- SMS Configuration -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">SMS Configuration & Templates</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Gateway API Key (Textlocal/Twilio/Msg91)</label>
                                <input type="password" name="sms_api_key" value="<?= htmlspecialchars(getSetting($conn, 'sms_api_key')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            </div>
                            
                            <div class="grid col-span-1 md:grid-cols-2 gap-4 pt-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Account Welcome SMS</label>
                                    <textarea name="sms_template_open" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm font-mono"><?= htmlspecialchars(getSetting($conn, 'sms_template_open')) ?></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Alert SMS</label>
                                    <textarea name="sms_template_txn" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm font-mono"><?= htmlspecialchars(getSetting($conn, 'sms_template_txn')) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Configuration -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Email SMTP Configuration</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Host</label>
                                <input type="text" name="email_smtp_host" value="<?= htmlspecialchars(getSetting($conn, 'email_smtp_host')) ?>" placeholder="smtp.gmail.com or mail.office365.com" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Username</label>
                                    <input type="text" name="email_smtp_user" value="<?= htmlspecialchars(getSetting($conn, 'email_smtp_user')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Password</label>
                                    <input type="password" name="email_smtp_pass" value="<?= htmlspecialchars(getSetting($conn, 'email_smtp_pass')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sender Email ("From" Address)</label>
                                <input type="email" name="email_from" value="<?= htmlspecialchars(getSetting($conn, 'email_from')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            </div>
                        </div>
                    </div>

                </div>

                <div class="px-8 py-4 bg-gray-50 border-t border-gray-100 flex justify-end">
                    <button type="submit" name="save_templates" class="px-6 py-2 bg-indigo-600 text-white rounded-lg font-medium shadow hover:bg-indigo-700 transition flex items-center gap-2">
                        <i class="ph ph-floppy-disk"></i> Save Configurations
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
