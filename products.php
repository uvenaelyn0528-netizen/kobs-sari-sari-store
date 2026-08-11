<?php
session_start();
require_once 'db.php';

// Allow 'admin', 'tindera', and 'viewer' to open the page
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'tindera', 'viewer'])) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role'];
$is_viewer = ($user_role === 'viewer');

include 'header.php';

$edit_mode = false;
$edit_product = null;
$message = '';
$message_type = '';

// Handle Delete Product (Blocked for viewers)
if (!$is_viewer && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $del_id = $_POST['original_id'] ?? '';
    if (!empty($del_id)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$del_id]);
            $message = "Product deleted successfully!";
            $message_type = "success";
            $edit_mode = false;
            $edit_product = null;
        } catch (PDOException $e) {
            $message = "Error deleting product: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Handle Add Product Submission (Blocked for viewers)
if (!$is_viewer && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $code          = trim($_POST['product_code'] ?? '');
    $name          = $_POST['product_name'] ?? '';
    $category      = $_POST['category'] ?? '';
    $um            = $_POST['um'] ?? 'pc';
    $buy_price     = $_POST['buy_price'] ?? 0;
    $retail_price  = $_POST['retail_price'] ?? 0;
    $stock_in      = $_POST['stock_in'] ?? 0;
    $stock_out     = $_POST['stock_out'] ?? 0;
    $stock_qty     = $stock_in - $stock_out; // Calculate remaining directly

    try {
        $stmt = $pdo->prepare('INSERT INTO products (product_code, product_name, category, um, "Stock_in", "Stock_out", stock_qty, buy_price, retail_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$code !== '' ? $code : null, $name, $category, $um, $stock_in, $stock_out, $stock_qty, $buy_price, $retail_price]);
        $message = "Product added successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle Update Product Submission (Blocked for viewers)
if (!$is_viewer && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $code          = trim($_POST['product_code'] ?? '');
    $name          = $_POST['product_name'] ?? '';
    $category      = $_POST['category'] ?? '';
    $um            = $_POST['um'] ?? 'pc';
    $buy_price     = $_POST['buy_price'] ?? 0;
    $retail_price  = $_POST['retail_price'] ?? 0;
    $stock_in      = $_POST['stock_in'] ?? 0;
    $stock_out     = $_POST['stock_out'] ?? 0;
    $stock_qty     = $stock_in - $stock_out; // Calculate remaining directly
    $original_id   = $_POST['original_id'] ?? '';

    try {
        $stmt = $pdo->prepare('UPDATE products SET product_code = ?, product_name = ?, category = ?, um = ?, "Stock_in" = ?, "Stock_out" = ?, stock_qty = ?, buy_price = ?, retail_price = ? WHERE id = ?');
        $stmt->execute([$code !== '' ? $code : null, $name, $category, $um, $stock_in, $stock_out, $stock_qty, $buy_price, $retail_price, $original_id]);
        $message = "Product updated successfully!";
        $message_type = "success";
        $edit_mode = false;
        $edit_product = null;
    } catch (PDOException $e) {
        $message = "Error updating product: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle Edit Trigger via URL (Blocked for viewers)
if (!$is_viewer && isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    try {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$edit_id]);
        $edit_product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($edit_product) {
            $edit_mode = true;
        }
    } catch (PDOException $e) {
        $edit_product = null;
    }
}

// Fetch unique categories
try {
    $catStmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category ASC");
    $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}

// Fetch Products List with dynamic transaction sums if applicable
try {
    $stmt = $pdo->query('SELECT * FROM products ORDER BY product_name ASC');
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching products: " . $e->getMessage();
    $message_type = "error";
    $products = [];
}
?>

<div class="container mx-auto px-4 py-8">
    <!-- Back Button -->
    <div class="mb-6">
        <a href="javascript:history.back()" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-semibold rounded-md shadow-sm transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back
        </a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($is_viewer): ?>
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 text-blue-700 rounded-lg text-sm">
            <strong>Viewer Mode:</strong> You are logged in with a viewer account. You can view the product inventory list, but transaction forms and modifications are restricted.
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Add / Edit Product Form -->
        <div class="bg-white p-6 rounded-xl shadow-md h-fit">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold text-gray-800">
                    <?= $edit_mode ? 'Edit Product' : 'Add New Product' ?>
                </h2>
                <?php if ($edit_mode): ?>
                    <a href="products.php" class="text-xs bg-gray-200 hover:bg-gray-300 text-gray-700 px-2 py-1 rounded font-semibold transition">Cancel Edit</a>
                <?php endif; ?>
            </div>

            <form method="POST" class="space-y-4" id="productForm">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="original_id" value="<?= htmlspecialchars($edit_product['id'] ?? '') ?>">
                <?php endif; ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Product Code / Barcode</label>
                    <input type="text" id="product_code_input" name="product_code" <?= $is_viewer ? 'disabled' : '' ?> value="<?= htmlspecialchars($edit_product['product_code'] ?? '') ?>" placeholder="Scan or type barcode" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-indigo-500 focus:border-indigo-500 <?= $is_viewer ? 'bg-gray-100 cursor-not-allowed' : '' ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Product Name</label>
                    <input type="text" id="product_name_input" name="product_name" required <?= $is_viewer ? 'disabled' : '' ?> value="<?= htmlspecialchars($edit_product['product_name'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border <?= $is_viewer ? 'bg-gray-100 cursor-not-allowed' : '' ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Category</label>
                    <input type="text" id="category_input" name="category" list="category_list" required <?= $is_viewer ? 'disabled' : '' ?> value="<?= htmlspecialchars($edit_product['category'] ?? '') ?>" placeholder="Select or type category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-white <?= $is_viewer ? 'bg-gray-100 cursor-not-allowed' : '' ?>">
                    <datalist id="category_list">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">UM (Unit of Measure)</label>
                    <input type="text" id="um_input" name="um" required <?= $is_viewer ? 'disabled' : '' ?> value="<?= htmlspecialchars($edit_product['um'] ?? 'pc') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border <?= $is_viewer ? 'bg-gray-100 cursor-not-allowed' : '' ?>">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Buy Price (₱)</label>
                        <input type="number" step="0.01" id="buy_price_input" name="buy_price" required <?= $is_viewer ? 'disabled' : '' ?> value="<?= htmlspecialchars($edit_product['buy_price'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border <?= $is_viewer ? 'bg-gray-100 cursor-not-allowed' : '' ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Retail Price (₱)</label>
                        <input type="number" step="0.01" id="retail_price_input" name="retail_price" required <?= $is_viewer ? 'disabled' : '' ?> value="<?= htmlspecialchars($edit_product['retail_price'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border <?= $is_viewer ? 'bg-gray-100 cursor-not-allowed' : '' ?>">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Stock In</label>
                        <input type="number" id="stock_in_input" name="stock_in" required <?= $is_viewer ? 'disabled' : '' ?> value="<?= htmlspecialchars($edit_product['Stock_in'] ?? 0) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border <?= $is_viewer ? 'bg-gray-100 cursor-not-allowed' : '' ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Stock Out</label>
                        <input type="number" id="stock_out_input" name="stock_out" required <?= $is_viewer ? 'disabled' : '' ?> value="<?= htmlspecialchars($edit_product['Stock_out'] ?? 0) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border <?= $is_viewer ? 'bg-gray-100 cursor-not-allowed' : '' ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Remaining Qty</label>
                        <input type="number" id="stock_qty_input" name="stock_qty" required <?= $is_viewer ? 'disabled' : '' ?> value="<?= htmlspecialchars($edit_product['stock_qty'] ?? 0) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border <?= $is_viewer ? 'bg-gray-100 cursor-not-allowed' : '' ?>">
                    </div>
                </div>

                <!-- Form Action Buttons -->
                <?php if (!$is_viewer): ?>
                    <div class="mt-6 pt-4 border-t border-gray-200">
                        <?php if ($edit_mode): ?>
                            <div class="space-y-2">
                                <button type="submit" name="update_product" class="w-full bg-emerald-600 text-white py-2 px-4 rounded-md hover:bg-emerald-700 transition font-semibold text-center shadow">
                                    Update Product
                                </button>
                                <div class="flex gap-2">
                                    <button type="submit" name="delete_product" onclick="return confirm('Are you sure you want to delete this product?');" class="w-1/2 bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 transition font-semibold text-center shadow">
                                        Delete
                                    </button>
                                    <a href="products.php" class="w-1/2 bg-gray-500 text-white py-2 px-4 rounded-md text-center hover:bg-gray-600 transition font-semibold shadow flex items-center justify-center">
                                        Cancel
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div>
                                <button type="submit" name="add_product" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition font-semibold shadow">
                                    Save Product
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="mt-6 pt-4 border-t border-gray-200 text-center text-xs text-gray-400">
                        Actions disabled for viewer accounts.
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Product List Table -->
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
            <h2 class="text-lg font-bold text-gray-800 mb-1">Inventory List</h2>
            <p class="text-xs text-gray-500 mb-4"><?= $is_viewer ? 'Viewing inventory list records.' : 'Click any row or use the Action button to load product details into the edit form.' ?></p>
            
            <div class="max-h-[600px] overflow-x-auto overflow-y-auto border border-gray-200 rounded-lg pb-2">
                <table class="min-w-[850px] w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0 z-10 shadow-sm">
                        <tr>
                            <?php if (!$is_viewer): ?>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Action</th>
                            <?php endif; ?>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Product Code / Barcode</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Product Name</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Category</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">UM</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">In</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Out</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Remaining</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Retail</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm bg-white">
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $p): ?>
                                <?php 
                                    $p_id     = $p['id'] ?? '';
                                    $p_code   = $p['product_code'] ?? '';
                                    $p_name   = $p['product_name'] ?? '';
                                    $p_cat    = $p['category'] ?? 'Uncategorized';
                                    $p_um     = $p['um'] ?? '';
                                    $p_in     = $p['Stock_in'] ?? 0;
                                    $p_out    = $p['Stock_out'] ?? 0;
                                    $p_qty    = $p_in - $p_out; // Calculates negative values properly
                                    $p_ret    = $p['retail_price'] ?? 0;
                                    $amount   = $p_qty * $p_ret; 
                                ?>
                                <tr <?= !$is_viewer ? "onclick=\"window.location.href='products.php?edit=" . urlencode($p_id) . "'\" class=\"cursor-pointer transition hover:bg-indigo-50\"" : "class=\"transition hover:bg-gray-50\"" ?>>
                                    <?php if (!$is_viewer): ?>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <a href="products.php?edit=<?= urlencode($p_id) ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-2 py-1 rounded shadow inline-block">Edit</a>
                                        </td>
                                    <?php endif; ?>
                                    <td class="px-3 py-3 font-mono text-indigo-600 font-semibold text-xs whitespace-nowrap"><?= htmlspecialchars($p_code ?: '-') ?></td>
                                    <td class="px-3 py-3 font-semibold text-gray-900 whitespace-nowrap"><?= htmlspecialchars($p_name) ?></td>
                                    <td class="px-3 py-3 text-gray-600 whitespace-nowrap"><?= htmlspecialchars($p_cat) ?></td>
                                    <td class="px-3 py-3 text-gray-600 whitespace-nowrap"><?= htmlspecialchars($p_um) ?></td>
                                    <td class="px-3 py-3 text-gray-600 whitespace-nowrap"><?= htmlspecialchars($p_in) ?></td>
                                    <td class="px-3 py-3 text-gray-600 whitespace-nowrap"><?= htmlspecialchars($p_out) ?></td>
                                    <td class="px-3 py-3 font-bold whitespace-nowrap <?= $p_qty <= 0 ? 'text-red-600' : 'text-gray-800' ?>">
                                        <?= htmlspecialchars($p_qty) ?>
                                    </td>
                                    <td class="px-3 py-3 text-green-700 font-bold whitespace-nowrap">₱<?= number_format($p_ret, 2) ?></td>
                                    <td class="px-3 py-3 font-semibold text-gray-800 whitespace-nowrap">₱<?= number_format($amount, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="px-4 py-4 text-center text-gray-500">No products found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
<?php if (!$is_viewer): ?>
// Barcode scanner listener
document.getElementById('product_code_input').addEventListener('input', function() {
    let barcode = this.value.trim();
    if (barcode.length > 1) {
        fetch('get_product.php?code=' + encodeURIComponent(barcode))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.product) {
                    let p = data.product;
                    document.getElementById('product_name_input').value = p.product_name || '';
                    document.getElementById('category_input').value = p.category || '';
                    document.getElementById('um_input').value = p.um || 'pc';
                    document.getElementById('buy_price_input').value = p.buy_price || 0;
                    document.getElementById('retail_price_input').value = p.retail_price || 0;
                    document.getElementById('stock_in_input').value = p.Stock_in || 0;
                    document.getElementById('stock_out_input').value = p.Stock_out || 0;
                    document.getElementById('stock_qty_input').value = (p.Stock_in || 0) - (p.Stock_out || 0);
                }
            })
            .catch(err => console.error('Error fetching barcode:', err));
    }
});

// Auto-compute remaining quantity (allows negative numbers)
function calculateRemaining() {
    let stockIn = parseFloat(document.getElementById('stock_in_input').value) || 0;
    let stockOut = parseFloat(document.getElementById('stock_out_input').value) || 0;
    let remaining = stockIn - stockOut;
    
    document.getElementById('stock_qty_input').value = remaining;
}

document.getElementById('stock_in_input').addEventListener('input', calculateRemaining);
document.getElementById('stock_out_input').addEventListener('input', calculateRemaining);
<?php endif; ?>
</script>

</body>
</html>
