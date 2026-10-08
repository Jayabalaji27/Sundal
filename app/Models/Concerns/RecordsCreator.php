<?php

namespace App\Models\Concerns;

/**
 * Fills the model's creator column (created_by, reported_by, uploaded_by…)
 * from the signed-in user when a record is created without one, so neither a
 * screen nor an AI tool can forget it. A value set by the caller always wins.
 *
 * Models whose column is not `created_by` define `protected string $creatorColumn`.
 */
trait RecordsCreator
{
    protected static function bootRecordsCreator(): void
    {
        static::creating(function ($model) {
            $column = $model->creatorColumn ?? 'created_by';

            if (empty($model->{$column}) && auth()->check()) {
                $model->{$column} = auth()->id();
            }
        });
    }
}
