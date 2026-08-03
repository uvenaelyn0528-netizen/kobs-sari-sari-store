<?php
// header.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOBS Sari-Sari Store</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <!-- Top Navigation Bar -->
    <nav class="bg-indigo-700 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl">🛒</span>
                    <span class="font-bold text-xl tracking-wide">KOBS Store</span>
                </div>
                <div class="flex space-x-4">
                    <a href="index.php" class="px-3 py-2 rounded-md text-sm font-medium hover:bg-indigo-600 transition"><i class="fa-solid fa-chart-line mr-1"></i> Dashboard</a>
                    <a href="products.php" class="px-3 py-2 rounded-md text-sm font-medium hover:bg-indigo-600 transition"><i class="fa-solid fa-box mr-1"></i> Products</a>
                    <a href="credit.php" class="px-3 py-2 rounded-md text-sm font-medium hover:bg-indigo-600 transition"><i class="fa-solid fa-tags mr-1"></i> Credit List</a>
                    <a href="stockout.php" class="px-3 py-2 rounded-md text-sm font-medium hover:bg-indigo-600 transition"><i class="fa-solid fa-receipt mr-1"></i> Stock Out</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
