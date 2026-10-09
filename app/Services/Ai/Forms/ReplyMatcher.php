<?php

namespace App\Services\Ai\Forms;

use Carbon\Carbon;

/**
 * Answers a waiting draft from the user's typed reply, with no AI call.
 *
 * "mobile app, make it high" → project = Mobile App, priority = high: each
 * word is matched against the choices the draft already offered. It only
 * answers when the reply is clearly a short answer: questions, long
 * messages and messages with words it cannot explain go to the AI instead.
 */
class ReplyMatcher
{
    /** Replies starting like this are new questions or commands, not answers. */
    private const NOT_AN_ANSWER = [
        'what', 'which', 'who', 'how', 'why', 'when', 'where', 'show', 'list', 'give', 'tell', 'find', 'search',
        'is', 'are', 'do', 'does', 'can', 'could', 'should', 'create', 'add', 'new', 'report', 'delete', 'remove',
        'approve', 'reject', 'send', 'invite', 'cancel', 'stop',
    ];

    /** Words an answer may contain besides the values themselves. */
    private const FILLER = [
        'a', 'an', 'the', 'it', 'its', 'to', 'in', 'into', 'on', 'under', 'for', 'of', 'and', 'with', 'as', 'be', 'is',
        'make', 'set', 'change', 'put', 'use', 'assign', 'assigned', 'give', 'move', 'mark', 'call', 'name', 'named',
        'please', 'ok', 'okay', 'yes', 'sure', 'just', 'go', 'that', 'this', 'one', 'pls', 'thanks', 'thank', 'you',
        'priority', 'severity', 'status', 'stage', 'project', 'assignee', 'due', 'date', 'by', 'role', 'person',
        'hours', 'hour', 'hrs', 'hr', 'h', 'amount', 'total', 'each', 'per', 'usd', 'dollars', 'rupees', 'rs', 'inr', 'eur',
    ];

    private const MAX_WORDS = 12;

    /**
     * @param  array  $form  the draft's form (fields with their options)
     * @param  string[]  $ask  the names of the fields still missing
     * @return array<string, string|string[]> field values to apply; empty when the reply is not a plain answer
     */
    public function match(array $form, array $ask, string $text, int $userId): array
    {
        // "1,200" is one number, not two.
        $text = preg_replace('/(?<=\d),(?=\d{3}\b)/', '', $text);
        $words = $this->words($text);
        if (!$words || count($words) > self::MAX_WORDS || str_ends_with(trim($text), '?') || in_array($words[0], self::NOT_AN_ANSWER, true)) {
            return [];
        }

        $states = collect($form['fields'] ?? [])->keyBy('name');

        // Asked for one free-text value (a title, an email): the reply is the value.
        if (count($ask) === 1 && ($states[$ask[0]]['type'] ?? null) === 'text') {
            $value = $this->stripLeadIn(trim($text));

            return $value === '' ? [] : [$ask[0] => mb_substr($value, 0, 255)];
        }

        $said = ' ' . implode(' ', $words) . ' ';
        $values = [];
        $used = [];

        foreach ($states as $name => $state) {
            [$value, $matchedWords] = match ($state['type']) {
                'select' => $this->matchOne($state['options'], $said, $this->isPeople($state) ? $userId : null),
                'multi' => $this->matchMany($state['options'], $said, $this->isPeople($state) ? $userId : null),
                'date' => $this->matchDate($said),
                // Numbers are everywhere (dates, names): only for a number that was asked for.
                'number' => in_array($name, $ask, true) ? $this->matchNumber($said) : [null, []],
                default => [null, []],
            };
            if ($value !== null && $value !== []) {
                $values[$name] = $value;
                $used[$name] = $matchedWords;
            }
        }

        // A word that fits two fields ("critical": priority and severity) is
        // ambiguous: keep it only for a field that was asked for.
        foreach ($used as $name => $matched) {
            foreach ($used as $other => $otherMatched) {
                if ($name !== $other && array_intersect($matched, $otherMatched) && !in_array($name, $ask, true)) {
                    unset($values[$name]);
                }
            }
        }

        if (!$values || ($ask && !array_intersect(array_keys($values), $ask))) {
            return [];
        }

        // Every word must be explained by a value or be a filler word,
        // otherwise the reply says something more and goes to the AI.
        $explained = array_merge(self::FILLER, ...array_values(array_intersect_key($used, $values)));
        $unexplained = array_diff($words, $explained);

        return count($unexplained) <= 1 ? $values : [];
    }

