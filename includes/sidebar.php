<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);

// Service enablement flags
$loan_only = getSetting($conn, 'loan_only_mode') == '1';
$svc_loan = getSetting($conn, 'service_loan_enabled') == '1';
$svc_savings = getSetting($conn, 'service_savings_enabled') == '1';
$svc_dd = getSetting($conn, 'service_dd_enabled') == '1';
$svc_rd = getSetting($conn, 'service_rd_enabled') == '1';
$svc_fd = getSetting($conn, 'service_fd_enabled') == '1';
$svc_mis = getSetting($conn, 'service_mis_enabled') == '1';
?>
<aside class="w-56 bg-slate-900 text-white flex flex-col shadow-xl">
    <div class="h-16 flex items-center justify-center border-b border-slate-700 px-4">
        <h1 class="text-lg font-bold tracking-wider flex items-center gap-2 text-center">
            <i class="ph ph-bank text-indigo-400 text-2xl shrink-0"></i>
            <span
                class="truncate uppercase"><?= htmlspecialchars(getSetting($conn, 'bank_name') ?: 'NBFC Core') ?></span>
        </h1>
    </div>

    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
        <a href="<?= APP_URL ?>index.php"
            class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all <?= $current_page == 'index.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/50' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-squares-four text-lg"></i>
            <span class="font-medium">Dashboard</span>
        </a>

        <!-- Member Registry -->
        <div class="pt-4 pb-1 px-3">
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Member Registry</p>
        </div>
        <a href="<?= APP_URL ?>members/add.php"
            class="group flex items-center gap-3 px-3 py-1.5 rounded-lg transition-all <?= $current_page == 'add.php' ? 'text-indigo-400 font-bold' : 'text-slate-400 hover:text-white' ?>">
            <i class="ph ph-user-plus text-lg"></i>
            <span class="text-sm">Enrol New Member</span>
        </a>
        <a href="<?= APP_URL ?>members/list.php"
            class="group flex items-center gap-3 px-3 py-1.5 rounded-lg transition-all <?= $current_page == 'list.php' && strpos($_SERVER['REQUEST_URI'], 'members') !== false ? 'text-indigo-400 font-bold' : 'text-slate-400 hover:text-white' ?>">
            <i class="ph ph-users-three text-lg"></i>
            <span class="text-sm">Member Directory</span>
        </a>

        <!-- Credit Services -->
        <div class="pt-4 pb-1 px-3">
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Credit Services</p>
        </div>
        <?php if ($_SESSION['role'] == 'admin'): ?>
            <a href="<?= APP_URL ?>loans/list.php?status=pending_approval"
                class="group flex items-center justify-between px-3 py-1.5 rounded-lg transition-all <?= (isset($_GET['status']) && $_GET['status'] == 'pending_approval') ? 'bg-amber-600 text-white shadow-lg shadow-amber-900/50' : 'text-slate-400 hover:text-white' ?>">
                <div class="flex items-center gap-3">
                    <i
                        class="ph ph-shield-warning text-lg <?= (isset($_GET['status']) && $_GET['status'] == 'pending_approval') ? 'text-white' : 'text-amber-500' ?>"></i>
                    <span class="text-sm">Pending Queue</span>
                </div>
                <?php
                $p_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM accounts WHERE status = 'pending_approval'"))['c'];
                if ($p_count > 0):
                    ?>
                    <span
                        class="<?= (isset($_GET['status']) && $_GET['status'] == 'pending_approval') ? 'bg-white text-amber-600' : 'bg-amber-600 text-white' ?> text-[10px] font-black px-1.5 py-0.5 rounded-md"><?= $p_count ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= APP_URL ?>loans/list.php"
                class="group flex items-center gap-3 px-3 py-1.5 rounded-lg transition-all <?= ($current_page == 'list.php' && !isset($_GET['status'])) ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/10' : 'text-slate-400 hover:text-white' ?>">
                <i class="ph ph-briefcase text-lg text-indigo-400"></i>
                <span class="text-sm">Active Loans</span>
            </a>
        <?php endif; ?>

        <a href="<?= APP_URL ?>loans/disburse.php"
            class="group flex items-center gap-3 px-3 py-1.5 rounded-lg transition-all <?= $current_page == 'disburse.php' ? 'text-indigo-400 font-bold' : 'text-slate-400 hover:text-white' ?>">
            <i class="ph ph-hand-coins text-lg text-emerald-500"></i>
            <span class="text-sm"><?= $_SESSION['role'] == 'admin' ? 'New Disbursal' : 'Apply for Loan' ?></span>
        </a>
        <a href="<?= APP_URL ?>loans/today_collection.php"
            class="group flex items-center gap-3 px-3 py-1.5 rounded-lg transition-all <?= $current_page == 'today_collection.php' ? 'bg-indigo-600/10 text-indigo-400 font-bold border border-indigo-500/10' : 'text-slate-400 hover:text-white' ?>">
            <i class="ph ph-phone-call text-lg text-indigo-400"></i>
            <span class="text-sm">Today's Collections</span>
        </a>
        <a href="<?= APP_URL ?>loans/pay.php"
            class="group flex items-center gap-3 px-3 py-1.5 rounded-lg transition-all <?= $current_page == 'pay.php' ? 'text-indigo-400 font-bold' : 'text-slate-400 hover:text-white' ?>">
            <i class="ph ph-currency-circle-dollar text-lg text-amber-500"></i>
            <span class="text-sm">Collect EMI</span>
        </a>

        <!-- Audit Center -->
        <div class="pt-4 pb-1 px-3">
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Audit & Intelligence</p>
        </div>
        <a href="<?= APP_URL ?>reports/collection_report.php"
            class="group flex items-center gap-3 px-3 py-1.5 rounded-lg transition-all <?= $current_page == 'collection_report.php' ? 'text-indigo-400 font-bold' : 'text-slate-400 hover:text-white' ?>">
            <i class="ph ph-chart-line-up text-lg text-emerald-500"></i>
            <span class="text-sm">Collection Report</span>
        </a>
        <?php if ($_SESSION['role'] == 'admin'): ?>
            <a href="<?= APP_URL ?>reports/branch_report.php"
                class="group flex items-center gap-3 px-3 py-1.5 rounded-lg transition-all <?= $current_page == 'branch_report.php' ? 'text-indigo-400 font-bold' : 'text-slate-400 hover:text-white' ?>">
                <i class="ph ph-buildings text-lg text-indigo-400"></i>
                <span class="text-sm">Branch Portfolio</span>
            </a>
            <a href="<?= APP_URL ?>reports/staff_commissions.php"
                class="group flex items-center gap-3 px-3 py-1.5 rounded-lg transition-all <?= $current_page == 'staff_commissions.php' ? 'text-indigo-400 font-bold' : 'text-slate-400 hover:text-white' ?>">
                <i class="ph ph-coins text-lg text-amber-500"></i>
                <span class="text-sm">Commission Report</span>
            </a>
            <a href="<?= APP_URL ?>reports/defaulters.php"
                class="group flex items-center gap-3 px-3 py-1.5 rounded-lg transition-all <?= $current_page == 'defaulters.php' ? 'text-indigo-400 font-bold' : 'text-slate-400 hover:text-white' ?>">
                <i class="ph ph-warning-octagon text-lg text-rose-500"></i>
                <span class="text-sm">Defaulters List</span>
            </a>
            <a href="<?= APP_URL ?>reports/cancelled_receipts.php"
                class="group flex items-center gap-3 px-3 py-1.5 rounded-lg transition-all <?= $current_page == 'cancelled_receipts.php' ? 'text-indigo-400 font-bold' : 'text-slate-400 hover:text-white' ?>">
                <i class="ph ph-trash text-lg text-rose-500"></i>
                <span class="text-sm">Canceled Receipt</span>
            </a>
        <?php endif; ?>

        <?php if ($_SESSION['role'] == 'admin'): ?>
            <!-- Administration -->
            <div class="pt-4 pb-1 px-3">
                <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Regional Control</p>
            </div>
            <a href="<?= APP_URL ?>settings/branches.php"
                class="group flex items-center gap-3 px-3 py-1.5 rounded-lg transition-all <?= strpos($_SERVER['REQUEST_URI'], 'branches') !== false ? 'text-indigo-400 font-bold' : 'text-slate-400 hover:text-white' ?>">
                <i class="ph ph-buildings text-lg text-amber-500"></i>
                <span class="text-sm">Manage Branches</span>
            </a>
            <a href="<?= APP_URL ?>users/index.php"
                class="group flex items-center gap-3 px-3 py-1.5 rounded-lg transition-all <?= $current_page == 'index.php' && strpos($_SERVER['REQUEST_URI'], 'users') !== false ? 'text-indigo-400 font-bold' : 'text-slate-400 hover:text-white' ?>">
                <i class="ph ph-user-gear text-lg text-violet-500"></i>
                <span class="text-sm">Staff Manager</span>
            </a>
            <a href="<?= APP_URL ?>settings/general.php"
                class="group flex items-center gap-3 px-3 py-1.5 rounded-lg transition-all <?= strpos($_SERVER['REQUEST_URI'], 'settings/general') !== false ? 'text-indigo-400 font-bold' : 'text-slate-400 hover:text-white' ?>">
                <i class="ph ph-gear text-lg text-slate-400"></i>
                <span class="text-sm">Master Setup</span>
            </a>
        <?php endif; ?>
    </nav>

    </div>
