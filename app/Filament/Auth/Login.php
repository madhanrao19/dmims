<?php

namespace App\Filament\Auth;

use App\Services\TurnstileVerifier;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Adds Cloudflare Turnstile to the login form. The widget itself only proves
 * a human/non-automated browser rendered it — the actual security boundary
 * is server-side verification in authenticate() below, never trusting the
 * client-submitted token alone. Existing rate limiting (WithRateLimiting,
 * unchanged from the base Login), CSRF (panel-wide middleware, unchanged)
 * and password-reset behaviour are untouched.
 */
class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
                ...($this->turnstile()->isEnabled() ? [$this->getTurnstileFormComponent()] : []),
            ]);
    }

    protected function getTurnstileFormComponent(): Component
    {
        return ViewComponent::make('filament.turnstile-widget')
            ->viewData(['siteKey' => config('services.turnstile.site_key')]);
    }

    public function authenticate(): ?LoginResponse
    {
        if ($this->turnstile()->isEnabled()) {
            // Read directly off the Livewire component's own $data array
            // rather than $this->form->getState(): the widget's Blade
            // partial writes the token via $wire.set('data.turnstile_token',
            // ...) rather than through a real dehydrated Filament field (a
            // raw ViewComponent isn't part of form state dehydration), so
            // getState() would not see it.
            $token = $this->data['turnstile_token'] ?? null;

            if (! $this->turnstile()->verify($token, request()->ip())) {
                throw ValidationException::withMessages([
                    'data.turnstile_token' => 'Verification failed. Please complete the challenge and try again.',
                ]);
            }
        }

        return parent::authenticate();
    }

    protected function turnstile(): TurnstileVerifier
    {
        return app(TurnstileVerifier::class);
    }
}
