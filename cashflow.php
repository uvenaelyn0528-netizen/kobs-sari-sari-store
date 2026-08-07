<?php
session_start();
require_once 'db.php';

$role = strtolower(trim($_SESSION['role'] ?? 'viewer'));
$is_viewer = ($role === 'viewer');
$is_admin = ($role === 'admin');

include 'header.php';

$message = '';
$message_type = '';

// Handle CSV Import for Cashflow
if (!$is_viewer && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_cashflow_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileExtension === 'csv') {
            if (($handle = fopen($fileTmpPath, 'r')) !== FALSE) {
                $header = fgetcsv($handle, 1000, ",");
                
                $header = array_map(function($h) {
                    return strtolower(trim(str_replace('"', '', $h)));
                }, $header);

                $dateIndex        = array_search('date', $header);
                $particularsIndex = array_search('particulars', $header);
                $amountIndex      = array_search('amount', $header);
                $nameIndex        = array_search('name', $header);
                $remarksIndex     = array_search('remarks', $header);

                if ($dateIndex === false) $dateIndex = 0;
                if ($particularsIndex === false) $particularsIndex = 1;
                if ($amountIndex === false) $amountIndex = 2;
                if ($nameIndex === false) $nameIndex = 3;
                if ($remarksIndex === false) $remarksIndex = 4;

                $imported_count = 0;
                $db_error = '';

                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (empty(array_filter($data))) continue;

                    $date_val        = trim($data[$dateIndex] ?? '');
                    $particulars_val = trim($data[$particularsIndex] ?? '');
                    $amount_raw      = $data[$amountIndex] ?? 0;
                    $amount_val      = floatval(str_replace([',', ' '], '', $amount_raw));
                    $name_val        = trim($data[$nameIndex] ?? '');
                    $remarks_val     = trim($data[$remarksIndex] ?? '');

                    if (!empty($date_val) || !empty($particulars_val)) {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO cashflow (date, particulars, amount, name, remarks) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$date_val, $particulars_val, $amount_val, $name_val, $remarks_val]);
                            $imported_count++;
                        } catch (PDOException $e) {
                            $db_error = $e->getMessage();
                        }
                    }
                }
                fclose($handle);

                if ($imported_count > 0) {
                    $message = "Successfully imported $imported_count cashflow records from CSV!";
                    $message_type = "success";
                } else {
                    $message = "Imported 0 records. Check if your CSV has data rows below the header." . ($db_error ? " Error: " . $db_error : "");
                    $message_type = "error";
                }
            } else {
                $message = "Error opening the uploaded file.";
                $message_type = "error";
            }
        } else {
            $message = "Please upload a valid .csv file.";
            $message_type = "error";
        }
    } else {
        $message = "Please select a file to upload.";
        $message_type = "error";
    }
}

