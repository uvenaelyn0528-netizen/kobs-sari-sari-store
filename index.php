<?php
require_once 'db.php';
include 'header.php';

// Fetch summary metrics safely
try {
    // Total Products
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $total_products = $stmt->fetchColumn();

    // Total Items in Stock
    $stmt = $pdo->query("SELECT SUM(stock_qty) FROM products");
    $total_stock = $stmt->fetchColumn() ?: 0;

    // Total Revenue & Profit from Sales (if sales table exists)
    $total_revenue = 0;
    $total_profit = 0;
    try {
        $salesStmt = $pdo->query("SELECT SUM(total_amount) as rev, SUM(total_profit) as prof FROM sales");
        $salesData = $salesStmt->fetch(PDO::FETCH_ASSOC);
        if ($salesData) {
            $total_revenue = $salesData['rev'] ?? 0;
            $total_profit = $salesData['prof'] ?? 0;
        }
    } catch (Exception $e) {
        // Sales table might not exist yet
    }

    // Low Stock Alerts (< 5 items)
    $lowStmt = $pdo->query("SELECT * FROM products WHERE stock_qty <= 5 ORDER BY stock_qty ASC");
    $low_stock_products = $lowStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<div class='bg-red-100 text-red-700 p-4 m-4 rounded'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    $total_products = 0;
    $total_stock = 0;
    $total_revenue = 0;
    $total_profit = 0;
    $low_stock_products = [];
}
?>

<div class="container mx-auto px-4 py-8">
    <!-- Top Summary Cards (Stay visible at top) -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-indigo-600">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Products</p>
            <h3 class="text-3xl font-extrabold text-gray-800 mt-2"><?= number_format($total_products) ?></h3>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-blue-600">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Items in Stock</p>
            <h3 class="text-3xl font-extrabold text-gray-800 mt-2"><?= number_format($total_stock) ?></h3>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-emerald-600">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Revenue</p>
            <h3 class="text-3xl font-extrabold text-emerald-700 mt-2">₱<?= number_format($total_revenue, 2) ?></h3>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-amber-600">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Profit</p>
            <h3 class="text-3xl font-extrabold text-amber-700 mt-2">₱<?= number_format($total_profit, 2) ?></h3>
        </div>
    </div>

    <!-- Low Stock Alerts Section with Scrollable Container -->
    <div class="bg-white p-6 rounded-xl shadow-md">
        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            Low Stock Alerts
        </h2>

        <?php if (!empty($low_stock_products)): ?>
            <div class="max-h-[450px] overflow-y-auto overflow-x-auto relative">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product Code</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remaining Qty</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm">
                        <?php foreach ($low_stock_products as $item): ?>
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-indigo-600"><?= htmlspecialchars($item['product_code'] ?? '-') ?></td>
                                <td class="px-4 py-3 font-semibold text-gray-900"><?= htmlspecialchars($item['product_name']) ?></td>
                                <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($item['category'] ?? 'Uncategorized') ?></td>
                                <td class="px-4 py-3 font-bold text-red-600"><?= htmlspecialchars($item['stock_qty']) ?> <?= htmlspecialchars($item['um'] ?? '') ?></td>
                                <td class="px-4 py-3">
                                    <a href="products.php?edit=<?= urlencode($item['id']) ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1 rounded shadow">Restock</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-sm text-gray-500 bg-gray-50 p-4 rounded-lg">All products have sufficient stock levels! 👍</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
