<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasBarcodeAction;
use App\Filament\Resources\DocumentFileResource\Pages;
use App\Filament\Resources\DocumentFileResource\RelationManagers;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\Box;
use App\Models\Department;
use App\Models\DocumentFile;
use App\Models\Location;
use App\Services\DocumentMovementService;
use App\Services\MovementTimelineService;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;
use InvalidArgumentException;

class DocumentFileResource extends BaseResource
{
    use HasBarcodeAction;

    protected static ?string $model = DocumentFile::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':document_tracking'];

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage documents';

    protected static ?string $usageLimitKey = 'max_document_files';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Documents';

    protected static ?int $navigationSort = 2;

    public static function getGloballySearchableAttributes(): array
    {
        return ['file_barcode', 'file_reference_no', 'title', 'owner_name'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->preload()
                    ->default(fn (): ?int => auth()->user()?->is_platform_user ? null : auth()->user()?->customer_id)
                    ->required()
                    ->visible(fn (): bool => (bool) auth()->user()?->is_platform_user),
                Forms\Components\TextInput::make('file_barcode')->maxLength(150)
                    // Pre-fills from a ?file_barcode= query param, if present
                    // (e.g. a reserved-but-unclaimed barcode from Barcode
                    // Center's "Reserve Labels" — see BarcodeService::claim()).
                    ->default(fn (string $operation): ?string => $operation === 'create' ? request()->query('file_barcode') : null)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('customer_id', $get('customer_id')),
                    )
                    ->validationMessages(['unique' => 'This file barcode is already in use for the selected customer.']),
                Forms\Components\TextInput::make('file_reference_no')->maxLength(150),
                Forms\Components\TextInput::make('title')->maxLength(255),
                Forms\Components\Select::make('document_type_id')
                    ->label('Document Type')
                    ->relationship('documentType', 'type_name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    // Department has no seed data — a customer with none
                    // configured yet previously saw an empty, unexplained
                    // dropdown with no way to fix it (the actual root cause
                    // reported as "the Department dropdown doesn't work").
                    ->helperText(fn (): ?string => Department::query()->exists()
                        ? null
                        : (DepartmentResource::canAccess()
                            ? new HtmlString('No departments configured yet. <a href="'.e(DepartmentResource::getUrl('create')).'" class="underline text-primary-600">Add one</a> first.')
                            : 'No departments configured yet. Ask an administrator to set one up.')),
                Forms\Components\TextInput::make('owner_name')->maxLength(255),
                Forms\Components\Select::make('current_box_id')
                    ->label('Box Assignment')
                    ->relationship('currentBox', 'box_number')
                    ->searchable(['box_number', 'box_barcode'])
                    ->preload()
                    ->live()
                    // Editing this directly here would change the file's
                    // box without going through transferFileAction()/
                    // moveOutFileAction()/returnFileAction() — silently
                    // skipping the DocumentMovementLog entry and box
                    // file-count adjustment those write. Create still sets
                    // it freely (optional); corrections after that go
                    // through Transfer/Move Out/Return instead.
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated(fn (string $operation): bool => $operation !== 'edit')
                    ->helperText(function (string $operation, Get $get): string {
                        if ($operation === 'edit') {
                            return 'Use Transfer, Move Out, or Return to change this — keeps movement history accurate.';
                        }

                        $boxId = $get('current_box_id');
                        $location = $boxId ? Box::find($boxId)?->currentLocation?->ancestry_path : null;

                        return $location
                            ? "Storage location: {$location}"
                            : 'Optional — files can be registered before being boxed.';
                    }),
                Forms\Components\Select::make('current_status')
                    ->options([
                        'active' => 'Active',
                        'transferred' => 'Transferred',
                        'moved_out' => 'Moved Out',
                        'archived' => 'Archived',
                        'missing' => 'Missing',
                        'damaged' => 'Damaged',
                        'closed' => 'Closed',
                    ])
                    ->default('active')
                    // 'active'/'moved_out' are also written by Transfer/Move
                    // Out/Return (DocumentMovementService) and must stay in
                    // sync with current_box_id, which is locked on edit above
                    // — setting either directly here would desync them (e.g.
                    // 'moved_out' while current_box_id still points at a
                    // box). Every other value (archived/missing/damaged/
                    // closed/transferred) has no dedicated action and must
                    // stay freely editable here.
                    ->rule(fn (Get $get, string $operation, ?DocumentFile $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $operation, $record): void {
                        if ($operation !== 'edit' || $value === $record?->current_status) {
                            return;
                        }

                        if ($value === 'active' && $get('current_box_id') === null) {
                            $fail('Cannot set Active directly without a box — use Transfer or Return instead.');
                        }

                        if ($value === 'moved_out' && $get('current_box_id') !== null) {
                            $fail('Cannot set Moved Out directly while still boxed — use Move Out instead.');
                        }
                    }),
                Forms\Components\TextInput::make('source_origin')->maxLength(255),
                Forms\Components\TextInput::make('destination')->maxLength(255),
                Forms\Components\DatePicker::make('received_date')
                    // Auto-fills today's date when arriving via the scan-to-create
                    // shortcut (barcode prefilled from an unregistered scan).
                    ->default(fn (string $operation): ?string => $operation === 'create' && request()->filled('file_barcode') ? now()->toDateString() : null),
                Forms\Components\DatePicker::make('archived_date'),
                Forms\Components\Select::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')->required(),
                        Forms\Components\ColorPicker::make('color')->default('#6b7280'),
                    ]),
                Forms\Components\Textarea::make('remarks')->rows(3),
            ]);
    }

    /**
     * Matches the reference screenshot's "Document Details"/"Physical
     * Location"/"Notes" card layout — same scoped exception to this app's
     * "no infolist() override" convention as BoxResource::infolist() (see
     * that method's own doc-comment). ViewDocumentFile needs no change for
     * this to take effect.
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Document Details')
                ->columns(3)
                ->schema([
                    TextEntry::make('file_barcode')->label('Barcode')->fontFamily('mono')->copyable(),
                    TextEntry::make('title')->label('Title'),
                    TextEntry::make('current_status')
                        ->label('Status')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'active' => 'success',
                            'transferred' => 'info',
                            'moved_out' => 'warning',
                            'archived', 'closed' => 'gray',
                            'missing', 'damaged' => 'danger',
                            default => 'gray',
                        }),
                    TextEntry::make('file_reference_no')->label('Reference')->placeholder('—'),
                    TextEntry::make('received_date')->label('Doc Date')->date()->placeholder('—'),
                    TextEntry::make('source_origin')->label('Origin')->placeholder('—'),
                    TextEntry::make('creator.name')->label('Created By')->placeholder('—'),
                    TextEntry::make('created_at')->label('Created')->dateTime(),
                ]),
            Section::make('Physical Location')
                ->columns(2)
                ->schema([
                    TextEntry::make('currentBox.box_barcode')->label('Box Barcode')->placeholder('—'),
                    TextEntry::make('currentBox.status')
                        ->label('Box Status')
                        ->badge()
                        ->placeholder('—')
                        ->color(fn (?string $state): string => match ($state) {
                            'active' => 'success',
                            'closed', 'archived' => 'gray',
                            'moved_out' => 'info',
                            'damaged', 'missing' => 'danger',
                            default => 'gray',
                        }),
                    TextEntry::make('physical_path')
                        ->label('Rack Location (Full Path)')
                        ->columnSpanFull(),
                ]),
            Section::make('Notes')
                ->schema([
                    TextEntry::make('remarks')->hiddenLabel()->placeholder('No remarks.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('file_barcode')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('title')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('owner_name')->label('Owner')->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('currentBox.box_number')->label('Box')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('physical_path')
                    ->label('Location')
                    ->limit(28)
                    ->tooltip(fn (DocumentFile $record): string => $record->physical_path)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('current_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'transferred' => 'info',
                        'moved_out' => 'warning',
                        'archived', 'closed' => 'gray',
                        'missing', 'damaged' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('tags.name')
                    ->label('Tags')
                    ->badge()
                    ->color(fn (string $state, DocumentFile $record): string => $record->tags->firstWhere('name', $state)?->color ?? 'gray'),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due back')
                    ->date()
                    ->placeholder('—')
                    ->color(fn (DocumentFile $record): string => $record->is_overdue ? 'danger' : 'gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue returns')
                    ->query(fn ($query) => $query->where('current_status', 'moved_out')->whereNotNull('due_date')->whereDate('due_date', '<', now())),
                Tables\Filters\SelectFilter::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('current_status')
                    ->options([
                        'active' => 'Active',
                        'transferred' => 'Transferred',
                        'moved_out' => 'Moved Out',
                        'archived' => 'Archived',
                        'missing' => 'Missing',
                        'damaged' => 'Damaged',
                        'closed' => 'Closed',
                    ]),
                Tables\Filters\SelectFilter::make('department_id')
                    ->relationship('department', 'name')
                    ->label('Department')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('current_box_id')
                    ->relationship('currentBox', 'box_number')
                    ->label('Box')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('warehouse')
                    ->label('Warehouse/Shelf')
                    ->searchable()
                    ->preload()
                    ->options(fn () => Location::query()->pluck('location_name', 'id')->all())
                    ->query(function ($query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            fn ($q, $locationId) => $q->whereHas('currentBox', fn ($bq) => $bq->where('current_location_id', $locationId))
                        );
                    }),
                Tables\Filters\Filter::make('received_date')
                    ->schema([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('received_date', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('received_date', '<=', $date));
                    }),
            ])
            // Row click opens View (Edit lives inside View's own header
            // actions, matching Boxes) — Filament's default recordUrl only
            // resolves to a 'view'/'edit' *table action* registered below,
            // so this must be explicit rather than relying on getPages().
            ->recordUrl(fn (DocumentFile $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    static::transferFileAction(),
                    static::moveOutFileAction(),
                    static::returnFileAction(),
                    static::timelineAction(),
                    static::barcodeAction(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    static::bulkBarcodeAction(),
                    DeleteBulkAction::make()
                        ->action(fn (Collection $records) => static::deleteSelectedWithReport($records)),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Extracted from table()'s recordActions so ViewDocumentFile/EditDocumentFile
     * can also expose it as a header action — a file reached by scanning its
     * barcode (which now lands on the view page) needs Transfer/Move Out/
     * Return available there too, not only from the Document Files list row.
     */
    public static function transferFileAction(): Action
    {
        return Action::make('transferFile')
            ->label('Transfer')
            ->icon('heroicon-o-arrows-right-left')
            ->visible(fn (DocumentFile $record): bool => $record->current_status !== 'moved_out')
            ->authorize(fn (DocumentFile $record): bool => static::can('update', $record))
            ->schema([
                static::boxSelect('to_box_id', 'To box'),
                Forms\Components\Textarea::make('remarks'),
            ])
            ->action(function (DocumentFile $record, array $data): void {
                try {
                    app(DocumentMovementService::class)->transferFile($record, (int) $data['to_box_id'], $data);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Cannot transfer file')->body($e->getMessage())->danger()->send();

                    return;
                }
                Notification::make()->title('File transferred')->success()->send();
            });
    }

    /**
     * Fields match the "Dispatch to External Party" reference exactly
     * (Recipient/Contact Name, Company/Vendor, Delivery Address, Courier
     * Tracking Ref, Expected Return Date, Additional Notes). Only
     * `destination`/`borrowed_by`/`due_date` have dedicated document_files
     * columns; the rest have no schema home of their own, so they're folded
     * into `remarks` (already flows into DocumentMovementLog and shows on
     * the Timeline action) rather than adding new columns for a UI-only
     * request — no movement/return business rule needs them as separate
     * fields today.
     */
    public static function moveOutFileAction(): Action
    {
        return Action::make('moveOutFile')
            ->label('Move Out')
            ->modalHeading('Dispatch to External Party')
            ->modalDescription('This will remove the file from its current box and mark it as dispatched externally.')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('danger')
            ->visible(fn (DocumentFile $record): bool => $record->current_status !== 'moved_out')
            ->authorize(fn (DocumentFile $record): bool => static::can('update', $record))
            ->schema([
                Forms\Components\TextInput::make('borrowed_by')->label('Recipient / Contact Name')->required(),
                Forms\Components\TextInput::make('destination')->label('Company / Bank / Vendor Name')->required(),
                Forms\Components\Textarea::make('delivery_address')->label('Delivery Address')->rows(2),
                Forms\Components\TextInput::make('courier_tracking_ref')->label('Courier Tracking Ref.'),
                Forms\Components\DatePicker::make('due_date')->label('Expected Return Date'),
                Forms\Components\Textarea::make('remarks')->label('Additional Notes'),
            ])
            ->action(function (DocumentFile $record, array $data): void {
                app(DocumentMovementService::class)->moveOutFile($record, $data['destination'], [
                    ...$data,
                    'remarks' => static::composeDispatchRemarks($data),
                ]);
                Notification::make()->title('File moved out')->success()->send();
            });
    }

    /** @param array<string, mixed> $data */
    protected static function composeDispatchRemarks(array $data): ?string
    {
        $lines = array_filter([
            filled($data['delivery_address'] ?? null) ? "Delivery address: {$data['delivery_address']}" : null,
            filled($data['courier_tracking_ref'] ?? null) ? "Courier tracking ref: {$data['courier_tracking_ref']}" : null,
            filled($data['remarks'] ?? null) ? $data['remarks'] : null,
        ]);

        return $lines === [] ? null : implode("\n", $lines);
    }

    public static function returnFileAction(): Action
    {
        return Action::make('returnFile')
            ->label('Return')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('success')
            ->visible(fn (DocumentFile $record): bool => $record->current_status === 'moved_out')
            ->authorize(fn (DocumentFile $record): bool => static::can('update', $record))
            ->schema([
                static::boxSelect('to_box_id', 'Return to box'),
                Forms\Components\Textarea::make('remarks'),
            ])
            ->action(function (DocumentFile $record, array $data): void {
                try {
                    app(DocumentMovementService::class)->returnFile($record, (int) $data['to_box_id'], $data);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Cannot return file')->body($e->getMessage())->danger()->send();

                    return;
                }
                Notification::make()->title('File returned')->success()->send();
            });
    }

    public static function timelineAction(): Action
    {
        return Action::make('timeline')
            ->label('Timeline')
            ->icon('heroicon-o-clock')
            ->modalHeading(fn (DocumentFile $record): string => "Activity timeline — {$record->title}")
            ->modalContent(fn (DocumentFile $record) => view('filament.activity-timeline', [
                'entries' => app(MovementTimelineService::class)->forDocumentFile($record),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    /**
     * Document File View tabs — Physical Movement History / System Activity
     * Log render inline on the same page (Filament's native RelationManager
     * tab strip), not as separate sub-navigation pages. Mirrors
     * BoxResource::getRelations() exactly.
     */
    public static function getRelations(): array
    {
        return [
            RelationManagers\MovementLogRelationManager::class,
            RelationManagers\AuditLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentFiles::route('/'),
            'create' => Pages\CreateDocumentFile::route('/create'),
            'view' => Pages\ViewDocumentFile::route('/{record}'),
            'edit' => Pages\EditDocumentFile::route('/{record}/edit'),
        ];
    }

    /**
     * Searches box_number AND box_barcode so a handheld scanner's input
     * (which types the barcode, not the box number) resolves to a match.
     */
    protected static function boxSelect(string $name, string $label): Forms\Components\Select
    {
        return Forms\Components\Select::make($name)
            ->label($label)
            ->searchable(['box_number', 'box_barcode'])
            ->getSearchResultsUsing(fn (string $search): array => Box::searchByNumberOrBarcode($search)->all())
            ->getOptionLabelUsing(fn ($value): ?string => Box::find($value)?->box_number)
            ->required();
    }
}

namespace App\Filament\Resources\DocumentFileResource\Pages;

use App\Filament\Resources\DocumentFileResource;
use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Pages\ListRecords;
use App\Models\DocumentFile;
use App\Services\BarcodeService;
use App\Services\DocumentMovementService;
use Filament\Notifications\Notification;
use InvalidArgumentException;

class ListDocumentFiles extends ListRecords
{
    protected static string $resource = DocumentFileResource::class;
}

class CreateDocumentFile extends CreateRecord
{
    protected static string $resource = DocumentFileResource::class;

    /**
     * Captured at mount() — the only point in the request lifecycle where
     * request()->query() still reflects this page's own URL; by afterCreate()
     * the create action has gone through a separate Livewire AJAX request
     * with no query string of its own, so this flag (a public Livewire
     * property, preserved across that round trip) is what afterCreate() must
     * check instead of re-reading request()->filled('file_barcode') there.
     */
    public bool $fromBarcodeScan = false;

    public function mount(): void
    {
        parent::mount();

        $this->fromBarcodeScan = request()->filled('file_barcode');
    }

    /**
     * Route creation through the same guided-workflow logging as every other
     * document movement (DocumentMovementService::receiveInFile), rather
     * than leaving the file's first event unlogged and the containing box's
     * current_file_count un-incremented.
     */
    protected function afterCreate(): void
    {
        /** @var DocumentFile $record */
        $record = $this->record;

        // Attach a reserved-but-unclaimed barcode if one was pre-filled —
        // see BarcodeService::claim(). No-op for a manually-typed barcode.
        // A barcode arriving via the global scan-to-create flow (?file_barcode=
        // on an unknown scan, not a reservation) still needs registering so
        // scanning it again resolves straight to this record — ordinary
        // manual entry deliberately skips this (see registerExisting() doc).
        if (! app(BarcodeService::class)->claim($record) && $this->fromBarcodeScan) {
            app(BarcodeService::class)->registerExisting($record);
        }

        // A file can now be registered before it's boxed (Current Box is
        // optional) — nothing to log/count until it's actually assigned.
        if ($record->current_box_id === null) {
            return;
        }

        try {
            app(DocumentMovementService::class)->receiveInFile($record, $record->current_box_id);
        } catch (InvalidArgumentException $e) {
            // The file record itself is already created (this hook runs
            // post-insert) with current_box_id set from the raw create
            // form. Since the receive was rejected, no movement log exists
            // for that placement — clear it back to unboxed rather than
            // leaving current_box_id pointing at a box the file was never
            // actually logged into (the same invariant Edit's current_box_id
            // lock protects). Recoverable via Transfer once the box has room.
            $record->update(['current_box_id' => null]);
            Notification::make()->title('File created but not boxed')->body($e->getMessage())->danger()->send();
        }
    }
}

class EditDocumentFile extends EditRecord
{
    protected static string $resource = DocumentFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DocumentFileResource::transferFileAction(),
            DocumentFileResource::moveOutFileAction(),
            DocumentFileResource::returnFileAction(),
            DocumentFileResource::timelineAction(),
            ...parent::getHeaderActions(),
        ];
    }
}
