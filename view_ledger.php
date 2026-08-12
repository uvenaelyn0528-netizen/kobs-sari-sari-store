<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db.php';

$customer_id = $_GET['customer_id'] ?? null;
if (!$customer_id) {
    header("Location: stockout.php");
    exit();
}

// Flexible column value extractor
function getLedgerVal($row, $candidates, $keywords = [], $default = '-') {
    foreach ($candidates as $cand) {
        foreach ($row as $col_name => $val) {
            if (strtolower($col_name) === strtolower($cand) && $val !== null && trim((string)$val) !== '' && trim((string)$val) !== '-') {
                return $val;
            }
        }
    }
    foreach ($keywords as $kw) {
        foreach ($row as $col_name => $val) {
            $col_lower = strtolower($col_name);
            if ($kw !== 'id' && (substr($col_lower, -3) === '_id' || $col_lower === 'id')) {
                continue;
            }
            if (strpos($col_lower, strtolower($kw)) !== false && $val !== null && trim((string)$val) !== '' && trim((string)$val) !== '-') {
                return $val;
            }
        }
    }
    return $default;
}

// Column candidates definition
$code_candidates = ['product_code', 'code', 'barcode', 'item_code', 'pcode', 'prod_code', 'sku', 'bar_code'];
$code_keywords   = ['code', 'bar', 'sku'];

$desc_candidates = ['description', 'product_name', 'item_name', 'desc', 'details', 'particulars', 'remarks', 'title', 'item_desc', 'prod_name', 'product_desc', 'name', 'item'];
$desc_keywords   = ['desc', 'particular', 'remark', 'title', 'item_desc', 'prod_name', 'product_desc', 'name', 'detail'];

$type_candidates = ['transaction_type', 'type', 'tx_type', 'trans_type', 'payment_type', 'mode', 'payment_mode', 'status', 'pay_type', 'payment_method', 'method'];
$type_keywords   = ['type', 'mode', 'pay', 'method', 'kind'];

$qty_candidates  = ['qty', 'quantity', 'qty_sold', 'count', 'amount_qty'];
$qty_keywords    = ['qty', 'quant', 'count'];

$date_candidates = ['transaction_date', 'tx_date', 'date', 'created_at', 'datetime', 'timestamp', 'date_created'];
$date_keywords   = ['date', 'time', 'created'];

$amt_candidates  = ['amount', 'price', 'retail_price', 'subtotal', 'total', 'grand_total', 'cost', 'val'];
$amt_keywords    = ['amount', 'price', 'subtotal', 'total'];

// Detect customer details from customers table
$customer_name = 'Customer Ledger';
try {
    $c_stmt = $pdo->prepare("SELECT * FROM customers WHERE id::text = ? OR customer_id::text = ? LIMIT 1");
    $c_stmt->execute([(string)$customer_id, (string)$customer_id]);
    $c_data = $c_stmt->fetch(PDO::FETCH_ASSOC);
    if ($c_data) {
        $customer_name = getLedgerVal($c_data, ['name', 'customer_name', 'full_name', 'cust_name'], ['name'], 'Customer #' . $customer_id);
    }
} catch (Exception $e) {}

// Fetch product lookup map
$products_map = [];
$products_by_id = [];
try {
    $p_stmt = $pdo->query("SELECT * FROM products");
    while ($p = $p_stmt->fetch(PDO::FETCH_ASSOC)) {
        $p_code = getLedgerVal($p, $code_candidates, $code_keywords, null);
        $p_name = getLedgerVal($p, $desc_candidates, $desc_keywords, 'Product Item');
        
        $p_id = 0;
        foreach (['id', 'product_id', 'item_id', 'prod_id', 'p_id'] as $id_k) {
            if (isset($p[$id_k]) && is_numeric($p[$id_k]) && (int)$p[$id_k] > 0) {
                $p_id = (int)$p[$id_k];
                break;
            }
        }
        
        if ($p_code) $products_map[trim((string)$p_code)] = $p_name;
        if ($p_id > 0) $products_by_id[$p_id] = $p_name;
    }
} catch (Exception $e) {}

// Fetch Customer Transactions flexibly
$transactions = [];
$total_credit = 0;
$total_payment = 0;

