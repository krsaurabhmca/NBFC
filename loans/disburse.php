<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$error = '';
$success = '';

// Fetch Members (Filtered by Branch)
$members = mysqli_query($conn, "SELECT id, member_no, first_name, last_name FROM members WHERE 1=1 " . getBranchWhere('', false) . " AND status = 'active' ORDER BY first_name ASC");
$pre_select_member = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;

// Fetch Employees/Advisors (Filtered by Branch)
$employees = mysqli_query($conn, "SELECT id, name, role FROM users WHERE 1=1 " . getBranchWhere('', false) . " AND status = 'active' ORDER BY name ASC");

// Fetch Loan Schemes
$schemes = mysqli_query($conn, "SELECT * FROM schemes WHERE scheme_type = 'Loan' AND status = 'active' ORDER BY scheme_name ASC");
$schemes_json = [];
while($s = mysqli_fetch_assoc($schemes)) {
    $schemes_json[$s['id']] = $s;
}
mysqli_data_seek($schemes, 0);

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['disburse_loan'])) {
    // Handle File Uploads
    $aadhar_path = '';
    $pan_path = '';
    $cheque_path = '';
    
    $upload_dir = '../uploads/documents/';
    if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $max_size = 2 * 1024 * 1024; // 2MB
    
    $files = [
        'aadhar_copy' => &$aadhar_path,
        'pan_copy' => &$pan_path,
        'cheque_copy' => &$cheque_path
    ];
    
    foreach($files as $key => &$path) {
        if(isset($_FILES[$key]) && $_FILES[$key]['error'] == 0) {
            if($_FILES[$key]['size'] <= $max_size) {
                $ext = pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION);
                $new_name = $key . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                if(move_uploaded_file($_FILES[$key]['tmp_name'], $upload_dir . $new_name)) {
                    $path = 'uploads/documents/' . $new_name;
                }
            }
        }
    }

    $member_id = (int)$_POST['member_id'];
    $scheme_id = (int)$_POST['scheme_id'];
    $principal = (float)$_POST['amount'];
    $tenure = (int)$_POST['tenure'];
    $interest_rate = (float)$_POST['interest_rate'];
    $emi_date = (int)($_POST['repayment_day_1'] ?? 1);
    $frequency = sanitize($conn, $_POST['repayment_frequency']);
    $day1 = (int)($_POST['repayment_day_1'] ?? 1);
    $day2 = (int)($_POST['repayment_day_2'] ?? 15);
    $referred_by = !empty($_POST['referred_by']) ? (int)$_POST['referred_by'] : 'NULL';
    $disbursal_comm_pct = (float)$_POST['disbursal_comm_pct'];
    $collection_comm_pct = (float)$_POST['collection_comm_pct'];
    $interest_type = sanitize($conn, $_POST['loan_interest_type']);
    $disbursal_date = !empty($_POST['disbursal_date']) ? sanitize($conn, $_POST['disbursal_date']) : date('Y-m-d');
    
    $account_no = generateSequenceNo($conn, 'LOAN', 'accounts', 'account_no');
    $maturity_date = date('Y-m-d', strtotime($disbursal_date . " +$tenure months"));
    
    mysqli_query($conn, "START TRANSACTION");
    
    // 1. Create Loan Account (Status: Pending Approval)
    $branch_id = (int)$_SESSION['branch_id'];
    $sql_acc = "INSERT INTO accounts (account_no, branch_id, member_id, scheme_id, account_type, opening_balance, current_balance, principal_amount, tenure_months, interest_rate, opening_date, maturity_date, emi_date, repayment_frequency, repayment_day_1, repayment_day_2, referred_by, disbursal_commission_percent, collection_commission_percent, loan_interest_type, status, aadhar_copy, pan_copy, cheque_copy) 
                VALUES ('$account_no', $branch_id, $member_id, $scheme_id, 'Loan', -$principal, -$principal, $principal, $tenure, $interest_rate, '$disbursal_date', '$maturity_date', $day1, '$frequency', $day1, $day2, $referred_by, $disbursal_comm_pct, $collection_comm_pct, '$interest_type', 'pending_approval', '$aadhar_path', '$pan_path', '$cheque_path')";
    
    if(mysqli_query($conn, $sql_acc)) {
        $account_id = mysqli_insert_id($conn);
        
        // 2 & 3. Generate EMI Schedule & Update Account via Helper Function
        generateLoanSchedules($conn, $account_id, $principal, $interest_rate, $tenure, $disbursal_date, $frequency, $day1, $day2, $interest_type);
        
        mysqli_query($conn, "COMMIT");
        $_SESSION['success'] = "Loan Application submitted successfully! A/c No: $account_no. This loan is now pending administrative approval.";
        header("Location: list.php");
        exit();
    } else {
        mysqli_query($conn, "ROLLBACK");
        $error = "Error: " . mysqli_error($conn);
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-6xl mx-auto space-y-8 pb-20">
    <!-- Main Header (Compact) -->
    <div class="bg-white p-6 rounded-xl border border-slate-100 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4 overflow-hidden relative">
        <div class="absolute top-0 right-0 w-32 h-32 bg-slate-50 rounded-full -mr-16 -mt-16 -z-0 opacity-40"></div>
        <div class="relative z-10 flex items-center gap-4">
            <div class="w-12 h-12 bg-indigo-600 text-white rounded-lg flex items-center justify-center shadow-lg shadow-indigo-100 rotate-2">
                <i class="ph ph-hand-coins text-2xl"></i>
            </div>
            <div>
                <h1 class="text-xl font-black text-slate-800 tracking-tight">New Loan Entry</h1>
                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mt-0.5">Fill Details & Check Payments</p>
            </div>
        </div>
        <div class="relative z-10">
             <a href="list.php" class="px-5 py-2 bg-white border border-slate-200 rounded-xl text-[10px] font-black uppercase tracking-widest text-slate-700 hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
                <i class="ph ph-squares-four text-lg text-indigo-400"></i> Loan Book
            </a>
        </div>
    </div>

    <?= displayAlert() ?>

    <form method="POST" action="" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        <div class="lg:col-span-8 space-y-8">
            
            <!-- Section 1: Customer & Scheme -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 relative overflow-hidden group">
                <div class="mb-6 flex items-center justify-between border-b border-slate-50 pb-4">
                    <h3 class="font-black text-slate-800 text-[10px] uppercase tracking-[0.15em] flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-slate-900 text-white flex items-center justify-center text-[10px]">01</span>
                        Basic Info
                    </h3>
                    <div class="text-[8px] font-black text-indigo-500 bg-indigo-50 px-2 py-0.5 rounded-full uppercase tracking-widest">Enrollment Verified</div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Select Customer</label>
                        <select name="member_id" required class="select2-init w-full px-4 py-3 border-2 border-slate-50 rounded-xl text-sm font-bold bg-slate-50 focus:bg-white focus:border-indigo-600 outline-none transition-all cursor-pointer">
                            <option value="">-- Search Customer --</option>
                            <?php mysqli_data_seek($members, 0); while($m = mysqli_fetch_assoc($members)): ?>
                                <option value="<?= $m['id'] ?>" <?= $pre_select_member == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?> (<?= $m['member_no'] ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Loan Scheme</label>
                        <select name="scheme_id" id="schemeSelect" required class="w-full px-4 py-3 border-2 border-slate-50 rounded-xl text-sm font-bold bg-slate-50 focus:bg-white focus:border-indigo-600 outline-none transition-all cursor-pointer">
                            <option value="">-- Choose Loan Type --</option>
                            <?php foreach($schemes_json as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['scheme_name']) ?> (<?= $s['interest_rate'] ?>%)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 2: Loan Financials -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100">
                <div class="mb-6 flex items-center justify-between border-b border-slate-50 pb-4">
                    <h3 class="font-black text-slate-800 text-[10px] uppercase tracking-[0.15em] flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-[10px]">02</span>
                        Loan Amount & Time
                    </h3>
                    <div class="text-[8px] font-black text-emerald-500 bg-emerald-50 px-2 py-0.5 rounded-full uppercase tracking-widest">Real-time Stats</div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="space-y-2">
                        <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Loan Amount (₹)</label>
                        <input type="number" step="0.01" name="amount" id="principal" placeholder="0.00" required class="w-full px-4 py-3 border-2 border-slate-50 rounded-xl text-lg font-black text-indigo-600 bg-slate-50 focus:bg-white focus:border-indigo-600 outline-none transition-all">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Interest Rate (%)</label>
                        <input type="number" step="0.01" name="interest_rate" id="interest_rate" required class="w-full px-4 py-3 border-2 border-slate-50 rounded-xl text-lg font-black text-slate-800 bg-slate-50 focus:bg-white focus:border-indigo-600 outline-none transition-all">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Loan Time (Months)</label>
                        <input type="number" name="tenure" id="tenure" required class="w-full px-4 py-3 border-2 border-slate-50 rounded-xl text-lg font-black text-slate-800 bg-slate-50 focus:bg-white focus:border-indigo-600 outline-none transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div class="space-y-2">
                        <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">EMI Frequency</label>
                        <select name="repayment_frequency" id="freqSelect" class="w-full px-4 py-3 border-2 border-slate-50 rounded-xl text-sm font-bold bg-slate-50 focus:bg-white focus:border-indigo-600 outline-none transition-all">
                            <option value="Monthly">Monthly</option>
                            <option value="Bi-Weekly">2 Times in Month</option>
                            <option value="Weekly">Every Week</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Interest Method</label>
                        <select name="loan_interest_type" class="w-full px-4 py-3 border-2 border-slate-50 rounded-xl text-sm font-bold bg-slate-50 focus:bg-white focus:border-indigo-600 outline-none transition-all">
                            <option value="Flat">Simple Interest (Flat)</option>
                            <option value="Reducing">Reducing Balance</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                     <div class="space-y-2" id="day1_container">
                        <label id="day1_label" class="block text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Collection Day</label>
                        <select name="repayment_day_1" id="day1_select" class="w-full px-4 py-3 border-2 border-slate-50 rounded-xl text-sm font-black bg-slate-50 focus:bg-white focus:border-indigo-600 outline-none transition-all">
                            <?php for($i=1; $i<=28; $i++): ?>
                                <option value="<?= $i ?>" <?= $i==5 ? 'selected' : '' ?>>Date: <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="space-y-2 hidden" id="day2_container">
                        <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">2nd Due Date</label>
                        <select name="repayment_day_2" class="w-full px-4 py-3 border-2 border-slate-50 rounded-xl text-sm font-black bg-slate-50 focus:bg-white focus:border-indigo-600 outline-none transition-all">
                            <?php for($i=1; $i<=28; $i++): ?>
                                <option value="<?= $i ?>" <?= $i==20 ? 'selected' : '' ?>>Date: <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Section 3: Staff -->
            <div class="bg-slate-50 p-6 rounded-xl border border-slate-200/50">
                 <div class="mb-4 flex items-center border-b border-slate-200/50 pb-4">
                    <h3 class="font-black text-slate-800 text-[10px] uppercase tracking-widest flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-slate-800 text-white flex items-center justify-center text-[10px]">03</span>
                        Assignment
                    </h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                    <div class="md:col-span-6 space-y-2">
                        <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Advisor / Staff</label>
                        <select name="referred_by" class="select2-init w-full px-4 py-3 border-2 border-transparent rounded-xl text-sm font-bold bg-white focus:bg-white focus:border-indigo-600 outline-none transition-all shadow-sm">
                            <option value="">-- No Advisor --</option>
                            <?php mysqli_data_seek($employees, 0); while($e = mysqli_fetch_assoc($employees)): ?>
                                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?> (<?= $e['role'] ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="md:col-span-3 space-y-2">
                        <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Issue Comm. %</label>
                        <input type="number" step="0.01" name="disbursal_comm_pct" value="0.00" class="w-full px-4 py-3 border-2 border-transparent rounded-xl text-sm font-black text-slate-800 bg-white shadow-sm">
                    </div>
                    <div class="md:col-span-3 space-y-2">
                        <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Collection %</label>
                        <input type="number" step="0.01" name="collection_comm_pct" value="0.00" class="w-full px-4 py-3 border-2 border-transparent rounded-xl text-sm font-black text-slate-800 bg-white shadow-sm">
                    </div>
                </div>
            </div>

            <!-- Section 4: Document Uploads -->
            <div class="bg-indigo-50/30 p-6 rounded-xl border border-indigo-100/50">
                 <div class="mb-6 flex items-center border-b border-indigo-100/30 pb-4 justify-between">
                    <h3 class="font-black text-slate-800 text-[10px] uppercase tracking-widest flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-[10px]">04</span>
                        KYC Documents
                    </h3>
                    <span class="text-[8px] font-black text-indigo-400 uppercase tracking-widest italic">Max 2MB per file (Optional)</span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="space-y-2">
                        <label class="block text-[9px] font-black text-indigo-400 uppercase tracking-widest ml-1">Aadhar Copy</label>
                        <div class="relative group/up">
                            <input type="file" name="aadhar_copy" onchange="previewFile(this, 'aadhar_preview')" accept="image/*,.pdf" class="w-full text-xs font-bold text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-indigo-600 file:text-white hover:file:bg-slate-900 transition-all cursor-pointer">
                            <div id="aadhar_preview" class="hidden mt-2 p-1 bg-white border border-slate-100 rounded-lg w-20 h-20 overflow-hidden flex items-center justify-center"></div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[9px] font-black text-indigo-400 uppercase tracking-widest ml-1">PAN Card Copy</label>
                        <div class="relative group/up">
                            <input type="file" name="pan_copy" onchange="previewFile(this, 'pan_preview')" accept="image/*,.pdf" class="w-full text-xs font-bold text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-indigo-600 file:text-white hover:file:bg-slate-900 transition-all cursor-pointer">
                            <div id="pan_preview" class="hidden mt-2 p-1 bg-white border border-slate-100 rounded-lg w-20 h-20 overflow-hidden flex items-center justify-center"></div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[9px] font-black text-indigo-400 uppercase tracking-widest ml-1">Cancelled Cheque</label>
                        <div class="relative group/up">
                            <input type="file" name="cheque_copy" onchange="previewFile(this, 'cheque_preview')" accept="image/*,.pdf" class="w-full text-xs font-bold text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-indigo-600 file:text-white hover:file:bg-slate-900 transition-all cursor-pointer">
                            <div id="cheque_preview" class="hidden mt-2 p-1 bg-white border border-slate-100 rounded-lg w-20 h-20 overflow-hidden flex items-center justify-center"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Actions -->
        <div class="lg:col-span-4 space-y-8 sticky top-24">
            <!-- Compact Projection View -->
            <div class="bg-slate-900 rounded-xl p-8 text-white shadow-xl relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-48 h-48 bg-indigo-500/10 rounded-full -mr-24 -mt-24"></div>
                
                <h3 class="font-black text-indigo-400 text-[9px] uppercase tracking-widest mb-6 flex items-center gap-2">
                    <i class="ph ph-trend-up"></i> Payment Summary
                </h3>
                
                <div class="space-y-8 text-center">
                    <div>
                        <p class="text-indigo-200 text-[9px] font-black uppercase tracking-widest mb-1 opacity-60">Estimated EMI</p>
                        <h2 class="text-4xl font-black tracking-tighter" id="projEMI">₹ 0.00</h2>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-6 py-6 border-y border-white/5">
                        <div class="text-left font-mono">
                            <span class="block text-[9px] text-white/40 uppercase mb-1">Loan Amount</span>
                            <span class="font-bold text-sm" id="projPrincRec">₹ 0.00</span>
                        </div>
                        <div class="text-right font-mono border-l border-white/5 pl-6">
                            <span class="block text-[9px] text-white/40 uppercase mb-1">Total Interest</span>
                            <span class="font-bold text-sm text-emerald-400" id="projTotalInterest">₹ 0.00</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-2">
                        <span class="text-indigo-200 text-[10px] font-bold uppercase opacity-50">Total Contract</span>
                        <span class="text-xl font-black" id="projGrandTotal">₹ 0.00</span>
                    </div>
                </div>
            </div>

            <!-- Actions Dashboard -->
            <div class="bg-white rounded-xl p-6 border border-slate-100 shadow-sm space-y-4">
                <?php if($_SESSION['role'] == 'admin'): ?>
                <div class="space-y-1.5">
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Disbursal Date</label>
                    <input type="date" name="disbursal_date" value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-xs font-black text-slate-800">
                </div>
                <?php else: ?>
                    <input type="hidden" name="disbursal_date" value="<?= date('Y-m-d') ?>">
                <?php endif; ?>

                <button type="submit" name="disburse_loan" class="w-full py-4 bg-indigo-600 hover:bg-slate-900 text-white rounded-xl font-black text-[10px] uppercase tracking-widest transition-all shadow-lg flex items-center justify-center gap-2">
                    <i class="ph ph-lightning text-lg"></i> Apply for Loan
                </button>
            </div>
                
                <p class="text-[9px] text-slate-400 text-center px-4 leading-relaxed font-medium mt-2">
                    <?= $_SESSION['role'] == 'admin' ? 'Confirmation of loan disbursal will immediately post a debit entry to the customer\'s ledger.' : 'Submitting this application will notify the head office for credit review and sanction.' ?>
                </p>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const schemes = <?= json_encode($schemes_json) ?>;
    const princEl = document.getElementById('principal');
    const rateEl = document.getElementById('interest_rate');
    const tenureEl = document.getElementById('tenure');
    const schemeSelect = document.getElementById('schemeSelect');
    
    function updateProjection() {
        const p = parseFloat(princEl.value) || 0;
        const r = parseFloat(rateEl.value) || 0;
        const t = parseInt(tenureEl.value) || 0;
        const type = document.querySelector('select[name="loan_interest_type"]').value;
        const freq = document.getElementById('freqSelect').value;
        
        if(p > 0 && r > 0 && t > 0) {
            let total_installments = t;
            if(freq === 'Weekly') total_installments = t * 4;
            else if(freq === 'Bi-Weekly') total_installments = t * 2;

            let emi = 0;
            let total_int = 0;
            if(type === 'Reducing') {
                const r_monthly = (r / 100) / 12;
                const total_payable = ((p * r_monthly * Math.pow(1 + r_monthly, t)) / (Math.pow(1 + r_monthly, t) - 1)) * t;
                emi = total_payable / total_installments;
                total_int = total_payable - p;
            } else {
                total_int = p * (r / 100) * (t / 12);
                emi = (p + total_int) / total_installments;
            }
            
            document.getElementById('projEMI').innerText = '₹ ' + emi.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('projPrincRec').innerText = '₹ ' + p.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('projTotalInterest').innerText = '₹ ' + total_int.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('projGrandTotal').innerText = '₹ ' + (p + total_int).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
    }

    schemeSelect.addEventListener('change', function() {
        const id = this.value;
        if(id && schemes[id]) {
            rateEl.value = schemes[id].interest_rate;
            updateProjection();
        }
    });

    [princEl, rateEl, tenureEl].forEach(el => el.addEventListener('input', updateProjection));
    document.querySelector('select[name="loan_interest_type"]').addEventListener('change', updateProjection);
    document.getElementById('freqSelect').addEventListener('change', function() {
        const val = this.value;
        const d1_cont = document.getElementById('day1_container');
        const d2_cont = document.getElementById('day2_container');
        const d1_label = document.getElementById('day1_label');
        const d1_select = document.getElementById('day1_select');

        if(val === 'Weekly') {
            d1_label.innerText = 'Collection Day (Weekly)';
            d1_select.innerHTML = '<option value="1">Monday</option><option value="2">Tuesday</option><option value="3">Wednesday</option><option value="4">Thursday</option><option value="5">Friday</option><option value="6">Saturday</option><option value="7">Sunday</option>';
            d2_cont.classList.add('hidden');
        } else if(val === 'Bi-Weekly') {
            d1_label.innerText = 'First Due Date';
            let opts = ''; for(let i=1; i<=28; i++) opts += `<option value="${i}" ${i==1?'selected':''}>Date: ${i}</option>`;
            d1_select.innerHTML = opts;
            d2_cont.classList.remove('hidden');
        } else {
            d1_label.innerText = 'EMI Due Date';
            let opts = ''; for(let i=1; i<=28; i++) opts += `<option value="${i}" ${i==5?'selected':''}>Date: ${i}</option>`;
            d1_select.innerHTML = opts;
            d2_cont.classList.add('hidden');
        }
        updateProjection();
    });
});

function previewFile(input, previewId) {
    const preview = document.getElementById(previewId);
    const file = input.files[0];
    const reader = new FileReader();

    if (file) {
        if(file.size > 2 * 1024 * 1024) {
            alert('File too large! Max 2MB allowed.');
            input.value = '';
            preview.classList.add('hidden');
            return;
        }

        preview.classList.remove('hidden');
        if (file.type.match('image.*')) {
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
            }
            reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf') {
            preview.innerHTML = `<div class="flex flex-col items-center justify-center text-rose-500"><i class="ph ph-file-pdf text-3xl"></i><span class="text-[8px] font-black uppercase">PDF</span></div>`;
        } else {
            preview.innerHTML = `<div class="flex flex-col items-center justify-center text-slate-400"><i class="ph ph-file text-3xl"></i><span class="text-[8px] font-black uppercase">FILE</span></div>`;
        }
    } else {
        preview.classList.add('hidden');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
