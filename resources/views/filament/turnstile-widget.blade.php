{{--
    wire:ignore is the actual fix here: Turnstile injects its own iframe into
    the widget div after this renders, and $wire.set() below triggers a
    Livewire request (e.g. on every keystroke elsewhere on the form) that
    re-renders/morphs the DOM — without wire:ignore, morphdom doesn't know
    about that foreign iframe and strips/reinitialises it. wire:ignore tells
    Livewire to never touch this subtree, so Turnstile fully owns its own DOM
    across re-renders.

    min-height on the container reserves the widget's standard rendered size
    (Cloudflare's own recommended fix for this) — without it, the container
    is 0px tall until the iframe loads in asynchronously, which visibly
    shifts every field below it (and the Sign in button) down the moment it
    appears.

    $wire.set(..., token) below must sync immediately (no `false` deferred
    arg) — 'turnstile_token' is not a real dehydrated Filament form field
    (this is a raw ViewComponent, not part of form state), so a deferred
    set() never made it into the login request's payload. Login.php reads
    `$this->data['turnstile_token']` (see its own comment) specifically
    because it's not part of getState() either.

    EXPLICIT rendering (turnstile.render() called from x-init) instead of
    Cloudflare's implicit auto-scan (a bare .cf-turnstile[data-sitekey] div)
    is the actual fix for the real login-blocker: the implicit scan runs
    once, synchronously, whenever api.js's async script finishes loading —
    if that happens before the browser has parsed this far down the page
    (a real race with `async`, not fixed by `defer` since `async` wins when
    both are set), the scan finds nothing and the widget div is left
    permanently empty, with no retry. Confirmed directly: a clean full page
    load of the live login page left the div with zero children/no iframe
    while `window.turnstile`'s API was already fully loaded (manual
    `turnstile.render()` worked instantly) — this is why the widget only
    "stuck" after several refreshes and why real users' tokens were blank
    server-side 100% of the time despite the widget occasionally rendering
    "Success!" on a lucky load. Calling render() ourselves, once, from
    x-init (which only runs after this element exists in the DOM) removes
    the race entirely. `render=explicit` on the script URL below disables
    the implicit scan so it can never double-render this div.
--}}
<div
    wire:ignore
    x-data="{}"
    x-init="
        window.onDmimsTurnstileVerified = (token) => { $wire.set('data.turnstile_token', token); };
        window.onDmimsTurnstileExpired = () => { $wire.set('data.turnstile_token', null); };
        (function renderDmimsTurnstile() {
            if (window.turnstile) {
                window.turnstile.render($refs.widget, {
                    sitekey: @js($siteKey),
                    callback: window.onDmimsTurnstileVerified,
                    'expired-callback': window.onDmimsTurnstileExpired,
                    'error-callback': window.onDmimsTurnstileExpired,
                });
            } else {
                setTimeout(renderDmimsTurnstile, 50);
            }
        })();
    "
    style="min-height: 65px"
>
    <div x-ref="widget"></div>
</div>
{{-- No integrity/SRI hash: Cloudflare explicitly documents that Turnstile's
     api.js is versioned/rotated server-side and must be loaded without SRI
     pinning, or the widget breaks unpredictably when Cloudflare updates it.

     data-cfasync="false" exempts this script from Cloudflare's Rocket
     Loader (a zone-level "Speed" optimization that defers/reorders script
     tags) — when this domain is proxied through Cloudflare with Rocket
     Loader on, it was rewriting this tag's execution, which is Cloudflare's
     own documented cause of Turnstile tokens failing server-side
     verification even though the widget still visibly renders "Success!"
     client-side (seen directly in this app: a console exception traced
     into rocket-loader.min.js the first time this widget ran on a
     Cloudflare-proxied hostname).

     render=explicit disables Cloudflare's implicit auto-scan entirely — see
     the long comment above the div for why that scan is the real root
     cause of the login-blocker. --}}
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer data-cfasync="false"></script>
