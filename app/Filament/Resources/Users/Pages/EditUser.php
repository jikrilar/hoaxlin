<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->modalHeading('Hapus pengguna ini?')
                ->modalDescription('Akun dan riwayat pemeriksaannya akan dihapus. Tindakan ini tidak dapat dibatalkan.')
                ->modalSubmitActionLabel('Hapus pengguna'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Keep credentials empty even if model serialization changes later.
        $data['password'] = null;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            if (! array_key_exists('is_admin', $errors)) {
                throw $exception;
            }

            // The model invariant uses a domain field; Filament renders errors
            // against the form state path. Preserve the rule and its message.
            $errors[$this->form->getStatePath().'.is_admin'] = $errors['is_admin'];
            unset($errors['is_admin']);

            throw ValidationException::withMessages($errors);
        }
    }

    protected function afterSave(): void
    {
        $this->data['password'] = null;
    }
}
