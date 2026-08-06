<?php
require_once 'db.php';
include 'header.php';

$message = '';
$message_type = '';

// Check if current user is admin (assuming session stores role or username, adjust as per your auth system)
$is_admin = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') || (isset($_SESSION['username']) && strtolower($_SESSION['username']) === 'admin');

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

// Handle Delete Action
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    try {
        // Fetch transaction details before deleting to reverse credits effect if needed
        $tStmt = $pdo->prepare("SELECT * FROM stockouts WHERE id = ? AND remarks IN ('Cash Lending', 'Cash Lending Payment')");
        $tStmt->execute([$del_id]);
        $tx_to_del = $tStmt->fetch(PDO::FETCH_ASSOC);

        if ($tx_to_del) {
            $c_name = $tx_to_del['customer_name'];
            $t_amount = floatval($tx_to_del['total_amount']);
            $t_remarks = $tx_to_del['remarks'];

            // Reverse credit balance
            $cCheck = $pdo->prepare("SELECT store_credit, total_payment FROM credits WHERE customer_name = ?");
            $cCheck->execute([$c_name]);
            $cData = $cCheck->fetch(PDO::FETCH_ASSOC);

            if ($cData) {
                if ($t_remarks === 'Cash Lending') {
                    $new_credit = max(0, floatval($cData['store_credit']) - $t_amount);
                    $new_balance = $new_credit - floatval($cData['total_payment']);
                    $pdo->prepare("UPDATE credits SET store_credit = ?, total_balance = ? WHERE customer_name = ?")
                        ->execute([$new_credit, $new_balance, $c_name]);
                } else {
                    $new_payment = max(0, floatval($cData['total_payment']) - $t_amount);
                    $new_balance = floatval($cData['store_credit']) - $new_payment;
                    $pdo->prepare("UPDATE credits SET total_payment = ?, total_balance = ? WHERE customer_name = ?")
                        ->execute([$new_payment, $new_balance, $c_name]);
                }
            }

            // Delete record
            $delStmt = $pdo->prepare("DELETE FROM stockouts WHERE id = ?");
            $delStmt->execute([$del_id]);

            $message = "Transaction successfully deleted and balances updated!";
            $message_type = "success";
        }
    } catch (Exception $e) {
        $message = "Error deleting transaction: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle Edit Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_lending'])) {
    $edit_id       = intval($_POST['edit_id']);
    $customer_name = trim($_POST['customer_name'] ?? '');
    $date_sold     = $_POST['date_sold'] ?? date('Y-m-d');
    $total_amount  = floatval($_POST['total_amount'] ?? 0);

    try {
        if (empty($customer_name) || $total_amount <= 0) {
            throw new Exception("Please provide valid customer and amount.");
        }

        // Fetch old record to calculate difference and adjust credits ledger properly
        $oldStmt = $pdo->prepare("SELECT * FROM stockouts WHERE id = ?");
        $oldStmt->execute([$edit_id]);
        $oldRecord = $oldStmt->fetch(PDO::FETCH_ASSOC);

        if ($oldRecord) {
            $old_amount = floatval($oldRecord['total_amount']);
            $old_customer = $oldRecord['customer_name'];
            $remarks = $oldRecord['remarks'];
            $diff = $total_amount - $old_amount;

            // Update stockouts record
            $updt = $pdo->prepare("UPDATE stockouts SET customer_name = ?, date_sold = ?, total_amount = ? WHERE id = ?");
            $updt->execute([$customer_name, $date_sold, $total_amount, $edit_id]);

            // Adjust credits if customer changed or amount changed
            if ($old_customer === $customer_name) {
                $cCheck = $pdo->prepare("SELECT store_credit, total_payment FROM credits WHERE customer_name = ?");
                $cCheck->execute([$customer_name]);
                $cData = $cCheck->fetch(PDO::FETCH_ASSOC);

                if ($cData) {
                    if ($remarks === 'Cash Lending') {
                        $new_credit = floatval($cData['store_credit']) + $diff;
                        $new_balance = $new_credit - floatval($cData['total_payment']);
                        $pdo->prepare("UPDATE credits SET store_credit = ?, total_balance = ? WHERE customer_name = ?")
                            ->execute([$new_credit, $new_balance, $customer_name]);
                    } else {
                        $new_payment = floatval($cData['total_payment']) + $diff;
                        $new_balance = floatval($cData['store_credit']) - $new_payment;
                        $pdo->prepare("UPDATE credits SET total_payment = ?, total_balance = ? WHERE customer_name = ?")
                            ->execute([$new_payment, $new_balance, $customer_name]);
                    }
                }
            }

            $message = "Transaction updated successfully!";
            $message_type = "success";
        }
    } catch (Exception $e) {
        $message = "Error updating: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle New Form Submission (Cash Borrow or Payment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lending'])) {
    $tx_type       = $_POST['tx_type'] ?? 'Cash Borrow'; 
    $customer_name = trim($_POST['customer_name'] ?? '');
    $date_sold     = $_POST['date_sold'] ?? date('Y-m-d');

    try {
        if (empty($customer_name)) {
            throw new Exception("Please select a customer.");
        }

        if ($tx_type === 'Cash Borrow') {
            $borrow_amount = floatval($_POST['borrow_amount'] ?? 0);

            if ($borrow_amount <= 0) {
                throw new Exception("Please enter a valid cash borrow amount.");
            }

            $insertStmt = $pdo->prepare("INSERT INTO stockouts (product_code, date_sold, qty_sold, remarks, customer_name, product_name, category, total_amount) VALUES ('-', ?, 0, 'Cash Lending', ?, 'Cash Loan', 'Money Lending', ?)");
            $insertStmt->execute([$date_sold, $customer_name, $borrow_amount]);

            $checkCust = $pdo->prepare("SELECT id, store_credit, total_payment FROM credits WHERE customer_name = ?");
            $checkCust->execute([$customer_name]);
            $exists = $checkCust->fetch(PDO::FETCH_ASSOC);

            if ($exists) {
                $new_credit = floatval($exists['store_credit']) + $borrow_amount;
                $new_balance = $new_credit - floatval($exists['total_payment']);
                $pdo->prepare("UPDATE credits SET store_credit = ?, total_balance = ? WHERE customer_name = ?")
                    ->execute([$new_credit, $new_balance, $customer_name]);
            } else {
                $pdo->prepare("INSERT INTO credits (customer_name, store_credit, total_payment, total_balance) VALUES (?, ?, 0.00, ?)")
                    ->execute([$customer_name, $borrow_amount, $borrow_amount]);
            }

            $message = "Cash lending recorded successfully!";
            $message_type = "success";

        } elseif ($tx_type === 'Payment') {
            $payment_amount = floatval($_POST['payment_amount'] ?? 0);
            $interest_amount = floatval($_POST['interest_amount'] ?? 0);
            
            if ($payment_amount <= 0 && $interest_amount <= 0) {
                throw new Exception("Please enter a valid payment or interest amount.");
            }

            $total_inflow = $payment_amount + $interest_amount;

            $checkCust = $pdo->prepare("SELECT store_credit, total_payment FROM credits WHERE customer_name = ?");
            $checkCust->execute([$customer_name]);
            $exists = $checkCust->fetch(PDO::FETCH_ASSOC);

            if ($exists) {
                $new_payment = floatval($exists['total_payment']) + $total_inflow;
                $new_balance = floatval($exists['store_credit']) - $new_payment;
                $pdo->prepare("UPDATE credits SET total_payment = ?, total_balance = ? WHERE customer_name = ?")
                    ->execute([$new_payment, $new_balance, $customer_name]);
            } else {
                $pdo->prepare("INSERT INTO credits (customer_name, store_credit, total_payment, total_balance) VALUES (?, 0.00, ?, ?)")
                    ->execute([$customer_name, $total_inflow, -$total_inflow]);
            }

            $logStmt = $pdo->prepare("INSERT INTO stockouts (product_code, date_sold, qty_sold, remarks, customer_name, product_name, category, total_amount) VALUES ('-', ?, 0, 'Cash Lending Payment', ?, 'Payment / Interest', 'Money Lending', ?)");
            $logStmt->execute([$date_sold, $customer_name, $total_inflow]);

            $message = "Payment and interest successfully recorded!";
            $message_type = "success";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Fetch Cash Lending Records
try {
    $stmt = $pdo->query("SELECT * FROM stockouts WHERE remarks IN ('Cash Lending', 'Cash Lending Payment') ORDER BY id DESC LIMIT 100");
    $lendings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sumLending = $pdo->query("SELECT SUM(total_amount) as total_lending FROM stockouts WHERE remarks = 'Cash Lending'");
    $total_lending_res = $sumLending->fetch(PDO::FETCH_ASSOC)['total_lending'] ?? 0;
} catch (PDOException $e) {
    $lendings = [];
    $total_lending_res = 0;
}
?>

<div class="container mx-auto px-4 py-8 mb-12">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">💵 Cash Lending & Interest Management</h1>
            <p class="text-sm text-gray-600">Record cash borrowings, compute duration interest, and track payments.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="lending_summary.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg font-bold shadow-sm hover:bg-indigo-700 transition flex items-center gap-2 text-sm">
                📊 View Summary Report
            </a>
            <div class="bg-teal-100 text-teal-800 px-4 py-2 rounded-lg font-bold shadow-sm">
                Total Active Cash Lending: ₱<?= number_format($total_lending_res, 2) ?>
            </div>
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
            <h2 class="text-lg font-bold text-gray-800 mb-4">New Money Transaction</h2>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Transaction Type</label>
                    <select name="tx_type" id="txTypeSelect" onchange="toggleTxFields()" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-white font-semibold text-teal-700">
                        <option value="Cash Borrow">Cash Borrow (Pautang na Pera)</option>
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

                <!-- Cash Borrow Fields -->
                <div id="borrowFields" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Borrow Amount (₱)</label>
                        <input type="number" step="0.01" name="borrow_amount" id="borrowAmountInput" placeholder="0.00" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                </div>

                <!-- Payment & Interest Fields -->
                <div id="paymentFields" class="space-y-4 hidden">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Payment Amount (Principal)</label>
                        <input type="number" step="0.01" name="payment_amount" value="0.00" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Interest Amount (Tubo)</label>
                        <input type="number" step="0.01" name="interest_amount" value="0.00" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                        <p class="text-xs text-gray-500 mt-1">Kalkulahin ang tubo batay sa 10% kada buwan.</p>
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
                <h2 class="text-lg font-bold text-gray-800">Money Transaction History Log</h2>
                <input type="text" id="searchLending" placeholder="Search records..." class="border rounded-md px-3 py-1 text-sm">
            </div>

            <div class="max-h-[500px] overflow-x-auto overflow-y-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200" id="lendingTable">
                    <thead class="bg-teal-100 sticky top-0 z-10">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Type</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Date</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Customer Name</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Description</th>
                            <th class="px-3 py-3 text-right text-xs font-bold text-gray-800 uppercase">Interest (10%/mo)</th>
                            <th class="px-3 py-3 text-right text-xs font-bold text-gray-800 uppercase">Amount</th>
                            <?php if ($is_admin): ?>
                                <th class="px-3 py-3 text-center text-xs font-bold text-gray-800 uppercase">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm bg-white">
                        <?php if (!empty($lendings)): ?>
                            <?php foreach ($lendings as $l): ?>
                                <?php 
                                    $tx_date = new DateTime($l['date_sold']);
                                    $current_date = new DateTime();
                                    $interval = $tx_date->diff($current_date);
                                    
                                    $months = ($interval->y * 12) + $interval->m;
                                    if ($interval->d > 0) {
                                        $months += ($interval->d / 30);
                                    }
                                    if ($months < 1) {
                                        $months = 1;
                                    }

                                    $computed_interest = 0.00;
                                    if ($l['remarks'] === 'Cash Lending') {
                                        $computed_interest = floatval($l['total_amount']) * 0.10 * $months;
                                    }
                                ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-3">
                                        <?php if ($l['remarks'] === 'Cash Lending'): ?>
                                            <span class="bg-blue-100 text-blue-800 text-xs font-bold px-2 py-0.5 rounded">Cash Borrow</span>
                                        <?php else: ?>
                                            <span class="bg-green-100 text-green-800 text-xs font-bold px-2 py-0.5 rounded">Payment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600"><?= htmlspecialchars($l['date_sold']) ?></td>
                                    <td class="px-3 py-3 text-gray-700 font-medium"><?= htmlspecialchars($l['customer_name']) ?></td>
                                    <td class="px-3 py-3 text-gray-900"><?= htmlspecialchars($l['product_name']) ?></td>
                                    <td class="px-3 py-3 text-right font-semibold text-amber-600">
                                        <?php if ($l['remarks'] === 'Cash Lending'): ?>
                                            ₱<?= number_format($computed_interest, 2) ?> <span class="text-[10px] text-gray-400 block">(~<?= number_format($months, 1) ?> mo)</span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-3 text-right font-bold text-gray-900">₱<?= number_format($l['total_amount'], 2) ?></td>
                                    
                                    <?php if ($is_admin): ?>
                                        <td class="px-3 py-3 text-center space-x-2">
                                            <button onclick="openEditModal(<?= $l['id'] ?>, '<?= htmlspecialchars($l['customer_name'], ENT_QUOTES) ?>', '<?= $l['date_sold'] ?>', <?= $l['total_amount'] ?>)" class="text-indigo-600 hover:text-indigo-900 font-semibold text-xs bg-indigo-50 px-2 py-1 rounded">Edit</button>
                                            <a href="lending.php?delete_id=<?= $l['id'] ?>" onclick="return confirm('Are you sure you want to delete this transaction?');" class="text-red-600 hover:text-red-900 font-semibold text-xs bg-red-50 px-2 py-1 rounded">Delete</a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= $is_admin ? 7 : 6 ?>" class="px-4 py-4 text-center text-gray-500">No transaction records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-xl shadow-lg w-full max-w-md">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Edit Transaction</h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="edit_id" id="edit_id">
            <div>
                <label class="block text-sm font-medium text-gray-700">Customer Name</label>
                <input type="text" name="customer_name" id="edit_customer_name" readonly class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 shadow-sm p-2 border">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Date</label>
                <input type="date" name="date_sold" id="edit_date_sold" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Amount (₱)</label>
                <input type="number" step="0.01" name="total_amount" id="edit_total_amount" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeEditModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 font-semibold text-sm">Cancel</button>
                <button type="submit" name="update_lending" class="bg-teal-600 text-white px-4 py-2 rounded-md hover:bg-teal-700 font-semibold text-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleTxFields() {
    let type = document.getElementById('txTypeSelect').value;
    let borrowFields = document.getElementById('borrowFields');
    let paymentFields = document.getElementById('paymentFields');
    let borrowInput = document.getElementById('borrowAmountInput');

    if (type === 'Cash Borrow') {
        borrowFields.classList.remove('hidden');
        paymentFields.classList.add('hidden');
        borrowInput.setAttribute('required', 'required');
    } else {
        borrowFields.classList.add('hidden');
        paymentFields.classList.remove('hidden');
        borrowInput.removeAttribute('required');
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

function openEditModal(id, customer, date, amount) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_customer_name').value = customer;
    document.getElementById('edit_date_sold').value = date;
    document.getElementById('edit_total_amount').value = amount;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
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