</aside>
<div class="flex-1 flex flex-col overflow-hidden">
    <!-- Topbar -->
    <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 z-10 border-b border-gray-100">
        <div class="flex items-center gap-4">
            <button onclick="toggleSidebar()"
                class="p-2 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors cursor-pointer"
                title="Toggle Sidebar">
                <i class="ph ph-list text-2xl"></i>
            </button>
            <h2 class="text-xl font-semibold text-gray-800">
                <?php
                $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                $parts = explode('/', trim($path, '/'));
                if (count($parts) > 1 && !in_array(end($parts), ['index.php', ''])) {
                    $dir = $parts[count($parts) - 2];
                    if ($dir != 'nbfc')
                        echo ucfirst($dir) . ' / ';
                }
                $file = basename($_SERVER['PHP_SELF']);
                if ($file == 'index.php') {
                    echo 'Dashboard';
                } else {
                    echo ucwords(str_replace(['.php', '_'], ['', ' '], $file));
                }
                ?>
            </h2>
        </div>

        <script>
            function toggleSidebar() {
                document.body.classList.toggle('sidebar-collapsed');
                const isCollapsed = document.body.classList.contains('sidebar-collapsed');
                localStorage.setItem('sidebarState', isCollapsed ? 'collapsed' : 'expanded');
            }
        </script>
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2 mr-4 border-r border-gray-100 pr-4">
                <a href="<?= APP_URL ?>help/calculations.php"
                    class="p-2 text-sky-500 hover:bg-sky-50 rounded-lg transition-colors cursor-pointer"
                    title="Calculation Logic Help">
                    <i class="ph ph-lightbulb text-xl"></i>
                </a>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                    <a href="<?= APP_URL ?>reports/system_logs.php"
                        class="p-2 text-indigo-500 hover:bg-indigo-50 rounded-lg transition-colors cursor-pointer"
                        title="System Audit Logs">
                        <i class="ph ph-activity text-xl"></i>
                    </a>
                <?php endif; ?>
            </div>

            <div class="relative group">
                <button type="button" onclick="document.getElementById('profileDropdown').classList.toggle('hidden')"
                    class="flex items-center gap-3 hover:bg-gray-50 p-1 rounded-lg transition-colors cursor-pointer">
                    <div
                        class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold shadow-sm">
                        <?= isset($_SESSION['name']) ? strtoupper(substr($_SESSION['name'], 0, 1)) : 'U' ?>
                    </div>
                    <div class="text-left hidden md:block">
                        <p class="text-sm font-bold text-gray-800 leading-tight">
                            <?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'User' ?>
                        </p>
                        <p class="text-[10px] text-gray-500 uppercase font-bold tracking-tighter">
                            <?= isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'Role' ?>
                        </p>
                    </div>
                    <i class="ph ph-caret-down text-gray-400 text-xs ml-1"></i>
                </button>

                <!-- Dropdown Menu -->
                <div id="profileDropdown"
                    class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-gray-100 z-50 overflow-hidden py-1">
                    <div class="px-4 py-2 border-b border-gray-50 md:hidden">
                        <p class="text-sm font-bold text-gray-800 leading-tight">
                            <?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'User' ?>
                        </p>
                        <p class="text-[10px] text-gray-500 uppercase font-bold tracking-tighter">
                            <?= isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'Role' ?>
                        </p>
                    </div>
                    <a href="<?= APP_URL ?>profile/password.php"
                        class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                        <i class="ph ph-key"></i> Change Password
                    </a>
                    <div class="h-px bg-gray-50 my-1"></div>
                    <a href="javascript:void(0)" onclick="confirmLogout()"
                        class="flex items-center gap-2 px-4 py-2 text-sm text-rose-600 hover:bg-rose-50 transition-colors">
                        <i class="ph ph-sign-out"></i> Logout Session
                    </a>
                </div>
            </div>
        </div>

        <script>
            function confirmLogout() {
                if (confirm('Are you sure you want to terminate this operational session?')) {
                    window.location.href = '<?= APP_URL ?>logout.php';
                }
            }
            // Close dropdown when clicking outside
            window.addEventListener('click', function (e) {
                const dropdown = document.getElementById('profileDropdown');
                const btn = dropdown.previousElementSibling;
                if (!btn.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });
        </script>
    </header>
    <!-- Main Content Area -->
    <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">