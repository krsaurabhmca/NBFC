<?php
require_once '../includes/db.php';
checkAuth();

if ($_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
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

if (!$loan) {
    $_SESSION['error'] = "Loan application not found or already processed.";
    header("Location: list.php?status=pending_approval");
    exit();
}

// Fetch Draft Schedule
$schedules = mysqli_query($conn, "SELECT * FROM loan_schedules WHERE account_id = $id ORDER BY installment_no ASC");

require_once '../includes/header.php';
// Hide Breadcrumbs for cleaner review
echo '<style>.breadcrumb, .content-header { display: none !important; }</style>';
require_once '../includes/sidebar.php';
?>

<div class="max-w-[1200px] mx-auto space-y-8 pb-20">
    <!-- Header: Ultra Compact -->
    <div class="flex items-center justify-between bg-white px-6 py-4 rounded-xl border border-slate-100 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-indigo-600/10 text-indigo-600 rounded-lg flex items-center justify-center border border-indigo-100">
                <i class="ph ph-shield-check text-xl"></i>
            </div>
            <div>
                <h1 class="text-lg font-black text-slate-800 tracking-tight">Final Review: <?= $loan['account_no'] ?></h1>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="approve.php?id=<?= $id ?>&action=reject" onclick="return confirm('Reject application?')" class="px-4 py-2 text-rose-500 font-bold text-[10px] uppercase hover:bg-rose-50 rounded-lg transition-all">Cancel</a>
            <button type="submit" form="sanctionForm" class="px-6 py-2.5 bg-slate-900 hover:bg-indigo-600 text-white rounded-lg text-[10px] font-black uppercase tracking-widest transition-all">Approve & Issue Loan</button>
        </div>
    </div>

    <form method="POST" action="approve.php" id="sanctionForm" class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="action" value="approve">

        <!-- Left Column -->
        <div class="lg:col-span-4 space-y-8">
            <!-- High Density Member & Advisor Profile -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-4 bg-slate-50/50 border-b border-slate-100 flex items-center gap-3">
                    <div class="flex gap-2">
                        <!-- Photo with Zoom -->
                        <div onclick="viewDoc('<?= APP_URL ?><?= $loan['photo_path'] ?: 'assets/img/default-user.png' ?>', 'Member Photo')" class="w-12 h-12 bg-white rounded-lg border border-slate-200 overflow-hidden cursor-zoom-in hover:border-indigo-500 transition-all shadow-sm">
                            <?php if ($loan['photo_path']): ?>
                                <img src="<?= APP_URL ?><?= $loan['photo_path'] ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-slate-300"><i class="ph ph-user"></i></div>
                            <?php endif; ?>
                        </div>
                        <!-- Signature with Zoom -->
                        <div onclick="viewDoc('<?= APP_URL ?><?= $loan['signature_path'] ?: 'assets/img/no-sig.png' ?>', 'Digital Signature')" class="w-12 h-12 bg-white rounded-lg border border-slate-200 overflow-hidden cursor-zoom-in hover:border-indigo-500 transition-all shadow-sm">
                            <?php if ($loan['signature_path']): ?>
                                <img src="<?= APP_URL ?><?= $loan['signature_path'] ?>" class="w-full h-full object-contain p-1">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-slate-300"><i class="ph ph-signature"></i></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-sm font-black text-slate-800"><?= $loan['first_name'] ?> <?= $loan['last_name'] ?></div>
                        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">ID: <?= $loan['member_no'] ?></div>
                    </div>
                </div>
                
                <div class="p-4 space-y-3">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Mobile</span>
                            <span class="text-[11px] font-bold text-slate-700"><?= $loan['phone'] ?></span>
                        </div>
                        <div>
                            <span class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Issue Date</span>
                            <input type="date" name="disbursal_date" value="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 border-0 p-0 text-[11px] font-bold text-indigo-600 outline-none">
                        </div>
                    </div>
                    
                    <div class="pt-3 border-t border-slate-50">
                        <label class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-2">Advisor: <span class="text-slate-700"><?= $loan['staff_name'] ?: 'Direct' ?></span></label>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <span class="block text-[8px] text-slate-400 mb-1">Sanction %</span>
                                <input type="number" step="0.01" name="disbursal_comm_pct" value="<?= $loan['disbursal_commission_percent'] ?>" class="w-full bg-slate-50 border border-slate-100 rounded px-2 py-1 text-xs font-bold">
                            </div>
                            <div>
                                <span class="block text-[8px] text-slate-400 mb-1">Recovery %</span>
                                <input type="number" step="0.01" name="collection_comm_pct" value="<?= $loan['collection_commission_percent'] ?>" class="w-full bg-slate-50 border border-slate-100 rounded px-2 py-1 text-xs font-bold">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KYC Documents Preview -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 space-y-4">
                <h3 class="text-[9px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-50 pb-2 flex items-center gap-2">
                    <i class="ph ph-file-search text-lg text-indigo-500"></i> Verification Documents
                </h3>
                
                <div class="grid grid-cols-1 gap-3">
                    <?php 
                    $docs = [
                        ['label' => 'Aadhar Copy', 'path' => $loan['aadhar_copy'], 'icon' => 'ph-identification-card'],
                        ['label' => 'PAN Card', 'path' => $loan['pan_copy'], 'icon' => 'ph-cardholder'],
                        ['label' => 'Cancelled Cheque', 'path' => $loan['cheque_copy'], 'icon' => 'ph-bank']
                    ];
                    
                    foreach($docs as $doc):
                    ?>
                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-100 group">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-white flex items-center justify-center text-slate-400 group-hover:text-indigo-600 transition-colors">
                                    <i class="ph <?= $doc['icon'] ?> text-lg"></i>
                                </div>
                                <span class="text-[10px] font-black text-slate-600 uppercase tracking-tight"><?= $doc['label'] ?></span>
                            </div>
                            <?php if($doc['path']): ?>
                                <button type="button" onclick="viewDoc('<?= APP_URL ?><?= $doc['path'] ?>', '<?= $doc['label'] ?>')" class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-[8px] font-black uppercase tracking-widest hover:bg-slate-900 transition-all">View File</button>
                            <?php else: ?>
                                <span class="text-[8px] font-bold text-slate-300 italic">Not Uploaded</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="lg:col-span-8 space-y-8">
            <!-- High-Density Loan Configuration -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
                <div class="grid grid-cols-3 gap-6">
                    <div class="space-y-1">
                        <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Loan Amount (₹)</label>
                        <input type="number" step="0.01" name="principal" id="principal" value="<?= $loan['principal_amount'] ?>" class="w-full bg-slate-50 border border-slate-100 rounded-lg px-3 py-2 text-sm font-black text-slate-800 outline-none focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Interest (%)</label>
                        <input type="number" step="0.01" name="interest_rate" id="interest_rate" value="<?= $loan['interest_rate'] ?>" class="w-full bg-slate-50 border border-slate-100 rounded-lg px-3 py-2 text-sm font-black text-slate-800 outline-none focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Months</label>
                        <input type="number" name="tenure" id="tenure" value="<?= $loan['tenure_months'] ?>" class="w-full bg-slate-50 border border-slate-100 rounded-lg px-3 py-2 text-sm font-black text-slate-800 outline-none focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-6 mt-4">
                    <div class="space-y-1">
                        <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Method</label>
                        <select name="loan_interest_type" id="interest_type" class="w-full bg-slate-50 border border-slate-100 rounded-lg px-3 py-2 text-xs font-bold text-slate-800 outline-none">
                            <option value="Flat" <?= $loan['loan_interest_type'] == 'Flat' ? 'selected' : '' ?>>Flat</option>
                            <option value="Reducing" <?= $loan['loan_interest_type'] == 'Reducing' ? 'selected' : '' ?>>Reducing</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Cycle</label>
                        <select name="repayment_frequency" id="freqSelect" class="w-full bg-slate-50 border border-slate-100 rounded-lg px-3 py-2 text-xs font-bold text-slate-800 outline-none">
                            <option value="Monthly" <?= $loan['repayment_frequency'] == 'Monthly' ? 'selected' : '' ?>>Monthly</option>
                            <option value="Bi-Weekly" <?= $loan['repayment_frequency'] == 'Bi-Weekly' ? 'selected' : '' ?>>Bi-Weekly</option>
                            <option value="Weekly" <?= $loan['repayment_frequency'] == 'Weekly' ? 'selected' : '' ?>>Weekly</option>
                        </select>
                    </div>
                    <div class="space-y-1" id="day1_container">
                        <label id="day1_label" class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Due Date</label>
                        <select name="repayment_day_1" id="day1_select" class="w-full bg-slate-50 border border-slate-100 rounded-lg px-3 py-2 text-xs font-bold text-slate-800 outline-none">
                            <?php if ($loan['repayment_frequency'] == 'Weekly'): ?>
                                <?php foreach (['1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday', '7' => 'Sunday'] as $v => $n): ?>
                                    <option value="<?= $v ?>" <?= $loan['repayment_day_1'] == $v ? 'selected' : '' ?>><?= $n ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php for ($i = 1; $i <= 28; $i++): ?>
                                    <option value="<?= $i ?>" <?= $loan['repayment_day_1'] == $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mt-4 <?= $loan['repayment_frequency'] == 'Bi-Weekly' ? '' : 'hidden' ?>" id="day2_container">
                    <label class="block text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Second Due Date</label>
                    <select name="repayment_day_2" class="w-full bg-slate-50 border border-slate-100 rounded-lg px-3 py-2 text-xs font-bold text-slate-800 outline-none">
                        <?php for ($i = 1; $i <= 28; $i++): ?>
                            <option value="<?= $i ?>" <?= $loan['repayment_day_2'] == $i ? 'selected' : '' ?>>Date: <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Ultra Slim Summary Bar -->
                <div class="mt-4 bg-slate-900 rounded-lg p-3 flex items-center justify-around text-white shadow-md relative overflow-hidden">
                    <div class="text-center">
                        <div class="text-[7px] uppercase tracking-widest text-indigo-300">EMI</div>
                        <div class="text-lg font-black tracking-tight" id="projEMI">₹ 0</div>
                    </div>
                    <div class="text-center border-l border-white/5 pl-4">
                        <div class="text-[7px] uppercase tracking-widest text-white/40">Interest</div>
                        <div class="text-xs font-bold" id="projInt">₹ 0</div>
                    </div>
                    <div class="text-center border-l border-white/5 pl-4">
                        <div class="text-[7px] uppercase tracking-widest text-white/40">Payable</div>
                        <div class="text-xs font-black text-emerald-400" id="projTotal">₹ 0</div>
                    </div>
                </div>
            </div>

                <!-- Action Strip -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-3">
                    <div class="flex items-center gap-3 bg-amber-50/50 p-2 rounded-lg border border-amber-100 mb-3">
                        <i class="ph ph-shield-check text-amber-500 text-sm"></i>
                        <p class="text-[9px] text-amber-800 font-bold uppercase tracking-tighter">Sanction Audit Enabled</p>
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-slate-900 text-white rounded-lg text-[10px] font-black uppercase tracking-widest transition-all">
                        Final Approve & Issue
                    </button>
                </div>

                <!-- Schedule View -->
                <div class="bg-white rounded-lg shadow-sm border border-slate-100 overflow-hidden">
                    <div class="px-8 py-5 border-b border-slate-50">
                        <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                            <i class="ph ph-list-numbers text-indigo-500"></i> Payment Plan
                        </h3>
                    </div>
                    <div class="overflow-x-auto max-h-[300px]">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr
                                    class="text-slate-400 font-bold uppercase border-b border-slate-50 bg-slate-50/50 sticky top-0">
                                    <th class="px-8 py-3 w-16">No.</th>
                                    <th class="px-8 py-3">Due Date</th>
                                    <th class="px-8 py-3 text-right">EMI Amount</th>
                                    <th class="px-8 py-3 text-right">Principal + Interest</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php mysqli_data_seek($schedules, 0); ?>
                                <?php while ($s = mysqli_fetch_assoc($schedules)): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-8 py-4 font-mono font-bold text-slate-400"><?= $s['installment_no'] ?></td>
                                        <td class="px-8 py-4">
                                            <div class="font-bold text-slate-800"><?= date('d M, Y', strtotime($s['due_date'])) ?></div>
                                            <div class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter"><?= date('l', strtotime($s['due_date'])) ?></div>
                                        </td>
                                        <td class="px-8 py-4 text-right font-black text-indigo-600 text-sm">
                                            <?= formatCurrency($s['emi_amount']) ?>
                                        </td>
                                        <td class="px-8 py-4 text-right text-[10px] text-slate-500 font-medium">
                                            <span class="text-slate-400 italic">P:</span> <?= formatCurrency($s['principal_component']) ?> 
                                            <span class="mx-1 text-slate-200">|</span> 
                                            <span class="text-slate-400 italic">I:</span> <?= formatCurrency($s['interest_component']) ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    </form>
</div>

<!-- Document Viewer Modal -->
<div id="docModal" class="fixed inset-0 z-[200] hidden flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-md">
    <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-4xl h-[85vh] flex flex-col overflow-hidden transform transition-all border border-white/20">
        <div class="px-8 py-6 border-b border-slate-50 flex items-center justify-between bg-white sticky top-0">
            <div>
                <h3 id="docTitle" class="text-lg font-black text-slate-800 tracking-tight">Verification Document</h3>
                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">Sanction Audit Panel</p>
            </div>
            <button onclick="closeDocModal()" class="w-10 h-10 bg-slate-50 text-slate-400 hover:text-rose-500 rounded-xl flex items-center justify-center transition-all group">
                <i class="ph ph-x text-xl group-hover:rotate-90 transition-transform"></i>
            </button>
        </div>
        <div class="flex-1 bg-slate-50 p-6 overflow-auto flex items-center justify-center" id="docContent">
            <!-- Dynamic Content -->
        </div>
    </div>
</div>

<script>
function viewDoc(path, title) {
    const modal = document.getElementById('docModal');
    const content = document.getElementById('docContent');
    const titleEl = document.getElementById('docTitle');
    
    titleEl.innerText = title;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    const ext = path.split('.').pop().toLowerCase();
    
    if(ext === 'pdf') {
        content.innerHTML = `<iframe src="${path}" class="w-full h-full rounded-xl border-0 shadow-sm bg-white"></iframe>`;
    } else {
        content.innerHTML = `<img src="${path}" class="max-w-full max-h-full rounded-xl shadow-2xl border-4 border-white animate-fade-in">`;
    }
}

function closeDocModal() {
    document.getElementById('docModal').classList.add('hidden');
    document.getElementById('docContent').innerHTML = '';
    document.body.style.overflow = '';
}
</script>

<style>
@keyframes fade-in {
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
}
.animate-fade-in { animation: fade-in 0.3s ease-out forwards; }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const princEl = document.getElementById('principal');
        const rateEl = document.getElementById('interest_rate');
        const tenureEl = document.getElementById('tenure');
        const typeEl = document.getElementById('interest_type');

        function updateProjection() {
            const p = parseFloat(princEl.value) || 0;
            const r = parseFloat(rateEl.value) || 0;
            const t = parseInt(tenureEl.value) || 0;
            const type = document.getElementById('interest_type').value;
            const freq = document.getElementById('freqSelect').value;

            if (p > 0 && r > 0 && t > 0) {
                let total_installments = t;
                if (freq === 'Weekly') total_installments = t * 4;
                else if (freq === 'Bi-Weekly') total_installments = t * 2;

                let emi = 0;
                let total_int = 0;
                if (type === 'Reducing') {
                    const r_monthly = (r / 100) / 12;
                    const total_payable = ((p * r_monthly * Math.pow(1 + r_monthly, t)) / (Math.pow(1 + r_monthly, t) - 1)) * t;
                    emi = total_payable / total_installments;
                    total_int = total_payable - p;
                } else {
                    total_int = p * (r / 100) * (t / 12);
                    emi = (p + total_int) / total_installments;
                }

                const format = (val) => '₹ ' + val.toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                document.getElementById('projEMI').innerText = format(emi);
                document.getElementById('projInt').innerText = format(total_int);
                document.getElementById('projTotal').innerText = format(p + total_int);
            }
        }

        [princEl, rateEl, tenureEl, document.getElementById('interest_type'), document.getElementById('freqSelect')].forEach(el => {
            if (el) el.addEventListener('input', updateProjection);
        });

        document.getElementById('freqSelect').addEventListener('change', function () {
            const val = this.value;
            const d1_cont = document.getElementById('day1_container');
            const d2_cont = document.getElementById('day2_container');
            const d1_label = document.getElementById('day1_label');
            const d1_select = document.getElementById('day1_select');

            if (val === 'Weekly') {
                d1_label.innerText = 'Collection Day (Weekly)';
                d1_select.innerHTML = '<option value="1">Monday</option><option value="2">Tuesday</option><option value="3">Wednesday</option><option value="4">Thursday</option><option value="5">Friday</option><option value="6">Saturday</option><option value="7">Sunday</option>';
                d2_cont.classList.add('hidden');
            } else if (val === 'Bi-Weekly') {
                d1_label.innerText = 'First Due Date';
                let opts = ''; for (let i = 1; i <= 28; i++) opts += `<option value="${i}" ${i == 1 ? 'selected' : ''}>Date: ${i}</option>`;
                d1_select.innerHTML = opts;
                d2_cont.classList.remove('hidden');
            } else {
                d1_label.innerText = 'EMI Due Date';
                let opts = ''; for (let i = 1; i <= 28; i++) opts += `<option value="${i}" ${i == 5 ? 'selected' : ''}>Date: ${i}</option>`;
                d1_select.innerHTML = opts;
                d2_cont.classList.add('hidden');
            }
            updateProjection();
        });

        updateProjection();
    });
</script>

<?php require_once '../includes/footer.php'; ?>