<?php
// includes/header.php
if(session_status() !== PHP_SESSION_ACTIVE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(getSetting($conn, 'bank_name') ?: 'NBFC Core') ?> - Banking System</title>
    <!-- Tailwind CSS for styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Phosphor Icons for premium UI -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6; /* Tailwind gray-100 */
        }
        
        /* Select2 Tailwind Override */
        .select2-container--default .select2-selection--single {
            height: 2.6rem;
            border-color: #d1d5db;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 2.6rem;
        }

        /* Transparent Ghost Scrollbar for Sidebar */
        aside nav::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        aside nav::-webkit-scrollbar-track {
            background: transparent;
        }
        aside nav::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.05); /* Very subtle */
            border-radius: 20px;
        }
        aside nav:hover::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2); /* Slightly visible on hover */
        }
        
        /* Firefox Support */
        aside nav {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.0) transparent;
        }
        aside nav:hover {
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
        }
        
        /* Sidebar Toggle Styles */
        body.sidebar-collapsed aside {
            display: none !important;
        }

        /* Overall Compact UI Tweaks */
        body { font-size: 0.875rem; }
        h1 { font-size: 1.25rem !important; }
        
        .max-w-6xl, .max-w-7xl, .max-w-5xl, .max-w-4xl {
            padding-left: 1rem;
            padding-right: 1rem;
        }

        /* Table Tightening */
        table th {
            font-size: 0.7rem !important;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.5rem 1rem !important;
            background: #f8fafc;
            color: #64748b;
        }
        table td {
            padding: 0.4rem 1rem !important;
            font-size: 0.85rem;
        }
        
        /* Card refinements */
        .bg-white.rounded-2xl {
            border-radius: 0.75rem !important;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05) !important;
        }
        .p-8 { padding: 1.25rem !important; }
        .p-6, .p-5 { padding: 1rem !important; }
        .gap-6 { gap: 0.75rem !important; }
        .mb-6, .mb-5 { margin-bottom: 0.75rem !important; }
        .space-y-6 > * + * { margin-top: 0.75rem !important; }
        .space-y-4 > * + * { margin-top: 0.5rem !important; }
        
        /* Form Tightening */
        label { 
            font-size: 0.75rem !important; 
            font-weight: 600 !important;
            color: #475569 !important;
        }
        input, select, textarea {
            padding: 0.375rem 0.75rem !important;
            font-size: 0.85rem !important;
            border-radius: 0.5rem !important;
            border-color: #e2e8f0 !important;
        }
        
        button {
            border-radius: 0.5rem !important;
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
            font-size: 0.85rem !important;
        }

        /* Sidebar refinements */
        aside { width: 14rem !important; }
        aside a { font-size: 0.85rem !important; padding: 0.375rem 0.75rem !important; gap: 0.5rem !important; }
        aside .ph { font-size: 1.1rem !important; }
    </style>
    <script>
        // Apply sidebar state immediately to prevent flicker
        if (localStorage.getItem('sidebarState') === 'collapsed') {
            document.documentElement.classList.add('sidebar-collapsed-init');
            window.addEventListener('DOMContentLoaded', () => {
                document.body.classList.add('sidebar-collapsed');
            });
        }
    </script>
</head>
<body class="flex h-screen overflow-hidden text-gray-800">

<?php if(isset($_SESSION['admin_user_id'])): ?>
<div class="fixed top-0 left-0 right-0 z-[100] bg-amber-600 text-white px-4 py-1.5 flex items-center justify-between shadow-lg">
    <div class="flex items-center gap-3">
        <i class="ph ph-mask-happy text-xl animate-bounce"></i>
        <span class="text-xs font-bold uppercase tracking-widest">System Mode: Logged in as <span class="underline decoration-2"><?= htmlspecialchars($_SESSION['name']) ?></span> (<?= htmlspecialchars($_SESSION['role']) ?>)</span>
    </div>
    <a href="<?= APP_URL ?>users/impersonate.php?revert=1" class="bg-white/20 hover:bg-white/30 text-white px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all border border-white/30">
        Exit & Back to Admin
    </a>
</div>
<style>
    /* Adjust top offset when impersonating */
    aside, .flex-1 { margin-top: 2rem; }
    header { top: 2rem; }
</style>
<?php endif; ?>
