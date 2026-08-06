<?>
<?php
require_once 'db.php';
include 'header.php';
?>

<div class="container mx-auto px-4 py-12">
    <!-- Top Bar with Back Button -->
    <div class="flex justify-between items-center max-w-4xl mx-auto mb-6">
        <a href="index.php" class="inline-flex items-center gap-2 bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-semibold shadow-sm transition text-sm">
            &larr; Back to Dashboard
        </a>
    </div>

    <div class="text-center mb-10">
        <h1 class="text-3xl font-bold text-gray-800">🏪 Store Management</h1>
        <p class="text-gray-600 mt-2">Pumili ng opsyon sa ibaba upang pamahalaan ang mga produkto, stockouts, at credit list.</p>
    </div>

    <!-- Store Action Buttons Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
        <!-- Products Button -->
        <a href="products.php" class="bg-white p-8 rounded-xl shadow-md hover:shadow-xl border-t-4 border-indigo-600 transition transform hover:-translate-y-1 text-center group">
            <div class="text-4xl mb-4">📦</div>
            <h2 class="text-xl font-bold text-gray-800 group-hover:text-indigo-600 transition">Products</h2>
            <p class="text-sm text-gray-500 mt-2">Pamahalaan ang listahan at presyo ng mga paninda.</p>
        </a>

        <!-- Stockout Button -->
        <a href="stockout.php" class="bg-white p-8 rounded-xl shadow-md hover:shadow-xl border-t-4 border-amber-600 transition transform hover:-translate-y-1 text-center group">
            <div class="text-4xl mb-4">🛒</div>
            <h2 class="text-xl font-bold text-gray-800 group-hover:text-amber-600 transition">Stockout</h2>
            <p class="text-sm text-gray-500 mt-2">Itala ang mga nabili o lumabas na produkto mula sa tindahan.</p>
        </a>

        <!-- Credit List Button -->
        <a href="credit.php" class="bg-white p-8 rounded-xl shadow-md hover:shadow-xl border-t-4 border-teal-600 transition transform hover:-translate-y-1 text-center group">
            <div class="text-4xl mb-4">📝</div>
            <h2 class="text-xl font-bold text-gray-800 group-hover:text-teal-600 transition">Credit List</h2>
            <p class="text-sm text-gray-500 mt-2">Tingnan ang listahan ng mga utang at balanse ng mga customer.</p>
        </a>
    </div>
</div>

<?php 
// include 'footer.php'; 
?>
