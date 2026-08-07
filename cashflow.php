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
                    return strtolower(trim($h));
                }, $header);

                $dateIndex = array_search('date', $header);
                $particularsIndex = array_search('particulars', $header);
                $amountIndex = array_search('amount', $header);
                $nameIndex = array_search('name', $header);
                $remarksIndex = array_search('remarks', $header);

                // Fallbacks based on standard column layout (A=0, B=1, C=2, D=3, E=4)
                if ($dateIndex === false) $dateIndex = 0;
                if ($particularsIndex === false) $particularsIndex = 1;
                if ($amountIndex === false) $amountIndex = 2;
                if ($nameIndex === false) $nameIndex = 3;
                if ($remarksIndex === false) $remarksIndex = 4;

                $imported_count = 0;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $date_val = trim($data[$dateIndex] ?? '');
                    $particulars_val = trim($data[$particularsIndex] ?? '');
                    $amount_raw = $data[$amountIndex] ?? 0;
                    $amount_val = floatval(str_replace(',', '', $amount_raw));
                    $name_val = trim($data[$nameIndex] ?? '');
                    $remarks_val = trim($data[$remarksIndex] ?? '');

                    if (!empty($date_val) && !empty($particulars_val)) {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO cashflow (date, particulars, amount, name, remarks) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$date_val, $particulars_val, $amount_val, $name_val, $remarks_val]);
                            $imported_count++;
                        } catch (PDOException $e) {
                            // Skip or log row error if table structure differs
                        }
                    }
                }
                fclose($handle);
                $message = "Successfully imported $imported_count cashflow records from CSV!";
                $message_type = "success";
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

// Handle Single Entry Add Form (if applicable)
if (!$is_viewer && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entry'])) {
    $date_val        = trim($_POST['date'] ?? date('Y-m-d'));
    $type_val        = trim($_POST['type'] ?? '');
    $category_val    = trim($_POST['category'] ?? '');
    $amount_val      = floatval($_POST['amount'] ?? 0);
    $notes_val       = trim($_POST['notes'] ?? '');

    try {
        $stmt = $pdo->prepare("INSERT INTO cashflow (date, particulars, amount, remarks) VALUES (?, ?, ?, ?)");
        $stmt->execute([$date_val, $category_val, $amount_val, $type_val]);
        $message = "Cashflow entry added successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Fetch Cashflow Records
try {
    $stmt = $pdo->query("SELECT * FROM cashflow ORDER BY id DESC");
    $cashflows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cashflows = [];
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center">
        <a href="management.php" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-semibold rounded-md shadow-sm transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Management Hub
        </a>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">📈 Cashflow Tracking & Import</h1>
        <p class="text-xs text-gray-500 mt-1">Upload your cashflow CSV or log entries manually.</p>
    </div>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <?php if (!$is_viewer): ?>
        <div class="space-y-6">
            <!-- CSV Import Box -->
            <div class="bg-white p-6 rounded-xl shadow-md">
                <h2 class="text-lg font-bold text-gray-800 mb-2">📁 Import Cashflow CSV</h2>
                <p class="text-xs text-gray-500 mb-4">Upload your CSV with columns: `Date`, `Particulars`, `Amount`, `Name`, `Remarks`.</p>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <input type="file" name="csv_file" accept=".csv" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>
                    <button type="submit" name="import_cashflow_csv" class="w-full bg-emerald-600 text-white py-2 px-4 rounded-md hover:bg-emerald-700 transition font-semibold shadow">
                        Upload & Import Cashflow
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Cashflow Transaction Table -->
        <div class="<?= $is_viewer ? 'lg:col-span-3' : 'lg:col-span-2' ?> bg-white p-6 rounded-xl shadow-md">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Cashflow Transactions</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Particulars</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remarks</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (!empty($cashflows)): ?>
                            <?php foreach ($cashflows as $row): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-3 text-gray-700 whitespace-nowrap"><?= htmlspecialchars($row['date'] ?? '') ?></td>
                                    <td class="px-3 py-3 font-semibold text-gray-900"><?= htmlspecialchars($row['particulars'] ?? '') ?></td>
                                    <td class="px-3 py-3 text-gray-700"><?= htmlspecialchars($row['name'] ?? '-') ?></td>
                                    <td class="px-3 py-3 text-gray-600">
                                        <span class="px-2 py-1 rounded text-xs <?= (strtolower($row['remarks'] ?? '') === 'cash received') ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                            <?= htmlspecialchars($row['remarks'] ?? '') ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-right font-bold text-gray-900">₱<?= number_format(floatval($row['amount'] ?? 0), 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">No cashflow logs found. Upload your CSV file to populate records.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
