<?php
require_once 'db.php';
include 'header.php';

$message = '';
$message_type = '';

// Handle GCash Transaction Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_gcash'])) {
    $ref_number   = trim($_POST['ref_number'] ?? '');
    $sender_name  = trim($_POST['sender_name'] ?? '');
    $mobile_num   = trim($_POST['mobile_num'] ?? '');
    $amount       = floatval($_POST['amount'] ?? 0);
    $tx_type      = $_POST['tx_type'] ?? 'Cash In'; // Cash In or Cash Out
    $fee          = floatval($_POST['fee'] ?? 0);

    if (empty($ref_number) || empty($sender_name) || $amount <= 0) {
        $message = "Please fill in all required fields correctly.";
        $message_type = "error";
    } else {
        try {
            // Optional: Check if reference number already exists to avoid double entries
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

// Fetch Recent GCash Transactions
try {
    $stmt = $pdo->query('SELECT * FROM gcash_transactions ORDER BY created_at DESC LIMIT 50');
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $transactions = [];
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

    <h1 class="text-2xl font-bold text-blue-600 mb-6 flex items-center gap-2">
        <span>📱 GCash Transaction Manager</span>
    </h1>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($message) ?>
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
            <h2 class="text-lg font-bold text-gray-800 mb-1">Recent GCash Logs</h2>
            <p class="text-xs text-gray-500 mb-4">Showing the latest recorded cash-in and cash-out activities.</p>
            
            <div class="max-h-[600px] overflow-x-auto overflow-y-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0 z-10 shadow-sm">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Type</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Ref Number</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Name</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Mobile</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Amount</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Fee</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Date/Time</th>
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
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-4 py-4 text-center text-gray-500">No GCash transactions found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
