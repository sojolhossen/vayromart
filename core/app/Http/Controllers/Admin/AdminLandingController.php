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
     * Store/Update manual landing page
     */
    public function generate(Request $request)
    {
        $request->validate([
            'id' => 'nullable|integer|exists:chatbot_landing_pages,id',
            'product_id' => 'required|integer|exists:products,id',
            'title' => 'required|string|max:255',
            'headline' => 'required|string|max:255',
            'subtitle' => 'required|string|max:500',
            'bullets' => 'required|string',
            'description' => 'required|string',
            'image_url' => 'nullable|url',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'custom_price' => 'nullable|numeric|min:0',
            'custom_regular_price' => 'nullable|numeric|min:0',
            'video_url' => 'nullable|url',
            'reviewer_name_1' => 'nullable|string',
            'reviewer_comment_1' => 'nullable|string',
            'reviewer_name_2' => 'nullable|string',
            'reviewer_comment_2' => 'nullable|string',
            'reviewer_name_3' => 'nullable|string',
            'reviewer_comment_3' => 'nullable|string',
        ]);

        $product = Product::published()->findOrFail($request->product_id);

        $imageUrl = $request->image_url;
        if ($request->hasFile('image_file')) {
            try {
                $imageName = fileUploader($request->file('image_file'), 'assets/images/landing');
                $imageUrl = asset('assets/images/landing/' . $imageName);
            } catch (\Exception $e) {
                \Log::error("Image Upload Error: " . $e->getMessage());
            }
        }

        // Merge uploaded image URL and prices into settings array
        $settings = $request->all();
        $settings['image_url'] = $imageUrl;
        unset($settings['image_file']); // File object cannot be serialized

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
        $title = e($data['title']);
        $headline = e($data['headline']);
        $subtitle = e($data['subtitle']);
        $videoUrl = $data['video_url'] ?? '';
        $description = $data['description'] ?? '';
        $imageUrl = ($data['image_url'] ?? '') ?: $product->mainImage(false);
        
        $baseColor = '#' . (gs('base_color') ?: '4634ff');

        $videoEmbedHtml = '';
        if ($videoUrl) {
            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^\"&?\/ ]{11})/', $videoUrl, $match)) {
                $embedCode = $match[1];
                $videoEmbedHtml = '<iframe class="w-full aspect-video rounded-2xl shadow-lg" src="https://www.youtube.com/embed/' . $embedCode . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
            }
        }

        $bullets = array_filter(array_map('trim', explode("\n", $data['bullets'])));
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

            <div class="flex flex-col sm:flex-row gap-4 max-w-lg">
                <a href="#checkout-form" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xl font-bold px-8 py-4 rounded-full transition-all duration-300 shadow-lg text-center flex items-center justify-center gap-3 pulsing-btn">
                    <i class="fas fa-hand-pointer"></i>
                    <span>অর্ডার করতে ফর্মটি পূরণ করুন</span>
                </a>
            </div>
        </div>

        <div class="lg:col-span-5 flex flex-col justify-center">
            ' . ($videoEmbedHtml ?: '<img src="' . $imageUrl . '" alt="' . $title . '" class="w-full rounded-2xl shadow-2xl border border-gray-100 hover:scale-[1.02] transition-all duration-300 object-cover aspect-square">') . '
            
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
            
            ' . ($videoEmbedHtml ? '<div class="mb-12"><img src="' . $imageUrl . '" alt="' . $title . '" class="w-full max-w-xl mx-auto rounded-2xl shadow-lg border border-gray-100 object-cover aspect-video"></div>' : '') . '

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
                                <option value="inside" data-charge="80">ঢাকা সিটির ভেতরে (80 BDT)</option>
                                <option value="outside" data-charge="130">ঢাকা সিটির বাইরে (130 BDT)</option>
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
                        <span class="font-bold text-gray-600">ডেলিভারি চার্জ: <span id="delivery_charge_val">80 BDT</span></span>
                        <span class="font-black text-indigo-600 text-xl">সর্বমোট বিল: <span id="total_bill_val">' . ($price + 80) . ' BDT</span></span>
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

    <script>
        document.getElementById("shipping_location").addEventListener("change", function() {
            var charge = parseInt(this.options[this.selectedIndex].getAttribute("data-charge"));
            var basePrice = ' . $price . ';
            document.getElementById("delivery_charge_val").innerText = charge + " BDT";
            document.getElementById("total_bill_val").innerText = (basePrice + charge) + " BDT";
        });
    </script>

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
