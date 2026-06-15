<?php

namespace App\Filament\Pages;

use App\Services\AccessControlService;
use App\Services\ScannerService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class BarcodeScanner extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static string|\UnitEnum|null $navigationGroup = 'Shared Services';

    protected static ?string $title = 'Barcode Scanner';

    protected string $view = 'filament.pages.barcode-scanner';

    public ?array $data = [];

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
                    ->required(),
            ])
            ->statePath('data');
    }

    public function scan(): void
    {
        $barcode = trim((string) ($this->form->getState()['barcode'] ?? ''));

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

        Notification::make()
            ->title(match ($outcome['result']) {
                'inactive' => 'Barcode is inactive',
                default => 'Barcode not found',
            })
            ->body("No open record for \"{$barcode}\".")
            ->warning()
            ->send();

        $this->data['barcode'] = '';
    }
}
