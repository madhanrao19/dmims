<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BackupResource\Pages;
use App\Models\Backup;
use App\Services\BackupService;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupResource extends BaseResource
{
    protected static ?string $model = Backup::class;

    protected static bool $applyCustomerScope = false;

    protected static ?string $permission = 'manage settings';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('backup_no')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('backup_type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success', 'restored' => 'success',
                        'running', 'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('file_size')
                    ->label('Size')
                    ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 1024, 1).' KB' : '—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('started_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('completed_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'running' => 'Running',
                        'success' => 'Success',
                        'failed' => 'Failed',
                        'restored' => 'Restored',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('runBackup')
                    ->label('Run Database Backup')
                    ->icon('heroicon-o-play')
                    ->requiresConfirmation()
                    ->modalDescription('This will create a full backup of the application database.')
                    ->action(function (): void {
                        try {
                            $backup = app(BackupService::class)->backupDatabase();
                            Notification::make()
                                ->title('Backup completed')
                                ->body("Backup {$backup->backup_no} created successfully.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Backup failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Backup $record): bool => $record->status === 'success' && filled($record->file_path))
                    ->action(fn (Backup $record): StreamedResponse => Storage::disk($record->storage_location ?? 'local')->download($record->file_path)),
                Tables\Actions\Action::make('restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Backup $record): bool => in_array($record->status, ['success', 'restored'], true) && filled($record->file_path))
                    ->requiresConfirmation()
                    ->modalHeading('Restore database from backup')
                    ->modalDescription('This will OVERWRITE the current database with the contents of this backup. This cannot be undone.')
                    ->action(function (Backup $record): void {
                        try {
                            app(BackupService::class)->restoreDatabase($record);
                            Notification::make()->title('Database restored')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Restore failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBackups::route('/'),
        ];
    }
}

namespace App\Filament\Resources\BackupResource\Pages;

use App\Filament\Resources\BackupResource;
use Filament\Resources\Pages\ListRecords;

class ListBackups extends ListRecords
{
    protected static string $resource = BackupResource::class;
}
