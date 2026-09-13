// Live camera barcode scanning — registers an Alpine.data('barcodeCamera')
// component used by resources/views/components/barcode-camera-button.blade.php.
// Decoded values are dispatched as a plain 'barcode-camera-decoded' CustomEvent
// (mirroring this app's existing 'barcode-scanned' event convention) rather
// than calling $wire directly here, so this file stays unaware of — and the
// existing keyboard-wedge scan paths stay untouched by — whatever each call
// site does with the decoded string.
import { Html5Qrcode } from 'html5-qrcode';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('barcodeCamera', () => ({
        // iOS Safari has no native BarcodeDetector and some older/embedded
        // WebViews lack getUserMedia entirely — feature-detect once so the
        // button (x-show="supported") never renders on those, rather than
        // showing a control that would fail when clicked.
        supported: Boolean(window.navigator.mediaDevices?.getUserMedia),
        open: false,
        error: null,
        readerId: `barcode-camera-reader-${Math.random().toString(36).slice(2)}`,
        scanner: null,

        async openScanner() {
            this.error = null;
            this.open = true;
            await this.$nextTick();

            // Reuse one Html5Qrcode instance across the whole page session
            // instead of `new Html5Qrcode(...)` on every open: creating a
            // fresh instance each time makes the browser treat it as a brand
            // new camera request, which is why the prompt reappeared on
            // every scan even within the same signed-in session, and why a
            // second open after a successful scan needed a full page
            // refresh to work again — start()/stop() on the same instance
            // is the library's intended repeat-scan usage.
            if (! this.scanner) {
                this.scanner = new Html5Qrcode(this.readerId);
            }

            try {
                await this.scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    (decodedText) => this.handleDecoded(decodedText),
                    // Per-frame "no code in this frame" callback — fires
                    // continuously while aiming, not an error worth surfacing.
                    () => {},
                );
            } catch (e) {
                // Keep the modal open so the error is actually seen — closing
                // it here would make a denied/unavailable camera a silent
                // failure (click the button, nothing visibly happens).
                this.error = e?.message || 'Could not access the camera.';
            }
        },

        handleDecoded(decodedText) {
            this.$dispatch('barcode-camera-decoded', decodedText);
            this.closeScanner();
        },

        async closeScanner() {
            this.open = false;

            if (! this.scanner) {
                return;
            }

            try {
                await this.scanner.stop();
            } catch (e) {
                // Already stopped/never started — nothing to clean up.
            }

            // Deliberately not clear()/null-ing the instance here — see
            // openScanner()'s comment: keeping it alive is what lets the
            // next open() reuse the same camera grant instead of prompting
            // again.
        },
    }));
});