// Handle Manual Single Entry Form
if (!$is_viewer && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entry'])) {
    $date_val        = trim($_POST['date'] ?? '');
    $particulars_val = trim($_POST['particulars'] ?? '');
    $amount_val      = floatval($_POST['amount'] ?? 0);
    $name_val        = trim($_POST['name'] ?? '');
    $remarks_val     = trim($_POST['remarks'] ?? 'Cash Received');

    try {
        $stmt = $pdo->prepare("INSERT INTO cashflow (date, particulars, amount, name, remarks) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$date_val, $particulars_val, $amount_val, $name_val, $remarks_val]);
        $message = "Cashflow entry added successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle Edit Entry (Admin Only)
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_entry'])) {
    $id              = intval($_POST['id'] ?? 0);
    $date_val        = trim($_POST['date'] ?? '');
    $particulars_val = trim($_POST['particulars'] ?? '');
    $amount_val      = floatval($_POST['amount'] ?? 0);
    $name_val        = trim($_POST['name'] ?? '');
    $remarks_val     = trim($_POST['remarks'] ?? 'Cash Received');

    try {
        $stmt = $pdo->prepare("UPDATE cashflow SET date = ?, particulars = ?, amount = ?, name = ?, remarks = ? WHERE id = ?");
        $stmt->execute([$date_val, $particulars_val, $amount_val, $name_val, $remarks_val, $id]);
        $message = "Cashflow entry updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating entry: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle Delete Entry (Admin Only)
if ($is_admin && isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM cashflow WHERE id = ?");
        $stmt->execute([$delete_id]);
        $message = "Cashflow entry deleted successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error deleting entry: " . $e->getMessage();
        $message_type = "error";
    }
}

// Fetch Cashflow Records & Compute Totals
try {
    $stmt = $pdo->query("SELECT * FROM cashflow ORDER BY id DESC");
    $cashflows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cashflows = [];
}

$total_received = 0;
$total_expenses = 0;

foreach ($cashflows as $row) {
    $amt = floatval($row['amount'] ?? 0);
    $rem = strtolower(trim($row['remarks'] ?? ''));
    if ($rem === 'expenses' || $rem === 'expense') {
        $total_expenses += $amt;
    } else {
        $total_received += $amt;
    }
}
$remaining_cash = $total_received - $total_expenses;
?>

<div class="container mx-auto px-4 py-4">
    <!-- Back Button Row -->
    <div class="mb-3 flex justify-between items-center">
        <a href="management.php" class="inline-flex items-center px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-semibold rounded-md shadow-sm transition">
            <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Management Hub
        </a>
    </div>

    <!-- Slim Top Header & Compact Import Form -->
    <div class="mb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-white px-4 py-3 rounded-xl shadow-sm border border-gray-100">
        <div>
            <h1 class="text-lg font-bold text-gray-800">📈 Cashflow Tracking & Import</h1>
            <p class="text-[11px] text-gray-500">Upload your cashflow CSV or log entries manually.</p>
        </div>

        <?php if (!$is_viewer): ?>
        <form method="POST" enctype="multipart/form-data" class="flex items-center gap-2 w-full sm:w-auto">
            <input type="file" name="csv_file" accept=".csv" required class="block w-full sm:w-auto text-xs text-gray-500 file:mr-2 file:py-1 file:px-2.5 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            <button type="submit" name="import_cashflow_csv" class="bg-emerald-600 text-white py-1 px-3 rounded hover:bg-emerald-700 transition font-semibold shadow-sm text-xs whitespace-nowrap">
                Import File
            </button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded text-xs <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Sidebar: Manual Entry Form -->
        <?php if (!$is_viewer): ?>
        <div class="space-y-6">
            <div class="bg-white p-5 rounded-xl shadow-md">
                <h2 class="text-md font-bold text-gray-800 mb-3">✍️ Add Manual Entry</h2>
                <form method="POST" class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Date</label>
                        <input type="text" name="date" placeholder="e.g., 18-Feb" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-1.5 border text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Particulars</label>
                        <input type="text" name="particulars" placeholder="e.g., Share, Purchased, Sales Received" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-1.5 border text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Amount (₱)</label>
                        <input type="number" step="any" name="amount" placeholder="0.00" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-1.5 border text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Name (Optional)</label>
                        <input type="text" name="name" placeholder="Pangalan" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-1.5 border text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Remarks</label>
                        <select name="remarks" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-1.5 border text-xs bg-white">
                            <option value="Cash Received">Cash Received</option>
                            <option value="Expenses">Expenses</option>
                        </select>
                    </div>
                    <button type="submit" name="add_entry" class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition font-semibold shadow text-xs">
                        Save Transaction Entry
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Right Content Section: Slimmer Summary Dashboard & Transaction Table -->
        <div class="<?= $is_viewer ? 'lg:col-span-3' : 'lg:col-span-2' ?> space-y-4">
            <!-- Slimmer Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="bg-white px-4 py-3 rounded-xl shadow-sm border-l-4 border-green-500 flex flex-col justify-center">
                    <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider">Total Amount Received</p>
                    <p class="text-base font-bold text-green-700 mt-0.5">₱<?= number_format($total_received, 2) ?></p>
                </div>
                <div class="bg-white px-4 py-3 rounded-xl shadow-sm border-l-4 border-red-500 flex flex-col justify-center">
                    <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider">Total Expenses</p>
                    <p class="text-base font-bold text-red-700 mt-0.5">₱<?= number_format($total_expenses, 2) ?></p>
                </div>
                <div class="bg-white px-4 py-3 rounded-xl shadow-sm border-l-4 border-indigo-500 flex flex-col justify-center">
                    <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider">Remaining Cash on Hand</p>
                    <p class="text-base font-bold text-indigo-700 mt-0.5">₱<?= number_format($remaining_cash, 2) ?></p>
                </div>
            </div>

            <!-- Cashflow Transaction Table with Scrollbar -->
            <div class="bg-white p-5 rounded-xl shadow-md">
                <h2 class="text-md font-bold text-gray-800 mb-3">Cashflow Transactions</h2>
                <div class="max-h-[550px] overflow-y-auto pr-1">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50 sticky top-0 z-10 shadow-sm">
                            <tr>
                                <th class="px-3 py-2.5 text-left font-medium text-gray-500 uppercase bg-gray-50">Date</th>
                                <th class="px-3 py-2.5 text-left font-medium text-gray-500 uppercase bg-gray-50">Particulars</th>
                                <th class="px-3 py-2.5 text-left font-medium text-gray-500 uppercase bg-gray-50">Name</th>
                                <th class="px-3 py-2.5 text-left font-medium text-gray-500 uppercase bg-gray-50">Remarks</th>
                                <th class="px-3 py-2.5 text-right font-medium text-gray-500 uppercase bg-gray-50">Amount</th>
                                <?php if ($is_admin): ?>
                                    <th class="px-3 py-2.5 text-center font-medium text-gray-500 uppercase bg-gray-50">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <?php if (!empty($cashflows)): ?>
                                <?php foreach ($cashflows as $row): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-3 py-2.5 text-gray-700 whitespace-nowrap"><?= htmlspecialchars($row['date'] ?? '') ?></td>
                                        <td class="px-3 py-2.5 font-semibold text-gray-900"><?= htmlspecialchars($row['particulars'] ?? '') ?></td>
                                        <td class="px-3 py-2.5 text-gray-700"><?= htmlspecialchars($row['name'] ?? '-') ?></td>
                                        <td class="px-3 py-2.5 text-gray-600">
                                            <span class="px-2 py-0.5 rounded text-[11px] <?= (strtolower($row['remarks'] ?? '') === 'cash received') ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                                <?= htmlspecialchars($row['remarks'] ?? '') ?>
                                            </span>
                                        </td>
                                        <td class="px-3 py-2.5 text-right font-bold text-gray-900">₱<?= number_format(floatval($row['amount'] ?? 0), 2) ?></td>
                                        <?php if ($is_admin): ?>
                                            <td class="px-3 py-2.5 text-center whitespace-nowrap space-x-1.5">
                                                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)" class="text-indigo-600 hover:text-indigo-900 font-semibold bg-indigo-50 px-2 py-0.5 rounded transition">Edit</button>
                                                <a href="cashflow.php?delete_id=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this cashflow entry?');" class="text-red-600 hover:text-red-900 font-semibold bg-red-50 px-2 py-0.5 rounded transition">Delete</a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?= $is_admin ? '6' : '5' ?>" class="px-4 py-6 text-center text-gray-500">No cashflow logs found. Upload your CSV file or add entries manually.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal (Admin Only) -->
<?php if ($is_admin): ?>
<div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md">
        <h2 class="text-lg font-bold text-gray-800 mb-4">✏️ Edit Cashflow Entry</h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="id" id="edit_id">
            <div>
                <label class="block text-sm font-medium text-gray-700">Date</label>
                <input type="text" name="date" id="edit_date" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Particulars</label>
                <input type="text" name="particulars" id="edit_particulars" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Amount (₱)</label>
                <input type="number" step="any" name="amount" id="edit_amount" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" name="name" id="edit_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Remarks</label>
                <select name="remarks" id="edit_remarks" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border text-sm bg-white">
                    <option value="Cash Received">Cash Received</option>
                    <option value="Expenses">Expenses</option>
                </select>
            </div>
            <div class="flex justify-end space-x-3 pt-2">
                <button type="button" onclick="closeEditModal()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300 text-sm font-semibold">Cancel</button>
                <button type="submit" name="edit_entry" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm font-semibold shadow">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(row) {
    document.getElementById('edit_id').value = row.id;
    document.getElementById('edit_date').value = row.date;
    document.getElementById('edit_particulars').value = row.particulars;
    document.getElementById('edit_amount').value = row.amount;
    document.getElementById('edit_name').value = row.name || '';
    document.getElementById('edit_remarks').value = row.remarks || 'Cash Received';
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>
<?php endif; ?>
