<?php

namespace App\Filament\Resources\BoxResource\Pages;

use App\Filament\Resources\BoxResource;
use App\Filament\Resources\DocumentFileResource;
use App\Models\Box;
use App\Models\DocumentMovementLog;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Box detail tab: files currently stored in this box. Custom columns rather
 * than reusing DocumentFileResource::table() wholesale — that table is
 * tuned for the general Document Files list (owner, tags, due date) and is
 * missing several columns the ticket asks for here (File Reference No,
 * Document Type, Department), so it isn't a drop-in fit.
 */
class Documents extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = BoxResource::class;

    protected static ?string $navigationLabel = 'Documents Inside';

    protected static ?string $title = 'Documents Inside';

    public static function canAccess(array $parameters = []): bool
    {
        return BoxResource::can('view', $parameters['record'] ?? null);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var Box $box */
        $box = $this->getRecord();

        // A per-row DocumentMovementLog query in the column's ->state()
        // closure would be an N+1 (one extra query per file in the box) —
        // pull "date added to this box" in with the main query instead.
        $dateAddedSubquery = DocumentMovementLog::query()
            ->selectRaw('MAX(performed_at)')
            ->where('movable_type', 'document_file')
            ->where('to_box_id', $box->id)
            ->whereColumn('movable_id', 'document_files.id');

        return $table
            ->query(DocumentFileResource::getEloquentQuery()
                ->where('current_box_id', $box->id)
                ->addSelect(['date_added' => $dateAddedSubquery]))
            ->columns([
                TextColumn::make('file_barcode')->label('File Barcode')->sortable()->searchable(),
                TextColumn::make('file_reference_no')->label('File Reference No')->sortable()->searchable(),
                TextColumn::make('title')->sortable()->searchable(),
                TextColumn::make('documentType.type_name')->label('Document Type')->sortable(),
                TextColumn::make('department.name')->label('Department')->sortable(),
                TextColumn::make('current_status')->label('Status')->badge(),
                TextColumn::make('date_added')->label('Date Added')->dateTime()->sortable(),
            ])
            ->recordUrl(fn (Model $record): string => DocumentFileResource::hasPage('view')
                ? DocumentFileResource::getUrl('view', ['record' => $record])
                : DocumentFileResource::getUrl('edit', ['record' => $record]));
    }
}
