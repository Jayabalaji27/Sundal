<?php

namespace App\Events;

use App\Models\ProjectBudget;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BudgetCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $budget;

    public function __construct(ProjectBudget $budget)
    {
        $this->budget = $budget;
    }
}