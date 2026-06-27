<?php

namespace App\Lib;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    /**
     * Send chat messages to the configured AI provider.
     * 
     * @param string $provider
     * @param string $apiKey
     * @param string $model
     * @param string $systemInstructions
     * @param array $chatHistory Array of messages like [['role' => 'user'/'bot', 'message' => '...']]
     * @param string|null $customUrl
     * @return string
     */
    public static function sendMessage($provider, $apiKey, $model, $systemInstructions, $chatHistory, $customUrl = null)
    {
        if (empty($apiKey)) {
            throw new \Exception("API key is not configured for the selected provider.");
        }

        switch ($provider) {
            case 'gemini':
                return self::callGemini($apiKey, $model, $systemInstructions, $chatHistory);
            case 'openai':
                return self::callOpenAiCompatible('https://api.openai.com/v1/chat/completions', $apiKey, $model ?: 'gpt-4o-mini', $systemInstructions, $chatHistory);
            case 'grok':
                return self::callOpenAiCompatible('https://api.x.ai/v1/chat/completions', $apiKey, $model ?: 'grok-beta', $systemInstructions, $chatHistory);
            case 'nvidia':
                return self::callOpenAiCompatible('https://integrate.api.nvidia.com/v1/chat/completions', $apiKey, $model ?: 'nvidia/llama-3.1-nemotron-70b-instruct', $systemInstructions, $chatHistory);
            case 'custom':
                $url = $customUrl ?: 'https://api.openai.com/v1/chat/completions';
                return self::callOpenAiCompatible($url, $apiKey, $model ?: 'default', $systemInstructions, $chatHistory);
            default:
                throw new \Exception("Unsupported provider selected.");
        }
    }

    /**
     * Call Google Gemini API
     */
    private static function callGemini($apiKey, $model, $systemInstructions, $chatHistory)
    {
        $model = $model ?: 'gemini-1.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        // Format history for Gemini API
        $contents = [];
        foreach ($chatHistory as $msg) {
            $role = $msg['sender'] === 'user' ? 'user' : 'model';
            $contents[] = [
                'role' => $role,
                'parts' => [
                    ['text' => $msg['message']]
                ]
            ];
        }

        $payload = [
            'contents' => $contents
        ];

        if (!empty($systemInstructions)) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstructions]
                ]
            ];
        }

        $response = Http::timeout(30)->post($url, $payload);

        if (!$response->successful()) {
            $error = $response->json();
            $errorMessage = $error['error']['message'] ?? 'Unknown Gemini error';
            Log::error("Gemini API Failure: " . json_encode($error));
            throw new \Exception($errorMessage);
        }

        $data = $response->json();
        $responseText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        return $responseText;
    }

    /**
     * Call OpenAI-Compatible chat completion API
     */
    private static function callOpenAiCompatible($url, $apiKey, $model, $systemInstructions, $chatHistory)
    {
        $messages = [];

        // Add system instructions if provided
        if (!empty($systemInstructions)) {
            $messages[] = [
                'role' => 'system',
                'content' => $systemInstructions
            ];
        }

        // Add chat history
        foreach ($chatHistory as $msg) {
            $role = $msg['sender'] === 'user' ? 'user' : 'assistant';
            $messages[] = [
                'role' => $role,
                'content' => $msg['message']
            ];
        }

        $payload = [
            'model' => $model,
            'messages' => $messages
        ];

        // If DeepSeek model or Nvidia API is used, inject parameters (like turning off reasoning thinking for instant chat responses)
        if (stripos($model, 'deepseek') !== false || stripos($url, 'nvidia') !== false) {
            $payload['extra_body'] = [
                'chat_template_kwargs' => [
                    'thinking' => false
                ]
            ];
            $payload['chat_template_kwargs'] = [
                'enable_thinking' => true,
                'thinking' => false
            ];
            $payload['temperature'] = 1.0;
            $payload['top_p'] = 0.95;
            $payload['max_tokens'] = 4096;
        }

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json'
            ])
            ->post($url, $payload);

        if (!$response->successful()) {
            $error = $response->json();
            $errorMessage = $error['error']['message'] ?? $response->body() ?: 'Unknown OpenAI-Compatible error';
            Log::error("OpenAI-Compatible API Failure at {$url}: " . json_encode($error));
            throw new \Exception($errorMessage);
        }

        $data = $response->json();
        $responseText = $data['choices'][0]['message']['content'] ?? '';

        return $responseText;
    }
}
