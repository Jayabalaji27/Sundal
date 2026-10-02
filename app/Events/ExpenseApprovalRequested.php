<?php

namespace App\Events;

use App\Models\ProjectExpense;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExpenseApprovalRequested
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $expense;

    public function __construct(ProjectExpense $expense)
    {
        $this->expense = $expense;
    }
}