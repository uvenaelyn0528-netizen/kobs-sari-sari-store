<?php
require_once 'db.php';
include 'header.php';

$message = '';
$message_type = '';

// Master list ng customers para sa dropdown
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

// Handle Delete Action para sa Admin
$is_admin = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') || (isset($_SESSION['username']) && strtolower($_SESSION['username']) === 'admin');

if ($is_admin && isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    try {
        $delStmt = $pdo->prepare("DELETE FROM stockouts WHERE id = ? AND remarks = 'ATM Withdrawal'");
        $delStmt->execute([$del_id]);
        $message = "ATM Withdrawal record successfully deleted!";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "Error deleting record: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle Form Submission para sa bagong ATM Withdrawal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_atm'])) {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $atm_amount    = floatval($_POST['atm_amount'] ?? 0);
    $atm_fee       = floatval($_POST['atm_fee'] ?? 0);
    $date_sold     = $_POST['date_sold'] ?? date('Y-m-d');

    try {
        if (empty($customer_name) || $atm_amount <= 0) {
            throw new Exception("Mangyaring pumili ng customer at maglagay ng wastong halaga ng withdrawal.");
        }

        $total_withdrawal = $atm_amount + $atm_fee;
        // Gagamitin ang product_name para itabi ang halaga ng withdrawal, at ang total_amount para sa (Amount + Charge)
        $product_desc = "Withdrawal: ₱" . number_format($atm_amount, 2);

        // I-save sa stockouts table (gamit ang remarks = 'ATM Withdrawal', product_code = fee para ma-store natin ang charge)
        $stmt = $pdo->prepare("INSERT INTO stockouts (product_code, date_sold, qty_sold, remarks, customer_name, product_name, category, total_amount) VALUES (?, ?, 1, 'ATM Withdrawal', ?, ?, 'ATM Services', ?)");
        $stmt->execute([$atm_fee, $date_sold, $customer_name, $product_desc, $total_withdrawal]);

        $message = "ATM Withdrawal naitala nang matagumpay!";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Kunin ang mga ATM Withdrawal records mula sa database
try {
    $stmt = $pdo->query("SELECT * FROM stockouts WHERE remarks = 'ATM Withdrawal' ORDER BY id DESC LIMIT 100");
    $atm_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sumStmt = $pdo->query("SELECT SUM(total_amount) as total_atm FROM stockouts WHERE remarks = 'ATM Withdrawal'");
    $total_atm_res = $sumStmt->fetch(PDO::FETCH_ASSOC)['total_atm'] ?? 0;
} catch (PDOException $e) {
    $atm_records = [];
    $total_atm_res = 0;
}
?>

<div class="container mx-auto px-4 py-8 mb-12">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">🏧 ATM Withdrawal Management</h1>
            <p class="text-sm text-gray-600">Itala at subaybayan ang mga transaksyon sa pag-withdraw sa ATM.</p>
        </div>
        <div class="bg-emerald-100 text-emerald-800 px-4 py-2 rounded-lg font-bold shadow-sm">
            Total ATM Transactions: ₱<?= number_format($total_atm_res, 2) ?>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Form para sa Bagong ATM Transaction -->
        <div class="bg-white p-6 rounded-xl shadow-md h-fit">
            <h2 class="text-lg font-bold text-gray-800 mb-4">New ATM Withdrawal</h2>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Customer Name</label>
                    <select name="customer_name" id="customerSelect" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-white">
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($master_customer_list as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Withdrawal Amount (₱)</label>
                    <input type="number" step="0.01" name="atm_amount" id="atmAmount" placeholder="0.00" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Fee / Charge (₱)</label>
                    <input type="number" step="0.01" name="atm_fee" id="atmFee" value="0.00" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Date</label>
                    <input type="date" name="date_sold" value="<?= date('Y-m-d') ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>

                <div>
                    <button type="submit" name="save_atm" class="w-full bg-emerald-600 text-white py-2 px-4 rounded-md hover:bg-emerald-700 transition font-semibold shadow">
                        Save ATM Transaction
                    </button>
                </div>
            </form>
        </div>

        <!-- History Table ng ATM Withdrawals -->
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold text-gray-800">ATM Transaction History</h2>
                <input type="text" id="searchAtm" placeholder="Search records..." class="border rounded-md px-3 py-1 text-sm">
            </div>

            <div class="max-h-[500px] overflow-x-auto overflow-y-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200" id="atmTable">
                    <thead class="bg-emerald-100 sticky top-0 z-10">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Date</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Customer Name</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-800 uppercase">Details</th>
                            <th class="px-3 py-3 text-right text-xs font-bold text-gray-800 uppercase">Charge</th>
                            <th class="px-3 py-3 text-right text-xs font-bold text-gray-800 uppercase">Total Amount</th>
                            <?php if ($is_admin): ?>
                                <th class="px-3 py-3 text-center text-xs font-bold text-gray-800 uppercase">Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm bg-white">
                        <?php if (!empty($atm_records)): ?>
                            <?php foreach ($atm_records as $row): 
                                $charge = floatval($row['product_code']); // Nakatabi dito ang fee/charge
                            ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-3 text-gray-600"><?= htmlspecialchars($row['date_sold']) ?></td>
                                    <td class="px-3 py-3 text-gray-800 font-medium"><?= htmlspecialchars($row['customer_name']) ?></td>
                                    <td class="px-3 py-3 text-gray-600 text-xs"><?= htmlspecialchars($row['product_name']) ?></td>
                                    <td class="px-3 py-3 text-right text-gray-700 font-medium">₱<?= number_format($charge, 2) ?></td>
                                    <td class="px-3 py-3 text-right font-bold text-emerald-700">₱<?= number_format($row['total_amount'], 2) ?></td>
                                    
                                    <?php if ($is_admin): ?>
                                        <td class="px-3 py-3 text-center">
                                            <a href="atm.php?delete_id=<?= $row['id'] ?>" onclick="return confirm('Sigurado ka bang gusto mong tanggalin ang rekord na ito?');" class="text-red-600 hover:text-red-900 font-semibold text-xs bg-red-50 px-2 py-1 rounded">Delete</a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= $is_admin ? 6 : 5 ?>" class="px-4 py-4 text-center text-gray-500">Walang nakitang rekord ng ATM.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Search filter para sa table
document.getElementById('searchAtm').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#atmTable tbody tr');
    rows.forEach(row => {
        let text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>
