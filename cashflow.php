<?php
session_start();
require_once 'db.php';

$role = strtolower(trim($_SESSION['role'] ?? 'viewer'));
$is_viewer = ($role === 'viewer');

include 'header.php';

$message = '';
$message_type = '';

if (!$is_viewer && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_cashflow'])) {
    $cf_date = $_POST['cf_date'] ?? date('Y-m-d');
    $type = $_POST['type'] ?? 'Inflow'; // Inflow or Outflow
    $category = $_POST['category'] ?? 'General';
    $amount = $_POST['amount'] ?? 0;
    $notes = trim($_POST['notes'] ?? '');

    try {
        $stmt = $pdo->prepare("INSERT INTO cashflow (cf_date, type, category, amount, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$cf_date, $type, $category, $amount, $notes]);
        $message = "Cashflow entry saved successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

try {
    $stmt = $pdo->query("SELECT * FROM cashflow ORDER BY cf_date DESC");
    $cashflows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cashflows = [];
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

    <h1 class="text-2xl font-bold text-gray-800 mb-6">📈 Cashflow Tracking</h1>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <?php if (!$is_viewer): ?>
        <div class="bg-white p-6 rounded-xl shadow-md h-fit">
            <h2 class="text-lg font-bold text-gray-800 mb-4">New Entry</h2>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Date</label>
                    <input type="date" name="cf_date" value="<?= date('Y-m-d') ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Type</label>
                    <select name="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-white">
                        <option value="Inflow">Cash In (Pumasok)</option>
                        <option value="Outflow">Cash Out (Lumabas)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Category</label>
                    <input type="text" name="category" placeholder="e.g., Sales, Supplies, Expenses" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Amount (₱)</label>
                    <input type="number" step="0.01" name="amount" placeholder="0.00" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border" placeholder="Optional notes..."></textarea>
                </div>
                <button type="submit" name="add_cashflow" class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition font-semibold shadow">
                    Save Entry
                </button>
            </form>
        </div>
        <?php endif; ?>

        <div class="<?= $is_viewer ? 'lg:col-span-3' : 'lg:col-span-2' ?> bg-white p-6 rounded-xl shadow-md">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Cashflow Transactions</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm">
                        <?php if (!empty($cashflows)): ?>
                            <?php foreach ($cashflows as $cf): ?>
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600"><?= htmlspecialchars($cf['cf_date']) ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold <?= $cf['type'] === 'Inflow' ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= htmlspecialchars($cf['type']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-800"><?= htmlspecialchars($cf['category']) ?></td>
                                    <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($cf['notes']) ?></td>
                                    <td class="px-4 py-3 text-right font-bold <?= $cf['type'] === 'Inflow' ? 'text-green-700' : 'text-red-700' ?>">
                                        <?= $cf['type'] === 'Inflow' ? '+' : '-' ?>₱<?= number_format($cf['amount'] ?? 0, 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-4 py-4 text-center text-gray-500">No cashflow logs found. (Ensure the `cashflow` table exists).</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
