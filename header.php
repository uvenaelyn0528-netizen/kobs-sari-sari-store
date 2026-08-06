<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOBS Store - Sari-Sari Inventory & POS</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js for dropdown interactivity -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">

    <!-- Navbar -->
    <nav class="bg-indigo-900 text-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <!-- Brand Logo / Name -->
            <div class="flex items-center space-x-2">
                <a href="dashboard.php" class="text-xl font-bold tracking-wide flex items-center gap-2">
                    🛍️ <span class="text-teal-400">KOBS</span> Store
                </a>
            </div>

            <!-- Navigation Links -->
            <div class="flex items-center space-x-6">
                <!-- Store Link (Redirects to dashboard.php) -->
                <a href="dashboard.php" class="font-medium hover:text-teal-300 transition">Store</a>

                <!-- Lending Link -->
                <a href="lending.php" class="font-medium hover:text-teal-300 transition">Lending</a>

                <!-- Gcash Link -->
                <a href="gcash.php" class="font-medium hover:text-teal-300 transition">Gcash</a>

                <!-- ATM Withdrawal Link -->
                <a href="atm.php" class="font-medium hover:text-teal-300 transition">ATM Withdrawal</a>
            </div>

            <!-- User / Logout Info -->
            <div class="flex items-center space-x-4">
                <?php if (isset($_SESSION['username'])): ?>
                    <span class="text-sm text-teal-300 hidden md:inline">Hello, <?= htmlspecialchars($_SESSION['username']) ?></span>
                <?php endif; ?>
                <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg text-sm font-semibold transition shadow-sm">
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content Wrapper -->
    <main class="flex-grow">
