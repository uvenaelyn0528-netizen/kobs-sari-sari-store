<?php
session_start();
require_once 'db.php';

$role = strtolower(trim($_SESSION['role'] ?? 'viewer'));
$is_viewer = ($role === 'viewer');

include 'header.php';

$message = '';
$message_type = '';

// Handle Add/Update Partner Share
if (!$is_viewer && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_share'])) {
    $partner_name      = trim($_POST['partner_name'] ?? '');
    $shares_count      = floatval($_POST['shares_count'] ?? 0);
    $investment_amount = floatval($_POST['investment_amount'] ?? 0);

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

// Fetch Shares
try {
    $stmt = $pdo->query("SELECT * FROM shares ORDER BY partner_name ASC");
    $shares = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $shares = [];
}

// Fetch Total Dividend Pool dynamically if linked to another table/setting, or set default
// (Change this logic if your total dividend comes from a database query or settings table)
$total_dividend_pool = 275419.80; 

// Calculate Totals for Summary Cards
$total_capital = 0;
$total_shares_count = 0;
foreach ($shares as $s) {
    $total_capital += floatval($s['investment_amount'] ?? 0);
    $total_shares_count += floatval($s['shares_count'] ?? 0);
}
$amount_per_share = $total_shares_count > 0 ? $total_dividend_pool / $total_shares_count : 0;
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center">
        <a href="management.php" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-semibold rounded-md shadow-sm transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Management Hub
        </a>
    </div>

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">🤝 KOBS COOP Shares & Dividends</h1>
            <p class="text-xs text-gray-500 mt-1">Real-time breakdown of shares, capital contributions, and computed dividends.</p>
        </div>

        <!-- Summary Metric Cards (Matching Excel Header) -->
        <div class="flex flex-wrap gap-3">
            <div class="bg-white px-4 py-2 rounded-lg shadow border border-gray-200 text-center">
                <span class="block text-xs text-gray-500 uppercase font-medium">Total Capital</span>
                <span class="text-base font-bold text-gray-800">₱<?= number_format($total_capital, 2) ?></span>
            </div>
            <div class="bg-white px-4 py-2 rounded-lg shadow border border-gray-200 text-center">
                <span class="block text-xs text-gray-500 uppercase font-medium">Total Dividend Pool</span>
                <span class="text-base font-bold text-green-700">₱<?= number_format($total_dividend_pool, 2) ?></span>
            </div>
            <div class="bg-white px-4 py-2 rounded-lg shadow border border-gray-200 text-center">
                <span class="block text-xs text-gray-500 uppercase font-medium">Amount / Share</span>
                <span class="text-base font-bold text-indigo-600">₱<?= number_format($amount_per_share, 2) ?></span>
            </div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <?php if (!$is_viewer): ?>
        <!-- Add Partner Form -->
        <div class="bg-white p-6 rounded-xl shadow-md h-fit">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Add Partner Share</h2>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Partner Name</label>
                    <input type="text" name="partner_name" placeholder="Pangalan ng Kasosyo" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700"># of Shares</label>
                    <input type="number" step="any" name="shares_count" placeholder="0" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Capital Amount (₱)</label>
                    <input type="number" step="0.01" name="investment_amount" placeholder="0.00" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <button type="submit" name="add_share" class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition font-semibold shadow">
                    Save Partner Share
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Main Cooperative Table -->
        <div class="<?= $is_viewer ? 'lg:col-span-4' : 'lg:col-span-3' ?> bg-white p-6 rounded-xl shadow-md">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Shareholders Directory & Dividend Computations</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">NO.</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">NAME</th>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase"># of Shares</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">AMOUNT</th>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">% SHARE</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">DIVIDEND</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">TOTAL MONEY</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (!empty($shares)): ?>
                            <?php 
                            $counter = 1;
                            foreach ($shares as $s): 
                                $shares_cnt = floatval($s['shares_count'] ?? 0);
                                $amount_val = floatval($s['investment_amount'] ?? 0);
                                
                                // Formulas matching spreadsheet logic
                                $share_percentage = $total_shares_count > 0 ? ($shares_cnt / $total_shares_count) * 100 : 0;
                                $dividend_val = $shares_cnt * $amount_per_share;
                                $total_money = $amount_val + $dividend_val;
                            ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-3 text-center text-gray-500"><?= $counter++ ?></td>
                                    <td class="px-3 py-3 font-semibold text-gray-900 whitespace-nowrap"><?= htmlspecialchars($s['partner_name']) ?></td>
                                    <td class="px-3 py-3 text-center text-gray-700"><?= $shares_cnt ?></td>
                                    <td class="px-3 py-3 text-right text-gray-800">₱<?= number_format($amount_val, 2) ?></td>
                                    <td class="px-3 py-3 text-center text-indigo-600 font-medium"><?= number_format($share_percentage, 2) ?>%</td>
                                    <td class="px-3 py-3 text-right text-green-700 font-bold">₱<?= number_format($dividend_val, 2) ?></td>
                                    <td class="px-3 py-3 text-right text-gray-900 font-bold bg-gray-50">₱<?= number_format($total_money, 2) ?></td>
                                    <td class="px-3 py-3 text-gray-400 text-xs">-</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-gray-500">No share records found in database.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
