<?php
session_start();
require_once 'db.php';
include 'header.php';

// Get current user role
$role = strtolower(trim($_SESSION['role'] ?? 'admin'));
$is_admin = ($role === 'admin');
$is_gcash_user = ($role === 'gcash_incharge');

// Restrict processing strictly to Admin and GCash In-Charge
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_admin && !$is_gcash_user) {
    echo "<script>alert('Access Denied: You are in viewer mode only and cannot process transactions.'); window.location='gcash.php';</script>";
    exit;
}
?>

<div class="container mx-auto px-4 py-8">
    <!-- Top Bar -->
    <div class="flex justify-between items-center max-w-5xl mx-auto mb-6">
        <a href="index.php" class="inline-flex items-center gap-2 bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-semibold shadow-sm transition text-sm">
            &larr; Back to Dashboard
        </a>
        <span class="bg-blue-100 text-blue-700 text-xs font-bold px-3 py-1 rounded-full uppercase">Role: <?= htmlspecialchars($role) ?></span>
    </div>

    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">📱 GCash Management</h1>
        <p class="text-gray-600 mt-1">Pamahalaan at tingnan ang mga transaksyon at balanse ng GCash.</p>
    </div>

    <!-- Notice for Tindera / Viewers -->
    <?php if (!$is_admin && !$is_gcash_user): ?>
        <div class="max-w-5xl mx-auto mb-6 bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-lg shadow-sm">
            <div class="flex items-center">
                <span class="text-2xl mr-3">👀</span>
                <div>
                    <p class="text-sm font-bold text-amber-800">Viewer Mode Active</p>
                    <p class="text-xs text-amber-700">Maaari mong tingnan ang mga transaksyon sa ibaba, ngunit ang paggawa ng transaksyon ay nakalaan lamang para sa GCash In-Charge at Admin.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Transaction Form Section (Allowed for Admin and GCash In-Charge) -->
    <?php if ($is_admin || $is_gcash_user): ?>
        <div class="max-w-5xl mx-auto bg-white p-6 rounded-xl shadow-md mb-8 border-t-4 border-blue-600">
            <h2 class="text-xl font-bold text-gray-800 mb-4">New GCash Transaction</h2>
            <form action="process_gcash.php" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Transaction Type</label>
                    <select name="type" required class="w-full border rounded-lg p-2 text-sm focus:ring focus:ring-blue-300">
                        <option value="Cash In">Cash In</option>
                        <option value="Cash Out">Cash Out</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Amount (₱)</label>
                    <input type="number" step="0.01" name="amount" required placeholder="0.00" class="w-full border rounded-lg p-2 text-sm focus:ring focus:ring-blue-300">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition shadow-sm">
                        Process Transaction
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Records / History Table -->
    <div class="max-w-5xl mx-auto bg-white rounded-xl shadow-md overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b flex justify-between items-center">
            <h3 class="font-bold text-gray-700">GCash Transaction History</h3>
            <span class="text-xs text-gray-500">Edit & Delete restricted to Admin</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Processed By</th>
                        <!-- Actions column is shown to everyone, but buttons inside are role-checked -->
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 text-sm text-gray-700">
                    <!-- Example Row / Loop -->
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">2026-08-06 17:15</td>
                        <td class="px-6 py-4 whitespace-nowrap font-semibold text-green-600">Cash In</td>
                        <td class="px-6 py-4 whitespace-nowrap font-bold">₱500.00</td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-500">gcash_user</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <?php if ($is_admin): ?>
                                <!-- Only Admin can see/use Edit and Delete -->
                                <a href="edit_gcash.php?id=1" class="text-indigo-600 hover:text-indigo-900 mr-2">Edit</a>
                                <a href="delete_gcash.php?id=1" class="text-red-600 hover:text-red-900" onclick="return confirm('Delete this transaction?');">Delete</a>
                            <?php else: ?>
                                <!-- GCash User and Tindera see a restricted badge instead of action buttons -->
                                <span class="text-gray-400 italic text-xs bg-gray-100 px-2 py-1 rounded">No Permission</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
// include 'footer.php'; 
?>
