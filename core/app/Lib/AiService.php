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

        // Fix model if empty fallback
        if (empty($model)) {
            $model = 'google/diffusiongemma-26b-a4b-it';
        }

        $payload = [
            'model' => $model,
            'messages' => $messages
        ];

        // Safely add configuration parameters for Nvidia endpoints
        if (stripos($url, 'nvidia') !== false) {
            $payload['temperature'] = 1.00;
            $payload['top_p'] = 0.95;
            $payload['max_tokens'] = 4096;
            
            // Required parameters for google/diffusiongemma model
            if ($model === 'google/diffusiongemma-26b-a4b-it') {
                $payload['chat_template_kwargs'] = [
                    'enable_thinking' => true
                ];
            }
        }

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json'
            ])
            ->post($url, $payload);

        if (!$response->successful()) {
            // Fallback: If 404 model not found occurs, retry with a guaranteed active model
            if ($response->status() === 404 && $model !== 'nvidia/llama-3.1-nemotron-70b-instruct') {
                Log::warning("Nvidia Model {$model} returned 404, retrying with nvidia/llama-3.1-nemotron-70b-instruct");
                $payload['model'] = 'nvidia/llama-3.1-nemotron-70b-instruct';
                $response = Http::timeout(30)
                    ->withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                        'Content-Type' => 'application/json'
                    ])
                    ->post($url, $payload);
            }
        }

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

    /**
     * Download an image from URL, encode to base64, and identify product name using NVIDIA Vision API
     * 
     * @param string $imageUrl
     * @param string $apiKey
     * @return string Product name keywords identified by the AI
     */
    public static function describeImage($imageUrl, $apiKey)
    {
        try {
            // 1. Download image content using Laravel Http Client to bypass allow_url_fopen restrictions
            $imageResponse = Http::timeout(20)->get($imageUrl);
            if (!$imageResponse->successful()) {
                Log::error("Failed to download Facebook image: Code " . $imageResponse->status());
                return '';
            }
            $imageContent = $imageResponse->body();
            if (empty($imageContent)) {
                return '';
            }

            // 2. Base64 encode the image
            $base64Image = base64_encode($imageContent);
            $mimeType = 'image/jpeg'; // Fallback MIME type

            // Detect actual MIME type from image content
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo->buffer($imageContent);
            if ($detectedMime) {
                $mimeType = $detectedMime;
            }

            $dataUri = "data:{$mimeType};base64,{$base64Image}";

            // Try Gemini 1.5/2.5 Flash Vision first as it is free and normally very fast
            $geminiKey = 'AIzaSyBYPLssQKJpdylMrvcFfnXeBfbgMRRWBD4';
            $geminiUrl = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key={$geminiKey}";
            
            $geminiPayload = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => 'Identify the product/item shown in this image. Respond with only the product brand and model or item name in 1 to 3 words. Example: "Hoco Power Bank" or "Tp-link Router". Do not write sentences or explanation.'
                            ],
                            [
                                'inlineData' => [
                                    'mimeType' => $mimeType,
                                    'data' => $base64Image
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            try {
                Log::info("Trying Gemini 2.5 Flash Vision...");
                $response = Http::timeout(4)->post($geminiUrl, $geminiPayload);
                if ($response->successful()) {
                    $data = $response->json();
                    $resultText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $cleanResult = trim(str_replace(['"', "'", '.', "\n"], '', $resultText));
                    if (!empty($cleanResult)) {
                        Log::info("Gemini Vision Identified product: {$cleanResult}");
                        return $cleanResult;
                    }
                } else {
                    Log::warning("Gemini Vision API returned status " . $response->status() . ": " . $response->body());
                }
            } catch (\Exception $geminiEx) {
                Log::warning("Gemini Vision Exception: " . $geminiEx->getMessage());
            }

            // --- Fallback: Try NVIDIA Vision API if Gemini is unavailable ---
            Log::info("Gemini Vision failed/unavailable. Falling back to NVIDIA Vision API...");
            $url = 'https://integrate.api.nvidia.com/v1/chat/completions';
            $payload = [
                'model' => 'meta/llama-3.2-90b-vision-instruct',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Identify the product/item shown in this image. Respond with only the product brand and model or item name in 1 to 3 words. Example: "Hoco Power Bank" or "Tp-link Router". Do not write sentences or explanation.'
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => $dataUri
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 50,
                'temperature' => 0.20,
                'top_p' => 0.70
            ];

            try {
                $response = Http::timeout(6)
                    ->withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                        'Content-Type' => 'application/json'
                    ])
                    ->post($url, $payload);
     
                if ($response->successful()) {
                    $data = $response->json();
                    $resultText = $data['choices'][0]['message']['content'] ?? '';
                    $cleanResult = trim(str_replace(['"', "'", '.', "\n"], '', $resultText));
                    if (!empty($cleanResult)) {
                        Log::info("NVIDIA Vision Identified product: {$cleanResult}");
                        return $cleanResult;
                    }
                }
            } catch (\Exception $nvEx) {
                Log::error("NVIDIA Vision Fallback Request failed: " . $nvEx->getMessage());
            }
        } catch (\Exception $e) {
            Log::error("AiService describeImage Exception: " . $e->getMessage());
        }

        return '';
    }

    /**
     * Intelligently extract the product brand/model name from a customer's message using AI
     * 
     * @param string $messageText
     * @param string $apiKey
     * @return string Extracted product brand/model or empty string
     */
    public static function extractProductQuery($messageText, $apiKey)
    {
        try {
            $url = 'https://integrate.api.nvidia.com/v1/chat/completions';
            $payload = [
                'model' => 'google/diffusiongemma-26b-a4b-it',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Identify the product brand name, model number, or catalog item the user is asking about in the following message. Respond with ONLY the extracted brand and model or product name. Do NOT write full sentences, thoughts, explanations, or quotes. If the user is not asking about any product, respond with 'none'.\n\nUser Message: \"{$messageText}\""
                    ]
                ],
                'max_tokens' => 50,
                'temperature' => 0.10,
                'top_p' => 0.70,
                'chat_template_kwargs' => ['enable_thinking' => false]
            ];

            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json'
                ])
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $resultText = trim($data['choices'][0]['message']['content'] ?? '');
                $cleanResult = trim(str_replace(['"', "'", '.', "\n"], '', $resultText));
                if (strtolower($cleanResult) !== 'none') {
                    Log::info("AI extracted search query: {$cleanResult}");
                    return $cleanResult;
                }
            }
        } catch (\Exception $e) {
            Log::error("AiService extractProductQuery Exception: " . $e->getMessage());
        }

        return '';
    }
}
