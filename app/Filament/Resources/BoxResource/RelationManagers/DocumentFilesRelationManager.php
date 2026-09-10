<?php

namespace App\Filament\Resources\BoxResource\RelationManagers;

use App\Filament\Resources\DocumentFileResource;
use App\Models\Box;
use App\Models\DocumentMovementLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

/**
 * Box View tab: files currently stored in this box. Custom columns rather
 * than reusing DocumentFileResource::table() wholesale — that table is
 * tuned for the general Document Files list (owner, tags, due date) and is
 * missing several columns wanted here (File Reference No, Document Type,
 * Department).
 */
class DocumentFilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    protected static ?string $title = 'Documents in this Box';

    /**
     * ViewBox::scanDocument() dispatches 'box-documents-updated' after
     * assigning a scanned file into this box — this listener is what
     * actually makes that refresh this table (Livewire re-renders a
     * component on any caught event, even one whose handler does nothing).
     */
    #[On('box-documents-updated')]
    public function refreshAfterScan(): void
    {
        //
    }

    public function table(Table $table): Table
    {
        /** @var Box $box */
        $box = $this->getOwnerRecord();

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
