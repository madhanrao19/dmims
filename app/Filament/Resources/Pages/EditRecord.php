<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\Pages\Concerns\ForcesOwnCustomerId;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord as BaseEditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * App-wide base for every resource's Edit page — same reasoning as
 * ../Pages/ListRecords.php. Without an explicit getHeaderActions()
 * override, no Edit page anywhere had a working Delete button either.
 *
 * DeleteAction::make() authorizes itself against the resource's own
 * can('delete', $record) — including $deletePermission (Company Admin can
 * edit users but not delete; Company Supervisor/Stock User can edit
 * products but not delete) — automatically.
 *
 * Also forces customer_id back to the actor's own tenant on save — see
 * ForcesOwnCustomerId. A subclass that needs its own mutateFormDataBeforeSave
 * (e.g. EditBillingRecord recomputing total_amount) must call
 * parent::mutateFormDataBeforeSave() first to keep this guard.
 */
abstract class EditRecord extends BaseEditRecord
{
    use ForcesOwnCustomerId;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->forceOwnCustomerId($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                // Several tables (subscription_logs, license_logs, etc.)
                // deliberately restrictOnDelete their parent (audit trail
                // must survive deleting the record it documents — see
                // 2026_08_18_000006_restrict_subscription_logs_cascade.php).
                // Filament's own DeleteAction has no exception handling
                // around the delete call, so that constraint violation was
                // surfacing as a raw, unstyled 500 page instead of the
                // in-app failure notification every other error uses.
                ->failureNotificationTitle('Cannot delete')
                ->failureNotificationMessage('This record is still referenced by other data (history, logs, or related records) and cannot be deleted while those exist.')
                ->action(function (DeleteAction $action): void {
                    try {
                        $result = $action->process(static fn (Model $record): ?bool => $record->delete());
                    } catch (QueryException $e) {
                        if ($e->getCode() !== '23000') {
                            throw $e;
                        }

                        $action->failure();

                        return;
                    }

                    if (! $result) {
                        $action->failure();

                        return;
                    }

                    $action->success();
                }),
        ];
    }
}
