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
     * Generate landing page HTML/CSS using AI
     */
    public function generate(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'style' => 'required|string|in:Modern,Clean,Minimalist,Corporate,Dark Mode,Elegant',
            'focus_keyword' => 'nullable|string|max:100',
            'extra_instructions' => 'nullable|string|max:500',
        ]);

        $product = Product::published()->findOrFail($request->product_id);
        
        // 1. Get chatbot settings for AI API credentials
        $general = gs();
        $chatbotSettings = [];
        if ($general->chatbot_settings) {
            $chatbotSettings = is_string($general->chatbot_settings) 
                ? json_decode($general->chatbot_settings, true) 
                : (array)$general->chatbot_settings;
        }

        $activeProvider = $chatbotSettings['active_provider'] ?? 'gemini';
        $apiKey = $chatbotSettings['api_key'][$activeProvider] ?? '';
        $modelName = $chatbotSettings['model_name'][$activeProvider] ?? '';
        $customUrl = $chatbotSettings['custom_url'] ?? '';

        if (empty($apiKey)) {
            $notify[] = ['error', 'AI configuration is missing API keys. Please configure the Chatbot AI settings first.'];
            return back()->withNotify($notify);
        }

        // 2. Prep product context details
        $price = $product->sale_price ?: $product->regular_price;
        $summary = strip_tags(html_entity_decode($product->summary ?? $product->meta_description ?? ''));
        $description = strip_tags(html_entity_decode($product->description ?? ''));
        $imageUrl = $product->mainImage(false);
        $shopUrl = url('/');
        $checkoutUrl = route('landing.checkout');
        $productUrl = route('product.detail', $product->slug);
        $csrfToken = csrf_token();

        // Calculate a dummy regular price if regular price is same as price to show discounts
        $regularPrice = $product->regular_price > $price ? $product->regular_price : round($price * 1.35);
        $discountAmount = $regularPrice - $price;

        // 3. Construct AI Prompt
        $systemInstructions = "You are a World-Class E-commerce Conversion Rate Optimization (CRO) expert, premium Web Designer, and expert copywriter.
Your goal is to write a highly persuasive, visually stunning, fully responsive standalone HTML landing page for the product provided.
The landing page must look like a premium, top-tier Shopify / custom-built landing page that immediately wows the visitor, establishes strong trust, and drives conversions.

