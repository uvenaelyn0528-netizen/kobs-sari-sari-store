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
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
        
        <?php if ($role !== 'gcash_incharge'): ?>
            <!-- Store Management Button (Hidden for GCash In-Charge) -->
            <a href="store.php" class="bg-white p-8 rounded-xl shadow-md hover:shadow-xl border-t-4 border-indigo-600 transition transform hover:-translate-y-1 text-center group">
                <div class="text-4xl mb-4">🏪</div>
                <h2 class="text-xl font-bold text-gray-800 group-hover:text-indigo-600 transition">Store Management</h2>
                <p class="text-sm text-gray-500 mt-2">Pamahalaan ang mga produkto, stockouts, at credit list.</p>
            </a>
        <?php else: ?>
            <!-- Restricted Store Placeholder for GCash User -->
            <div class="bg-gray-100 p-8 rounded-xl shadow-sm border-t-4 border-gray-400 text-center opacity-60 cursor-not-allowed">
                <div class="text-4xl mb-4">🏪</div>
                <h2 class="text-xl font-bold text-gray-500">Store Management</h2>
                <p class="text-sm text-gray-400 mt-2">Restricted for GCash In-Charge account.</p>
            </div>
        <?php endif; ?>

        <!-- GCash Section Button (Accessible to Everyone or specific roles) -->
        <a href="gcash.php" class="bg-white p-8 rounded-xl shadow-md hover:shadow-xl border-t-4 border-blue-600 transition transform hover:-translate-y-1 text-center group">
            <div class="text-4xl mb-4">📱</div>
            <h2 class="text-xl font-bold text-gray-800 group-hover:text-blue-600 transition">GCash In-Charge</h2>
            <p class="text-sm text-gray-500 mt-2">Pamahalaan ang mga transaksyon at balanse ng GCash.</p>
        </a>

        <?php if ($role === 'admin'): ?>
            <!-- Admin Only Settings/Users Button -->
            <a href="users.php" class="bg-white p-8 rounded-xl shadow-md hover:shadow-xl border-t-4 border-purple-600 transition transform hover:-translate-y-1 text-center group">
                <div class="text-4xl mb-4">⚙️</div>
                <h2 class="text-xl font-bold text-gray-800 group-hover:text-purple-600 transition">Admin Settings</h2>
                <p class="text-sm text-gray-500 mt-2">Pamahalaan ang mga user at system settings.</p>
            </a>
        <?php endif; ?>

    </div>
</div>

<?php 
// include 'footer.php'; 
?>
