<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

$error = '';
$success = '';

// Fetch Members
$members = mysqli_query($conn, "SELECT id, member_no, first_name, last_name FROM members WHERE status = 'active' ORDER BY first_name ASC");

// Fetch Employees/Advisors for Reference
$employees = mysqli_query($conn, "SELECT id, name, role FROM users WHERE status = 'active' ORDER BY name ASC");

// Fetch Loan Schemes
$schemes = mysqli_query($conn, "SELECT * FROM schemes WHERE scheme_type = 'Loan' AND status = 'active' ORDER BY scheme_name ASC");
$schemes_json = [];
while($s = mysqli_fetch_assoc($schemes)) {
    $schemes_json[$s['id']] = $s;
}
mysqli_data_seek($schemes, 0);

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['disburse_loan'])) {
    $member_id = (int)$_POST['member_id'];
    $scheme_id = (int)$_POST['scheme_id'];
    $principal = (float)$_POST['amount'];
    $tenure = (int)$_POST['tenure'];
    $interest_rate = (float)$_POST['interest_rate'];
    $emi_date = (int)$_POST['emi_date'];
    $referred_by = !empty($_POST['referred_by']) ? (int)$_POST['referred_by'] : 'NULL';
    $disbursal_comm_pct = (float)$_POST['disbursal_comm_pct'];
    $collection_comm_pct = (float)$_POST['collection_comm_pct'];
    $interest_type = sanitize($conn, $_POST['loan_interest_type']);
    $disbursal_date = !empty($_POST['disbursal_date']) ? sanitize($conn, $_POST['disbursal_date']) : date('Y-m-d');
    
    $account_no = generateSequenceNo($conn, 'LOAN', 'accounts', 'account_no');
    $maturity_date = date('Y-m-d', strtotime($disbursal_date . " +$tenure months"));
    
    mysqli_query($conn, "START TRANSACTION");
    
    // 1. Create Loan Account (Status: Pending Approval)
    $sql_acc = "INSERT INTO accounts (account_no, member_id, scheme_id, account_type, opening_balance, current_balance, principal_amount, tenure_months, interest_rate, opening_date, maturity_date, emi_date, referred_by, disbursal_commission_percent, collection_commission_percent, loan_interest_type, status) 
                VALUES ('$account_no', $member_id, $scheme_id, 'Loan', -$principal, -$principal, $principal, $tenure, $interest_rate, '$disbursal_date', '$maturity_date', $emi_date, $referred_by, $disbursal_comm_pct, $collection_comm_pct, '$interest_type', 'pending_approval')";
    
    if(mysqli_query($conn, $sql_acc)) {
        $account_id = mysqli_insert_id($conn);
        
        // 2. Generate EMI Schedule with Perfect Calculation
        $rate_monthly = ($interest_rate / 100) / 12;
        if($interest_type == 'Reducing') {
            $emi = ($principal * $rate_monthly * pow(1 + $rate_monthly, $tenure)) / (pow(1 + $rate_monthly, $tenure) - 1);
        } else {
            $total_int_flat = $principal * ($interest_rate / 100) * ($tenure / 12);
            $emi = ($principal + $total_int_flat) / $tenure;
        }
        $emi = round($emi, 2);
        
        $total_interest_accumulated = 0;
        $rem_principal = $principal;
        
        for($i = 0; $i < $tenure; $i++) {
            $base_time = strtotime($disbursal_date);
            $disbursal_day = (int)date('d', $base_time);
            $month_offset = ($emi_date > $disbursal_day) ? $i : ($i + 1);
            
            $d_date = date('Y-m-d', strtotime($disbursal_date . " + $month_offset months"));
            $d_parts = explode('-', $d_date);
            $final_due_date = $d_parts[0].'-'.$d_parts[1].'-'.str_pad($emi_date, 2, '0', STR_PAD_LEFT);
            
            if($interest_type == 'Reducing') {
                $int_comp = round($rem_principal * $rate_monthly, 2);
                if ($i == $tenure - 1) {
                    $prin_comp = $rem_principal; // Force last installment to finish principal
                    $emi_adj = $prin_comp + $int_comp;
                } else {
                    $prin_comp = round($emi - $int_comp, 2);
                    $emi_adj = $emi;
                }
                $rem_principal -= $prin_comp;
            } else {
                $prin_comp = round($principal / $tenure, 2);
                if ($i == $tenure - 1) $prin_comp = $principal - (($tenure-1) * $prin_comp); // Correction for last installment
                $int_comp = round($emi - $prin_comp, 2);
                $emi_adj = $emi;
            }
            
            $total_interest_accumulated += $int_comp;
            
            mysqli_query($conn, "INSERT INTO loan_schedules (account_id, installment_no, due_date, emi_amount, principal_component, interest_component, status) 
                                 VALUES ($account_id, ($i+1), '$final_due_date', $emi_adj, $prin_comp, $int_comp, 'Pending')");
        }
        
        // 3. Update Account with Total Contract Value (Principal + Interest)
        $total_payable = $principal + $total_interest_accumulated;
        mysqli_query($conn, "UPDATE accounts SET current_balance = -$total_payable, installment_amount = $emi WHERE id = $account_id");
        
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

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-800 tracking-tight">Loan Application Center</h1>
            <p class="text-slate-500 text-xs mt-1 flex items-center gap-1.5"><i class="ph ph-shield-warning text-amber-500"></i> Submit Application for Administrative Review</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="list.php" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all flex items-center gap-2">
                <i class="ph ph-list-bullets text-lg"></i> View Loan Book
            </a>
        </div>
    </div>

    <?= displayAlert() ?>

    <form method="POST" action="" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-8 space-y-6">
            
            <!-- Section 1: Customer & Scheme -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-slate-50 rounded-bl-full -z-0 opacity-50"></div>
                <div class="relative z-10">
                    <h3 class="font-black text-slate-800 text-[11px] uppercase tracking-widest mb-5 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-[10px]">01</span>
                        Account Configuration
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Select Customer</label>
                            <select name="member_id" required class="select2-init w-full px-4 py-2.5 border rounded-xl text-sm font-medium bg-slate-50/50 border-slate-200 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 transition-all">
                                <option value="">-- Search by Name or ID --</option>
                                <?php while($m = mysqli_fetch_assoc($members)): ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?> (<?= $m['member_no'] ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Loan Product / Scheme</label>
                            <select name="scheme_id" id="schemeSelect" required class="w-full px-4 py-2.5 border rounded-xl text-sm font-medium bg-slate-50/50 border-slate-200 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 transition-all">
                                <option value="">-- Choose Product --</option>
                                <?php foreach($schemes_json as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['scheme_name']) ?> (<?= $s['interest_rate'] ?>%)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Loan Financials -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <h3 class="font-black text-slate-800 text-[11px] uppercase tracking-widest mb-5 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-[10px]">02</span>
                    Financial Structure
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Principal Amount (₹)</label>
                        <div class="relative">
                            <i class="ph ph-currency-inr absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="number" step="0.01" name="amount" id="principal" placeholder="0.00" required class="w-full pl-9 pr-4 py-2.5 border rounded-xl text-sm font-black text-indigo-700 bg-slate-50 focus:bg-white transition-all">
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Interest Rate (% P.A.)</label>
                        <input type="number" step="0.01" name="interest_rate" id="interest_rate" required class="w-full px-4 py-2.5 border rounded-xl text-sm font-bold bg-slate-50 focus:bg-white transition-all">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Tenure (Months)</label>
                        <input type="number" name="tenure" id="tenure" required class="w-full px-4 py-2.5 border rounded-xl text-sm font-bold bg-slate-50 focus:bg-white transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">EMI Due Date (Day)</label>
                        <select name="emi_date" class="w-full px-4 py-2.5 border rounded-xl text-sm font-medium bg-slate-50 focus:bg-white transition-all">
                            <?php for($i=1; $i<=28; $i++): ?>
                                <option value="<?= $i ?>" <?= $i==5 ? 'selected' : '' ?>>Every <?= $i ?><?= ($i==1?'st':($i==2?'nd':($i==3?'rd':'th'))) ?> of the month</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Interest Method</label>
                        <select name="loan_interest_type" class="w-full px-4 py-2.5 border rounded-xl text-sm font-bold bg-slate-50 focus:bg-white transition-all">
                            <option value="Flat">Flat Interest Rate</option>
                            <option value="Reducing">Reducing Balance (Standard)</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Section 3: Staff & Commission -->
            <div class="bg-slate-50 p-6 rounded-2xl border border-slate-200/50">
                <h3 class="font-black text-slate-800 text-[11px] uppercase tracking-widest mb-5 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-slate-800 text-white flex items-center justify-center text-[10px]">03</span>
                    Staff Assignment & Commission
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                    <div class="md:col-span-6 space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Responsible Advisor / Reference</label>
                        <select name="referred_by" class="select2-init w-full px-4 py-2.5 border rounded-xl text-sm bg-white">
                            <option value="">-- No Assignment --</option>
                            <?php while($e = mysqli_fetch_assoc($employees)): ?>
                                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?> (<?= $e['role'] ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="md:col-span-3 space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Disbursal Comm (%)</label>
                        <input type="number" step="0.01" name="disbursal_comm_pct" value="0.00" class="w-full px-4 py-2.5 border rounded-xl text-sm font-bold bg-white">
                    </div>
                    <div class="md:col-span-3 space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">EMI Collection (%)</label>
                        <input type="number" step="0.01" name="collection_comm_pct" value="0.00" class="w-full px-4 py-2.5 border rounded-xl text-sm font-bold bg-white">
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-4 space-y-6">
            <!-- EMI Projection Card -->
            <div class="bg-slate-900 rounded-3xl p-6 text-white shadow-2xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-500/10 rounded-full -mr-16 -mt-16"></div>
                
                <h3 class="font-black text-indigo-400 text-[10px] uppercase tracking-widest mb-6 flex items-center gap-2">
                    <i class="ph ph-trend-up"></i> Repayment Projection
                </h3>
                
                <div class="space-y-6 text-center">
                    <div>
                        <p class="text-indigo-200 text-[10px] font-bold uppercase tracking-widest mb-1 opacity-70">Monthly Installment</p>
                        <h2 class="text-4xl font-black tracking-tighter" id="projEMI">₹ 0.00</h2>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 py-5 border-y border-white/5">
                        <div class="text-left">
                            <span class="block text-[10px] text-white/40 font-bold uppercase mb-1">Princ. Total</span>
                            <span class="font-bold text-xs" id="projPrincRec">₹ 0.00</span>
                        </div>
                        <div class="text-right">
                            <span class="block text-[10px] text-white/40 font-bold uppercase mb-1">Interest</span>
                            <span class="font-bold text-xs text-amber-500" id="projTotalInterest">₹ 0.00</span>
                        </div>
                    </div>

                    <div class="flex justify-between items-center px-2">
                        <span class="text-indigo-200 text-xs font-medium">Total Repayable</span>
                        <span class="text-lg font-black" id="projGrandTotal">₹ 0.00</span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="space-y-3">
                <?php if($_SESSION['role'] == 'admin'): ?>
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Disbursal Date</label>
                    <input type="date" name="disbursal_date" value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 border rounded-xl text-sm font-bold bg-slate-50 focus:bg-white transition-all outline-none">
                </div>
                <?php else: ?>
                    <input type="hidden" name="disbursal_date" value="<?= date('Y-m-d') ?>">
                    <div class="bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100/50 flex items-center justify-between">
                        <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest">Application Date</span>
                        <span class="text-xs font-black text-indigo-600"><?= date('d M, Y') ?> (Today)</span>
                    </div>
                <?php endif; ?>

                <button type="submit" name="disburse_loan" class="w-full py-5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-black text-sm shadow-xl shadow-indigo-100 transition-all flex flex-col items-center gap-1 group relative overflow-hidden">
                    <div class="absolute inset-0 bg-white/0 group-hover:bg-white/10 transition-colors"></div>
                    <?php if($_SESSION['role'] == 'admin'): ?>
                        <span class="relative z-10 flex items-center gap-2"><i class="ph ph-shield-check text-xl"></i> Finalize Disbursal</span>
                        <span class="relative z-10 text-[9px] font-medium opacity-60">Generate Schedule & Post Transaction</span>
                    <?php else: ?>
                        <span class="relative z-10 flex items-center gap-2"><i class="ph ph-paper-plane-tilt text-xl"></i> Submit Application</span>
                        <span class="relative z-10 text-[9px] font-medium opacity-60">Send for Administrative Review</span>
                    <?php endif; ?>
                </button>
                
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
        
        if(p > 0 && r > 0 && t > 0) {
            let emi = 0;
            let total_int = 0;
            if(type === 'Reducing') {
                const r_monthly = (r / 100) / 12;
                emi = (p * r_monthly * Math.pow(1 + r_monthly, t)) / (Math.pow(1 + r_monthly, t) - 1);
                total_int = (emi * t) - p;
            } else {
                total_int = p * (r / 100) * (t / 12);
                emi = (p + total_int) / t;
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
});
</script>

<?php require_once '../includes/footer.php'; ?>
