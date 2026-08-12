<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ChatbotLandingPage;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Guest;
use App\Models\ProductVariant;
use App\Models\AdminNotification;
use App\Lib\ProductManager;
use Illuminate\Http\Request;

class LandingPageController extends Controller
{
    /**
     * View standalone landing page by slug
     */
    public function viewPage($slug)
    {
        $landingPage = ChatbotLandingPage::where('slug', $slug)->firstOrFail();
        
        $content = $landingPage->content;

        // 1. Dynamically replace CSRF token to prevent "Session Expired" (419 error)
        $content = preg_replace_callback('/<input\s+[^>]*name=["\']_token["\'][^>]*>/i', function($match) {
            return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
        }, $content);
        
        $content = preg_replace_callback('/<input\s+[^>]*value=["\'][^"\']*["\'][^>]*name=["\']_token["\'][^>]*>/i', function($match) {
            return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
        }, $content);

        // Dynamically replace color codes with human-readable color names in dropdowns for existing pages
        if ($landingPage->product_id) {
            $product = Product::with('attributeValues')->find($landingPage->product_id);
            if ($product && $product->attributeValues) {
                foreach ($product->attributeValues as $attrVal) {
                    $attrName = !empty($attrVal->name) ? $attrVal->name : $attrVal->value;
                    $content = preg_replace(
                        '/<option\s+value=["\']' . $attrVal->id . '["\'][^>]*>.*?<\/option>/i',
                        '<option value="' . $attrVal->id . '">' . e($attrName) . '</option>',
                        $content
                    );
                }
            }
        }

        // 2. Replace the old scaling & blinking pulse animation with a modern glowing shadow pulse
        $content = preg_replace('/@keyframes pulse-ring\s*\{[^}]*\}/is', '@keyframes pulse-glow {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); }
            70% { box-shadow: 0 0 0 15px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }', $content);

        $content = preg_replace('/\.pulsing-btn\s*\{[^}]*\}/is', '.pulsing-btn {
            animation: pulse-glow 2s infinite;
        }', $content);

        // 3. Dynamically replace old button styles with the premium green gradient styling
        // Order form buttons
        $content = str_replace(
            'class="bg-emerald-600 hover:bg-emerald-700 text-white text-xl font-bold px-8 py-4 rounded-full transition-all duration-300 shadow-lg text-center flex items-center justify-center gap-3 pulsing-btn"',
            'class="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 hover:scale-[1.02] active:scale-[0.98] text-white text-xl font-bold px-8 py-4 rounded-full transition-all duration-300 shadow-lg hover:shadow-xl hover:shadow-emerald-500/20 text-center flex items-center justify-center gap-3 pulsing-btn"',
            $content
        );

        $content = str_replace(
            'class="bg-emerald-600 hover:bg-emerald-700 text-white text-xl font-bold px-8 py-4.5 rounded-2xl transition-all duration-300 shadow-lg hover:shadow-xl hover:shadow-emerald-600/20 text-center flex items-center justify-center gap-3 pulsing-btn"',
            'class="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 hover:scale-[1.02] active:scale-[0.98] text-white text-xl font-bold px-8 py-4.5 rounded-2xl transition-all duration-300 shadow-lg hover:shadow-xl hover:shadow-emerald-600/20 text-center flex items-center justify-center gap-3 pulsing-btn"',
            $content
        );

        // Confirm Order buttons
        $content = str_replace(
            'class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-lg py-4 rounded-xl shadow-lg transition-all duration-300 flex items-center justify-center gap-2 pulsing-btn"',
            'class="w-full bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 hover:scale-[1.01] active:scale-[0.99] text-white font-bold text-xl py-4 rounded-xl shadow-lg hover:shadow-xl hover:shadow-emerald-500/20 transition-all duration-300 flex items-center justify-center gap-2 pulsing-btn"',
            $content
        );

        $content = str_replace(
            'class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xl py-4.5 rounded-2xl shadow-lg hover:shadow-xl hover:shadow-emerald-600/20 active:scale-95 transition-all duration-300 flex items-center justify-center gap-3 pulsing-btn"',
            'class="w-full bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 hover:scale-[1.01] active:scale-[0.99] text-white font-black text-xl py-4.5 rounded-2xl shadow-lg hover:shadow-xl hover:shadow-emerald-500/20 transition-all duration-300 flex items-center justify-center gap-3 pulsing-btn"',
            $content
        );

        // 4. Enhance mobile form inputs (numeric keypad trigger on mobile and autocomplete)
        $content = str_replace(
            '<input type="tel" name="mobile"',
            '<input type="tel" name="mobile" inputmode="numeric" pattern="[0-9]*" autocomplete="tel"',
            $content
        );
        $content = str_replace(
            '<input type="text" name="name"',
            '<input type="text" name="name" autocomplete="name"',
            $content
        );

        // Reposition floating call button on mobile so it doesn't overlap the mobile sticky bar
        $content = str_replace('class="fixed bottom-6 left-6 z-50', 'class="fixed bottom-16 sm:bottom-6 left-4 sm:left-6 z-50', $content);

        // 5. Dynamically inject floating Mobile Sticky Order Bar for existing pages if not present
        if (strpos($content, 'id="mobile-sticky-bar"') === false) {
            $mobileBarHtml = '
    <!-- Mobile Sticky Floating Order Bar -->
    <div id="mobile-sticky-bar" class="fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-gray-200 p-2.5 shadow-[0_-4px_20px_rgba(0,0,0,0.15)] flex items-center justify-between gap-3 sm:hidden">
        <a href="#checkout-form" class="w-full bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold py-3 px-4 rounded-xl shadow-md text-base flex items-center justify-center gap-2 pulsing-btn active:scale-95 transition-all">
            <i class="fas fa-cart-shopping"></i>
            <span>অর্ডার করতে ক্লিক করুন</span>
        </a>
    </div>';
            $content = str_replace('</body>', $mobileBarHtml . "\n</body>", $content);
        }

        // 6. Ensure product image displays FIRST on mobile screens
        $content = str_replace('<div class="lg:col-span-7">', '<div class="lg:col-span-7 order-2 lg:order-1">', $content);
        $content = str_replace('<div class="lg:col-span-5 flex flex-col justify-center">', '<div class="lg:col-span-5 order-1 lg:order-2 flex flex-col justify-center">', $content);

        // 7. Dynamically inject Meta Pixel and ViewContent tracking event for existing pages
        if (strpos($content, 'fbevents.js') === false) {
            $pixelCode = loadExtension('facebook-pixel');
            $viewContentScript = '';
            if ($landingPage->product) {
                $productPrice = !empty($landingPage->design_settings['custom_price']) ? floatval($landingPage->design_settings['custom_price']) : ($landingPage->product->sale_price ?: $landingPage->product->regular_price);
                $viewContentScript = '
    <script>
        if (typeof fbq !== "undefined") {
            fbq("track", "ViewContent", {
                content_name: ' . json_encode($landingPage->product->name) . ',
                content_ids: ["' . $landingPage->product->id . '"],
                content_type: "product",
                value: ' . $productPrice . ',
                currency: "BDT"
            });
        }
    </script>';
            }
            $content = str_replace('</head>', $pixelCode . "\n" . $viewContentScript . "\n</head>", $content);
        }

        // 8. Dynamically highlight Free Delivery if free_delivery is enabled
        $isFree = isset($landingPage->design_settings['free_delivery']) && $landingPage->design_settings['free_delivery'] === 'free';
        if ($isFree) {
            $content = str_replace(
                'ধামাকা ক্যাশ অন ডেলিভারি অফার!',
                '<i class="fas fa-truck-fast text-emerald-600 mr-1 animate-bounce"></i> <span class="text-emerald-700 font-black">১০০% ফ্রি হোম ডেলিভারি অফার!</span>',
                $content
            );
            $content = str_replace(
                '<p class="text-xs font-bold text-gray-700">ফাস্ট হোম ডেলিভারি</p>',
                '<p class="text-xs font-bold text-emerald-700">১০০% ফ্রি ডেলিভারি</p>',
                $content
            );
            $content = str_replace(
                '<p class="text-xs font-bold text-slate-700">ফাস্ট হোম ডেলিভারি</p>',
                '<p class="text-xs font-bold text-emerald-700">১০০% ফ্রি ডেলিভারি</p>',
                $content
            );
            $content = str_replace(
                'ক্যাশ অন ডেলিভারি (হাতে পেয়ে মূল্য পরিশোধ)',
                '🚚 ১০০% ফ্রি হোম ডেলিভারি (কোনো ডেলিভারি চার্জ নেই)',
                $content
            );
            $content = str_replace(
                '<span class="text-[10px] text-gray-500 font-semibold block leading-tight">বিশেষ অফার</span>',
                '<span class="text-[10px] text-emerald-600 font-black block leading-tight">🚚 ফ্রি ডেলিভারি</span>',
                $content
            );
            $content = str_replace(
                '<span class="text-[10px] text-slate-500 font-semibold block leading-tight">বিশেষ অফার</span>',
                '<span class="text-[10px] text-emerald-600 font-black block leading-tight">🚚 ফ্রি ডেলিভারি</span>',
                $content
            );
        }

        // 9. Dynamically resolve YouTube video embeds (including YouTube Shorts URLs & Autoplay)
        $videoUrl = $landingPage->design_settings['video_url'] ?? '';
        if ($videoUrl) {
            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts)\/|.*[?&]v=)|youtu\.be\/)([^\"&?\/ ]{11})/i', $videoUrl, $match)) {
                $embedCode = $match[1];
                $embedIframe = '<iframe class="w-full aspect-video rounded-2xl shadow-lg" src="https://www.youtube.com/embed/' . $embedCode . '?autoplay=1&mute=1&enablejsapi=1&playsinline=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
                
                if (strpos($content, 'src="https://www.youtube.com/embed/' . $embedCode) === false) {
                    if (preg_match('/<iframe[^>]*src="https:\/\/www\.youtube\.com\/embed\/[^"]*"[^>]*><\/iframe>/i', $content)) {
                        $content = preg_replace('/<iframe[^>]*src="https:\/\/www\.youtube\.com\/embed\/[^"]*"[^>]*><\/iframe>/i', $embedIframe, $content);
                    }
                }
            }
        }

