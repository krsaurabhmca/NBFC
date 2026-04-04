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
