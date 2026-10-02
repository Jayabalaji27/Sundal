<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceStatusUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $invoice;
    public $oldStatus;
    public $newStatus;

    public function __construct(Invoice $invoice, $oldStatus, $newStatus)
    {
        $this->invoice = $invoice;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }
}