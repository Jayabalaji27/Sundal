<?php

namespace App\Events;

use App\Models\ProjectExpense;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExpenseCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ProjectExpense $expense
    ) {
    }
}