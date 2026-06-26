<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class ContactFormMessage extends Mailable
{
    public function __construct(
        public string $name,
        public string $email,
        public string $body,
    ) {}

    public function build(): self
    {
        return $this->subject('New contact form submission')
            ->replyTo($this->email, $this->name)
            ->html(nl2br(e("Name: {$this->name}\nEmail: {$this->email}\n\n{$this->body}")));
    }
}
