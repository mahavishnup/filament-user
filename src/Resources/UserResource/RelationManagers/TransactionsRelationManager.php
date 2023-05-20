<?php

namespace io3x1\FilamentUser\Resources\UserResource\RelationManagers;

use App\Filament\Resources\Settings\MediaResource;
use App\Filament\Resources\Wallets\TransactionResource;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $recordTitleAttribute = 'amount';

    protected static ?string $title = 'Transactions';

    public static function form(Form $form): Form
    {
        return TransactionResource::form($form);
    }

    public static function table(Table $table): Table
    {
        return TransactionResource::table($table);
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [30, 60, 100, 200, -1];
    }
}
