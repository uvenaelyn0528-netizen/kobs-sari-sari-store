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

// Handle Form Submission (Barrow or Payment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lending'])) {
    $tx_type       = $_POST['tx_type'] ?? 'Barrow'; // Barrow or Payment
    $customer_name = trim($_POST['customer_name'] ?? '');
    $date_sold     = $_POST['date_sold'] ?? date('Y-m-d');

    try {
        if (empty($customer_name)) {
            throw new Exception("Please select a customer.");
        }

        if ($tx_type === 'Barrow') {
            $product_code = trim($_POST['product_code'] ?? '');
            $qty_sold     = intval($_POST['qty_sold'] ?? 0);

            if (empty($product_code) || $qty_sold <= 0) {
                throw new Exception("Please provide product code and valid quantity for borrowing.");
            }

            $stmt = $pdo->prepare("SELECT * FROM products WHERE product_code = ?");
            $stmt->execute([$product_code]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                $total_amount = $qty_sold * floatval($product['retail_price']);
                
                // Insert into stockouts with remarks = 'Lending'
                $insertStmt = $pdo->prepare("INSERT INTO stockouts (product_code, date_sold, qty_sold, remarks, customer_name, product_name, category, total_amount) VALUES (?, ?, ?, 'Lending', ?, ?, ?, ?)");
                $insertStmt->execute([
                    $product_code, 
                    $date_sold, 
                    $qty_sold, 
                    $customer_name, 
                    $product['product_name'], 
                    $product['category'], 
                    $total_amount
                ]);

                // Deduct product stock
                $updateProd = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ?, \"Stock_out\" = \"Stock_out\" + ? WHERE product_code = ?");
                $updateProd->execute([$qty_sold, $qty_sold, $product_code]);

                // Update credits ledger
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

                $message = "Item borrow recorded successfully!";
                $message_type = "success";
            } else {
                throw new Exception("Product barcode not found.");
            }

        } elseif ($tx_type === 'Payment') {
            $payment_amount = floatval($_POST['payment_amount'] ?? 0);
            $interest_amount = floatval($_POST['interest_amount'] ?? 0);
            
            if ($payment_amount <= 0 && $interest_amount <= 0) {
                throw new Exception("Please enter a valid payment or interest amount.");
            }

            $total_inflow = $payment_amount + $interest_amount;

            // Update credits table (add to total_payment)
            $checkCust = $pdo->prepare("SELECT store_credit, total_payment FROM credits WHERE customer_name = ?");
            $checkCust->execute([$customer_name]);
            $exists = $checkCust->fetch(PDO::FETCH_ASSOC);

            if ($exists) {
                $new_payment = floatval($exists['total_payment']) + $total_inflow;
                $new_balance = floatval($exists['store_credit']) - $new_payment;
                
                $updateCredit = $pdo->prepare("UPDATE credits SET total_payment = ?, total_balance = ? WHERE customer_name = ?");
                $updateCredit->execute([$new_payment, $new_balance, $customer_name]);
            } else {
                $pdo->prepare("INSERT INTO credits (customer_name, store_credit, total_payment, total_balance) VALUES (?, 0.00, ?, ?)")
                    ->execute([$customer_name, $total_inflow, -$total_inflow]);
            }

            // Log payment in stockouts or a separate transaction log if needed
            $logStmt = $pdo->prepare("INSERT INTO stockouts (product_code, date_sold, qty_sold, remarks, customer_name, product_name, category, total_amount) VALUES ('-', ?, 0, 'Lending Payment', ?, 'Payment / Interest', 'Payment', ?)");
            $logStmt->execute([$date_sold, $customer_name, $total_inflow]);

            $message = "Payment and interest successfully recorded!";
            $message_type = "success";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Fetch Lending Records
try {
    $stmt = $pdo->query("SELECT * FROM stockouts WHERE remarks IN ('Lending', 'Lending Payment') ORDER BY id DESC LIMIT 100");
    $lendings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sumLending = $pdo->query("SELECT SUM(total_amount) as total_lending FROM stockouts WHERE remarks = 'Lending'");
    $total_lending_res = $sumLending->fetch(PDO::FETCH_ASSOC)['total_lending'] ?? 0;
} catch (PDOException $e) {
    $lendings = [];
    $total_lending_res = 0;
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">🤝 Item Lending & Payment Management</h1>
            <p class="text-sm text-gray-600">Record item borrowings, interest computations, and customer payments.</p>
        </div>
        <div class="bg-teal-100 text-teal-800 px-4 py-2 rounded-lg font-bold shadow-sm">
            Total Active Lending Balance: ₱<?= number_format($total_lending_res, 2) ?>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Transaction Form -->
        <div class="bg-white p-6 rounded-xl shadow-md h-fit">
            <h2 class="text-lg font-bold text-gray-800 mb-4">New Transaction</h2>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Transaction Type</label>
                    <select name="tx_type" id="txTypeSelect" onchange="toggleTxFields()" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-white font-semibold text-teal-700">
                        <option value="Barrow">Barrow (Pahiram ng Item)</option>
                        <option value="Payment">Payment / Interest (Bayad / Tubo)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Customer Name</label>
                    <select name="customer_name" id="customerSelect" onchange="handleCustomerSelectChange(this)" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-white">
                        <option value="">-- Select Customer --</option>
                        <option value="ADD_NEW" class="font-bold text-indigo-600">+ Add New Customer...</option>
                        <?php foreach ($master_customer_list as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Barrow Fields -->
                <div id="barrowFields" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Product Barcode / Code</label>
                        <input type="text" name="product_code" id="productCodeInput" placeholder="Scan barcode or type code" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Quantity</label>
                        <input type="number" name="qty_sold" value="1" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                </div>

                <!-- Payment & Interest Fields -->
                <div id="paymentFields" class="space-y-4 hidden">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Payment Amount (Principal)</label>
                        <input type="number" step="0.01" name="payment_amount" value="0.00" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Interest Amount (Tubo batay sa tagal/balanse)</label>
                        <input type="number" step="0.01" name="interest_amount" value="0.00" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                        <p class="text-xs text-gray-500 mt-1">Kalkulahin ang interes mula sa simula ng hiram hanggang ngayon batay sa natitirang balanse.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Date</label>
                    <input type="date" name="date_sold" value="<?= date('Y-m-d') ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>

                <div>
                    <button type="submit" name="save_lending" class="w-full bg-teal-600 text-white py-2 px-4 rounded-md hover:bg-teal-700 transition font-semibold shadow">
                        Save Transaction
                    </button>
                </div>
            </form>
        </div>

        <!-- History Table -->
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold text-gray-800">Transaction History Log</h2>
                <input type="text" id="searchLending" placeholder="Search records..." class="border rounded-md px-3 py-1 text-sm">
            </div>

            <div class="max-h-[500px] overflow-x-auto overflow-y-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200" id="lendingTable">
                    <thead class="bg-teal-100 sticky top-0 z-10">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Type</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Date</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Customer Name</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Description / Details</th>
                            <th class="px-3 py-3 text-right text-xs font-bold text-gray-800 uppercase">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm bg-white">
                        <?php if (!empty($lendings)): ?>
                            <?php foreach ($lendings as $l): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-3">
                                        <?php if ($l['remarks'] === 'Lending'): ?>
                                            <span class="bg-blue-100 text-blue-800 text-xs font-bold px-2 py-0.5 rounded">Barrow</span>
                                        <?php else: ?>
                                            <span class="bg-green-100 text-green-800 text-xs font-bold px-2 py-0.5 rounded">Payment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600"><?= htmlspecialchars($l['date_sold']) ?></td>
                                    <td class="px-3 py-3 text-gray-700 font-medium"><?= htmlspecialchars($l['customer_name']) ?></td>
                                    <td class="px-3 py-3 text-gray-900"><?= htmlspecialchars($l['product_name']) ?> <?= $l['qty_sold'] > 0 ? '(Qty: '.$l['qty_sold'].')' : '' ?></td>
                                    <td class="px-3 py-3 text-right font-bold text-gray-900">₱<?= number_format($l['total_amount'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-4 py-4 text-center text-gray-500">No transaction records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleTxFields() {
    let type = document.getElementById('txTypeSelect').value;
    let barrowFields = document.getElementById('barrowFields');
    let paymentFields = document.getElementById('paymentFields');
    let prodCodeInput = document.getElementById('productCodeInput');

    if (type === 'Barrow') {
        barrowFields.classList.remove('hidden');
        paymentFields.classList.add('hidden');
        prodCodeInput.setAttribute('required', 'required');
    } else {
        barrowFields.classList.add('hidden');
        paymentFields.classList.remove('hidden');
        prodCodeInput.removeAttribute('required');
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

// Search filter for table
document.getElementById('searchLending').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#lendingTable tbody tr');
    rows.forEach(row => {
        let text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

<?php include 'footer.php'; ?>
