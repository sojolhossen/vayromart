<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotLandingPage;
use App\Models\Product;
use App\Lib\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminLandingController extends Controller
{
    /**
     * Display landing page builder dashboard and generated list
     */
    public function index()
    {
        $pageTitle = 'AI Landing Page Generator';
        
        // Fetch all generated landing pages
        $landingPages = ChatbotLandingPage::with('product')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
            
        // Fetch published products for selection dropdown
        $products = Product::published()
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        return view('admin.landing.index', compact('pageTitle', 'landingPages', 'products'));
    }

    /**
     * Get all images of a product
     */
    public function getProductImages($id)
    {
        $product = Product::with('displayImage', 'galleryImages')->find($id);
        if (!$product) {
            return response()->json(['success' => false, 'error' => 'Product not found']);
        }

        $images = [];
        
        // Add main image
        if ($product->displayImage) {
            $images[] = [
                'url' => $product->mainImage(false),
                'path' => $product->displayImage->path . '/' . $product->displayImage->file_name
            ];
        }

        // Add gallery images
        if ($product->galleryImages) {
            foreach ($product->galleryImages as $media) {
                $images[] = [
                    'url' => $media->full_url,
                    'path' => $media->path . '/' . $media->file_name
                ];
            }
        }

        return response()->json([
            'success' => true,
            'images' => $images
        ]);
    }

    /**
     * Store/Update manual landing page
     */
    /**
     * Store/Update manual landing page
     */
    public function generate(Request $request)
    {
        $request->validate([
            'id' => 'nullable|integer|exists:chatbot_landing_pages,id',
            'product_id' => 'required|integer|exists:products,id',
            'title' => 'required|string|max:255',
            'template' => 'nullable|string|max:50',
            'free_delivery' => 'nullable|string|in:free,paid',
            'hotline_title' => 'nullable|string|max:255',
            'hotline_phone' => 'nullable|string|max:50',
            'video_title' => 'nullable|string|max:255',
            'video_url' => 'nullable|url',
            'why_us_title' => 'nullable|string|max:255',
            'why_us_description' => 'nullable|string',
            'product_description_title' => 'nullable|string|max:255',
            'descriptions' => 'nullable|array',
            'descriptions.*' => 'nullable|string',
            'headline' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'bullets' => 'nullable|string',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'custom_price' => 'nullable|numeric|min:0',
            'custom_regular_price' => 'nullable|numeric|min:0',
            'reviewer_name_1' => 'nullable|string',
            'reviewer_comment_1' => 'nullable|string',
            'reviewer_name_2' => 'nullable|string',
            'reviewer_comment_2' => 'nullable|string',
            'reviewer_name_3' => 'nullable|string',
            'reviewer_comment_3' => 'nullable|string',
            'existing_product_images' => 'nullable|array',
            'existing_product_images.*' => 'nullable|string',
            'manual_product_images' => 'nullable|array',
            'manual_product_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
        ]);

        $product = Product::published()->findOrFail($request->product_id);

        $imageUrl = $request->image_url;
        if ($request->hasFile('image_file')) {
            try {
                $imageName = fileUploader($request->file('image_file'), 'assets/images/landing');
                $imageUrl = 'assets/images/landing/' . $imageName;
            } catch (\Exception $e) {
                \Log::error("Image Upload Error: " . $e->getMessage());
            }
        }

        // Process review images
        $reviewImages = $request->existing_review_images ?? [];

        // Upload manual review images
        if ($request->hasFile('manual_review_images')) {
            foreach ($request->file('manual_review_images') as $file) {
                try {
                    $imgName = fileUploader($file, 'assets/images/landing');
                    $reviewImages[] = 'assets/images/landing/' . $imgName;
                } catch (\Exception $e) {
                    \Log::error("Manual Review Image Upload Error: " . $e->getMessage());
                }
            }
        }

        // Process multiple product images
        $productImages = $request->existing_product_images ?? [];

        // Upload manual product images
        if ($request->hasFile('manual_product_images')) {
            foreach ($request->file('manual_product_images') as $file) {
                try {
                    $imgName = fileUploader($file, 'assets/images/landing');
                    $productImages[] = 'assets/images/landing/' . $imgName;
                } catch (\Exception $e) {
                    \Log::error("Manual Product Image Upload Error: " . $e->getMessage());
                }
            }
        }

        // Merge uploaded image URL, review images, and other inputs into settings array
        $settings = $request->all();
        $settings['image_url'] = $imageUrl;
        $settings['review_images'] = $reviewImages;
        $settings['product_images'] = $productImages;
        
        // Remove file objects from settings array
        unset($settings['image_file']);
        unset($settings['manual_product_images']);
        unset($settings['existing_product_images']);
        for ($i = 1; $i <= 6; $i++) {
            unset($settings['review_image_' . $i]);
        }

        try {
            if ($request->id) {
                $landingPage = ChatbotLandingPage::findOrFail($request->id);
                $html = $this->compileManualTemplate($settings, $product);
                $landingPage->update([
                    'product_id' => $product->id,
                    'title' => $request->title,
                    'content' => $html,
                    'design_settings' => $settings
                ]);
                $notify[] = ['success', 'Landing Page updated successfully!'];
            } else {
                // Generate unique slug
                $slug = Str::slug($product->name);
                $baseSlug = $slug;
                $count = 1;
                while (ChatbotLandingPage::where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . $count;
                    $count++;
                }

                $landingPage = ChatbotLandingPage::create([
                    'product_id' => $product->id,
                    'slug' => $slug,
                    'title' => $request->title,
                    'content' => '', // Compiling below with actual database ID
                    'design_settings' => $settings
                ]);

                $settings['id'] = $landingPage->id;
                $html = $this->compileManualTemplate($settings, $product);
                $landingPage->update([
                    'content' => $html,
                    'design_settings' => $settings
                ]);

                $notify[] = ['success', 'Landing Page created successfully!'];
            }

            return back()->withNotify($notify);

        } catch (\Exception $e) {
            \Log::error("Landing Page Save Error: " . $e->getMessage());
            $notify[] = ['error', 'Failed to save landing page: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Compile manual HTML template
     */
    private function compileManualTemplate($data, $product)
    {
        $template = $data['template'] ?? 'template_1';

        if ($template === 'template_2') {
            return $this->compileTemplateTwo($data, $product);
        }

        return $this->compileTemplateOne($data, $product);
    }

    /**
     * Compile Template 1 (Original Style)
     */
    private function compileTemplateOne($data, $product)
    {
        $title = e($data['title']);
        $headline = e($data['headline'] ?? '');
        $subtitle = e($data['subtitle'] ?? '');
        $videoUrl = $data['video_url'] ?? '';
        $description = $data['description'] ?? '';

        $resolveUrl = function($path) {
            if (empty($path)) return '';
            if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
                return $path;
            }
            return asset($path);
        };

        $imageUrl = $resolveUrl(($data['image_url'] ?? '') ?: $product->mainImage(false));

        $countdownTimerHtml = '
        <!-- Countdown Timer & Stock Urgency -->
        <div class="bg-rose-50 border border-rose-100 rounded-2xl p-4 mb-8 max-w-md shadow-sm">
            <div class="flex items-center gap-2 mb-2 text-rose-700 font-bold text-sm">
                <i class="fas fa-clock animate-pulse"></i>
                <span>আজকের অফার শেষ হতে আর মাত্র সময় আছে:</span>
            </div>
            <div class="grid grid-cols-4 gap-2 text-center">
                <div class="bg-white rounded-lg p-2 shadow-xs border border-rose-100/50">
                    <span class="block text-2xl font-black text-rose-600" id="timer-hours">03</span>
                    <span class="text-[10px] text-gray-500 font-medium block">ঘণ্টা</span>
                </div>
                <div class="bg-white rounded-lg p-2 shadow-xs border border-rose-100/50">
                    <span class="block text-2xl font-black text-rose-600" id="timer-minutes">45</span>
                    <span class="text-[10px] text-gray-500 font-medium block">মিনিট</span>
                </div>
                <div class="bg-white rounded-lg p-2 shadow-xs border border-rose-100/50">
                    <span class="block text-2xl font-black text-rose-600" id="timer-seconds">12</span>
                    <span class="text-[10px] text-gray-500 font-medium block">সেকেন্ড</span>
                </div>
                <div class="bg-white rounded-lg p-2 shadow-xs border border-rose-100/50 flex flex-col justify-center items-center">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-600 animate-ping mb-1"></span>
                    <span class="text-[10px] text-red-600 font-bold">লাইভ স্টক</span>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-rose-700 font-semibold border-t border-rose-100 pt-2">
                <span><i class="fas fa-fire"></i> স্টক সীমিত! মাত্র <span id="live-stock-val">১২</span> টি বাকি আছে!</span>
                <span class="animate-pulse">১৫ জন এই মুহূর্তে দেখছেন</span>
            </div>
        </div>';

        $faqHtml = '
        <!-- FAQ Section -->
        <section class="max-w-4xl mx-auto px-4 py-16 border-t border-gray-100">
            <h2 class="text-3xl font-extrabold text-gray-900 text-center mb-10">প্রায়শই জিজ্ঞাসিত প্রশ্নাবলী (FAQ)</h2>
            <div class="space-y-4">
                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm transition-all duration-300 hover:shadow-md">
                    <button onclick="toggleFaq(this)" class="flex justify-between items-center w-full text-left font-bold text-lg text-gray-800 hover:text-primary outline-none">
                        <span>১. অর্ডার করার কত দিনের মধ্যে ডেলিভারি পাবো?</span>
                        <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
                    </button>
                    <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300 ease-in-out mt-3 text-gray-600 text-base leading-relaxed">
                        ঢাকা সিটির ভেতরে আমরা সর্বোচ্চ ২৪ থেকে ৪৮ ঘণ্টার মধ্যে হোম ডেলিভারি দিয়ে থাকি। আর ঢাকা সিটির বাইরে ৩ থেকে ৫ কার্যদিবসের মধ্যে পেয়ে যাবেন।
                    </div>
                </div>
                
                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm transition-all duration-300 hover:shadow-md">
                    <button onclick="toggleFaq(this)" class="flex justify-between items-center w-full text-left font-bold text-lg text-gray-800 hover:text-primary outline-none">
                        <span>২. আমি কি অর্ডার করার সময় ডেলিভারি চার্জ আগে পরিশোধ করব?</span>
                        <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
                    </button>
                    <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300 ease-in-out mt-3 text-gray-600 text-base leading-relaxed">
                        না, আমাদের কোনো অগ্রিম পেমেন্ট করতে হবে না। আপনি ডেলিভারি ম্যানের সামনে প্রোডাক্ট দেখে ও চেক করে ক্যাশ অন ডেলিভারিতে সম্পূর্ণ টাকা পরিশোধ করতে পারবেন।
                    </div>
                </div>

                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm transition-all duration-300 hover:shadow-md">
                    <button onclick="toggleFaq(this)" class="flex justify-between items-center w-full text-left font-bold text-lg text-gray-800 hover:text-primary outline-none">
                        <span>৩. প্রোডাক্টে কোনো ত্রুটি থাকলে কি ফেরত দেওয়া যাবে?</span>
                        <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
                    </button>
                    <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300 ease-in-out mt-3 text-gray-600 text-base leading-relaxed">
                        অবশ্যই! প্রোডাক্টে কোনো ধরনের সমস্যা থাকলে বা আপনার পছন্দ না হলে ডেলিভারি ম্যান থাকা অবস্থায় আপনি কোনো চার্জ ছাড়া রিটার্ন করতে পারবেন অথবা ৭ দিনের রিফান্ড/এক্সচেঞ্জ সুবিধা পাবেন।
                    </div>
                </div>
            </div>
        </section>';

        $purchasePopupHtml = '
        <!-- Real-time Purchase Popup Toast -->
        <div id="purchase-popup" class="fixed bottom-6 left-6 z-50 bg-white/95 backdrop-blur-md rounded-2xl p-4 shadow-2xl border border-gray-100 flex items-center gap-4 transition-all duration-500 transform translate-y-32 opacity-0 max-w-sm hidden sm:flex">
            <div class="w-12 h-12 bg-primary-light text-primary rounded-full flex items-center justify-center text-xl flex-shrink-0">
                <i class="fas fa-bag-shopping"></i>
            </div>
            <div class="pr-2">
                <p class="text-[11px] text-gray-500 font-semibold"><span id="popup-name">আজিম</span>, <span id="popup-city">ঢাকা</span> থেকে</p>
                <p class="text-xs font-black text-gray-800 mt-0.5"><span class="text-primary">' . $title . '</span></p>
                <p class="text-[10px] text-emerald-600 font-bold mt-0.5"><i class="fas fa-check-circle"></i> সফলভাবে অর্ডার করেছেন (<span id="popup-time">১ মিনিট আগে</span>)</p>
            </div>
            <button onclick="document.getElementById(\'purchase-popup\').classList.add(\'translate-y-32\', \'opacity-0\')" class="text-gray-400 hover:text-gray-600 self-start ml-auto"><i class="fas fa-times text-xs"></i></button>
        </div>';

        $faqAndUrgencyJs = '
        <script>
            // FAQ Accordion toggle
            function toggleFaq(button) {
                var answer = button.nextElementSibling;
                var icon = button.querySelector(\'i\');
                if (answer.style.maxHeight && answer.style.maxHeight !== \'0px\') {
                    answer.style.maxHeight = \'0px\';
                    icon.classList.remove(\'rotate-180\');
                } else {
                    answer.style.maxHeight = answer.scrollHeight + \'px\';
                    icon.classList.add(\'rotate-180\');
                }
            }

            // Countdown Timer
            (function() {
                var hoursSpan = document.getElementById("timer-hours");
                var minutesSpan = document.getElementById("timer-minutes");
                var secondsSpan = document.getElementById("timer-seconds");
                var stockSpan = document.getElementById("live-stock-val");
                
                if (!hoursSpan) return;
                
                var target = new Date();
                target.setHours(23, 59, 59, 999);
                
                function updateTimer() {
                    var current = new Date();
                    var diff = target - current;
                    if (diff <= 0) {
                        target = new Date();
                        target.setHours(23, 59, 59, 999);
                        return;
                    }
                    
                    var h = Math.floor(diff / (1000 * 60 * 60));
                    var m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    var s = Math.floor((diff % (1000 * 60)) / 1000);
                    
                    hoursSpan.innerText = h < 10 ? "0" + h : h;
                    minutesSpan.innerText = m < 10 ? "0" + m : m;
                    secondsSpan.innerText = s < 10 ? "0" + s : s;
                }
                
                setInterval(updateTimer, 1000);
                updateTimer();
                
                // Live stock reduction simulation
                var stock = 12;
                function reduceStock() {
                    if (stock > 3) {
                        stock -= Math.floor(Math.random() * 2);
                        if (stockSpan) {
                            stockSpan.innerText = stock;
                        }
                    }
                }
                setInterval(reduceStock, 25000);
            })();

            // Purchase Notification Popups
            (function() {
                var names = ["আরিফ", "সাকিব", "নাহিদ", "ফারহানা", "আজিম", "জাহিদুল", "সুমাইয়া", "ফয়সাল", "রায়হান", "তাসনিম"];
                var cities = ["ঢাকা", "চট্টগ্রাম", "সিলেট", "রাজশাহী", "খুলনা", "রংপুর", "বরিশাল", "কুমিল্লা", "গাজীপুর", "ময়মনসিংহ"];
                var times = ["১ মিনিট আগে", "৩ মিনিট আগে", "৪ মিনিট আগে", "৫ মিনিট আগে", "৭ মিনিট আগে"];
                
                var popup = document.getElementById("purchase-popup");
                if (!popup) return;
                
                function showPopup() {
                    var name = names[Math.floor(Math.random() * names.length)];
                    var city = cities[Math.floor(Math.random() * cities.length)];
                    var time = times[Math.floor(Math.random() * times.length)];
                    
                    document.getElementById("popup-name").innerText = name;
                    document.getElementById("popup-city").innerText = city;
                    document.getElementById("popup-time").innerText = time;
                    
                    popup.classList.remove("hidden", "translate-y-32", "opacity-0");
                    popup.classList.add("translate-y-0", "opacity-100");
                    
                    setTimeout(function() {
                        popup.classList.remove("translate-y-0", "opacity-100");
                        popup.classList.add("translate-y-32", "opacity-0");
                    }, 4500);
                }
                
                setTimeout(showPopup, 3000);
                setInterval(showPopup, 15000);
            })();
        </script>';
        
        $baseColor = '#' . (gs('base_color') ?: '4634ff');

        $productImages = $data['product_images'] ?? [];
        if (empty($productImages)) {
            $productImages = [$imageUrl];
        }

        $sliderHtml = '';
        $sliderJs = '';

        if (count($productImages) > 1) {
            $sliderHtml .= '<div class="relative w-full rounded-2xl shadow-2xl border border-gray-100 overflow-hidden aspect-square bg-white group mb-6">';
            $sliderHtml .= '<div class="flex transition-transform duration-500 ease-out h-full" id="product-slider">';
            foreach ($productImages as $imgPath) {
                $imgUrl = $resolveUrl($imgPath);
                $sliderHtml .= '<div class="w-full h-full flex-shrink-0"><img src="' . $imgUrl . '" class="w-full h-full object-cover"></div>';
            }
            $sliderHtml .= '</div>';
            
            $sliderHtml .= '
            <button onclick="prevSlide()" class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 hover:bg-white text-gray-800 flex items-center justify-center shadow-md transition-all opacity-0 group-hover:opacity-100 z-10">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button onclick="nextSlide()" class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 hover:bg-white text-gray-800 flex items-center justify-center shadow-md transition-all opacity-0 group-hover:opacity-100 z-10">
                <i class="fas fa-chevron-right"></i>
            </button>';
            
            $sliderHtml .= '<div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2 z-10" id="slider-dots">';
            foreach ($productImages as $idx => $imgUrl) {
                $activeClass = $idx === 0 ? 'bg-primary' : 'bg-gray-300';
                $sliderHtml .= '<button onclick="showSlide(' . $idx . ')" class="w-3 h-3 rounded-full ' . $activeClass . ' transition-all" style="' . ($idx === 0 ? 'background-color: ' . $baseColor . ';' : 'background-color: #d1d5db;') . '"></button>';
            }
            $sliderHtml .= '</div>';
            $sliderHtml .= '</div>';

            $sliderJs = '
            <script>
                var currentSlide = 0;
                var slides = document.querySelectorAll("#product-slider > div");
                var dots = document.querySelectorAll("#slider-dots > button");
                
                function showSlide(index) {
                    if (slides.length === 0) return;
                    if (index >= slides.length) currentSlide = 0;
                    else if (index < 0) currentSlide = slides.length - 1;
                    else currentSlide = index;
                    
                    document.getElementById("product-slider").style.transform = "translateX(-" + (currentSlide * 100) + "%)";
                    dots.forEach(function(dot, idx) {
                        if (idx === currentSlide) {
                            dot.style.backgroundColor = "' . $baseColor . '";
                        } else {
                            dot.style.backgroundColor = "#d1d5db";
                        }
                    });
                }
                
                function nextSlide() {
                    showSlide(currentSlide + 1);
                }
                
                function prevSlide() {
                    showSlide(currentSlide - 1);
                }
                
                if (slides.length > 1) {
                    setInterval(nextSlide, 5000);
                }
            </script>';
        } else {
            $singleImg = reset($productImages);
            $sliderHtml = '<img src="' . $singleImg . '" class="w-full rounded-2xl shadow-2xl border border-gray-100 hover:scale-[1.02] transition-all duration-300 object-cover aspect-square mb-6">';
        }

        $videoEmbedHtml = '';
        if ($videoUrl) {
            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts)\/|.*[?&]v=)|youtu\.be\/)([^\"&?\/ ]{11})/i', $videoUrl, $match)) {
                $embedCode = $match[1];
                $videoEmbedHtml = '
                <div class="relative group">
                    <div class="mb-3 text-center">
                        <span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-800 text-xs font-bold px-3.5 py-1.5 rounded-full border border-amber-200 shadow-xs animate-pulse">
                            <i class="fas fa-volume-high text-amber-600"></i>
                            <span>সাউন্ড শুনতে পেজটি স্ক্রল করুন অথবা স্ক্রিনে স্পর্শ করুন</span>
                        </span>
                    </div>
                    <iframe class="w-full aspect-video rounded-2xl shadow-lg" src="https://www.youtube.com/embed/' . $embedCode . '?autoplay=1&mute=1&enablejsapi=1&playsinline=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                </div>';
            }
        }

        $bullets = array_filter(array_map('trim', explode("\n", $data['bullets'] ?? '')));
        $bulletsHtml = '';
        foreach ($bullets as $bullet) {
            $bulletsHtml .= '<li class="flex items-start gap-3 text-gray-700 text-lg mb-3">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mt-1">
                    <i class="fas fa-check text-sm"></i>
                </span>
                <span>' . e($bullet) . '</span>
            </li>';
        }

        // Handle custom prices
        $price = !empty($data['custom_price']) ? floatval($data['custom_price']) : ($product->sale_price ?: $product->regular_price);
        $regularPrice = !empty($data['custom_regular_price']) ? floatval($data['custom_regular_price']) : ($product->regular_price > $price ? $product->regular_price : round($price * 1.35));
        $discountAmount = $regularPrice - $price;
        
        $checkoutUrl = route('landing.checkout');
        $csrfToken = csrf_token();

        $isFreeDelivery = ($data['free_delivery'] ?? 'paid') === 'free';
        $insideCharge = $isFreeDelivery ? 0 : 80;
        $outsideCharge = $isFreeDelivery ? 0 : 130;

        // Variants HTML for Template 1
        $variantsHtml = '';
        if ($product->product_type == 2 && $product->attributes->count() > 0) {
            $variantsHtml .= '<div class="space-y-4 mb-6 border-b border-gray-100 pb-6 text-left text-gray-900">';
            $variantsHtml .= '<h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fas fa-tags text-primary"></i>
                <span>পছন্দের ভ্যারিয়েন্ট সিলেক্ট করুন:</span>
            </h4>';
            foreach ($product->attributes as $attribute) {
                $attributeValues = $product->attributeValues->where('attribute_id', $attribute->id);
                $variantsHtml .= '<div class="form-group mb-4">
                    <label class="block text-xs font-bold text-gray-600 mb-2">' . e($attribute->name) . ' <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <select name="variant[' . $attribute->id . ']" required class="w-full pl-4 pr-10 py-3.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all duration-200 text-gray-800 bg-white font-medium appearance-none shadow-sm text-sm">';
                $index = 0;
                foreach ($attributeValues as $attributeValue) {
                    $attrValName = !empty($attributeValue->name) ? $attributeValue->name : $attributeValue->value;
                    $selected = ($index === 0) ? ' selected="selected"' : '';
                    $variantsHtml .= '<option value="' . $attributeValue->id . '"' . $selected . '>' . e($attrValName) . '</option>';
                    $index++;
                }
                $variantsHtml .= '</select>
                        <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 pointer-events-none"><i class="fas fa-chevron-down text-xs"></i></span>
                    </div>
                </div>';
            }
            $variantsHtml .= '</div>';
        }

        // Reviews HTML Auto Slider
        $reviewItemsHtml = '';
        $reviewers = [
            ['name' => ($data['reviewer_name_1'] ?? '') ?: 'Sojol Hossen', 'comment' => ($data['reviewer_comment_1'] ?? '') ?: 'অসাধারণ প্রোডাক্ট! ঠিক যেমনটি চেয়েছিলাম তেমনই পেয়েছি। ডেলিভারি সার্ভিসও খুব ফাস্ট ছিল। ধন্যবাদ বায়রোমার্ট!'],
            ['name' => ($data['reviewer_name_2'] ?? '') ?: 'Farhana Yasmin', 'comment' => ($data['reviewer_comment_2'] ?? '') ?: 'পণ্যটির কোয়ালিটি খুবই ভালো। ২ দিনেই ডেলিভারি পেয়েছি। আপনারা চাইলে চোখ বন্ধ করে নিতে পারেন।'],
            ['name' => ($data['reviewer_name_3'] ?? '') ?: 'Md. Arif', 'comment' => ($data['reviewer_comment_3'] ?? '') ?: 'প্রোডাক্ট হাতে পেয়ে চেক করে পেমেন্ট করেছি। ক্যাশ অন ডেলিভারি সুবিধা থাকায় অনেক সুবিধা হয়েছে। ১০/১০ দিব।']
        ];
        
        foreach ($reviewers as $idx => $rev) {
            $initial = mb_substr($rev['name'], 0, 1, 'utf-8');
            $reviewItemsHtml .= '
            <div class="review-slide-item flex-none w-full md:w-[calc(33.333%-16px)]">
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all duration-300 h-full flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-12 h-12 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-lg shadow-inner">' . $initial . '</div>
                            <div>
                                <h6 class="font-bold text-gray-800 text-base">' . e($rev['name']) . '</h6>
                                <div class="text-amber-500 text-xs flex gap-0.5 mt-1">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                </div>
                            </div>
                        </div>
                        <p class="text-gray-600 text-sm leading-relaxed">"' . e($rev['comment']) . '"</p>
                    </div>
                </div>
            </div>';
        }

        $reviewsHtml = '
        <div class="review-carousel-wrapper relative max-w-6xl mx-auto px-2">
            <button type="button" aria-label="Previous Review" class="review-carousel-prev absolute -left-2 md:-left-5 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full bg-white/95 shadow-xl border border-gray-200 text-gray-800 flex items-center justify-center hover:bg-emerald-600 hover:text-white hover:border-emerald-600 hover:scale-110 active:scale-95 transition-all duration-300 cursor-pointer">
                <i class="fas fa-chevron-left text-lg"></i>
            </button>
            <button type="button" aria-label="Next Review" class="review-carousel-next absolute -right-2 md:-right-5 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full bg-white/95 shadow-xl border border-gray-200 text-gray-800 flex items-center justify-center hover:bg-emerald-600 hover:text-white hover:border-emerald-600 hover:scale-110 active:scale-95 transition-all duration-300 cursor-pointer">
                <i class="fas fa-chevron-right text-lg"></i>
            </button>
            <div class="review-carousel-container overflow-hidden rounded-2xl p-2 cursor-grab active:cursor-grabbing">
                <div class="review-carousel-track flex gap-6 transition-transform duration-500 ease-out">
                    ' . $reviewItemsHtml . '
                </div>
            </div>
            <div class="review-carousel-dots flex justify-center items-center gap-2 mt-6"></div>
        </div>';

        $html = '<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $title . '</title>
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        html {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        body {
            font-family: \'Hind Siliguri\', \'Inter\', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            overflow-x: hidden;
        }
        #mobile-sticky-bar, header, .pulsing-btn {
            will-change: transform;
            transform: translateZ(0);
            -webkit-transform: translateZ(0);
        }
        .reveal-init, .reveal-active {
            opacity: 1 !important;
            transform: none !important;
            visibility: visible !important;
        }
        .hover-lift {
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .hover-lift:hover {
            transform: translateY(-6px) scale(1.01);
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.08);
        }
        @keyframes pulse-glow {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.5); }
            50% { transform: scale(1.015); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .pulsing-btn {
            animation: pulse-glow 2.5s infinite ease-in-out;
        }
        .bg-primary {
            background-color: var(--primary-color) !important;
        }
        .text-primary {
            color: var(--primary-color) !important;
        }
        .border-primary {
            border-color: var(--primary-color) !important;
        }
        .bg-primary-light {
            background-color: rgba(' . hexdec(substr($baseColor, 1, 2)) . ', ' . hexdec(substr($baseColor, 3, 2)) . ', ' . hexdec(substr($baseColor, 5, 2)) . ', 0.1) !important;
        }
        .border-primary-light {
            border-color: rgba(' . hexdec(substr($baseColor, 1, 2)) . ', ' . hexdec(substr($baseColor, 3, 2)) . ', ' . hexdec(substr($baseColor, 5, 2)) . ', 0.2) !important;
        }
    </style>
    ' . loadExtension('facebook-pixel') . '
    <script>
        if (typeof fbq !== "undefined") {
            fbq("track", "ViewContent", {
                content_name: "' . e($title) . '",
                content_ids: ["' . $product->id . '"],
                content_type: "product",
                value: ' . $price . ',
                currency: "BDT"
            });
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-900 scroll-smooth">

    <!-- Sticky Header -->
    <header class="sticky top-0 bg-white/95 backdrop-blur-md border-b border-gray-100 z-50 transition-all shadow-sm">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="javascript:void(0)" onclick="window.location.reload()" class="text-2xl font-black text-emerald-600 tracking-tight flex items-center gap-2">
                <i class="fas fa-shopping-bag"></i>
                <span>Vayromart</span>
            </a>
            <a href="#checkout-form" class="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold px-6 py-2.5 rounded-full transition-all duration-300 shadow-md hover:shadow-lg flex items-center gap-2">
                <i class="fas fa-cart-shopping"></i>
                <span>এখনই কিনুন</span>
            </a>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="max-w-6xl mx-auto px-4 py-12 lg:py-20 grid lg:grid-cols-12 gap-12 items-center">
        <div class="lg:col-span-7 order-2 lg:order-1">
            <span class="inline-block bg-primary-light text-primary font-bold px-4 py-1.5 rounded-full text-sm mb-6 border border-primary-light">
                ' . ($isFreeDelivery ? '<i class="fas fa-truck-fast text-emerald-600 mr-1 animate-bounce"></i> <span class="text-emerald-700 font-black">১০০% ফ্রি হোম ডেলিভারি অফার!</span>' : 'ধামাকা ক্যাশ অন ডেলিভারি অফার!') . '
            </span>
            <h1 class="text-4xl lg:text-5xl font-extrabold text-gray-900 leading-tight mb-4">
                ' . $headline . '
            </h1>
            <p class="text-xl text-gray-600 mb-8 font-medium">
                ' . $subtitle . '
            </p>

            <ul class="mb-8">
                ' . $bulletsHtml . '
            </ul>

            <!-- Pricing block -->
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm max-w-md mb-8 flex items-center justify-between">
                <div>
                    <span class="text-gray-400 line-through text-lg font-medium block">পূর্বে মূল্য: ' . $regularPrice . ' BDT</span>
                    <span class="text-emerald-600 text-3xl font-black block mt-1">আজকের অফার: ' . $price . ' BDT</span>
                    ' . ($isFreeDelivery ? '<div class="mt-2 inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 text-xs font-black px-3 py-1 rounded-full border border-emerald-200 animate-pulse"><i class="fas fa-truck-fast"></i> <span>সারা বাংলাদেশে ফ্রি ডেলিভারি!</span></div>' : '') . '
                </div>
                <div class="bg-emerald-50 text-emerald-700 font-bold px-4 py-2.5 rounded-xl border border-emerald-100 text-center animate-bounce text-sm">
                    সঞ্চয়: ' . $discountAmount . ' BDT!
                </div>
            </div>

            ' . $countdownTimerHtml . '

            <div class="flex flex-col sm:flex-row gap-4 max-w-lg">
                <a href="#checkout-form" class="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 hover:scale-[1.02] active:scale-[0.98] text-white text-xl font-bold px-8 py-4 rounded-full transition-all duration-300 shadow-lg hover:shadow-xl hover:shadow-emerald-500/20 text-center flex items-center justify-center gap-3 pulsing-btn">
                    <i class="fas fa-hand-pointer"></i>
                    <span>অর্ডার করতে ফর্মটি পূরণ করুন</span>
                </a>
            </div>
        </div>

        <div class="lg:col-span-5 order-1 lg:order-2 flex flex-col justify-center">
            ' . $sliderHtml . '
            
            <div class="grid grid-cols-3 gap-3 mt-6 text-center">
                <div class="bg-white p-4 rounded-xl border ' . ($isFreeDelivery ? 'border-emerald-200 bg-emerald-50/40 shadow-emerald-500/10' : 'border-gray-100') . ' shadow-sm">
                    <i class="fas fa-truck-fast ' . ($isFreeDelivery ? 'text-emerald-600 animate-bounce' : 'text-primary') . ' text-2xl mb-2"></i>
                    <p class="text-xs font-bold ' . ($isFreeDelivery ? 'text-emerald-700' : 'text-gray-700') . '">' . ($isFreeDelivery ? '১০০% ফ্রি ডেলিভারি' : 'ফাস্ট হোম ডেলিভারি') . '</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                    <i class="fas fa-hand-holding-dollar text-primary text-2xl mb-2"></i>
                    <p class="text-xs font-bold text-gray-700">ক্যাশ অন ডেলিভারি</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                    <i class="fas fa-shield-halved text-primary text-2xl mb-2"></i>
                    <p class="text-xs font-bold text-gray-700">১০০% অরিজিনাল পণ্য</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Description & Video Embed -->
    <section class="bg-white border-y border-gray-100 py-16">
        <div class="max-w-6xl mx-auto px-6">
            <h2 class="text-3xl font-extrabold text-gray-900 text-center mb-8">পণ্যটির বিস্তারিত বিবরণ</h2>
            
            ' . ($videoEmbedHtml ? '<div class="mb-12 max-w-3xl mx-auto">' . $videoEmbedHtml . '</div>' : '') . '

            <div class="prose max-w-none text-gray-800 text-xl font-medium leading-relaxed">
                ' . nl2br($description) . '
            </div>
        </div>
    </section>

    <!-- Social Proof / Reviews -->
    <section class="max-w-6xl mx-auto px-4 py-16">
        <h2 class="text-3xl font-extrabold text-gray-900 text-center mb-12">গ্রাহকদের মূল্যবান মতামত (Reviews)</h2>
        <div class="grid md:grid-cols-3 gap-8">
            ' . $reviewsHtml . '
        </div>
    </section>

    ' . $faqHtml . '

    <!-- COD Checkout Form -->
    <section class="bg-slate-900 text-white py-20 border-t border-slate-800" id="checkout-form">
        <div class="max-w-2xl mx-auto px-4">
            <div class="bg-white text-gray-900 p-8 lg:p-12 rounded-3xl shadow-2xl border border-gray-100">
                <div class="text-center mb-8">
                    <span class="bg-emerald-50 text-emerald-700 font-bold px-4 py-1.5 rounded-full text-xs border border-emerald-100 tracking-wide uppercase">
                        ' . ($isFreeDelivery ? '🚚 ১০০% ফ্রি হোম ডেলিভারি (কোনো ডেলিভারি চার্জ নেই)' : 'ক্যাশ অন ডেলিভারি (হাতে পেয়ে মূল্য পরিশোধ)') . '
                    </span>
                    <h3 class="text-2xl lg:text-3xl font-black mt-4 mb-2">অর্ডার করতে ফর্মটি পূরণ করুন</h3>
                    <p class="text-gray-500 font-medium">ডেলিভারি ম্যানের কাছ থেকে প্রোডাক্ট বুঝে পেয়ে টাকা পরিশোধ করুন।</p>
                </div>

                <form action="' . $checkoutUrl . '" method="POST" class="space-y-5">
                    <input type="hidden" name="_token" value="' . $csrfToken . '">
                    <input type="hidden" name="product_id" value="' . $product->id . '">
                    <input type="hidden" name="landing_page_id" value="' . ($data['id'] ?? '') . '">

                    ' . $variantsHtml . '

                    <!-- Product & Quantity Card -->
                    <div class="bg-slate-50 border border-slate-200/80 p-4 rounded-2xl flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center text-lg font-bold">
                                <i class="fas fa-box-open"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">পণ্যের পরিমাণ</p>
                                <p class="text-sm font-bold text-gray-800">৳ <span id="unit_price_val">' . $price . '</span> / প্রতি পিস</p>
                            </div>
                        </div>
                        <div class="inline-flex items-center rounded-xl border border-gray-300 bg-white p-1 shadow-sm">
                            <button type="button" id="qty_minus" onclick="changeQuantity(-1)" class="w-9 h-9 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-extrabold text-base flex items-center justify-center transition-all cursor-pointer">-</button>
                            <input type="number" name="quantity" id="quantity_input" value="1" min="1" max="99" readonly class="w-12 text-center font-black text-base text-gray-900 outline-none border-none bg-transparent">
                            <button type="button" id="qty_plus" onclick="changeQuantity(1)" class="w-9 h-9 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-extrabold text-base flex items-center justify-center transition-all cursor-pointer shadow-sm">+</button>
                        </div>
                    </div>

                    <!-- Customer Name -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">আপনার নাম <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-emerald-600"><i class="fas fa-user"></i></span>
                            <input type="text" name="name" required autocomplete="name" placeholder="আপনার সম্পূর্ণ নাম লিখুন" class="w-full pl-11 pr-4 py-3.5 text-base sm:text-sm rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all duration-200 font-medium">
                        </div>
                    </div>

                    <!-- Customer Phone -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">মোবাইল নম্বর <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-emerald-600"><i class="fas fa-phone"></i></span>
                            <input type="tel" name="mobile" required inputmode="tel" autocomplete="tel" placeholder="১১ ডিজিটের মোবাইল নম্বর (যেমন: 017... বা +88017...)" class="w-full pl-11 pr-4 py-3.5 text-base sm:text-sm rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all duration-200 font-medium">
                        </div>
                    </div>

                    <!-- Delivery Location Selector -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">ডেলিভারি এলাকা <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-emerald-600 pointer-events-none"><i class="fas fa-truck"></i></span>
                            <select name="shipping_location" id="shipping_location" required class="w-full pl-11 pr-10 py-3.5 text-base sm:text-sm rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all duration-200 font-medium appearance-none bg-white">
                                <option value="inside" data-charge="' . $insideCharge . '">ঢাকা সিটির ভেতরে (' . ($isFreeDelivery ? 'ফ্রি ডেলিভারি 🚚' : $insideCharge . ' BDT') . ')</option>
                                <option value="outside" data-charge="' . $outsideCharge . '">ঢাকা সিটির বাইরে (' . ($isFreeDelivery ? 'ফ্রি ডেলিভারি 🚚' : $outsideCharge . ' BDT') . ')</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 pointer-events-none"><i class="fas fa-chevron-down text-xs"></i></span>
                        </div>
                    </div>

                    <!-- Delivery Address -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">ডেলিভারি ঠিকানা <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute top-3.5 left-0 flex items-start pl-4 text-emerald-600"><i class="fas fa-location-dot mt-0.5"></i></span>
                            <textarea name="address" required placeholder="আপনার জেলা, থানা ও পূর্ণাঙ্গ ঠিকানা লিখুন" class="w-full pl-11 pr-4 py-3.5 text-base sm:text-sm rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all duration-200 font-medium" rows="2"></textarea>
                        </div>
                    </div>

                    <!-- Pricing Summary Box -->
                    <div class="bg-emerald-50/70 border border-emerald-100 p-4 rounded-2xl space-y-2 text-sm">
                        <div class="flex justify-between text-gray-600 font-medium">
                            <span>ডেলিভারি চার্জ:</span>
                            <span id="delivery_charge_val" class="font-bold text-gray-800">' . ($isFreeDelivery ? 'ফ্রি' : $insideCharge . ' BDT') . '</span>
                        </div>
                        <div class="border-t border-emerald-200/60 pt-2 flex justify-between items-center">
                            <span class="font-bold text-gray-800">সর্বমোট বিল:</span>
                            <span class="font-black text-emerald-600 text-xl" id="total_bill_val">' . ($price + $insideCharge) . ' BDT</span>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="w-full bg-gradient-to-r from-emerald-500 via-teal-500 to-emerald-600 hover:from-emerald-600 hover:to-teal-700 hover:scale-[1.01] active:scale-[0.99] text-white font-extrabold text-lg sm:text-xl py-4 rounded-xl shadow-lg hover:shadow-xl hover:shadow-emerald-500/20 transition-all duration-300 flex items-center justify-center gap-2 pulsing-btn cursor-pointer">
                        <i class="fas fa-circle-check"></i>
                        <span>অর্ডার নিশ্চিত করুন</span>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-950 text-gray-500 py-12 text-center border-t border-slate-900">
        <div class="max-w-6xl mx-auto px-4">
            <h4 class="text-white text-xl font-bold tracking-tight mb-4 flex items-center justify-center gap-2">
                <i class="fas fa-shopping-bag text-indigo-500"></i>
                <span>Vayromart</span>
            </h4>
            <p class="text-sm max-w-md mx-auto mb-6 text-gray-400">ডেলিভারি ম্যানের সামনে প্রোডাক্ট দেখে ও চেক করে পরিশোধ করার শতভাগ নিশ্চয়তা।</p>
            <div class="flex justify-center gap-6 text-xl mb-6">
                <a href="#" class="hover:text-white transition-colors"><i class="fab fa-facebook"></i></a>
                <a href="#" class="hover:text-white transition-colors"><i class="fab fa-instagram"></i></a>
                <a href="#" class="hover:text-white transition-colors"><i class="fab fa-whatsapp"></i></a>
            </div>
            <p class="text-xs">&copy; ' . date('Y') . ' Vayromart. All rights reserved.</p>
        </div>
    </footer>

    <!-- Mobile Sticky Floating Order Bar -->
    <div id="mobile-sticky-bar" class="fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-gray-200 p-2.5 shadow-[0_-4px_20px_rgba(0,0,0,0.15)] flex items-center justify-between gap-3 sm:hidden">
        <div class="pl-2">
            <span class="text-[10px] ' . ($isFreeDelivery ? 'text-emerald-600 font-black' : 'text-gray-500 font-semibold') . ' block leading-tight">' . ($isFreeDelivery ? '🚚 ফ্রি ডেলিভারি' : 'বিশেষ অফার') . '</span>
            <span class="text-base font-black text-emerald-600 leading-tight block">' . $price . ' BDT</span>
        </div>
        <a href="#checkout-form" class="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold py-2.5 px-4 rounded-xl shadow-md text-sm flex items-center justify-center gap-2 pulsing-btn flex-1 active:scale-95 transition-all">
            <i class="fas fa-cart-shopping"></i>
            <span>অর্ডার করুন</span>
        </a>
    </div>

    ' . $purchasePopupHtml . '

    <script>
        document.getElementById("shipping_location").addEventListener("change", function() {
            var charge = parseInt(this.options[this.selectedIndex].getAttribute("data-charge"));
            var basePrice = ' . $price . ';
            document.getElementById("delivery_charge_val").innerText = charge === 0 ? "ফ্রি" : charge + " BDT";
            document.getElementById("total_bill_val").innerText = (basePrice + charge) + " BDT";
        });
    </script>

    ' . $sliderJs . '
    ' . $faqAndUrgencyJs . '

    <!-- Review Image Lightbox Modal -->
    <div id="review-image-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/90 backdrop-blur-md p-4 transition-all duration-300 opacity-0 pointer-events-none">
        <button type="button" id="review-modal-close" class="absolute top-5 right-5 text-white/80 hover:text-white bg-white/10 hover:bg-white/20 w-12 h-12 rounded-full flex items-center justify-center text-2xl transition-all z-10 focus:outline-none cursor-pointer">
            <i class="fas fa-xmark"></i>
        </button>
        <div class="relative max-w-5xl max-h-[90vh] w-full flex items-center justify-center p-2">
            <img id="review-modal-img" src="" alt="Review Image Full View" class="max-h-[85vh] max-w-full object-contain rounded-2xl shadow-2xl border border-white/20 transform transition-transform duration-300 scale-95">
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var modal = document.getElementById("review-image-modal");
            var modalImg = document.getElementById("review-modal-img");
            var closeBtn = document.getElementById("review-modal-close");

            function openReviewModal(src) {
                if (!modal || !modalImg) return;
                modalImg.src = src;
                modal.classList.remove("hidden", "pointer-events-none");
                setTimeout(function() {
                    modal.classList.remove("opacity-0");
                    modalImg.classList.remove("scale-95");
                    modalImg.classList.add("scale-100");
                }, 10);
                document.body.style.overflow = "hidden";
            }

            function closeReviewModal() {
                if (!modal || !modalImg) return;
                modal.classList.add("opacity-0");
                if (modalImg) modalImg.classList.remove("scale-100");
                if (modalImg) modalImg.classList.add("scale-95");
                setTimeout(function() {
                    modal.classList.add("hidden", "pointer-events-none");
                    modalImg.src = "";
                    document.body.style.overflow = "";
                }, 300);
            }

            document.querySelectorAll(".image-popup, .cursor-zoom-in, [alt=\'Customer Review\']").forEach(function(img) {
                img.classList.add("cursor-pointer");
                img.addEventListener("click", function(e) {
                    e.preventDefault();
                    openReviewModal(this.src || this.getAttribute("data-src") || this.href);
                });
            });

            if (closeBtn) closeBtn.addEventListener("click", closeReviewModal);
            if (modal) {
                modal.addEventListener("click", function(e) {
                    if (e.target === modal || e.target.parentElement === modal) {
                        closeReviewModal();
                    }
                });
            }
            document.addEventListener("keydown", function(e) {
                if (e.key === "Escape") closeReviewModal();
            });

            // All elements guaranteed 100% visible
        });
    </script>
    ' . loadExtension('tawk-chat') . '
    <script type="text/javascript">
        var Tawk_API = Tawk_API || {};
        Tawk_API.onLoad = function(){
            if (typeof Tawk_API.hideWidget === "function") {
                Tawk_API.hideWidget();
            }
        };
    </script>
    <style>
        #tawk-default-container, iframe[title*="chat widget"], .tawk-min-container {
            display: none !important;
            visibility: hidden !important;
        }
    </style>
</body>
</html>';

        return $html;
    }

    /**
     * Compile Template 2 (Mohasagor Style)
     */
    private function compileTemplateTwo($data, $product)
    {
        $title = e($data['title']);
        $headline = e($data['headline'] ?? $product->name);
        $subtitle = e($data['subtitle'] ?? '');
        $videoUrl = $data['video_url'] ?? '';
        $whyUsTitle = e($data['why_us_title'] ?? 'পণ্যটির বিস্তারিত বিবরণ');
        $whyUsDescription = $data['why_us_description'] ?? '';
        $productDescTitle = e($data['product_description_title'] ?? 'পণ্যটি কেন আপনার জন্য প্রয়োজনীয়?');

        $resolveUrl = function($path) {
            if (empty($path)) return '';
            if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
                return $path;
            }
            return asset($path);
        };

        $imageUrl = $resolveUrl(($data['image_url'] ?? '') ?: $product->mainImage(false));

        $countdownTimerHtml = '
        <!-- Countdown Timer & Stock Urgency -->
        <div class="bg-rose-50 border border-rose-100 rounded-2xl p-4 mb-8 max-w-md shadow-sm">
            <div class="flex items-center gap-2 mb-2 text-rose-700 font-bold text-sm">
                <i class="fas fa-clock animate-pulse"></i>
                <span>আজকের অফার শেষ হতে আর মাত্র সময় আছে:</span>
            </div>
            <div class="grid grid-cols-4 gap-2 text-center">
                <div class="bg-white rounded-lg p-2 shadow-xs border border-rose-100/50">
                    <span class="block text-2xl font-black text-rose-600" id="timer-hours">03</span>
                    <span class="text-[10px] text-gray-500 font-medium block">ঘণ্টা</span>
                </div>
                <div class="bg-white rounded-lg p-2 shadow-xs border border-rose-100/50">
                    <span class="block text-2xl font-black text-rose-600" id="timer-minutes">45</span>
                    <span class="text-[10px] text-gray-500 font-medium block">মিনিট</span>
                </div>
                <div class="bg-white rounded-lg p-2 shadow-xs border border-rose-100/50">
                    <span class="block text-2xl font-black text-rose-600" id="timer-seconds">12</span>
                    <span class="text-[10px] text-gray-500 font-medium block">সেকেন্ড</span>
                </div>
                <div class="bg-white rounded-lg p-2 shadow-xs border border-rose-100/50 flex flex-col justify-center items-center">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-600 animate-ping mb-1"></span>
                    <span class="text-[10px] text-red-600 font-bold">লাইভ স্টক</span>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-rose-700 font-semibold border-t border-rose-100 pt-2">
                <span><i class="fas fa-fire"></i> স্টক সীমিত! মাত্র <span id="live-stock-val">১২</span> টি বাকি আছে!</span>
                <span class="animate-pulse">১৫ জন এই মুহূর্তে দেখছেন</span>
            </div>
        </div>';

        $faqHtml = '
        <!-- FAQ Section -->
        <section class="max-w-4xl mx-auto px-4 py-16 border-t border-gray-100">
            <h2 class="text-3xl font-extrabold text-gray-900 text-center mb-10">প্রায়শই জিজ্ঞাসিত প্রশ্নাবলী (FAQ)</h2>
            <div class="space-y-4">
                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm transition-all duration-300 hover:shadow-md">
                    <button onclick="toggleFaq(this)" class="flex justify-between items-center w-full text-left font-bold text-lg text-gray-800 hover:text-primary outline-none">
                        <span>১. অর্ডার করার কত দিনের মধ্যে ডেলিভারি পাবো?</span>
                        <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
                    </button>
                    <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300 ease-in-out mt-3 text-gray-600 text-base leading-relaxed">
                        ঢাকা সিটির ভেতরে আমরা সর্বোচ্চ ২৪ থেকে ৪৮ ঘণ্টার মধ্যে হোম ডেলিভারি দিয়ে থাকি। আর ঢাকা সিটির বাইরে ৩ থেকে ৫ কার্যদিবসের মধ্যে পেয়ে যাবেন।
                    </div>
                </div>
                
                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm transition-all duration-300 hover:shadow-md">
                    <button onclick="toggleFaq(this)" class="flex justify-between items-center w-full text-left font-bold text-lg text-gray-800 hover:text-primary outline-none">
                        <span>২. আমি কি অর্ডার করার সময় ডেলিভারি চার্জ আগে পরিশোধ করব?</span>
                        <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
                    </button>
                    <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300 ease-in-out mt-3 text-gray-600 text-base leading-relaxed">
                        না, আমাদের কোনো অগ্রিম পেমেন্ট করতে হবে না। আপনি ডেলিভারি ম্যানের সামনে প্রোডাক্ট দেখে ও চেক করে ক্যাশ অন ডেলিভারিতে সম্পূর্ণ টাকা পরিশোধ করতে পারবেন।
                    </div>
                </div>

                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm transition-all duration-300 hover:shadow-md">
                    <button onclick="toggleFaq(this)" class="flex justify-between items-center w-full text-left font-bold text-lg text-gray-800 hover:text-primary outline-none">
                        <span>৩. প্রোডাক্টে কোনো ত্রুটি থাকলে কি ফেরত দেওয়া যাবে?</span>
                        <i class="fas fa-chevron-down text-sm transition-transform duration-300"></i>
                    </button>
                    <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300 ease-in-out mt-3 text-gray-600 text-base leading-relaxed">
                        অবশ্যই! প্রোডাক্টে কোনো ধরনের সমস্যা থাকলে বা আপনার পছন্দ না হলে ডেলিভারি ম্যান থাকা অবস্থায় আপনি কোনো চার্জ ছাড়া রিটার্ন করতে পারবেন অথবা ৭ দিনের রিফান্ড/এক্সচেঞ্জ সুবিধা পাবেন।
                    </div>
                </div>
            </div>
        </section>';

        $purchasePopupHtml = '
        <!-- Real-time Purchase Popup Toast -->
        <div id="purchase-popup" class="fixed bottom-6 left-6 z-50 bg-white/95 backdrop-blur-md rounded-2xl p-4 shadow-2xl border border-gray-100 flex items-center gap-4 transition-all duration-500 transform translate-y-32 opacity-0 max-w-sm hidden sm:flex">
            <div class="w-12 h-12 bg-primary-light text-primary rounded-full flex items-center justify-center text-xl flex-shrink-0">
                <i class="fas fa-bag-shopping"></i>
            </div>
            <div class="pr-2">
                <p class="text-[11px] text-gray-500 font-semibold"><span id="popup-name">আজিম</span>, <span id="popup-city">ঢাকা</span> থেকে</p>
                <p class="text-xs font-black text-gray-800 mt-0.5"><span class="text-primary">' . $title . '</span></p>
                <p class="text-[10px] text-emerald-600 font-bold mt-0.5"><i class="fas fa-check-circle"></i> সফলভাবে অর্ডার করেছেন (<span id="popup-time">১ মিনিট আগে</span>)</p>
            </div>
            <button onclick="document.getElementById(\'purchase-popup\').classList.add(\'translate-y-32\', \'opacity-0\')" class="text-gray-400 hover:text-gray-600 self-start ml-auto"><i class="fas fa-times text-xs"></i></button>
        </div>';

        $faqAndUrgencyJs = '
        <script>
            // FAQ Accordion toggle
            function toggleFaq(button) {
                var answer = button.nextElementSibling;
                var icon = button.querySelector(\'i\');
                if (answer.style.maxHeight && answer.style.maxHeight !== \'0px\') {
                    answer.style.maxHeight = \'0px\';
                    icon.classList.remove(\'rotate-180\');
                } else {
                    answer.style.maxHeight = answer.scrollHeight + \'px\';
                    icon.classList.add(\'rotate-180\');
                }
            }

            // Countdown Timer
            (function() {
                var hoursSpan = document.getElementById("timer-hours");
                var minutesSpan = document.getElementById("timer-minutes");
                var secondsSpan = document.getElementById("timer-seconds");
                var stockSpan = document.getElementById("live-stock-val");
                
                if (!hoursSpan) return;
                
                var target = new Date();
                target.setHours(23, 59, 59, 999);
                
                function updateTimer() {
                    var current = new Date();
                    var diff = target - current;
                    if (diff <= 0) {
                        target = new Date();
                        target.setHours(23, 59, 59, 999);
                        return;
                    }
                    
                    var h = Math.floor(diff / (1000 * 60 * 60));
                    var m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    var s = Math.floor((diff % (1000 * 60)) / 1000);
                    
                    hoursSpan.innerText = h < 10 ? "0" + h : h;
                    minutesSpan.innerText = m < 10 ? "0" + m : m;
                    secondsSpan.innerText = s < 10 ? "0" + s : s;
                }
                
                setInterval(updateTimer, 1000);
                updateTimer();
                
                // Live stock reduction simulation
                var stock = 12;
                function reduceStock() {
                    if (stock > 3) {
                        stock -= Math.floor(Math.random() * 2);
                        if (stockSpan) {
                            stockSpan.innerText = stock;
                        }
                    }
                }
                setInterval(reduceStock, 25000);
            })();

            // Purchase Notification Popups
            (function() {
                var names = ["আরিফ", "সাকিব", "নাহিদ", "ফারহানা", "আজিম", "জাহিদুল", "সুমাইয়া", "ফয়সাল", "রায়হান", "তাসনিম"];
                var cities = ["ঢাকা", "চট্টগ্রাম", "সিলেট", "রাজশাহী", "খুলনা", "রংপুর", "বরিশাল", "কুমিল্লা", "গাজীপুর", "ময়মনসিংহ"];
                var times = ["১ মিনিট আগে", "৩ মিনিট আগে", "৪ মিনিট আগে", "৫ মিনিট আগে", "৭ মিনিট আগে"];
                
                var popup = document.getElementById("purchase-popup");
                if (!popup) return;
                
                function showPopup() {
                    var name = names[Math.floor(Math.random() * names.length)];
                    var city = cities[Math.floor(Math.random() * cities.length)];
                    var time = times[Math.floor(Math.random() * times.length)];
                    
                    document.getElementById("popup-name").innerText = name;
                    document.getElementById("popup-city").innerText = city;
                    document.getElementById("popup-time").innerText = time;
                    
                    popup.classList.remove("hidden", "translate-y-32", "opacity-0");
                    popup.classList.add("translate-y-0", "opacity-100");
                    
                    setTimeout(function() {
                        popup.classList.remove("translate-y-0", "opacity-100");
                        popup.classList.add("translate-y-32", "opacity-0");
                    }, 4500);
                }
                
                setTimeout(showPopup, 3000);
                setInterval(showPopup, 15000);
            })();
        </script>';

        $hotlineTitle = e($data['hotline_title'] ?? 'প্রয়োজনে কল করুন');
        $hotlinePhone = e($data['hotline_phone'] ?? '');
        
        $baseColor = '#' . (gs('base_color') ?: '4634ff');

        $productImages = $data['product_images'] ?? [];
        if (empty($productImages)) {
            $productImages = [$imageUrl];
        }

        $sliderHtml = '';
        $sliderJs = '';

        if (count($productImages) > 1) {
            $sliderHtml .= '<div class="relative w-full rounded-2xl shadow-2xl border border-gray-100 overflow-hidden aspect-square bg-white group mb-6">';
            $sliderHtml .= '<div class="flex transition-transform duration-500 ease-out h-full" id="product-slider">';
            foreach ($productImages as $imgPath) {
                $imgUrl = $resolveUrl($imgPath);
                $sliderHtml .= '<div class="w-full h-full flex-shrink-0"><img src="' . $imgUrl . '" class="w-full h-full object-cover"></div>';
            }
            $sliderHtml .= '</div>';
            
            $sliderHtml .= '
            <button onclick="prevSlide()" class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 hover:bg-white text-gray-800 flex items-center justify-center shadow-md transition-all opacity-0 group-hover:opacity-100 z-10">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button onclick="nextSlide()" class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 hover:bg-white text-gray-800 flex items-center justify-center shadow-md transition-all opacity-0 group-hover:opacity-100 z-10">
                <i class="fas fa-chevron-right"></i>
            </button>';
            
            $sliderHtml .= '<div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2 z-10" id="slider-dots">';
            foreach ($productImages as $idx => $imgUrl) {
                $activeClass = $idx === 0 ? 'bg-primary' : 'bg-gray-300';
                $sliderHtml .= '<button onclick="showSlide(' . $idx . ')" class="w-3 h-3 rounded-full ' . $activeClass . ' transition-all" style="' . ($idx === 0 ? 'background-color: ' . $baseColor . ';' : 'background-color: #d1d5db;') . '"></button>';
            }
            $sliderHtml .= '</div>';
            $sliderHtml .= '</div>';

            $sliderJs = '
            <script>
                var currentSlide = 0;
                var slides = document.querySelectorAll("#product-slider > div");
                var dots = document.querySelectorAll("#slider-dots > button");
                
                function showSlide(index) {
                    if (slides.length === 0) return;
                    if (index >= slides.length) currentSlide = 0;
                    else if (index < 0) currentSlide = slides.length - 1;
                    else currentSlide = index;
                    
                    document.getElementById("product-slider").style.transform = "translateX(-" + (currentSlide * 100) + "%)";
                    dots.forEach(function(dot, idx) {
                        if (idx === currentSlide) {
                            dot.style.backgroundColor = "' . $baseColor . '";
                        } else {
                            dot.style.backgroundColor = "#d1d5db";
                        }
                    });
                }
                
                function nextSlide() {
                    showSlide(currentSlide + 1);
                }
                
                function prevSlide() {
                    showSlide(currentSlide - 1);
                }
                
                if (slides.length > 1) {
                    setInterval(nextSlide, 5000);
                }
            </script>';
        } else {
            $singleImg = reset($productImages);
            $sliderHtml = '<img src="' . $singleImg . '" class="w-full rounded-2xl shadow-2xl border border-gray-100 hover:scale-[1.02] transition-all duration-300 object-cover aspect-square mb-6">';
        }

        $videoEmbedHtml = '';
        if ($videoUrl) {
            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts)\/|.*[?&]v=)|youtu\.be\/)([^\"&?\/ ]{11})/i', $videoUrl, $match)) {
                $embedCode = $match[1];
                $videoEmbedHtml = '
                <div class="relative group">
                    <div class="mb-3 text-center">
                        <span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-800 text-xs font-bold px-3.5 py-1.5 rounded-full border border-amber-200 shadow-xs animate-pulse">
                            <i class="fas fa-volume-high text-amber-600"></i>
                            <span>সাউন্ড শুনতে পেজটি স্ক্রল করুন অথবা স্ক্রিনে স্পর্শ করুন</span>
                        </span>
                    </div>
                    <iframe class="w-full aspect-video rounded-2xl shadow-lg" src="https://www.youtube.com/embed/' . $embedCode . '?autoplay=1&mute=1&enablejsapi=1&playsinline=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                </div>';
            }
        }

        $bullets = array_filter(array_map('trim', explode("\n", $data['bullets'] ?? '')));
        $bulletsHtml = '';
        foreach ($bullets as $bullet) {
            $bulletsHtml .= '<li class="flex items-center gap-3 text-slate-700 text-lg mb-4 hover:translate-x-1 transition-transform duration-200">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-primary-light text-primary flex items-center justify-center">
                    <i class="fas fa-check text-xs"></i>
                </span>
                <span class="font-semibold">' . e($bullet) . '</span>
            </li>';
        }

        // Custom descriptions list as Roadmap Timeline (Clean points without background box)
        $descriptionsHtml = '';
        if (!empty($data['descriptions'])) {
            $descriptionsHtml .= '<div class="relative pl-6 sm:pl-8 space-y-5 before:absolute before:left-3 sm:before:left-4 before:top-3 before:bottom-3 before:w-1 before:bg-gradient-to-b before:from-[#f2532c] before:via-[#ff7352] before:to-[#de3812] before:rounded-full my-6">';
            foreach ($data['descriptions'] as $idx => $desc) {
                if (trim($desc)) {
                    $stepNum = str_pad($idx + 1, 2, '0', STR_PAD_LEFT);
                    $descriptionsHtml .= '
                    <div class="relative group py-1">
                        <div class="absolute -left-[calc(1.5rem+8px)] sm:-left-[calc(2rem+8px)] top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-gradient-to-r from-[#f2532c] to-[#de3812] text-white font-black text-xs flex items-center justify-center shadow-md shadow-[#f2532c]/30 group-hover:scale-110 transition-transform duration-300 border-2 border-white z-10">
                            ' . $stepNum . '
                        </div>
                        <div class="flex items-center gap-3.5 pl-2">
                            <span class="text-[#f2532c] text-lg flex-shrink-0"><i class="fas fa-check-circle"></i></span>
                            <p class="text-slate-800 font-bold text-base sm:text-lg leading-relaxed">' . e($desc) . '</p>
                        </div>
                    </div>';
                }
            }
            $descriptionsHtml .= '</div>';
        }

        // Handle custom prices
        $price = !empty($data['custom_price']) ? floatval($data['custom_price']) : ($product->sale_price ?: $product->regular_price);
        $regularPrice = !empty($data['custom_regular_price']) ? floatval($data['custom_regular_price']) : ($product->regular_price > $price ? $product->regular_price : round($price * 1.35));
        $discountAmount = $regularPrice - $price;
        
        $checkoutUrl = route('landing.checkout');
        $csrfToken = csrf_token();

        $isFreeDelivery = ($data['free_delivery'] ?? 'paid') === 'free';
        $insideCharge = $isFreeDelivery ? 0 : 80;
        $outsideCharge = $isFreeDelivery ? 0 : 130;

        // Variants HTML for Template 2
        $variantsHtml = '';
        if ($product->product_type == 2 && $product->attributes->count() > 0) {
            $variantsHtml .= '<div class="space-y-4 mb-6 border-b border-slate-100 pb-6 text-left">';
            $variantsHtml .= '<h4 class="text-sm font-bold text-slate-800 mb-3 flex items-center gap-2">
                <i class="fas fa-tags text-primary"></i>
                <span>পছন্দের ভ্যারিয়েন্ট সিলেক্ট করুন:</span>
            </h4>';
            foreach ($product->attributes as $attribute) {
                $attributeValues = $product->attributeValues->where('attribute_id', $attribute->id);
                $variantsHtml .= '<div class="form-group mb-4">
                    <label class="block text-xs font-bold text-slate-600 mb-2">' . e($attribute->name) . ' <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <select name="variant[' . $attribute->id . ']" required class="w-full pl-4 pr-10 py-3.5 rounded-xl border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary-light outline-none transition-all duration-200 text-slate-800 bg-slate-50/50 hover:bg-white font-medium appearance-none shadow-sm text-sm">';
                $index = 0;
                foreach ($attributeValues as $attributeValue) {
                    $attrValName = !empty($attributeValue->name) ? $attributeValue->name : $attributeValue->value;
                    $selected = ($index === 0) ? ' selected="selected"' : '';
                    $variantsHtml .= '<option value="' . $attributeValue->id . '"' . $selected . '>' . e($attrValName) . '</option>';
                    $index++;
                }
                $variantsHtml .= '</select>
                        <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 pointer-events-none"><i class="fas fa-chevron-down text-xs"></i></span>
                    </div>
                </div>';
            }
            $variantsHtml .= '</div>';
        }

        // Review images / Text Reviews Auto Slider
        $reviewsHtml = '';
        $reviewItemsHtml = '';

        if (!empty($data['review_images'])) {
            foreach ($data['review_images'] as $imgPath) {
                if ($imgPath) {
                    $imgUrl = $resolveUrl($imgPath);
                    $reviewItemsHtml .= '
                    <div class="review-slide-item flex-none w-full md:w-[calc(33.333%-16px)]">
                        <div class="overflow-hidden rounded-2xl border border-gray-100 shadow-sm hover:shadow-lg hover:scale-[1.02] transition-all duration-300 bg-white p-2 h-full">
                            <img src="' . $imgUrl . '" alt="Customer Review" class="w-full h-64 object-cover rounded-xl image-popup cursor-zoom-in">
                        </div>
                    </div>';
                }
            }
        } else {
            // Fallback text reviews
            $reviewers = [
                ['name' => 'Sojol Hossen', 'comment' => 'অসাধারণ প্রোডাক্ট! ঠিক যেমনটি চেয়েছিলাম তেমনই পেয়েছি। ডেলিভারি সার্ভিসও খুব ফাস্ট ছিল। ধন্যবাদ বায়রোমার্ট!'],
                ['name' => 'Farhana Yasmin', 'comment' => 'পণ্যটির কোয়ালিটি খুবই ভালো। ২ দিনেই ডেলিভারি পেয়েছি। আপনারা চাইলে চোখ বন্ধ করে নিতে পারেন।'],
                ['name' => 'Md. Arif', 'comment' => 'প্রোডাক্ট হাতে পেয়ে চেক করে পেমেন্ট করেছি। ক্যাশ অন ডেলিভারি সুবিধা থাকায় অনেক সুবিধা হয়েছে। ১০/১০ দিব।']
            ];
            foreach ($reviewers as $rev) {
                $initial = mb_substr($rev['name'], 0, 1, 'utf-8');
                $reviewItemsHtml .= '
                <div class="review-slide-item flex-none w-full md:w-[calc(33.333%-16px)]">
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all duration-300 h-full flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-4 mb-4">
                                <div class="w-12 h-12 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-lg shadow-inner">' . $initial . '</div>
                                <div>
                                    <h6 class="font-bold text-gray-800 text-base">' . e($rev['name']) . '</h6>
                                    <div class="text-amber-500 text-xs flex gap-0.5 mt-1">
                                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                                    </div>
                                </div>
                            </div>
                            <p class="text-gray-600 text-sm leading-relaxed">"' . e($rev['comment']) . '"</p>
                        </div>
                    </div>
                </div>';
            }
        }

        $reviewsHtml = '
        <div class="review-carousel-wrapper relative max-w-6xl mx-auto px-2">
            <button type="button" aria-label="Previous Review" class="review-carousel-prev absolute -left-2 md:-left-5 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full bg-white/95 shadow-xl border border-gray-200 text-gray-800 flex items-center justify-center hover:bg-emerald-600 hover:text-white hover:border-emerald-600 hover:scale-110 active:scale-95 transition-all duration-300 cursor-pointer">
                <i class="fas fa-chevron-left text-lg"></i>
            </button>
            <button type="button" aria-label="Next Review" class="review-carousel-next absolute -right-2 md:-right-5 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full bg-white/95 shadow-xl border border-gray-200 text-gray-800 flex items-center justify-center hover:bg-emerald-600 hover:text-white hover:border-emerald-600 hover:scale-110 active:scale-95 transition-all duration-300 cursor-pointer">
                <i class="fas fa-chevron-right text-lg"></i>
            </button>
            <div class="review-carousel-container overflow-hidden rounded-2xl p-2 cursor-grab active:cursor-grabbing">
                <div class="review-carousel-track flex gap-6 transition-transform duration-500 ease-out">
                    ' . $reviewItemsHtml . '
                </div>
            </div>
            <div class="review-carousel-dots flex justify-center items-center gap-2 mt-6"></div>
        </div>';

        // Enhanced Hotline & Call + WhatsApp Banner Block
        $hotlineBlock = '';
        if ($hotlinePhone) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $hotlinePhone);
            $whatsappUrl = "https://api.whatsapp.com/send?phone=88" . ltrim($cleanPhone, '0') . "&text=" . urlencode("আসসালামু আলাইকুম, আমি " . $title . " সম্পর্কে বিস্তারিত জানতে চাই এবং অর্ডার করতে চাই।");

            $hotlineBlock = '
            <div class="relative overflow-hidden bg-gradient-to-br from-emerald-900 via-teal-900 to-slate-900 text-white p-8 lg:p-10 rounded-3xl text-center shadow-2xl mb-12 max-w-3xl mx-auto border border-emerald-500/30 backdrop-blur-xl">
                <div class="absolute -top-24 -right-24 w-48 h-48 bg-emerald-500/20 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-teal-500/20 rounded-full blur-3xl"></div>

                <h3 class="text-2xl sm:text-3xl font-extrabold mb-2 text-transparent bg-clip-text bg-gradient-to-r from-emerald-200 to-teal-100">' . $hotlineTitle . '</h3>
                <p class="text-emerald-200/80 text-sm mb-6 font-medium">যেকোনো তথ্যের জন্য সরাসরি কল করুন অথবা হোয়াটসঅ্যাপে মেসেজ দিন</p>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="tel:' . $hotlinePhone . '" class="w-full sm:w-auto bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white px-8 py-4 rounded-2xl font-black text-xl sm:text-2xl flex items-center justify-center gap-3 shadow-lg shadow-emerald-500/30 hover:scale-105 active:scale-95 transition-all duration-300 group cursor-pointer border border-emerald-400/40">
                        <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-xl group-hover:rotate-12 transition-transform">
                            <i class="fas fa-phone-volume animate-bounce"></i>
                        </span>
                        <span>' . $hotlinePhone . '</span>
                    </a>

                    <a href="' . $whatsappUrl . '" target="_blank" rel="noopener noreferrer" class="w-full sm:w-auto bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-8 py-4 rounded-2xl font-black text-xl sm:text-2xl flex items-center justify-center gap-3 shadow-lg shadow-green-500/30 hover:scale-105 active:scale-95 transition-all duration-300 group cursor-pointer border border-green-400/40">
                        <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                            <i class="fab fa-whatsapp"></i>
                        </span>
                        <span>WhatsApp এ মেসেজ দিন</span>
                    </a>
                </div>
            </div>';
        }

        // Floating Call & WhatsApp Widgets (Bottom Left)
        $floatingWidget = '';
        if ($hotlinePhone) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $hotlinePhone);
            $whatsappUrl = "https://api.whatsapp.com/send?phone=88" . ltrim($cleanPhone, '0') . "&text=" . urlencode("আসসালামু আলাইকুম, আমি " . $title . " সম্পর্কে বিস্তারিত জানতে চাই এবং অর্ডার করতে চাই।");

            $floatingWidget = '
            <div class="fixed bottom-16 sm:bottom-6 left-4 sm:left-6 z-50 flex flex-col gap-3">
                <a href="' . $whatsappUrl . '" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp Us" class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white p-3.5 sm:p-4 rounded-full shadow-2xl flex items-center gap-3 transition-all duration-300 hover:scale-110 active:scale-95 group border-2 border-white/80">
                    <span class="w-9 h-9 sm:w-10 sm:h-10 bg-white/20 rounded-full flex items-center justify-center text-xl sm:text-2xl animate-pulse"><i class="fab fa-whatsapp"></i></span>
                    <span class="max-w-0 overflow-hidden group-hover:max-w-xs transition-all duration-500 ease-out font-extrabold text-sm whitespace-nowrap pr-1">WhatsApp এ মেসেজ</span>
                </a>

                <a href="tel:' . $hotlinePhone . '" aria-label="Call Hotline" class="bg-gradient-to-r from-emerald-600 to-teal-700 hover:from-emerald-700 hover:to-teal-800 text-white p-3.5 sm:p-4 rounded-full shadow-2xl flex items-center gap-3 transition-all duration-300 hover:scale-110 active:scale-95 group border-2 border-white/80">
                    <span class="w-9 h-9 sm:w-10 sm:h-10 bg-white/20 rounded-full flex items-center justify-center text-lg sm:text-xl animate-bounce"><i class="fas fa-phone-volume"></i></span>
                    <span class="max-w-0 overflow-hidden group-hover:max-w-xs transition-all duration-500 ease-out font-extrabold text-sm whitespace-nowrap pr-1">সরাসরি কল করুন</span>
                </a>
            </div>';
        }

        $html = '<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $title . '</title>
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        html {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        body {
            font-family: \'Noto Sans Bengali\', \'Inter\', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            overflow-x: hidden;
        }
        #mobile-sticky-bar, header, .pulsing-btn {
            will-change: transform;
            transform: translateZ(0);
            -webkit-transform: translateZ(0);
        }
        .reveal-init, .reveal-active {
            opacity: 1 !important;
            transform: none !important;
            visibility: visible !important;
        }
        .hover-lift {
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .hover-lift:hover {
            transform: translateY(-6px) scale(1.01);
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.08);
        }
        @keyframes pulse-glow {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.5); }
            50% { transform: scale(1.015); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .pulsing-btn {
            animation: pulse-glow 2.5s infinite ease-in-out;
        }
        .bg-primary {
            background-color: var(--primary-color) !important;
        }
        .text-primary {
            color: var(--primary-color) !important;
        }
        .border-primary {
            border-color: var(--primary-color) !important;
        }
        .bg-primary-light {
            background-color: rgba(' . hexdec(substr($baseColor, 1, 2)) . ', ' . hexdec(substr($baseColor, 3, 2)) . ', ' . hexdec(substr($baseColor, 5, 2)) . ', 0.1) !important;
        }
        .border-primary-light {
            border-color: rgba(' . hexdec(substr($baseColor, 1, 2)) . ', ' . hexdec(substr($baseColor, 3, 2)) . ', ' . hexdec(substr($baseColor, 5, 2)) . ', 0.2) !important;
        }
    </style>
    ' . loadExtension('facebook-pixel') . '
    <script>
        if (typeof fbq !== "undefined") {
            fbq("track", "ViewContent", {
                content_name: "' . e($title) . '",
                content_ids: ["' . $product->id . '"],
                content_type: "product",
                value: ' . $price . ',
                currency: "BDT"
            });
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 scroll-smooth">

    <!-- Sticky Header -->
    <header class="sticky top-0 bg-white/95 backdrop-blur-md border-b border-gray-100 z-50 transition-all shadow-sm">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="javascript:void(0)" onclick="window.location.reload()" class="text-2xl font-black text-emerald-600 tracking-tight flex items-center gap-2">
                <i class="fas fa-shopping-bag"></i>
                <span>' . gs('site_name') . '</span>
            </a>
            <a href="#checkout-form" class="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold px-6 py-2.5 rounded-full transition-all duration-300 shadow-md hover:shadow-lg flex items-center gap-2">
                <i class="fas fa-cart-shopping"></i>
                <span>এখনই কিনুন</span>
            </a>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="max-w-6xl mx-auto px-4 py-12 lg:py-20 grid lg:grid-cols-12 gap-12 items-center">
        <div class="lg:col-span-7 order-2 lg:order-1">
            <span class="inline-block bg-primary-light text-primary font-bold px-4 py-1.5 rounded-full text-sm mb-6 border border-primary-light animate-pulse">
                ' . ($isFreeDelivery ? '<i class="fas fa-truck-fast text-emerald-600 mr-1 animate-bounce"></i> <span class="text-emerald-700 font-black">১০০% ফ্রি হোম ডেলিভারি অফার!</span>' : 'ধামাকা ক্যাশ অন ডেলিভারি অফার!') . '
            </span>
            <h1 class="text-4xl lg:text-5xl font-black text-slate-900 leading-tight mb-6 tracking-tight">
                ' . $headline . '
            </h1>
            <p class="text-lg text-slate-600 mb-8 font-medium leading-relaxed">
                ' . $subtitle . '
            </p>

            <ul class="mb-8 space-y-1">
                ' . $bulletsHtml . '
            </ul>

            <!-- Pricing block -->
            <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-[0_10px_30px_rgba(0,0,0,0.03)] max-w-md mb-8 flex items-center justify-between hover:shadow-md transition-shadow duration-300">
                <div>
                    <span class="text-slate-400 line-through text-lg font-medium block">পূর্বে মূল্য: ' . $regularPrice . ' BDT</span>
                    <span class="text-emerald-600 text-3xl font-black block mt-1">আজকের অফার: ' . $price . ' BDT</span>
                    ' . ($isFreeDelivery ? '<div class="mt-2 inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 text-xs font-black px-3 py-1 rounded-full border border-emerald-200 animate-pulse"><i class="fas fa-truck-fast"></i> <span>সারা বাংলাদেশে ফ্রি ডেলিভারি!</span></div>' : '') . '
                </div>
                <div class="bg-emerald-50 text-emerald-700 font-bold px-4 py-2.5 rounded-xl border border-emerald-100 text-center animate-bounce text-sm">
                    সঞ্চয়: ' . $discountAmount . ' BDT!
                </div>
            </div>

            ' . $countdownTimerHtml . '

            <div class="flex flex-col sm:flex-row gap-4 max-w-lg">
                <a href="#checkout-form" class="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 hover:scale-[1.02] active:scale-[0.98] text-white text-xl font-bold px-8 py-4.5 rounded-2xl transition-all duration-300 shadow-lg hover:shadow-xl hover:shadow-emerald-500/20 text-center flex items-center justify-center gap-3 pulsing-btn">
                    <i class="fas fa-hand-pointer"></i>
                    <span>অর্ডার করতে ফর্মটি পূরণ করুন</span>
                </a>
            </div>
        </div>

        <div class="lg:col-span-5 order-1 lg:order-2 flex flex-col justify-center">
            <div class="relative w-full rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.06)] border border-slate-100 overflow-hidden aspect-square bg-white group mb-6 hover:scale-[1.01] transition-transform duration-300">
                ' . $sliderHtml . '
            </div>
            
            <div class="grid grid-cols-3 gap-3 mt-6 text-center">
                <div class="bg-white p-5 rounded-2xl border ' . ($isFreeDelivery ? 'border-emerald-200 bg-emerald-50/40 shadow-emerald-500/10' : 'border-slate-100') . ' hover:border-primary-light hover:shadow-md hover:-translate-y-1 transition-all duration-300 flex flex-col items-center">
                    <div class="w-12 h-12 rounded-full ' . ($isFreeDelivery ? 'bg-emerald-100 text-emerald-600' : 'bg-primary-light text-primary') . ' flex items-center justify-center text-xl mb-3"><i class="fas fa-truck-fast ' . ($isFreeDelivery ? 'animate-bounce' : '') . '"></i></div>
                    <p class="text-xs font-bold ' . ($isFreeDelivery ? 'text-emerald-700' : 'text-slate-700') . '">' . ($isFreeDelivery ? '১০০% ফ্রি ডেলিভারি' : 'ফাস্ট হোম ডেলিভারি') . '</p>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-100 hover:border-primary-light hover:shadow-md hover:-translate-y-1 transition-all duration-300 flex flex-col items-center">
                    <div class="w-12 h-12 rounded-full bg-primary-light text-primary flex items-center justify-center text-xl mb-3"><i class="fas fa-hand-holding-dollar"></i></div>
                    <p class="text-xs font-bold text-slate-700">ক্যাশ অন ডেলিভারি</p>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-100 hover:border-primary-light hover:shadow-md hover:-translate-y-1 transition-all duration-300 flex flex-col items-center">
                    <div class="w-12 h-12 rounded-full bg-primary-light text-primary flex items-center justify-center text-xl mb-3"><i class="fas fa-shield-halved"></i></div>
                    <p class="text-xs font-bold text-slate-700">১০০% অরিজিনাল পণ্য</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Description & Video Embed -->
    <section class="bg-white border-y border-gray-100 py-16 lg:py-24">
        <div class="max-w-6xl mx-auto px-6">
            <div class="text-center mb-12">
                <span class="text-primary font-bold tracking-wider text-xs uppercase bg-primary-light px-4 py-1.5 rounded-full border border-primary-light">Product Details</span>
                <h2 class="text-3xl lg:text-4xl font-black text-slate-900 mt-4">' . $whyUsTitle . '</h2>
            </div>
            
            ' . ($videoEmbedHtml ? '<div class="mb-16 max-w-3xl mx-auto rounded-3xl overflow-hidden shadow-2xl border border-slate-100 aspect-video">' . $videoEmbedHtml . '</div>' : '') . '

            <div class="prose max-w-none text-slate-800 text-xl font-medium leading-relaxed">
                ' . $whyUsDescription . '
            </div>
        </div>
    </section>

    <!-- Product Description list -->
    ' . ($descriptionsHtml ? '
    <section class="py-16 bg-slate-50 border-b border-gray-100">
        <div class="max-w-4xl mx-auto px-4">
            <h2 class="text-3xl font-extrabold text-gray-900 text-center mb-12">' . $productDescTitle . '</h2>
            <div class="grid md:grid-cols-2 gap-6">
                ' . $descriptionsHtml . '
            </div>
        </div>
    </section>
    ' : '') . '

    <!-- Social Proof / Reviews -->
    <section class="max-w-6xl mx-auto px-4 py-16 lg:py-24">
        <div class="text-center mb-16">
            <span class="text-primary font-bold tracking-wider text-xs uppercase bg-primary-light px-4 py-1.5 rounded-full border border-primary-light">Reviews</span>
            <h2 class="text-3xl lg:text-4xl font-black text-slate-900 mt-4">গ্রাহকদের মূল্যবান মতামত (Reviews)</h2>
        </div>
        ' . $reviewsHtml . '
    </section>

    ' . $faqHtml . '

    <!-- COD Checkout Form -->
    <section class="bg-slate-900 text-white py-20 border-t border-slate-800" id="checkout-form">
        <div class="max-w-2xl mx-auto px-4">
            ' . $hotlineBlock . '

            <div class="bg-white text-slate-800 p-8 lg:p-12 rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.1)] border border-slate-100">
                <div class="text-center mb-8">
                    <span class="bg-emerald-50 text-emerald-700 font-bold px-4 py-1.5 rounded-full text-xs border border-emerald-100 tracking-wide uppercase">
                        ' . ($isFreeDelivery ? '🚚 ১০০% ফ্রি হোম ডেলিভারি (কোনো ডেলিভারি চার্জ নেই)' : 'ক্যাশ অন ডেলিভারি (হাতে পেয়ে মূল্য পরিশোধ)') . '
                    </span>
                    <h3 class="text-2xl lg:text-3xl font-black mt-4 mb-2">অর্ডার করতে ফর্মটি পূরণ করুন</h3>
                    <p class="text-slate-500 font-medium">ডেলিভারি ম্যানের কাছ থেকে প্রোডাক্ট বুঝে পেয়ে টাকা পরিশোধ করুন।</p>
                </div>

                <form action="' . $checkoutUrl . '" method="POST" class="space-y-5">
                    <input type="hidden" name="_token" value="' . $csrfToken . '">
                    <input type="hidden" name="product_id" value="' . $product->id . '">
                    <input type="hidden" name="landing_page_id" value="' . ($data['id'] ?? '') . '">

                    ' . $variantsHtml . '

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">পণ্যের পরিমাণ (Quantity) <span class="text-rose-500">*</span></label>
                        <div class="flex items-center gap-3">
                            <div class="inline-flex items-center rounded-xl border border-gray-200 bg-white p-1 shadow-sm">
                                <button type="button" id="qty_minus" onclick="changeQuantity(-1)" class="w-10 h-10 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-lg flex items-center justify-center transition-all cursor-pointer">-</button>
                                <input type="number" name="quantity" id="quantity_input" value="1" min="1" max="99" readonly class="w-14 text-center font-bold text-lg text-gray-800 outline-none border-none bg-transparent">
                                <button type="button" id="qty_plus" onclick="changeQuantity(1)" class="w-10 h-10 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-lg flex items-center justify-center transition-all cursor-pointer shadow-sm">+</button>
                            </div>
                            <span class="text-sm font-semibold text-gray-500">(৳ <span id="unit_price_val">' . $price . '</span> / টি)</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">আপনার নাম <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400"><i class="fas fa-user"></i></span>
                            <input type="text" name="name" required autocomplete="name" placeholder="আপনার সম্পূর্ণ নাম লিখুন" class="w-full pl-12 pr-4 py-4 text-base sm:text-sm rounded-2xl border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary-light outline-none transition-all duration-200 text-slate-800 bg-slate-50/50 hover:bg-white font-medium shadow-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">মোবাইল নম্বর <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400"><i class="fas fa-phone"></i></span>
                            <input type="tel" name="mobile" required inputmode="tel" autocomplete="tel" placeholder="১১ ডিজিটের মোবাইল নম্বর (যেমন: 017... বা +88017...)" class="w-full pl-12 pr-4 py-4 text-base sm:text-sm rounded-2xl border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary-light outline-none transition-all duration-200 text-slate-800 bg-slate-50/50 hover:bg-white font-medium shadow-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">ডেলিভারি এলাকা <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400"><i class="fas fa-truck"></i></span>
                            <select name="shipping_location" id="shipping_location" required class="w-full pl-12 pr-10 py-4 rounded-2xl border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary-light outline-none transition-all duration-200 text-slate-800 bg-slate-50/50 hover:bg-white font-medium appearance-none shadow-sm">
                                <option value="inside" data-charge="' . $insideCharge . '">ঢাকা সিটির ভেতরে (' . ($isFreeDelivery ? 'ফ্রি' : $insideCharge . ' BDT') . ')</option>
                                <option value="outside" data-charge="' . $outsideCharge . '">ঢাকা সিটির বাইরে (' . ($isFreeDelivery ? 'ফ্রি' : $outsideCharge . ' BDT') . ')</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 pointer-events-none"><i class="fas fa-chevron-down text-xs"></i></span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">ডেলিভারি ঠিকানা <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute top-4 left-0 flex items-start pl-4 text-slate-400"><i class="fas fa-map-marker-alt mt-1"></i></span>
                            <textarea name="address" required placeholder="আপনার জেলা, থানা ও গ্রামের নাম বা বাসা নম্বর লিখুন" class="w-full pl-12 pr-4 py-4 rounded-2xl border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary-light outline-none transition-all duration-200 text-slate-800 bg-slate-50/50 hover:bg-white font-medium shadow-sm" rows="3"></textarea>
                        </div>
                    </div>

                    <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 flex items-center justify-between text-base">
                        <span class="font-bold text-slate-600">ডেলিভারি চার্জ: <span id="delivery_charge_val" class="text-primary font-extrabold">' . ($isFreeDelivery ? 'ফ্রি' : $insideCharge . ' BDT') . '</span></span>
                        <span class="font-black text-slate-800 text-xl">সর্বমোট বিল: <span id="total_bill_val" class="text-emerald-600 font-black">' . ($price + $insideCharge) . ' BDT</span></span>
                    </div>

                    <button type="submit" class="w-full bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 hover:scale-[1.01] active:scale-[0.99] text-white font-black text-xl py-4.5 rounded-2xl shadow-lg hover:shadow-xl hover:shadow-emerald-600/20 transition-all duration-300 flex items-center justify-center gap-3 pulsing-btn">
                        <i class="fas fa-circle-check"></i>
                        <span>অর্ডার নিশ্চিত করুন</span>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-950 text-gray-500 py-12 text-center border-t border-slate-900">
        <div class="max-w-6xl mx-auto px-4">
            <h4 class="text-white text-xl font-bold tracking-tight mb-4 flex items-center justify-center gap-2">
                <i class="fas fa-shopping-bag text-indigo-500"></i>
                <span>' . gs('site_name') . '</span>
            </h4>
            <p class="text-sm max-w-md mx-auto mb-6 text-gray-400">ডেলিভারি ম্যানের সামনে প্রোডাক্ট দেখে ও চেক করে পরিশোধ করার শতভাগ নিশ্চয়তা।</p>
            <p class="text-xs">&copy; ' . date('Y') . ' ' . gs('site_name') . '. All rights reserved.</p>
        </div>
    </footer>

    <!-- Mobile Sticky Floating Order Bar -->
    <div id="mobile-sticky-bar" class="fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200 p-2.5 shadow-[0_-4px_20px_rgba(0,0,0,0.15)] flex items-center justify-between gap-3 sm:hidden">
        <div class="pl-2">
            <span class="text-[10px] ' . ($isFreeDelivery ? 'text-emerald-600 font-black' : 'text-slate-500 font-semibold') . ' block leading-tight">' . ($isFreeDelivery ? '🚚 ফ্রি ডেলিভারি' : 'বিশেষ অফার') . '</span>
            <span class="text-base font-black text-emerald-600 leading-tight block">' . $price . ' BDT</span>
        </div>
        <a href="#checkout-form" class="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold py-2.5 px-4 rounded-xl shadow-md text-sm flex items-center justify-center gap-2 pulsing-btn flex-1 active:scale-95 transition-all">
            <i class="fas fa-cart-shopping"></i>
            <span>অর্ডার করুন</span>
        </a>
    </div>

    ' . $floatingWidget . '

    ' . $purchasePopupHtml . '

    <script>
        document.getElementById("shipping_location").addEventListener("change", function() {
            var charge = parseInt(this.options[this.selectedIndex].getAttribute("data-charge"));
            var basePrice = ' . $price . ';
            document.getElementById("delivery_charge_val").innerText = charge === 0 ? "ফ্রি" : charge + " BDT";
            document.getElementById("total_bill_val").innerText = (basePrice + charge) + " BDT";
        });
    </script>

    ' . $sliderJs . '
    ' . $faqAndUrgencyJs . '

    <!-- Review Image Lightbox Modal -->
    <div id="review-image-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/90 backdrop-blur-md p-4 transition-all duration-300 opacity-0 pointer-events-none">
        <button type="button" id="review-modal-close" class="absolute top-5 right-5 text-white/80 hover:text-white bg-white/10 hover:bg-white/20 w-12 h-12 rounded-full flex items-center justify-center text-2xl transition-all z-10 focus:outline-none cursor-pointer">
            <i class="fas fa-xmark"></i>
        </button>
        <div class="relative max-w-5xl max-h-[90vh] w-full flex items-center justify-center p-2">
            <img id="review-modal-img" src="" alt="Review Image Full View" class="max-h-[85vh] max-w-full object-contain rounded-2xl shadow-2xl border border-white/20 transform transition-transform duration-300 scale-95">
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var modal = document.getElementById("review-image-modal");
            var modalImg = document.getElementById("review-modal-img");
            var closeBtn = document.getElementById("review-modal-close");

            function openReviewModal(src) {
                if (!modal || !modalImg) return;
                modalImg.src = src;
                modal.classList.remove("hidden", "pointer-events-none");
                setTimeout(function() {
                    modal.classList.remove("opacity-0");
                    modalImg.classList.remove("scale-95");
                    modalImg.classList.add("scale-100");
                }, 10);
                document.body.style.overflow = "hidden";
            }

            function closeReviewModal() {
                if (!modal || !modalImg) return;
                modal.classList.add("opacity-0");
                if (modalImg) modalImg.classList.remove("scale-100");
                if (modalImg) modalImg.classList.add("scale-95");
                setTimeout(function() {
                    modal.classList.add("hidden", "pointer-events-none");
                    modalImg.src = "";
                    document.body.style.overflow = "";
                }, 300);
            }

            document.querySelectorAll(".image-popup, .cursor-zoom-in, [alt=\'Customer Review\']").forEach(function(img) {
                img.classList.add("cursor-pointer");
                img.addEventListener("click", function(e) {
                    e.preventDefault();
                    openReviewModal(this.src || this.getAttribute("data-src") || this.href);
                });
            });

            if (closeBtn) closeBtn.addEventListener("click", closeReviewModal);
            if (modal) {
                modal.addEventListener("click", function(e) {
                    if (e.target === modal || e.target.parentElement === modal) {
                        closeReviewModal();
                    }
                });
            }
            document.addEventListener("keydown", function(e) {
                if (e.key === "Escape") closeReviewModal();
            });

            // Scroll Reveal IntersectionObserver
            var selectors = ["section > div", ".lg\\:col-span-7", ".lg\\:col-span-5", "#checkout-form", ".grid > div", "iframe", ".prose"];
            selectors.forEach(function(sel) {
                document.querySelectorAll(sel).forEach(function(el, index) {
                    if (!el.classList.contains("reveal-init") && !el.closest("#mobile-sticky-bar")) {
                        el.classList.add("reveal-init");
                        if (el.parentElement && el.parentElement.classList.contains("grid")) {
                            var delay = (index % 3) * 0.15;
                            el.style.transitionDelay = delay + "s";
                        }
                    }
                });
            });
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add("reveal-active");
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.08, rootMargin: "0px 0px -40px 0px" });
            document.querySelectorAll(".reveal-init").forEach(function(el) {
                observer.observe(el);
            });
        });
    </script>
    ' . loadExtension('tawk-chat') . '
    <script type="text/javascript">
        var Tawk_API = Tawk_API || {};
        Tawk_API.onLoad = function(){
            if (typeof Tawk_API.hideWidget === "function") {
                Tawk_API.hideWidget();
            }
        };
    </script>
    <style>
        #tawk-default-container, iframe[title*="chat widget"], .tawk-min-container {
            display: none !important;
            visibility: hidden !important;
        }
    </style>
</body>
</html>';

        return $html;
    }

    /**
     * Delete landing page
     */
    public function destroy($id)
    {
        $landingPage = ChatbotLandingPage::findOrFail($id);
        $landingPage->delete();

        $notify[] = ['success', 'Landing Page deleted successfully'];
        return back()->withNotify($notify);
    }
}