try {
    $tx_stmt = $pdo->prepare("
        SELECT * FROM transactions 
        WHERE customer_id::text = ? 
           OR LOWER(customer_name) = LOWER(?) 
        ORDER BY id DESC
    ");
    $tx_stmt->execute([(string)$customer_id, $customer_name]);
    $transactions = $tx_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals including Partial and Full Payment types
    foreach ($transactions as $tx) {
        $type = strtolower(getLedgerVal($tx, $type_candidates, $type_keywords, ''));
        $amt  = (float)getLedgerVal($tx, $amt_candidates, $amt_keywords, 0);

        if ($type === 'credit') {
            $total_credit += $amt;
        } elseif (in_array($type, ['payment', 'partial payment', 'full payment', 'cash'])) {
            $total_payment += $amt;
        }
    }
} catch (Exception $e) {
    $error_message = "Error fetching ledger data: " . $e->getMessage();
}

$remaining_balance = $total_credit - $total_payment;

// Helper to resolve item description
function resolveLedgerItemDesc($tx, $products_map, $products_by_id, $desc_candidates, $desc_keywords) {
    $val = getLedgerVal($tx, $desc_candidates, $desc_keywords, null);
    if ($val !== null && $val !== '-' && !is_numeric($val) && trim((string)$val) !== '') {
        return $val;
    }

    foreach ($tx as $col => $cval) {
        $clow = strtolower($col);
        if ((in_array($clow, ['product_id', 'item_id', 'prod_id', 'p_id']) || (strpos($clow, 'product') !== false && strpos($clow, 'id') !== false)) && is_numeric($cval) && (int)$cval > 0) {
            $pid = (int)$cval;
            if (isset($products_by_id[$pid])) return $products_by_id[$pid];
        }
    }

    foreach ($tx as $col => $cval) {
        $clow = strtolower($col);
        if ((strpos($clow, 'code') !== false || strpos($clow, 'bar') !== false || strpos($clow, 'sku') !== false) && !empty(trim((string)$cval)) && trim((string)$cval) !== '-') {
            $pcode = trim((string)$cval);
            if (isset($products_map[$pcode])) return $products_map[$pcode];
        }
    }

    $t_type = strtolower(getLedgerVal($tx, ['transaction_type', 'type', 'tx_type', 'payment_type'], ['type'], ''));
    if (in_array($t_type, ['payment', 'partial payment', 'full payment'])) {
        return 'Account Payment (' . ucwords($t_type) . ')';
    }

    return '-';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Ledger - <?= htmlspecialchars($customer_name) ?></title>
    <style>
        :root {
            --primary-blue: #4f46e5;
            --card-border: #e2e8f0;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 20px;
            color: var(--text-dark);
        }

        .back-btn-container { margin-bottom: 15px; }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background-color: #ffffff;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            color: var(--text-dark);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background-color: #f8fafc;
            color: var(--primary-blue);
        }

        .header-title {
            margin: 0 0 4px 0;
            font-size: 26px;
            font-weight: 800;
        }

        .header-subtitle {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border-top: 5px solid transparent;
        }

        .stat-card.blue { border-top-color: #3b82f6; }
        .stat-card.green { border-top-color: #22c55e; }
        .stat-card.red { border-top-color: #ef4444; }

        .stat-card .title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .stat-card .amount { font-size: 28px; font-weight: 800; }
        .stat-card.blue .amount { color: #1d4ed8; }
        .stat-card.green .amount { color: #15803d; }
        .stat-card.red .amount { color: #b91c1c; }

        .panel-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            border: 1px solid var(--card-border);
        }

        .panel-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 18px 0;
        }

        .ledger-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .ledger-table th {
            background-color: #fef9c3;
            color: #713f12;
            text-align: left;
            padding: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .ledger-table td {
            padding: 12px;
            border-bottom: 1px solid var(--card-border);
            vertical-align: middle;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge.credit { background: #dbeafe; color: #1e40af; }
        .badge.payment { background: #dcfce7; color: #15803d; }
        .badge.partial-payment { background: #ccfbf1; color: #115e59; }
        .badge.full-payment { background: #a7f3d0; color: #065f46; }
    </style>
</head>
<body>

<div class="back-btn-container">
    <a href="Credit.php" class="back-btn">&larr; Back to Credit List</a>
</div>

<h1 class="header-title"><?= htmlspecialchars($customer_name) ?></h1>
<div class="header-subtitle">Transaction History & Statement</div>

<!-- Stats Overview -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="title">Total Credit</div>
        <div class="amount">₱<?= number_format($total_credit, 2) ?></div>
    </div>
    <div class="stat-card green">
        <div class="title">Total Payment</div>
        <div class="amount">₱<?= number_format($total_payment, 2) ?></div>
    </div>
    <div class="stat-card red">
        <div class="title">Remaining Balance</div>
        <div class="amount">₱<?= number_format($remaining_balance, 2) ?></div>
    </div>
</div>

<!-- Ledger Table -->
<div class="panel-card">
    <h2 class="panel-title">Transaction History</h2>
    <div style="overflow-x: auto;">
        <table class="ledger-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Item / Description</th>
                    <th>Qty</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #94a3b8; padding: 20px;">No transaction history found for this customer.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $tx): 
                        $t_date = getLedgerVal($tx, $date_candidates, $date_keywords, '-');
                        $t_type = getLedgerVal($tx, $type_candidates, $type_keywords, 'Credit');
                        $t_desc = resolveLedgerItemDesc($tx, $products_map, $products_by_id, $desc_candidates, $desc_keywords);
                        $t_qty  = getLedgerVal($tx, $qty_candidates, $qty_keywords, 1);
                        $t_amt  = getLedgerVal($tx, $amt_candidates, $amt_keywords, 0);

                        $t_type_lower = strtolower($t_type);
                        if ($t_type_lower === 'credit') {
                            $badge_cls = 'credit';
                        } elseif ($t_type_lower === 'partial payment') {
                            $badge_cls = 'partial-payment';
                        } elseif ($t_type_lower === 'full payment') {
                            $badge_cls = 'full-payment';
                        } else {
                            $badge_cls = 'payment';
                        }
                    ?>
                        <tr>
                            <td><?= htmlspecialchars(date('Y-m-d', strtotime($t_date))) ?></td>
                            <td><span class="badge <?= $badge_cls ?>"><?= htmlspecialchars($t_type) ?></span></td>
                            <td><?= htmlspecialchars($t_desc) ?></td>
                            <td><?= htmlspecialchars($t_qty) ?></td>
                            <td><strong>₱<?= number_format((float)$t_amt, 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
