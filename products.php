<?php
require_once 'db.php';
include 'header.php';

// Handle New Product Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $code       = $_POST['product_code'];
    $name       = $_POST['product_name'];
    $category_id= $_POST['category_id'];
    $buy_price  = $_POST['buy_price'];
    $retail_price= $_POST['retail_price'];
    $stock_qty  = $_POST['stock_qty'];

    try {
        $stmt = $pdo->prepare("INSERT INTO products (product_code, product_name, category_id, buy_price, retail_price, stock_qty) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, $name, $category_id, $buy_price, $retail_price, $stock_qty]);
        echo "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>Product added successfully!</div>";
    } catch (PDOException $e) {
        echo "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>Error: " . $e->getMessage() . "</div>";
    }
}

// Fetch Categories for Dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();

// Fetch Products List with Category Names
$query = "SELECT p.*, c.category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          ORDER BY p.id DESC";
$products = $pdo->query($query)->fetchAll();
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Add Product Form -->
    <div class="bg-white p-6 rounded-xl shadow-md h-fit">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Add New Product</h2>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Product Code</label>
                <input type="number" name="product_code" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Product Name</label>
                <input type="text" name="product_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Category</label>
                <select name="category_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-white">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Buy Price (₱)</label>
                    <input type="number" step="0.01" name="buy_price" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Retail Price (₱)</label>
                    <input type="number" step="0.01" name="retail_price" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Stock Quantity</label>
                <input type="number" name="stock_qty" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
            </div>
            <button type="submit" name="add_product" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition font-semibold">
                Save Product
            </button>
        </form>
    </div>

    <!-- Product List Table -->
    <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Inventory List</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Retail</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm">
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td class="px-4 py-3 font-mono text-gray-600"><?= $p['product_code'] ?></td>
                            <td class="px-4 py-3 font-semibold text-gray-900"><?= htmlspecialchars($p['product_name']) ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($p['category_name'] ?: 'Uncategorized') ?></td>
                            <td class="px-4 py-3 text-green-700 font-bold">₱<?= number_format($p['retail_price'], 2) ?></td>
                            <td class="px-4 py-3 font-bold <?= $p['stock_qty'] <= 5 ? 'text-red-600' : 'text-gray-800' ?>">
                                <?= $p['stock_qty'] ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