    /** @return array{0: ?string, 1: string[]} */
    private function matchOne(array $options, string $said, ?int $userId): array
    {
        $hits = $this->hits($options, $said, $userId);

        // A full-name match beats a single matching word.
        $full = array_filter($hits, fn ($hit) => $hit['full']);
        $hits = $full ?: $hits;

        // (string): PHP turns numeric array keys such as "2" into integers.
        return count($hits) === 1 ? [(string) array_key_first($hits), reset($hits)['words']] : [null, []];
    }

    /** @return array{0: string[], 1: string[]} */
    private function matchMany(array $options, string $said, ?int $userId): array
    {
        $hits = $this->hits($options, $said, $userId);

        return [array_map('strval', array_keys($hits)), array_merge([], ...array_column($hits, 'words'))];
    }

    /**
     * @param  int|null  $userId  people lists only: "me" is this user
     * @return array<string, array{full: bool, words: string[]}> option value => how it matched
     */
    private function hits(array $options, string $said, ?int $userId): array
    {
        $hits = [];
        foreach ($options as $option) {
            $value = (string) $option['value'];

            if ($userId !== null && $value === 'none' && preg_match('/ (unassigned|nobody|no one|none) /', $said, $m)) {
                $hits[$value] = ['full' => true, 'words' => explode(' ', $m[1])];
                continue;
            }
            if ($userId !== null && $value === (string) $userId && preg_match('/ (me|myself|mine) /', $said, $m)) {
                $hits[$value] = ['full' => true, 'words' => [$m[1]]];
                continue;
            }

            $name = $this->words($this->core($option['label']));
            if (!$name) {
                continue;
            }
            if (str_contains($said, ' ' . implode(' ', $name) . ' ')) {
                $hits[$value] = ['full' => true, 'words' => $name];
                continue;
            }
            $distinctive = array_values(array_filter($name, fn ($w) => mb_strlen($w) >= 3 && !in_array($w, self::FILLER, true) && str_contains($said, " {$w} ")));
            if ($distinctive) {
                $hits[$value] = ['full' => false, 'words' => $distinctive];
            }
        }

        return $hits;
    }

    /** @return array{0: ?string, 1: string[]} */
    private function matchDate(string $said): array
    {
        if (preg_match('/ (\d{4}) (\d{2}) (\d{2}) /', $said, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return ["{$m[1]}-{$m[2]}-{$m[3]}", [$m[1], $m[2], $m[3]]];
        }
        if (str_contains($said, ' today ')) {
            return [now()->toDateString(), ['today']];
        }
        if (str_contains($said, ' tomorrow ')) {
            return [now()->addDay()->toDateString(), ['tomorrow']];
        }
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            if (str_contains($said, " {$day} ")) {
                $date = now()->isDayOfWeek(Carbon::parse($day)->dayOfWeek) ? now() : now()->next($day);

                return [$date->toDateString(), [$day, 'next', 'this', 'on']];
            }
        }

        return [null, []];
    }

    /** "500", "1,200.50", "2.5 hours" → the one number in the reply. */
    private function matchNumber(string $said): array
    {
        preg_match_all('/ (\d+(?:\.\d+)?)(h|hrs?)? /', str_replace(',', '', $said), $m, PREG_SET_ORDER);

        return count($m) === 1 && (float) $m[0][1] > 0 ? [$m[0][1], [trim($m[0][0])]] : [null, []];
    }

    /** "me" and "unassigned" only mean something in a list of people. */
    private function isPeople(array $state): bool
    {
        return in_array($state['kind'] ?? null, ['member', 'members'], true);
    }

    /** "Ravi Kumar (ravi@x.com)" → "Ravi Kumar"; "Login page · Mobile App (#12)" → "Login page". */
    private function core(string $label): string
    {
        return trim(preg_split('/\s+·\s+|\s+\(/u', $label)[0]);
    }

    /** "call it Login page" → "Login page". */
    private function stripLeadIn(string $text): string
    {
        return trim(preg_replace('/^(?:call it|name it|it\'?s called|it is called|the (?:title|name) is|title:|name:)\s+/iu', '', $text), " \t\"'.");
    }

    /** @return string[] lower-case words and numbers */
    private function words(string $text): array
    {
        $words = explode(' ', trim(preg_replace('/[^\p{L}\p{N}@.]+/u', ' ', mb_strtolower($text))));

        return array_values(array_filter(array_map(fn ($w) => trim($w, '.'), $words), fn ($w) => $w !== ''));
    }
}
