<?php

declare(strict_types=1);

namespace io3x1\FilamentUser\Resources\UserResource\Pages;

use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use io3x1\FilamentUser\Resources\UserResource;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        if (app()->isProduction()) {
            Notification::make()
                ->title('New user')
                ->icon('heroicon-o-lock-closed')
                ->body("**{$this->record->name} user created.**")
                ->actions([
                    Action::make('View')->url(static::$resource::getUrl('edit', ['record' => $this->record])),
                ])
                ->sendToDatabase(User::admins()->get());
        }
    }

    protected function getTitle(): string
    {
        return trans('filament-user::user.resource.title.create');
    }
}
