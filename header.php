<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOBS Store</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

    <!-- Navigation Bar with Logo / Design -->
    <nav class="bg-indigo-700 shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <!-- Brand Logo & Name -->
            <div class="flex items-center space-x-3">
                <div class="bg-white text-indigo-700 p-2 rounded-lg shadow-inner flex items-center justify-center">
                    <!-- Store / Shopping Bag Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
                <div>
                    <span class="text-white font-extrabold text-lg tracking-wider block leading-tight">KOBS Store</span>
                    <span class="text-indigo-200 text-xs font-medium">Sari-Sari Inventory & POS</span>
                </div>
            </div>

            <!-- Navigation Links -->
            <div class="flex space-x-1 sm:space-x-4 text-sm font-semibold">
                <a href="index.php" class="text-indigo-100 hover:bg-indigo-600 px-3 py-2 rounded-md transition">Dashboard</a>
                <a href="products.php" class="text-indigo-100 hover:bg-indigo-600 px-3 py-2 rounded-md transition">Products</a>
                <a href="credit.php" class="text-indigo-100 hover:bg-indigo-600 px-3 py-2 rounded-md transition">Credit List</a>
                <a href="stockout.php" class="text-white hover:text-gray-200 px-3 py-2 rounded-md font-medium">Stockout</a>
            </div>
        </div>
    </nav>
