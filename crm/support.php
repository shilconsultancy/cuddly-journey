<?php
// coming-soon.php

// Include the global header. This starts the session and checks for a valid login.
require_once '../templates/header.php';

// Set the title for this specific page.
$page_title = "Feature Coming Soon - BizManager";
?>

<!-- This title tag will be placed within the <head> of the document by the header template. -->
<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col items-center justify-center h-full">
    <div class="glass-card p-8 md:p-12 text-center">
        <div class="mx-auto mb-6 p-4 bg-indigo-100/50 rounded-full w-24 h-24 flex items-center justify-center backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <h2 class="text-3xl font-bold text-gray-800 mb-2">Coming Soon!</h2>
        <p class="text-gray-600 max-w-sm mb-8">
            This feature is currently under construction. We're working hard to bring it to you as soon as possible.
        </p>
        <a href="index.php" class="inline-block px-6 py-3 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-700 transition-colors">
            &larr; Back to CRM
        </a>
    </div>
</div>

<?php
// Include the global footer.
require_once 'templates/footer.php';
?>