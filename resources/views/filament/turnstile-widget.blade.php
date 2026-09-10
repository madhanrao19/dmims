{{--
    wire:ignore is the actual fix here: Turnstile injects its own iframe into
    .cf-turnstile after this renders, and $wire.set() below triggers a
    Livewire request (e.g. on every keystroke elsewhere on the form) that
    re-renders/morphs the DOM — without wire:ignore, morphdom doesn't know
    about that foreign iframe and strips/reinitialises it, which is why the
    widget needed several page refreshes to "stick" and then vanished the
    moment it was checked. wire:ignore tells Livewire to never touch this
    subtree, so Turnstile fully owns its own DOM across re-renders.

    min-height on the container reserves the widget's standard rendered size
    (Cloudflare's own recommended fix for this) — without it, the container
    is 0px tall until the iframe loads in asynchronously, which visibly
    shifts every field below it (and the Sign in button) down the moment it
    appears. A click/type aimed at pre-shift coordinates then lands on the
    wrong element — this is what looked like "needs several refreshes to
    load".
--}}
<div
    wire:ignore
    x-data="{}"
    x-init="
        window.onDmimsTurnstileVerified = (token) => { $wire.set('data.turnstile_token', token, false); };
        window.onDmimsTurnstileExpired = () => { $wire.set('data.turnstile_token', null, false); };
    "
    style="min-height: 65px"
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
