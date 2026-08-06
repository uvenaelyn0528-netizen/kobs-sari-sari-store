<?php
session_start();
require_once 'db.php';
include 'header.php';

$message = '';
$message_type = '';

// Check if current user is admin
$is_admin = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') || (isset($_SESSION['username']) && strtolower($_SESSION['username']) === 'admin');

// Handle GCash Transaction Submission (New Entry)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_gcash'])) {
    $ref_number   = trim($_POST['ref_number'] ?? '');
    $sender_name  = trim($_POST['sender_name'] ?? '');
    $mobile_num   = trim($_POST['mobile_num'] ?? '');
    $amount       = floatval($_POST['amount'] ?? 0);
    $tx_type      = $_POST['tx_type'] ?? 'Cash In'; 
    $fee          = floatval($_POST['fee'] ?? 0);

    if (empty($ref_number) || empty($sender_name) || $amount <= 0) {
        $message = "Please fill in all required fields correctly.";
        $message_type = "error";
    } else {
        try {
            $checkStmt = $pdo->prepare("SELECT id FROM gcash_transactions WHERE ref_number = ?");
            $checkStmt->execute([$ref_number]);
            if ($checkStmt->rowCount() > 0) {
                $message = "Error: Reference number already exists!";
                $message_type = "error";
            } else {
                $stmt = $pdo->prepare('INSERT INTO gcash_transactions (ref_number, sender_name, mobile_num, amount, tx_type, fee, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
                $stmt->execute([$ref_number, $sender_name, $mobile_num, $amount, $tx_type, $fee]);
                $message = "GCash transaction recorded successfully!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Database Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Handle Delete Action (Admin Only Protection)
if (isset($_GET['delete_id'])) {
    if (!$is_admin) {
        echo "<script>alert('Access Denied: Only Admin can delete GCash transactions.'); window.location='gcash.php';</script>";
        exit;
    }

    $del_id = intval($_GET['delete_id']);
    try {
        $delStmt = $pdo->prepare("DELETE FROM gcash_transactions WHERE id = ?");
        $delStmt->execute([$del_id]);
        $message = "GCash transaction successfully deleted!";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "Error deleting transaction: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle Edit Form Submission (Admin Only Protection)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_gcash'])) {
    if (!$is_admin) {
        echo "<script>alert('Access Denied: Only Admin can update GCash transactions.'); window.location='gcash.php';</script>";
        exit;
    }

    $edit_id     = intval($_POST['edit_id']);
    $sender_name = trim($_POST['sender_name'] ?? '');
    $mobile_num  = trim($_POST['mobile_num'] ?? '');
    $amount      = floatval($_POST['amount'] ?? 0);
    $tx_type     = $_POST['tx_type'] ?? 'Cash In';
    $fee         = floatval($_POST['fee'] ?? 0);

    try {
        if (empty($sender_name) || $amount <= 0) {
            throw new Exception("Please provide a valid sender name and amount.");
        }

        $updt = $pdo->prepare("UPDATE gcash_transactions SET sender_name = ?, mobile_num = ?, amount = ?, tx_type = ?, fee = ? WHERE id = ?");
        $updt->execute([$sender_name, $mobile_num, $amount, $tx_type, $fee, $edit_id]);

        $message = "GCash transaction updated successfully!";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "Error updating: " . $e->getMessage();
        $message_type = "error";
    }
}

// Fetch Recent GCash Transactions
try {
    $stmt = $pdo->query('SELECT * FROM gcash_transactions ORDER BY created_at DESC LIMIT 50');
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $transactions = [];
}
?>

<div class="container mx-auto px-4 py-8 mb-12">
    <!-- Back Button -->
    <div class="mb-6">
        <a href="javascript:history.back()" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-semibold rounded-md shadow-sm transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back
        </a>
    </div>

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-600 flex items-center gap-2">
            <span>📱 GCash Transaction Manager</span>
        </h1>
    </div>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Viewer Notice for Non-Admin Users -->
    <?php if (!$is_admin): ?>
        <div class="mb-6 bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-lg shadow-sm">
            <div class="flex items-center">
                <span class="text-2xl mr-3">👀</span>
                <div>
                    <p class="text-sm font-bold text-amber-800">Viewer Mode Notice</p>
                    <p class="text-xs text-amber-700">Maaari mong tingnan ang mga rekord at magdagdag ng transaksyon, ngunit ang pag-edit at pag-delete ay para lamang sa Admin.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- GCash Form -->
        <div class="bg-white p-6 rounded-xl shadow-md h-fit border-t-4 border-blue-500">
            <h2 class="text-lg font-bold text-gray-800 mb-4">New GCash Entry</h2>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Transaction Type</label>
                    <select name="tx_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-white focus:ring-blue-500 focus:border-blue-500">
                        <option value="Cash In">Cash In</option>
                        <option value="Cash Out">Cash Out</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Reference Number</label>
                    <input type="text" name="ref_number" required placeholder="Enter 13-digit ref number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Customer / Sender Name</label>
                    <input type="text" name="sender_name" required placeholder="Full Name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Mobile Number</label>
                    <input type="text" name="mobile_num" placeholder="09XXXXXXXXX" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Amount (₱)</label>
                        <input type="number" step="0.01" name="amount" required placeholder="0.00" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fee (₱)</label>
                        <input type="number" step="0.01" name="fee" value="0.00" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-200">
                    <button type="submit" name="process_gcash" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition font-semibold shadow">
                        Save Transaction
                    </button>
                </div>
            </form>
        </div>

        <!-- Transactions History Table -->
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-800 mb-1">Recent GCash Logs</h2>
                    <p class="text-xs text-gray-500">Showing the latest recorded cash-in and cash-out activities.</p>
                </div>
                <input type="text" id="searchGcash" placeholder="Search records..." class="border rounded-md px-3 py-1 text-sm">
            </div>
            
            <div class="max-h-[600px] overflow-x-auto overflow-y-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200" id="gcashTable">
                    <thead class="bg-gray-50 sticky top-0 z-10 shadow-sm">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Type</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Ref Number</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Name</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Mobile</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Amount</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Fee</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Date/Time</th>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm bg-white">
                        <?php if (!empty($transactions)): ?>
                            <?php foreach ($transactions as $tx): ?>
                                <tr class="hover:bg-blue-50 transition">
                                    <td class="px-3 py-3 whitespace-nowrap font-bold <?= $tx['tx_type'] === 'Cash In' ? 'text-green-600' : 'text-orange-600' ?>">
                                        <?= htmlspecialchars($tx['tx_type']) ?>
                                    </td>
                                    <td class="px-3 py-3 font-mono text-xs whitespace-nowrap"><?= htmlspecialchars($tx['ref_number']) ?></td>
                                    <td class="px-3 py-3 font-semibold text-gray-900 whitespace-nowrap"><?= htmlspecialchars($tx['sender_name']) ?></td>
                                    <td class="px-3 py-3 text-gray-600 whitespace-nowrap"><?= htmlspecialchars($tx['mobile_num']) ?></td>
                                    <td class="px-3 py-3 font-bold text-gray-800 whitespace-nowrap">₱<?= number_format($tx['amount'], 2) ?></td>
                                    <td class="px-3 py-3 text-gray-600 whitespace-nowrap">₱<?= number_format($tx['fee'], 2) ?></td>
                                    <td class="px-3 py-3 text-xs text-gray-500 whitespace-nowrap"><?= htmlspecialchars($tx['created_at']) ?></td>
                                    <td class="px-3 py-3 text-center whitespace-nowrap">
                                        <?php if ($is_admin): ?>
                                            <!-- Admin Only Edit and Delete Buttons -->
                                            <button onclick="openEditModal(<?= $tx['id'] ?>, '<?= htmlspecialchars($tx['tx_type'], ENT_QUOTES) ?>', '<?= htmlspecialchars($tx['sender_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($tx['mobile_num'], ENT_QUOTES) ?>', <?= $tx['amount'] ?>, <?= $tx['fee'] ?>)" class="text-indigo-600 hover:text-indigo-900 font-semibold text-xs bg-indigo-50 px-2 py-1 rounded mr-1">Edit</button>
                                            <a href="gcash.php?delete_id=<?= $tx['id'] ?>" onclick="return confirm('Are you sure you want to delete this GCash transaction?');" class="text-red-600 hover:text-red-900 font-semibold text-xs bg-red-50 px-2 py-1 rounded">Delete</a>
                                        <?php else: ?>
                                            <!-- Non-Admin / Viewers Badge -->
                                            <span class="text-gray-400 italic text-xs bg-gray-100 px-2 py-1 rounded">Restricted</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-4 py-4 text-center text-gray-500">No GCash transactions found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-xl shadow-lg w-full max-w-md">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Edit GCash Transaction</h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="edit_id" id="edit_id">
            <div>
                <label class="block text-sm font-medium text-gray-700">Transaction Type</label>
                <select name="tx_type" id="edit_tx_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-white">
                    <option value="Cash In">Cash In</option>
                    <option value="Cash Out">Cash Out</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Customer / Sender Name</label>
                <input type="text" name="sender_name" id="edit_sender_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Mobile Number</label>
                <input type="text" name="mobile_num" id="edit_mobile_num" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Amount (₱)</label>
                    <input type="number" step="0.01" name="amount" id="edit_amount" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Fee (₱)</label>
                    <input type="number" step="0.01" name="fee" id="edit_fee" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeEditModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 font-semibold text-sm">Cancel</button>
                <button type="submit" name="update_gcash" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 font-semibold text-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, txType, senderName, mobileNum, amount, fee) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_tx_type').value = txType;
    document.getElementById('edit_sender_name').value = senderName;
    document.getElementById('edit_mobile_num').value = mobileNum;
    document.getElementById('edit_amount').value = amount;
    document.getElementById('edit_fee').value = fee;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

// Live search filter for table
document.getElementById('searchGcash').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#gcashTable tbody tr');
    rows.forEach(row => {
        let text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

</body>
</html>
