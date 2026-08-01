<?php
// db.php - Supabase IPv4 Connection Pooler

$host     = 'aws-0-ap-southeast-1.pooler.supabase.com'; // Paste host from Step 2
$port     = '6543';                                    // Pooler port (or 5432)
$db       = 'postgres';
$user     = 'postgres.dgiiteboqvtfrqbelqyj';            // Format: postgres.[PROJECT_ID]

// Reads password from Render Environment Variable, or uses local fallback
$password = getenv('SUPABASE_PASSWORD') ?: 'Wed05282017!2026';

$dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode=require";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}