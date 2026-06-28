<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\ChatbotKnowledge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminChatbotController extends Controller
{
    /**
     * Display Unified Dashboard: Settings, Chat Logs, and Knowledge Base
     */
    public function index()
    {
        $pageTitle = 'AI Chatbot Configuration & History';
        
        // Settings
        $general = gs();
        $chatbotSettings = [];
        if ($general->chatbot_settings) {
            $chatbotSettings = is_string($general->chatbot_settings) 
                ? json_decode($general->chatbot_settings, true) 
                : (array)$general->chatbot_settings;
        }
        
        $chatbotEnabled = $general->chatbot_enabled;

        // Conversations / Chat logs
        $conversations = ChatbotConversation::with(['user', 'messages'])
            ->withCount('messages')
            ->orderBy('updated_at', 'desc')
            ->paginate(15, ['*'], 'chat_page');

        // Knowledge Base FAQs
        $knowledges = ChatbotKnowledge::orderBy('created_at', 'desc')
            ->paginate(15, ['*'], 'kb_page');

        return view('admin.setting.chatbot.index', compact(
            'pageTitle', 
            'chatbotSettings', 
            'chatbotEnabled', 
            'conversations', 
            'knowledges'
        ));
    }

    /**
     * Update Chatbot AI settings and keys
     */
    public function update(Request $request)
    {
        $request->validate([
            'bot_name' => 'required|string|max:40',
            'welcome_message' => 'required|string|max:250',
            'active_provider' => 'required|in:gemini,openai,grok,nvidia,custom',
            'system_prompt' => 'nullable|string|max:2000',
            'custom_url' => 'nullable|url|max:255',
        ]);

        $general = gs();
        $general->chatbot_enabled = $request->chatbot_enabled ? 1 : 0;

        $chatbotSettings = [
            'bot_name' => $request->bot_name,
            'welcome_message' => $request->welcome_message,
            'active_provider' => $request->active_provider,
            'api_key' => [
                'gemini' => $request->api_key_gemini,
                'openai' => $request->api_key_openai,
                'grok' => $request->api_key_grok,
                'nvidia' => $request->api_key_nvidia,
                'custom' => $request->api_key_custom,
            ],
            'model_name' => [
                'gemini' => $request->model_name_gemini ?: 'gemini-1.5-flash',
                'openai' => $request->model_name_openai ?: 'gpt-4o-mini',
                'grok' => $request->model_name_grok ?: 'grok-beta',
                'nvidia' => $request->model_name_nvidia ?: 'nvidia/llama-3.1-nemotron-70b-instruct',
                'custom' => $request->model_name_custom ?: 'default',
            ],
            'custom_url' => $request->custom_url,
            'system_prompt' => $request->system_prompt,
        ];

        $general->chatbot_settings = $chatbotSettings;
        $general->save();

        $notify[] = ['success', 'AI Chatbot configuration updated successfully'];
        return back()->withNotify($notify);
    }

    /**
     * Get single conversation transcript logs for AJAX modal
     */
    public function viewLog($id)
    {
        $conversation = ChatbotConversation::with(['user', 'messages' => function ($q) {
            $q->orderBy('created_at', 'asc');
        }])->find($id);

        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found']);
        }

        return response()->json([
            'success' => true,
            'session_id' => $conversation->session_id,
            'ip_address' => $conversation->ip_address,
            'user' => $conversation->user ? $conversation->user->username : 'Guest',
            'messages' => $conversation->messages->map(function ($msg) {
                return [
                    'sender' => $msg->sender,
                    'message' => $msg->message,
                    'time' => $msg->created_at->format('Y-m-d H:i:s'),
                ];
            })
        ]);
    }

    /**
     * Save or update Knowledge Base rules
     */
    public function knowledgeStore(Request $request)
    {
        $request->validate([
            'id' => 'nullable|integer|exists:chatbot_knowledge,id',
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'is_active' => 'required|in:0,1',
        ]);

        if ($request->id) {
            $knowledge = ChatbotKnowledge::findOrFail($request->id);
            $message = 'Knowledge Base FAQ updated successfully';
        } else {
            $knowledge = new ChatbotKnowledge();
            $message = 'Knowledge Base FAQ added successfully';
        }

        $knowledge->question = $request->question;
        $knowledge->answer = $request->answer;
        $knowledge->is_active = $request->is_active;
        $knowledge->save();

        $notify[] = ['success', $message];
        return back()->withNotify($notify);
    }

    /**
     * Delete FAQ rule from Knowledge Base
     */
    public function knowledgeDelete($id)
    {
        $knowledge = ChatbotKnowledge::findOrFail($id);
        $knowledge->delete();

        $notify[] = ['success', 'Knowledge Base FAQ deleted successfully'];
        return back()->withNotify($notify);
    }

    /**
     * Display the Chatbot AI JSON Exporter form
     */
    public function exportForm()
    {
        $pageTitle = 'Chatbot AI JSON Data Exporter';
        
        // List of database tables that are highly relevant to chatbot operations
        $tables = [
            'products' => 'Products (Names, Prices, Stocks, Details)',
            'categories' => 'Product Categories',
            'brands' => 'Product Brands',
            'coupons' => 'Active Discount Coupons',
            'offers' => 'Special Deals & Campaigns',
            'chatbot_knowledges' => 'Custom Knowledge Base FAQs',
            'shipping_methods' => 'Shipping & Delivery Charges',
            'frontends' => 'General Website Info & Policies'
        ];

        return view('admin.setting.chatbot.export', compact('pageTitle', 'tables'));
    }

    /**
     * Process chunked table extraction, run AI conversion, and write to JSON
     */
    public function exportProcess(Request $request)
    {
        $request->validate([
            'tables' => 'required|array',
            'tables.*' => 'string'
        ]);

        $selectedTables = $request->tables;
        $exportedData = [];

        try {
            foreach ($selectedTables as $table) {
                // Read database rows based on selected tables
                if ($table === 'products') {
                    $records = \App\Models\Product::published()
                        ->get(['id', 'name', 'sale_price', 'regular_price', 'in_stock', 'summary', 'meta_description', 'slug'])
                        ->map(function($p) {
                            $price = $p->sale_price ?: $p->regular_price;
                            $p->url = route('product.detail', $p->slug);
                            return [
                                'id' => $p->id,
                                'name' => $p->name,
                                'price' => $price . ' BDT',
                                'stock' => $p->in_stock > 0 ? "{$p->in_stock} items in stock" : "Out of stock",
                                'summary' => strip_tags(html_entity_decode($p->summary ?? $p->meta_description ?? '')),
                                'link' => $p->url
                            ];
                        })->toArray();
                    $exportedData['products'] = $records;
                }
                elseif ($table === 'categories') {
                    $exportedData['categories'] = \App\Models\Category::pluck('name')->toArray();
                }
                elseif ($table === 'brands') {
                    $exportedData['brands'] = \App\Models\Brand::pluck('name')->toArray();
                }
                elseif ($table === 'coupons') {
                    $exportedData['coupons'] = \App\Models\Coupon::where('status', 1)
                        ->get(['coupon_code', 'discount_type', 'coupon_amount', 'minimum_spend'])
                        ->map(function($c) {
                            return [
                                'code' => $c->coupon_code,
                                'discount' => $c->coupon_amount . ($c->discount_type == 1 ? ' BDT' : '%'),
                                'min_purchase' => $c->minimum_spend . ' BDT'
                            ];
                        })->toArray();
                }
                elseif ($table === 'offers') {
                    $exportedData['offers'] = \App\Models\Offer::where('status', 1)
                        ->get(['name', 'amount', 'discount_type'])
                        ->map(function($o) {
                            return [
                                'title' => $o->name,
                                'discount' => $o->amount . ($o->discount_type == 1 ? ' BDT' : '%')
                            ];
                        })->toArray();
                }
                elseif ($table === 'chatbot_knowledges') {
                    $exportedData['chatbot_knowledges'] = \App\Models\ChatbotKnowledge::where('is_active', 1)
                        ->get(['question', 'answer'])
                        ->toArray();
                }
                elseif ($table === 'shipping_methods') {
                    $exportedData['shipping_methods'] = \App\Models\ShippingMethod::all()
                        ->map(function($s) {
                            return [
                                'name' => $s->name,
                                'charge' => $s->charge . ' BDT',
                                'time' => $s->deliver_in
                            ];
                        })->toArray();
                }
                elseif ($table === 'frontends') {
                    $frontendContext = [];
                    // About Us
                    $aboutUs = \App\Models\Frontend::where('data_keys', 'about_us.content')->first();
                    if ($aboutUs && isset($aboutUs->data_values)) {
                        $frontendContext['about_us'] = strip_tags(html_entity_decode($aboutUs->data_values->description ?? ''));
                    }
                    // FAQs
                    $faqs = \App\Models\Frontend::where('data_keys', 'faq_page.content')->first();
                    if ($faqs && isset($faqs->data_values->description)) {
                        $frontendContext['website_faqs'] = strip_tags(html_entity_decode($faqs->data_values->description));
                    }
                    $exportedData['frontends'] = $frontendContext;
                }
            }

            // Direct pure PHP JSON encoding to ensure 100% of rows are saved without truncation
            $finalJsonContent = json_encode($exportedData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            // Save JSON to storage path
            $storageDir = storage_path('app/chatbot');
            if (!file_exists($storageDir)) {
                mkdir($storageDir, 0775, true);
            }
            
            file_put_contents($storageDir . '/data.json', trim($finalJsonContent));
            
            return response()->json([
                'success' => true,
                'message' => 'Chatbot JSON context file generated and saved successfully! All selected records exported.',
                'file_path' => 'storage/app/chatbot/data.json'
            ]);

        } catch (\Exception $e) {
            Log::error("Chatbot Exporter Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error exporting database tables: ' . $e->getMessage()
            ]);
        }
    }
}
