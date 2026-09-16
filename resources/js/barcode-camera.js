// Live camera barcode scanning — registers an Alpine.data('barcodeCamera')
// component used by resources/views/components/barcode-camera-button.blade.php.
// Decoded values are dispatched as a plain 'barcode-camera-decoded' CustomEvent
// (mirroring this app's existing 'barcode-scanned' event convention) rather
// than calling $wire directly here, so this file stays unaware of — and the
// existing keyboard-wedge scan paths stay untouched by — whatever each call
// site does with the decoded string.
import { prepareZXingModule, readBarcodes } from 'zxing-wasm/reader';
import zxingWasmUrl from 'zxing-wasm/reader/zxing_reader.wasm?url';

// Self-hosted: Vite copies the .wasm into the versioned /build/ output and
// gives us a content-hashed URL, so the browser fetches it from our own
// origin (already covered by the service worker's stale-while-revalidate
// rule for /build/ paths — see public/service-worker.js) instead of a CDN.
prepareZXingModule({ overrides: { locateFile: () => zxingWasmUrl } });

document.addEventListener('alpine:init', () => {
    window.Alpine.data('barcodeCamera', () => ({
        // iOS Safari has no native BarcodeDetector and some older/embedded
        // WebViews lack getUserMedia entirely — feature-detect once so the
        // button (x-show="supported") never renders on those, rather than
        // showing a control that would fail when clicked.
        supported: Boolean(window.navigator.mediaDevices?.getUserMedia),
        open: false,
        error: null,
        stream: null,
        rafId: null,
        scanning: false,
        busy: false,
        canvas: null,
        ctx: null,

        async openScanner() {
            this.error = null;
            this.open = true;
            await this.$nextTick();

            try {
                // A fresh getUserMedia() call each open — unlike the previous
                // html5-qrcode integration, this doesn't re-prompt for
                // permission once granted for the page, so there's no need
                // to keep a camera stream alive between opens. That means
                // closeScanner() below can always fully stop the tracks:
                // simpler cleanup, and a guaranteed-fresh camera on reopen
                // (including after Livewire navigation re-creates this
                // component).
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' },
                });

                const video = this.$refs.video;
                video.srcObject = this.stream;
                // video.play() deliberately stays inside this try — a
                // rejection here (e.g. iOS Safari autoplay policy, or the
                // element getting detached mid-await) must not leave
                // this.stream's tracks running with no way to stop them;
                // the catch below releases the camera either way.
                await video.play();
            } catch (e) {
                // Release the camera but keep the modal (this.open) itself
                // open — closeScanner() would hide it, and an error the
                // user can't see is worse than a denied/unavailable camera.
                this.error = e?.message || 'Could not access the camera.';
                this.releaseCamera();
                return;
            }

            if (! this.canvas) {
                this.canvas = document.createElement('canvas');
                this.ctx = this.canvas.getContext('2d', { willReadFrequently: true });
            }

            this.scanning = true;
            this.scanFrame();
        },

        scanFrame() {
            if (! this.scanning) {
                return;
            }

            if (! this.busy) {
                this.busy = true;
                this.decodeFrame().finally(() => { this.busy = false; });
            }

            this.rafId = requestAnimationFrame(() => this.scanFrame());
        },

        async decodeFrame() {
            const video = this.$refs.video;

            if (! video.videoWidth) {
                return;
            }

            this.canvas.width = video.videoWidth;
            this.canvas.height = video.videoHeight;
            this.ctx.drawImage(video, 0, 0);
            const imageData = this.ctx.getImageData(0, 0, video.videoWidth, video.videoHeight);

            // No `formats` restriction — zxing-wasm's default set already
            // covers every 1D format the previous html5-qrcode integration
            // supported, plus QR/MicroQR/DataMatrix/Aztec/PDF417 for free.
            const results = await readBarcodes(imageData, { maxNumberOfSymbols: 1 });

            // scanning may have been turned off (closeScanner) while this
            // async decode was in flight — stop() prevents a decode racing
            // in after the modal has already closed.
            if (results.length && this.scanning) {
                this.handleDecoded(results[0].text);
            }
        },

        handleDecoded(decodedText) {
            // Stops scanFrame's rAF loop immediately, before the async
            // closeScanner() below finishes — the single source of
            // duplicate-scan prevention: once a symbol decodes, no further
            // frame is captured for this open() session.
            this.scanning = false;
            this.$dispatch('barcode-camera-decoded', decodedText);
            this.closeScanner();
        },

        closeScanner() {
            this.open = false;
            this.releaseCamera();
        },

        releaseCamera() {
            this.scanning = false;

            if (this.rafId) {
                cancelAnimationFrame(this.rafId);
                this.rafId = null;
            }

            const video = this.$refs?.video;
            if (video) {
                video.pause();
                video.srcObject = null;
            }

            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
            }
        },

        // Alpine's own lifecycle hook — runs when this component is removed
        // from the DOM (e.g. Livewire SPA/wire:navigate tearing down the
        // whole page while the scanner modal is open). Without this, a
        // still-open stream's tracks are only released whenever the
        // garbage collector gets to them, leaving the camera indicator lit.
        destroy() {
            this.releaseCamera();
        },
    }));
});
