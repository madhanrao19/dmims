<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasBarcodeAction;
use App\Filament\Resources\BoxResource\Pages;
use App\Filament\Resources\BoxResource\RelationManagers;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\Box;
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
use Illuminate\Validation\Rules\Unique;
use InvalidArgumentException;

class BoxResource extends BaseResource
{
    use HasBarcodeAction;

    protected static ?string $model = Box::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':document_tracking'];

    protected static bool $applyCustomerScope = true;

    // Boxes are a document-tracking concept (Document Tracking nav group, gated on
    // the document_tracking module), so they use the documents permission — not
    // "manage inventory". With the old value the Document Tracking User and Viewer
    // roles (which hold manage/view documents, not inventory) were locked out.
    protected static ?string $permission = 'manage documents';

    protected static ?string $usageLimitKey = 'max_boxes';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|\UnitEnum|null $navigationGroup = 'Document Tracking';

    protected static ?int $navigationSort = 1;

    public static function getGloballySearchableAttributes(): array
    {
        return ['box_number', 'box_barcode'];
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
                Forms\Components\TextInput::make('box_barcode')->maxLength(150)
                    // Pre-fills from a ?box_barcode= query param, if present
                    // (e.g. a reserved-but-unclaimed barcode from Barcode
                    // Center's "Reserve Labels" — see BarcodeService::claim()).
                    ->default(fn (string $operation): ?string => $operation === 'create' ? request()->query('box_barcode') : null)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('customer_id', $get('customer_id')),
                    )
                    ->validationMessages(['unique' => 'This box barcode is already in use for the selected customer.']),
                Forms\Components\TextInput::make('box_number')->maxLength(100)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('customer_id', $get('customer_id')),
                    )
                    ->validationMessages(['unique' => 'This box number is already in use for the selected customer.']),
                Forms\Components\Select::make('current_location_id')
                    ->label('Current Location')
                    ->relationship('currentLocation', 'location_name')
                    ->getOptionLabelFromRecordUsing(fn (Location $record): string => $record->ancestry_path)
                    ->searchable()
                    ->preload()
                    // Editing this directly here would change the box's
                    // location without going through transferBoxAction()/
                    // moveOutBoxAction()/returnBoxAction() — silently
                    // skipping the DocumentMovementLog entry those write.
                    // Create still sets it freely; corrections after that
                    // go through Transfer/Move Out/Return instead.
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated(fn (string $operation): bool => $operation !== 'edit')
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Use Transfer, Move Out, or Return to change this — keeps movement history accurate.'
                        : null),
                Forms\Components\TextInput::make('source_origin')->maxLength(255),
                Forms\Components\TextInput::make('capacity_limit')->numeric()->helperText('Maximum number of files this box can hold.'),
                Forms\Components\TextInput::make('current_file_count')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Derived automatically from file movements — not editable.'),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'closed' => 'Closed',
                        'moved_out' => 'Moved Out',
                        'archived' => 'Archived',
                        'damaged' => 'Damaged',
                        'missing' => 'Missing',
                    ])
                    ->default('active')
                    // 'active'/'moved_out' are also written by Transfer/Move
                    // Out/Return (DocumentMovementService) and must stay in
                    // sync with current_location_id, which is locked on edit
                    // above — setting either directly here would desync
                    // them. Every other value (closed/archived/damaged/
                    // missing) has no dedicated action and must stay freely
                    // editable here.
                    ->rule(fn (Get $get, string $operation, ?Box $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $operation, $record): void {
                        if ($operation !== 'edit' || $value === $record?->status) {
                            return;
                        }

                        if ($value === 'active' && $get('current_location_id') === null) {
                            $fail('Cannot set Active directly without a location — use Transfer or Return instead.');
                        }

                        if ($value === 'moved_out' && $get('current_location_id') !== null) {
                            $fail('Cannot set Moved Out directly while still placed in a location — use Move Out instead.');
                        }
                    }),
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
     * Deliberate, scoped exception to this app's "no infolist() override"
     * convention (see ViewBox's own doc-comment — every other View page
     * falls back to a read-only form embed). Box's screenshots need
     * TextEntry-shaped breadcrumb/badge/count rendering a disabled form
     * field can't produce without hand-rolled Blade. ViewBox::content()
     * needs no change for this to take effect: Filament's ViewRecord
     * switches from form-embed to infolist-embed automatically the moment
     * this method returns non-empty components.
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identification')
                ->columns(2)
                ->schema([
                    TextEntry::make('box_barcode')->label('Barcode')->fontFamily('mono')->copyable(),
                    TextEntry::make('box_number')->label('Box Number'),
                    TextEntry::make('status')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'active' => 'success',
                            'closed', 'archived' => 'gray',
                            'moved_out' => 'info',
                            'damaged', 'missing' => 'danger',
                            default => 'gray',
                        }),
                    TextEntry::make('source_origin')->label('Origin')->placeholder('—'),
                    TextEntry::make('created_at')->dateTime(),
                ]),
            Section::make('Current Location')
                ->schema([
                    TextEntry::make('location_details')
                        ->label('Exact Physical Path')
                        ->getStateUsing(function (Box $record): string {
                            if (! $record->currentLocation) {
                                return $record->status === 'moved_out' ? 'Dispatched — not currently placed' : 'Not placed';
                            }

                            $nodes = [];
                            $node = $record->currentLocation;
                            $depth = 0;

                            // 50 matches Location::booted()'s own cycle-guard
                            // walk depth — the Location Chain Builder has no
                            // row cap, so a real chain should never be this
                            // deep either; this only bounds a genuinely
                            // corrupted/cyclic tree.
                            while ($node && $depth < 50) {
                                $nodes[] = $node;
                                $node = $node->parent;
                                $depth++;
                            }

                            return collect(array_reverse($nodes))
                                ->map(function (Location $n): string {
                                    $typeLabel = $n->locationType !== null ? $n->locationType->type_name : 'Location';

                                    return e($typeLabel).': '.e($n->location_name).($n->barcode ? ' (Barcode: '.e($n->barcode).')' : '');
                                })
                                ->implode('<br> &#8618; ');
                        })
                        ->html(),
                    TextEntry::make('currentLocation.barcode')->label('Assigned Node Barcode')->badge()->placeholder('—'),
                ]),
            Section::make('External Dispatch Details')
                ->columns(2)
                ->visible(fn (Box $record): bool => $record->status === 'moved_out')
                ->schema([
                    TextEntry::make('dispatch_recipient_name')->label('Recipient / Contact Name')
                        ->getStateUsing(fn (Box $record) => static::latestMoveOutMetadata($record)['recipient_name'] ?? null)
                        ->placeholder('—'),
                    TextEntry::make('dispatch_company')->label('Company / Bank / Vendor Name')
                        ->getStateUsing(function (Box $record) {
                            $log = $record->movementLogs()->where('action_type', 'move_out')->latest('performed_at')->first();

                            return $log?->destination;
                        })
                        ->placeholder('—'),
                    TextEntry::make('dispatch_address')->label('Delivery Address')
                        ->getStateUsing(fn (Box $record) => static::latestMoveOutMetadata($record)['delivery_address'] ?? null)
                        ->placeholder('—'),
                    TextEntry::make('dispatch_tracking_ref')->label('Courier Tracking Ref.')
                        ->getStateUsing(fn (Box $record) => static::latestMoveOutMetadata($record)['courier_tracking_ref'] ?? null)
                        ->placeholder('—'),
                    TextEntry::make('dispatch_expected_return_date')->label('Expected Return Date')
                        ->getStateUsing(fn (Box $record) => static::latestMoveOutMetadata($record)['expected_return_date'] ?? null)
                        ->date()
                        ->placeholder('—'),
                ]),
            Section::make('Contents')
                ->columns(3)
                ->schema([
                    TextEntry::make('files_total')->label('Total Files')
                        ->getStateUsing(fn (Box $record): int => $record->files()->count()),
                    TextEntry::make('files_active')->label('Active')
                        ->getStateUsing(fn (Box $record): int => $record->files()->where('current_status', 'active')->count()),
                    TextEntry::make('files_moved_out')->label('Moved Out')
                        ->getStateUsing(fn (Box $record): int => $record->files()->where('current_status', 'moved_out')->count()),
                ]),
            Section::make('Notes')
                ->schema([
                    TextEntry::make('remarks')->hiddenLabel()->placeholder('No remarks.'),
                ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function latestMoveOutMetadata(Box $record): array
    {
        $log = $record->movementLogs()->where('action_type', 'move_out')->latest('performed_at')->first();

        if (! $log) {
            return [];
        }

        return $log->metadata ?? [];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('box_number')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('box_barcode')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('physical_path')
                    ->label('Location')
                    ->limit(28)
                    ->tooltip(fn (Box $record): string => $record->physical_path)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'closed', 'archived' => 'gray',
                        'moved_out' => 'info',
                        'damaged', 'missing' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('capacity_percent')
                    ->label('Capacity')
                    ->state(fn (Box $record): string => $record->capacity_limit
                        ? "{$record->current_file_count}/{$record->capacity_limit} ({$record->capacity_percent}%)"
                        : "{$record->current_file_count} files")
                    ->badge()
                    ->color(fn (Box $record): string => match (true) {
                        $record->capacity_percent === null => 'gray',
                        $record->capacity_percent >= 100 => 'danger',
                        $record->capacity_percent >= 80 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('tags.name')
                    ->label('Tags')
                    ->badge()
                    ->color(fn (string $state, Box $record): string => $record->tags->firstWhere('name', $state)?->color ?? 'gray'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ])
            // Row click opens View (Edit lives inside View's own header
            // actions) — Filament's default recordUrl only resolves to a
            // 'view'/'edit' *table action* registered below, so this must
            // be explicit rather than relying on getPages().
            ->recordUrl(fn (Box $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    static::transferBoxAction(),
                    static::moveOutBoxAction(),
                    static::returnBoxAction(),
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
     * Extracted from table()'s recordActions so ViewBox/EditBox can also
     * expose it as a header action — a box reached by scanning its barcode
     * (which now lands on the view page) needs Transfer/Move Out/Return
     * available there too, not only from the Boxes list row.
     */
    public static function transferBoxAction(): Action
    {
        return Action::make('transferBox')
            ->label('Transfer')
            ->icon('heroicon-o-arrows-right-left')
            ->visible(fn (Box $record): bool => $record->status !== 'moved_out')
            ->authorize(fn (Box $record): bool => static::can('update', $record))
            ->schema([
                Forms\Components\Select::make('to_location_id')->label('To location')
                    ->options(fn () => static::locationOptions())->searchable()->preload()->required(),
                Forms\Components\Textarea::make('remarks'),
            ])
            ->action(function (Box $record, array $data): void {
                try {
                    app(DocumentMovementService::class)->transferBox($record, (int) $data['to_location_id'], $data);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Cannot transfer box')->body($e->getMessage())->danger()->send();

                    return;
                }
                Notification::make()->title('Box transferred')->success()->send();
            });
    }

    /**
     * Fields match the "Dispatch to External Party" reference. Structured
     * fields (recipient/address/tracking ref/expected return date) are
     * stored in the move-out DocumentMovementLog's `metadata` JSON column,
     * independently readable by infolist()'s "External Dispatch Details"
     * section below — `remarks` stays free text ("Additional Notes") only.
     */
    public static function moveOutBoxAction(): Action
    {
        return Action::make('moveOutBox')
            ->label('Move Out')
            ->modalHeading('Dispatch to External Party')
            ->modalDescription('This will remove the box from its current physical location and mark it as dispatched externally.')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('danger')
            ->visible(fn (Box $record): bool => $record->status !== 'moved_out')
            ->authorize(fn (Box $record): bool => static::can('update', $record))
            ->schema([
                Forms\Components\TextInput::make('recipient_name')->label('Recipient / Contact Name')->required(),
                Forms\Components\TextInput::make('destination')->label('Company / Bank / Vendor Name')->required(),
                Forms\Components\Textarea::make('delivery_address')->label('Delivery Address')->rows(2),
                Forms\Components\TextInput::make('courier_tracking_ref')->label('Courier Tracking Ref.'),
                Forms\Components\DatePicker::make('expected_return_date')->label('Expected Return Date'),
                Forms\Components\Textarea::make('remarks')->label('Additional Notes'),
            ])
            ->action(function (Box $record, array $data): void {
                app(DocumentMovementService::class)->moveOutBox($record, $data['destination'], [
                    ...$data,
                    'metadata' => [
                        'recipient_name' => $data['recipient_name'],
                        'delivery_address' => $data['delivery_address'] ?? null,
                        'courier_tracking_ref' => $data['courier_tracking_ref'] ?? null,
                        'expected_return_date' => $data['expected_return_date'] ?? null,
                    ],
                ]);
                Notification::make()->title('Box moved out')->success()->send();
            });
    }

    public static function returnBoxAction(): Action
    {
        return Action::make('returnBox')
            ->label('Return')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('success')
            ->visible(fn (Box $record): bool => $record->status === 'moved_out')
            ->authorize(fn (Box $record): bool => static::can('update', $record))
            ->schema([
                Forms\Components\Select::make('to_location_id')->label('Return to location')
                    ->options(fn () => static::locationOptions())->searchable()->preload()->required(),
                Forms\Components\Textarea::make('remarks'),
            ])
            ->action(function (Box $record, array $data): void {
                try {
                    app(DocumentMovementService::class)->returnBox($record, (int) $data['to_location_id'], $data);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Cannot return box')->body($e->getMessage())->danger()->send();

                    return;
                }
                Notification::make()->title('Box returned')->success()->send();
            });
    }

    public static function timelineAction(): Action
    {
        return Action::make('timeline')
            ->label('Timeline')
            ->icon('heroicon-o-clock')
            ->modalHeading(fn (Box $record): string => "Activity timeline — Box {$record->box_number}")
            ->modalContent(fn (Box $record) => view('filament.activity-timeline', [
                'entries' => app(MovementTimelineService::class)->forBox($record),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    /**
     * Box View tabs — Documents in this Box / Physical Movement History /
     * System Activity Log render inline on the same page (Filament's
     * standard RelationManager tab strip), not as separate sub-navigation
     * pages — matches the demo-readiness UI/UX request (Sep 2026).
     */
    public static function getRelations(): array
    {
        return [
            RelationManagers\DocumentFilesRelationManager::class,
            RelationManagers\MovementLogRelationManager::class,
            RelationManagers\AuditLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBoxes::route('/'),
            'create' => Pages\CreateBox::route('/create'),
            'view' => Pages\ViewBox::route('/{record}'),
            'edit' => Pages\EditBox::route('/{record}/edit'),
        ];
    }

    /** @return array<int, string> location id => "Room 1 > Area A > Shelf-A01" */
    protected static function locationOptions(): array
    {
        return Location::ancestryPathMap();
    }
}

namespace App\Filament\Resources\BoxResource\Pages;

use App\Filament\Resources\BoxResource;
use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Pages\ListRecords;
use App\Models\Box;
use App\Services\BarcodeService;
use App\Services\DocumentMovementService;
use Filament\Notifications\Notification;
use InvalidArgumentException;

class ListBoxes extends ListRecords
{
    protected static string $resource = BoxResource::class;
}

class CreateBox extends CreateRecord
{
    protected static string $resource = BoxResource::class;

    /**
     * Captured at mount() — see CreateDocumentFile::$fromBarcodeScan for why
     * afterCreate() can't just re-read request()->filled('box_barcode') itself.
     */
    public bool $fromBarcodeScan = false;

    public function mount(): void
    {
        parent::mount();

        $this->fromBarcodeScan = request()->filled('box_barcode');
    }

    /** Same reasoning as CreateDocumentFile::afterCreate() — log the box's
     *  first event through DocumentMovementService::receiveInBox(). */
    protected function afterCreate(): void
    {
        /** @var Box $record */
        $record = $this->record;

        // Attach a reserved-but-unclaimed barcode if one was pre-filled —
        // see BarcodeService::claim(). No-op for a manually-typed barcode.
        // A barcode arriving via the global scan-to-create flow (?box_barcode=
        // on an unknown scan, not a reservation) still needs registering so
        // scanning it again resolves straight to this record — ordinary
        // manual entry deliberately skips this (see registerExisting() doc).
        if (! app(BarcodeService::class)->claim($record) && $this->fromBarcodeScan) {
            app(BarcodeService::class)->registerExisting($record);
        }

        // A box can now be registered before it's placed (Current Location
        // is optional) — nothing to log until it's actually placed.
        if ($record->current_location_id === null) {
            return;
        }

        try {
            app(DocumentMovementService::class)->receiveInBox(
                $record,
                $record->current_location_id,
                $record->source_origin,
            );
        } catch (InvalidArgumentException $e) {
            // The box record itself is already created at this point (this
            // hook runs post-insert) with current_location_id set from the
            // raw create form. Since the receive was rejected, no movement
            // log exists for that placement — clear it the same way
            // moveOutBox() represents "exists, not currently placed
            // anywhere" (null location + 'moved_out'), rather than leaving
            // current_location_id pointing at a location the box was never
            // actually logged into. Recoverable via Transfer once the
            // destination has room.
            $record->update(['current_location_id' => null, 'status' => 'moved_out']);
            Notification::make()->title('Box created but not placed')->body($e->getMessage())->danger()->send();
        }
    }
}

class EditBox extends EditRecord
{
    protected static string $resource = BoxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BoxResource::transferBoxAction(),
            BoxResource::moveOutBoxAction(),
            BoxResource::returnBoxAction(),
            BoxResource::timelineAction(),
            ...parent::getHeaderActions(),
        ];
    }
}
