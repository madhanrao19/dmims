PWA integration — DMIMS
======================

Summary
-------
This documents the PWA changes applied to the app and how to test/install the PWA locally.

What I added
------------
- `public/manifest.webmanifest` — web manifest referencing icons and theme colors
- `public/build/icons/icon-192.svg`, `public/build/icons/icon-512.svg` — placeholder icons
- `public/service-worker.js` — service worker (precache + navigation fallback)
- `public/offline.html` — offline fallback page
- `public/sw-register.js` — simple SW registration script
- `resources/js/app.js` — frontend entry (minimal)
- `app/Http/Middleware/InjectPwaScript.php` — middleware that injects manifest/meta and SW registration into HTML responses
- `bootstrap/app.php` — middleware registered globally

Why middleware
--------------
The middleware injects a `<link rel="manifest">`, a `theme-color` meta tag, and a `<script src="/sw-register.js">` into any `text/html` response. This ensures the manifest and registration are available across public pages and admin (Filament) pages without editing every layout.

Build & test (local)
--------------------
Run these commands from repository root:

```bash
npm install
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Then in a browser open `http://127.0.0.1:8000` and check DevTools → Application:

- Service Workers: ensure `/service-worker.js` is registered and active
- Manifest: ensure it reads `manifest.webmanifest` and icons are displayed
- Offline test: disable network and navigate — the `offline.html` fallback should appear for navigation requests

Notes & next steps
------------------
- Icons: I added SVG placeholders. For best compatibility, add PNGs (192×192 and 512×512) in `public/build/icons/` and update `manifest.webmanifest` if desired.
 - Icons: I added SVG placeholders and generated PNGs (`192×192`, `512×512`) plus an Apple touch icon (`180×180`) in `public/build/icons/`.
	 - To regenerate icons locally run:

```bash
node tools/convert-icons.js
```

 - Scripts: `package.json` includes `icons:generate` and `build:assets` (which runs `icons:generate` then `vite build`).
 - Scripts: `package.json` includes `icons:generate` and `build:assets` (which runs `icons:generate` then `vite build`). CI now runs `npm run build:assets`.
 - Platform assets: added `mask-icon.svg` for pinned tab icons and a Windows tile meta tag (`msapplication-TileImage`) is injected into HTML.
 - Service worker: now uses a versioned cache name, cleans up old caches on activation, and applies a stale-while-revalidate strategy for built/static assets.
- Admin (Filament): middleware injects manifest and SW registration into admin pages. If you prefer to exclude admin pages from SW caching, we can update the SW to ignore `/_ign` routes or adjust the middleware to skip paths starting with `/admin`.
- Update CI/CD: ensure `npm run build` runs during deployment so `public/build/` and `manifest.webmanifest` are generated/copied.
- Customization: change `theme_color` and icons in `public/manifest.webmanifest` to match your branding.

Files changed/created (quick links)
- `public/manifest.webmanifest`
- `public/service-worker.js`
- `public/sw-register.js`
- `public/offline.html`
- `public/build/icons/icon-192.svg`
- `public/build/icons/icon-512.svg`
- `resources/js/app.js`
- `app/Http/Middleware/InjectPwaScript.php`
- `bootstrap/app.php`

If you'd like, I can:

- generate PNG icons from the SVG placeholders and update the manifest, or
- update the SW to exclude admin routes explicitly, or
- add a CI step to run `npm run build` and copy assets into the release.
