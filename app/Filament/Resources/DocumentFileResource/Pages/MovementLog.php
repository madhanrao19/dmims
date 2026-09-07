<?php

namespace App\Filament\Resources\DocumentFileResource\Pages;

use App\Filament\Resources\DocumentFileResource;
use App\Filament\Resources\DocumentMovementLogResource;
use App\Filament\Resources\Pages\Concerns\HasScopedEmbeddedTable;
use App\Models\DocumentFile;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Document File detail tab: physical movements and box assignments over
 * time, embedding DocumentMovementLogResource's table scoped to this file.
 */
class MovementLog extends Page implements HasTable
{
    use HasScopedEmbeddedTable;
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = DocumentFileResource::class;

    protected static ?string $navigationLabel = 'Movement Log';

    protected static ?string $title = 'Document Movement Log';

    public static function canAccess(array $parameters = []): bool
    {
        return DocumentFileResource::can('view', $parameters['record'] ?? null);
    }

    protected static function sourceResource(): string
    {
        return DocumentMovementLogResource::class;
    }

    protected function scopeQuery(Builder $query): Builder
    {
        /** @var DocumentFile $file */
        $file = $this->getRecord();

        return $query->where('movable_type', 'document_file')->where('movable_id', $file->id);
    }

    public function table(Table $table): Table
    {
        return $this->scopedResourceTable($table);
    }
}
