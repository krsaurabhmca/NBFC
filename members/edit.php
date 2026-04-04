<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$m_res = mysqli_query($conn, "SELECT * FROM members WHERE id = $id");
if(!$member = mysqli_fetch_assoc($m_res)) {
    die("Member not found.");
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_member'])) {
    $first_name = sanitize($conn, $_POST['first_name']);
    $last_name = sanitize($conn, $_POST['last_name']);
    $dob = sanitize($conn, $_POST['dob']);
    $gender = sanitize($conn, $_POST['gender']);
    $phone = sanitize($conn, $_POST['phone']);
    $email = sanitize($conn, $_POST['email']);
    $address = sanitize($conn, $_POST['address']);
    $aadhar_no = sanitize($conn, $_POST['aadhar_no']);
    $pan_no = sanitize($conn, $_POST['pan_no']);
    $nominee_name = sanitize($conn, $_POST['nominee_name']);
    $nominee_relation = sanitize($conn, $_POST['nominee_relation']);
    $status = sanitize($conn, $_POST['status']);

    $photo_path = $member['photo_path'];
    $signature_path = $member['signature_path'];

    if(isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if(in_array($ext, $allowed)) {
            $photo_path = 'uploads/photo_' . time() . '_' . rand(100,999) . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], '../' . $photo_path);
        }
    }

    if(isset($_FILES['signature']) && $_FILES['signature']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION));
        if(in_array($ext, $allowed)) {
            $signature_path = 'uploads/sig_' . time() . '_' . rand(100,999) . '.' . $ext;
            move_uploaded_file($_FILES['signature']['tmp_name'], '../' . $signature_path);
        }
    }

    $sql = "UPDATE members SET 
            first_name = '$first_name', 
            last_name = '$last_name', 
            dob = '$dob', 
            gender = '$gender', 
            phone = '$phone', 
            email = '$email', 
            address = '$address', 
            aadhar_no = '$aadhar_no', 
            pan_no = '$pan_no', 
            nominee_name = '$nominee_name', 
            nominee_relation = '$nominee_relation', 
            status = '$status',
            photo_path = '$photo_path',
            signature_path = '$signature_path'
            WHERE id = $id";
            
    if(mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Member details updated successfully!";
        header("Location: list.php");
        exit();
    } else {
        $error = "Error updating member: " . mysqli_error($conn);
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Edit Member Details</h1>
            <p class="text-gray-500 text-sm mt-1">Updating information for <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?> (<?= $member['member_no'] ?>)</p>
        </div>
        <a href="list.php" class="bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            <i class="ph ph-arrow-left mr-1"></i> Back to List
        </a>
    </div>

    <?php if($error): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-lg mb-6 border border-red-100 flex items-center gap-3">
            <i class="ph ph-warning-circle text-xl"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="ph ph-user-circle text-indigo-500 text-xl"></i> Personal Information
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($member['first_name']) ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($member['last_name']) ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth <span class="text-red-500">*</span></label>
                    <input type="date" name="dob" value="<?= $member['dob'] ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gender <span class="text-red-500">*</span></label>
                    <select name="gender" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                        <option value="Male" <?= $member['gender']=='Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $member['gender']=='Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= $member['gender']=='Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Number <span class="text-red-500">*</span></label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($member['phone']) ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($member['email']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1 text-rose-600 font-bold">Member Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-rose-300 rounded-lg focus:ring-2 focus:ring-rose-500 outline-none bg-rose-50 font-bold">
                        <option value="active" <?= $member['status']=='active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $member['status']=='inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="blocked" <?= $member['status']=='blocked' ? 'selected' : '' ?>>Blocked</option>
                    </select>
                </div>
            </div>
            
            <div class="mt-5">
                <label class="block text-sm font-medium text-gray-700 mb-1">Complete Address <span class="text-red-500">*</span></label>
                <textarea name="address" required rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"><?= htmlspecialchars($member['address']) ?></textarea>
            </div>
        </div>

        <div class="p-6 bg-gray-50/50">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="ph ph-identification-card text-indigo-500 text-xl"></i> KYC & nominee Details
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Aadhar Number <span class="text-red-500">*</span></label>
                    <input type="text" name="aadhar_no" value="<?= htmlspecialchars($member['aadhar_no']) ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">PAN Number</label>
                    <input type="text" name="pan_no" value="<?= htmlspecialchars($member['pan_no']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none uppercase font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nominee Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="nominee_name" value="<?= htmlspecialchars($member['nominee_name']) ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nominee Relation <span class="text-red-500">*</span></label>
                    <input type="text" name="nominee_relation" value="<?= htmlspecialchars($member['nominee_relation']) ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>
        </div>

        <div class="p-6 border-t border-gray-100 bg-white">
             <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="ph ph-image-square text-indigo-500 text-xl"></i> Documents Update
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Member Photo</label>
                    <div class="flex items-center gap-4">
                        <?php if($member['photo_path']): ?>
                            <img src="../<?= $member['photo_path'] ?>" class="w-20 h-20 rounded-lg object-cover border border-gray-200">
                        <?php endif; ?>
                        <input type="file" name="photo" accept="image/*" class="text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Digital Signature</label>
                    <div class="flex items-center gap-4">
                        <?php if($member['signature_path']): ?>
                            <img src="../<?= $member['signature_path'] ?>" class="h-10 w-auto border border-gray-200 p-1">
                        <?php endif; ?>
                        <input type="file" name="signature" accept="image/*" class="text-sm">
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6 border-t border-gray-100 flex items-center justify-end gap-3 bg-gray-50">
            <button type="submit" name="edit_member" class="px-8 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium shadow-sm transition-colors flex items-center gap-2">
                <i class="ph ph-check-circle text-lg"></i> Update Member Profile
            </button>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
