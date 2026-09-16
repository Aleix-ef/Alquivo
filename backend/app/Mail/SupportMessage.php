<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class SupportMessage extends Mailable
{
    // Sent synchronously: UploadedFile temporary paths must never enter a queue.
    public function __construct(public array $details, private array $uploads = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Alquivo · Soporte] '.$this->details['subject'],
            replyTo: [new Address($this->details['email'])],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.support');
    }

    public function attachments(): array
    {
        return array_map(fn ($file, $index) => Attachment::fromPath($file->getRealPath())
            ->as('adjunto-'.($index + 1).'.'.$file->guessExtension())
            ->withMime($file->getMimeType()), $this->uploads, array_keys($this->uploads));
    }
}
