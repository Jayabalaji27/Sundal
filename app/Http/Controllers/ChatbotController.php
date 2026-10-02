<?php
namespace App\Http\Controllers;

use App\Models\KbArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class ChatbotController extends Controller
{
    public function index()
    {
        $apiKey = getSetting('chatgptKey');
        return Inertia::render('chatbot/Index', [
            'hasChatgptKey'  => !empty($apiKey),
            'settingsUrl'    => route('settings'),
        ]);
    }

    public function ask(Request $request)
    {
        $request->validate(['message' => 'required|string|max:2000']);

        $apiKey = getSetting('chatgptKey');
        if (!$apiKey) {
            return response()->json(['error' => 'OpenAI API key not configured. Go to Settings → ChatGPT.'], 422);
        }

        $workspaceId = auth()->user()->current_workspace_id;
        $userMessage = $request->message;

        // Search knowledge base for relevant context
        $kbContext = '';
        $relevant = KbArticle::where('workspace_id', $workspaceId)
            ->where('is_published', true)
            ->where(fn($q) => $q->where('title','like',"%{$userMessage}%")->orWhere('content','like',"%{$userMessage}%"))
            ->limit(3)
            ->get(['title','content']);

        if ($relevant->isNotEmpty()) {
            $kbContext = "\n\nRelevant knowledge base articles:\n";
            foreach ($relevant as $a) {
                $kbContext .= "- **{$a->title}**: " . substr(strip_tags($a->content), 0, 400) . "\n";
            }
        }

        $systemPrompt = getSetting('chatbotSystemPrompt', 'You are a helpful assistant for this workspace. Answer concisely and professionally.');

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'    => 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt . $kbContext],
                        ['role' => 'user',   'content' => $userMessage],
                    ],
                    'max_tokens' => 500,
                ]);

            if ($response->failed()) {
                return response()->json(['error' => 'OpenAI request failed: ' . $response->body()], 422);
            }

            $answer = $response->json('choices.0.message.content', 'No response.');
            return response()->json(['answer' => $answer]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
