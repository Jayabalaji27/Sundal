<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Setting;
use OpenAI;

class ChatGptController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'prompt' => 'required|string|max:1000',
            'language' => 'string|in:en,es,ar,da,de,fr,he,it,ja,nl,pl,pt,pt-BR,ru,tr,zh',
            'creativity' => 'string|in:low,medium,high',
            'num_results' => 'integer|min:1|max:5',
            'max_length' => 'integer|min:1|max:500'
        ]);

        try {
            $apiKey = Setting::where('key', 'chatgptKey')->value('value');
            $model = Setting::where('key', 'chatgptModel')->value('value') ?? 'gpt-3.5-turbo';

            if (!$apiKey || !str_starts_with(trim($apiKey), 'sk-')) {
                return response()->json([
                    'success' => false,
                    'message' => __('Please set proper configuration for Api Key')
                ], 422);
            }

            $apiKey = trim($apiKey);

            $temperature = (float) $request->input('creativity', 0.7);
            if (is_string($request->input('creativity'))) {
                $temperature = match($request->input('creativity')) {
                    'low' => 0.3,
                    'high' => 0.9,
                    default => 0.7
                };
            }

            $language = $request->input('language', 'en');
            $langText = $language !== 'en' ? "Provide response in " . match($language) {
                'es' => 'Spanish',
                'ar' => 'Arabic',
                'da' => 'Danish',
                'de' => 'German',
                'fr' => 'French',
                'he' => 'Hebrew',
                'it' => 'Italian',
                'ja' => 'Japanese',
                'nl' => 'Dutch',
                'pl' => 'Polish',
                'pt' => 'Portuguese',
                'pt-BR' => 'Brazilian Portuguese',
                'ru' => 'Russian',
                'tr' => 'Turkish',
                'zh' => 'Chinese',
                default => 'English'
            } . " language.\n\n " : "";

            $maxTokens = (int) $request->input('max_length', 150);
            $maxResults = (int) $request->input('num_results', 1);

            $client = OpenAI::client($apiKey);

            $response = $client->chat()->create([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $request->prompt . ' ' . $langText
                    ]
                ],
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
                'n' => $maxResults
            ]);

            if (isset($response->choices)) {
                $text = '';
                $counter = 1;

                if (count($response->choices) > 1) {
                    foreach ($response->choices as $choice) {
                        $text .= $counter . '. ' . trim($choice->message->content) . "\r\n\r\n\r\n";
                        $counter++;
                    }
                } else {
                    $text = $response->choices[0]->message->content;
                }

                return response()->json([
                    'success' => true,
                    'content' => trim($text)
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => __('Text was not generated, please try again')
                ], 422);
            }

        } catch (\Exception $e) {
            \Log::error('ChatGPT API Error: ' . $e->getMessage(), [
                'prompt' => $request->prompt,
                'model' => $model ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            
            // Provide more specific error messages
            $errorMessage = $e->getMessage();
            
            if (str_contains($errorMessage, 'API key')) {
                $errorMessage = __('Invalid API key. Please check your ChatGPT configuration.');
            } elseif (str_contains($errorMessage, 'quota')) {
                $errorMessage = __('API quota exceeded. Please check your ChatGPT usage limits.');
            } elseif (str_contains($errorMessage, 'rate limit')) {
                $errorMessage = __('Rate limit exceeded. Please try again in a few moments.');
            } elseif (str_contains($errorMessage, 'timeout')) {
                $errorMessage = __('Request timeout. Please try again.');
            } elseif (str_contains($errorMessage, 'model')) {
                $errorMessage = __('Invalid model configuration. Please check your ChatGPT settings.');
            } else {
                $errorMessage = __('AI service error: ') . $errorMessage;
            }
            
            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}