<?php

namespace App\Filament\Pages;

use App\Services\AccessControlService;
use App\Services\ReportExportService;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Shared Services';

    protected static ?string $title = 'Reports';

    protected static string $view = 'filament.pages.reports';

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

    public function form(Form $form): Form
    {
        $options = [];
        foreach (ReportExportService::availableTo(auth()->user()) as $key => $def) {
            $options[$key] = "{$def['group']} — {$def['label']}";
        }

        return $form
            ->schema([
                Select::make('report')
                    ->label('Report')
                    ->options($options)
                    ->searchable()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function download(): StreamedResponse
    {
        $key = $this->form->getState()['report'];

        // Guard against requesting a report the user is not entitled to.
        abort_unless(array_key_exists($key, ReportExportService::availableTo(auth()->user())), 403);

        return app(ReportExportService::class)->generate($key);
    }
}
