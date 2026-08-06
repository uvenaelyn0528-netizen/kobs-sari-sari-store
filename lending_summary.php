<?php
require_once 'db.php';
include 'header.php';

// Fetch summary per customer from credits table or stockouts for Money Lending
try {
    $stmt = $pdo->query("SELECT customer_name, store_credit, total_payment, total_balance FROM credits WHERE store_credit > 0 OR total_balance > 0 ORDER BY customer_name ASC");
    $summaries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Compute totals
    $grand_total_lending = 0;
    $grand_total_payment = 0;
    $grand_total_balance = 0;

    foreach ($summaries as $s) {
        $grand_total_lending += floatval($s['store_credit']);
        $grand_total_payment += floatval($s['total_payment']);
        $grand_total_balance += floatval($s['total_balance']);
    }
} catch (PDOException $e) {
    $summaries = [];
    $grand_total_lending = 0;
    $grand_total_payment = 0;
    $grand_total_balance = 0;
}
?>

<div class="container mx-auto px-4 py-8 mb-12">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">📊 Cash Lending Summary Report</h1>
            <p class="text-sm text-gray-600">Overview of total active balances, total loans, and payments per customer.</p>
        </div>
        <div>
            <a href="lending.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg font-bold shadow-sm hover:bg-gray-700 transition text-sm">
                &larr; Back to Lending
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-blue-500">
            <p class="text-sm font-medium text-gray-500">Total Cash Loaned Out</p>
            <h3 class="text-2xl font-bold text-gray-900 mt-1">₱<?= number_format($grand_total_lending, 2) ?></h3>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-green-500">
            <p class="text-sm font-medium text-gray-500">Total Collected Payments & Interest</p>
            <h3 class="text-2xl font-bold text-gray-900 mt-1">₱<?= number_format($grand_total_payment, 2) ?></h3>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-teal-500">
            <p class="text-sm font-medium text-gray-500">Total Outstanding Balance</p>
            <h3 class="text-2xl font-bold text-teal-700 mt-1">₱<?= number_format($grand_total_balance, 2) ?></h3>
        </div>
    </div>

    <!-- Customer Summary Table -->
    <div class="bg-white p-6 rounded-xl shadow-md">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold text-gray-800">Customer Balance Breakdown</h2>
            <input type="text" id="searchSummary" placeholder="Search customer..." class="border rounded-md px-3 py-1 text-sm">
        </div>

        <div class="max-h-[500px] overflow-x-auto overflow-y-auto border border-gray-200 rounded-lg">
            <table class="min-w-full divide-y divide-gray-200" id="summaryTable">
                <thead class="bg-teal-100 sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-800 uppercase">Customer Name</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-800 uppercase">Total Borrowed</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-800 uppercase">Total Paid</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-800 uppercase">Remaining Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm bg-white">
                    <?php if (!empty($summaries)): ?>
                        <?php foreach ($summaries as $row): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3 text-gray-900 font-medium"><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td class="px-4 py-3 text-right text-gray-700">₱<?= number_format($row['store_credit'], 2) ?></td>
                                <td class="px-4 py-3 text-right text-green-600 font-semibold">₱<?= number_format($row['total_payment'], 2) ?></td>
                                <td class="px-4 py-3 text-right text-teal-800 font-bold">₱<?= number_format($row['total_balance'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-4 py-4 text-center text-gray-500">No active summary records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Search filter for summary table
document.getElementById('searchSummary').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#summaryTable tbody tr');
    rows.forEach(row => {
        let text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>
