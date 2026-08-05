<?php
require_once 'db.php';
include 'header.php';

$message = '';
$message_type = '';

// Complete master list of customers
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
    "Quimbo,", "Ramayramay, Jonathan", "Regajal, Rolando", "Reyes, Ricky", 
    "Rivera, Aldrin", "Rodolfo, Fernando", "Rosal, Napoleon", "Sabas, Jeffrey", 
    "Saga, Rezniel", "Saldivar, Jeamboy", "Salve, Levi", "Sandoval, Erwin", 
    "Santos, Mark James", "Saylag, Kieth", "Sia, Amor", "Sidaya, Jannel", 
    "Somoray, Rogelio", "Sulapas, Justine", "Taguba, Glenn", "Tarpin, Ma. Andrea", 
    "Tejero, Mark Anthony", "Tubil, Romeo", "Uveña, Elyn", "Uveña, Mario", 
    "Villarosa, Reynan Dave", "Yurong, Edmon"
];
sort($master_customer_list);

// Handle Deletion
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    try {
        $st_stmt = $pdo->prepare("SELECT * FROM stockouts WHERE id = ?");
        $st_stmt->execute([$del_id]);
        $tx = $st_stmt->fetch(PDO::FETCH_ASSOC);

        if ($tx) {
            if ($tx['product_code'] !== '-' && !empty($tx['product_code'])) {
                $revProd = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?, \"Stock_out\" = \"Stock_out\" - ? WHERE product_code = ?");
                $revProd->execute([$tx['qty_sold'], $tx['qty_sold'], $tx['product_code']]);
            }

            if (!empty($tx['customer_name'])) {
                $c_name = $tx['customer_name'];
                $chkC = $pdo->prepare("SELECT store_credit, total_payment FROM credits WHERE customer_name = ?");
                $chkC->execute([$c_name]);
                $c_data = $chkC->fetch(PDO::FETCH_ASSOC);

                if ($c_data) {
                    if ($tx['remarks'] === 'Credit') {
                        $new_credit = floatval($c_data['store_credit']) - floatval($tx['total_amount']);
                        $new_bal = $new_credit - floatval($c_data['total_payment']);
                        $updC = $pdo->prepare("UPDATE credits SET store_credit = ?, total_balance = ? WHERE customer_name = ?");
                        $updC->execute([$new_credit, $new_bal, $c_name]);
                    } elseif ($tx['remarks'] === 'Payment') {
                        $new_pay = floatval($c_data['total_payment']) - floatval($tx['total_amount']);
                        $new_bal = floatval($c_data['store_credit']) - $new_pay;
                        $updC = $pdo->prepare("UPDATE credits SET total_payment = ?, total_balance = ? WHERE customer_name = ?");
                        $updC->execute([$new_pay, $new_bal, $c_name]);
                    } elseif ($tx['remarks'] === 'Balance') {
                        $new_credit = floatval($c_data['store_credit']) - floatval($tx['total_amount']);
                        $new_bal = $new_credit - floatval($c_data['total_payment']);
                        $updC = $pdo->prepare("UPDATE credits SET store_credit = ?, total_balance = ? WHERE customer_name = ?");
                        $updC->execute([$new_credit, $new_bal, $c_name]);
                    }
                }
            }

            $delStmt = $pdo->prepare("DELETE FROM stockouts WHERE id = ?");
            $delStmt->execute([$del_id]);

            $message = "Transaction deleted and inventory/ledger successfully reverted.";
            $message_type = "success";
        }
    } catch (Exception $e) {
        $message = "Error deleting transaction: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle Form Submission (Add or Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stockout'])) {
    $edit_id       = intval($_POST['edit_id'] ?? 0);
    $product_code  = trim($_POST['product_code'] ?? '');
    $qty_sold      = intval($_POST['qty_sold'] ?? 0);
    $remarks       = $_POST['remarks'] ?? 'Cash';
    $customer_name = trim($_POST['customer_name'] ?? '');
    $date_sold     = $_POST['date_sold'] ?? date('Y-m-d');
    $custom_amount = floatval($_POST['custom_amount'] ?? 0);

    try {
        if ($edit_id > 0) {
            $st_stmt = $pdo->prepare("SELECT * FROM stockouts WHERE id = ?");
            $st_stmt->execute([$edit_id]);
            $old_tx = $st_stmt->fetch(PDO::FETCH_ASSOC);

            if ($old_tx) {
                if ($old_tx['product_code'] !== '-' && !empty($old_tx['product_code'])) {
                    $revProd = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?, \"Stock_out\" = \"Stock_out\" - ? WHERE product_code = ?");
                    $revProd->execute([$old_tx['qty_sold'], $old_tx['qty_sold'], $old_tx['product_code']]);
                }
                if (!empty($old_tx['customer_name'])) {
                    $c_name = $old_tx['customer_name'];
                    $chkC = $pdo->prepare("SELECT store_credit, total_payment FROM credits WHERE customer_name = ?");
                    $chkC->execute([$c_name]);
                    $c_data = $chkC->fetch(PDO::FETCH_ASSOC);
                    if ($c_data) {
                        if ($old_tx['remarks'] === 'Credit') {
                            $nc = floatval($c_data['store_credit']) - floatval($old_tx['total_amount']);
                            $nb = $nc - floatval($c_data['total_payment']);
                            $pdo->prepare("UPDATE credits SET store_credit = ?, total_balance = ? WHERE customer_name = ?")->execute([$nc, $nb, $c_name]);
                        } elseif ($old_tx['remarks'] === 'Payment') {
                            $np = floatval($c_data['total_payment']) - floatval($old_tx['total_amount']);
                            $nb = floatval($c_data['store_credit']) - $np;
                            $pdo->prepare("UPDATE credits SET total_payment = ?, total_balance = ? WHERE customer_name = ?")->execute([$np, $nb, $c_name]);
                        } elseif ($old_tx['remarks'] === 'Balance') {
                            $nc = floatval($c_data['store_credit']) - floatval($old_tx['total_amount']);
                            $nb = $nc - floatval($c_data['total_payment']);
                            $pdo->prepare("UPDATE credits SET store_credit = ?, total_balance = ? WHERE customer_name = ?")->execute([$nc, $nb, $c_name]);
                        }
                    }
                }
                $pdo->prepare("DELETE FROM stockouts WHERE id = ?")->execute([$edit_id]);
            }
        }

        if ($remarks === 'Payment' || $remarks === 'Balance') {
            if (empty($customer_name)) {
                throw new Exception("Please select a customer name for this transaction.");
            }

            $total_amount = $custom_amount;
            $product_name = ($remarks === 'Payment') ? 'Customer Account Payment' : 'Customer Opening Balance';
            $category     = 'Credit Ledger';

            $insertStmt = $pdo->prepare("INSERT INTO stockouts (product_code, date_sold, qty_sold, remarks, customer_name, product_name, category, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->execute(['-', $date_sold, 0, $remarks, $customer_name, $product_name, $category, $total_amount]);

            $checkCust = $pdo->prepare("SELECT id, store_credit, total_payment FROM credits WHERE customer_name = ?");
            $checkCust->execute([$customer_name]);
            $exists = $checkCust->fetch(PDO::FETCH_ASSOC);

            if ($remarks === 'Payment') {
                if ($exists) {
                    $new_payment = floatval($exists['total_payment']) + $total_amount;
                    $new_balance = floatval($exists['store_credit']) - $new_payment;
                    $upd = $pdo->prepare("UPDATE credits SET total_payment = ?, total_balance = ?, updated_at = CURRENT_TIMESTAMP WHERE customer_name = ?");
                    $upd->execute([$new_payment, $new_balance, $customer_name]);
                } else {
                    $upd = $pdo->prepare("INSERT INTO credits (customer_name, store_credit, total_payment, total_balance) VALUES (?, 0.00, ?, ?)");
                    $upd->execute([$customer_name, $total_amount, -$total_amount]);
                }
            } else { // Balance
                if ($exists) {
                    $new_credit = floatval($exists['store_credit']) + $total_amount;
                    $new_balance = $new_credit - floatval($exists['total_payment']);
                    $upd = $pdo->prepare("UPDATE credits SET store_credit = ?, total_balance = ?, updated_at = CURRENT_TIMESTAMP WHERE customer_name = ?");
                    $upd->execute([$new_credit, $new_balance, $customer_name]);
                } else {
                    $upd = $pdo->prepare("INSERT INTO credits (customer_name, store_credit, total_payment, total_balance) VALUES (?, ?, 0.00, ?)");
                    $upd->execute([$customer_name, $total_amount, $total_amount]);
                }
            }

            $message = "Ledger transaction saved successfully!";
            $message_type = "success";

        } else {
            if (empty($product_code) || $qty_sold <= 0) {
                throw new Exception("Please provide a valid product code and quantity.");
            }

            $stmt = $pdo->prepare("SELECT * FROM products WHERE product_code = ?");
            $stmt->execute([$product_code]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                $total_amount = $qty_sold * floatval($product['retail_price']);
                
                $insertStmt = $pdo->prepare("INSERT INTO stockouts (product_code, date_sold, qty_sold, remarks, customer_name, product_name, category, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->execute([
                    $product_code, 
                    $date_sold, 
                    $qty_sold, 
                    $remarks, 
                    ($remarks === 'Credit' ? $customer_name : ''), 
                    $product['product_name'], 
                    $product['category'], 
                    $total_amount
                ]);

                $updateProd = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ?, \"Stock_out\" = \"Stock_out\" + ? WHERE product_code = ?");
                $updateProd->execute([$qty_sold, $qty_sold, $product_code]);

                if ($remarks === 'Credit' && !empty($customer_name)) {
                    $checkCust = $pdo->prepare("SELECT id, store_credit, total_payment FROM credits WHERE customer_name = ?");
                    $checkCust->execute([$customer_name]);
                    $exists = $checkCust->fetch(PDO::FETCH_ASSOC);

                    if ($exists) {
                        $new_credit = floatval($exists['store_credit']) + $total_amount;
                        $new_balance = $new_credit - floatval($exists['total_payment']);
                        $updateCredit = $pdo->prepare("UPDATE credits SET store_credit = ?, total_balance = ? WHERE customer_name = ?");
                        $updateCredit->execute([$new_credit, $new_balance, $customer_name]);
                    } else {
                        $insertCredit = $pdo->prepare("INSERT INTO credits (customer_name, store_credit, total_payment, total_balance) VALUES (?, ?, 0.00, ?)");
                        $insertCredit->execute([$customer_name, $total_amount, $total_amount]);
                    }
                }

                $message = "Stockout transaction saved successfully!";
                $message_type = "success";
            } else {
                throw new Exception("Product barcode not found in inventory.");
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Fetch Data & Totals
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS stockouts (
        id SERIAL PRIMARY KEY,
        product_code VARCHAR(100),
        date_sold DATE DEFAULT CURRENT_DATE,
        qty_sold INT DEFAULT 0,
        remarks VARCHAR(50) DEFAULT 'Cash',
        customer_name VARCHAR(255),
        product_name VARCHAR(255),
        category VARCHAR(255),
        total_amount NUMERIC(10,2) DEFAULT 0.00
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS credits (
        id SERIAL PRIMARY KEY,
        customer_name VARCHAR(255) NOT NULL UNIQUE,
        store_credit NUMERIC(10,2) DEFAULT 0.00,
        total_payment NUMERIC(10,2) DEFAULT 0.00,
        total_balance NUMERIC(10,2) DEFAULT 0.00,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $today = date('Y-m-d');

    $sumSalesToday = $pdo->prepare("SELECT SUM(total_amount) as total_sales FROM stockouts WHERE remarks IN ('Cash', 'Payment') AND date_sold = ?");
    $sumSalesToday->execute([$today]);
    $total_sales_today = $sumSalesToday->fetch(PDO::FETCH_ASSOC)['total_sales'] ?? 0;

    $sumPaymentToday = $pdo->prepare("SELECT SUM(total_amount) as total_payment FROM stockouts WHERE remarks = 'Payment' AND date_sold = ?");
    $sumPaymentToday->execute([$today]);
    $total_payment_today = $sumPaymentToday->fetch(PDO::FETCH_ASSOC)['total_payment'] ?? 0;

    $sumCashToday = $pdo->prepare("SELECT SUM(total_amount) as total_cash FROM stockouts WHERE remarks = 'Cash' AND date_sold = ?");
    $sumCashToday->execute([$today]);
    $total_cash_today = $sumCashToday->fetch(PDO::FETCH_ASSOC)['total_cash'] ?? 0;

    $sumCredit = $pdo->query("SELECT SUM(total_amount) as total_credit FROM stockouts WHERE remarks IN ('Credit', 'Balance')");
    $total_credit_res = $sumCredit->fetch(PDO::FETCH_ASSOC)['total_credit'] ?? 0;

    // Limit fetched history to the last 100 transactions to speed up initial page load
    $stmt = $pdo->query("SELECT * FROM stockouts ORDER BY id DESC LIMIT 100");
    $stockouts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $total_sales_today = 0;
    $total_payment_today = 0;
    $total_cash_today = 0;
    $total_credit_res = 0;
    $stockouts = [];
}
?>

<div class="container mx-auto px-4 py-8">
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Summary Header Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white p-4 rounded-xl shadow-md border-l-4 border-yellow-500">
            <p class="text-xs font-semibold text-gray-500 uppercase">Total Cash on Hand (Today)</p>
            <h3 class="text-2xl font-extrabold text-gray-800 mt-1">₱<?= number_format($total_sales_today, 2) ?></h3>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-md border-l-4 border-green-500">
            <p class="text-xs font-semibold text-gray-500 uppercase">Payment for Today</p>
            <h3 class="text-2xl font-extrabold text-green-700 mt-1">₱<?= number_format($total_payment_today, 2) ?></h3>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-md border-l-4 border-blue-500">
            <p class="text-xs font-semibold text-gray-500 uppercase">Cash Sales (Today)</p>
            <h3 class="text-2xl font-extrabold text-blue-700 mt-1">₱<?= number_format($total_cash_today, 2) ?></h3>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-md border-l-4 border-orange-500">
            <p class="text-xs font-semibold text-gray-500 uppercase">Total Credit Accumulation (Credit + Balance)</p>
            <h3 class="text-2xl font-extrabold text-orange-600 mt-1">₱<?= number_format($total_credit_res, 2) ?></h3>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- New Stockout Form -->
        <div class="bg-white p-6 rounded-xl shadow-md h-fit">
            <div class="flex justify-between items-center mb-4">
                <h2 id="formTitle" class="text-lg font-bold text-gray-800">Record Transaction</h2>
                <a href="stockout.php" id="cancelEditBtn" class="hidden text-xs text-red-600 font-semibold underline">Cancel Edit</a>
            </div>
            
            <form method="POST" class="space-y-4" id="transactionForm">
                <input type="hidden" name="edit_id" id="edit_id" value="0">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Transaction Type</label>
                    <select name="remarks" id="transactionType" onchange="handleTransactionChange(this.value)" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-white">
                        <option value="Cash">Cash</option>
                        <option value="Credit">Credit</option>
                        <option value="Payment">Payment</option>
                        <option value="Balance">Balance</option>
                    </select>
                </div>

                <!-- Product Fields -->
                <div id="productFields">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Product Barcode / Code</label>
                        <input type="text" id="scan_code" name="product_code" placeholder="Scan barcode or type code" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Quantity Sold</label>
                        <input type="number" name="qty_sold" id="qtySoldInput" value="1" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                </div>

                <!-- Custom Amount Field -->
                <div id="amountFieldContainer" class="hidden">
                    <label class="block text-sm font-medium text-gray-700" id="amountLabel">Amount (₱)</label>
                    <input type="number" step="0.01" name="custom_amount" id="customAmountInput" value="0.00" min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>

                <!-- Customer Field -->
                <div id="customerFieldContainer" class="hidden">
                    <label class="block text-sm font-medium text-gray-700">Customer Name</label>
                    <select name="customer_name" id="customerSelect" onchange="handleCustomerSelectChange(this)" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-white">
                        <option value="">-- Select Customer --</option>
                        <option value="ADD_NEW" class="font-bold text-indigo-600">+ Add New Customer...</option>
                        <?php foreach ($master_customer_list as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Date</label>
                    <input type="date" name="date_sold" id="dateSoldInput" value="<?= date('Y-m-d') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div>
                    <button type="submit" name="save_stockout" id="submitBtn" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition font-semibold shadow">
                        Process Transaction
                    </button>
                </div>
            </form>
        </div>

        <!-- History Log Table -->
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold text-gray-800">Transaction History Log</h2>
                <input type="text" id="searchStockout" placeholder="Search transactions..." class="border rounded-md px-3 py-1 text-sm">
            </div>

            <div class="max-h-[500px] overflow-x-auto overflow-y-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200" id="stockoutTable">
                    <thead class="bg-yellow-100 sticky top-0 z-10">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Code</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Date</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Qty</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Type</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Customer Name</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Description</th>
                            <th class="px-3 py-3 text-right text-xs font-bold text-gray-800 uppercase">Amount</th>
                            <th class="px-3 py-3 text-center text-xs font-bold text-gray-800 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm bg-white">
                        <?php if (!empty($stockouts)): ?>
                            <?php foreach ($stockouts as $s): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-3 font-mono text-xs text-indigo-600 font-semibold"><?= htmlspecialchars($s['product_code']) ?></td>
                                    <td class="px-3 py-3 text-gray-600"><?= htmlspecialchars($s['date_sold']) ?></td>
                                    <td class="px-3 py-3 font-bold text-gray-800"><?= $s['qty_sold'] > 0 ? htmlspecialchars($s['qty_sold']) : '-' ?></td>
                                    <td class="px-3 py-3">
                                        <?php 
                                            $badgeColor = 'bg-blue-100 text-blue-700';
                                            if ($s['remarks'] === 'Credit') $badgeColor = 'bg-orange-100 text-orange-700';
                                            if ($s['remarks'] === 'Payment') $badgeColor = 'bg-green-100 text-green-700';
                                            if ($s['remarks'] === 'Balance') $badgeColor = 'bg-purple-100 text-purple-700';
                                        ?>
                                        <span class="px-2 py-1 text-xs rounded font-semibold <?= $badgeColor ?>">
                                            <?= htmlspecialchars($s['remarks']) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-gray-700 italic"><?= htmlspecialchars($s['customer_name'] ?: '-') ?></td>
                                    <td class="px-3 py-3 font-medium text-gray-900"><?= htmlspecialchars($s['product_name']) ?></td>
                                    <td class="px-3 py-3 text-right font-bold text-gray-900">₱<?= number_format($s['total_amount'], 2) ?></td>
                                    <td class="px-3 py-3 text-center space-x-2">
                                        <button onclick='editTransaction(<?= json_encode($s) ?>)' class="text-indigo-600 hover:text-indigo-900 font-semibold text-xs bg-indigo-50 px-2 py-1 rounded">Edit</button>
                                        <a href="stockout.php?delete_id=<?= $s['id'] ?>" onclick="return confirm('Are you sure you want to delete this transaction? This will revert inventory and ledger balances.')" class="text-red-600 hover:text-red-900 font-semibold text-xs bg-red-50 px-2 py-1 rounded">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-4 py-4 text-center text-gray-500">No transactions recorded yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function handleTransactionChange(val) {
    let productFields = document.getElementById('productFields');
    let customerContainer = document.getElementById('customerFieldContainer');
    let amountContainer = document.getElementById('amountFieldContainer');
    let scanCode = document.getElementById('scan_code');
    let qtyInput = document.getElementById('qtySoldInput');
    let customerSelect = document.getElementById('customerSelect');
    let amountLabel = document.getElementById('amountLabel');

    if (val === 'Payment' || val === 'Balance') {
        productFields.classList.add('hidden');
        scanCode.removeAttribute('required');
        qtyInput.removeAttribute('required');

        customerContainer.classList.remove('hidden');
        customerSelect.setAttribute('required', 'required');

        amountContainer.classList.remove('hidden');
        document.getElementById('customAmountInput').setAttribute('required', 'required');
        
        amountLabel.textContent = val === 'Payment' ? 'Payment Amount (₱)' : 'Opening Balance Amount (₱)';
    } else {
        productFields.classList.remove('hidden');
        scanCode.setAttribute('required', 'required');
        qtyInput.setAttribute('required', 'required');

        amountContainer.classList.add('hidden');
        document.getElementById('customAmountInput').removeAttribute('required');

        // Dito ipinapakita ang customer field kapag Credit, at itatago naman kapag Cash
        if (val === 'Credit') {
            customerContainer.classList.remove('hidden');
            customerSelect.setAttribute('required', 'required');
        } else {
            customerContainer.classList.add('hidden');
            customerSelect.removeAttribute('required');
        }
    }
}

function handleCustomerSelectChange(selectElem) {
    if (selectElem.value === 'ADD_NEW') {
        let newName = prompt("Enter new customer name (Last Name, First Name):");
        if (newName && newName.trim() !== "") {
            newName = newName.trim();
            let exists = false;
            for (let opt of selectElem.options) {
                if (opt.value.toLowerCase() === newName.toLowerCase()) {
                    exists = true;
                    break;
                }
            }
            if (!exists) {
                let opt = document.createElement('option');
                opt.value = newName;
                opt.textContent = newName;
                selectElem.insertBefore(opt, selectElem.options[2]);
            }
            selectElem.value = newName;
        } else {
            selectElem.value = "";
        }
    }
}

function editTransaction(tx) {
    document.getElementById('edit_id').value = tx.id;
    document.getElementById('transactionType').value = tx.remarks;
    handleTransactionChange(tx.remarks);

    if (tx.remarks === 'Payment' || tx.remarks === 'Balance') {
        document.getElementById('customAmountInput').value = tx.total_amount;
    } else {
        document.getElementById('scan_code').value = tx.product_code;
        document.getElementById('qtySoldInput').value = tx.qty_sold;
    }

    if (tx.customer_name) {
        let custSelect = document.getElementById('customerSelect');
        let found = false;
        for (let opt of custSelect.options) {
            if (opt.value === tx.customer_name) {
                found = true;
                break;
            }
        }
        if (!found) {
            let opt = document.createElement('option');
            opt.value = tx.customer_name;
            opt.textContent = tx.customer_name;
            custSelect.insertBefore(opt, custSelect.options[2]);
        }
        custSelect.value = tx.customer_name;
    }

    document.getElementById('dateSoldInput').value = tx.date_sold;
    document.getElementById('formTitle').textContent = "Edit Transaction (ID: " + tx.id + ")";
    document.getElementById('cancelEditBtn').classList.remove('hidden');
    document.getElementById('submitBtn').textContent = "Update Transaction";
    document.getElementById('submitBtn').classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
    document.getElementById('submitBtn').classList.add('bg-amber-600', 'hover:bg-amber-700');
}

// Search filter for history log table
document.getElementById('searchStockout').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#stockoutTable tbody tr');
    rows.forEach(row => {
        let text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>
