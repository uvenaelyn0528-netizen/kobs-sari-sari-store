<?php
session_start();
require_once 'db.php';
include 'header.php';

$role = strtolower(trim($_SESSION['role'] ?? 'admin')); 
?>

<div class="container mx-auto px-4 py-12">
    <div class="text-center mb-10">
        <h1 class="text-3xl font-bold text-gray-800">🏪 KOBS Store Dashboard</h1>
        <p class="text-gray-600 mt-2">Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>! (Role: <span class="capitalize font-semibold"><?= htmlspecialchars($role) ?></span>)</p>
    </div>

    <!-- Main Navigation Grid - All buttons clickable for everyone -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6 max-w-6xl mx-auto">
        
        <!-- Store Management -->
        <a href="store.php" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl border-t-4 border-indigo-600 transition transform hover:-translate-y-1 text-center group">
            <div class="text-4xl mb-3">🏪</div>
            <h2 class="text-lg font-bold text-gray-800 group-hover:text-indigo-600 transition">KOBS Store</h2>
            <p class="text-xs text-gray-500 mt-1">Produkto, stockouts, at credit list.</p>
        </a>

        <!-- GCash In-Charge -->
        <a href="gcash.php" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl border-t-4 border-blue-600 transition transform hover:-translate-y-1 text-center group">
            <div class="text-4xl mb-3">📱</div>
            <h2 class="text-lg font-bold text-gray-800 group-hover:text-blue-600 transition">KOBS GCash</h2>
            <p class="text-xs text-gray-500 mt-1">Transaksyon at balanse ng GCash.</p>
        </a>

        <!-- Lending -->
        <a href="lending.php" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl border-t-4 border-green-600 transition transform hover:-translate-y-1 text-center group">
            <div class="text-4xl mb-3">💰</div>
            <h2 class="text-lg font-bold text-gray-800 group-hover:text-green-600 transition">KOBS Lending</h2>
            <p class="text-xs text-gray-500 mt-1">Pamahalaan ang cash lending.</p>
        </a>

        <!-- ATM Withdrawal -->
        <a href="atm.php" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl border-t-4 border-yellow-600 transition transform hover:-translate-y-1 text-center group">
            <div class="text-4xl mb-3">💳</div>
            <h2 class="text-lg font-bold text-gray-800 group-hover:text-yellow-600 transition">KOBS ATM Withdrawal</h2>
            <p class="text-xs text-gray-500 mt-1">Itala ang mga ATM withdrawals.</p>
        </a>

        <!-- Admin Settings -->
        <a href="users.php" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl border-t-4 border-purple-600 transition transform hover:-translate-y-1 text-center group">
            <div class="text-4xl mb-3">⚙️</div>
            <h2 class="text-lg font-bold text-gray-800 group-hover:text-purple-600 transition">KOBS Admin Settings</h2>
            <p class="text-xs text-gray-500 mt-1">Pamahalaan ang mga user.</p>
        </a>

    </div>
</div>

<?php 
// include 'footer.php'; 
?>
