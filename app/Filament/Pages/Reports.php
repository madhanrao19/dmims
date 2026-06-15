<?php

namespace App\Filament\Pages;

use App\Services\AccessControlService;
use App\Services\ReportExportService;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Shared Services';

    protected static ?string $title = 'Reports';

    protected string $view = 'filament.pages.reports';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->is_platform_user) {
            return true;
        }

        return $user->can('view reports')
            && app(AccessControlService::class)->moduleEnabled($user->customer_id, 'reports');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        $options = [];
        foreach (ReportExportService::availableTo(auth()->user()) as $key => $def) {
            $options[$key] = "{$def['group']} — {$def['label']}";
        }

        return $schema
            ->components([
                Select::make('report')
                    ->label('Report')
                    ->options($options)
                    ->searchable()
                    ->required(),
                Select::make('format')
                    ->label('Format')
                    ->options(['csv' => 'CSV', 'xlsx' => 'Excel (XLSX)', 'pdf' => 'PDF'])
                    ->default('csv')
                    ->required(),
            ])
            ->statePath('data');
    }

    public function download(): Response|StreamedResponse
    {
        $state = $this->form->getState();
        $key = $state['report'];
        $format = $state['format'] ?? 'csv';

        // Guard against requesting a report the user is not entitled to.
        abort_unless(array_key_exists($key, ReportExportService::availableTo(auth()->user())), 403);

        return app(ReportExportService::class)->generate($key, $format);
    }
}
