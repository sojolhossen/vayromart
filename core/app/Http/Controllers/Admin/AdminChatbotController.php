<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\ChatbotKnowledge;
use Illuminate\Http\Request;

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
}
