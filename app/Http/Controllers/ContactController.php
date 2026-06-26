<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('contact');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            // ponytail: honeypot, not a real field - bots fill it, humans never see it
            'website' => ['max:0'],
        ]);

        Mail::to(config('mail.contact_to'))
            ->send(new ContactFormMessage($data['name'], $data['email'], $data['message']));

        return back()->with('status', 'Thanks for reaching out — we\'ll get back to you soon.');
    }
}
