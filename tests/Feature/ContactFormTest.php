<?php

namespace Tests\Feature;

use App\Mail\ContactFormMessage;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_contact_page_renders(): void
    {
        $this->get('/contact')->assertStatus(200);
    }

    public function test_valid_submission_sends_mail_and_redirects_with_status(): void
    {
        Mail::fake();

        $response = $this->post('/contact', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'message' => 'Hello, I have a question.',
            'website' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        Mail::assertSent(ContactFormMessage::class, function (ContactFormMessage $mail) {
            return $mail->email === 'jane@example.com';
        });
    }

    public function test_invalid_email_fails_validation(): void
    {
        Mail::fake();

        $response = $this->post('/contact', [
            'name' => 'Jane Doe',
            'email' => 'not-an-email',
            'message' => 'Hello',
            'website' => '',
        ]);

        $response->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    }

    public function test_honeypot_filled_rejects_submission(): void
    {
        Mail::fake();

        $response = $this->post('/contact', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'message' => 'Spam',
            'website' => 'http://spam.example',
        ]);

        $response->assertSessionHasErrors('website');
        Mail::assertNothingSent();
    }
}
