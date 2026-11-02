<?php

namespace App\Mail;

use App\Services\Reports\RenderedReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

/** A generated report sent from the Reports pages, attached as PDF, Excel or Word. */
class ReportMail extends Mailable
{
    use Queueable;

    public function __construct(
        public RenderedReport $file,
        public string $reportTitle,
        public string $senderName,
        public string $workspaceName,
        public ?string $note = null,
    ) {
    }

    public function build()
    {
        return $this->subject($this->reportTitle . ' - ' . $this->workspaceName)
            ->view('emails.report')
            ->with([
                'reportTitle' => $this->reportTitle,
                'senderName' => $this->senderName,
                'workspaceName' => $this->workspaceName,
                'note' => $this->note,
                'filename' => $this->file->filename,
            ])
            ->attachData($this->file->content, $this->file->filename, ['mime' => $this->file->mime]);
    }
}
