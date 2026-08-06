<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOBS COOP - Welcome</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-indigo-900 via-indigo-800 to-teal-900 min-h-screen flex flex-col items-center justify-center text-white px-4">

    <!-- Container Card -->
    <div class="bg-white/10 backdrop-blur-md p-8 rounded-2xl shadow-2xl max-w-md w-full text-center border border-white/20">
        
        <!-- Logo / Picture Representation -->
        <div class="mb-6 flex justify-center">
            <div class="w-32 h-32 bg-teal-400 rounded-full flex items-center justify-center shadow-lg border-4 border-white/30">
                <!-- Maaari mong palitan ito ng <img src="logo.png" alt="KOBS COOP Logo" class="w-full h-full object-cover rounded-full"> kung mayroon kang imahe -->
                <span class="text-4xl font-black text-indigo-900 tracking-wider">KOBS</span>
            </div>
        </div>

        <h1 class="text-2xl font-bold mb-2 tracking-wide">KOBS COOP</h1>
        <p class="text-teal-200 text-sm mb-8">Pumili ng opsyon sa ibaba upang magpatuloy:</p>

        <!-- Navigation Buttons -->
        <div class="space-y-4">
            <a href="dashboard.php" class="block w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-3 px-4 rounded-xl shadow-lg transition transform hover:-translate-y-0.5 text-center">
                Store
            </a>
            
            <a href="lending.php" class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-xl shadow-lg transition transform hover:-translate-y-0.5 text-center">
                Lending
            </a>
            
            <a href="gcash.php" class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl shadow-lg transition transform hover:-translate-y-0.5 text-center">
                Gcash
            </a>
            
            <a href="atm.php" class="block w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-4 rounded-xl shadow-lg transition transform hover:-translate-y-0.5 text-center">
                ATM Withdrawal
            </a>
        </div>

    </div>

    <!-- Footer Note -->
    <footer class="mt-8 text-xs text-gray-300">
        &copy; <?= date('Y') ?> KOBS COOP. All rights reserved.
    </footer>

</body>
</html>
