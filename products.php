<?php
require_once 'db.php';
include 'header.php';

// Ensure $is_admin is defined
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
$is_logged_in = isset($_SESSION['user_role']);

$edit_mode = false;
$edit_product = null;
$message = '';
$message_type = '';

// Handle Delete Product (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    if (!$is_admin) {
        $message = "Unauthorized action! Only administrators can delete products.";
        $message_type = "error";
    } else {
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
}

// Handle Add Product Submission (Accessible to Admin & Tindera)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    if (!$is_logged_in) {
        $message = "Unauthorized action!";
        $message_type = "error";
    } else {
        $code         = trim($_POST['product_code'] ?? '');
        $name         = $_POST['product_name'] ?? '';
        $category     = $_POST['category'] ?? '';
        $um           = $_POST['um'] ?? 'pc';
        $buy_price    = $_POST['buy_price'] ?? 0;
        $retail_price = $_POST['retail_price'] ?? 0;
        $stock_qty    = $_POST['stock_qty'] ?? 0;
        $stock_in     = $_POST['stock_in'] ?? $stock_qty;
        $stock_out    = $_POST['stock_out'] ?? 0;

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
}

// Handle Update Product Submission (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    if (!$is_admin) {
        $message = "Unauthorized action! Only administrators can update existing products.";
        $message_type = "error";
    } else {
        $code         = trim($_POST['product_code'] ?? '');
        $name         = $_POST['product_name'] ?? '';
        $category     = $_POST['category'] ?? '';
        $um           = $_POST['um'] ?? 'pc';
        $buy_price    = $_POST['buy_price'] ?? 0;
        $retail_price = $_POST['retail_price'] ?? 0;
        $stock_qty    = $_POST['stock_qty'] ?? 0;
        $stock_in     = $_POST['stock_in'] ?? $stock_qty;
        $stock_out    = $_POST['stock_out'] ?? 0;
        $original_id  = $_POST['original_id'] ?? '';

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
}

// Handle Edit Trigger via URL parameter using id (Admin Only)
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    if ($is_admin) {
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
}

// Fetch unique categories for the datalist
try {
    $catStmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category ASC");
    $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}

// Fetch Products List
try {
    $stmt = $pdo->query('SELECT * FROM products ORDER BY product_name ASC');
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching products: " . $e->getMessage();
    $message_type = "error";
    $products = [];
}
?>

<!-- Pass products data safely to JavaScript for barcode auto-fill -->
<script>
    const productsData = <?= json_encode($products); ?>;
    const isAdmin = <?= $is_admin ? 'true' : 'false' ?>;
</script>

