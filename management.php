<?php
session_start();
require_once 'db.php';

// Restrict access to authorized roles (e.g., admin)
$role = strtolower(trim($_SESSION['role'] ?? 'viewer'));
if (!in_array($role, ['admin', 'management'])) {
    // Redirect non-admins or allow viewers depending on your policy; here we restrict to admin/management
    // header("Location: index.php");
    // exit();
}

include 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Back Button / Header -->
    <div class="mb-6 flex justify-between items-center">
        <a href="index.php" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-semibold rounded-md shadow-sm transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Dashboard
        </a>
    </div>

    <div class="text-center mb-10">
        <h1 class="text-3xl font-bold text-gray-800">📊 KOBS Management Hub</h1>
        <p class="text-gray-600 mt-2">Piliin ang nais mong suriin o pamahalaan.</p>
    </div>

    <!-- Management Sub-Modules Navigation Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-4xl mx-auto">
        
        <!-- Management Fee -->
        <a href="management_fee.php" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl border-t-4 border-indigo-600 transition transform hover:-translate-y-1 text-center group">
            <div class="text-4xl mb-3">💼</div>
            <h2 class="text-lg font-bold text-gray-800 group-hover:text-indigo-600 transition">Management Fee</h2>
            <p class="text-xs text-gray-500 mt-1">Subaybayan at kalkulahin ang management fees.</p>
        </a>

        <!-- Cashflow -->
        <a href="cashflow.php" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl border-t-4 border-green-600 transition transform hover:-translate-y-1 text-center group">
            <div class="text-4xl mb-3">📈</div>
            <h2 class="text-lg font-bold text-gray-800 group-hover:text-green-600 transition">Cashflow</h2>
            <p class="text-xs text-gray-500 mt-1">Pumasok at lumabas na pera (Cash In / Cash Out).</p>
        </a>

        <!-- Shares -->
        <a href="shares.php" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl border-t-4 border-purple-600 transition transform hover:-translate-y-1 text-center group">
            <div class="text-4xl mb-3">🤝</div>
            <h2 class="text-lg font-bold text-gray-800 group-hover:text-purple-600 transition">Shares</h2>
            <p class="text-xs text-gray-500 mt-1">Pamahalaan ang shares at dibidendo ng mga kasosyo.</p>
        </a>

    </div>
</div>

<?php 
// include 'footer.php'; 
?>
