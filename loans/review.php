<?php
require_once '../includes/db.php';
checkAuth();

if($_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// Fetch Account + Member + Scheme + Branch
$sql = "SELECT a.*, m.*, m.id as member_id, s.scheme_name, b.branch_name, u.name as staff_name 
        FROM accounts a 
        JOIN members m ON a.member_id = m.id 
        JOIN schemes s ON a.scheme_id = s.id
        LEFT JOIN branches b ON a.branch_id = b.id
        LEFT JOIN users u ON a.referred_by = u.id
        WHERE a.id = $id AND a.status = 'pending_approval' LIMIT 1";
$res = mysqli_query($conn, $sql);
$loan = mysqli_fetch_assoc($res);

if(!$loan) {
    $_SESSION['error'] = "Loan application not found or already processed.";
    header("Location: list.php?status=pending_approval");
    exit();
}

// Fetch Draft Schedule
$schedules = mysqli_query($conn, "SELECT * FROM loan_schedules WHERE account_id = $id ORDER BY installment_no ASC");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center font-black text-xl shadow-inner">
                <i class="ph ph-shield-warning"></i>
            </div>
            <div>
                <h1 class="text-xl font-black text-slate-800 tracking-tight">Sanction Review: <?= $loan['account_no'] ?></h1>
                <p class="text-slate-500 text-xs mt-0.5">Application Date: <?= date('d M, Y', strtotime($loan['opening_date'])) ?> &bull; Branch: <?= $loan['branch_name'] ?: 'N/A' ?></p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="approve.php?id=<?= $id ?>&action=reject" onclick="return confirm('Reject this application?')" class="px-6 py-2.5 bg-rose-50 text-rose-600 rounded-xl text-xs font-black uppercase hover:bg-rose-600 hover:text-white transition-all border border-rose-100">Reject Application</a>
            <a href="approve.php?id=<?= $id ?>&action=approve" class="px-8 py-2.5 bg-emerald-600 text-white rounded-xl text-xs font-black uppercase shadow-lg shadow-emerald-200 hover:bg-emerald-700 transition-all flex items-center gap-2">
                <i class="ph ph-shield-check text-lg"></i> Final Sanction & Disburse
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- left: Member Profile -->
        <div class="lg:col-span-4 space-y-6">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="bg-slate-50 p-6 flex flex-col items-center">
                    <div class="w-32 h-32 bg-indigo-100 rounded-2xl flex items-center justify-center text-indigo-400 mb-4 border-2 border-white shadow-lg overflow-hidden">
                        <?php if($loan['photo_path']): ?>
                            <img src="<?= APP_URL ?><?= $loan['photo_path'] ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="ph ph-user text-6xl"></i>
                        <?php endif; ?>
                    </div>
                    <h2 class="text-lg font-black text-slate-800"><?= $loan['first_name'] ?> <?= $loan['last_name'] ?></h2>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-widest mt-1"><?= $loan['member_no'] ?></p>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-[10px] font-black text-slate-400 uppercase">Mobile No</span>
                        <span class="text-xs font-bold text-slate-700"><?= $loan['phone'] ?></span>
                    </div>
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-[10px] font-black text-slate-400 uppercase">Aadhaar No</span>
                        <span class="text-xs font-bold text-slate-700"><?= $loan['aadhar_no'] ?: 'N/A' ?></span>
                    </div>
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-[10px] font-black text-slate-400 uppercase">Nominee</span>
                        <span class="text-xs font-bold text-slate-700"><?= $loan['nominee_name'] ?></span>
                    </div>
                    <div class="pt-2">
                        <span class="text-[10px] font-black text-slate-400 uppercase block mb-1">Permanent Address</span>
                        <p class="text-[11px] leading-relaxed text-slate-600"><?= $loan['address'] ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-indigo-600 rounded-3xl p-6 text-white shadow-xl shadow-indigo-100">
                <div class="flex items-center gap-2 mb-4 opacity-75">
                    <i class="ph ph-briefcase"></i>
                    <span class="text-[10px] font-black uppercase tracking-widest">Sourcing Details</span>
                </div>
                <div class="space-y-4">
                    <div>
                        <span class="text-[10px] font-bold text-white/50 block uppercase">Submitted By</span>
                        <p class="font-bold"><?= $loan['staff_name'] ?: 'System Admin' ?></p>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-white/50 block uppercase">Branch Office</span>
                        <p class="font-bold"><?= $loan['branch_name'] ?: 'Main HO' ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Loan Particulars -->
        <div class="lg:col-span-8 space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-3xl border border-slate-100 shadow-sm">
                    <span class="text-[10px] font-black text-slate-400 uppercase block mb-1">Sanction Principal</span>
                    <p class="text-xl font-black text-slate-800 tracking-tight"><?= formatCurrency($loan['principal_amount']) ?></p>
                </div>
                <div class="bg-white p-5 rounded-3xl border border-slate-100 shadow-sm">
                    <span class="text-[10px] font-black text-slate-400 uppercase block mb-1">Interest Rate</span>
                    <p class="text-xl font-black text-indigo-600 tracking-tight"><?= $loan['interest_rate'] ?>% <span class="text-[10px] font-bold uppercase opacity-60">p.a</span></p>
                </div>
                <div class="bg-white p-5 rounded-3xl border border-slate-100 shadow-sm">
                    <span class="text-[10px] font-black text-slate-400 uppercase block mb-1">Tenure (Months)</span>
                    <p class="text-xl font-black text-slate-800 tracking-tight"><?= $loan['tenure_months'] ?></p>
                </div>
                <div class="bg-white p-5 rounded-3xl border border-slate-100 shadow-sm">
                    <span class="text-[10px] font-black text-slate-400 uppercase block mb-1">Interest Type</span>
                    <p class="text-xl font-black text-slate-800 tracking-tight"><?= $loan['loan_interest_type'] ?></p>
                </div>
            </div>

            <!-- Schedule -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest">Proposed EMI Schedule</h3>
                    <span class="bg-indigo-600 text-white text-[10px] font-black px-2 py-0.5 rounded-full">Monthly EMI: <?= formatCurrency($loan['installment_amount']) ?></span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[11px] border-collapse">
                        <thead>
                            <tr class="text-slate-400 font-bold uppercase border-b border-slate-50">
                                <th class="px-6 py-3">Inst. #</th>
                                <th class="px-6 py-3">Due Date</th>
                                <th class="px-6 py-3">Principal</th>
                                <th class="px-6 py-3">Interest</th>
                                <th class="px-6 py-3">Total EMI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php while($s = mysqli_fetch_assoc($schedules)): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-3 font-mono font-bold text-slate-400"><?= str_pad($s['installment_no'], 2, '0', STR_PAD_LEFT) ?></td>
                                <td class="px-6 py-3 font-bold text-slate-700"><?= date('d M, Y', strtotime($s['due_date'])) ?></td>
                                <td class="px-6 py-3 text-slate-600"><?= formatCurrency($s['principal_component']) ?></td>
                                <td class="px-6 py-3 text-slate-600"><?= formatCurrency($s['interest_component']) ?></td>
                                <td class="px-6 py-3 font-black text-indigo-600"><?= formatCurrency($s['emi_amount']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
