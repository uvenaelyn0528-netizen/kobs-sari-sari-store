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

// Handle Deletion / Return
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    try {
        $st_stmt = $pdo->prepare("SELECT * FROM stockouts WHERE id = ? AND remarks = 'Lending'");
        $st_stmt->execute([$del_id]);
        $tx = $st_stmt->fetch(PDO::FETCH_ASSOC);

        if ($tx) {
            // Revert inventory
            if ($tx['product_code'] !== '-' && !empty($tx['product_code'])) {
                $revProd = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?, \"Stock_out\" = \"Stock_out\" - ? WHERE product_code = ?");
                $revProd->execute([$tx['qty_sold'], $tx['qty_sold'], $tx['product_code']]);
            }

            // Revert credits ledger
            if (!empty($tx['customer_name'])) {
                $c_name = $tx['customer_name'];
                $chkC = $pdo->prepare("SELECT store_credit, total_payment FROM credits WHERE customer_name = ?");
                $chkC->execute([$c_name]);
                $c_data = $chkC->fetch(PDO::FETCH_ASSOC);

                if ($c_data) {
                    $new_credit = floatval($c_data['store_credit']) - floatval($tx['total_amount']);
                    $new_bal = $new_credit - floatval($c_data['total_payment']);
                    $updC = $pdo->prepare("UPDATE credits SET store_credit = ?, total_balance = ? WHERE customer_name = ?");
                    $updC->execute([$new_credit, $new_bal, $c_name]);
                }
            }

            $delStmt = $pdo->prepare("DELETE FROM stockouts WHERE id = ?");
            $delStmt->execute([$del_id]);

            $message = "Lending record deleted and inventory successfully reverted.";
            $message_type = "success";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lending'])) {
    $product_code  = trim($_POST['product_code'] ?? '');
    $qty_sold      = intval($_POST['qty_sold'] ?? 0);
    $customer_name = trim($_POST['customer_name'] ?? '');
    $date_sold     = $_POST['date_sold'] ?? date('Y-m-d');
    $remarks       = 'Lending';

    try {
        if (empty($product_code) || $qty_sold <= 0 || empty($customer_name)) {
            throw new Exception("Please complete all required fields (Product Code, Quantity, and Customer Name).");
        }

        $stmt = $pdo->prepare("SELECT * FROM products WHERE product_code = ?");
        $stmt->execute([$product_code]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            $total_amount = $qty_sold * floatval($product['retail_price']);
            
            // Insert into stockouts with remarks = 'Lending'
            $insertStmt = $pdo->prepare("INSERT INTO stockouts (product_code, date_sold, qty_sold, remarks, customer_name, product_name, category, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->execute([
                $product_code, 
                $date_sold, 
                $qty_sold, 
                $remarks, 
                $customer_name, 
                $product['product_name'], 
                $product['category'], 
                $total_amount
            ]);

            // Deduct product stock
            $updateProd = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ?, \"Stock_out\" = \"Stock_out\" + ? WHERE product_code = ?");
            $updateProd->execute([$qty_sold, $qty_sold, $product_code]);

            // Update credit/lending ledger
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

            $message = "Lending transaction recorded successfully!";
            $message_type = "success";
        } else {
            throw new Exception("Product barcode not found in inventory.");
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Fetch Lending Records
try {
    $stmt = $pdo->query("SELECT * FROM stockouts WHERE remarks = 'Lending' ORDER BY id DESC LIMIT 100");
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
            <h1 class="text-2xl font-bold text-gray-800">🤝 Item Lending Management</h1>
            <p class="text-sm text-gray-600">Record and monitor items lent out to customers.</p>
        </div>
        <div class="bg-teal-100 text-teal-800 px-4 py-2 rounded-lg font-bold shadow-sm">
            Total Active Lending: ₱<?= number_format($total_lending_res, 2) ?>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- New Lending Form -->
        <div class="bg-white p-6 rounded-xl shadow-md h-fit">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Record New Lending</h2>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Product Barcode / Code</label>
                    <input type="text" name="product_code" required placeholder="Scan barcode or type code" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Quantity</label>
                    <input type="number" name="qty_sold" value="1" min="1" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
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
                <div>
                    <label class="block text-sm font-medium text-gray-700">Date</label>
                    <input type="date" name="date_sold" value="<?= date('Y-m-d') ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div>
                    <button type="submit" name="save_lending" class="w-full bg-teal-600 text-white py-2 px-4 rounded-md hover:bg-teal-700 transition font-semibold shadow">
                        Save Lending Record
                    </button>
                </div>
            </form>
        </div>

        <!-- Lending History Table -->
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold text-gray-800">Lending History Log</h2>
                <input type="text" id="searchLending" placeholder="Search lending records..." class="border rounded-md px-3 py-1 text-sm">
            </div>

            <div class="max-h-[500px] overflow-x-auto overflow-y-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200" id="lendingTable">
                    <thead class="bg-teal-100 sticky top-0 z-10">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Code</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Date</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Qty</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Customer Name</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Item Description</th>
                            <th class="px-3 py-3 text-right text-xs font-bold text-gray-800 uppercase">Amount</th>
                            <th class="px-3 py-3 text-center text-xs font-bold text-gray-800 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm bg-white">
                        <?php if (!empty($lendings)): ?>
                            <?php foreach ($lendings as $l): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-3 font-mono text-xs text-teal-700 font-semibold"><?= htmlspecialchars($l['product_code']) ?></td>
                                    <td class="px-3 py-3 text-gray-600"><?= htmlspecialchars($l['date_sold']) ?></td>
                                    <td class="px-3 py-3 font-bold text-gray-800"><?= htmlspecialchars($l['qty_sold']) ?></td>
                                    <td class="px-3 py-3 text-gray-700 italic"><?= htmlspecialchars($l['customer_name']) ?></td>
                                    <td class="px-3 py-3 font-medium text-gray-900"><?= htmlspecialchars($l['product_name']) ?></td>
                                    <td class="px-3 py-3 text-right font-bold text-gray-900">₱<?= number_format($l['total_amount'], 2) ?></td>
                                    <td class="px-3 py-3 text-center">
                                        <a href="lending.php?delete_id=<?= $l['id'] ?>" onclick="return confirm('Are you sure you want to remove this lending record? This will return the items back to inventory.')" class="text-red-600 hover:text-red-900 font-semibold text-xs bg-red-50 px-2 py-1 rounded">Return / Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-4 py-4 text-center text-gray-500">No active lending records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
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

// Search filter for lending table
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
