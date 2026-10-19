<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A task planned into a sprint (sprint_tasks). A model of its own so that
 * attach and detach fire events, which the history log records on the task
 * (App\Services\History\HistoryRecorder).
 */
class SprintTask extends Pivot
{
    protected $table = 'sprint_tasks';

    public $incrementing = true;
}
