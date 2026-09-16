<?php

namespace App\Filament\Clusters\MyCompany\Pages;

use App\Filament\Clusters\MyCompany;
use App\Filament\Clusters\MyCompany\Pages\Concerns\HasEmbeddedResourceTable;
use App\Filament\Resources\UserResource;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class CompanyUsers extends Page implements HasTable
{
    use HasEmbeddedResourceTable;
    use InteractsWithTable;

    protected static ?string $cluster = MyCompany::class;

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $title = 'Company Users';

    protected static function sourceResource(): string
    {
        return UserResource::class;
    }

    public function table(Table $table): Table
    {
        return $this->embeddedResourceTable($table);
    }

    /**
     * Page-level header actions (Filament\Pages\Concerns\
     * InteractsWithHeaderActions) — NOT Table::headerActions(), which
     * renders inside the table's own Livewire container and never appeared
     * on this plain (non-ListRecords) Page. Mirrors the fix already applied
     * to every resource's List page (see App\Filament\Resources\Pages\
     * ListRecords's own docblock: "Filament never auto-adds a Create
     * button... no List page anywhere had a working Create button").
     *
     * A link to UserResource's own hardened create page, not a plain
     * CreateAction — that page's CreateUser::mutateFormDataBeforeCreate()/
     * afterCreate() are what force the new user's customer_id back to this
     * actor's own company and strip any platform role a Company Admin
     * shouldn't be able to grant. A bare CreateAction here, using
     * UserResource::form() directly the way HasEmbeddedResourceTable's
     * default schema resolver does, would skip both of those Filament
     * page-lifecycle hooks entirely (they only fire on a real CreateRecord
     * page) — reusing the existing page is what keeps them in force.
     * Without this, "My Company > Users" had no way at all to add a user —
     * the standalone /admin/users/create route still worked, but nothing
     * on this page linked to it.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('addUser')
                ->label('Add User')
                ->icon('heroicon-o-plus')
                ->url(fn (): string => UserResource::getUrl('create'))
                ->authorize(fn (): bool => UserResource::can('create')),
        ];
    }
}
