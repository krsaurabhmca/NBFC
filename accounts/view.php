<?php
require_once '../includes/db.php';
checkAuth();
require_once '../includes/functions.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$filter_type = isset($_GET['type']) ? sanitize($conn, $_GET['type']) : '';
$where = "WHERE 1=1";
if($filter_type && in_array($filter_type, ['Savings', 'Loan', 'FD', 'RD', 'MIS', 'DD'])) {
    $where .= " AND a.account_type = '$filter_type'";
}

$sql = "SELECT a.*, m.first_name, m.last_name, m.member_no, s.scheme_name, s.interest_rate 
        FROM accounts a 
        JOIN members m ON a.member_id = m.id 
        JOIN schemes s ON a.scheme_id = s.id 
        $where 
        ORDER BY a.id DESC";
$result = mysqli_query($conn, $sql);
?>

<div class="max-w-7xl mx-auto">
    
    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Account Directory</h1>
            <p class="text-gray-500 text-sm mt-1">Manage and view all customer accounts across schemes</p>
        </div>
        
        <div class="flex items-center gap-3">
            <div class="bg-white border border-gray-200 rounded-lg p-1 hidden lg:flex items-center text-sm shadow-sm">
                <a href="view.php" class="px-3 py-1.5 rounded-md <?= !$filter_type ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>">All</a>
                <a href="?type=Savings" class="px-3 py-1.5 rounded-md <?= $filter_type=='Savings' ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>">Savings</a>
                <a href="?type=Loan" class="px-3 py-1.5 rounded-md <?= $filter_type=='Loan' ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>">Loans</a>
                <a href="?type=FD" class="px-3 py-1.5 rounded-md <?= $filter_type=='FD' ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>">FDs</a>
                <a href="?type=RD" class="px-3 py-1.5 rounded-md <?= $filter_type=='RD' ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>">RDs</a>
                <a href="?type=MIS" class="px-3 py-1.5 rounded-md <?= $filter_type=='MIS' ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>">MIS</a>
                <a href="?type=DD" class="px-3 py-1.5 rounded-md <?= $filter_type=='DD' ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>">Daily Dep..</a>
            </div>
            
            <a href="open.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm flex items-center gap-2">
                <i class="ph ph-folder-plus text-lg"></i> Open Account
            </a>
        </div>
    </div>

    <?= displayAlert() ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/80 text-gray-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-4 font-medium">Account Details</th>
                        <th class="px-6 py-4 font-medium">Customer</th>
                        <th class="px-6 py-4 font-medium">Scheme & Term</th>
                        <th class="px-6 py-4 font-medium text-right">Balance/Principal</th>
                        <th class="px-6 py-4 font-medium text-center">Status</th>
                        <th class="px-6 py-4 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <span class="font-bold text-gray-800 tracking-wide text-sm flex items-center gap-2">
                                        <?php if($row['account_type'] == 'Loan') echo '<i class="ph ph-hand-coins text-rose-500"></i>'; ?>
                                        <?php if($row['account_type'] == 'Savings') echo '<i class="ph ph-piggy-bank text-emerald-500"></i>'; ?>
                                        <?php if(in_array($row['account_type'], ['FD','RD','MIS','DD'])) echo '<i class="ph ph-chart-line-up text-blue-500"></i>'; ?>
                                        <?= htmlspecialchars($row['account_no']) ?>
                                    </span>
                                    <div class="text-xs text-gray-400 mt-1">Opened: <?= date('d M Y', strtotime($row['opening_date'])) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-800"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                    <div class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($row['member_no']) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-gray-700 bg-gray-100 px-2 py-1 rounded w-max text-xs font-medium mb-1">
                                        <?= htmlspecialchars($row['account_type']) ?> - <?= $row['interest_rate'] ?>%
                                    </div>
                                    <?php if($row['maturity_date']): ?>
                                        <div class="text-xs text-slate-500 flex items-center gap-1">
                                            <i class="ph ph-calendar-blank"></i> Matures: <?= date('M Y', strtotime($row['maturity_date'])) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-xs text-slate-400">No fixed maturity</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <?php if($row['account_type'] == 'Loan'): ?>
                                        <div class="font-semibold text-rose-600 block"><?= formatCurrency(abs($row['current_balance'])) ?> <span class="text-[10px] text-rose-400 font-normal uppercase ml-0.5">Due</span></div>
                                        <div class="text-xs text-gray-500 mt-0.5">Disbursed: <?= formatCurrency($row['principal_amount']) ?></div>
                                    <?php else: ?>
                                        <div class="font-semibold text-emerald-600 block"><?= formatCurrency($row['current_balance']) ?></div>
                                        <?php if($row['principal_amount'] > 0): ?>
                                            <div class="text-xs text-gray-500 mt-0.5">Principal: <?= formatCurrency($row['principal_amount']) ?></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php
                                        $scolor = 'emerald';
                                        if($row['status'] == 'closed' || $row['status'] == 'pre-closed') $scolor = 'gray';
                                        if($row['status'] == 'defaulted') $scolor = 'rose';
                                        if($row['status'] == 'matured') $scolor = 'blue';
                                    ?>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-<?= $scolor ?>-100 text-<?= $scolor ?>-700 capitalize">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex gap-2 justify-end">
                                    <a href="../transactions/process.php?account_id=<?= $row['id'] ?>" class="text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-medium px-3 py-1.5 rounded-lg transition-colors border border-indigo-200">
                                        Transact
                                    </a>
                                    
                                    <?php if(in_array($row['account_type'], ['FD', 'RD', 'MIS'])): ?>
                                        <a href="certificate.php?id=<?= $row['id'] ?>" target="_blank" class="text-xs bg-amber-50 hover:bg-amber-100 text-amber-700 font-medium px-3 py-1.5 rounded-lg transition-colors border border-amber-200" title="Print Certificate">
                                            <i class="ph ph-certificate"></i> Cert
                                        </a>
                                    <?php else: ?>
                                        <a href="statement.php?id=<?= $row['id'] ?>" class="text-xs bg-gray-50 hover:bg-gray-100 text-gray-700 font-medium px-3 py-1.5 rounded-lg transition-colors border border-gray-200" title="Print Statement">
                                            <i class="ph ph-file-text"></i> Stmt
                                        </a>
                                    <?php endif; ?>

                                    <a href="../members/ledger.php?id=<?= $row['member_id'] ?>" class="text-xs bg-slate-50 hover:bg-slate-100 text-slate-700 font-medium px-3 py-1.5 rounded-lg transition-colors border border-slate-200" title="Member Ledger">
                                        <i class="ph ph-user"></i>
                                    </a>
                                </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <i class="ph ph-folder-open text-4xl text-gray-300 mb-3 block"></i>
                            No accounts found for the selected filter.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