Design Guidelines:
- The design style selected by user: {$request->style}.
- Use clean, modern Tailwind CSS for all layouts and styles. Inject the CDN: <script src=\"https://cdn.tailwindcss.com\"></script>.
- Inject Font Awesome for gorgeous vector icons: <link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css\">.
- Use the Google Font 'Poppins' or 'Inter' for premium typography. Apply class='font-sans' or appropriate styling.
- Layout sections:
  1. Sticky Navigation Header: Branding logo ('Vayromart'), features / reviews / specs anchor links, and a prominent \"এখনই অর্ডার করুন\" (Order Now) button linking directly to '{$productUrl}'.
  2. Hero Section:
     - Catchy benefit-driven headline in bold gradient text (e.g. bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent).
     - Subheadline highlighting target audience's core desire.
     - Urgent CTA / Order buttons (e.g. \"এখনই কিনুন\", \"অর্ডার করুন\", \"Buy Now\"): All main CTA and order buttons throughout the page must link directly to the main store product details page URL: '{$productUrl}' (e.g. <a href=\"{$productUrl}\" class=\"...\">এখনই অর্ডার করুন</a>). Do NOT use javascript scrolling or anchor tags pointing to the checkout form for these main CTA buttons. They must open the main product page '{$productUrl}'.
     - Visual trust badges (e.g. Cash on Delivery, Fast Shipping in 24-72 hours, 7-Day Replacement Warranty).
     - PRODUCT IMAGE: Display the actual product image. You MUST use the exact URL provided in the context: '{$imageUrl}'. Embed it as: <img src=\"{$imageUrl}\" alt=\"{$product->name}\" class=\"w-full rounded-2xl shadow-2xl object-cover hover:scale-105 transition-all duration-500 border border-gray-100\">. Do NOT use SVG placeholder icons, mock placeholders, or empty image containers. The actual product image must be clearly visible.
     - Price block: Display original price ({$regularPrice} BDT with line-through) and current sale price ({$price} BDT) in large bold digits. Show a badge stating \"Save {$discountAmount} BDT!\" or discount percentage.
  3. Problem vs Solution: Highlight pain points of the target audience and how this product solves them with interactive card components.
  4. Features & Benefits Grid: Detailed list of features with colorful background icons (Font Awesome) and grid formatting.
  5. Technical Specifications: A clean, sleek table summarizing specifications.
  6. Social Proof / Reviews: 3 realistic positive customer testimonials with 5-star rating icons, avatars with initials, and buyer badges (e.g. \"Verified Buyer\").
  7. FAQ Section: Accordion panels answering questions about delivery, warranty, and return policies.
  8. Cash on Delivery (COD) Checkout Form:
     - Wrap this in a premium-styled checkout card with a prominent ID: id=\"checkout-form\".
     - Include header: \"অর্ডার করতে ফর্মটি পূরণ করুন\" and a trust notice: \"ডেলিভারি ম্যানের কাছ থেকে প্রোডাক্ট বুঝে পেয়ে টাকা পরিশোধ করুন\".
     - Form must submit to: '{$checkoutUrl}' via POST method.
     - Include a hidden input for CSRF token: <input type=\"hidden\" name=\"_token\" value=\"{$csrfToken}\">
     - Include a hidden input for product_id: <input type=\"hidden\" name=\"product_id\" value=\"{$product->id}\">
     - Inputs styled with premium borders and focus rings:
       - Name: <input type=\"text\" name=\"name\" required placeholder=\"আপনার সম্পূর্ণ নাম\" class=\"w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all\">
       - Mobile: <input type=\"tel\" name=\"mobile\" required placeholder=\"১১ ডিজিটের মোবাইল নম্বর\" class=\"w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all\">
       - Address: <textarea name=\"address\" required placeholder=\"ডেলিভারি ঠিকানা (জেলা, থানা, বাসা নং/গ্রাম)\" class=\"w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all\" rows=\"3\"></textarea>
     - Submit button: Pulsing animation or gradient hover. Text: \"অর্ডার কনফার্ম করুন (ক্যাশ অন ডেলিভারি)\".

Product details to use:
- Name: {$product->name}
- Price: {$price} BDT
- Shipping Charge: Standard 80 BDT
- Total Amount: " . ($price + 80) . " BDT (Cash on Delivery)
- Image URL: {$imageUrl}
- Shop URL: {$shopUrl}
- Product Page URL: {$productUrl}
- Additional Info: {$summary}
- Description context: {$description}

Optional settings:
" . ($request->focus_keyword ? "- Focus Keyword to optimize for SEO: {$request->focus_keyword}" : "") . "
" . ($request->extra_instructions ? "- Special request / instructions: {$request->extra_instructions}" : "") . "

Copywriting Rules:
- Write standard, persuasive, and engaging Bengali (বাংলা) copy, keeping model names or technical terms in English (Bangla-English mix).
- ALWAYS write numbers, phone numbers, prices, and specs in standard English digits (0-9) instead of Bengali digits (০-৯) (e.g. '1400 BDT', '01310997902').
- Do NOT greet the user or append greetings, just write the page content.

OUTPUT RULE: Output ONLY the raw HTML code starting with <!DOCTYPE html>. Do NOT wrap the code in markdown blocks (e.g. do NOT include ```html ... ```). Do not include any reasoning, notes, or commentary. Only return valid, executable HTML content.";

        try {
            // Call AI helper (we pass an empty chat history since it's a single one-shot generation prompt)
            $chatHistory = [
                ['sender' => 'user', 'message' => "Generate the landing page HTML code for the product: {$product->name}"]
            ];
            
            $responseText = AiService::sendMessage($activeProvider, $apiKey, $modelName, $systemInstructions, $chatHistory, $customUrl);
            
            $html = trim($responseText);
            
            // Clean up any potential markdown wrappers if returned by sensitive LLMs
            if (preg_match('/^```html(.*?)```$/is', $html, $matches)) {
                $html = trim($matches[1]);
            } elseif (preg_match('/^```(.*?)```$/is', $html, $matches)) {
                $html = trim($matches[1]);
            }

            if (empty($html) || stripos($html, '<!DOCTYPE') === false && stripos($html, '<html') === false) {
                $notify[] = ['error', 'AI generated invalid HTML output. Please try again.'];
                return back()->withNotify($notify);
            }

            // 4. Generate unique slug
            $slug = Str::slug($product->name);
            $baseSlug = $slug;
            $count = 1;
            while (ChatbotLandingPage::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $count;
                $count++;
            }

            // 5. Store record
            ChatbotLandingPage::create([
                'product_id' => $product->id,
                'slug' => $slug,
                'title' => 'Landing Page - ' . $product->name,
                'content' => $html,
                'design_settings' => [
                    'style' => $request->style,
                    'focus_keyword' => $request->focus_keyword,
                    'extra_instructions' => $request->extra_instructions,
                ]
            ]);

            $notify[] = ['success', 'AI Landing Page generated successfully!'];
            return back()->withNotify($notify)->with('generated_slug', $slug);

        } catch (\Exception $e) {
            \Log::error("AI Landing Page Generation Error: " . $e->getMessage());
            $notify[] = ['error', 'Generation failed: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Delete generated landing page
     */
    public function destroy($id)
    {
        $landingPage = ChatbotLandingPage::findOrFail($id);
        $landingPage->delete();

        $notify[] = ['success', 'AI Landing Page deleted successfully'];
        return back()->withNotify($notify);
    }
}
