<?php
// templates/header.php

// Include the new global configuration file.
// This is the correct place for this. It's included by every secure page.
require_once __DIR__ . '/../config.php';

// --- LOGOUT FUNCTIONALITY ---
if (isset($_GET['logout'])) {
    $_SESSION = array();
    session_destroy();
    header("location: index.php"); // Redirect to login page after logout
    exit;
}

// --- SECURITY CHECK ---
// Get the name of the current script
$current_page = basename($_SERVER['PHP_SELF']);

// If user is not logged in AND they are not already on the login page, redirect them.
if (!isset($_SESSION['user_id']) && $current_page != 'index.php') {
    header("Location: /git/cuddly-journey/"); // Adjust this path to your project's root login page if needed
    exit();
}

// --- MAINTENANCE MODE CHECK ---
if (isset($app_config['maintenance_mode']) && $app_config['maintenance_mode'] == '1' && ($_SESSION['user_role_id'] ?? 0) != 1) {
    die('
        <div style="font-family: sans-serif; text-align: center; padding: 50px;">
            <h1>Under Maintenance</h1>
            <p>Our system is currently down for scheduled maintenance. Please check back soon.</p>
        </div>
    ');
}

// Get user's name from session.
$user_full_name = $_SESSION['user_full_name'] ?? 'User';
$user_profile_image = $_SESSION['user_profile_image'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($app_config['company_favicon_url'] ?? ''); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f4ff 0%, #e6f0ff 100%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }
        .glass-header {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: rgba(241, 245, 249, 0.5); }
        ::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.5); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(100, 116, 139, 0.5); }
        .app-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .app-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body class="min-h-screen">

    <div class="flex flex-col h-screen">
        <header class="glass-header flex justify-between items-center p-4 sticky top-0 z-40">
            <div class="flex items-center">
                <a href="#" class="text-2xl font-bold text-gray-800 ml-2">
                    <?php echo htmlspecialchars($app_config['company_name'] ?? 'BizManager'); ?>
                </a>
            </div>
            
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <input type="text" placeholder="Search apps..." 
                           class="w-full md:w-64 px-4 py-2 bg-white/70 border border-white/30 rounded-full text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300/50 focus:border-indigo-300/50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 absolute right-3 top-2.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                
                <button class="p-2 bg-white/70 rounded-full hover:bg-white border border-white/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </button>
                
                <div class="relative">
                    <button id="user-menu-button" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                        <img class="h-8 w-8 rounded-full object-cover" 
                             src="<?php echo htmlspecialchars(!empty($user_profile_image) ? $user_profile_image : 'https://placehold.co/100x100/6366f1/white?text=' . strtoupper(substr($user_full_name, 0, 1))); ?>" 
                             alt="User avatar">
                    </button>
                    <div id="user-menu" class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white/90 backdrop-blur-md ring-1 ring-black/5 focus:outline-none">
                        <div class="px-4 py-2 text-sm text-gray-700">
                            <p class="font-semibold"><?php echo htmlspecialchars($user_full_name); ?></p>
                        </div>
                        <div class="border-t border-gray-200/50 my-1"></div>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100/50">Your Profile</a>
                        <a href="settings/" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100/50">Settings</a>
                        <div class="border-t border-gray-200/50 my-1"></div>
                        <a href="?logout=true" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100/50">Sign out</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-x-hidden overflow-y-auto p-6">