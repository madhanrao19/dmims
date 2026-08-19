<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\Pages\Concerns\ForcesOwnCustomerId;
use Filament\Resources\Pages\CreateRecord as BaseCreateRecord;

/**
 * App-wide base for every resource's Create page whose form includes a
 * customer_id field — see ForcesOwnCustomerId for why this exists.
 * UserResource has its own, more involved version of this (it also strips
 * platform-only roles) and deliberately does not use this base.
 */
abstract class CreateRecord extends BaseCreateRecord
{
    use ForcesOwnCustomerId;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->forceOwnCustomerId($data);
    }
}
