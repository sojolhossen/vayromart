<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>অর্ডার সফল হয়েছে - Vayromart</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Font: Inter & Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4">

    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 transform transition-all duration-300 hover:shadow-2xl">
        <!-- Top Colored Bar / Design Accent -->
        <div class="h-2 bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500"></div>

        <div class="p-8 text-center">
            <!-- Animated Green Success Checkmark -->
            <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-emerald-50 text-emerald-500 mb-6 animate-bounce">
                <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>

            <!-- Heading -->
            <h2 class="text-2xl font-bold text-gray-800 mb-2 font-poppins">অর্ডার সফলভাবে সম্পন্ন হয়েছে!</h2>
            <p class="text-gray-500 text-sm mb-6">আপনার অর্ডারটি গ্রহণ করা হয়েছে। নিচে অর্ডারের বিবরণ দেওয়া হলো:</p>

            <!-- Order Details Card -->
            <div class="bg-gray-50 rounded-xl p-5 text-left border border-gray-100 space-y-4 mb-6">
                <!-- Order Number -->
                <div class="flex justify-between items-center pb-2 border-b border-gray-200">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">অর্ডার নাম্বার</span>
                    <span class="text-sm font-bold text-emerald-600 font-mono">{{ $order->order_number }}</span>
                </div>

                <!-- Product Name -->
                <div>
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">প্রোডাক্ট</span>
                    <span class="text-sm font-medium text-gray-800 block mt-0.5">{{ $product->name }}</span>
                </div>

                <!-- Total Amount -->
                <div class="flex justify-between items-center pt-2">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">মোট পরিশোধযোগ্য মূল্য</span>
                    <span class="text-base font-bold text-gray-800 font-mono">{{ $totalAmount }} BDT</span>
                </div>

                <!-- Payment Status -->
                <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">পেমেন্ট মেথড</span>
                    <span class="text-xs font-medium bg-orange-50 text-orange-600 px-2 py-0.5 rounded-full border border-orange-100">ক্যাশ অন ডেলিভারি (COD)</span>
                </div>

                <!-- Shipping Address -->
                <div class="pt-2 border-t border-gray-200">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">ডেলিভারি ঠিকানা</span>
                    <p class="text-xs text-gray-600 mt-1 leading-relaxed">{{ $order->shipping_address->address }}</p>
                    <p class="text-xs text-gray-600 mt-0.5 font-semibold font-mono">মোবাইল: {{ $order->shipping_address->mobile }}</p>
                </div>
            </div>

            <!-- Contact/Notice info -->
            <p class="text-xs text-gray-400 leading-relaxed mb-8">
                আমাদের কাস্টমার সাপোর্ট প্রতিনিধি খুব শীঘ্রই আপনার মোবাইলে কল করে অর্ডারটি নিশ্চিত করবেন। অনুগ্রহ করে আপনার ফোনটি সচল রাখুন।
            </p>

            <!-- Buttons -->
            <div class="flex flex-col space-y-3">
                <a href="{{ url('/') }}" class="w-full inline-flex justify-center items-center py-3 px-5 border border-transparent text-sm font-semibold rounded-xl text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-all duration-200 shadow-md">
                    ওয়েবসাইটে ফিরে যান
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 text-center">
            <span class="text-xs text-gray-400">&copy; {{ date('Y') }} Vayromart. All Rights Reserved.</span>
        </div>
    </div>

</body>
</html>
