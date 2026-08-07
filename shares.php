<?php
session_start();
require_once 'db.php';

$role = strtolower(trim($_SESSION['role'] ?? 'viewer'));
$is_viewer = ($role === 'viewer');

include 'header.php';

$message = '';
$message_type = '';

if (!$is_viewer && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_share'])) {
    $partner_name = trim($_POST['partner_name'] ?? '');
    $shares_count = $_POST['shares_count'] ?? 0;
    $investment_amount = $_POST['investment_amount'] ?? 0;

    try {
        $stmt = $pdo->prepare("INSERT INTO shares (partner_name, shares_count, investment_amount) VALUES (?, ?, ?)");
        $stmt->execute([$partner_name, $shares_count, $investment_amount]);
        $message = "Partner share recorded successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

try {
    $stmt = $pdo->query("SELECT * FROM shares ORDER BY partner_name ASC");
    $shares = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $shares = [];
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

    <h1 class="text-2xl font-bold text-gray-800 mb-6">🤝 Partner Shares Management</h1>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <?php if (!$is_viewer): ?>
        <div class="bg-white p-6 rounded-xl shadow-md h-fit">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Add Partner Share</h2>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Partner Name</label>
                    <input type="text" name="partner_name" placeholder="Pangalan ng Kasosyo" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Number of Shares</label>
                    <input type="number" name="shares_count" placeholder="0" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Total Investment (₱)</label>
                    <input type="number" step="0.01" name="investment_amount" placeholder="0.00" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <button type="submit" name="add_share" class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition font-semibold shadow">
                    Save Share
                </button>
            </form>
        </div>
        <?php endif; ?>

        <div class="<?= $is_viewer ? 'lg:col-span-3' : 'lg:col-span-2' ?> bg-white p-6 rounded-xl shadow-md">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Partners Directory</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Partner Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Shares Count</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Investment Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm">
                        <?php if (!empty($shares)): ?>
                            <?php foreach ($shares as $s): ?>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-gray-900"><?= htmlspecialchars($s['partner_name']) ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($s['shares_count']) ?></td>
                                    <td class="px-4 py-3 text-right font-bold text-indigo-700">₱<?= number_format($s['investment_amount'] ?? 0, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="px-4 py-4 text-center text-gray-500">No share records found. (Ensure the `shares` table exists in your database).</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
