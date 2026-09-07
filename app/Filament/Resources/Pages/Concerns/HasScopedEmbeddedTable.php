<?php

namespace App\Filament\Resources\Pages\Concerns;

use App\Filament\Resources\BaseResource;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * General-purpose counterpart to
 * App\Filament\Resources\CustomerResource\Pages\Concerns\HasCustomerScopedEmbeddedTable
 * for a record-detail tab that embeds another resource's table scoped to an
 * arbitrary parent record (e.g. a Box's "Documents Inside" tab, scoped by
 * current_box_id) rather than always by customer_id. Kept separate from that
 * trait rather than merged into it, since this one has no create-action
 * scoping (these tabs are read-only history/contents views).
 */
trait HasScopedEmbeddedTable
{
    /** @return class-string<BaseResource> */
    abstract protected static function sourceResource(): string;

    abstract protected function scopeQuery(Builder $query): Builder;

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

    protected function scopedResourceTable(Table $table): Table
    {
        $resource = static::sourceResource();

        $table = $resource::table($table)
            ->query($this->scopeQuery($resource::getEloquentQuery()));

        if (! $table->hasCustomRecordUrl()) {
            $table->recordUrl(function (Model $record) use ($resource): ?string {
                foreach (['view', 'edit'] as $action) {
                    if (! $resource::hasPage($action) || ! $resource::{'can'.ucfirst($action)}($record)) {
                        continue;
                    }

                    return $resource::getUrl($action, ['record' => $record]);
                }

                return null;
            });
        }

        return $table;
    }
}
