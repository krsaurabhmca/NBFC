<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

if($_SESSION['role'] !== 'admin') {
    die("Unauthorized.");
}

$error = '';
$success = '';

// Handle Add Branch
if(isset($_POST['add_branch'])) {
    $name = sanitize($conn, $_POST['branch_name']);
    $code = sanitize($conn, $_POST['branch_code']);
    $addr = sanitize($conn, $_POST['address']);
    $contact = sanitize($conn, $_POST['contact_no']);
    
    $check = mysqli_query($conn, "SELECT id FROM branches WHERE branch_code = '$code'");
    if(mysqli_num_rows($check) > 0) {
        $error = "Branch Code already exists.";
    } else {
        if(mysqli_query($conn, "INSERT INTO branches (branch_name, branch_code, address, contact_no) VALUES ('$name', '$code', '$addr', '$contact')")) {
            $success = "Branch added successfully.";
        } else {
            $error = "Error adding branch.";
        }
    }
}

// Handle Delete (Only if no users/members linked)
if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $check_users = mysqli_query($conn, "SELECT id FROM users WHERE branch_id = $id");
    if(mysqli_num_rows($check_users) > 0) {
        $error = "Cannot delete branch. Users are still linked to it.";
    } else {
        mysqli_query($conn, "DELETE FROM branches WHERE id = $id AND id != 1");
        $success = "Branch removed.";
    }
}

$branches = mysqli_query($conn, "SELECT * FROM branches ORDER BY id ASC");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ph ph-buildings text-indigo-500 text-3xl"></i> Branch Management
            </h1>
            <p class="text-gray-500 text-sm mt-1">Configure and monitor regional operation hubs.</p>
        </div>
        <button onclick="document.getElementById('addBranchModal').classList.remove('hidden')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-medium shadow-sm transition-all flex items-center gap-2">
            <i class="ph ph-plus-circle text-lg"></i> Create New Branch
        </button>
    </div>

    <?= displayAlert($error, $success) ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php while($b = mysqli_fetch_assoc($branches)): 
            $user_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE branch_id = {$b['id']}"))['c'];
            $member_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM members WHERE branch_id = {$b['id']}"))['c'];
        ?>
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:border-indigo-200 transition-all group">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl group-hover:bg-indigo-600 group-hover:text-white transition-all">
                        <i class="ph ph-tree-structure"></i>
                    </div>
                    <?php if($b['id'] != 1): ?>
                    <a href="?delete=<?= $b['id'] ?>" onclick="return confirm('Remove branch?')" class="text-gray-300 hover:text-rose-500 transition-colors">
                        <i class="ph ph-trash text-lg"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <h3 class="text-lg font-bold text-gray-800 mb-1"><?= htmlspecialchars($b['branch_name']) ?></h3>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-4">Code: <?= $b['branch_code'] ?></p>
                
                <div class="space-y-3 pt-4 border-t border-gray-50">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-500">Staff Count</span>
                        <span class="font-bold text-gray-800"><?= $user_count ?> Adults</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-500">Member Base</span>
                        <span class="font-bold text-gray-800"><?= $member_count ?> Accounts</span>
                    </div>
                    <div class="pt-2">
                        <p class="text-[10px] text-gray-400 uppercase font-bold mb-1">Location Details</p>
                        <p class="text-xs text-gray-600 line-clamp-2"><?= htmlspecialchars($b['address']) ?></p>
                        <p class="text-xs text-indigo-500 font-bold mt-1 tracking-tighter"><?= htmlspecialchars($b['contact_no'] ?: 'No Contact') ?></p>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Add Modal -->
<div id="addBranchModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl animate-in zoom-in-95 duration-200">
        <form action="" method="POST">
            <div class="p-6 border-b border-gray-50 flex justify-between items-center">
                <h3 class="font-bold text-gray-800">New Branch Registration</h3>
                <button type="button" onclick="document.getElementById('addBranchModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                    <i class="ph ph-x text-xl"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Branch Name</label>
                    <input type="text" name="branch_name" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Branch Code</label>
                        <input type="text" name="branch_code" required placeholder="e.g. BR02" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-center font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Contact No</label>
                        <input type="text" name="contact_no" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Branch Address</label>
                    <textarea name="address" rows="3" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all resize-none"></textarea>
                </div>
            </div>
            <div class="p-6 bg-gray-50 border-t border-gray-100 flex gap-3">
                <button type="button" onclick="document.getElementById('addBranchModal').classList.add('hidden')" class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-600 rounded-xl font-bold text-sm hover:bg-white transition-all">Cancel</button>
                <button type="submit" name="add_branch" class="flex-1 bg-indigo-600 text-white rounded-xl font-bold text-sm shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition-all">Create Branch</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
