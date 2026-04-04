<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="w-64 bg-slate-900 text-white flex flex-col shadow-xl">
    <div class="h-16 flex items-center justify-center border-b border-slate-700 px-4">
        <h1 class="text-lg font-bold tracking-wider flex items-center gap-2 text-center">
            <i class="ph ph-bank text-indigo-400 text-2xl shrink-0"></i> 
            <span class="truncate uppercase"><?= htmlspecialchars(getSetting($conn, 'bank_name') ?: 'NBFC Core') ?></span>
        </h1>
    </div>
    
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
        <a href="<?= APP_URL ?>index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= $current_page == 'index.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/50' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-squares-four text-xl <?= $current_page == 'index.php' ? 'text-white' : 'text-indigo-400' ?>"></i> 
            <span class="font-medium">Dashboard</span>
        </a>

        <?php if($_SESSION['role'] == 'admin'): ?>
        <div class="pt-5 pb-1 px-3">
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Member Management</p>
        </div>
        <a href="<?= APP_URL ?>members/add.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= $current_page == 'add.php' ? 'bg-emerald-600/10 text-emerald-400 border border-emerald-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-user-plus text-xl text-emerald-500 group-hover:scale-110 transition-transform"></i> 
            <span class="font-medium">New Membership</span>
        </a>
        <a href="<?= APP_URL ?>members/list.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= $current_page == 'list.php' ? 'bg-emerald-600/10 text-emerald-400 border border-emerald-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-users text-xl text-emerald-500 group-hover:scale-110 transition-transform"></i> 
            <span class="font-medium">Member Directory</span>
        </a>

        <div class="pt-5 pb-1 px-3">
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Core Banking</p>
        </div>
        <a href="<?= APP_URL ?>accounts/open.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= $current_page == 'open.php' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-folder-plus text-xl text-blue-500 group-hover:scale-110 transition-transform"></i> 
            <span class="font-medium">Open Account</span>
        </a>
        <a href="<?= APP_URL ?>accounts/view.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= $current_page == 'view.php' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-folders text-xl text-blue-500 group-hover:scale-110 transition-transform"></i> 
            <span class="font-medium">Account Ledger</span>
        </a>
        <a href="<?= APP_URL ?>accounts/closure.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= $current_page == 'closure.php' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-lock-key-open text-xl text-blue-400 group-hover:scale-110 transition-transform"></i> 
            <span class="font-medium">Account Closure</span>
        </a>

        <div class="pt-5 pb-1 px-3">
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Revenue & TXN</p>
        </div>
        <a href="<?= APP_URL ?>transactions/process.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= $current_page == 'process.php' ? 'bg-rose-600/10 text-rose-400 border border-rose-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-arrows-left-right text-xl text-rose-500 group-hover:scale-110 transition-transform"></i> 
            <span class="font-medium">Process Entry</span>
        </a>
        <?php endif; ?>

        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'advisor'): ?>
        <div class="pt-5 pb-1 px-3">
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Field Operations</p>
        </div>
        <a href="<?= APP_URL ?>advisor/collect.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= $current_page == 'collect.php' ? 'bg-amber-600/10 text-amber-400 border border-amber-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-hand-coins text-xl text-amber-500 group-hover:scale-110 transition-transform"></i> 
            <span class="font-medium">Collect Deposit</span>
        </a>
        <a href="<?= APP_URL ?>advisor/wallet_history.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= $current_page == 'wallet_history.php' ? 'bg-amber-600/10 text-amber-400 border border-amber-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-wallet text-xl text-amber-500 group-hover:scale-110 transition-transform"></i> 
            <span class="font-medium">My Performance</span>
        </a>
        <?php endif; ?>

        <div class="pt-5 pb-1 px-3">
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Analytics & Intelligence</p>
        </div>
        <?php if($_SESSION['role'] == 'admin'): ?>
        <a href="<?= APP_URL ?>reports/index.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= $current_page == 'index.php' && strpos($_SERVER['REQUEST_URI'], 'reports') !== false ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-chart-line-up text-xl text-indigo-400 group-hover:scale-110 transition-transform"></i> 
            <span class="font-medium">Filing Reports</span>
        </a>
        <a href="<?= APP_URL ?>reports/fine_collection.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= $current_page == 'fine_collection.php' ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-coins text-xl text-amber-500 group-hover:scale-110 transition-transform"></i> 
            <span class="font-medium">Fine Collection</span>
        </a>
        <a href="<?= APP_URL ?>transactions/advisor_txns.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= $current_page == 'advisor_txns.php' ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-receipt text-xl text-indigo-400 group-hover:scale-110 transition-transform"></i> 
            <span class="font-medium">Revenue Stream</span>
        </a>
        <?php endif; ?>
        
        <div class="pt-5 pb-1 px-3">
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">System Control</p>
        </div>
        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
        <a href="<?= APP_URL ?>users/index.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= $current_page == 'index.php' && strpos($_SERVER['REQUEST_URI'], 'users') !== false ? 'bg-violet-600/10 text-violet-400 border border-violet-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-shield-check text-xl text-violet-500 group-hover:scale-110 transition-transform"></i> 
            <span class="font-medium">Staff & RBAC</span>
        </a>
        <a href="<?= APP_URL ?>settings/general.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= strpos($_SERVER['REQUEST_URI'], 'settings') !== false ? 'bg-violet-600/10 text-violet-400 border border-violet-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-gear text-xl text-violet-500 group-hover:scale-110 transition-transform"></i> 
            <span class="font-medium">System Settings</span>
        </a>
        <a href="<?= APP_URL ?>reports/system_logs.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= $current_page == 'system_logs.php' ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-mask-happy text-xl text-indigo-400 group-hover:scale-110 transition-transform"></i> 
            <span class="font-medium">Audit Activity Logs</span>
        </a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>help/calculations.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= $current_page == 'calculations.php' ? 'bg-sky-600/10 text-sky-400 border border-sky-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-calculator text-xl text-sky-400 group-hover:scale-110 transition-transform"></i> 
            <span class="font-medium">Calculation Docs</span>
        </a>
    </nav>
    
    </div>