        // Dynamically append autoplay=1&mute=1&enablejsapi=1&playsinline=1 to any existing YouTube embed iframe
        $content = preg_replace_callback('/src=["\'](https:\/\/www\.youtube\.com\/embed\/[a-zA-Z0-9_-]{11})(?:\?[^"\']*)?["\']/i', function($m) {
            return 'src="' . $m[1] . '?autoplay=1&mute=1&enablejsapi=1&playsinline=1"';
        }, $content);

        // 10. Inject CSS performance optimizations and Scroll Reveal rules for 60fps smooth scrolling
        $smoothScrollCss = '
    <style>
        html {
            scroll-behavior: smooth !important;
            -webkit-overflow-scrolling: touch !important;
        }
        body {
            -webkit-font-smoothing: antialiased !important;
            -moz-osx-font-smoothing: grayscale !important;
            text-rendering: optimizeLegibility !important;
            overflow-x: hidden !important;
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
    </style>';
        $content = str_replace('</head>', $smoothScrollCss . "\n</head>", $content);

        // 11. Auto unmute YouTube video sound on first interaction with passive, unbinding listeners
        $unmuteScript = '
    <script>
        (function() {
            var events = ["touchstart", "touchmove", "scroll", "wheel", "click", "keydown"];
            function unmuteVideos() {
                events.forEach(function(evt) {
                    window.removeEventListener(evt, unmuteVideos, { passive: true });
                    document.removeEventListener(evt, unmuteVideos, { passive: true });
                });
                var iframes = document.querySelectorAll("iframe[src*=\'youtube.com/embed\']");
                iframes.forEach(function(iframe) {
                    if (iframe.contentWindow) {
                        iframe.contentWindow.postMessage(\'{"event":"command","func":"unMute","args":""}\', \'*\');
                        iframe.contentWindow.postMessage(\'{"event":"command","func":"setVolume","args":[100]}\', \'*\');
                    }
                });
            }
            events.forEach(function(evt) {
                window.addEventListener(evt, unmuteVideos, { passive: true, once: true });
                document.addEventListener(evt, unmuteVideos, { passive: true, once: true });
            });
        })();
    </script>';
        $content = str_replace('</body>', $unmuteScript . "\n</body>", $content);

        // 12. Dynamically inject Review Image Lightbox Modal for existing landing pages
        if (strpos($content, 'review-image-modal') === false) {
            $lightboxModalHtml = '
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
        });
    </script>';
            $content = str_replace('</body>', $lightboxModalHtml . "\n</body>", $content);
        }

        // All elements guaranteed 100% visible

        return response($content)
            ->header('Content-Type', 'text/html');
    }

