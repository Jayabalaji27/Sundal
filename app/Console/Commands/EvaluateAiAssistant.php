<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Ai\AiAccess;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\AiRequest;
use App\Services\Ai\ToolRegistry;
use App\Services\Ai\ToolSpec;
use App\Services\Ai\Tools\AiTool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Runs the evaluation set (tests/AiEval/prompts.json) against the user's
 * workspace provider and reports how often the model picks the right tool.
 * Nothing is changed: every tool call is only recorded. Each prompt costs
 * one model call on the workspace's own AI account.
 */
class EvaluateAiAssistant extends Command
{
    protected $signature = 'ai:eval
        {--user= : Email of an owner or manager in the workspace to test}
        {--set= : owner or manager (default: the user\'s role)}
        {--limit=0 : Only the first N prompts}
        {--threshold=90 : Minimum % of correct picks}';

    protected $description = 'Check which tool the AI Assistant picks for each prompt of the evaluation set';

    public function handle(ToolRegistry $registry, AiProviderFactory $providers): int
    {
        $user = User::where('email', $this->option('user'))->first();
        if (!$user) {
            $this->error('Pass --user=<email> of an owner or manager.');

            return self::FAILURE;
        }

        // Tools, permissions and workspace scoping all read the signed-in user.
        Auth::guard('web')->setUser($user);

        $role = AiAccess::role($user);
        $settings = AiAccess::settings($user);
        if (!$role || !$settings || !AiAccess::canUse($user)) {
            $this->error('This user cannot use the AI Assistant, or no provider is connected.');

            return self::FAILURE;
        }

        $set = $this->option('set') ?: $role;
        $prompts = json_decode(file_get_contents(base_path('tests/AiEval/prompts.json')), true)[$set] ?? [];
        if ((int) $this->option('limit') > 0) {
            $prompts = array_slice($prompts, 0, (int) $this->option('limit'));
        }

        $tools = $registry->forUser($user, $settings->isReadOnlyModel());
        $provider = $providers->make($settings);
        $this->info("Evaluating {$set} set on {$settings->provider} / {$settings->model} with " . count($tools) . ' tools…');

        $rows = [];
        $scored = $correct = 0;

        foreach ($prompts as $case) {
            // null = the right answer is no tool at all.
            $expected = is_array($case['expect']) ? $case['expect'] : [$case['expect']];
            // Skip prompts that need a tool this user may not use.
            $needs = array_filter($expected);
            if ($needs && !array_intersect($needs, array_keys($tools))) {
                $rows[] = [$case['prompt'], implode('|', $needs), '-', 'skipped'];
                continue;
            }

            // A plain loop, not array_map(fn …): arrow functions capture by value,
            // so the handlers could not add to $called.
            $called = [];
            $specs = [];
            foreach ($tools as $tool) {
                $specs[] = new ToolSpec(
                    $tool->name(),
                    $tool->description(),
                    $tool->parameters(),
                    function (array $args) use ($tool, &$called) {
                        $called[] = $tool->name();

                        return 'Evaluation run: recorded, nothing was looked up or changed.';
                    },
                );
            }

            try {
                $provider->run(new AiRequest(
                    system: "You are the Sundal AI Assistant for a {$role}. Use a tool when one fits; otherwise answer briefly. Today is " . now()->format('l, Y-m-d') . '.',
                    messages: [['role' => 'user', 'content' => $case['prompt']]],
                    tools: $specs,
                    maxSteps: 1,
                    maxTokens: 512,
                ));
            } catch (AiProviderException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $first = $called[0] ?? null;
            $ok = in_array($first, $expected, true);
            $scored++;
            $correct += $ok ? 1 : 0;
            $rows[] = [$case['prompt'], implode('|', array_map(fn ($e) => $e ?? '(none)', $expected)), $first ?? '(none)', $ok ? 'ok' : 'WRONG'];
        }

        $this->table(['Prompt', 'Expected', 'Picked', 'Result'], $rows);

        $percent = $scored ? round($correct / $scored * 100, 1) : 0;
        $threshold = (float) $this->option('threshold');
        $this->line("Correct tool picks: {$correct}/{$scored} ({$percent}%), gate {$threshold}%");

        return $percent >= $threshold ? self::SUCCESS : self::FAILURE;
    }
}
