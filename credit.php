<?php
require_once 'db.php';

// Kunin ang kabuuang total para sa mga kard sa taas
try {
    $totalCreditStmt = $pdo->query("SELECT SUM(store_credit) as total_credit, SUM(total_payment) as total_payment, SUM(total_balance) as total_balance FROM customer_credits");
    $totals = $totalCreditStmt->fetch(PDO::FETCH_ASSOC);
    
    $total_store_credit = $totals['total_credit'] ?? 0;
    $total_payment = $totals['total_payment'] ?? 0;
    $total_balance = $totals['total_balance'] ?? 0;

    // Kunin ang listahan ng mga customer credits
    $stmt = $pdo->query("SELECT * FROM customer_credits ORDER BY customer_name ASC");
    $credits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $total_store_credit = 0;
    $total_payment = 0;
    $total_balance = 0;
    $credits = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOBS Sari-Sari Store Credit list</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">

<div class="container mx-auto px-4 py-8">
    
    <!-- Title Section & Back Button -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">KOBS Sari-Sari Store Credit list</h1>
        </div>
        <div>
            <a href="store.php" class="inline-flex items-center gap-2 bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-semibold shadow-sm transition text-sm">
                &larr; Back to Store
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Total Store Credit -->
        <div class="bg-white p-6 rounded-xl shadow-md border-t-4 border-blue-500">
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">TOTAL STORE CREDIT</p>
            <p class="text-2xl md:text-3xl font-black text-blue-600 mt-2">₱<?= number_format($total_store_credit, 2) ?></p>
        </div>

        <!-- Total Payment -->
        <div class="bg-white p-6 rounded-xl shadow-md border-t-4 border-emerald-500">
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">TOTAL PAYMENT</p>
            <p class="text-2xl md:text-3xl font-black text-emerald-600 mt-2">₱<?= number_format($total_payment, 2) ?></p>
        </div>

        <!-- Total Balance -->
        <div class="bg-white p-6 rounded-xl shadow-md border-t-4 border-red-500">
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">TOTAL BALANCE</p>
            <p class="text-2xl md:text-3xl font-black text-red-600 mt-2">₱<?= number_format($total_balance, 2) ?></p>
        </div>
    </div>

    <!-- Main Content Card -->
    <div class="bg-white p-6 rounded-xl shadow-md">
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
            <h2 class="text-lg font-bold text-gray-800">Customer Credit Ledger</h2>
            <input type="text" id="searchCredit" placeholder="Search customer..." class="border rounded-md px-3 py-1.5 text-sm w-full md:w-72">
        </div>

        <!-- Table -->
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full divide-y divide-gray-200" id="creditTable">
                <thead class="bg-amber-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-800 uppercase tracking-wider">CUSTOMER NAME</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-800 uppercase tracking-wider">STORE CREDIT</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-800 uppercase tracking-wider">TOTAL PAYMENT</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-800 uppercase tracking-wider">TOTAL BALANCE</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-800 uppercase tracking-wider">ACTIONS</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm bg-white">
                    <?php if (!empty($credits)): ?>
                        <?php foreach ($credits as $row): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3 text-gray-900 font-medium"><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td class="px-4 py-3 text-right text-gray-800 font-semibold">₱<?= number_format($row['store_credit'], 2) ?></td>
                                <td class="px-4 py-3 text-right text-emerald-600 font-semibold">₱<?= number_format($row['total_payment'], 2) ?></td>
                                <td class="px-4 py-3 text-right text-red-600 font-bold">₱<?= number_format($row['total_balance'], 2) ?></td>
                                <td class="px-4 py-3 text-center">
                                    <a href="edit_credit.php?id=<?= $row['id'] ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1 rounded text-xs font-semibold border shadow-sm transition">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500">Walang nakitang rekord ng utang.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Search filter para sa credit table
document.getElementById('searchCredit').addEventListener('keyup', function() {
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