    /**
     * Place order from landing page COD form
     */
    public function placeOrder(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|regex:/^(?:\+?88)?01[3-9]\d{8}$/',
            'address' => 'required|string|max:500',
            'shipping_location' => 'nullable|string|in:inside,outside',
            'landing_page_id' => 'nullable|integer|exists:chatbot_landing_pages,id',
        ], [
            'name.required' => 'অনুগ্রহ করে আপনার নাম লিখুন।',
            'mobile.required' => 'অনুগ্রহ করে আপনার মোবাইল নম্বর লিখুন।',
            'mobile.regex' => 'অনুগ্রহ করে একটি সঠিক ১১ ডিজিটের মোবাইল নম্বর লিখুন।',
            'address.required' => 'অনুগ্রহ করে আপনার ডেলিভারি ঠিকানা লিখুন।',
        ]);

        $product = Product::published()->findOrFail($request->product_id);
        $quantity = 1; // Default quantity is 1 for direct landing page purchase
        $variantId = 0; // Default variant is 0

        // Resolve product variant
        if ($product->product_type == 2) {
            if (!$request->variant || !is_array($request->variant)) {
                $notify[] = ['error', 'অনুগ্রহ করে পণ্যটির সাইজ বা কালার সিলেক্ট করুন।'];
                return back()->withNotify($notify)->withInput();
            }
            $attributeValuesJson = prepareAttributeValues($request->variant);
            $variant = ProductVariant::where('product_id', $product->id)
                ->published()
                ->where('attribute_values', $attributeValuesJson)
                ->first();
            if (!$variant) {
                $notify[] = ['error', 'দুঃখিত, এই ভ্যারিয়েন্টটি এই মুহূর্তে উপলব্ধ নেই।'];
                return back()->withNotify($notify)->withInput();
            }
            $variantId = $variant->id;
        }

        // Check stock
        if ($product->track_inventory) {
            $stockQuantity = $product->inStock(null);
            if ($quantity > $stockQuantity) {
                $notify[] = ['error', 'দুঃখিত, এই পণ্যটি এই মুহূর্তে স্টকে নেই।'];
                return back()->withNotify($notify)->withInput();
            }
        }

        // Get/Create Guest user or authenticate
        $userId = auth()->id() ?? 0;
        $guestId = null;

        if ($userId === 0) {
            $cleanMobile = preg_replace('/[^0-9]/', '', $request->mobile);
            // Bangladesh mobile is 11 digits, extract last 11 digits
            if (strlen($cleanMobile) > 11) {
                $cleanMobile = substr($cleanMobile, -11);
            }

            $guestEmail = 'guest_landing_' . session()->getId() . '@vayromart.local';
            $guest = Guest::where('mobile', $cleanMobile)->first();
            if (!$guest) {
                $guest = new Guest();
                $guest->email = $guestEmail;
                $guest->mobile = $cleanMobile;
                $guest->session_id = session()->getId();
                $guest->dial_code = '880';
                $guest->country_code = 'BD';
                $guest->country_name = 'Bangladesh';
                $guest->save();
            }
            $guestId = $guest->id;
            session()->put('guest_user_data', $guest);
        }

        // Price calculation
        $price = null;
        $discount = 0;
        if ($request->landing_page_id) {
            $landingPage = ChatbotLandingPage::find($request->landing_page_id);
            if ($landingPage && isset($landingPage->design_settings['custom_price']) && !empty($landingPage->design_settings['custom_price'])) {
                $price = floatval($landingPage->design_settings['custom_price']);
                $regPrice = !empty($landingPage->design_settings['custom_regular_price']) ? floatval($landingPage->design_settings['custom_regular_price']) : ($product->regular_price > $price ? $product->regular_price : round($price * 1.35));
                $discount = $regPrice - $price;
            }
        }

        if ($price === null) {
            $prices = $product->prices(null);
            $price = $prices->sale_price;
            $discount = $prices->regular_price - $prices->sale_price;
        }

        $subtotal = $price * $quantity;
        $shippingCharge = ($request->shipping_location === 'outside') ? 130.00 : 80.00;

        if ($request->landing_page_id) {
            $landingPage = ChatbotLandingPage::find($request->landing_page_id);
            if ($landingPage && isset($landingPage->design_settings['free_delivery']) && $landingPage->design_settings['free_delivery'] === 'free') {
                $shippingCharge = 0.00;
            }
        }

        $totalAmount = $subtotal + $shippingCharge;

        // Generate unique Order Number
        $prefix = 'OID-';
        $last = Order::max('id') + 1;
        $formattedLast = str_pad($last, 5, '0', STR_PAD_LEFT);
        $orderNumber = $prefix . $formattedLast;

        // Create Order
        $order = new Order();
        $order->order_number = $orderNumber;
        $order->user_id = $userId;
        $order->guest_id = $guestId;

        $names = explode(' ', trim($request->name), 2);
        $firstName = $names[0] ?? '';
        $lastName = $names[1] ?? '';

        $shippingAddressObj = [
            'firstname' => $firstName,
            'lastname' => $lastName,
            'mobile' => $request->mobile,
            'email' => $userId ? auth()->user()->email : $guest->email,
            'city' => 'Dhaka',
            'state' => 'Dhaka',
            'zip' => '1000',
            'country_code' => 'BD',
            'dial_code' => '880',
            'country' => 'Bangladesh',
            'address' => $request->address,
        ];
        $order->shipping_address = (object)$shippingAddressObj;
        $order->shipping_method_id = 1; // Standard Delivery
        $order->shipping_charge = $shippingCharge;
        $order->is_cod = 1; // Cash on delivery
        $order->payment_status = 0; // Not Paid
        $order->status = 0; // Pending
        $order->subtotal = $subtotal;
        $order->total_amount = $totalAmount;
        $order->save();

        // Create Order Details
        $orderDetail = new OrderDetail();
        $orderDetail->order_id = $order->id;
        $orderDetail->product_id = $product->id;
        $orderDetail->product_variant_id = $variantId;
        $orderDetail->quantity = $quantity;
        $orderDetail->price = $price;
        $orderDetail->discount = $discount;
        $orderDetail->save();

        // Deduct stock and update log
        if ($product->track_inventory) {
            $product->in_stock -= $quantity;
            $product->save();

            $desc = "Sold {$quantity} product(s) via AI Landing Page";
            $productManager = new ProductManager();
            $productManager->createStockLog($product, $quantity, $desc, null, '-', $order->id);
        }

        // Send Facebook Conversions API (CAPI) Purchase Event
        sendFbCapiEvent('Purchase', [
            'value' => $totalAmount,
            'content_ids' => [(string)$product->id],
            'content_type' => 'product',
            'num_items' => $quantity
        ], [
            'name' => $request->name,
            'phone' => $request->mobile,
            'email' => $shippingAddressObj['email'] ?? null
        ]);

        // Send Admin notification
        try {
            $adminNotification = new AdminNotification();
            $adminNotification->title = 'New order #' . $order->order_number . ' has been created via AI Landing Page';
            $adminNotification->click_url = urlPath('admin.order.index') . '?search=' . $order->order_number;
            $adminNotification->save();
        } catch (\Exception $e) {}

        // Show a premium order success page
        return view('templates.basic.landing_success', compact('order', 'product', 'totalAmount'));
    }
}
