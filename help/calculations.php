<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-5xl mx-auto pb-12">
    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight flex items-center gap-3">
            <i class="ph ph-info text-indigo-600"></i> Calculation Methodologies
        </h1>
        <p class="text-gray-500 mt-2 text-lg">Detailed breakdown of how interest, penalties, and payouts are computed within the core banking system.</p>
    </div>

    <!-- Savings Section -->
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden mb-10 transition-all hover:shadow-md">
        <div class="p-6 md:p-10 border-b border-gray-50 bg-gradient-to-r from-indigo-50/50 to-transparent">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg shadow-indigo-200">
                    <i class="ph ph-piggy-bank"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Savings Account Interest</h2>
                    <p class="text-sm text-indigo-600 font-semibold tracking-wide uppercase">Daily Balance Method</p>
                </div>
            </div>
            <p class="text-gray-600 leading-relaxed max-w-2xl">
                Interest on savings accounts is calculated on a daily basis based on the end-of-day balance. This ensures accuracy even with multiple transactions within the same month.
            </p>
        </div>
        <div class="p-8 md:p-10 grid md:grid-cols-2 gap-10 bg-white">
            <div>
                <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="ph ph-function text-indigo-500"></i> The Formula
                </h3>
                <div class="bg-slate-900 rounded-2xl p-6 text-indigo-100 font-mono text-center shadow-inner">
                    <span class="text-indigo-400">Interest</span> = (B &times; R &times; D) / 36500
                </div>
                <div class="mt-6 space-y-3 text-sm">
                    <div class="flex gap-3">
                        <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xs shrink-0">B</span>
                        <p><span class="font-semibold text-gray-800">Balance:</span> The standard closing balance of the day.</p>
                    </div>
                    <div class="flex gap-3">
                        <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xs shrink-0">R</span>
                        <p><span class="font-semibold text-gray-800">Rate:</span> Annual interest rate percentage (e.g., 3.50%).</p>
                    </div>
                    <div class="flex gap-3">
                        <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xs shrink-0">D</span>
                        <p><span class="font-semibold text-gray-800">Days:</span> Number of days for which interest is calculated (usually 1 for daily processor).</p>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 rounded-2xl p-8 border border-gray-100">
                <h4 class="font-bold text-gray-800 mb-3 text-sm flex items-center gap-2 uppercase tracking-wider">
                    <i class="ph ph-lightning text-amber-500"></i> Step-by-Step Example
                </h4>
                <ol class="space-y-4 text-sm text-gray-600">
                    <li class="flex gap-3">
                        <span class="font-bold text-gray-400">01.</span>
                        <p>Closing Balance: <span class="text-gray-900 font-semibold">₹1,00,000</span></p>
                    </li>
                    <li class="flex gap-3">
                        <span class="font-bold text-gray-400">02.</span>
                        <p>Annual Rate: <span class="text-gray-900 font-semibold">4.00%</span></p>
                    </li>
                    <li class="flex gap-3">
                        <span class="font-bold text-gray-400">03.</span>
                        <div class="p-3 bg-white rounded-lg border border-gray-200 shadow-sm leading-relaxed text-xs">
                            (1,00,000 &times; 4 &times; 1) &divide; 36,500 = <strong class="text-indigo-600">₹10.96</strong>
                        </div>
                    </li>
                    <li class="flex gap-3">
                        <span class="font-bold text-gray-400">04.</span>
                        <p>The system stores this in <span class="font-bold text-gray-900">'interest_accrued'</span> daily. The <span class="text-indigo-600 font-bold underline">Final Credit</span> to the ledger happens only on <span class="bg-indigo-50 px-1 rounded">Quarterly Cycle Heads</span> (Jan 1st, Apr 1st, Jul 1st, Oct 1st).</p>
                    </li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Loan Section -->
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden mb-10 transition-all hover:shadow-md">
        <div class="p-6 md:p-10 border-b border-gray-50 bg-gradient-to-r from-rose-50/50 to-transparent">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-rose-600 rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg shadow-rose-200">
                    <i class="ph ph-hand-coins"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Loan & EMI Management</h2>
                    <p class="text-sm text-rose-600 font-semibold tracking-wide uppercase">EMI, Penalties & Defaults</p>
                </div>
            </div>
            <p class="text-gray-600 leading-relaxed max-w-2xl">
                The system supports both Flat and Reducing balance methods for EMI generation. It also tracks overdue installments to apply automated penalty fines.
            </p>
        </div>
        <div class="p-8 md:p-10 bg-white">
            <div class="grid md:grid-cols-2 gap-12">
                <!-- EMI Calc -->
                <div class="space-y-8">
                    <div>
                        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2 uppercase tracking-widest text-xs pr-4">
                            <span class="bg-rose-100 text-rose-700 px-2 py-0.5 rounded">01</span> Standard EMI Formulas
                        </h3>
                        <div class="space-y-4">
                            <div class="p-5 border border-dashed border-gray-200 rounded-2xl">
                                <p class="text-xs font-bold text-gray-400 mb-2 uppercase tracking-widest">Reducing Balance (Standard)</p>
                                <div class="bg-gray-50 p-4 rounded-xl text-center font-mono text-sm text-gray-800">
                                    [P &times; r &times; (1+r)^n] / [(1+r)^n - 1]
                                </div>
                                <p class="text-[11px] text-gray-400 mt-2 italic">*r = Monthly Rate, n = Monthly Tenure</p>
                            </div>
                            <div class="p-5 border border-dashed border-gray-200 rounded-2xl">
                                <p class="text-xs font-bold text-gray-400 mb-2 uppercase tracking-widest">Flat Rate (Simple)</p>
                                <div class="bg-gray-50 p-4 rounded-xl text-center font-mono text-sm text-gray-800">
                                    (Principal + Total Interest) / Total Months
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bounce & Fine -->
                <div class="bg-rose-50 rounded-2xl p-8 border border-rose-100 self-start">
                    <h4 class="font-bold text-rose-900 mb-4 flex items-center gap-2">
                        <i class="ph ph-warning-octagon text-xl"></i> EMI Bounce & Fine Calculation
                    </h4>
                    <p class="text-sm text-rose-800/80 mb-6 leading-relaxed">
                        If an EMI is not paid by 11:59 PM on the Due Date, the daily system processor marks it as <strong>Overdue</strong> and applies a penalty.
                    </p>
                    <div class="bg-white p-5 rounded-xl shadow-sm border border-rose-200">
                        <p class="text-xs font-bold text-rose-400 mb-1 uppercase tracking-widest text-center">Fine Amount Formula</p>
                        <p class="text-lg font-mono text-rose-900 text-center font-bold">EMI &times; Penalty %</p>
                    </div>
                    <div class="mt-6 bg-rose-100/50 p-4 rounded-lg">
                        <p class="text-xs text-rose-800 font-medium leading-relaxed">
                            <strong class="text-rose-900">Example:</strong> EMI of ₹5,000 with a 5% Penalty scheme. If bounced, a fine of <span class="font-bold">₹250</span> is added to the account balance, and the installment is locked for clearance.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RD & FD Section -->
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden mb-10 transition-all hover:shadow-md">
        <div class="p-6 md:p-10 border-b border-gray-50 bg-gradient-to-r from-emerald-50/50 to-transparent">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-emerald-600 rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg shadow-emerald-200">
                    <i class="ph ph-calendar-check"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Term Deposits (FD/RD)</h2>
                    <p class="text-sm text-emerald-600 font-semibold tracking-wide uppercase">Maturity & Pre-Closure logic</p>
                </div>
            </div>
        </div>
        <div class="p-8 md:p-10 bg-white space-y-12">
            <!-- Maturity Payout -->
            <div class="grid md:grid-cols-2 gap-10">
                <div>
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="ph ph-graph text-emerald-500"></i> Maturity Value (Standard)
                    </h3>
                    <div class="space-y-4 text-sm text-gray-600">
                        <div class="bg-gray-50 p-6 rounded-2xl border border-gray-200">
                            <p class="font-bold text-gray-800 mb-2">FD (Fixed Deposit)</p>
                            <p class="font-mono text-gray-600">P &times; (1 + r/n)<sup>nt</sup></p>
                            <p class="text-[11px] mt-1 text-gray-400">Quarterly compounding applied (n=4)</p>
                        </div>
                        <div class="bg-gray-50 p-6 rounded-2xl border border-gray-200">
                            <p class="font-bold text-gray-800 mb-2">RD (Recurring Deposit)</p>
                            <p class="font-mono text-gray-600">Total Contrib + Interest</p>
                            <p class="mt-2 text-[11px] text-gray-400 leading-relaxed italic">
                                Interest = [Installment &times; N &times; (N+1)/2] &times; (Rate/12)
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Pre-Closure Logic -->
                <div class="bg-amber-50 rounded-3xl p-8 border border-amber-200 shadow-sm shadow-amber-100">
                    <h4 class="font-extrabold text-amber-900 mb-4 flex items-center gap-2">
                        <i class="ph ph-scissors text-xl"></i> Pre-Closure Penalties
                    </h4>
                    <p class="text-sm text-amber-800 mb-6 leading-relaxed font-medium">
                        Closing a term deposit before the maturity date triggers a <span class="text-amber-900 underline underline-offset-4 decoration-amber-400 font-bold">Penal Rate Reduction</span>.
                    </p>
                    <div class="space-y-4">
                        <div class="bg-white p-5 rounded-2xl border border-amber-300">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-xs text-gray-400 font-bold uppercase">Applied Interest Rate</span>
                                <span class="text-xs text-rose-600 font-bold bg-rose-50 px-2 py-0.5 rounded">Penalty Deducted</span>
                            </div>
                            <div class="flex items-center gap-3 text-lg font-bold text-gray-800">
                                <span>Scheme Rate</span>
                                <i class="ph ph-minus text-amber-500"></i>
                                <span>Penalty Rate</span>
                            </div>
                        </div>
                        <p class="text-[11px] text-amber-700 leading-relaxed">
                            <strong class="text-amber-900">Bank Safeguard:</strong> For RD pre-closure, interest is calculated on the <span class="font-bold">Actual Days Held</span> for each deposit. Late lump-sum payments just before closure do not earn historical interest backdated to the account opening.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Discontinued RD Payment -->
            <div class="border-t border-gray-100 pt-10">
                <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="ph ph-warning-circle text-rose-500"></i> Discontinued RD Payments
                </h3>
                <div class="p-6 bg-gray-50 rounded-2xl border border-gray-200 grid md:grid-cols-3 gap-6">
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-700 leading-relaxed mb-4">
                            When a customer misses one or more RD payments, the system marks those installments as <strong>Overdue</strong>. Unlike Savings, RDs require sequential payments to qualify for full projected interest.
                        </p>
                        <div class="flex gap-4">
                            <div class="bg-white p-3 rounded-lg border border-gray-200">
                                <span class="block text-[10px] text-gray-400 font-bold mb-1 uppercase tracking-widest">Impact 01</span>
                                <p class="text-xs font-semibold text-gray-800 italic">Delayed fine applied per missed cycle.</p>
                            </div>
                            <div class="bg-white p-3 rounded-lg border border-gray-200">
                                <span class="block text-[10px] text-gray-400 font-bold mb-1 uppercase tracking-widest">Impact 02</span>
                                <p class="text-xs font-semibold text-gray-800 italic">Maturity amount is deferred until dues are cleared.</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-center items-center text-center">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Status Trigger</span>
                        <div class="w-16 h-1 bg-indigo-500 rounded-full mb-4"></div>
                        <p class="text-rose-600 font-bold text-lg">Auto-Defaulter</p>
                        <p class="text-[10px] text-gray-400 mt-1 leading-tight">After 3 consecutive months of non-payment.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
