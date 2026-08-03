<?php
require_once 'db.php';
include 'header.php';

$message = '';
$message_type = '';

// Handle Edit Submission for Credit Ledger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_credit'])) {
    $edit_id       = intval($_POST['edit_id'] ?? 0);
    $customer_name = trim($_POST['customer_name'] ?? '');
    $store_credit  = floatval($_POST['store_credit'] ?? 0);
    $total_payment = floatval($_POST['total_payment'] ?? 0);

    try {
        if ($edit_id > 0) {
            $total_balance = $store_credit - $total_payment;

            $stmt = $pdo->prepare("UPDATE credits SET customer_name = ?, store_credit = ?, total_payment = ?, total_balance = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$customer_name, $store_credit, $total_payment, $total_balance, $edit_id]);

            $message = "Customer credit ledger updated successfully!";
            $message_type = "success";
        }
    } catch (Exception $e) {
        $message = "Error updating credit: " . $e->getMessage();
        $message_type = "error";
    }
}

// Fetch Data & Totals
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS credits (
        id SERIAL PRIMARY KEY,
        customer_name VARCHAR(255) NOT NULL UNIQUE,
        store_credit NUMERIC(10,2) DEFAULT 0.00,
        total_payment NUMERIC(10,2) DEFAULT 0.00,
        total_balance NUMERIC(10,2) DEFAULT 0.00,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Overall Totals across all customers
    $sumQuery = $pdo->query("SELECT SUM(store_credit) as total_credit, SUM(total_payment) as total_payment, SUM(total_balance) as total_balance FROM credits");
    $totals = $sumQuery->fetch(PDO::FETCH_ASSOC);

    $total_store_credit = $totals['total_credit'] ?? 0;
    $total_payment      = $totals['total_payment'] ?? 0;
    $total_balance      = $totals['total_balance'] ?? 0;

    // Fetch all customer credits
    $stmt = $pdo->query("SELECT * FROM credits ORDER BY customer_name ASC");
    $credits = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $total_store_credit = 0;
    $total_payment = 0;
    $total_balance = 0;
    $credits = [];
}
?>

<div class="container mx-auto px-4 py-8">
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Summary Header Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-white p-4 rounded-xl shadow-md border-l-4 border-blue-500">
            <p class="text-xs font-semibold text-gray-500 uppercase">Total Store Credit</p>
            <h3 class="text-2xl font-extrabold text-gray-800 mt-1">₱<?= number_format($total_store_credit, 2) ?></h3>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-md border-l-4 border-green-500">
            <p class="text-xs font-semibold text-gray-500 uppercase">Total Payment</p>
            <h3 class="text-2xl font-extrabold text-green-700 mt-1">₱<?= number_format($total_payment, 2) ?></h3>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-md border-l-4 border-red-500">
            <p class="text-xs font-semibold text-gray-500 uppercase">Total Balance</p>
            <h3 class="text-2xl font-extrabold text-red-600 mt-1">₱<?= number_format($total_balance, 2) ?></h3>
        </div>
    </div>

    <!-- Customer Credit Ledger Table -->
    <div class="bg-white p-6 rounded-xl shadow-md">
        <div class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
            <h2 class="text-lg font-bold text-gray-800">Customer Credit Ledger</h2>
            <input type="text" id="searchCustomer" placeholder="Search customer..." class="border rounded-md px-3 py-1 text-sm w-full md:w-64">
        </div>

        <div class="max-h-[500px] overflow-x-auto overflow-y-auto border border-gray-200 rounded-lg">
            <table class="min-w-full divide-y divide-gray-200" id="creditTable">
                <thead class="bg-yellow-100 sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-800 uppercase">Customer Name</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-800 uppercase">Store Credit</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-800 uppercase">Total Payment</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-800 uppercase">Total Balance</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-800 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm bg-white">
                    <?php if (!empty($credits)): ?>
                        <?php foreach ($credits as $c): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3 font-semibold text-gray-900"><?= htmlspecialchars($c['customer_name']) ?></td>
                                <td class="px-4 py-3 text-right text-gray-800">₱<?= number_format($c['store_credit'], 2) ?></td>
                                <td class="px-4 py-3 text-right font-bold text-green-700">₱<?= number_format($c['total_payment'], 2) ?></td>
                                <td class="px-4 py-3 text-right font-extrabold <?= floatval($c['total_balance']) > 0 ? 'text-red-600' : 'text-gray-800' ?>">
                                    ₱<?= number_format($c['total_balance'], 2) ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button onclick='openEditModal(<?= json_encode($c) ?>)' class="text-indigo-600 hover:text-indigo-900 font-semibold text-xs bg-indigo-50 px-3 py-1.5 rounded shadow-sm">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-center text-gray-500">No customer credit records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal Overlay -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 px-4">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-800">Edit Customer Credit</h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 font-bold text-xl">&times;</button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="edit_id" id="modal_edit_id">
            <div>
                <label class="block text-sm font-medium text-gray-700">Customer Name</label>
                <input type="text" name="customer_name" id="modal_customer_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-gray-50" readonly>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Store Credit (₱)</label>
                <input type="number" step="0.01" name="store_credit" id="modal_store_credit" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Total Payment (₱)</label>
                <input type="number" step="0.01" name="total_payment" id="modal_total_payment" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
            </div>
            <div class="flex justify-end space-x-2 pt-2">
                <button type="button" onclick="closeEditModal()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300 transition text-sm font-semibold">Cancel</button>
                <button type="submit" name="update_credit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition text-sm font-semibold shadow">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(c) {
    document.getElementById('modal_edit_id').value = c.id;
    document.getElementById('modal_customer_name').value = c.customer_name;
    document.getElementById('modal_store_credit').value = c.store_credit;
    document.getElementById('modal_total_payment').value = c.total_payment;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

// Live search customer ledger table
document.getElementById('searchCustomer').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#creditTable tbody tr');
    rows.forEach(row => {
        let text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

</body>
</html>
