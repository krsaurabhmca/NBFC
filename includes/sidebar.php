<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="w-64 bg-slate-900 text-white flex flex-col shadow-xl">
    <div class="h-16 flex items-center justify-center border-b border-slate-700">
        <h1 class="text-xl font-bold tracking-wider flex items-center gap-2">
            <i class="ph ph-bank text-indigo-400 text-2xl"></i> NBFC <span class="text-indigo-400">Core</span>
        </h1>
    </div>
    
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
        <a href="/nbfc/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?= $current_page == 'index.php' ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-squares-four text-xl"></i> Dashboard
        </a>
        
        <div class="pt-4 pb-1">
            <p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Members</p>
        </div>
        <a href="/nbfc/members/add.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?= $current_page == 'add.php' && strpos($_SERVER['REQUEST_URI'], 'members') !== false ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-user-plus text-xl"></i> Add Member
        </a>
        <a href="/nbfc/members/list.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?= $current_page == 'list.php' && strpos($_SERVER['REQUEST_URI'], 'members') !== false ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-users rounded-lg text-xl"></i> Member Directory
        </a>

        <div class="pt-4 pb-1">
            <p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Accounts</p>
        </div>
        <a href="/nbfc/accounts/open.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?= $current_page == 'open.php' ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-folder-plus text-xl"></i> Open Account
        </a>
        <a href="/nbfc/accounts/view.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?= $current_page == 'view.php' ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-folders text-xl"></i> View Accounts
        </a>
        <a href="/nbfc/accounts/closure.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?= $current_page == 'closure.php' ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-lock-key-open text-xl"></i> Account Closure
        </a>

        <div class="pt-4 pb-1">
            <p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Transactions</p>
        </div>
        <a href="/nbfc/transactions/process.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?= $current_page == 'process.php' && strpos($_SERVER['REQUEST_URI'], 'transactions') !== false ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-arrows-left-right text-xl"></i> Process TXN
        </a>

        <div class="pt-4 pb-1">
            <p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">System</p>
        </div>
        <a href="/nbfc/reports/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?= $current_page == 'index.php' && strpos($_SERVER['REQUEST_URI'], 'reports') !== false ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-chart-line-up text-xl"></i> Reports
        </a>
        
        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
        <a href="/nbfc/users/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?= $current_page == 'index.php' && strpos($_SERVER['REQUEST_URI'], 'users') !== false ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-shield-check text-xl"></i> Staff & Users
        </a>
        <a href="/nbfc/settings/general.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?= in_array($current_page, ['general.php', 'interest_rates.php', 'templates.php']) && strpos($_SERVER['REQUEST_URI'], 'settings') !== false ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i class="ph ph-gear text-xl"></i> Settings
        </a>
        <?php endif; ?>
    </nav>
    
    <div class="px-4 py-2 border-t border-slate-700 flex flex-col gap-2 relative">
        <a href="/nbfc/profile/password.php" class="flex items-center justify-center gap-2 w-full py-2 px-4 bg-slate-800 text-slate-300 hover:bg-slate-700 hover:text-white rounded-lg transition-colors text-sm font-medium">
            <i class="ph ph-key text-lg"></i> Password
        </a>
        <a href="/nbfc/logout.php" class="flex items-center justify-center gap-2 w-full py-2 px-4 bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition-colors text-sm font-medium">
            <i class="ph ph-sign-out text-lg"></i> Logout
        </a>
    </div>
</aside>
<div class="flex-1 flex flex-col overflow-hidden">
    <!-- Topbar -->
    <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 z-10 border-b border-gray-100">
        <div class="flex items-center gap-4">
            <h2 class="text-xl font-semibold text-gray-800">
                <?php 
                $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                $parts = explode('/', trim($path, '/'));
                if(count($parts) > 1 && $parts[1] != 'index.php') echo ucfirst($parts[1]) . ' / ';
                echo ucwords(str_replace(['.php', '_'], ['', ' '], basename($_SERVER['PHP_SELF'])));
                ?>
            </h2>
        </div>
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold">
                    <?= isset($_SESSION['name']) ? substr($_SESSION['name'], 0, 1) : 'U' ?>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-700"><?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'User' ?></p>
                    <p class="text-xs text-gray-500 capitalize"><?= isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'Role' ?></p>
                </div>
            </div>
        </div>
    </header>
    <!-- Main Content Area -->
    <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
