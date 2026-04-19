<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_member'])) {
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

    $photo_path = null;
    $signature_path = null;

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

    // Check Duplicate Aadhar
    $check = mysqli_query($conn, "SELECT id FROM members WHERE aadhar_no = '$aadhar_no'");
    if(mysqli_num_rows($check) > 0) {
        $error = "A member with Aadhar No ($aadhar_no) already exists.";
    } else {
        $member_no = generateSequenceNo($conn, 'MBR', 'members', 'member_no');
        $branch_id = (int)$_SESSION['branch_id'];
        
        $sql = "INSERT INTO members (member_no, branch_id, first_name, last_name, dob, gender, phone, email, address, aadhar_no, pan_no, nominee_name, nominee_relation, photo_path, signature_path) 
                VALUES ('$member_no', $branch_id, '$first_name', '$last_name', '$dob', '$gender', '$phone', '$email', '$address', '$aadhar_no', '$pan_no', '$nominee_name', '$nominee_relation', " . ($photo_path ? "'$photo_path'" : "NULL") . ", " . ($signature_path ? "'$signature_path'" : "NULL") . ")";
                
        if(mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Member Registration Successful! Member No: $member_no";
            header("Location: list.php");
            exit();
        } else {
            $error = "Error adding member: " . mysqli_error($conn);
        }
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">New Member Registration</h1>
            <p class="text-gray-500 text-sm mt-1">Add a new customer completing KYC requirements</p>
        </div>
        <a href="list.php" class="bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            <i class="ph ph-list-dashes mr-1"></i> Member List
        </a>
    </div>

    <?php if($error): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-lg mb-6 flex items-start gap-3 border border-red-100">
            <i class="ph ph-warning-circle text-xl"></i>
            <div>
                <h3 class="font-medium text-red-800">Registration Failed</h3>
                <p class="text-sm mt-0.5"><?= htmlspecialchars($error) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        
        <!-- Personal Info -->
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="ph ph-user-circle text-indigo-500 text-xl"></i> Personal Information
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth <span class="text-red-500">*</span></label>
                    <input type="date" name="dob" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gender <span class="text-red-500">*</span></label>
                    <select name="gender" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all bg-white">
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Number <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">+91</span>
                        <input type="text" name="phone" pattern="[0-9]{10}" title="10 digit mobile number" required class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-gray-400 font-normal">(Optional)</span></label>
                    <input type="email" name="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                </div>
            </div>
            
            <div class="mt-5">
                <label class="block text-sm font-medium text-gray-700 mb-1">Complete Address <span class="text-red-500">*</span></label>
                <textarea name="address" required rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"></textarea>
            </div>

            <!-- Documents Upload -->
            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5 p-4 bg-indigo-50/50 rounded-xl border border-indigo-100/50">
                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-1 flex items-center gap-1.5"><i class="ph ph-image-square text-indigo-500"></i> Member Photo <span class="text-gray-400 font-normal">(JPG/PNG)</span></label>
                    <input type="file" name="photo" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition-all">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-1 flex items-center gap-1.5"><i class="ph ph-signature text-indigo-500"></i> Digital Signature <span class="text-gray-400 font-normal">(JPG/PNG)</span></label>
                    <input type="file" name="signature" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition-all">
                </div>
            </div>
        </div>

        <!-- KYC & Nominee -->
        <div class="p-6 bg-gray-50/50">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="ph ph-identification-card text-indigo-500 text-xl"></i> KYC & Nominee Details
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Aadhar Number <span class="text-red-500">*</span></label>
                    <input type="text" name="aadhar_no" pattern="[0-9]{12}" title="12 digit Aadhar number" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">PAN Number <span class="text-gray-400 font-normal">(Optional)</span></label>
                    <input type="text" name="pan_no" pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}" title="Valid PAN format (e.g. ABCDE1234F)" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all uppercase font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nominee Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="nominee_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nominee Relation <span class="text-red-500">*</span></label>
                    <input type="text" name="nominee_relation" required placeholder="e.g. Spouse, Son, Daughter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                </div>
            </div>
        </div>

        <div class="p-6 border-t border-gray-100 flex items-center justify-end gap-3 bg-white">
            <button type="reset" class="px-5 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors">
                Clear Form
            </button>
            <button type="submit" name="add_member" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium shadow-sm transition-colors flex items-center gap-2">
                <i class="ph ph-check-circle text-lg"></i> Register Member
            </button>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
