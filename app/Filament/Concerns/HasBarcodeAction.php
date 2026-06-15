<?php

namespace App\Filament\Concerns;

use App\Services\BarcodeService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Adds a "Barcode" table action to a resource: it generates and registers the
 * record's barcode on first use (idempotent), shows a printable label, and
 * records each print.
 */
trait HasBarcodeAction
{
    public static function barcodeAction(): Action
    {
        return Action::make('barcode')
            ->label('Barcode')
            ->icon('heroicon-o-qr-code')
            ->modalHeading('Barcode label')
            ->modalSubmitActionLabel('Mark as printed')
            ->modalContent(function (Model $record) {
                $registry = app(BarcodeService::class)->registerFor($record);

                return view('filament.barcode-label', [
                    'barcode' => $registry->barcode,
                    'type' => $registry->barcode_type,
                ]);
            })
            ->action(function (Model $record): void {
                $registry = app(BarcodeService::class)->registerFor($record);
                app(BarcodeService::class)->incrementPrinted($registry);

                Notification::make()
                    ->title("Barcode printed: {$registry->barcode}")
                    ->success()
                    ->send();
            });
    }
}
