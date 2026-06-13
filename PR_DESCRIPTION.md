# PR: Add PWA support for DMIMS

## Summary
Adds Progressive Web App (PWA) support across the DMIMS application.

### What changed
- Added `public/manifest.webmanifest` for web app manifest metadata.
- Added `public/service-worker.js` with precache, navigation fallback, and asset caching.
- Added `public/sw-register.js` to register the service worker in browsers.
- Added `public/offline.html` as the offline fallback page.
- Added PWA metadata injection middleware in `app/Http/Middleware/InjectPwaScript.php`.
- Added `tools/convert-icons.js` to generate PNG icons from SVG placeholders.
- Updated docs with `docs/PWA.md` and `docs/PWA_PR_BODY.md`.
- Added build support for icons and PWA assets in `package.json`.
- Added patch file `pwa-changes.patch` for local application if needed.

## Verification
1. `npm ci`
2. `npm run build:assets`
3. `php artisan serve --host=127.0.0.1 --port=8000`
4. Confirm the following endpoints return `200`:
   - `/manifest.webmanifest`
   - `/service-worker.js`
   - `/offline.html`
5. In browser DevTools → Application:
   - Confirm the manifest loads and icons are visible.
   - Confirm `/service-worker.js` is registered.
   - Disable network and confirm the offline fallback shows for navigation requests.

## Notes
- The service worker avoids caching admin/internal routes.
- Icon generation is part of `npm run build:assets`.
- `git` is needed locally to apply the patch and create/ push a branch.
