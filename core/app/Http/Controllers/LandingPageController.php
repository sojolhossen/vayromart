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

        // 2. Replace the old scaling & blinking pulse animation with a modern glowing shadow pulse in brand color #f2532c
        $content = preg_replace('/@keyframes pulse-ring\s*\{[^}]*\}/is', '@keyframes pulse-glow {
            0% { box-shadow: 0 0 0 0 rgba(242, 83, 44, 0.6); }
            70% { box-shadow: 0 0 0 16px rgba(242, 83, 44, 0); }
            100% { box-shadow: 0 0 0 0 rgba(242, 83, 44, 0); }
        }', $content);

        $content = preg_replace('/\.pulsing-btn\s*\{[^}]*\}/is', '.pulsing-btn {
            animation: pulse-glow 2s infinite;
        }', $content);

        // Inject #f2532c Main Brand Primary Color & Dual Marquee Keyframes override into head
        $brandColorOverrideCss = '
    <style id="brand-main-color-theme">
        :root {
            --primary-color: #f2532c !important;
            --primary-hover: #de3812 !important;
            --primary-light: #fff0ed !important;
        }
        .bg-primary, .bg-emerald-600, .bg-emerald-500, .bg-teal-600, .bg-indigo-600 {
            background-color: #f2532c !important;
        }
        .hover\:bg-emerald-600:hover, .hover\:bg-emerald-700:hover, .hover\:bg-teal-700:hover {
            background-color: #de3812 !important;
        }
        .text-primary, .text-emerald-600, .text-emerald-500, .text-teal-600 {
            color: #f2532c !important;
        }
        .border-primary, .border-emerald-500, .border-emerald-600 {
            border-color: #f2532c !important;
        }
        .pulsing-btn {
            background: linear-gradient(135deg, #f2532c 0%, #de3812 100%) !important;
            box-shadow: 0 10px 25px -5px rgba(242, 83, 44, 0.4) !important;
        }
        @keyframes marqueeLeft {
            0% { transform: translateX(0%); }
            100% { transform: translateX(-33.3333%); }
        }
        @keyframes marqueeRight {
            0% { transform: translateX(-33.3333%); }
            100% { transform: translateX(0%); }
        }
        .animate-marquee-left {
            animation: marqueeLeft 28s linear infinite !important;
            will-change: transform;
        }
        .animate-marquee-right {
            animation: marqueeRight 28s linear infinite !important;
            will-change: transform;
        }
    </style>';
        $content = str_replace('</head>', $brandColorOverrideCss . "\n</head>", $content);

        // 3. Dynamically replace button styles with the thick, bold #f2532c brand gradient styling
        $content = str_replace(
            'from-emerald-500 to-teal-600',
            'from-[#f2532c] to-[#d83d16]',
            $content
        );
        $content = str_replace(
            'hover:from-emerald-600 hover:to-teal-700',
            'hover:from-[#d83d16] hover:to-[#ba2a0b]',
            $content
        );
        $content = str_replace(
            'bg-emerald-600 hover:bg-emerald-700',
            'bg-[#f2532c] hover:bg-[#d83d16]',
            $content
        );

        // Make order buttons thicker, bolder and larger
        $content = str_replace(
            'py-4 rounded-full',
            'py-5 sm:py-6 px-10 text-2xl sm:text-3xl font-black rounded-2xl shadow-2xl ring-4 ring-[#fff0ed]',
            $content
        );
        $content = str_replace(
            'py-4.5 rounded-2xl',
            'py-5 sm:py-6 px-10 text-2xl sm:text-3xl font-black rounded-2xl shadow-2xl ring-4 ring-[#fff0ed]',
            $content
        );
        $content = str_replace(
            'py-4 rounded-xl',
            'py-5 sm:py-6 px-10 text-2xl sm:text-3xl font-black rounded-2xl shadow-2xl ring-4 ring-[#fff0ed]',
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

        $content = str_replace(
            '৭ দিনের রিফান্ড/এক্সচেঞ্জ সুবিধা পাবেন',
            '২৪ ঘণ্টার রিপ্লেসমেন্ট ও রিফান্ড/এক্সচেঞ্জ সুবিধা পাবেন',
            $content
        );
        $content = str_replace(
            '৭ দিনের রিপ্লেসমেন্ট',
            '২৪ ঘণ্টার রিপ্লেসমেন্ট',
            $content
        );
        $content = str_replace(
            '7 days replacement',
            '24 hours replacement',
            $content
        );
        $content = str_replace(
            '7 Days Replacement',
            '24 Hours Replacement',
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

        // 13. Dynamically convert any Review Carousel or Review Grid into Dual-Row Marquee Slider
        $content = preg_replace_callback('/<div class="review-carousel-wrapper[^"]*">([\s\S]*?)<\/div>\s*<\/div>\s*<\/div>/i', function($m) {
            $inner = $m[1];
            if (preg_match_all('/<div class="review-slide-item[^"]*">([\s\S]*?)<\/div>\s*<\/div>/i', $inner, $matches)) {
                $items = $matches[0];
            } else if (preg_match_all('/<div class="[^"]*rounded-2xl[^"]*">([\s\S]*?)<\/div>/i', $inner, $matches)) {
                $items = $matches[0];
            } else {
                return $m[0];
            }

            $rawItems = [];
            foreach ($items as $itemHtml) {
                $card = preg_replace('/w-full md:w-\[calc\(33\.333%-16px\)\]/', 'w-[280px] sm:w-[340px]', $itemHtml);
                $card = preg_replace('/review-slide-item/', 'review-card-item', $card);
                $rawItems[] = '<div class="review-card-item flex-none w-[280px] sm:w-[340px]">' . $card . '</div>';
            }

            $row1 = []; $row2 = [];
            foreach ($rawItems as $idx => $html) {
                if ($idx % 2 === 0) $row1[] = $html;
                else $row2[] = $html;
            }
            if (empty($row2)) $row2 = $row1;

            $row1Html = implode('', array_merge($row1, $row1, $row1, $row1));
            $row2Html = implode('', array_merge($row2, $row2, $row2, $row2));

            return '
            <div class="dual-review-marquee-container space-y-6 max-w-full overflow-hidden py-4">
                <div class="marquee-row-wrapper relative overflow-hidden group">
                    <div class="marquee-track-left flex gap-6 w-max animate-marquee-left group-hover:[animation-play-state:paused] active:[animation-play-state:paused]">
                        ' . $row1Html . '
                    </div>
                </div>
                <div class="marquee-row-wrapper relative overflow-hidden group">
                    <div class="marquee-track-right flex gap-6 w-max animate-marquee-right group-hover:[animation-play-state:paused] active:[animation-play-state:paused]">
                        ' . $row2Html . '
                    </div>
                </div>
            </div>';
        }, $content);

        // Also inject Client-Side Dual-Row Marquee script for any remaining review grid elements
        if (strpos($content, 'dual-review-marquee-script') === false) {
            $dualMarqueeScript = '
    <script id="dual-review-marquee-script">
        (function() {
            function initDualMarquee() {
                var reviewSections = document.querySelectorAll("section");
                reviewSections.forEach(function(sec) {
                    var heading = sec.querySelector("h2");
                    if (heading && (heading.textContent.indexOf("গ্রাহকদের") !== -1 || heading.textContent.indexOf("Reviews") !== -1 || heading.textContent.indexOf("মতামত") !== -1)) {
                        var grid = sec.querySelector(".grid, .review-carousel-wrapper");
                        if (grid && !grid.closest(".dual-review-marquee-container")) {
                            var children = Array.from(grid.querySelectorAll(".review-slide-item, .grid > div, img"));
                            if (children.length > 0) {
                                var raw = [];
                                children.forEach(function(child) {
                                    if (child.tagName === "IMG") {
                                        raw.push(\'<div class="review-card-item flex-none w-[280px] sm:w-[340px]"><div class="overflow-hidden rounded-2xl border border-gray-200/80 shadow-sm hover:shadow-xl hover:scale-[1.02] transition-all duration-300 bg-white p-2 h-full">\' + child.outerHTML + \'</div></div>\');
                                    } else {
                                        raw.push(\'<div class="review-card-item flex-none w-[280px] sm:w-[340px] font-sans">\' + child.innerHTML + \'</div>\');
                                    }
                                });

                                var r1 = [], r2 = [];
                                raw.forEach(function(h, idx) {
                                    if (idx % 2 === 0) r1.push(h);
                                    else r2.push(h);
                                });
                                if (r2.length === 0) r2 = r1;

                                var r1Html = r1.concat(r1, r1, r1).join("");
                                var r2Html = r2.concat(r2, r2, r2).join("");

                                var container = document.createElement("div");
                                container.className = "dual-review-marquee-container space-y-6 max-w-full overflow-hidden py-4";
                                container.innerHTML = 
                                    \'<div class="marquee-row-wrapper relative overflow-hidden group">\' +
                                        \'<div class="marquee-track-left flex gap-6 w-max animate-marquee-left group-hover:[animation-play-state:paused] active:[animation-play-state:paused]">\' + r1Html + \'</div>\' +
                                    \'</div>\' +
                                    \'<div class="marquee-row-wrapper relative overflow-hidden group">\' +
                                        \'<div class="marquee-track-right flex gap-6 w-max animate-marquee-right group-hover:[animation-play-state:paused] active:[animation-play-state:paused]">\' + r2Html + \'</div>\' +
                                    \'</div>\';

                                grid.parentNode.replaceChild(container, grid);
                            }
                        }
                    }
                });
            }

            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", initDualMarquee);
            } else {
                initDualMarquee();
            }
        })();
    </script>';
            $content = str_replace('</body>', $dualMarqueeScript . "\n</body>", $content);
        }

        // 14. Dynamically convert any old Roadmap Timeline HTML to the new Alternating Center-Line Timeline layout
        if (strpos($content, 'left-1/2 -translate-x-1/2') === false) {
            $content = preg_replace_callback('/<div class="relative (?:pl-6|pl-8)[^"]*before:absolute[^"]*">([\s\S]*?)<\/div>\s*<\/div>/i', function($m) {
                $innerHtml = $m[1];
                if (preg_match_all('/<div class="relative group">([\s\S]*?)<\/div>\s*<\/div>/i', $innerHtml, $cards)) {
                    $newTimeline = '<div class="relative max-w-4xl mx-auto my-12 py-4">
                        <div class="hidden md:block absolute left-1/2 -translate-x-1/2 top-6 bottom-6 w-1 bg-gray-200 rounded-full"></div>
                        <div class="space-y-8 md:space-y-12">';
                    
                    $validCards = 0;
                    foreach ($cards[0] as $idx => $cardHtml) {
                        $descText = '';
                        if (preg_match('/<p[^>]*>([\s\S]*?)<\/p>/i', $cardHtml, $pMatch)) {
                            $descText = trim(strip_tags($pMatch[1]));
                        }

                        if ($descText) {
                            $validCards++;
                            $stepNum = str_pad($validCards, 2, '0', STR_PAD_LEFT);
                            $isLeft = (($validCards - 1) % 2 === 0);

                            $newTimeline .= '
                            <div class="relative flex flex-col md:flex-row items-center group">
                                <div class="hidden md:flex absolute left-1/2 -translate-x-1/2 top-6 w-10 h-10 rounded-full bg-[#f2532c] text-white font-black text-sm items-center justify-center shadow-md shadow-[#f2532c]/30 border-2 border-white z-20 group-hover:scale-125 transition-transform duration-300">
                                    ' . $stepNum . '
                                </div>
                                <div class="md:hidden flex mb-2 w-8 h-8 rounded-full bg-[#f2532c] text-white font-black text-xs items-center justify-center shadow-md border-2 border-white">
                                    ' . $stepNum . '
                                </div>
                                <div class="w-full md:w-1/2 ' . ($isLeft ? 'md:pr-12 md:text-right' : 'md:ml-auto md:pl-12 md:text-left') . '">
                                    <div class="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-sm hover:shadow-xl hover:border-[#f2532c]/40 transition-all duration-300 hover:-translate-y-1">
                                        <div class="flex items-center gap-2 mb-2 ' . ($isLeft ? 'md:justify-end' : 'md:justify-start') . '">
                                            <span class="text-xs font-black uppercase text-[#f2532c] bg-[#fff0ed] px-2.5 py-0.5 rounded-full border border-[#f2532c]/20">Step ' . $stepNum . '</span>
                                        </div>
                                        <p class="text-slate-800 font-bold text-base sm:text-lg leading-relaxed">' . e($descText) . '</p>
                                    </div>
                                </div>
                            </div>';
                        }
                    }

                    $newTimeline .= '</div></div>';
                    if ($validCards > 0) {
                        return $newTimeline;
                    }
                }
                return $m[0];
            }, $content);
        }

        // 15. Dynamically fix header button and logo visibility
        $content = str_replace(
            'bg-primary hover:brightness-95 text-white font-bold px-6 py-2.5 rounded-full',
            'bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold px-6 py-2.5 rounded-full shadow-md',
            $content
        );
        $content = str_replace(
            'text-2xl font-black text-primary tracking-tight',
            'text-2xl font-black text-emerald-600 tracking-tight',
            $content
        );

        // 15. Dynamically inject Tawk.to Visitor Monitoring (with hidden chat widget icon) for existing landing pages
        if (strpos($content, 'embed.tawk.to') === false) {
            $tawkScript = loadExtension('tawk-chat');
            if ($tawkScript) {
                $hideWidgetScript = '
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
    </style>';
                $content = str_replace('</body>', $tawkScript . "\n" . $hideWidgetScript . "\n</body>", $content);
            }
        }

        // 16. Dynamically inject Quantity Selector for existing landing pages if missing
        // 16. Dynamically inject Quantity Selector HTML & JS for landing pages
        if (strpos($content, 'name="quantity"') === false) {
            $qtySelectorHtml = '
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">পণ্যের পরিমাণ (Quantity) <span class="text-rose-500">*</span></label>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="inline-flex items-center rounded-xl border border-gray-200 bg-white p-1 shadow-sm">
                                <button type="button" id="qty_minus" class="w-10 h-10 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-lg flex items-center justify-center transition-all cursor-pointer">-</button>
                                <input type="number" name="quantity" id="quantity_input" value="1" min="1" max="99" readonly class="w-14 text-center font-bold text-lg text-gray-800 outline-none border-none bg-transparent">
                                <button type="button" id="qty_plus" class="w-10 h-10 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-lg flex items-center justify-center transition-all cursor-pointer shadow-sm">+</button>
                            </div>
                        </div>
                    </div>';

            $content = str_replace('<label class="block text-sm font-bold text-gray-700 mb-2">আপনার নাম', $qtySelectorHtml . "\n" . '<label class="block text-sm font-bold text-gray-700 mb-2">আপনার নাম', $content);
            $content = str_replace('<label class="block text-sm font-bold text-slate-700 mb-2">আপনার নাম', $qtySelectorHtml . "\n" . '<label class="block text-sm font-bold text-slate-700 mb-2">আপনার নাম', $content);
        }

        $qtyScript = '
    <script>
        window.changeQuantity = function(change) {
            var inputs = document.querySelectorAll("input[name=\'quantity\'], #quantity_input");
            inputs.forEach(function(input) {
                var current = parseInt(input.value) || 1;
                var newQty = current + change;
                if (newQty < 1) newQty = 1;
                if (newQty > 99) newQty = 99;
                input.value = newQty;
                input.setAttribute("value", newQty);
            });
            window.updateBillTotal();
        };

        window.updateBillTotal = function() {
            var qtyInput = document.getElementById("quantity_input") || document.querySelector("input[name=\'quantity\']");
            var totalBillElem = document.getElementById("total_bill_val");
            var shippingSelect = document.getElementById("shipping_location");

            if (!qtyInput || !totalBillElem) return;

            var qty = parseInt(qtyInput.value) || 1;
            var unitElem = document.getElementById("unit_price_val");
            var unitPrice = 0;

            if (unitElem) {
                unitPrice = parseFloat(unitElem.innerText.replace(/[^0-9.]/g, "")) || 0;
            }

            var shippingCharge = 0;
            if (shippingSelect && shippingSelect.options && shippingSelect.selectedIndex >= 0) {
                var selectedOpt = shippingSelect.options[shippingSelect.selectedIndex];
                if (selectedOpt && selectedOpt.getAttribute("data-charge") !== null) {
                    shippingCharge = parseFloat(selectedOpt.getAttribute("data-charge")) || 0;
                }
            }

            if (unitPrice > 0) {
                var total = (unitPrice * qty) + shippingCharge;
                totalBillElem.innerText = total + " BDT";
            }
        };

        document.addEventListener("click", function(e) {
            var btn = e.target.closest("#qty_plus, #qty_minus, .qty-plus, .qty-minus");
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                if (btn.id === "qty_plus" || btn.classList.contains("qty-plus")) {
                    window.changeQuantity(1);
                } else if (btn.id === "qty_minus" || btn.classList.contains("qty-minus")) {
                    window.changeQuantity(-1);
                }
            }
        });

        document.addEventListener("DOMContentLoaded", function() {
            var shippingSelect = document.getElementById("shipping_location");
            if (shippingSelect) {
                shippingSelect.addEventListener("change", window.updateBillTotal);
            }
        });
    </script>';

        $content = str_replace('</body>', $qtyScript . "\n</body>", $content);

        // 17. Dynamically remove pattern="[0-9]*" so +880 format is allowed in existing HTML forms
        $content = str_replace('pattern="[0-9]*"', 'inputmode="tel"', $content);

        // 18. Auto-select first variant option by default for existing landing pages
        $autoSelectVariantScript = '
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll("select[name^=\'variant\']").forEach(function(sel) {
                if (sel.options.length > 1 && (sel.value === "" || sel.selectedIndex === 0)) {
                    if (sel.options[0].value === "" && sel.options.length > 1) {
                        sel.selectedIndex = 1;
                    } else if (sel.options.length > 0) {
                        sel.selectedIndex = 0;
                    }
                }
            });
        });
    </script>';
        // 19. Dynamically prevent header logo from opening main site; reload landing page instead
        $content = preg_replace(
            '/<a\s+href=["\']' . preg_quote(url('/'), '/') . '["\']([^>]*)>/i',
            '<a href="javascript:void(0)" onclick="window.location.reload()"$1>',
            $content
        );

        // 20. Dynamically inject Order Progress Modal & Duplicate Order Prevention Script
        $orderProgressModalHtml = '
<!-- Order Progress & Success Modal -->
<div id="order-process-modal" class="fixed inset-0 z-[150] hidden items-center justify-center bg-black/80 backdrop-blur-md p-4 transition-all duration-300 opacity-0 pointer-events-none">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-emerald-100 text-center relative overflow-hidden transform transition-all duration-300 scale-95" id="modal-card">
        
        <!-- Loading State -->
        <div id="order-loading-state" class="py-4">
            <div class="relative w-24 h-24 mx-auto mb-6 flex items-center justify-center">
                <div class="absolute inset-0 rounded-full border-4 border-emerald-100"></div>
                <div class="absolute inset-0 rounded-full border-4 border-emerald-500 border-t-transparent animate-spin"></div>
                <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl font-bold">
                    <i class="fas fa-truck-fast"></i>
                </div>
            </div>

            <h3 class="text-xl sm:text-2xl font-black text-gray-800 mb-2">আপনার অর্ডার প্রসেস করা হচ্ছে...</h3>
            <p class="text-xs font-semibold text-gray-500 mb-6">অনুগ্রহ করে কয়েক সেকেন্ড অপেক্ষা করুন</p>

            <!-- Progress Bar -->
            <div class="w-full bg-gray-100 rounded-full h-3 mb-6 overflow-hidden p-0.5 border border-gray-200">
                <div id="order-progress-bar" class="bg-gradient-to-r from-emerald-500 to-teal-500 h-full rounded-full transition-all duration-500 w-[15%] shadow-sm"></div>
            </div>

            <!-- Steps Checklist -->
            <div class="space-y-3 text-left max-w-xs mx-auto text-xs font-bold">
                <div id="step-1" class="flex items-center gap-3 text-emerald-600 transition-all">
                    <span class="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center text-xs flex-shrink-0"><i class="fas fa-circle-notch animate-spin"></i></span>
                    <span>১. আপনার তথ্য যাচাই করা হচ্ছে...</span>
                </div>
                <div id="step-2" class="flex items-center gap-3 text-gray-400 transition-all">
                    <span class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center text-xs flex-shrink-0"><i class="fas fa-box"></i></span>
                    <span>২. প্রোডাক্ট স্টক চেক করা হচ্ছে...</span>
                </div>
                <div id="step-3" class="flex items-center gap-3 text-gray-400 transition-all">
                    <span class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center text-xs flex-shrink-0"><i class="fas fa-truck"></i></span>
                    <span>৩. ক্যাশ অন ডেলিভারি কনফার্ম হচ্ছে...</span>
                </div>
            </div>
        </div>

        <!-- Success State -->
        <div id="order-success-state" class="hidden py-2">
            <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-4xl mx-auto mb-4 shadow-inner border-2 border-emerald-300">
                <i class="fas fa-check-circle"></i>
            </div>
            
            <span class="inline-block bg-emerald-50 text-emerald-700 font-bold px-3 py-1 rounded-full text-xs border border-emerald-200 mb-2">🎉 অর্ডার সফল হয়েছে!</span>
            <h3 class="text-2xl font-black text-gray-800 mb-1">আপনার অর্ডার সফলভাবে গৃহীত হয়েছে</h3>
            <p class="text-xs font-medium text-gray-500 mb-4">আমাদের প্রতিনিধি শীঘ্রই আপনার সাথে কথা বলবেন।</p>

            <!-- Order Details Box -->
            <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 text-left text-xs space-y-2 mb-6">
                <div class="flex justify-between border-b border-gray-200 pb-2">
                    <span class="text-gray-500 font-semibold">অর্ডার নম্বর:</span>
                    <span id="succ_order_number" class="font-black text-gray-800 text-sm">#OID-XXXXX</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500 font-semibold">নাম:</span>
                    <span id="succ_customer_name" class="font-bold text-gray-800"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500 font-semibold">মোবাইল:</span>
                    <span id="succ_customer_mobile" class="font-bold text-gray-800"></span>
                </div>
                <div class="flex justify-between border-t border-gray-200 pt-2 font-bold text-sm">
                    <span class="text-gray-800">সর্বমোট মূল্য:</span>
                    <span id="succ_total_amount" class="text-emerald-600 font-black text-base">৳ 0</span>
                </div>
            </div>

            <button type="button" id="close-success-modal" class="w-full bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-extrabold py-3.5 rounded-xl shadow-lg transition-all cursor-pointer text-sm">
                ঠিক আছে, ধন্যবাদ!
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var checkoutForm = document.querySelector("form[action*=\'landing/checkout\']") || document.querySelector("#checkout-form form");
    if (!checkoutForm) return;

    checkoutForm.addEventListener("submit", function(e) {
        e.preventDefault();
        
        var submitBtn = checkoutForm.querySelector("button[type=\'submit\']");
        var modal = document.getElementById("order-process-modal");
        var modalCard = document.getElementById("modal-card");
        var progressBar = document.getElementById("order-progress-bar");
        var loadingState = document.getElementById("order-loading-state");
        var successState = document.getElementById("order-success-state");

        var step1 = document.getElementById("step-1");
        var step2 = document.getElementById("step-2");
        var step3 = document.getElementById("step-3");

        // 1. Immediately disable button & prevent duplicate clicks
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add("opacity-50", "cursor-not-allowed");
            submitBtn.innerHTML = \'<i class="fas fa-spinner animate-spin"></i> <span>প্রক্রিয়াকরণ করা হচ্ছে...</span>\';
        }

        // 2. Open Progress Modal
        if (modal) {
            modal.classList.remove("hidden", "pointer-events-none");
            setTimeout(function() {
                modal.classList.remove("opacity-0");
                if (modalCard) modalCard.classList.remove("scale-95");
            }, 10);
        }

        if (progressBar) progressBar.style.width = "35%";

        setTimeout(function() {
            if (progressBar) progressBar.style.width = "65%";
            if (step1) step1.innerHTML = \'<span class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs flex-shrink-0"><i class="fas fa-check"></i></span><span>১. আপনার তথ্য যাচাই সম্পন্ন</span>\';
            if (step2) {
                step2.classList.remove("text-gray-400");
                step2.classList.add("text-emerald-600");
                step2.innerHTML = \'<span class="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center text-xs flex-shrink-0"><i class="fas fa-circle-notch animate-spin"></i></span><span>২. স্টক নিশ্চিত করা হচ্ছে...</span>\';
            }
        }, 500);

        var formData = new FormData(checkoutForm);
        fetch(checkoutForm.action, {
            method: "POST",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": formData.get("_token") || ""
            },
            body: formData
        })
        .then(function(res) {
            return res.json();
        })
        .then(function(data) {
            if (data.status === "success") {
                if (progressBar) progressBar.style.width = "100%";
                if (step2) step2.innerHTML = \'<span class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs flex-shrink-0"><i class="fas fa-check"></i></span><span>২. প্রোডাক্ট স্টক নিশ্চিত</span>\';
                if (step3) {
                    step3.classList.remove("text-gray-400");
                    step3.classList.add("text-emerald-600");
                    step3.innerHTML = \'<span class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs flex-shrink-0"><i class="fas fa-check"></i></span><span>৩. অর্ডার সফলভাবে নথিভুক্ত!</span>\';
                }

                // 3. Clear Form Inputs completely so accidental re-click submits NOTHING!
                checkoutForm.reset();
                var nameInput = checkoutForm.querySelector("input[name=\'name\']");
                var phoneInput = checkoutForm.querySelector("input[name=\'mobile\']");
                var addressInput = checkoutForm.querySelector("textarea[name=\'address\']");
                if (nameInput) nameInput.value = "";
                if (phoneInput) phoneInput.value = "";
                if (addressInput) addressInput.value = "";

                // Fire Facebook Pixel Purchase Event (Client-Side)
                if (typeof fbq !== "undefined") {
                    try {
                        fbq("track", "Purchase", {
                            value: parseFloat(data.total_val) || 0,
                            currency: "BDT",
                            content_name: data.product_name || "Product",
                            content_ids: [String(data.product_id || "")],
                            content_type: "product",
                            num_items: parseInt(data.quantity) || 1
                        }, { eventID: data.event_id || "" });
                    } catch (e) {}
                }

                // Show Success State after 600ms
                setTimeout(function() {
                    if (loadingState) loadingState.classList.add("hidden");
                    if (successState) successState.classList.remove("hidden");

                    document.getElementById("succ_order_number").innerText = "#" + (data.order_number || "");
                    document.getElementById("succ_customer_name").innerText = data.customer_name || "";
                    document.getElementById("succ_customer_mobile").innerText = data.customer_mobile || "";
                    document.getElementById("succ_total_amount").innerText = "৳ " + (data.total_amount || "0");
                }, 600);

            } else {
                alert(data.message || "অর্ডার প্রসেস করতে একটি সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।");
                if (modal) modal.classList.add("hidden", "pointer-events-none", "opacity-0");
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove("opacity-50", "cursor-not-allowed");
                    submitBtn.innerHTML = \'<i class="fas fa-circle-check"></i> <span>অর্ডার নিশ্চিত করুন</span>\';
                }
            }
        })
        .catch(function(err) {
            checkoutForm.submit();
        });
    });

    var closeBtn = document.getElementById("close-success-modal");
    if (closeBtn) {
        closeBtn.addEventListener("click", function() {
            var modal = document.getElementById("order-process-modal");
            if (modal) modal.classList.add("hidden", "pointer-events-none", "opacity-0");
            window.location.reload();
        });
    }
});
</script>';

        $content = str_replace('</body>', $orderProgressModalHtml . "\n</body>", $content);

        return response($content)
            ->header('Content-Type', 'text/html');
    }

    /**
     * Place order from landing page COD form
     */
    public function placeOrder(Request $request)
    {
        // Sanitize mobile input (remove spaces, hyphens, parentheses, trim whitespace)
        if ($request->has('mobile')) {
            $sanitizedMobile = preg_replace('/[^\d+]/', '', trim($request->mobile));
            $request->merge(['mobile' => $sanitizedMobile]);
        }

        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'name' => 'required|string|max:255',
            'mobile' => ['required', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            'address' => 'required|string|max:500',
            'quantity' => 'nullable|integer|min:1|max:99',
            'shipping_location' => 'nullable|string|in:inside,outside',
            'landing_page_id' => 'nullable|integer|exists:chatbot_landing_pages,id',
        ], [
            'name.required' => 'অনুগ্রহ করে আপনার নাম লিখুন।',
            'mobile.required' => 'অনুগ্রহ করে আপনার মোবাইল নম্বর লিখুন।',
            'mobile.regex' => 'অনুগ্রহ করে একটি সঠিক ১১ ডিজিটের মোবাইল নম্বর লিখুন (যেমন: 017XXXXXXXX বা +88017XXXXXXXX)।',
            'address.required' => 'অনুগ্রহ করে আপনার ডেলিভারি ঠিকানা লিখুন।',
        ]);

        $product = Product::published()->findOrFail($request->product_id);
        $quantity = max(1, (int) $request->input('quantity', 1));
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

        // Generate unique Order Number starting with prefix M (e.g. M4637)
        $lastId = Order::max('id') + 1;
        $orderNumber = 'M' . (4636 + $lastId);
        while (Order::where('order_number', $orderNumber)->exists()) {
            $lastId++;
            $orderNumber = 'M' . (4636 + $lastId);
        }

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
        $eventId = 'PURCHASE_' . $order->id . '_' . time();
        sendFbCapiEvent('Purchase', [
            'value' => (float)$totalAmount,
            'currency' => 'BDT',
            'content_ids' => [(string)$product->id],
            'content_name' => $product->name,
            'content_type' => 'product',
            'num_items' => (int)$quantity
        ], [
            'name' => $request->name,
            'phone' => $request->mobile,
            'email' => $shippingAddressObj['email'] ?? null
        ], $eventId);

        // Send Admin notification (which automatically sends detailed Telegram alert via AdminNotification model hook)
        try {
            $adminNotification = new AdminNotification();
            $adminNotification->title = 'New order #' . $order->order_number . ' has been created via AI Landing Page';
            $adminNotification->click_url = urlPath('admin.order.index') . '?search=' . $order->order_number;
            $adminNotification->save();
        } catch (\Exception $e) {}

        // Show a premium order success page or return JSON for AJAX progress modal
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'আপনার অর্ডারটি সফলভাবে সম্পন্ন হয়েছে!',
                'order_number' => $order->order_number,
                'total_amount' => showAmount($totalAmount),
                'total_val' => (float)$totalAmount,
                'product_id' => (string)$product->id,
                'product_name' => $product->name,
                'quantity' => (int)$quantity,
                'event_id' => $eventId,
                'customer_name' => $request->name,
                'customer_mobile' => $request->mobile,
                'shipping_address' => $request->address,
            ]);
        }

        return view('templates.basic.landing_success', compact('order', 'product', 'totalAmount'));
    }
}
