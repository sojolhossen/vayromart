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
            'image_url' => 'nullable|url',
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

        // Upload review images (up to 6)
        $reviewImages = [];
        if ($request->id) {
            $existingPage = ChatbotLandingPage::find($request->id);
            if ($existingPage && isset($existingPage->design_settings['review_images'])) {
                $reviewImages = $existingPage->design_settings['review_images'];
            }
        }
        for ($i = 1; $i <= 6; $i++) {
            $inputName = "review_image_" . $i;
            if ($request->hasFile($inputName)) {
                try {
                    $imgName = fileUploader($request->file($inputName), 'assets/images/landing');
                    $reviewImages[$i - 1] = 'assets/images/landing/' . $imgName;
                } catch (\Exception $e) {
                    \Log::error("Review Image {$i} Upload Error: " . $e->getMessage());
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
            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^\"&?\/ ]{11})/', $videoUrl, $match)) {
                $embedCode = $match[1];
                $videoEmbedHtml = '<iframe class="w-full aspect-video rounded-2xl shadow-lg" src="https://www.youtube.com/embed/' . $embedCode . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
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

        // Reviews HTML
        $reviewsHtml = '';
        $reviewers = [
            ['name' => ($data['reviewer_name_1'] ?? '') ?: 'Sojol Hossen', 'comment' => ($data['reviewer_comment_1'] ?? '') ?: 'অসাধারণ প্রোডাক্ট! ঠিক যেমনটি চেয়েছিলাম তেমনই পেয়েছি। ডেলিভারি সার্ভিসও খুব ফাস্ট ছিল। ধন্যবাদ বায়রোমার্ট!'],
            ['name' => ($data['reviewer_name_2'] ?? '') ?: 'Farhana Yasmin', 'comment' => ($data['reviewer_comment_2'] ?? '') ?: 'পণ্যটির কোয়ালিটি খুবই ভালো। ২ দিনেই ডেলিভারি পেয়েছি। আপনারা চাইলে চোখ বন্ধ করে নিতে পারেন।'],
            ['name' => ($data['reviewer_name_3'] ?? '') ?: 'Md. Arif', 'comment' => ($data['reviewer_comment_3'] ?? '') ?: 'প্রোডাক্ট হাতে পেয়ে চেক করে পেমেন্ট করেছি। ক্যাশ অন ডেলিভারি সুবিধা থাকায় অনেক সুবিধা হয়েছে। ১০/১০ দিব।']
        ];
        
        foreach ($reviewers as $idx => $rev) {
            $initial = mb_substr($rev['name'], 0, 1, 'utf-8');
            $reviewsHtml .= '
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all duration-300">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-lg shadow-inner">' . $initial . '</div>
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
            </div>';
        }

        $html = '<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $title . '</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: ' . $baseColor . ';
        }
        body {
            font-family: \'Hind Siliguri\', \'Inter\', sans-serif;
        }
        @keyframes pulse-ring {
            0% { transform: scale(0.95); opacity: 0.5; }
            50% { transform: scale(1.05); opacity: 0.8; }
            100% { transform: scale(0.95); opacity: 0.5; }
        }
        .pulsing-btn {
            animation: pulse-ring 2s infinite ease-in-out;
            background-color: var(--primary-color) !important;
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
</head>
<body class="bg-gray-50 text-gray-900 scroll-smooth">

    <!-- Sticky Header -->
    <header class="sticky top-0 bg-white/95 backdrop-blur-md border-b border-gray-100 z-50 transition-all shadow-sm">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="' . url('/') . '" class="text-2xl font-black text-primary tracking-tight flex items-center gap-2">
                <i class="fas fa-shopping-bag"></i>
                <span>Vayromart</span>
            </a>
            <a href="#checkout-form" class="bg-primary hover:brightness-95 text-white font-bold px-6 py-2.5 rounded-full transition-all duration-300 shadow-md hover:shadow-lg flex items-center gap-2">
                <i class="fas fa-cart-shopping"></i>
                <span>এখনই কিনুন</span>
            </a>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="max-w-6xl mx-auto px-4 py-12 lg:py-20 grid lg:grid-cols-12 gap-12 items-center">
        <div class="lg:col-span-7">
            <span class="inline-block bg-primary-light text-primary font-bold px-4 py-1.5 rounded-full text-sm mb-6 border border-primary-light">
                ধামাকা ক্যাশ অন ডেলিভারি অফার!
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
                </div>
                <div class="bg-emerald-50 text-emerald-700 font-bold px-4 py-2.5 rounded-xl border border-emerald-100 text-center animate-bounce text-sm">
                    সঞ্চয়: ' . $discountAmount . ' BDT!
                </div>
            </div>

            ' . $countdownTimerHtml . '

            <div class="flex flex-col sm:flex-row gap-4 max-w-lg">
                <a href="#checkout-form" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xl font-bold px-8 py-4 rounded-full transition-all duration-300 shadow-lg text-center flex items-center justify-center gap-3 pulsing-btn">
                    <i class="fas fa-hand-pointer"></i>
                    <span>অর্ডার করতে ফর্মটি পূরণ করুন</span>
                </a>
            </div>
        </div>

        <div class="lg:col-span-5 flex flex-col justify-center">
            ' . $sliderHtml . '
            
            <div class="grid grid-cols-3 gap-3 mt-6 text-center">
                <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                    <i class="fas fa-truck-fast text-primary text-2xl mb-2"></i>
                    <p class="text-xs font-bold text-gray-700">ফাস্ট হোম ডেলিভারি</p>
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
        <div class="max-w-4xl mx-auto px-4">
            <h2 class="text-3xl font-extrabold text-gray-900 text-center mb-8">পণ্যটির বিস্তারিত বিবরণ</h2>
            
            ' . ($videoEmbedHtml ? '<div class="mb-12 max-w-3xl mx-auto">' . $videoEmbedHtml . '</div>' : '') . '

            <div class="prose max-w-none text-gray-700 text-lg leading-relaxed">
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
                        ক্যাশ অন ডেলিভারি (হাতে পেয়ে মূল্য পরিশোধ)
                    </span>
                    <h3 class="text-2xl lg:text-3xl font-black mt-4 mb-2">অর্ডার করতে ফর্মটি পূরণ করুন</h3>
                    <p class="text-gray-500 font-medium">ডেলিভারি ম্যানের কাছ থেকে প্রোডাক্ট বুঝে পেয়ে টাকা পরিশোধ করুন।</p>
                </div>

                <form action="' . $checkoutUrl . '" method="POST" class="space-y-6">
                    <input type="hidden" name="_token" value="' . $csrfToken . '">
                    <input type="hidden" name="product_id" value="' . $product->id . '">
                    <input type="hidden" name="landing_page_id" value="' . ($data['id'] ?? '') . '">

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">আপনার নাম <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="fas fa-user"></i></span>
                            <input type="text" name="name" required placeholder="আপনার সম্পূর্ণ নাম লিখুন" class="w-full pl-11 pr-4 py-3.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all duration-200">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">মোবাইল নম্বর <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="fas fa-phone"></i></span>
                            <input type="tel" name="mobile" required placeholder="১১ ডিজিটের মোবাইল নম্বর লিখুন" class="w-full pl-11 pr-4 py-3.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all duration-200">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">ডেলিভারি এলাকা <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="fas fa-truck"></i></span>
                            <select name="shipping_location" id="shipping_location" required class="w-full pl-11 pr-4 py-3.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all duration-200">
                                <option value="inside" data-charge="' . $insideCharge . '">ঢাকা সিটির ভেতরে (' . ($isFreeDelivery ? 'ফ্রি' : $insideCharge . ' BDT') . ')</option>
                                <option value="outside" data-charge="' . $outsideCharge . '">ঢাকা সিটির বাইরে (' . ($isFreeDelivery ? 'ফ্রি' : $outsideCharge . ' BDT') . ')</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">ডেলিভারি ঠিকানা <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute top-4 left-0 flex items-start pl-4 text-gray-400"><i class="fas fa-map-marker-alt mt-1"></i></span>
                            <textarea name="address" required placeholder="আপনার জেলা, থানা ও গ্রামের নাম বা বাসা নম্বর লিখুন" class="w-full pl-11 pr-4 py-3.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all duration-200" rows="3"></textarea>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-6 rounded-2xl border border-gray-100 flex items-center justify-between text-base">
                        <span class="font-bold text-gray-600">ডেলিভারি চার্জ: <span id="delivery_charge_val">' . ($isFreeDelivery ? 'ফ্রি' : $insideCharge . ' BDT') . '</span></span>
                        <span class="font-black text-indigo-600 text-xl">সর্বমোট বিল: <span id="total_bill_val">' . ($price + $insideCharge) . ' BDT</span></span>
                    </div>

                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-lg py-4 rounded-xl shadow-lg transition-all duration-300 flex items-center justify-center gap-2 pulsing-btn">
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
            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^\"&?\/ ]{11})/', $videoUrl, $match)) {
                $embedCode = $match[1];
                $videoEmbedHtml = '<iframe class="w-full aspect-video rounded-2xl shadow-lg" src="https://www.youtube.com/embed/' . $embedCode . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
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

        // Custom descriptions list
        $descriptionsHtml = '';
        if (!empty($data['descriptions'])) {
            foreach ($data['descriptions'] as $desc) {
                if (trim($desc)) {
                    $descriptionsHtml .= '
                    <div class="flex gap-4 items-start bg-white p-5 rounded-2xl border border-slate-100 hover:border-primary-light hover:shadow-md transition-all duration-300">
                        <span class="w-8 h-8 rounded-full bg-primary-light text-primary flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fas fa-arrow-right text-xs"></i></span>
                        <p class="text-slate-700 font-semibold">' . e($desc) . '</p>
                    </div>';
                }
            }
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

        // Review images
        $reviewsHtml = '';
        if (!empty($data['review_images'])) {
            $reviewsHtml .= '<div class="grid grid-cols-2 md:grid-cols-3 gap-6">';
            foreach ($data['review_images'] as $imgPath) {
                if ($imgPath) {
                    $imgUrl = $resolveUrl($imgPath);
                    $reviewsHtml .= '
                    <div class="overflow-hidden rounded-2xl border border-gray-100 shadow-sm hover:shadow-lg hover:scale-[1.02] transition-all duration-300 bg-white p-2">
                        <img src="' . $imgUrl . '" alt="Customer Review" class="w-full h-64 object-cover rounded-xl image-popup cursor-zoom-in">
                    </div>';
                }
            }
            $reviewsHtml .= '</div>';
        } else {
            // Fallback text reviews
            $reviewers = [
                ['name' => 'Sojol Hossen', 'comment' => 'অসাধারণ প্রোডাক্ট! ঠিক যেমনটি চেয়েছিলাম তেমনই পেয়েছি। ডেলিভারি সার্ভিসও খুব ফাস্ট ছিল। ধন্যবাদ বায়রোমার্ট!'],
                ['name' => 'Farhana Yasmin', 'comment' => 'পণ্যটির কোয়ালিটি খুবই ভালো। ২ দিনেই ডেলিভারি পেয়েছি। আপনারা চাইলে চোখ বন্ধ করে নিতে পারেন।'],
                ['name' => 'Md. Arif', 'comment' => 'প্রোডাক্ট হাতে পেয়ে চেক করে পেমেন্ট করেছি। ক্যাশ অন ডেলিভারি সুবিধা থাকায় অনেক সুবিধা হয়েছে। ১০/১০ দিব।']
            ];
            $reviewsHtml .= '<div class="grid md:grid-cols-3 gap-8">';
            foreach ($reviewers as $rev) {
                $initial = mb_substr($rev['name'], 0, 1, 'utf-8');
                $reviewsHtml .= '
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all duration-300">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-lg shadow-inner">' . $initial . '</div>
                        <div>
                            <h6 class="font-bold text-gray-800 text-base">' . e($rev['name']) . '</h6>
                            <div class="text-amber-500 text-xs flex gap-0.5 mt-1">
                                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm leading-relaxed">"' . e($rev['comment']) . '"</p>
                </div>';
            }
            $reviewsHtml .= '</div>';
        }

        $hotlineBlock = '';
        if ($hotlinePhone) {
            $hotlineBlock = '
            <div class="bg-gradient-to-r from-orange-500 to-amber-500 text-white p-8 rounded-3xl text-center shadow-xl mb-12 max-w-2xl mx-auto border border-orange-400">
                <h3 class="text-2xl font-bold mb-3">' . $hotlineTitle . '</h3>
                <a href="tel:' . $hotlinePhone . '" class="text-3xl lg:text-4xl font-black flex items-center justify-center gap-4 hover:scale-105 transition-all"><i class="fas fa-phone-volume animate-bounce"></i> ' . $hotlinePhone . '</a>
            </div>';
        }

        $floatingWidget = '';
        if ($hotlinePhone) {
            $floatingWidget = '
            <a href="tel:' . $hotlinePhone . '" class="fixed bottom-6 left-6 z-50 bg-emerald-600 hover:bg-emerald-700 text-white p-4 rounded-full shadow-2xl flex items-center gap-3 transition-all hover:scale-110 active:scale-95 group">
                <span class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center text-xl animate-pulse"><i class="fas fa-phone"></i></span>
                <span class="max-w-0 overflow-hidden group-hover:max-w-xs transition-all duration-500 ease-out font-bold text-sm whitespace-nowrap">কল করুন</span>
            </a>';
        }

        $html = '<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $title . '</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: ' . $baseColor . ';
        }
        body {
            font-family: \'Noto Sans Bengali\', \'Inter\', sans-serif;
        }
        @keyframes pulse-ring {
            0% { transform: scale(0.95); opacity: 0.5; }
            50% { transform: scale(1.05); opacity: 0.8; }
            100% { transform: scale(0.95); opacity: 0.5; }
        }
        .pulsing-btn {
            animation: pulse-ring 2s infinite ease-in-out;
            background-color: var(--primary-color) !important;
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
</head>
<body class="bg-slate-50 text-slate-800 scroll-smooth">

    <!-- Sticky Header -->
    <header class="sticky top-0 bg-white/95 backdrop-blur-md border-b border-gray-100 z-50 transition-all shadow-sm">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="' . url('/') . '" class="text-2xl font-black text-primary tracking-tight flex items-center gap-2">
                <i class="fas fa-shopping-bag"></i>
                <span>' . gs('site_name') . '</span>
            </a>
            <a href="#checkout-form" class="bg-primary hover:brightness-95 text-white font-bold px-6 py-2.5 rounded-full transition-all duration-300 shadow-md hover:shadow-lg flex items-center gap-2">
                <i class="fas fa-cart-shopping"></i>
                <span>এখনই কিনুন</span>
            </a>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="max-w-6xl mx-auto px-4 py-12 lg:py-20 grid lg:grid-cols-12 gap-12 items-center">
        <div class="lg:col-span-7">
            <span class="inline-block bg-primary-light text-primary font-bold px-4 py-1.5 rounded-full text-sm mb-6 border border-primary-light">
                ধামাকা ক্যাশ অন ডেলিভারি অফার!
            </span>
            <h1 class="text-3xl lg:text-4xl font-extrabold text-gray-900 leading-tight mb-4">
                ' . $headline . '
            </h1>
            <p class="text-lg text-gray-600 mb-8 font-medium">
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
                </div>
                <div class="bg-emerald-50 text-emerald-700 font-bold px-4 py-2.5 rounded-xl border border-emerald-100 text-center animate-bounce text-sm">
                    সঞ্চয়: ' . $discountAmount . ' BDT!
                </div>
            </div>

            ' . $countdownTimerHtml . '

            <div class="flex flex-col sm:flex-row gap-4 max-w-lg">
                <a href="#checkout-form" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xl font-bold px-8 py-4 rounded-full transition-all duration-300 shadow-lg text-center flex items-center justify-center gap-3 pulsing-btn">
                    <i class="fas fa-hand-pointer animate-pulse"></i>
                    <span>অর্ডার করতে ফর্মটি পূরণ করুন</span>
                </a>
            </div>
        </div>

        <div class="lg:col-span-5 flex flex-col justify-center">
            ' . $sliderHtml . '
            
            <div class="grid grid-cols-3 gap-3 mt-6 text-center">
                <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                    <i class="fas fa-truck-fast text-primary text-2xl mb-2"></i>
                    <p class="text-xs font-bold text-gray-700">ফাস্ট হোম ডেলিভারি</p>
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
        <div class="max-w-4xl mx-auto px-4">
            <h2 class="text-3xl font-extrabold text-gray-900 text-center mb-8">' . $whyUsTitle . '</h2>
            
            ' . ($videoEmbedHtml ? '<div class="mb-12 max-w-3xl mx-auto">' . $videoEmbedHtml . '</div>' : '') . '

            <div class="prose max-w-none text-gray-700 text-lg leading-relaxed">
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
    <section class="max-w-6xl mx-auto px-4 py-16">
        <h2 class="text-3xl font-extrabold text-gray-900 text-center mb-12">গ্রাহকদের মূল্যবান মতামত (Reviews)</h2>
        ' . $reviewsHtml . '
    </section>

    ' . $faqHtml . '

    <!-- COD Checkout Form -->
    <section class="bg-slate-900 text-white py-20 border-t border-slate-800" id="checkout-form">
        <div class="max-w-2xl mx-auto px-4">
            ' . $hotlineBlock . '

            <div class="bg-white text-gray-900 p-8 lg:p-12 rounded-3xl shadow-2xl border border-gray-100">
                <div class="text-center mb-8">
                    <span class="bg-emerald-50 text-emerald-700 font-bold px-4 py-1.5 rounded-full text-xs border border-emerald-100 tracking-wide uppercase">
                        ক্যাশ অন ডেলিভারি (হাতে পেয়ে মূল্য পরিশোধ)
                    </span>
                    <h3 class="text-2xl lg:text-3xl font-black mt-4 mb-2">অর্ডার করতে ফর্মটি পূরণ করুন</h3>
                    <p class="text-gray-500 font-medium">ডেলিভারি ম্যানের কাছ থেকে প্রোডাক্ট বুঝে পেয়ে টাকা পরিশোধ করুন।</p>
                </div>

                <form action="' . $checkoutUrl . '" method="POST" class="space-y-6">
                    <input type="hidden" name="_token" value="' . $csrfToken . '">
                    <input type="hidden" name="product_id" value="' . $product->id . '">
                    <input type="hidden" name="landing_page_id" value="' . ($data['id'] ?? '') . '">

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">আপনার নাম <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="fas fa-user"></i></span>
                            <input type="text" name="name" required placeholder="আপনার সম্পূর্ণ নাম লিখুন" class="w-full pl-11 pr-4 py-3.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all duration-200">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">মোবাইল নম্বর <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="fas fa-phone"></i></span>
                            <input type="tel" name="mobile" required placeholder="১১ ডিজিটের মোবাইল নম্বর লিখুন" class="w-full pl-11 pr-4 py-3.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all duration-200">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">ডেলিভারি এলাকা <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="fas fa-truck"></i></span>
                            <select name="shipping_location" id="shipping_location" required class="w-full pl-11 pr-4 py-3.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all duration-200">
                                <option value="inside" data-charge="' . $insideCharge . '">ঢাকা সিটির ভেতরে (' . ($isFreeDelivery ? 'ফ্রি' : $insideCharge . ' BDT') . ')</option>
                                <option value="outside" data-charge="' . $outsideCharge . '">ঢাকা সিটির বাইরে (' . ($isFreeDelivery ? 'ফ্রি' : $outsideCharge . ' BDT') . ')</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">ডেলিভারি ঠিকানা <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute top-4 left-0 flex items-start pl-4 text-gray-400"><i class="fas fa-map-marker-alt mt-1"></i></span>
                            <textarea name="address" required placeholder="আপনার জেলা, থানা ও গ্রামের নাম বা বাসা নম্বর লিখুন" class="w-full pl-11 pr-4 py-3.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all duration-200" rows="3"></textarea>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-6 rounded-2xl border border-gray-100 flex items-center justify-between text-base">
                        <span class="font-bold text-gray-600">ডেলিভারি চার্জ: <span id="delivery_charge_val">' . ($isFreeDelivery ? 'ফ্রি' : $insideCharge . ' BDT') . '</span></span>
                        <span class="font-black text-indigo-600 text-xl">সর্বমোট বিল: <span id="total_bill_val">' . ($price + $insideCharge) . ' BDT</span></span>
                    </div>

                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-lg py-4 rounded-xl shadow-lg transition-all duration-300 flex items-center justify-center gap-2 pulsing-btn">
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
