<?php
// Force error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db.php';

// Handle deletion if delete_id is passed in URL
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
        $stmt->execute([$delete_id]);
        header("Location: stockout.php?msg=deleted");
        exit();
    } catch (Exception $e) {
        $error_msg = "Error deleting transaction: " . $e->getMessage();
    }
}

// Fetch totals for summary cards (Today's metrics)
$today = date('Y-m-d');

// 1. Cash Sales Today
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE transaction_type = 'Cash' AND DATE(transaction_date) = ?");
$stmt->execute([$today]);
$cash_sales_today = (float)$stmt->fetchColumn();

// 2. Payments Today
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE transaction_type = 'Payment' AND DATE(transaction_date) = ?");
$stmt->execute([$today]);
$payment_today = (float)$stmt->fetchColumn();

// 3. Cash on Hand Today
$total_cash_on_hand = $cash_sales_today + $payment_today;

// 4. Total Credit Accumulation
$stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE transaction_type = 'Credit'");
$credit_accumulation = (float)$stmt->fetchColumn();

// Fetch Transaction History Log
$stmt = $pdo->query("SELECT * FROM transactions ORDER BY id DESC LIMIT 50");
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stockout / Transactions Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .stat-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            background: #fff;
            padding: 20px;
            border-left: 5px solid #ccc;
        }
        .stat-card.stat-cash-hand { border-left-color: #f39c12; }
        .stat-card.stat-payment { border-left-color: #27ae60; }
        .stat-card.stat-sales { border-left-color: #2980b9; }
        .stat-card.stat-credit { border-left-color: #e67e22; }

        .stat-title {
            font-size: 11px;
            font-weight: 700;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-amount {
            font-size: 24px;
            font-weight: 800;
            margin-top: 5px;
        }
        .cart-table-container {
            max-height: 220px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }
        .badge-payment { background-color: #d4edda; color: #155724; }
        .badge-cash { background-color: #cce5ff; color: #004085; }
        .badge-credit { background-color: #fff3cd; color: #856404; }
    </style>
</head>
<body class="p-3 p-md-4">

<div class="container-fluid">

    <!-- TOP NAVIGATION BAR WITH BACK BUTTON -->
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <a href="index.php" class="btn btn-outline-secondary btn-sm fw-bold">
                &larr; Back to Dashboard
            </a>
            <h4 class="fw-bold text-dark mb-0">Stockout & Transactions Management</h4>
        </div>
        <div>
            <button type="button" onclick="history.back()" class="btn btn-light btn-sm text-muted">
                &larr; Go Back
            </button>
        </div>
    </div>
    
    <!-- TOP SUMMARY CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card stat-cash-hand">
                <div class="stat-title">TOTAL CASH ON HAND (TODAY)</div>
                <div class="stat-amount text-dark">₱<?php echo number_format($total_cash_on_hand, 2); ?></div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card stat-payment">
                <div class="stat-title">PAYMENT FOR TODAY</div>
                <div class="stat-amount text-success">₱<?php echo number_format($payment_today, 2); ?></div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card stat-sales">
                <div class="stat-title">CASH SALES (TODAY)</div>
                <div class="stat-amount text-primary">₱<?php echo number_format($cash_sales_today, 2); ?></div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card stat-credit">
                <div class="stat-title">TOTAL CREDIT ACCUMULATION (CREDIT + BALANCE)</div>
                <div class="stat-amount text-danger">₱<?php echo number_format($credit_accumulation, 2); ?></div>
            </div>
        </div>
    </div>

    <!-- MAIN TWO-COLUMN LAYOUT -->
    <div class="row g-4">
        
        <!-- LEFT COLUMN: RECORD TRANSACTION FORM -->
        <div class="col-12 col-lg-5 col-xl-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 bg-white">
                
                <!-- HEADER WITH BACK BUTTON -->
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <h5 class="fw-bold text-dark mb-0">Record Transaction</h5>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm fw-semibold">
                        &larr; Back
                    </a>
                </div>

                <?php if (isset($error_msg)): ?>
                    <div class="alert alert-danger py-2 small"><?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>

                <form id="transactionForm" action="process_transaction.php" method="POST">
                    <!-- Transaction Type -->
                    <div class="mb-2">
                        <label class="form-label fw-bold text-muted small mb-1">Transaction Type</label>
                        <select name="transaction_type" id="transaction_type" class="form-select form-select-sm" required>
                            <option value="Cash">Cash</option>
                            <option value="Credit">Credit</option>
                        </select>
                    </div>

                    <!-- Customer Name (Hidden unless Credit is selected) -->
                    <div class="mb-2" id="customer_name_group" style="display: none;">
                        <label class="form-label fw-bold text-muted small mb-1">Customer Name</label>
                        <input type="text" name="customer_name" class="form-control form-control-sm" placeholder="Enter customer name">
                    </div>

                    <!-- Date -->
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small mb-1">Date</label>
                        <input type="date" name="transaction_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <hr class="my-3">

                    <!-- Multi-Item Barcode Scanning Area -->
                    <div class="row g-2 mb-2">
                        <div class="col-8">
                            <label class="form-label fw-bold text-muted small mb-1">Product Barcode / Code</label>
                            <input type="text" id="barcode_input" class="form-control form-control-sm" placeholder="Scan barcode or type code" autofocus>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-bold text-muted small mb-1">Qty</label>
                            <input type="number" id="qty_input" class="form-control form-control-sm" value="1" min="1">
                        </div>
                        <div class="col-12 mt-2">
                            <button type="button" id="btn_add_item" class="btn btn-secondary btn-sm w-100 fw-bold">
                                + Add Item to List
                            </button>
                        </div>
                    </div>

                    <!-- Scanned Items Table / Cart -->
                    <div class="cart-table-container mb-3">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="bg-light sticky-top">
                                <tr class="small text-muted">
                                    <th>Item</th>
                                    <th style="width: 45px;" class="text-center">Qty</th>
                                    <th style="width: 65px;">Price</th>
                                    <th style="width: 75px;">Subtotal</th>
                                    <th style="width: 30px;"></th>
                                </tr>
                            </thead>
                            <tbody id="cart_tbody">
                                <tr>
                                    <td colspan="5" class="text-center text-muted small py-3">No items added yet. Scan a barcode above.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Total Display -->
                    <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded mb-3 border">
                        <span class="fw-bold text-uppercase text-muted small">Total Amount:</span>
                        <span class="h4 fw-bold text-primary mb-0" id="grand_total_display">₱0.00</span>
                    </div>

                    <!-- Hidden Cart Data Input -->
                    <input type="hidden" name="cart_data" id="cart_data">

                    <!-- Submit Process Button -->
                    <button type="submit" id="btn_process" class="btn btn-primary w-100 py-2 fw-bold" disabled style="background-color: #534bae; border: none;">
                        Process Transaction
                    </button>
                </form>
            </div>
        </div>

        <!-- RIGHT COLUMN: TRANSACTION HISTORY LOG -->
        <div class="col-12 col-lg-7 col-xl-8">
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 bg-white">
                
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-3 gap-2">
                    <h5 class="fw-bold text-dark mb-0">Transaction History Log</h5>
                    <input type="text" id="search_input" class="form-control form-control-sm style-search" style="max-width: 250px;" placeholder="Search transactions...">
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="transactions_table">
                        <thead class="table-light small">
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
                        <tbody class="small">
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No transactions recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['product_code'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['transaction_date'] ?? $row['created_at'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['qty'] ?? '-'); ?></td>
                                        <td>
                                            <?php 
                                                $type = $row['transaction_type'] ?? 'Cash';
                                                $badgeClass = ($type === 'Payment') ? 'badge-payment' : (($type === 'Credit') ? 'badge-credit' : 'badge-cash');
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($type); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['customer_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['description'] ?? 'Stockout / Sale'); ?></td>
                                        <td class="fw-bold">₱<?php echo number_format($row['amount'] ?? 0, 2); ?></td>
                                        <td>
                                            <a href="edit_transaction.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-light text-primary py-0 px-2 me-1">Edit</a>
                                            <a href="stockout.php?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-light text-danger py-0 px-2" onclick="return confirm('Delete this transaction log?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- JavaScript Logic -->
<script>
let cart = [];

// Toggle Customer Name field based on Transaction Type selection
document.getElementById('transaction_type').addEventListener('change', function() {
    const custGroup = document.getElementById('customer_name_group');
    custGroup.style.display = (this.value === 'Credit') ? 'block' : 'none';
});

// Barcode input Enter key press listener
document.getElementById('barcode_input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addItemToCart();
    }
});

document.getElementById('btn_add_item').addEventListener('click', addItemToCart);

async function addItemToCart() {
    const barcodeInput = document.getElementById('barcode_input');
    const qtyInput = document.getElementById('qty_input');
    const barcode = barcodeInput.value.trim();
    const qty = parseInt(qtyInput.value) || 1;

    if (!barcode) return;

    try {
        const response = await fetch(`get_product.php?code=${encodeURIComponent(barcode)}`);
        const data = await response.json();

        if (data.success) {
            const existingIndex = cart.findIndex(item => item.code === data.product.code);
            
            if (existingIndex > -1) {
                cart[existingIndex].qty += qty;
                cart[existingIndex].subtotal = cart[existingIndex].qty * cart[existingIndex].price;
            } else {
                cart.push({
                    code: data.product.code,
                    name: data.product.name,
                    price: parseFloat(data.product.price),
                    qty: qty,
                    subtotal: qty * parseFloat(data.product.price)
                });
            }

            renderCart();
            barcodeInput.value = '';
            qtyInput.value = 1;
            barcodeInput.focus();
        } else {
            alert(data.message || 'Product code not found!');
        }
    } catch (error) {
        console.error('Error fetching product:', error);
        alert('Server error while retrieving product code.');
    }
}

function renderCart() {
    const tbody = document.getElementById('cart_tbody');
    const processBtn = document.getElementById('btn_process');
    tbody.innerHTML = '';
    let grandTotal = 0;

    if (cart.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted small py-3">No items added yet. Scan a barcode above.</td></tr>`;
        processBtn.disabled = true;
        document.getElementById('grand_total_display').innerText = '₱0.00';
        document.getElementById('cart_data').value = '';
        return;
    }

    cart.forEach((item, index) => {
        grandTotal += item.subtotal;
        tbody.innerHTML += `
            <tr class="small">
                <td><strong>${item.name}</strong><br><span class="text-muted" style="font-size:10px;">${item.code}</span></td>
                <td class="text-center">${item.qty}</td>
                <td>₱${item.price.toFixed(2)}</td>
                <td class="fw-bold">₱${item.subtotal.toFixed(2)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger border-0 py-0 px-1" onclick="removeItem(${index})">&times;</button>
                </td>
            </tr>
        `;
    });

    document.getElementById('grand_total_display').innerText = `₱${grandTotal.toFixed(2)}`;
    document.getElementById('cart_data').value = JSON.stringify(cart);
    processBtn.disabled = false;
}

function removeItem(index) {
    cart.splice(index, 1);
    renderCart();
}

// Live Search for Transaction History Table
document.getElementById('search_input').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#transactions_table tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

</body>
</html>
