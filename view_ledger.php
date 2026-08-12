<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db.php';

$customer_id = $_GET['customer_id'] ?? null;
if (!$customer_id) {
    header("Location: credit.php");
    exit();
}

// DEBUG TEST: Tingnan natin kung ano ang nakukuha sa database
try {
    $test_stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $test_stmt->execute([$customer_id]);
    $test_row = $test_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<pre style='background:#000; color:#0f0; padding:10px;'>";
    echo "DEBUGGING CUSTOMER ID: " . htmlspecialchars($customer_id) . "\n";
    print_r($test_row);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
exit(); // Pansamantalang hihinto muna dito para makita natin ang lumabas sa screen
