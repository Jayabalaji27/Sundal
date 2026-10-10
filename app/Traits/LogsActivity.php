<?php

namespace App\Traits;

use App\Models\ProjectActivity;

trait LogsActivity
{
    public static function bootLogsActivity()
    {
        static::created(function ($model) {
            $model->logActivity('created');
        });

        static::updated(function ($model) {
            $model->logActivity('updated');
        });

        static::deleted(function ($model) {
            $model->logActivity('deleted');
        });
    }

    public function logActivity(string $action, array $metadata = [])
    {
        if (!$this->shouldLogActivity()) {
            return;
        }

        // No signed-in user (queue job, scheduled task, seeder): skip the feed
        // entry rather than credit the change to user 1. Who did it is still
        // recorded in the history log (App\Services\History\HistoryRecorder).
        if (!auth()->id()) {
            return;
        }

        ProjectActivity::create([
            'project_id' => $this->getProjectId(),
            'user_id' => auth()->id(),
            'action' => $this->getActivityAction($action),
            'description' => $this->getActivityDescription($action),
            'metadata' => array_merge($this->getActivityMetadata(), \App\Services\Ai\AiActionContext::metadata(), $metadata)
        ]);
    }

    protected function shouldLogActivity(): bool
    {
        return $this->getProjectId() !== null;
    }

    protected function getProjectId()
    {
        return $this->project_id ?? null;
    }

    protected function getActivityAction(string $action): string
    {
        $modelName = strtolower(class_basename($this));
        return "{$modelName}_{$action}";
    }

    protected function getActivityDescription(string $action): string
    {
        $modelName = class_basename($this);
        $title = $this->title ?? $this->name ?? "#{$this->id}";
        
        return match($action) {
            'created' => "{$modelName} '{$title}' was created",
            'updated' => "{$modelName} '{$title}' was updated",
            'deleted' => "{$modelName} '{$title}' was deleted",
            default => "{$modelName} '{$title}' {$action}"
        };
    }

    protected function getActivityMetadata(): array
    {
        return [
            'model_type' => get_class($this),
            'model_id' => $this->id,
            'changes' => $this->getChanges()
        ];
    }
}