<?php
namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class AgentController extends Controller
{
    private function ws(): int { return auth()->user()->current_workspace_id; }

    /** Read API key directly from DB to bypass stale cache */
    private function getChatgptKey(): ?string
    {
        $user = auth()->user();
        $key = Setting::where('user_id', $user->id)
            ->where('workspace_id', $user->current_workspace_id)
            ->where('key', 'chatgptKey')
            ->value('value');

        // Fallback: try without workspace (some setups store globally)
        if (empty($key)) {
            $key = Setting::where('user_id', $user->id)
                ->whereNull('workspace_id')
                ->where('key', 'chatgptKey')
                ->value('value');
        }

        return empty($key) ? null : $key;
    }

    private function getChatgptModel(): string
    {
        $user = auth()->user();
        $model = Setting::where('user_id', $user->id)
            ->where('key', 'chatgptModel')
            ->value('value');
        return $model ?: 'gpt-3.5-turbo';
    }

    // ── CRUD ─────────────────────────────────────────────────────────────
    public function index()
    {
        $agents = Agent::where('workspace_id', $this->ws())
            ->with('creator:id,name')
            ->latest()->get();

        $categories = KbCategory::where('workspace_id', $this->ws())
            ->withCount('publishedArticles')->get(['id','name','description']);

        $hasKey = !empty($this->getChatgptKey());

        return Inertia::render('agents/Index', compact('agents','categories','hasKey'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string|max:500',
            'system_prompt'    => 'nullable|string|max:3000',
            'greeting_message' => 'nullable|string|max:500',
            'kb_category_ids'  => 'nullable|array',
            'kb_category_ids.*'=> 'exists:kb_categories,id',
            'model'            => 'nullable|string|max:50',
        ]);

        Agent::create([
            'workspace_id' => $this->ws(),
            'created_by'   => auth()->id(),
            ...$request->only('name','description','system_prompt','greeting_message','kb_category_ids','model'),
        ]);

        return back()->with('success', 'Agent created.');
    }

    public function update(Request $request, Agent $agent)
    {
        abort_if($agent->workspace_id !== $this->ws(), 404);
        $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string|max:500',
            'system_prompt'    => 'nullable|string|max:3000',
            'greeting_message' => 'nullable|string|max:500',
            'kb_category_ids'  => 'nullable|array',
            'model'            => 'nullable|string|max:50',
            'is_active'        => 'boolean',
        ]);
        $agent->update($request->only('name','description','system_prompt','greeting_message','kb_category_ids','model','is_active'));
        return back()->with('success', 'Agent updated.');
    }

    public function destroy(Agent $agent)
    {
        abort_if($agent->workspace_id !== $this->ws(), 404);
        $agent->delete();
        return back()->with('success', 'Agent deleted.');
    }

    // ── Chat page ─────────────────────────────────────────────────────────
    public function chat(Agent $agent)
    {
        abort_if($agent->workspace_id !== $this->ws(), 404);
        $hasKey = !empty($this->getChatgptKey());
        return Inertia::render('agents/Chat', [
            'agent'  => $agent,
            'hasKey' => $hasKey,
            'settingsUrl' => route('settings'),
        ]);
    }

    // ── Ask (POST) ────────────────────────────────────────────────────────
    public function ask(Request $request, Agent $agent)
    {
        abort_if($agent->workspace_id !== $this->ws(), 404);
        $request->validate(['message' => 'required|string|max:2000']);

        $apiKey = $this->getChatgptKey();
        if (!$apiKey) {
            return response()->json(['error' => 'OpenAI API key not configured. Go to Settings → ChatGPT.'], 422);
        }

        $userMessage = $request->message;

        // Build KB context from agent's selected categories
        $kbContext = '';
        $catIds = $agent->kb_category_ids ?? [];
        $query = KbArticle::where('workspace_id', $this->ws())->where('is_published', true);

        if (!empty($catIds)) {
            $query->whereIn('kb_category_id', $catIds);
        }

        $relevant = $query->where(fn($q) =>
            $q->where('title','like',"%{$userMessage}%")
              ->orWhere('content','like',"%{$userMessage}%")
        )->limit(3)->get(['title','content']);

        if ($relevant->isNotEmpty()) {
            $kbContext = "\n\nRelevant knowledge base articles:\n";
            foreach ($relevant as $a) {
                $kbContext .= "- **{$a->title}**: " . substr(strip_tags($a->content), 0, 500) . "\n";
            }
        }

        $systemPrompt = $agent->system_prompt
            ?: 'You are a helpful assistant. Answer concisely and professionally.';

        $model = $agent->model ?: $this->getChatgptModel();

        try {
            $response = Http::withToken($apiKey)->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'    => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt . $kbContext],
                        ['role' => 'user',   'content' => $userMessage],
                    ],
                    'max_tokens' => 600,
                ]);

            if ($response->failed()) {
                return response()->json(['error' => 'OpenAI error: ' . $response->json('error.message','Unknown error')], 422);
            }

            return response()->json(['answer' => $response->json('choices.0.message.content','No response.')]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
