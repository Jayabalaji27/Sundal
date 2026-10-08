<?php

namespace App\Services\Ai\Forms;

/**
 * Fallback with no AI: recognises a few clear commands by keyword
 * ("create a task…", "assign…", "report a bug…") so the matching form card
 * can still be offered when the provider fails or the model calls no tool.
 * It only picks the form and a title; the user fills the rest on the card.
 */
class IntentMatcher
{
    /** @return array{tool: string, args: array<string, string>}|null */
    public function match(string $text): ?array
    {
        $t = ' ' . mb_strtolower(preg_replace('/\s+/', ' ', $text)) . ' ';
        $isBug = (bool) preg_match('/\b(bug|bugs|defect|issue)\b/', $t);
        $isTask = (bool) preg_match('/\b(task|tasks|to-?do|to do|story|stories|ticket)\b/', $t);

        if (preg_match('/\binvite\b/', $t)) {
            preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/', $t, $email);

            return ['tool' => 'invite_user', 'args' => array_filter(['email' => $email[0] ?? null])];
        }

        if (preg_match('/\bsend\b.*\binvoice\b/', $t)) {
            preg_match('/\binv-[a-z0-9\-]+/', $t, $number);

            return ['tool' => 'send_invoice', 'args' => array_filter(['invoice' => isset($number[0]) ? strtoupper($number[0]) : null])];
        }

        if (preg_match('/\b(re)?assign\b/', $t) && !preg_match('/\b(create|add|make|report|log)\b/', $t)) {
            return ['tool' => $isBug ? 'assign_bug' : 'assign_task', 'args' => []];
        }

        if (preg_match('/\b(move|mark|change|set|update)\b/', $t)
            && preg_match('/\b(status|stage|done|complete|completed|in progress|review|blocked|resolved|closed|to do|todo)\b/', $t)) {
            return ['tool' => $isBug ? 'change_bug_status' : 'change_task_status', 'args' => []];
        }

        if (preg_match('/\b(create|add|make|log|raise|file|report|open)\b/', $t) && !preg_match('/\bsprint\b/', $t)) {
            $tool = match (true) {
                $isBug => 'create_bug',
                $isTask => 'create_task',
                (bool) preg_match('/\bproject\b/', $t) => 'create_project',
                default => null,
            };

            return $tool ? ['tool' => $tool, 'args' => array_filter(['title' => $this->title($text)])] : null;
        }

        return null;
    }

    /** "create a to do task for a login page" → "Login page". */
    private function title(string $text): ?string
    {
        if (!preg_match('/\b(?:called|named|titled|name of|for|about)\s+(.+)$/iu', trim($text), $m)
            && !preg_match('/:\s*(.+)$/u', trim($text), $m)) {
            return null;
        }

        $title = preg_replace('/^(?:a|an|the|our|my)\s+/iu', '', trim($m[1], " \t\n\r\0\x0B.!?\"'"));
        $title = trim((string) $title);

        return $title === '' ? null : mb_strtoupper(mb_substr($title, 0, 1)) . mb_substr($title, 1, 254);
    }
}
