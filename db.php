<?php
// db.php - Supabase PostgreSQL Connection Setup

// 1. Database Credentials
$host     = 'db.dgiiteboqvtfrqbelqyj.supabase.co'; // Your Supabase Host domain
$port     = '5432';                                 // Default PostgreSQL Port
$db       = 'postgres';                             // Default Supabase Database Name
$user     = 'postgres';                             // Default Supabase User
$password = 'Wed05282017!2026';       // Replace with your real Supabase password

// 2. Data Source Name (DSN) - requires sslmode=require for Supabase
$dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode=require";

// 3. PDO Options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on SQL errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return arrays indexed by column name
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements
];

// 4. Create Connection
try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (PDOException $e) {
    // If connection fails, display clear error message
    die("Database Connection Error: " . $e->getMessage());
}