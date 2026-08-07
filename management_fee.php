<?php
session_start();
require_once 'db.php';

$role = strtolower(trim($_SESSION['role'] ?? 'viewer'));
$is_viewer = ($role === 'viewer');

include 'header.php';

$message = '';
$message_type = '';

// Handle Add Fee Entry
if (!$is_viewer && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fee'])) {
    $fee_date = $_POST['fee_date'] ?? date('Y-m-d');
    $description = trim($_POST['description'] ?? '');
    $amount = $_POST['amount'] ?? 0;

    try {
        $stmt = $pdo->prepare("INSERT INTO management_fees (fee_date, description, amount) VALUES (?, ?, ?)");
        $stmt->execute([$fee_date, $description, $amount]);
        $message = "Management fee recorded successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Fetch Records
try {
    $stmt = $pdo->query("SELECT * FROM management_fees ORDER BY fee_date DESC");
    $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If table doesn't exist yet, fallback gracefully
    $fees = [];
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="management.php" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-semibold rounded-md shadow-sm transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Management Hub
        </a>
    </div>

    <h1 class="text-2xl font-bold text-gray-800 mb-6">💼 Management Fee Records</h1>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <?php if (!$is_viewer): ?>
        <!-- Form to Add Fee -->
        <div class="bg-white p-6 rounded-xl shadow-md h-fit">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Add Fee Record</h2>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Date</label>
                    <input type="date" name="fee_date" value="<?= date('Y-m-d') ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Description / Source</label>
                    <input type="text" name="description" placeholder="e.g., Monthly store cut" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Amount (₱)</label>
                    <input type="number" step="0.01" name="amount" placeholder="0.00" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <button type="submit" name="add_fee" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition font-semibold shadow">
                    Save Fee
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- List Table -->
        <div class="<?= $is_viewer ? 'lg:col-span-3' : 'lg:col-span-2' ?> bg-white p-6 rounded-xl shadow-md">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Fee History</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm">
                        <?php if (!empty($fees)): ?>
                            <?php foreach ($fees as $f): ?>
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600"><?= htmlspecialchars($f['fee_date']) ?></td>
                                    <td class="px-4 py-3 text-gray-900"><?= htmlspecialchars($f['description']) ?></td>
                                    <td class="px-4 py-3 text-right font-bold text-green-700">₱<?= number_format($f['amount'] ?? 0, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="px-4 py-4 text-center text-gray-500">No management fee logs found. (Note: Ensure the `management_fees` table is created in your database).</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
