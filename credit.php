<?php
session_start();
require_once 'db.php';

// Allow 'admin', 'tindera', and 'viewer' to access
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'tindera', 'viewer'])) {
    header("Location: login.php");
    exit();
}

include 'header.php';

// -------------------------------------------------------------
// DYNAMIC CREDIT LEDGER AGREGATOR & TRANSACTION LOG SCANNER
// -------------------------------------------------------------
$customer_credits = [];
$total_store_credit = 0;
$total_payment = 0;
$total_balance = 0;

try {
    // 1. Check and read from dedicated 'credits' table if present
    $has_credits_table = false;
    try {
        $chk = $pdo->query("SELECT 1 FROM credits LIMIT 1");
        if ($chk) $has_credits_table = true;
    } catch (PDOException $e) {
        $has_credits_table = false;
    }

    if ($has_credits_table) {
        $stmt = $pdo->query("SELECT * FROM credits ORDER BY customer_name ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $c_name = trim($row['customer_name'] ?? 'Walk-in Credit');
            if ($c_name === '' || $c_name === '-') $c_name = 'Walk-in Credit';
            
            $s_credit = (float)($row['store_credit'] ?? 0);
            $s_payment = (float)($row['total_payment'] ?? 0);
            $s_balance = $s_credit - $s_payment;

            $customer_credits[$c_name] = [
                'customer_name' => $c_name,
                'store_credit' => $s_credit,
                'total_payment' => $s_payment,
                'total_balance' => $s_balance
            ];
        }
    }

    // 2. Scan stockout / transaction logs for Credit transaction types
    $log_tables = ['stockouts', 'stockout', 'transactions', 'sales'];
    foreach ($log_tables as $tbl) {
        try {
            $logStmt = $pdo->query("SELECT * FROM \"$tbl\"");
            $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($logs)) {
                foreach ($logs as $log) {
                    $log_lower = array_change_key_case($log, CASE_LOWER);
                    
                    // Identify credit transactions
                    $type = strtolower(trim($log_lower['type'] ?? $log_lower['transaction_type'] ?? $log_lower['remarks'] ?? ''));
                    if (strpos($type, 'credit') !== false || strpos($type, 'utang') !== false) {
                        
                        // Extract customer name or fallback if '-'
                        $c_name = trim($log_lower['customer_name'] ?? $log_lower['customer'] ?? '');
                        if ($c_name === '' || $c_name === '-') {
                            $c_name = 'Walk-in Credit';
                        }

                        // Extract amount
                        $amt = 0;
                        foreach ($log_lower as $k => $v) {
                            if (strpos($k, 'amount') !== false || strpos($k, 'total') !== false || strpos($k, 'price') !== false) {
                                $clean = preg_replace('/[^0-9.-]/', '', (string)$v);
                                if ($clean !== '' && is_numeric($clean)) {
                                    $amt = (float)$clean;
                                    break;
                                }
                            }
                        }

                        if ($amt > 0) {
                            if (!isset($customer_credits[$c_name])) {
                                $customer_credits[$c_name] = [
                                    'customer_name' => $c_name,
                                    'store_credit' => 0,
                                    'total_payment' => 0,
                                    'total_balance' => 0
                                ];
                            }
                            $customer_credits[$c_name]['store_credit'] += $amt;
                            $customer_credits[$c_name]['total_balance'] = $customer_credits[$c_name]['store_credit'] - $customer_credits[$c_name]['total_payment'];
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            continue;
        }
    }

    // Calculate overall summary totals
    foreach ($customer_credits as $cc) {
        $total_store_credit += $cc['store_credit'];
        $total_payment += $cc['total_payment'];
        $total_balance += $cc['total_balance'];
    }

} catch (PDOException $e) {
    $customer_credits = [];
}
?>

<div class="container mx-auto px-4 py-8">
    <!-- Back Button -->
    <div class="mb-6">
        <a href="stockout.php" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-semibold rounded-md shadow-sm transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Store
        </a>
    </div>

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">KOBS Sari-Sari Store Credit list</h1>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl shadow-md border-t-4 border-blue-500">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">TOTAL STORE CREDIT</p>
            <p class="text-3xl font-extrabold text-blue-600 mt-2">₱<?= number_format($total_store_credit, 2) ?></p>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md border-t-4 border-emerald-500">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">TOTAL PAYMENT</p>
            <p class="text-3xl font-extrabold text-emerald-600 mt-2">₱<?= number_format($total_payment, 2) ?></p>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md border-t-4 border-red-500">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">TOTAL BALANCE</p>
            <p class="text-3xl font-extrabold text-red-600 mt-2">₱<?= number_format($total_balance, 2) ?></p>
        </div>
    </div>

    <!-- Customer Credit Ledger Table -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-6 border-b border-gray-200 flex flex-col md:flex-row justify-between items-center gap-4">
            <h2 class="text-lg font-bold text-gray-800">Customer Credit Ledger</h2>
            <div class="w-full md:w-72">
                <input type="text" id="searchCustomer" placeholder="Search customer..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200">
                <thead class="bg-amber-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Customer Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Store Credit</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Total Payment</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Total Balance</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 text-sm" id="creditTableBody">
                    <?php if (!empty($customer_credits)): ?>
                        <?php foreach ($customer_credits as $cust): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap font-semibold text-gray-900"><?= htmlspecialchars($cust['customer_name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-blue-600 font-bold">₱<?= number_format($cust['store_credit'], 2) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-emerald-600 font-bold">₱<?= number_format($cust['total_payment'], 2) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-red-600 font-bold">₱<?= number_format($cust['total_balance'], 2) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                    <a href="customer_details.php?name=<?= urlencode($cust['customer_name']) ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1.5 rounded-md shadow transition inline-block font-medium">View Ledger</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500 italic">
                                Walang nakitang rekord ng utang.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.getElementById('searchCustomer').addEventListener('input', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#creditTableBody tr');
    
    rows.forEach(row => {
        let nameCell = row.cells[0];
        if (nameCell) {
            let nameText = nameCell.textContent.toLowerCase();
            row.style.display = nameText.includes(filter) ? '' : 'none';
        }
    });
});
</script>

</body>
</html>
