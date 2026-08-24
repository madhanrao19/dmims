<?php

namespace App\Filament\Clusters\MyCompany\Pages;

use App\Filament\Clusters\MyCompany;
use App\Filament\Clusters\MyCompany\Pages\Concerns\HasEmbeddedResourceTable;
use App\Filament\Resources\UserResource;
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
}
