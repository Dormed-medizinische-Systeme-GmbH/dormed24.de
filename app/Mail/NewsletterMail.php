<?php

namespace App\Mail;

use App\Models\Newsletter;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Ein Newsletter so, wie der Editor ihn gespeichert hat. In Phase 1 nur als
 * Testmail an den Shop; der Betreff ist dafür markiert.
 */
class NewsletterMail extends Mailable
{
    public function __construct(
        public readonly Newsletter $newsletter,
        public readonly bool $isTest = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->isTest ? '[Test] ' : '').$this->newsletter->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: (string) $this->newsletter->html,
        );
    }
}
