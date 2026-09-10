<div
    x-data="{}"
    x-init="
        window.onDmimsTurnstileVerified = (token) => { $wire.set('data.turnstile_token', token); };
        window.onDmimsTurnstileExpired = () => { $wire.set('data.turnstile_token', null); };
    "
>
    <div
        class="cf-turnstile"
        data-sitekey="{{ $siteKey }}"
        data-callback="onDmimsTurnstileVerified"
        data-expired-callback="onDmimsTurnstileExpired"
        data-error-callback="onDmimsTurnstileExpired"
    ></div>
</div>
{{-- No integrity/SRI hash: Cloudflare explicitly documents that Turnstile's
     api.js is versioned/rotated server-side and must be loaded without SRI
     pinning, or the widget breaks unpredictably when Cloudflare updates it. --}}
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
