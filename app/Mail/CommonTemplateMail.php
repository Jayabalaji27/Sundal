<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CommonTemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $content;
    public $variables;
    public $language;

    public function __construct(string $subject, string $content, array $variables = [], string $language = 'en')
    {
        $this->subject = $subject;
        $this->content = $content;
        $this->variables = $variables;
        $this->language = $language;
    }

    public function build()
    {
        // Set the locale for this email
        app()->setLocale($this->language);
        
        return $this->subject($this->subject)
                    ->view('emails.layout')
                    ->with([
                        'content' => $this->content,
                        'appName' => $this->variables['{app_name}'] ?? config('app.name'),
                        'subject' => $this->subject,
                        'language' => $this->language
                    ]);
    }
}