<?php
session_start();
require_once 'db.php';

$role = strtolower(trim($_SESSION['role'] ?? 'viewer'));
$is_viewer = ($role === 'viewer');
$is_admin = ($role === 'admin');

include 'header.php';

$message = '';
$message_type = '';
$amount_per_share = 3000.00;
$total_dividend_pool = 275419.80; 

// Handle Delete Partner Share
if ($is_admin && isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM shares WHERE id = ?");
        $stmt->execute([$delete_id]);
        $message = "Partner share deleted successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error deleting record: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle Edit Partner Share
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_share'])) {
    $edit_id           = intval($_POST['edit_id'] ?? 0);
    $partner_name      = trim($_POST['partner_name'] ?? '');
    $shares_count      = floatval($_POST['shares_count'] ?? 0);
    $investment_amount = $shares_count * $amount_per_share;

    try {
        $stmt = $pdo->prepare("UPDATE shares SET partner_name = ?, shares_count = ?, investment_amount = ? WHERE id = ?");
        $stmt->execute([$partner_name, $shares_count, $investment_amount, $edit_id]);
        $message = "Partner share updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating record: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle CSV Import
if (!$is_viewer && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileExtension === 'csv') {
            if (($handle = fopen($fileTmpPath, 'r')) !== FALSE) {
                $header = fgetcsv($handle, 1000, ",");
                $header = array_map(function($h) {
                    return strtolower(trim(str_replace(['# of ', '.'], ['', ''], $h)));
                }, $header);

                $nameIndex = array_search('name', $header);
                $sharesIndex = array_search('shares', $header);

                if ($nameIndex === false) $nameIndex = 1; 
                if ($sharesIndex === false) $sharesIndex = 2; 

                $imported_count = 0;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (isset($data[$nameIndex]) && trim($data[$nameIndex]) !== '') {
                        $partner_name = trim($data[$nameIndex]);
                        $shares_raw = $data[$sharesIndex] ?? 0;
                        $shares_count = floatval(preg_replace('/[^0-9.]/', '', $shares_raw));
                        
                        if ($shares_count > 0 || trim($partner_name) !== '') {
                            $investment_amount = $shares_count * $amount_per_share;

                            try {
                                $stmt = $pdo->prepare("INSERT INTO shares (partner_name, shares_count, investment_amount) 
                                                       VALUES (?, ?, ?) 
                                                       ON CONFLICT (partner_name) 
                                                       DO UPDATE SET shares_count = EXCLUDED.shares_count, investment_amount = EXCLUDED.investment_amount");
                                $stmt->execute([$partner_name, $shares_count, $investment_amount]);
                                $imported_count++;
                            } catch (PDOException $e) {
                                try {
                                    $stmt = $pdo->prepare("INSERT INTO shares (partner_name, shares_count, investment_amount) 
                                                           VALUES (?, ?, ?) 
                                                           ON DUPLICATE KEY UPDATE shares_count = VALUES(shares_count), investment_amount = VALUES(investment_amount)");
                                    $stmt->execute([$partner_name, $shares_count, $investment_amount]);
                                    $imported_count++;
                                } catch (PDOException $ex) {
                                    // Skip row if error persists
                                }
                            }
                        }
                    }
                }
                fclose($handle);
                $message = "Successfully imported $imported_count records from CSV!";
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

// Handle Single Add Partner Share Form
if (!$is_viewer && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_share'])) {
    $partner_name      = trim($_POST['partner_name'] ?? '');
    $shares_count      = floatval($_POST['shares_count'] ?? 0);
    $investment_amount = $shares_count * $amount_per_share;

    try {
        $stmt = $pdo->prepare("INSERT INTO shares (partner_name, shares_count, investment_amount) VALUES (?, ?, ?)");
        $stmt->execute([$partner_name, $shares_count, $investment_amount]);
        $message = "Partner share recorded successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Fetch Shares
try {
    $stmt = $pdo->query("SELECT * FROM shares ORDER BY partner_name ASC");
    $shares = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $shares = [];
}

// Calculate Totals for Summary Cards
$total_capital = 0;
$total_shares_count = 0;
foreach ($shares as $s) {
    $total_capital += floatval($s['investment_amount'] ?? 0);
    $total_shares_count += floatval($s['shares_count'] ?? 0);
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

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">🤝 KOBS COOP Shares & Dividends</h1>
            <p class="text-xs text-gray-500 mt-1">Import CSV lists or manage shareholders directly.</p>
        </div>

        <!-- Summary Metric Cards -->
        <div class="flex flex-wrap gap-3">
            <div class="bg-white px-4 py-2 rounded-lg shadow border border-gray-200 text-center">
                <span class="block text-xs text-gray-500 uppercase font-medium">Total Capital</span>
                <span class="text-base font-bold text-gray-800">₱<?= number_format($total_capital, 2) ?></span>
            </div>
            <div class="bg-white px-4 py-2 rounded-lg shadow border border-gray-200 text-center">
                <span class="block text-xs text-gray-500 uppercase font-medium">Total Dividend Pool</span>
                <span class="text-base font-bold text-green-700">₱<?= number_format($total_dividend_pool, 2) ?></span>
            </div>
            <div class="bg-white px-4 py-2 rounded-lg shadow border border-gray-200 text-center">
                <span class="block text-xs text-gray-500 uppercase font-medium">Amount / Share</span>
                <span class="text-base font-bold text-indigo-600">₱<?= number_format($amount_per_share, 2) ?></span>
            </div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <?php if (!$is_viewer): ?>
        <div class="space-y-6">
            <!-- CSV Import Box -->
            <div class="bg-white p-6 rounded-xl shadow-md">
                <h2 class="text-lg font-bold text-gray-800 mb-2">📁 Import CSV File</h2>
                <p class="text-xs text-gray-500 mb-4">Upload your exported CSV file containing columns: `NO.`, `NAME`, `# of Shares`.</p>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <input type="file" name="csv_file" accept=".csv" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>
                    <button type="submit" name="import_csv" class="w-full bg-emerald-600 text-white py-2 px-4 rounded-md hover:bg-emerald-700 transition font-semibold shadow">
                        Upload & Import CSV
                    </button>
                </form>
            </div>

            <!-- Add Single Partner Form -->
            <div class="bg-white p-6 rounded-xl shadow-md">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Add Single Partner</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Partner Name</label>
                        <input type="text" name="partner_name" placeholder="Pangalan ng Kasosyo" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700"># of Shares</label>
                        <input type="number" step="any" name="shares_count" placeholder="0" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <button type="submit" name="add_share" class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition font-semibold shadow">
                        Save Partner Share
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Cooperative Table -->
        <div class="<?= $is_viewer ? 'lg:col-span-4' : 'lg:col-span-3' ?> bg-white p-6 rounded-xl shadow-md">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Shareholders Directory & Dividend Computations</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">NO.</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">NAME</th>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase"># of Shares</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">AMOUNT</th>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">% SHARE</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">DIVIDEND</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">TOTAL MONEY</th>
                            <?php if ($is_admin): ?>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (!empty($shares)): ?>
                            <?php 
                            $counter = 1;
                            foreach ($shares as $s): 
                                $shares_cnt = floatval($s['shares_count'] ?? 0);
                                $amount_val = $shares_cnt * $amount_per_share;
                                
                                $share_percentage = $total_shares_count > 0 ? ($shares_cnt / $total_shares_count) * 100 : 0;
                                $dividend_val = ($share_percentage / 100) * $total_dividend_pool;
                                $total_money = $amount_val + $dividend_val;
                            ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-3 text-center text-gray-500"><?= $counter++ ?></td>
                                    <td class="px-3 py-3 font-semibold text-gray-900 whitespace-nowrap"><?= htmlspecialchars($s['partner_name']) ?></td>
                                    <td class="px-3 py-3 text-center text-gray-700"><?= $shares_cnt ?></td>
                                    <td class="px-3 py-3 text-right text-gray-800">₱<?= number_format($amount_val, 2) ?></td>
                                    <td class="px-3 py-3 text-center text-indigo-600 font-medium"><?= number_format($share_percentage, 2) ?>%</td>
                                    <td class="px-3 py-3 text-right text-green-700 font-bold">₱<?= number_format($dividend_val, 2) ?></td>
                                    <td class="px-3 py-3 text-right text-gray-900 font-bold bg-gray-50">₱<?= number_format($total_money, 2) ?></td>
                                    
                                    <?php if ($is_admin): ?>
                                        <td class="px-3 py-3 text-center whitespace-space space-x-2">
                                            <button onclick="openEditModal(<?= $s['id'] ?>, '<?= htmlspecialchars($s['partner_name'], ENT_QUOTES) ?>', <?= $shares_cnt ?>)" class="text-blue-600 hover:text-blue-800 font-semibold text-xs bg-blue-50 px-2 py-1 rounded">Edit</button>
                                            <a href="shares.php?delete_id=<?= $s['id'] ?>" onclick="return confirm('Are you sure you want to delete this partner?');" class="text-red-600 hover:text-red-800 font-semibold text-xs bg-red-50 px-2 py-1 rounded">Delete</a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= $is_admin ? 8 : 7 ?>" class="px-4 py-6 text-center text-gray-500">No share records found. Upload a CSV file or add partners manually.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal Overlay -->
<div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-xl shadow-lg w-full max-w-md">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Edit Partner Share</h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="edit_id" id="edit_id">
            <div>
                <label class="block text-sm font-medium text-gray-700">Partner Name</label>
                <input type="text" name="partner_name" id="edit_partner_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700"># of Shares</label>
                <input type="number" step="any" name="shares_count" id="edit_shares_count" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
            </div>
            <div class="flex justify-end space-x-2 mt-4">
                <button type="button" onclick="closeEditModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 font-semibold">Cancel</button>
                <button type="submit" name="edit_share" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 font-semibold">Update Share</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, name, shares) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_partner_name').value = name;
    document.getElementById('edit_shares_count').value = shares;
    document.getElementById('edit_Modal').classList.remove('hidden'); // matches popup ID wrapper
}
// Helper fix for modal ID reference
function openEditModal(id, name, shares) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_partner_name').value = name;
    document.getElementById('edit_shares_count').value = shares;
    document.getElementById('editModal').classList.remove('hidden');
}
function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>
