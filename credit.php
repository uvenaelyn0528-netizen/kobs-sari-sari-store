<?php
require_once 'db.php'; 

// Auto-detect transaction type column name from PostgreSQL table metadata
$typeCol = 'type';
try {
    $metaStmt = $pdo->query("SELECT * FROM transactions LIMIT 0");
    for ($i = 0; $i < $metaStmt->columnCount(); $i++) {
        $meta = $metaStmt->getColumnMeta($i);
        $colName = strtolower($meta['name']);
        if (in_array($colName, ['transaction_type', 'type', 'trans_type', 'transtype'])) {
            $typeCol = $meta['name'];
            break;
        }
    }
} catch (Exception $e) {
    $typeCol = 'transaction_type';
}

// Safely format column identifier for PostgreSQL
$typeColSql = '"' . str_replace('"', '""', $typeCol) . '"';

// Fetch summary card totals
$summaryQuery = "
    SELECT 
        COALESCE(SUM(CASE WHEN {$typeColSql} = 'Credit' THEN amount ELSE 0 END), 0) AS total_store_credit,
        COALESCE(SUM(CASE WHEN {$typeColSql} = 'Payment' THEN amount ELSE 0 END), 0) AS total_payment
    FROM transactions
";
$summary = $pdo->query($summaryQuery)->fetch(PDO::FETCH_ASSOC);

$totalStoreCredit = $summary['total_store_credit'] ?? 0;
$totalPayment = $summary['total_payment'] ?? 0;
$totalBalance = $totalStoreCredit - $totalPayment;

// Fetch customer ledger balances grouped by customer_id
$ledgerQuery = "
    SELECT 
        c.id AS customer_id,
        c.name AS customer_name,
        COALESCE(SUM(CASE WHEN t.{$typeColSql} = 'Credit' THEN t.amount ELSE 0 END), 0) AS store_credit,
        COALESCE(SUM(CASE WHEN t.{$typeColSql} = 'Payment' THEN t.amount ELSE 0 END), 0) AS total_payment,
        (COALESCE(SUM(CASE WHEN t.{$typeColSql} = 'Credit' THEN t.amount ELSE 0 END), 0) - 
         COALESCE(SUM(CASE WHEN t.{$typeColSql} = 'Payment' THEN t.amount ELSE 0 END), 0)) AS total_balance
    FROM customers c
    LEFT JOIN transactions t ON c.id = t.customer_id
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
";
$ledgerRows = $pdo->query($ledgerQuery)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KOBS COOP - Credit List</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 p-6 font-sans">
    <div class="max-w-6xl mx-auto space-y-6">
        
        <div>
            <a href="stockout.php" class="inline-block px-4 py-2 bg-white rounded shadow text-sm font-semibold text-gray-700 hover:bg-gray-50">
                &larr; Back to Store
            </a>
        </div>

        <h1 class="text-2xl font-bold text-slate-800">KOBS Sari-Sari Store Credit list</h1>

        <!-- Metric Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white p-5 rounded-xl shadow-sm border-t-4 border-blue-500">
                <span class="text-xs font-bold text-gray-400 tracking-wider uppercase">Total Store Credit</span>
                <p class="text-3xl font-extrabold text-blue-600 mt-2">&#8369;<?= number_format($totalStoreCredit, 2) ?></p>
            </div>
            <div class="bg-white p-5 rounded-xl shadow-sm border-t-4 border-emerald-500">
                <span class="text-xs font-bold text-gray-400 tracking-wider uppercase">Total Payment</span>
                <p class="text-3xl font-extrabold text-emerald-600 mt-2">&#8369;<?= number_format($totalPayment, 2) ?></p>
            </div>
            <div class="bg-white p-5 rounded-xl shadow-sm border-t-4 border-red-500">
                <span class="text-xs font-bold text-gray-400 tracking-wider uppercase">Total Balance</span>
                <p class="text-3xl font-extrabold text-red-600 mt-2">&#8369;<?= number_format($totalBalance, 2) ?></p>
            </div>
        </div>

        <!-- Customer Credit Ledger Table -->
        <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
            <div class="flex justify-between items-center">
                <h2 class="text-lg font-bold text-slate-800">Customer Credit Ledger</h2>
                <input type="text" id="searchInput" placeholder="Search customer..." class="px-3 py-1.5 border rounded-lg text-sm w-64 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="ledgerTable">
                    <thead>
                        <tr class="bg-amber-50 text-amber-900 text-xs font-bold uppercase tracking-wider">
                            <th class="p-3">Customer Name</th>
                            <th class="p-3">Store Credit</th>
                            <th class="p-3">Total Payment</th>
                            <th class="p-3">Total Balance</th>
                            <th class="p-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <?php if (empty($ledgerRows)): ?>
                            <tr>
                                <td colspan="5" class="p-4 text-center text-gray-500">No customer records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ledgerRows as $row): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3 font-semibold text-gray-800"><?= htmlspecialchars($row['customer_name']) ?></td>
                                    <td class="p-3 font-bold text-blue-600">&#8369;<?= number_format($row['store_credit'], 2) ?></td>
                                    <td class="p-3 font-bold text-emerald-600">&#8369;<?= number_format($row['total_payment'], 2) ?></td>
                                    <td class="p-3 font-bold text-red-600">&#8369;<?= number_format($row['total_balance'], 2) ?></td>
                                    <td class="p-3 text-center">
                                        <a href="view_ledger.php?customer_id=<?= $row['customer_id'] ?>" class="px-3 py-1 bg-indigo-600 text-white rounded text-xs font-semibold hover:bg-indigo-700">
                                            View Ledger
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        document.getElementById('searchInput').addEventListener('keyup', function () {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#ledgerTable tbody tr');
            rows.forEach(row => {
                const nameCell = row.children[0];
                if (nameCell) {
                    const name = nameCell.textContent.toLowerCase();
                    row.style.display = name.includes(filter) ? '' : 'none';
                }
            });
        });
    </script>
</body>
</html>
