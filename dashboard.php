<?php
// dashboard.php

// Include the global header. This starts the session, connects to the DB,
// loads the config, and checks for a valid login.
require_once 'templates/header.php';

// Set the title for this specific page.
$page_title = "Dashboard - " . ($app_config['company_name'] ?? 'BizManager');
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="glass-card p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-800 mb-1">Welcome back, <?php echo htmlspecialchars(explode(' ', $user_full_name)[0]); ?></h2>
    <p class="text-gray-600">Here are your business applications</p>
</div>

<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-7 gap-6">
    
    <?php // --- PERMISSION CHECK for Sales ---
    if (check_permission('Sales', 'view')): ?>
    <a href="sales/" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-indigo-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Sales</h3>
    </a>
    <?php endif; ?>

    <?php if (check_permission('Products', 'view')): ?>
    <a href="products/" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-orange-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Products</h3>
    </a>
    <?php endif; ?>
    
    <?php // --- PERMISSION CHECK for Warehouses ---
    if (check_permission('Warehouses', 'view')): ?>
    <a href="warehouses/" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-blue-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 010 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Warehouses</h3>
    </a>
    <?php endif; ?>
    
    <?php // --- PERMISSION CHECK for Procurement ---
    if (check_permission('Procurement', 'view')): ?>
    <a href="procurement/" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-green-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2-2h8a1 1 0 001-1z"/></svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Procurement</h3>
    </a>
    <?php endif; ?>

    <?php // --- PERMISSION CHECK for Shops ---
    if (check_permission('Shops', 'view')): ?>
    <a href="shops/" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-yellow-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Shops</h3>
    </a>
    <?php endif; ?>

    <?php // --- PERMISSION CHECK for Accounts ---
    if (check_permission('Accounts', 'view')): ?>
    <a href="accounts/" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-red-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8h6m-5 4h4m5 6H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Accounts</h3>
    </a>
    <?php endif; ?>

    <?php // --- PERMISSION CHECK for Users ---
    if (check_permission('Users', 'view')): ?>
    <a href="users/" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-purple-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M15 21v-1a6 6 0 00-5.176-5.97M15 21h6v-1a6 6 0 00-9-5.197"/></svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Users</h3>
    </a>
    <?php endif; ?>

    <?php // --- PERMISSION CHECK for Reports ---
    if (check_permission('Reports', 'view')): ?>
    <a href="reports/" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-pink-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-pink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Reports</h3>
    </a>
    <?php endif; ?>

    <?php // --- PERMISSION CHECK for Marketing ---
    if (check_permission('Marketing', 'view')): ?>
    <a href="marketing/" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-teal-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-teal-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Marketing</h3>
    </a>
    <?php endif; ?>

    <?php // --- PERMISSION CHECK for CRM ---
    if (check_permission('CRM', 'view')): ?>
    <a href="crm/" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-orange-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">CRM</h3>
    </a>
    <?php endif; ?>

    <?php // --- PERMISSION CHECK for HR ---
    if (check_permission('HR', 'view')): ?>
    <a href="hr/" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-cyan-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-cyan-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">HR</h3>
    </a>
    <?php endif; ?>

    <?php // --- PERMISSION CHECK for POS ---
    if (check_permission('POS', 'view')): ?>
    <a href="pos/" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-lime-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-lime-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
            </svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">POS</h3>
    </a>
    <?php endif; ?>

    <?php // --- PERMISSION CHECK for Projects ---
    if (check_permission('Projects', 'view')): ?>
    <a href="projects/" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-fuchsia-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-fuchsia-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Projects</h3>
    </a>
    <?php endif; ?>

    <?php // --- PERMISSION CHECK for Communication ---
    if (check_permission('Communication', 'view')): ?>
    <a href="communication/" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-rose-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Communication</h3>
    </a>
    <?php endif; ?>
    
    <?php // --- PERMISSION CHECK for Settings ---
    if (check_permission('Settings', 'view')): ?>
    <a href="settings/" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-gray-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Settings</h3>
    </a>
    <?php endif; ?>
    
</div>

<?php
// Include the global footer.
require_once 'templates/footer.php';
?>