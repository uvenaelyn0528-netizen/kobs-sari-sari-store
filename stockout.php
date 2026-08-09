<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db.php';

$today = date('Y-m-d');
$error_message = '';

// --- DETECT COLUMNS IN 'TRANSACTIONS' TABLE (HANDLING GENERATED & NOT NULL CONSTRAINTS) ---
$all_tx_cols = [];
$insertable_tx_cols = [];
$not_null_cols = [];

try {
    $col_stmt = $pdo->query("
        SELECT column_name, is_generated, is_insertable_into, is_nullable, column_default 
        FROM information_schema.columns 
        WHERE table_name = 'transactions'
    ");
    while ($row = $col_stmt->fetch(PDO::FETCH_ASSOC)) {
        $cname = strtolower($row['column_name']);
        $all_tx_cols[] = $cname;
        
        $is_gen = strtoupper($row['is_generated'] ?? 'NEVER');
        $is_ins = strtoupper($row['is_insertable_into'] ?? 'YES');
        
        // Exclude generated columns from insert operations
        if ($is_gen !== 'ALWAYS' && $is_ins !== 'NO' && $cname !== 'total_amount') {
            $insertable_tx_cols[] = $cname;
            if (strtoupper($row['is_nullable']) === 'NO' && $row['column_default'] === null && $cname !== 'id') {
                $not_null_cols[] = $cname;
            }
        }
    }
} catch (Exception $e) {}

// Fallback if information_schema query returns empty
if (empty($all_tx_cols)) {
    try {
        $col_stmt = $pdo->query("SELECT * FROM transactions LIMIT 1");
        for ($i = 0; $i < $col_stmt->columnCount(); $i++) {
            $meta = $col_stmt->getColumnMeta($i);
            if ($meta && isset($meta['name'])) {
                $cname = strtolower($meta['name']);
                $all_tx_cols[] = $cname;
                if ($cname !== 'total_amount') {
                    $insertable_tx_cols[] = $cname;
                }
            }
        }
    } catch (Exception $e) {}
}

// Map column getters for INSERT queries
$getInsertCol = function($candidates) use ($insertable_tx_cols) {
    foreach ($candidates as $cand) {
        if (in_array(strtolower($cand), $insertable_tx_cols)) {
            return $cand;
        }
    }
    return null;
};

// Map column getters for SELECT queries
$getSelectCol = function($candidates) use ($all_tx_cols) {
    foreach ($candidates as $cand) {
        if (in_array(strtolower($cand), $all_tx_cols)) {
            return $cand;
        }
    }
    return null;
};

// Target insertable columns
$col_code_ins      = $getInsertCol(['product_code', 'item_code', 'barcode', 'code']);
$col_date_ins      = $getInsertCol(['transaction_date', 'tx_date', 'date', 'created_at']);
$col_qty_ins       = $getInsertCol(['qty', 'quantity', 'qty_sold']);
$col_type_ins      = $getInsertCol(['transaction_type', 'type', 'tx_type', 'remarks']);
$col_cust_ins      = $getInsertCol(['customer_name', 'customer', 'name']);
$col_desc_ins      = $getInsertCol(['description', 'item_name', 'details']);
$col_amt_ins       = $getInsertCol(['amount', 'price', 'unit_price', 'total', 'selling_price']);
$col_buy_price_ins = $getInsertCol(['buy_price', 'cost_price', 'cost', 'purchase_price', 'supplier_price']);

// Target selectable columns for stats and history
$col_code_sel = $getSelectCol(['product_code', 'item_code', 'barcode', 'code']);
$col_date_sel = $getSelectCol(['transaction_date', 'tx_date', 'date', 'created_at']);
$col_qty_sel  = $getSelectCol(['qty', 'quantity', 'qty_sold']);
$col_type_sel = $getSelectCol(['transaction_type', 'type', 'tx_type', 'remarks']);
$col_cust_sel = $getSelectCol(['customer_name', 'customer', 'name']);
$col_desc_sel = $getSelectCol(['description', 'item_name', 'details']);
$col_amt_sel  = $getSelectCol(['total_amount', 'amount', 'price', 'total']);


// --- FETCH CUSTOMER NAMES FOR DROPDOWN ---
$customer_list = [];
try {
    if ($col_cust_sel) {
        $c_stmt = $pdo->query("SELECT DISTINCT $col_cust_sel FROM transactions WHERE $col_cust_sel IS NOT NULL AND $col_cust_sel != ''");
        while ($row = $c_stmt->fetch(PDO::FETCH_NUM)) {
            if (!empty($row[0])) {
                $customer_list[] = trim($row[0]);
            }
        }
    }
    $customer_list = array_values(array_unique($customer_list));
} catch (Exception $e) {}


// --- HANDLE BATCH TRANSACTION SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_batch_transaction'])) {
    $tx_type = $_POST['tx_type'] ?? 'Cash';
    $customer_name = trim($_POST['customer_name'] ?? '');
    $tx_date = $_POST['tx_date'] ?? $today;
    $items_raw = $_POST['items_payload'] ?? '[]';
    $items = json_decode($items_raw, true);

    if (!empty($items) && is_array($items)) {
        try {
            $pdo->beginTransaction();

            $fields = [];
            $placeholders = [];
            
            if ($col_code_ins)      { $fields[] = $col_code_ins;      $placeholders[] = ':code'; }
            if ($col_date_ins)      { $fields[] = $col_date_ins;      $placeholders[] = ':tdate'; }
            if ($col_qty_ins)       { $fields[] = $col_qty_ins;       $placeholders[] = ':qty'; }
            if ($col_type_ins)      { $fields[] = $col_type_ins;      $placeholders[] = ':ttype'; }
            if ($col_cust_ins)      { $fields[] = $col_cust_ins;      $placeholders[] = ':cname'; }
            if ($col_desc_ins)      { $fields[] = $col_desc_ins;      $placeholders[] = ':desc'; }
            if ($col_amt_ins)       { $fields[] = $col_amt_ins;       $placeholders[] = ':amount'; }
            if ($col_buy_price_ins) { $fields[] = $col_buy_price_ins; $placeholders[] = ':buy_price'; }

            // Ensure all NOT NULL schema columns without defaults are captured in INSERT
            foreach ($not_null_cols as $nn_col) {
                if (!in_array($nn_col, $fields)) {
                    $fields[] = $nn_col;
                    $placeholders[] = ':' . $nn_col;
                }
            }

            $sql = "INSERT INTO transactions (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);

            foreach ($items as $item) {
                $barcode = trim($item['code']);
                $qty = (int)$item['qty'];
                $price = (float)$item['price'];
                $buy_price = (float)($item['buy_price'] ?? 0);
                $amount = $qty * $price;
                $desc = $item['name'] ?? 'Product Item';

                $binds = [];
                if ($col_code_ins)      $binds[':code']      = $barcode;
                if ($col_date_ins)      $binds[':tdate']     = $tx_date;
                if ($col_qty_ins)       $binds[':qty']       = $qty;
                if ($col_type_ins)      $binds[':ttype']     = $tx_type;
                if ($col_cust_ins)      $binds[':cname']     = ($tx_type === 'Credit' || $tx_type === 'Payment') ? $customer_name : null;
                if ($col_desc_ins)      $binds[':desc']      = $desc;
                if ($col_amt_ins)       $binds[':amount']    = $amount;
                if ($col_buy_price_ins) $binds[':buy_price'] = $buy_price;

                // Provide safe default values for unhandled NOT NULL columns
                foreach ($not_null_cols as $nn_col) {
                    $p_key = ':' . $nn_col;
                    if (!array_key_exists($p_key, $binds)) {
                        if (strpos($nn_col, 'price') !== false || strpos($nn_col, 'amt') !== false || strpos($nn_col, 'cost') !== false || strpos($nn_col, 'qty') !== false) {
                            $binds[$p_key] = 0;
                        } else {
                            $binds[$p_key] = '';
                        }
                    }
                }

                $stmt->execute($binds);

                // Deduct inventory quantity if not a pure payment
                if ($tx_type !== 'Payment') {
                    try {
                        $deductStmt = $pdo->prepare("
                            UPDATE products 
                            SET quantity = quantity - :qty 
                            WHERE product_code = :barcode 
                               OR barcode = :barcode 
                               OR item_code = :barcode
                        ");
                        $deductStmt->execute([':qty' => $qty, ':barcode' => $barcode]);
                    } catch (Exception $ex) {
                        try {
                            $deductStmt = $pdo->prepare("
                                UPDATE products 
                                SET remaining_qty = remaining_qty - :qty 
                                WHERE product_code = :barcode OR barcode = :barcode
                            ");
                            $deductStmt->execute([':qty' => $qty, ':barcode' => $barcode]);
                        } catch (Exception $ex2) {}
                    }
                }
            }

            $pdo->commit();
            header("Location: stockout.php?success=1");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Transaction failed: " . $e->getMessage();
        }
    } else {
        $error_message = "No items scanned in the transaction cart.";
    }
}

// --- HANDLE DELETION ---
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $del = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
    $del->execute([$delete_id]);
    header("Location: stockout.php");
    exit();
}

// --- FETCH DASHBOARD TOTALS ---
$total_cash_today = 0;
$payment_today = 0;
$cash_sales_today = 0;
$total_credit = 0;

try {
    if ($col_amt_sel && $col_date_sel && $col_type_sel) {
        $stmt = $pdo->prepare("SELECT SUM($col_amt_sel) FROM transactions WHERE $col_date_sel = ? AND $col_type_sel = 'Cash'");
        $stmt->execute([$today]);
        $cash_sales_today = (float)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT SUM($col_amt_sel) FROM transactions WHERE $col_date_sel = ? AND $col_type_sel = 'Payment'");
        $stmt->execute([$today]);
        $payment_today = (float)$stmt->fetchColumn();

        $total_cash_today = $cash_sales_today + $payment_today;

        $stmt = $pdo->query("SELECT SUM($col_amt_sel) FROM transactions WHERE $col_type_sel = 'Credit'");
        $total_credit = (float)$stmt->fetchColumn();
    }
} catch (Exception $e) {}

// --- FETCH PRODUCT LIST FOR BARCODE RESOLUTION ---
$products_map = [];
try {
    $prod_stmt = $pdo->query("SELECT * FROM products");
    while ($p = $prod_stmt->fetch(PDO::FETCH_ASSOC)) {
        $code = $p['product_code'] ?? $p['barcode'] ?? $p['item_code'] ?? $p['code'] ?? null;
        $name = $p['product_name'] ?? $p['item_name'] ?? $p['description'] ?? 'Product Item';
        $price = $p['retail_price'] ?? $p['selling_price'] ?? $p['price'] ?? $p['unit_price'] ?? 0;
        $buy_price = $p['buy_price'] ?? $p['cost_price'] ?? $p['cost'] ?? $p['purchase_price'] ?? 0;

        if ($code !== null && trim((string)$code) !== '') {
            $products_map[trim((string)$code)] = [
                'name'      => $name,
                'price'     => (float)$price,
                'buy_price' => (float)$buy_price
            ];
        }
    }
} catch (Exception $e) {}

// --- FETCH RECENT TRANSACTIONS ---
$transactions = [];
try {
    $tx_stmt = $pdo->query("SELECT * FROM transactions ORDER BY id DESC LIMIT 50");
    $transactions = $tx_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOBS COOP - Stock Out & POS</title>
    <style>
        :root {
            --primary-blue: #4f46e5;
            --primary-hover: #4338ca;
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

        .back-btn-container {
            margin-bottom: 15px;
        }

        .back-to-store-btn {
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

        .back-to-store-btn:hover {
            background-color: #f8fafc;
            border-color: #cbd5e1;
            color: var(--primary-blue);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border-top: 5px solid transparent;
        }

        .stat-card.yellow { border-top-color: #eab308; }
        .stat-card.green { border-top-color: #22c55e; }
        .stat-card.blue { border-top-color: #3b82f6; }
        .stat-card.orange { border-top-color: #f97316; }

        .stat-card .title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .stat-card .amount {
            font-size: 24px;
            font-weight: 800;
        }

        .stat-card.yellow .amount { color: #854d0e; }
        .stat-card.green .amount { color: #15803d; }
        .stat-card.blue .amount { color: #1d4ed8; }
        .stat-card.orange .amount { color: #c2410c; }

        .main-container {
            display: grid;
            grid-template-columns: 420px 1fr;
            gap: 20px;
            align-items: start;
        }

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
            color: var(--text-dark);
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: var(--primary-blue);
        }

        .scan-row {
            display: grid;
            grid-template-columns: 1fr 80px 45px;
            gap: 8px;
            align-items: end;
        }

        .add-btn {
            background-color: var(--primary-blue);
            color: white;
            border: none;
            height: 38px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            font-size: 16px;
        }

        .add-btn:hover {
            background-color: var(--primary-hover);
        }

        .cart-box {
            margin: 15px 0;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            max-height: 220px;
            overflow-y: auto;
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .cart-table th {
            background: #e2e8f0;
            padding: 8px 10px;
            text-align: left;
            font-weight: 700;
            font-size: 11px;
            color: #475569;
        }

        .cart-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            background: white;
        }

        .cart-delete-btn {
            background: #fee2e2;
            color: #ef4444;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            padding: 2px 6px;
        }

        .cart-summary {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .cart-summary .label {
            font-size: 13px;
            font-weight: 600;
            color: #1e40af;
        }

        .cart-summary .total-value {
            font-size: 20px;
            font-weight: 800;
            color: #1e3a8a;
        }

        .process-btn {
            width: 100%;
            background-color: #4f46e5;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .process-btn:hover {
            background-color: #4338ca;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 10px;
        }

        .history-table th {
            background-color: #fef9c3;
            color: #713f12;
            text-align: left;
            padding: 10px 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .history-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        .badge-type {
            background-color: #dcfce7;
            color: #15803d;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .action-del {
            color: #ef4444;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <div class="back-btn-container">
        <a href="store.php" class="back-to-store-btn">
            &larr; Back to Store
        </a>
    </div>

    <!-- ALERTS -->
    <?php if (!empty($error_message)): ?>
        <div style="background-color: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 15px; font-weight: 600;">
            ⚠️ <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div style="background-color: #f0fdf4; border: 1px solid #86efac; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 15px; font-weight: 600;">
            ✅ Transaction processed successfully!
        </div>
    <?php endif; ?>

    <!-- STAT CARDS -->
    <div class="stats-grid">
        <div class="stat-card yellow">
            <div class="title">Total Cash On Hand (Today)</div>
            <div class="amount">₱<?php echo number_format($total_cash_today, 2); ?></div>
        </div>
        <div class="stat-card green">
            <div class="title">Payment For Today</div>
            <div class="amount">₱<?php echo number_format($payment_today, 2); ?></div>
        </div>
        <div class="stat-card blue">
            <div class="title">Cash Sales (Today)</div>
            <div class="amount">₱<?php echo number_format($cash_sales_today, 2); ?></div>
        </div>
        <div class="stat-card orange">
            <div class="title">Total Credit Accumulation</div>
            <div class="amount">₱<?php echo number_format($total_credit, 2); ?></div>
        </div>
    </div>

    <!-- MAIN WORKSPACE -->
    <div class="main-container">
        
        <!-- TRANSACTION FORM -->
        <div class="panel-card">
            <h2 class="panel-title">Record Transaction</h2>
            
            <form id="transactionForm" method="POST" action="stockout.php">
                <input type="hidden" name="process_batch_transaction" value="1">
                <input type="hidden" name="items_payload" id="items_payload" value="[]">

                <div class="form-group">
                    <label>Transaction Type</label>
                    <select name="tx_type" id="tx_type" class="form-control" onchange="toggleCustomerField()">
                        <option value="Cash">Cash</option>
                        <option value="Credit">Credit</option>
                        <option value="Payment">Payment</option>
                    </select>
                </div>

                <div class="form-group" id="customer_group" style="display: none;">
                    <label>Customer Name</label>
                    <input type="text" name="customer_name" id="customer_name_input" class="form-control" list="customer_dropdown_list" placeholder="Select or type customer name" autocomplete="off">
                    <datalist id="customer_dropdown_list">
                        <?php foreach ($customer_list as $cname): ?>
                            <option value="<?php echo htmlspecialchars($cname); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="form-group">
                    <label>Product Barcode / Code</label>
                    <div class="scan-row">
                        <input type="text" id="barcode_input" class="form-control" placeholder="Scan barcode or enter code" autofocus autocomplete="off">
                        <div>
                            <input type="number" id="qty_input" class="form-control" value="1" min="1">
                        </div>
                        <button type="button" class="add-btn" onclick="addItemFromScan()" title="Add to transaction">+</button>
                    </div>
                </div>

                <div class="cart-box">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Item / Code</th>
                                <th style="width: 40px;">Qty</th>
                                <th style="width: 65px;">Price</th>
                                <th style="width: 75px;">Subtotal</th>
                                <th style="width: 25px;"></th>
                            </tr>
                        </thead>
                        <tbody id="cart_body">
                            <tr>
                                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 15px;">No items scanned yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="cart-summary">
                    <span class="label">Total Amount:</span>
                    <span class="total-value" id="grand_total_display">₱0.00</span>
                </div>

                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="tx_date" class="form-control" value="<?php echo $today; ?>">
                </div>

                <button type="submit" class="process-btn" onclick="return validateBeforeSubmit()">Process Transaction</button>
            </form>
        </div>

        <!-- HISTORY LOG -->
        <div class="panel-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 class="panel-title" style="margin: 0;">Transaction History Log</h2>
                <input type="text" placeholder="Search transactions..." class="form-control" style="width: 220px;" onkeyup="filterHistory(this.value)">
            </div>

            <table class="history-table" id="history_table">
                <thead>
                    <tr>
                        <th>CODE</th>
                        <th>DATE</th>
                        <th>QTY</th>
                        <th>TYPE</th>
                        <th>CUSTOMER NAME</th>
                        <th>DESCRIPTION</th>
                        <th>AMOUNT</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transactions)): ?>
                        <?php foreach ($transactions as $tx): ?>
                            <?php 
                                $t_code = ($col_code_sel && isset($tx[$col_code_sel])) ? $tx[$col_code_sel] : ($tx['product_code'] ?? $tx['item_code'] ?? $tx['barcode'] ?? $tx['code'] ?? '-');
                                $t_date = ($col_date_sel && isset($tx[$col_date_sel])) ? $tx[$col_date_sel] : ($tx['transaction_date'] ?? $tx['date'] ?? '-');
                                $t_qty  = ($col_qty_sel && isset($tx[$col_qty_sel]))   ? $tx[$col_qty_sel]  : ($tx['qty'] ?? $tx['quantity'] ?? '-');
                                $t_type = ($col_type_sel && isset($tx[$col_type_sel])) ? $tx[$col_type_sel] : ($tx['transaction_type'] ?? $tx['type'] ?? '-');
                                $t_cust = ($col_cust_sel && isset($tx[$col_cust_sel])) ? $tx[$col_cust_sel] : ($tx['customer_name'] ?? $tx['customer'] ?? '-');
                                $t_desc = ($col_desc_sel && isset($tx[$col_desc_sel])) ? $tx[$col_desc_sel] : ($tx['description'] ?? $tx['item_name'] ?? '-');
                                $t_amt  = ($col_amt_sel && isset($tx[$col_amt_sel]))   ? $tx[$col_amt_sel]  : ($tx['amount'] ?? $tx['total_amount'] ?? 0);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$t_code); ?></td>
                                <td><?php echo htmlspecialchars((string)$t_date); ?></td>
                                <td><?php echo htmlspecialchars((string)$t_qty); ?></td>
                                <td><span class="badge-type"><?php echo htmlspecialchars((string)$t_type); ?></span></td>
                                <td><em><?php echo htmlspecialchars((string)($t_cust ?: '-')); ?></em></td>
                                <td><?php echo htmlspecialchars((string)$t_desc); ?></td>
                                <td><strong>₱<?php echo number_format((float)$t_amt, 2); ?></strong></td>
                                <td>
                                    <a href="stockout.php?delete_id=<?php echo $tx['id']; ?>" class="action-del" onclick="return confirm('Delete this record?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: #94a3b8; padding: 20px;">No transactions recorded today.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <script>
        const productDatabase = <?php echo json_encode($products_map); ?>;
        let cartItems = [];

        const barcodeInput = document.getElementById('barcode_input');
        const qtyInput = document.getElementById('qty_input');
        const cartBody = document.getElementById('cart_body');
        const grandTotalDisplay = document.getElementById('grand_total_display');
        const itemsPayload = document.getElementById('items_payload');

        barcodeInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addItemFromScan();
            }
        });

        function addItemFromScan() {
            const code = barcodeInput.value.trim();
            const qty = parseInt(qtyInput.value) || 1;

            if (!code) return;

            let name = "Scanned Item (" + code + ")";
            let price = 0.00;
            let buyPrice = 0.00;

            if (productDatabase[code]) {
                name = productDatabase[code].name;
                price = parseFloat(productDatabase[code].price) || 0.00;
                buyPrice = parseFloat(productDatabase[code].buy_price) || 0.00;
            } else {
                const manualPrice = prompt(`Item code "${code}" not found in database.\nEnter selling price per unit:`, "0");
                if (manualPrice === null) return;
                price = parseFloat(manualPrice) || 0;
            }

            const existingIndex = cartItems.findIndex(item => item.code === code);
            if (existingIndex > -1) {
                cartItems[existingIndex].qty += qty;
            } else {
                cartItems.push({
                    code: code,
                    name: name,
                    price: price,
                    buy_price: buyPrice,
                    qty: qty
                });
            }

            renderCart();

            barcodeInput.value = '';
            qtyInput.value = '1';
            barcodeInput.focus();
        }

        function removeCartItem(index) {
            cartItems.splice(index, 1);
            renderCart();
        }

        function renderCart() {
            if (cartItems.length === 0) {
                cartBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 15px;">No items scanned yet.</td></tr>`;
                grandTotalDisplay.innerText = "₱0.00";
                itemsPayload.value = "[]";
                return;
            }

            let html = '';
            let grandTotal = 0;

            cartItems.forEach((item, index) => {
                const subtotal = item.qty * item.price;
                grandTotal += subtotal;

                html += `
                    <tr>
                        <td>
                            <strong>${item.code}</strong><br>
                            <small style="color: #64748b;">${item.name}</small>
                        </td>
                        <td>${item.qty}</td>
                        <td>₱${item.price.toFixed(2)}</td>
                        <td><strong>₱${subtotal.toFixed(2)}</strong></td>
                        <td>
                            <button type="button" class="cart-delete-btn" onclick="removeCartItem(${index})">✕</button>
                        </td>
                    </tr>
                `;
            });

            cartBody.innerHTML = html;
            grandTotalDisplay.innerText = "₱" + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            itemsPayload.value = JSON.stringify(cartItems);
        }

        function toggleCustomerField() {
            const txType = document.getElementById('tx_type').value;
            const customerGroup = document.getElementById('customer_group');
            customerGroup.style.display = (txType === 'Credit' || txType === 'Payment') ? 'block' : 'none';
        }

        function validateBeforeSubmit() {
            if (cartItems.length === 0) {
                alert("Please scan at least one item before processing the transaction.");
                return false;
            }
            return true;
        }

        function filterHistory(query) {
            const filter = query.toLowerCase();
            const rows = document.querySelectorAll("#history_table tbody tr");
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? "" : "none";
            });
        }
    </script>
</body>
</html>
