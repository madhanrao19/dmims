{{--
    Single root element required — this is a Livewire component template.
    The keydown-buffering div below is entirely unmodified from before
    camera scanning existed; the camera button is a plain sibling, not a
    wrapper around it, so the keyboard-wedge path is unaffected either way.
--}}
<div>
    <div
        x-data="{
            buffer: '',
            lastKeyTime: 0,
            threshold: 50, {{-- ms between keystrokes; scanner-gun input is far faster than typing --}}

            handleKey(e) {
                const activeTag = document.activeElement ? document.activeElement.tagName : '';
                const isEditable = document.activeElement && document.activeElement.isContentEditable;

                // Don't buffer while the user is typing into a field anywhere in
                // the app — this also leaves ViewBox's own 'Add Document Mode'
                // scan field untouched, since that field has focus while in use.
                if (isEditable || activeTag === 'INPUT' || activeTag === 'TEXTAREA' || activeTag === 'SELECT') {
                    this.buffer = '';
                    return;
                }

                const now = Date.now();
                const gap = now - this.lastKeyTime;
                this.lastKeyTime = now;

                if (gap > this.threshold && this.buffer.length > 0) {
                    this.buffer = '';
                }

                if (e.key === 'Enter') {
                    if (this.buffer.length > 2) {
                        $wire.scan(this.buffer);
                    }
                    this.buffer = '';
                } else if (e.key.length === 1) {
                    this.buffer += e.key;
                }
            }
        }"
        x-init="window.addEventListener('keydown', handleKey.bind($data))"
    ></div>

    @auth
        <div class="fixed bottom-4 right-4 z-40">
            <x-barcode-camera-button x-on:barcode-camera-decoded="$wire.scan($event.detail)" />
        </div>
    @endauth
</div>
