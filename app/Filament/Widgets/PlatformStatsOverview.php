<?php

namespace App\Filament\Widgets;

use App\Models\BarcodeRegistry;
use App\Models\Customer;
use App\Models\DocumentFile;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class PlatformStatsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'DMIMS Platform Summary';

    protected function getCards(): array
    {
        return [
            Card::make('Customers', Customer::count()),
            Card::make('Active Products', Product::where('status', 'active')->count()),
            Card::make('Documents', DocumentFile::count()),
            Card::make('Barcodes', BarcodeRegistry::count()),
        ];
    }
}
