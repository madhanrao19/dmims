<?php

namespace App\Livewire;

use App\Filament\Resources\BoxResource;
use App\Filament\Resources\DocumentFileResource;
use App\Filament\Resources\LocationResource;
use App\Models\LocationType;
use App\Services\ScannerService;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Livewire\Component;

/**
 * Global "scan from anywhere" listener, mounted once on every panel page
 * (see FilamentPanelProvider's BODY_END render hook). The Alpine side
 * (barcode-scanner-listener.blade.php) buffers scanner-gun keystrokes and
 * calls scan() on Enter — only while no form field has focus, so it never
 * competes with normal typing or ViewBox's own "Add Document Mode" scan
 * field.
 *
 * Restores the "Scan Center" page's unregistered-barcode toast (New Box /
 * New Document / New Rack / Cancel) and found-barcode redirect, removed in
 * 19a6e74, but globally rather than on a dedicated page.
 */
class BarcodeScannerListener extends Component
{
    public function scan(string $barcode): void
    {
        // This component is mounted on every panel page, including the
        // guest-facing login/password-reset layout — without this guard a
        // scan there would hit ScannerService::scan()'s non-nullable User
        // parameter and throw.
        if (! auth()->check()) {
            return;
        }

        $barcode = trim(substr($barcode, 0, 150));

        if ($barcode === '') {
            return;
        }

        $scanner = app(ScannerService::class);
        $outcome = $scanner->scan($barcode, auth()->user());

        if ($outcome['result'] === 'found' && $outcome['registry']) {
            $url = $scanner->recordUrl($outcome['registry']);

            if ($url) {
                $this->redirect($url);

                return;
            }
        }

        // A reserved-but-unclaimed label — its type is already known, so go
        // straight to that resource's create form instead of the 3-button
        // "what are you scanning?" prompt unknown barcodes get below.
        if ($outcome['result'] === 'unused' && $outcome['registry']) {
            $url = match ($outcome['registry']->barcode_type) {
                'document_file' => DocumentFileResource::getUrl('create', ['file_barcode' => $barcode]),
                'box' => BoxResource::getUrl('create', ['box_barcode' => $barcode]),
                'location' => LocationResource::getUrl('create', ['barcode' => $barcode]),
                default => null,
            };

            if ($url) {
                $this->redirect($url);

                return;
            }
        }

        if ($outcome['result'] === 'unknown') {
            $rackTypeId = LocationType::where('type_code', 'rack')->value('id');

            Notification::make()
                ->title('Unregistered Barcode Scanned')
                ->body('The barcode '.e($barcode).' is not in the system.')
                ->warning()
                ->persistent()
                ->actions([
                    NotificationAction::make('createBox')
                        ->label('New Box')
                        ->url(BoxResource::getUrl('create', ['box_barcode' => $barcode]))
                        ->button(),
                    NotificationAction::make('createDocument')
                        ->label('New Document')
                        ->url(DocumentFileResource::getUrl('create', ['file_barcode' => $barcode]))
                        ->button(),
                    NotificationAction::make('createRack')
                        ->label('New Rack')
                        ->url(LocationResource::getUrl('create', array_filter([
                            'barcode' => $barcode,
                            'location_type_id' => $rackTypeId,
                        ])))
                        ->button(),
                    NotificationAction::make('dismiss')
                        ->label('Cancel')
                        ->color('gray')
                        ->close(),
                ])
                ->send();

            return;
        }

        Notification::make()
            ->title('Barcode is inactive')
            ->body('No open record for "'.e($barcode).'".')
            ->warning()
            ->send();
    }

    public function render()
    {
        return view('livewire.barcode-scanner-listener');
    }
}
