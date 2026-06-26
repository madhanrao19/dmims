<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExportResource\Pages;
use App\Jobs\RunExport;
use App\Models\Export;
use App\Services\ExportService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportResource extends BaseResource
{
    protected static ?string $model = Export::class;

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('export_no')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('customer.company_name')->label('Company')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('export_type')->badge()->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'processing', 'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
            ])
            ->headerActions([
                Action::make('newExport')
                    ->label('New Export')
                    ->icon('heroicon-o-plus')
                    ->schema([
                        Forms\Components\Select::make('export_type')
                            ->label('Data to export')
                            ->options(collect(array_keys(ExportService::exportableTypes()))
                                ->mapWithKeys(fn (string $t) => [$t => str($t)->headline()->toString()])
                                ->all())
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $user = auth()->user();
                        $customerId = $user->is_platform_user ? null : $user->customer_id;

                        $export = app(ExportService::class)->createPending($data['export_type'], $customerId);
                        RunExport::dispatch($export);

                        Notification::make()
                            ->title('Export queued')
                            ->body("Export {$export->export_no} has been queued and will be ready shortly.")
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Export $record): bool => $record->status === 'completed' && filled($record->file_path))
                    ->action(fn (Export $record): StreamedResponse => Storage::disk('local')->download($record->file_path, $record->file_name)),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExports::route('/'),
        ];
    }
}

namespace App\Filament\Resources\ExportResource\Pages;

use App\Filament\Resources\ExportResource;
use Filament\Resources\Pages\ListRecords;

class ListExports extends ListRecords
{
    protected static string $resource = ExportResource::class;
}