<div class="container mx-auto px-4 py-8">
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Add / Edit Product Form -->
        <div class="bg-white p-6 rounded-xl shadow-md h-fit">
            <div class="flex justify-between items-center mb-4">
                <h2 id="form-title" class="text-lg font-bold text-gray-800">
                    <?= $edit_mode ? 'Edit Product' : 'Add New Product' ?>
                </h2>
                <?php if ($edit_mode): ?>
                    <a href="products.php" class="text-xs bg-gray-200 hover:bg-gray-300 text-gray-700 px-2 py-1 rounded font-semibold transition">Cancel Edit</a>
                <?php endif; ?>
            </div>

            <?php if (!$is_admin && $edit_mode): ?>
                <div class="mb-4 bg-amber-50 border border-amber-200 text-amber-800 p-3 rounded-xl text-xs font-medium text-center">
                    Tindera accounts cannot update or delete existing products. You can use this form to add new items.
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4" id="product-form">
                <input type="hidden" name="original_id" id="original_id" value="<?= htmlspecialchars($edit_product['id'] ?? '') ?>">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">Product Code / Barcode</label>
                    <input type="text" id="product_code" name="product_code" value="<?= htmlspecialchars($edit_product['product_code'] ?? '') ?>" placeholder="Scan or type barcode" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Product Name</label>
                    <input type="text" id="product_name" name="product_name" required value="<?= htmlspecialchars($edit_product['product_name'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Category</label>
                    <input type="text" id="category" name="category" list="category_list" required value="<?= htmlspecialchars($edit_product['category'] ?? '') ?>" placeholder="Select or type category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-white">
                    <datalist id="category_list">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">UM (Unit of Measure)</label>
                    <input type="text" id="um" name="um" required value="<?= htmlspecialchars($edit_product['um'] ?? 'pc') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Buy Price (₱)</label>
                        <input type="number" step="0.01" id="buy_price" name="buy_price" required value="<?= htmlspecialchars($edit_product['buy_price'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Retail Price (₱)</label>
                        <input type="number" step="0.01" id="retail_price" name="retail_price" required value="<?= htmlspecialchars($edit_product['retail_price'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Stock In</label>
                        <input type="number" id="stock_in" name="stock_in" required value="<?= htmlspecialchars($edit_product['Stock_in'] ?? 0) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Stock Out</label>
                        <input type="number" id="stock_out" name="stock_out" required value="<?= htmlspecialchars($edit_product['Stock_out'] ?? 0) ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Remaining Qty</label>
                        <input type="number" id="stock_qty" name="stock_qty" required value="<?= htmlspecialchars($edit_product['stock_qty'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                </div>

                <!-- Form Action Buttons -->
                <div class="mt-6 pt-4 border-t border-gray-200">
                    <div id="form-buttons">
                        <?php if ($edit_mode): ?>
                            <?php if ($is_admin): ?>
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
                                <div class="space-y-2">
                                    <button type="submit" name="add_product" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition font-semibold shadow">
                                        Save as New Product
                                    </button>
                                    <a href="products.php" class="w-full bg-gray-500 text-white py-2 px-4 rounded-md text-center hover:bg-gray-600 transition font-semibold shadow block">
                                        Clear / Cancel
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div>
                                <button type="submit" name="add_product" id="submit_btn" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition font-semibold shadow">
                                    Save Product
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Product List Table -->
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
            <h2 class="text-lg font-bold text-gray-800 mb-1">Inventory List</h2>
            <p class="text-xs text-gray-500 mb-4">
                <?= $is_admin ? 'Click any row or use the Action button to load product details into the edit form.' : 'Scanning an existing barcode will load details; saving will record it as a new entry.' ?>
            </p>
            <div class="max-h-[600px] overflow-y-auto overflow-x-auto relative">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            <?php if ($is_admin): ?>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            <?php endif; ?>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product Code / Barcode</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product Name</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">UM</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">In</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Out</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remaining</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Retail</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm">
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $p): ?>
                                <?php 
                                    $p_id      = $p['id'] ?? '';
                                    $p_code   = $p['product_code'] ?? '';
                                    $p_name   = $p['product_name'] ?? '';
                                    $p_cat    = $p['category'] ?? 'Uncategorized';
                                    $p_um     = $p['um'] ?? '';
                                    $p_in     = $p['Stock_in'] ?? 0;
                                    $p_out    = $p['Stock_out'] ?? 0;
                                    $p_qty    = $p['stock_qty'] ?? 0;
                                    $p_ret    = $p['retail_price'] ?? 0;
                                    $amount   = $p_qty * $p_ret; 
                                    $is_selected = ($edit_mode && ($edit_product['id'] ?? '') == $p_id);
                                ?>
                                <tr <?= $is_admin ? "onclick=\"window.location.href='products.php?edit=" . urlencode($p_id) . "'\" class=\"cursor-pointer transition hover:bg-indigo-50 " . ($is_selected ? 'bg-indigo-100 font-medium' : '') . "\"" : "class=\"transition hover:bg-gray-50\"" ?>>
                                    <?php if ($is_admin): ?>
                                        <td class="px-3 py-3">
                                            <a href="products.php?edit=<?= urlencode($p_id) ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-2 py-1 rounded shadow inline-block">Edit</a>
                                        </td>
                                    <?php endif; ?>
                                    <td class="px-3 py-3 font-mono text-indigo-600 font-semibold text-xs"><?= htmlspecialchars($p_code ?: '-') ?></td>
                                    <td class="px-3 py-3 font-semibold text-gray-900"><?= htmlspecialchars($p_name) ?></td>
                                    <td class="px-3 py-3 text-gray-600"><?= htmlspecialchars($p_cat) ?></td>
                                    <td class="px-3 py-3 text-gray-600"><?= htmlspecialchars($p_um) ?></td>
                                    <td class="px-3 py-3 text-gray-600"><?= htmlspecialchars($p_in) ?></td>
                                    <td class="px-3 py-3 text-gray-600"><?= htmlspecialchars($p_out) ?></td>
                                    <td class="px-3 py-3 font-bold <?= $p_qty <= 5 ? 'text-red-600' : 'text-gray-800' ?>">
                                        <?= htmlspecialchars($p_qty) ?>
                                    </td>
                                    <td class="px-3 py-3 text-green-700 font-bold">₱<?= number_format($p_ret, 2) ?></td>
                                    <td class="px-3 py-3 font-semibold text-gray-800">₱<?= number_format($amount, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= $is_admin ? 10 : 9 ?>" class="px-4 py-4 text-center text-gray-500">No products found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Barcode Auto-fill JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const barcodeInput = document.getElementById('product_code');
    if (!barcodeInput) return;

    const isUrlEditing = <?= $edit_mode ? 'true' : 'false' ?>;

    barcodeInput.addEventListener('input', function() {
        if (isUrlEditing && isAdmin) return; 

        const enteredCode = this.value.trim();
        if (enteredCode === '') return;

        const matchedProduct = productsData.find(p => p.product_code && p.product_code.trim() === enteredCode);

        const formTitle = document.getElementById('form-title');
        const originalIdInput = document.getElementById('original_id');
        const formButtons = document.getElementById('form-buttons');

        if (matchedProduct) {
            // Auto-fill values from matched barcode
            document.getElementById('product_name').value = matchedProduct.product_name || '';
            document.getElementById('category').value = matchedProduct.category || '';
            document.getElementById('um').value = matchedProduct.um || 'pc';
            document.getElementById('buy_price').value = matchedProduct.buy_price || '';
            document.getElementById('retail_price').value = matchedProduct.retail_price || '';
            document.getElementById('stock_in').value = matchedProduct.Stock_in || 0;
            document.getElementById('stock_out').value = matchedProduct.Stock_out || 0;
            document.getElementById('stock_qty').value = matchedProduct.stock_qty || '';
            originalIdInput.value = matchedProduct.id || '';

            if (isAdmin) {
                formTitle.textContent = 'Edit Product (Existing Barcode)';
                formButtons.innerHTML = `
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
                `;
            } else {
                formTitle.textContent = 'Add Product from Barcode Template';
                formButtons.innerHTML = `
                    <div>
                        <button type="submit" name="add_product" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition font-semibold shadow">
                            Save as New Product
                        </button>
                    </div>
                `;
            }
        } else {
            originalIdInput.value = '';
            document.getElementById('product_name').value = '';
            document.getElementById('category').value = '';
            document.getElementById('um').value = 'pc';
            document.getElementById('buy_price').value = '';
            document.getElementById('retail_price').value = '';
            document.getElementById('stock_in').value = '0';
            document.getElementById('stock_out').value = '0';
            document.getElementById('stock_qty').value = '';

            formTitle.textContent = 'Add New Product';
            formButtons.innerHTML = `
                <div>
                    <button type="submit" name="add_product" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition font-semibold shadow">
                        Save Product
                    </button>
                </div>
            `;
        }
    });
});
</script>

</body>
</html>
