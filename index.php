<?php
session_start();
require_once 'db.php';
include 'header.php';

$role = $_SESSION['role'] ?? 'admin'; 
?>

<div class="container mx-auto px-4 py-12">
    <div class="text-center mb-10">
        <h1 class="text-3xl font-bold text-gray-800">🏪 KOBS Store Dashboard</h1>
        <p class="text-gray-600 mt-2">Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>!</p>
    </div>

    <!-- Main Navigation Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6 max-w-6xl mx-auto">
        
        <?php if ($role !== 'gcash_incharge'): ?>
            <!-- Store Management Button -->
            <a href="store.php" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl border-t-4 border-indigo-600 transition transform hover:-translate-y-1 text-center group">
                <div class="text-4xl mb-3">🏪</div>
                <h2 class="text-lg font-bold text-gray-800 group-hover:text-indigo-600 transition">Store Management</h2>
                <p class="text-xs text-gray-500 mt-1">Produkto, stockouts, at credit list.</p>
            </a>
        <?php else: ?>
            <!-- Restricted Store Placeholder for GCash User -->
            <div class="bg-gray-100 p-6 rounded-xl shadow-sm border-t-4 border-gray-400 text-center opacity-60 cursor-not-allowed">
                <div class="text-4xl mb-3">🏪</div>
                <h2 class="text-lg font-bold text-gray-500">Store Management</h2>
                <p class="text-xs text-gray-400 mt-1">Restricted account.</p>
            </div>
        <?php endif; ?>

        <!-- GCash In-Charge Button -->
        <a href="gcash.php" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl border-t-4 border-blue-600 transition transform hover:-translate-y-1 text-center group">
            <div class="text-4xl mb-3">📱</div>
            <h2 class="text-lg font-bold text-gray-800 group-hover:text-blue-600 transition">GCash In-Charge</h2>
            <p class="text-xs text-gray-500 mt-1">Transaksyon at balanse ng GCash.</p>
        </a>

        <?php if ($role !== 'gcash_incharge'): ?>
            <!-- Lending Button -->
            <a href="lending.php" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl border-t-4 border-green-600 transition transform hover:-translate-y-1 text-center group">
                <div class="text-4xl mb-3">💰</div>
                <h2 class="text-lg font-bold text-gray-800 group-hover:text-green-600 transition">Lending</h2>
                <p class="text-xs text-gray-500 mt-1">Pamahalaan ang cash lending.</p>
            </a>

            <!-- ATM Withdrawal Button -->
            <a href="atm.php" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl border-t-4 border-yellow-600 transition transform hover:-translate-y-1 text-center group">
                <div class="text-4xl mb-3">💳</div>
                <h2 class="text-lg font-bold text-gray-800 group-hover:text-yellow-600 transition">ATM Withdrawal</h2>
                <p class="text-xs text-gray-500 mt-1">Itala ang mga ATM withdrawals.</p>
            </a>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <!-- Admin Settings Button -->
            <a href="users.php" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl border-t-4 border-purple-600 transition transform hover:-translate-y-1 text-center group">
                <div class="text-4xl mb-3">⚙️</div>
                <h2 class="text-lg font-bold text-gray-800 group-hover:text-purple-600 transition">Admin Settings</h2>
                <p class="text-xs text-gray-500 mt-1">Pamahalaan ang mga user.</p>
            </a>
        <?php endif; ?>

    </div>
</div>

<?php 
// include 'footer.php'; 
?>
