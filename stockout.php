<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db.php';

$today = date('Y-m-d');
$error_message = '';

// Safe 32-Bit Integer Helper (Prevents PostgreSQL integer overflow/FK violations)
function safeInt32($val, $fallback = null) {
    if (!is_numeric($val) || $val === null || $val === '' || $val === '-' || (int)$val === 0) return $fallback;
    $num = (float)$val;
    if ($num > 2147483647 || $num < -2147483648) {
        return $fallback;
    }
    return (int)$num;
}

// --- HELPER FUNCTION FOR FLEXIBLE ROW VALUE EXTRACTION ---
function getTxVal($row, $candidates, $keywords = [], $default = '-') {
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

// --- 1. INSPECT 'TRANSACTIONS' TABLE COLUMNS & DATA TYPES ---
$tx_columns = [];
$tx_col_types = [];
$generated_cols = [];

try {
    $col_stmt = $pdo->query("
        SELECT column_name, data_type, is_generated, generation_expression 
        FROM information_schema.columns 
        WHERE lower(table_name) = 'transactions' 
          AND table_schema = 'public'
    ");
    while ($r = $col_stmt->fetch(PDO::FETCH_ASSOC)) {
        $cname = $r['column_name'];
        $dtype = strtolower($r['data_type'] ?? 'text');
        $tx_columns[] = $cname;
        $tx_col_types[strtolower($cname)] = $dtype;
        
        $is_gen = strtoupper($r['is_generated'] ?? 'NEVER');
        if ($is_gen === 'ALWAYS' || !empty($r['generation_expression'])) {
            $generated_cols[] = strtolower($cname);
        }
    }
} catch (Exception $e) {}

function isIntColumn($cname, $tx_col_types) {
    $c_lower = strtolower($cname);
    if (!isset($tx_col_types[$c_lower])) return false;
    $dt = $tx_col_types[$c_lower];
    return (strpos($dt, 'int') !== false || strpos($dt, 'serial') !== false);
}

function findSingleColumn($candidates, $keywords, $tx_columns, $exclude_cols = []) {
    $tx_lower = array_map('strtolower', $tx_columns);
    $exclude_lower = array_map('strtolower', $exclude_cols);

    foreach ($tx_columns as $idx => $col) {
        $col_lower = $tx_lower[$idx];
        if (in_array($col_lower, $exclude_lower)) continue;

        foreach ($candidates as $cand) {
            if ($col_lower === strtolower($cand)) {
                return $col;
            }
        }
    }
    foreach ($tx_columns as $idx => $col) {
        $col_lower = $tx_lower[$idx];
        if (in_array($col_lower, $exclude_lower)) continue;

        foreach ($keywords as $kw) {
            if (strpos($col_lower, strtolower($kw)) !== false) {
                return $col;
            }
        }
    }
    return null;
}

// Column candidate field maps
$code_candidates = ['product_code', 'code', 'barcode', 'item_code', 'pcode', 'prod_code', 'sku', 'bar_code', 'upc', 'ean'];
$code_keywords   = ['code', 'bar', 'sku', 'upc', 'ean'];

$id_candidates   = ['product_id', 'item_id', 'prod_id', 'p_id'];
$id_keywords     = ['product_id', 'item_id'];

$desc_candidates = ['description', 'product_name', 'item_name', 'desc', 'details', 'particulars', 'remarks', 'title', 'item_desc', 'prod_name', 'product_desc'];
$desc_keywords   = ['desc', 'particular', 'remark', 'title', 'item_desc', 'prod_name', 'product_desc'];

$cust_candidates = ['customer_name', 'customer', 'client_name', 'client', 'cust_name', 'buyer_name', 'buyer'];
$cust_keywords   = ['cust', 'client', 'buyer'];

$type_candidates = ['transaction_type', 'type', 'tx_type', 'trans_type', 'payment_type', 'mode', 'payment_mode', 'status', 'pay_type'];
$type_keywords   = ['type', 'mode', 'pay'];

$qty_candidates  = ['qty', 'quantity', 'qty_sold', 'count', 'amount_qty', 'items_count'];
$qty_keywords    = ['qty', 'quant', 'count'];

$date_candidates = ['transaction_date', 'tx_date', 'date', 'created_at', 'datetime', 'timestamp', 'date_created', 'created_date'];
$date_keywords   = ['date', 'time', 'created'];

$amt_candidates  = ['amount', 'price', 'retail_price', 'subtotal', 'total', 'grand_total', 'cost', 'val', 'price_total'];
$amt_keywords    = ['amount', 'price', 'subtotal', 'total'];

$pk_col = $tx_columns[0] ?? 'id';

// --- 2. PRE-DETECT PRODUCTS & CUSTOMERS TABLES ---
$prod_qty_col = null;
$prod_code_col = null;

try {
    $p_cols_stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE lower(table_name) = 'products' AND table_schema = 'public'");
    $p_cols = $p_cols_stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($p_cols)) {
        $prod_qty_col  = findSingleColumn(['quantity', 'remaining_qty', 'qty', 'stock'], ['qty', 'stock', 'quantity'], $p_cols);
        $prod_code_col = findSingleColumn(['product_code', 'barcode', 'item_code', 'code'], ['code', 'bar'], $p_cols);
    }
} catch (Exception $e) {}

// --- MASTER CUSTOMER LIST INTEGRATION ---
$master_customer_list = [
    "Abrajano, Dandreb", "Abrajano, Victoria", "Abug, Milecha", "Abulencia, Dennes", 
    "Aguilo, Kim", "Aguindao, Omar", "Albia, Jhonmark", "Amoguis, Joshua Rheynaird", 
    "Ansing, Arje", "Ansing, Jose", "Araño, Ronelo", "Aromin, Dan Alekhine", 
    "Arroyo, Olcen", "Asuncion, Jonathan", "Badinas, Pedro", "Baguinon, Mark Aldrin", 
    "Balasa, Harvey", "Balbalang, Manny", "Baldicañas, Manuel", "Ballesteros, Emilio", 
    "Bambico, Michael", "Barlomento, Mark Anthony", "Beracis, Greg", "Bermudez, Liezel", 
    "Bonao, Ronald", "Borabod, Rogelio", "Bulawan, Mervin", "Caberio, Aljun", 
    "Caberio, John Angelo", "Calista, Ralph John", "Campo, Ederlito", "Cang, Eric", 
    "Canor, Arniel", "Capagalan, Nico", "Carabio, Dindo", "Caranyagan, Romeo", 
    "Casaña, Roger", "Catamora, Jounysis", "Codaste, Dino", "Colinares, Drexell John", 
    "Cordiner, Ryan Lyn", "Corpo (Peter, Marlo, Timot)", "Corpo (Kevin, Kelly & Dan)", 
    "Corpo (Dexil, Lingatong and others)", "Corpo (Kevin & Junmar)", "Cruz, Rolliber", 
    "Cuaresma, Jemson", "Dado, Eric", "Dadsa-ag, Peter Paul", "Daganio, Denmark", 
    "Daganio, Janus", "Daiz, Jhon Carlo", "Damage", "Daño, Juven", "Dapo, Angelo", 
    "Dela Cruz, Flordeliza", "Delalamon, Jovani", "Delfinado, Velcar", "Delos Santos, Jessy", 
    "Deloso, Rotchell", "Deocampo, Junlie", "Diez, Jems", "Doblon, Mario Francis", 
    "Dolorosa, Jerwen", "Dorimon, Wilson", "Dulfo, Erika Loren", "Ebano, Joseph", 
    "Elegado, Bernard", "Ensalada, Dionisio", "Enson, George", "Ercillo, Ersal", 
    "Escobal, Mark Jorel", "Espino, Apple", "Florante, Isaias", "Flores, Juancho", 
    "Formilles, Jerry", "Gagabuan, Arjay", "Gagujas, Marlou", "Gajol, Ginalyn", 
    "Garcia, Benny", "Genelaso, Elmer", "Gingco, Bhert Joy", "Golloso, Raymond", 
    "Guiao, Ashley", "Guimbaolibot, Jayson Anthony", "Hombria, Nestor", "Iligan, Rolly", 
    "Jimenez, Aljon", "Juntilla, Ranel", "Juros, Jessie", "Kobelco Junmar", 
    "Kobelco kelly", "Kobelco kevin", "Labotap, Reggie", "Laconsay, Kevin", 
    "Lazo, Mark Anthony", "Lingatong, Albert", "Lipranon, Jomar", "Lisondra, Dennis", 
    "Llemos, John Marvic", "Logroño, Gerundio", "Longatan, Mark Foustere", 
    "Macabalo, Adrian", "Macabenta, Jesreel", "Macabenta, Jessie", "Maque, Juluis", 
    "Mara, Pedro", "Marquito, Edwin", "Melendez, Duddy", "Monoy, Louien", 
    "Napoles, Aileen", "Ogrimen, Nonito", "Olasiman, Reynante", "Oliver, Nico", 
    "Ollay, Arnel", "Omapas, Antonino", "Omoso, Jing", "Orongan, Romel", 
    "Orongan, Jay", "Ortillo, Francisco", "Ortillo, Rico", "Pabelonia, Peter Jeffrey", 
    "Padriquez, Ronie", "Padual, Benjie", "Padulaga, Roldan", "Pagatpatan, Salvacion", 
    "Panungcat, Desiderio", "Parco, RV Niño", "Pelenio, Evelyn", "Picardal, Baldomero", 
    "Pinggoy, Ranil", "Pingos, Jovel", "Porton, Ronnel", "Princillo, Ramil", 
    "Quimbo", "Ramayramay, Jonathan", "Regajal, Rolando", "Reyes, Ricky", 
    "Rivera, Aldrin", "Rodolfo, Fernando", "Rosal, Napoleon", "Sabas, Jeffrey", 
    "Saga, Rezniel", "Saldivar, Jeamboy", "Salve, Levi", "Sandoval, Erwin", 
    "Santos, Mark James", "Saylag, Kieth", "Sia, Amor", "Sidaya, Jannel", 
    "Somoray, Rogelio", "Sulapas, Justine", "Taguba, Glenn", "Tarpin, Ma. Andrea", 
    "Tejero, Mark Anthony", "Tubil, Romeo", "Uveña, Elyn", "Uveña, Mario", 
    "Villarosa, Reynan Dave", "Yurong, Edmon"
];

$customers_data = [];
$customers_map  = []; // Lowercase Name -> Customer ID

// Fetch existing customers from database table to map IDs
try {
    $c_stmt = $pdo->query("SELECT * FROM customers ORDER BY 1 ASC");
    if ($c_stmt) {
        while ($crow = $c_stmt->fetch(PDO::FETCH_ASSOC)) {
            $cid = null;
            $cname = null;
            foreach ($crow as $k => $v) {
                $k_lower = strtolower($k);
                if (($k_lower === 'id' || $k_lower === 'customer_id' || $k_lower === 'cust_id') && is_numeric($v)) {
                    $cid = (int)$v;
                }
                if (($k_lower === 'name' || $k_lower === 'customer_name' || $k_lower === 'full_name' || $k_lower === 'fullname') && !empty(trim((string)$v))) {
                    $cname = trim((string)$v);
                }
            }
            if ($cname) {
                $customers_map[strtolower($cname)] = $cid;
            }
        }
    }
} catch (Exception $e) {}

// Consolidate master customer list and DB records
$all_customers_set = [];
foreach ($master_customer_list as $m_name) {
    $clean_name = rtrim(trim($m_name), ',');
    if ($clean_name !== '') {
        $all_customers_set[strtolower($clean_name)] = $clean_name;
    }
}

// Add any DB-only customer names to set
foreach ($customers_map as $low_name => $cid) {
    if (!isset($all_customers_set[$low_name])) {
        $all_customers_set[$low_name] = ucwords($low_name);
    }
}

// Build final customers data array
foreach ($all_customers_set as $low_name => $disp_name) {
    $cid = $customers_map[$low_name] ?? null;
    $customers_data[] = [
        'id'   => $cid,
        'name' => $disp_name
    ];
}

// Sort customer list alphabetically
usort($customers_data, function($a, $b) {
    return strnatcasecmp($a['name'], $b['name']);
});

// --- HANDLE BATCH TRANSACTION SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_batch_transaction'])) {
    $tx_type = $_POST['tx_type'] ?? 'Cash';
    $customer_name = trim($_POST['customer_name'] ?? '');
    $selected_cust_id = !empty($_POST['customer_id_val']) ? (int)$_POST['customer_id_val'] : null;
    $tx_date = $_POST['tx_date'] ?? $today;
    $items_raw = $_POST['items_payload'] ?? '[]';
    $items = json_decode($items_raw, true);

    // Dynamic resolution for customer_id
    if (!$selected_cust_id && !empty($customer_name)) {
        $c_lower = strtolower($customer_name);
        if (isset($customers_map[$c_lower])) {
            $selected_cust_id = $customers_map[$c_lower];
        }
    }

    if ($tx_type === 'Cash') {
        $selected_cust_id = null;
        $customer_name = '-';
    }

    if (!empty($items) && is_array($items)) {
        try {
            $pdo->beginTransaction();

            foreach ($items as $item) {
                $barcode    = trim((string)$item['code']);
                $prod_id    = (int)($item['id'] ?? 0);
                $qty        = (int)$item['qty'];
                $price      = (float)$item['price'];
                $buy_price  = (float)($item['buy_price'] ?? 0);
                $amount     = $qty * $price;
                $desc       = !empty($item['name']) ? trim($item['name']) : 'Product Item';

                $insert_data = [];

                foreach ($tx_columns as $col) {
                    $c = strtolower($col);

                    if ($c === strtolower($pk_col) || in_array($c, $generated_cols)) {
                        continue;
                    }

                    // Product Code / Barcode assignment
                    if (in_array($c, ['product_code', 'code', 'barcode', 'item_code', 'pcode', 'prod_code', 'sku', 'bar_code', 'upc', 'ean']) || strpos($c, 'code') !== false || strpos($c, 'barcode') !== false) {
                        $insert_data[$col] = $barcode;
                    }

                    // Item Description assignment
                    elseif (in_array($c, ['description', 'product_name', 'item_name', 'desc', 'details', 'particulars', 'remarks', 'title', 'item_desc', 'prod_name', 'product_desc']) || (strpos($c, 'desc') !== false && strpos($c, 'id') === false)) {
                        $insert_data[$col] = (string)$desc;
                    }

                    // Foreign Key Product ID
                    elseif (in_array($c, ['product_id', 'item_id', 'prod_id', 'p_id'])) {
                        $insert_data[$col] = ($prod_id > 0) ? $prod_id : null;
                    }

                    // Foreign Key Customer ID & Customer Name
                    elseif ($c === 'customer_id') {
                        $insert_data[$col] = ($selected_cust_id && $selected_cust_id > 0) ? $selected_cust_id : null;
                    }
                    elseif (in_array($c, ['customer_name', 'customer', 'client_name', 'client', 'cust_name', 'buyer_name', 'buyer']) || strpos($c, 'cust') !== false) {
                        if (substr($c, -3) === '_id') {
                            $insert_data[$col] = ($selected_cust_id && $selected_cust_id > 0) ? $selected_cust_id : null;
                        } else {
                            $insert_data[$col] = !empty($customer_name) ? $customer_name : '-';
                        }
                    }

                    // Quantities, Amounts, Prices, Date, Type
                    elseif (in_array($c, ['transaction_date', 'tx_date', 'date', 'created_at', 'datetime', 'timestamp', 'date_created', 'created_date']) || strpos($c, 'date') !== false) {
                        $insert_data[$col] = $tx_date;
                    }
                    elseif (in_array($c, ['qty', 'quantity', 'qty_sold', 'count', 'amount_qty', 'items_count']) || strpos($c, 'qty') !== false) {
                        $insert_data[$col] = $qty;
                    }
                    elseif (in_array($c, ['transaction_type', 'type', 'tx_type', 'trans_type', 'payment_type', 'mode', 'payment_mode', 'status', 'pay_type']) || strpos($c, 'type') !== false) {
                        $insert_data[$col] = $tx_type;
                    }
                    elseif (in_array($c, ['amount', 'subtotal', 'total', 'grand_total', 'val', 'price_total'])) {
                        $insert_data[$col] = $amount;
                    }
                    elseif (in_array($c, ['retail_price', 'selling_price', 'price', 'unit_price'])) {
                        $insert_data[$col] = $price;
                    }
                    elseif (in_array($c, ['buy_price', 'cost_price', 'cost', 'purchase_price'])) {
                        $insert_data[$col] = $buy_price;
                    }
                }

                // Default assignment for unmapped columns
                try {
                    $nn_stmt = $pdo->query("
                        SELECT column_name, data_type, is_nullable 
                        FROM information_schema.columns 
                        WHERE lower(table_name) = 'transactions' 
                          AND table_schema = 'public'
                    ");
                    while ($nn_row = $nn_stmt->fetch(PDO::FETCH_ASSOC)) {
                        $c_name = $nn_row['column_name'];
                        $c_lower = strtolower($c_name);

                        if (in_array($c_lower, $generated_cols) || $c_lower === strtolower($pk_col)) continue;

                        if (!array_key_exists($c_name, $insert_data)) {
                            $dtype = strtolower($nn_row['data_type']);
                            $is_nullable = strtoupper($nn_row['is_nullable']) === 'YES';

                            if (substr($c_lower, -3) === '_id' || $c_lower === 'customer_id' || $c_lower === 'product_id') {
                                $insert_data[$c_name] = null;
                            } elseif ($is_nullable) {
                                $insert_data[$c_name] = null;
                            } else {
                                if (strpos($dtype, 'int') !== false || strpos($dtype, 'num') !== false || strpos($dtype, 'dec') !== false || strpos($dtype, 'float') !== false) {
                                    $insert_data[$c_name] = 0;
                                } elseif (strpos($dtype, 'bool') !== false) {
                                    $insert_data[$c_name] = false;
                                } elseif (strpos($dtype, 'date') !== false || strpos($dtype, 'time') !== false) {
                                    $insert_data[$c_name] = $tx_date;
                                } else {
                                    $insert_data[$c_name] = '-';
                                }
                            }
                        }
                    }
                } catch (Exception $e) {}

                // STRICT FOREIGN KEY & DATA TYPE SANITIZATION
                foreach ($insert_data as $f_col => $f_val) {
                    $c_lower = strtolower($f_col);
                    $is_fk_or_id = (substr($c_lower, -3) === '_id' || $c_lower === 'id' || in_array($c_lower, array_map('strtolower', $id_candidates)));

                    if (isIntColumn($f_col, $tx_col_types)) {
                        if ($is_fk_or_id) {
                            $insert_data[$f_col] = safeInt32($f_val, null); // Convert 0 to NULL
                        } else {
                            $insert_data[$f_col] = (int)$f_val;
                        }
                    }
                }

                // Execute INSERT query
                $fields = array_keys($insert_data);
                $placeholders = array_map(function($f) { return ':' . $f; }, $fields);

                $sql = "INSERT INTO transactions (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $pdo->prepare($sql);

                $binds = [];
                foreach ($insert_data as $f => $v) {
                    $binds[':' . $f] = $v;
                }
                $stmt->execute($binds);

                // Deduct stock from products
                if ($tx_type !== 'Payment' && $prod_qty_col && $prod_code_col) {
                    $deductStmt = $pdo->prepare("
                        UPDATE products 
                        SET {$prod_qty_col} = {$prod_qty_col} - :qty 
                        WHERE {$prod_code_col} = :barcode
                    ");
                    $deductStmt->execute([':qty' => $qty, ':barcode' => $barcode]);
                }
            }

            $pdo->commit();
            header("Location: stockout.php?success=1");
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = "Transaction failed: " . $e->getMessage();
        }
    } else {
        $error_message = "No items scanned in the transaction cart.";
    }
}

// --- HANDLE DELETION ---
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    try {
        $del = $pdo->prepare("DELETE FROM transactions WHERE {$pk_col} = ?");
        $del->execute([$delete_id]);
    } catch (Exception $e) {}
    header("Location: stockout.php");
    exit();
}

// --- FETCH DASHBOARD TOTALS ---
$total_cash_today = 0;
$payment_today = 0;
$cash_sales_today = 0;
$total_credit = 0;

$col_amt  = findSingleColumn($amt_candidates, $amt_keywords, $tx_columns);
$col_date = findSingleColumn($date_candidates, $date_keywords, $tx_columns);
$col_type = findSingleColumn($type_candidates, $type_keywords, $tx_columns);

try {
    if ($col_amt && $col_date && $col_type) {
        $stmt = $pdo->prepare("SELECT SUM({$col_amt}) FROM transactions WHERE CAST({$col_date} AS DATE) = ? AND {$col_type} = 'Cash'");
        $stmt->execute([$today]);
        $cash_sales_today = (float)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT SUM({$col_amt}) FROM transactions WHERE CAST({$col_date} AS DATE) = ? AND {$col_type} = 'Payment'");
        $stmt->execute([$today]);
        $payment_today = (float)$stmt->fetchColumn();

        $total_cash_today = $cash_sales_today + $payment_today;

        $stmt = $pdo->query("SELECT SUM({$col_amt}) FROM transactions WHERE {$col_type} = 'Credit'");
        $total_credit = (float)$stmt->fetchColumn();
    }
} catch (Exception $e) {}

// --- FETCH PRODUCTS FOR LOOKUP ---
$products_map = [];
$products_by_id = [];
try {
    $prod_stmt = $pdo->query("SELECT * FROM products");
    while ($p = $prod_stmt->fetch(PDO::FETCH_ASSOC)) {
        $code = getTxVal($p, $code_candidates, $code_keywords, null);
        $name = getTxVal($p, $desc_candidates, $desc_keywords, 'Product Item');
        $price = getTxVal($p, ['retail_price', 'selling_price', 'price', 'unit_price'], ['price'], 0);
        $buy_price = getTxVal($p, ['buy_price', 'cost_price', 'cost', 'purchase_price'], ['cost', 'buy'], 0);

        $p_id = 0;
        foreach (['id', 'product_id', 'item_id', 'prod_id', 'p_id'] as $id_key) {
            if (isset($p[$id_key]) && is_numeric($p[$id_key]) && (int)$p[$id_key] > 0) {
                $p_id = (int)$p[$id_key];
                break;
            }
        }

        $prod_info = [
            'id'        => $p_id,
            'code'      => $code,
            'name'      => $name,
            'price'     => (float)$price,
            'buy_price' => (float)$buy_price
        ];

        if ($code !== null && trim((string)$code) !== '') {
            $products_map[trim((string)$code)] = $prod_info;
        }
        if ($p_id > 0) {
            $products_by_id[$p_id] = $prod_info;
        }
    }
} catch (Exception $e) {}

// --- FETCH RECENT TRANSACTIONS ---
$transactions = [];
try {
    $tx_stmt = $pdo->query("SELECT * FROM transactions ORDER BY 1 DESC LIMIT 50");
    if ($tx_stmt) {
        $transactions = $tx_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error_message = "Error fetching history: " . $e->getMessage();
}
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

        .back-btn-container { margin-bottom: 15px; }

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

        .stat-card .amount { font-size: 24px; font-weight: 800; }
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

        .form-group { margin-bottom: 14px; }

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

        .form-control:focus { border-color: var(--primary-blue); }

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

        .add-btn:hover { background-color: var(--primary-hover); }

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

        .cart-summary .label { font-size: 13px; font-weight: 600; color: #1e40af; }
        .cart-summary .total-value { font-size: 20px; font-weight: 800; color: #1e3a8a; }

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

        .process-btn:hover { background-color: #4338ca; }

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
            display: inline-block;
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
        <a href="store.php" class="back-to-store-btn">&larr; Back to Store</a>
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
                <input type="hidden" name="customer_id_val" id="customer_id_val" value="">

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
                    <input type="text" name="customer_name" id="customer_name_input" class="form-control" list="customer_dropdown_list" placeholder="Select or type customer name" autocomplete="off" onchange="onCustomerSelect(this.value)">
                    <datalist id="customer_dropdown_list">
                        <?php foreach ($customers_data as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['name']); ?>" data-id="<?php echo $c['id'] ?? ''; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
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
                                $t_code = getTxVal($tx, $code_candidates, $code_keywords);
                                $t_id   = getTxVal($tx, $id_candidates, $id_keywords, null);
                                $t_date = getTxVal($tx, $date_candidates, $date_keywords);
                                $t_qty  = getTxVal($tx, $qty_candidates, $qty_keywords);
                                $t_type = getTxVal($tx, $type_candidates, $type_keywords, 'Cash');
                                $t_cust = getTxVal($tx, $cust_candidates, $cust_keywords);
                                $t_desc = getTxVal($tx, $desc_candidates, $desc_keywords);
                                $t_amt  = getTxVal($tx, $amt_candidates, $amt_keywords, 0);
                                $row_id = $tx[$pk_col] ?? reset($tx);

                                if (($t_code === '-' || empty($t_code)) && $t_id && isset($products_by_id[$t_id])) {
                                    $t_code = $products_by_id[$t_id]['code'];
                                }
                                if (($t_desc === '-' || is_numeric($t_desc)) && $t_code !== '-' && isset($products_map[$t_code])) {
                                    $t_desc = $products_map[$t_code]['name'];
                                } elseif (($t_desc === '-' || is_numeric($t_desc)) && $t_id && isset($products_by_id[$t_id])) {
                                    $t_desc = $products_by_id[$t_id]['name'];
                                }
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars((string)$t_code); ?></strong></td>
                                <td><?php echo htmlspecialchars((string)$t_date); ?></td>
                                <td><?php echo htmlspecialchars((string)$t_qty); ?></td>
                                <td><span class="badge-type"><?php echo htmlspecialchars((string)$t_type); ?></span></td>
                                <td><em><?php echo htmlspecialchars((string)$t_cust); ?></em></td>
                                <td><?php echo htmlspecialchars((string)$t_desc); ?></td>
                                <td><strong>₱<?php echo number_format((float)$t_amt, 2); ?></strong></td>
                                <td>
                                    <a href="stockout.php?delete_id=<?php echo urlencode($row_id); ?>" class="action-del" onclick="return confirm('Delete this record?')">Delete</a>
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
        const customersMap = <?php echo json_encode($customers_map); ?>;
        let cartItems = [];

        const barcodeInput = document.getElementById('barcode_input');
        const qtyInput = document.getElementById('qty_input');
        const cartBody = document.getElementById('cart_body');
        const grandTotalDisplay = document.getElementById('grand_total_display');
        const itemsPayload = document.getElementById('items_payload');
        const customerIdVal = document.getElementById('customer_id_val');

        barcodeInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addItemFromScan();
            }
        });

        function onCustomerSelect(val) {
            const cleanVal = val.trim().toLowerCase();
            if (customersMap[cleanVal]) {
                customerIdVal.value = customersMap[cleanVal];
            } else {
                customerIdVal.value = '';
            }
        }

        function addItemFromScan() {
            const code = barcodeInput.value.trim();
            const qty = parseInt(qtyInput.value) || 1;

            if (!code) return;

            let id = 0;
            let name = "Scanned Item (" + code + ")";
            let price = 0.00;
            let buyPrice = 0.00;

            if (productDatabase[code]) {
                id = productDatabase[code].id || 0;
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
                    id: id,
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
