<?php

namespace App\Services\Ai\Tools;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Undo for tools that change a few fields of one record: keep the values
 * before and after, and put the old ones back only while the record still
 * holds the new ones (nobody changed it again since).
 */
trait RevertsChanges
{
    /** @return array<string, mixed> the fields' values, comparable across saves (dates as Y-m-d, numbers as floats) */
    protected function snapshot(Model $model, array $fields): array
    {
        return collect($fields)->mapWithKeys(fn (string $field) => [$field => $this->comparable($model->getAttribute($field))])->all();
    }

    /** The undo data for ToolOutcome. */
    protected function changeUndo(Model $model, array $before): array
    {
        return ['id' => $model->getKey(), 'before' => $before, 'after' => $this->snapshot($model->refresh(), array_keys($before))];
    }

    /** @throws ToolInputException when the record changed again since */
    protected function ensureUnchanged(Model $model, array $undo, string $name): void
    {
        if ($this->snapshot($model, array_keys($undo['after'])) != $undo['after']) {
            throw new ToolInputException(__(':name was changed again since, so it was not undone.', ['name' => $name]));
        }
    }

    private function comparable(mixed $value): mixed
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_bool($value) => (int) $value,
            is_numeric($value) => (float) $value,
            default => $value,
        };
    }
}
