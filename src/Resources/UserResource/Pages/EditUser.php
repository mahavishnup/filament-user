<?php

declare(strict_types=1);

namespace io3x1\FilamentUser\Resources\UserResource\Pages;

use App\Models\User;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use io3x1\FilamentUser\Resources\UserResource;
use STS\FilamentImpersonate\Pages\Actions\Impersonate;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
            //Impersonate::make('impersonate')->record($this->getRecord()),
        ];
    }

    protected function getTitle(): string
    {
        return trans('filament-user::user.resource.title.edit');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $getUser = User::where('email', $data['email'])->first();
        if ($getUser) {
            if (empty($data['password'])) {
                $data['password'] = $getUser->password;
            }
        }

        return $data;
    }
}
