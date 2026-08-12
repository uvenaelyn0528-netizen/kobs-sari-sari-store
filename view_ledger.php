<?php
require_once 'db.php';

$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

if (!$customerId) {
    header('Location: credit.php');
    exit;
}

// 1. Fetch Customer Name dynamically
$customerName = "Customer #{$customerId}";
try {
    $sampleCust = $pdo->query("SELECT * FROM customers LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $custNameExpr = "id::text";
    if ($sampleCust !== false) {
        $cols = array_map('strtolower', array_keys($sampleCust));
        if (in_array('customer_name', $cols)) $custNameExpr = '"customer_name"';
        elseif (in_array('name', $cols)) $custNameExpr = '"name"';
        elseif (in_array('fullname', $cols)) $custNameExpr = '"fullname"';
        elseif (in_array('full_name', $cols)) $custNameExpr = '"full_name"';
    }

    $custStmt = $pdo->prepare("SELECT {$custNameExpr} AS name FROM customers WHERE id = ?");
    $custStmt->execute([$customerId]);
    $custData = $custStmt->fetch(PDO::FETCH_ASSOC);
    if ($custData && !empty($custData['name'])) {
        $customerName = $custData['name'];
    }
} catch (Exception $e) {
    // Fallback name remains
}

// 2. Fetch Customer Transactions
$transactions = [];
$totalCredit = 0;
$totalPayment = 0;

try {
    $txStmt = $pdo->prepare("
        SELECT 
            id,
            transaction_date,
            payment_type,
            total_amount,
            qty,
            created_at
        FROM transactions
        WHERE customer_id = ?
        ORDER BY created_at DESC, id DESC
    ");
    $txStmt->execute([$customerId]);
    $transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($transactions as $tx) {
        if (strcasecmp($tx['payment_type'], 'Credit') === 0) {
            $totalCredit += $tx['total_amount'];
        } elseif (strcasecmp($tx['payment_type'], 'Payment') === 0) {
            $totalPayment += $tx['total_amount'];
        }
    }
} catch (Exception $e) {
    die("Database Error: " . htmlspecialchars($e->getMessage()));
}

$balance = $totalCredit - $totalPayment;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Ledger - <?= htmlspecialchars($customerName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 p-6 font-sans">
    <div class="max-w-5xl mx-auto space-y-6">
        
        <div>
            <a href="credit.php" class="inline-block px-4 py-2 bg-white rounded shadow text-sm font-semibold text-gray-700 hover:bg-gray-50">
                &larr; Back to Credit List
            </a>
        </div>

        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($customerName) ?></h1>
                <p class="text-sm text-gray-500">Transaction History & Statement</p>
            </div>
        </div>

        <!-- Metric Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white p-5 rounded-xl shadow-sm border-t-4 border-blue-500">
                <span class="text-xs font-bold text-gray-400 tracking-wider uppercase">Total Credit</span>
                <p class="text-3xl font-extrabold text-blue-600 mt-2">&#8369;<?= number_format($totalCredit, 2) ?></p>
            </div>
            <div class="bg-white p-5 rounded-xl shadow-sm border-t-4 border-emerald-500">
                <span class="text-xs font-bold text-gray-400 tracking-wider uppercase">Total Payment</span>
                <p class="text-3xl font-extrabold text-emerald-600 mt-2">&#8369;<?= number_format($totalPayment, 2) ?></p>
            </div>
            <div class="bg-white p-5 rounded-xl shadow-sm border-t-4 border-red-500">
                <span class="text-xs font-bold text-gray-400 tracking-wider uppercase">Remaining Balance</span>
                <p class="text-3xl font-extrabold text-red-600 mt-2">&#8369;<?= number_format($balance, 2) ?></p>
            </div>
        </div>

        <!-- Transaction Ledger Table -->
        <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
            <h2 class="text-lg font-bold text-slate-800">Transaction History</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-amber-50 text-amber-900 text-xs font-bold uppercase tracking-wider">
                            <th class="p-3">Date</th>
                            <th class="p-3">Type</th>
                            <th class="p-3 text-center">Qty</th>
                            <th class="p-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="4" class="p-4 text-center text-gray-500">No transactions recorded for this customer.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $tx): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3 text-gray-700 font-medium">
                                        <?= htmlspecialchars($tx['transaction_date'] ?: date('Y-m-d', strtotime($tx['created_at']))) ?>
                                    </td>
                                    <td class="p-3">
                                        <?php if (strcasecmp($tx['payment_type'], 'Credit') === 0): ?>
                                            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-bold">Credit</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded text-xs font-bold">Payment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-center text-gray-600"><?= (int)$tx['qty'] ?></td>
                                    <td class="p-3 text-right font-bold <?= strcasecmp($tx['payment_type'], 'Credit') === 0 ? 'text-blue-600' : 'text-emerald-600' ?>">
                                        &#8369;<?= number_format($tx['total_amount'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</body>
</html>
