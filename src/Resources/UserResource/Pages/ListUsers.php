<?php

declare(strict_types=1);

namespace io3x1\FilamentUser\Resources\UserResource\Pages;

use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use io3x1\FilamentUser\Resources\UserResource;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [30, 60, 100, 200, -1];
    }

    protected function getTitle(): string
    {
        return trans('filament-user::user.resource.title.list');
    }
}
