<?php

namespace App\Filament\Clusters\MyCompany\Pages;

use App\Filament\Clusters\MyCompany;
use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

/**
 * Security & Access Control Matrix §5: "Own Company Profile: View" for
 * Company Admin/Supervisor — read-only, so every field is disabled rather
 * than reusing CustomerResource's edit page (which requires `manage
 * customers`, not held by either role). Field list mirrors
 * CustomerResource::form() for consistency; no validation/authorization
 * logic is duplicated, only labels for a read-only display.
 *
 * @property Schema $form
 */
class Overview extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = MyCompany::class;

    protected static ?string $navigationLabel = 'Profile';

    protected static ?string $title = 'Company Profile';

    public ?array $data = [];

    /**
     * Security review finding (24 August 2026): the single source of truth
     * for which Customer fields this read-only page may expose. Filling the
     * form from the model's full attributesToArray() put every column
     * (including `notes` — internal Datamation commentary about the
     * tenant — and `deployment_type`) into the public Livewire $data
     * property, which is serialised to the browser regardless of which
     * fields the form actually renders as disabled inputs. Both mount()
     * and form() are driven from this one list so they can't drift apart.
     */
    private const VISIBLE_FIELDS = [
        'company_name', 'company_code', 'registration_no', 'tin_no',
        'contact_person', 'email', 'phone', 'address', 'status',
    ];

    private static function ownCompany(): ?Customer
    {
        $customerId = auth()->user()?->customer_id;

        return $customerId ? Customer::find($customerId) : null;
    }

    public static function canAccess(): bool
    {
        $customer = self::ownCompany();

        return $customer !== null && CustomerResource::can('view', $customer);
    }

    public function mount(): void
    {
        $customer = self::ownCompany();

        $this->form->fill($customer ? Arr::only($customer->attributesToArray(), self::VISIBLE_FIELDS) : []);
    }

    // Base Page::content() returns the schema untouched, so without this
    // override the default 'filament-panels::pages.page' view has nothing
    // to render — the form() schema below is never embedded into the page.
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('company_name')->disabled(),
                Forms\Components\TextInput::make('company_code')->disabled(),
                Forms\Components\TextInput::make('registration_no')->disabled(),
                Forms\Components\TextInput::make('tin_no')->disabled(),
                Forms\Components\TextInput::make('contact_person')->disabled(),
                Forms\Components\TextInput::make('email')->disabled(),
                Forms\Components\TextInput::make('phone')->disabled(),
                Forms\Components\Textarea::make('address')->disabled()->rows(3),
                Forms\Components\TextInput::make('status')->disabled(),
            ])
            ->statePath('data');
    }
}
