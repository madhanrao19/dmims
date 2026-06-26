<?php

namespace App\Filament\Pages;

use App\Filament\Resources\BoxResource;
use App\Filament\Resources\DocumentFileResource;
use App\Models\BarcodeScanLog;
use App\Services\AccessControlService;
use App\Services\ScannerService;
use Filament\Actions\Action as NotificationAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

/**
 * Universal Scan Center (production-readiness roadmap #2): scan anything,
 * auto-detect what it is (product/location/box/document file/unknown), and
 * either open the record or — in bulk mode — keep scanning without
 * navigating away. Built on the existing ScannerService/BarcodeScanLog.
 */
class BarcodeScanner extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static string|\UnitEnum|null $navigationGroup = 'Shared Services';

    protected static ?string $title = 'Scan Center';

    protected string $view = 'filament.pages.barcode-scanner';

    public ?array $data = [];

    public bool $bulkMode = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->is_platform_user) {
            return true;
        }

        return app(AccessControlService::class)->moduleEnabled($user->customer_id, 'barcode_scanning')
            && ($user->can('manage inventory') || $user->can('manage documents'));
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('barcode')
                    ->label('Scan or enter a barcode')
                    ->placeholder('e.g. PRD-ACME-000001')
                    ->autofocus()
                    ->extraInputAttributes(['autocomplete' => 'off'])
                    ->required(),
                Toggle::make('bulkMode')
                    ->label('Bulk scan (keep scanning without opening records)')
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(fn ($state) => $this->bulkMode = (bool) $state),
            ])
            ->statePath('data');
    }

    /**
     * Last 20 scans for this tenant, most recent first — gives operators a
     * visible audit trail without leaving the page.
     */
    public function getRecentScansProperty(): Collection
    {
        $user = auth()->user();

        return BarcodeScanLog::query()
            ->when(! $user->is_platform_user, fn ($q) => $q->where('customer_id', $user->customer_id))
            ->latest('scanned_at')
            ->limit(20)
            ->get();
    }

    public function scan(): void
    {
        $barcode = trim((string) ($this->form->getState()['barcode'] ?? ''));

        if ($barcode === '') {
            return;
        }

        $scanner = app(ScannerService::class);
        $outcome = $scanner->scan($barcode, auth()->user());

        $this->dispatch('scan-result', result: $outcome['result']);
        $this->data['barcode'] = '';

        if ($outcome['result'] === 'found' && $outcome['registry']) {
            if ($this->bulkMode) {
                Notification::make()
                    ->title("Found: {$outcome['registry']->barcode}")
                    ->success()
                    ->send();

                return;
            }

            $url = $scanner->recordUrl($outcome['registry']);

            if ($url) {
                $this->redirect($url);

                return;
            }
        }

        if ($outcome['result'] === 'unknown') {
            Notification::make()
                ->title('Unknown barcode')
                ->body("\"{$barcode}\" isn't registered yet. What are you scanning?")
                ->warning()
                ->actions([
                    NotificationAction::make('createDocument')
                        ->label('New Document')
                        ->url(DocumentFileResource::getUrl('create'))
                        ->button(),
                    NotificationAction::make('createBox')
                        ->label('New Box')
                        ->url(BoxResource::getUrl('create'))
                        ->button(),
                ])
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title(match ($outcome['result']) {
                'inactive' => 'Barcode is inactive',
                default => 'Barcode not found',
            })
            ->body("No open record for \"{$barcode}\".")
            ->warning()
            ->send();
    }
}
