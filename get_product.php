<?php
require_once 'db.php';

header('Content-Type: application/json');

$code = $_GET['code'] ?? '';

if (!empty($code)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE product_code = ?");
        $stmt->execute([$code]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            echo json_encode(['success' => true, 'product' => $product]);
            exit;
        }
    } catch (PDOException $e) {
        // Return empty on error
    }
}

echo json_encode(['success' => false]);
