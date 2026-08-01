<?php
// Enable error reporting so we can see errors on screen while debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOBS Sari-Sari Store</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background-color: #f4f4f9; }
        h1 { color: #333; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .success { color: green; font-weight: bold; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🛒 KOBS Sari-Sari Store</h1>
        <p class="success">
            <?php 
                if (isset($pdo)) {
                    echo "Connected to Supabase Database Successfully!";
                }
            ?>
        </p>
    </div>
</body>
</html>