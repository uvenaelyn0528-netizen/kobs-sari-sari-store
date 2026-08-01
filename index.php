<?php
require_once 'db.php';
include 'header.php';

// Fetch summary metrics
try {
    $totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $totalStock    = $pdo->query("SELECT SUM(stock_qty) FROM products")->fetchColumn() ?: 0;
    $totalSales    = $pdo->query("SELECT SUM(total_amount) FROM transactions")->fetchColumn() ?: 0;
    $totalProfit   = $pdo->query("SELECT SUM(total_profit) FROM transactions")->fetchColumn() ?: 0;

    // Fetch low stock items (qty <= 5)
    $lowStockStmt  = $pdo->query("SELECT product_name, stock_qty FROM products WHERE stock_qty <= 5 ORDER BY stock_qty ASC");
    $lowStockItems = $lowStockStmt->fetchAll();
} catch (PDOException $e) {
    echo "<div class='bg-red-100 text-red-700 p-4 rounded mb-4'>Error: " . $e->getMessage() . "</div>";
}
?>

<!-- Metrics Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-indigo-500">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Products</p>
        <p class="text-3xl font-extrabold text-gray-800 mt-2"><?= number_format($totalProducts) ?></p>
    </div>
    
    <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-blue-500">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Items in Stock</p>
        <p class="text-3xl font-extrabold text-gray-800 mt-2"><?= number_format($totalStock) ?></p>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-green-500">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Revenue</p>
        <p class="text-3xl font-extrabold text-green-600 mt-2">₱<?= number_format($totalSales, 2) ?></p>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-emerald-500">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Profit</p>
        <p class="text-3xl font-extrabold text-emerald-600 mt-2">₱<?= number_format($totalProfit, 2) ?></p>
    </div>
</div>

<!-- Low Stock Alert Section -->
<div class="bg-white rounded-xl shadow-md p-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
        <i class="fa-solid fa-triangle-exclamation text-yellow-500 mr-2"></i> Low Stock Alerts
    </h2>

    <?php if (!empty($lowStockItems)): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining Stock</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($lowStockItems as $item): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($item['product_name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 font-bold"><?= $item['stock_qty'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    Restock Needed
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-gray-500 text-sm">All products have sufficient stock levels! 👍</p>
    <?php endif; ?>
</div>

</body>
</html>
