<?php

namespace App\Filament\Resources\BoxResource\Pages;

use App\Filament\Resources\BoxResource;
use App\Filament\Resources\DocumentMovementLogResource;
use App\Filament\Resources\Pages\Concerns\HasScopedEmbeddedTable;
use App\Models\Box;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Box detail tab: location transfer and dispatch history, embedding
 * DocumentMovementLogResource's table scoped to this box.
 */
class MovementLog extends Page implements HasTable
{
    use HasScopedEmbeddedTable;
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = BoxResource::class;

    protected static ?string $navigationLabel = 'Box Movement Log';

    protected static ?string $title = 'Box Movement Log';

    public static function canAccess(array $parameters = []): bool
    {
        return BoxResource::can('view', $parameters['record'] ?? null);
    }

    protected static function sourceResource(): string
    {
        return DocumentMovementLogResource::class;
    }

    protected function scopeQuery(Builder $query): Builder
    {
        /** @var Box $box */
        $box = $this->getRecord();

        return $query->where('movable_type', 'box')->where('movable_id', $box->id);
    }

    public function table(Table $table): Table
    {
        return $this->scopedResourceTable($table);
    }
}