</aside>
<div class="flex-1 flex flex-col overflow-hidden">
    <!-- Topbar -->
    <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 z-10 border-b border-gray-100">
        <div class="flex items-center gap-4">
            <button onclick="toggleSidebar()" class="p-2 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors cursor-pointer" title="Toggle Sidebar">
                <i class="ph ph-list text-2xl"></i>
            </button>
            <h2 class="text-xl font-semibold text-gray-800">
                <?php 
                $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                $parts = explode('/', trim($path, '/'));
                if(count($parts) > 1 && !in_array(end($parts), ['index.php', ''])) {
                     $dir = $parts[count($parts)-2];
                     if($dir != 'nbfc') echo ucfirst($dir) . ' / ';
                }
                $file = basename($_SERVER['PHP_SELF']);
                if($file == 'index.php') {
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
            <div class="relative group">
                <button type="button" onclick="document.getElementById('profileDropdown').classList.toggle('hidden')" class="flex items-center gap-3 hover:bg-gray-50 p-1 rounded-lg transition-colors cursor-pointer">
                    <div class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold shadow-sm">
                        <?= isset($_SESSION['name']) ? strtoupper(substr($_SESSION['name'], 0, 1)) : 'U' ?>
                    </div>
                    <div class="text-left hidden md:block">
                        <p class="text-sm font-bold text-gray-800 leading-tight"><?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'User' ?></p>
                        <p class="text-[10px] text-gray-500 uppercase font-bold tracking-tighter"><?= isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'Role' ?></p>
                    </div>
                    <i class="ph ph-caret-down text-gray-400 text-xs ml-1"></i>
                </button>
                
                <!-- Dropdown Menu -->
                <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden py-1">
                    <div class="px-4 py-2 border-b border-gray-50 md:hidden">
                         <p class="text-sm font-bold text-gray-800 leading-tight"><?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'User' ?></p>
                         <p class="text-[10px] text-gray-500 uppercase font-bold tracking-tighter"><?= isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'Role' ?></p>
                    </div>
                    <a href="<?= APP_URL ?>profile/password.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                        <i class="ph ph-key"></i> Change Password
                    </a>
                    <div class="h-px bg-gray-50 my-1"></div>
                    <a href="javascript:void(0)" onclick="confirmLogout()" class="flex items-center gap-2 px-4 py-2 text-sm text-rose-600 hover:bg-rose-50 transition-colors">
                        <i class="ph ph-sign-out"></i> Logout Session
                    </a>
                </div>
            </div>
        </div>

        <script>
            function confirmLogout() {
                if(confirm('Are you sure you want to terminate this operational session?')) {
                    window.location.href = '<?= APP_URL ?>logout.php';
                }
            }
            // Close dropdown when clicking outside
            window.addEventListener('click', function(e) {
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
