<?php

namespace App\Imports\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Reading people's spreadsheets: column names, priority and severity words,
 * dates in common formats, and workspace members by email or name. Shared by
 * the AI assistant's sheet imports and the Tasks page import.
 */
trait InterpretsSheetValues
{
    private const PRIORITY_WORDS = [
        'low' => ['low', 'lowest', 'minor', 'trivial', 'p4', 'p5', '4', '5'],
        'medium' => ['medium', 'normal', 'moderate', 'med', 'p3', '3'],
        'high' => ['high', 'major', 'important', 'p2', '2'],
        'critical' => ['critical', 'urgent', 'highest', 'blocker', 'immediate', 'p0', 'p1', '0', '1'],
    ];

    private const SEVERITY_WORDS = [
        'minor' => ['minor', 'low', 'trivial', 'cosmetic', 's4', 'sev4', '4'],
        'major' => ['major', 'medium', 'normal', 'moderate', 's3', 'sev3', '3'],
        'critical' => ['critical', 'high', 'severe', 's2', 'sev2', '2'],
        'blocker' => ['blocker', 'showstopper', 'urgent', 'highest', 'crash', 's1', 'sev1', '1'],
    ];

    /** "Due Date", "due_date" and "DUE-DATE" all become "duedate". */
    private function key(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($name));
    }

    private function choice(string $value, array $valid, array $words, string $default, string $label, array &$problems): string
    {
        $key = $this->key($value);
        if ($key === '') {
            return $default;
        }
        foreach ($words as $choice => $list) {
            if (in_array($choice, $valid, true) && in_array($key, $list, true)) {
                return $choice;
            }
        }
        $problems[] = __('":value" is not a known :label; :default is used', ['value' => $value, 'label' => $label, 'default' => ucfirst($default)]);

        return $default;
    }

    /** Only a sure match: the email, the full name, or a first name only one person has. */
    private function matchMember(string $cell, Collection $members, array &$problems): ?object
    {
        $lower = mb_strtolower($cell);
        $match = $members->first(fn ($m) => mb_strtolower($m->email) === $lower || mb_strtolower($m->name) === $lower);
        if (!$match) {
            $byFirst = $members->filter(fn ($m) => mb_strtolower(strtok($m->name, ' ')) === $lower);
            $match = $byFirst->count() === 1 ? $byFirst->first() : null;
        }
        if (!$match) {
            $problems[] = __('":name" is not in this workspace; left unassigned', ['name' => $cell]);
        }

        return $match;
    }

    private function date(string $value, array &$problems): ?string
    {
        if ($value === '') {
            return null;
        }
        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y', '!d.m.Y', '!Y/m/d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }
            if ($date && $date->format(ltrim($format, '!')) === $value) {
                return $date->toDateString();
            }
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            $problems[] = __('":value" is not a date; left empty', ['value' => $value]);

            return null;
        }
    }
}
