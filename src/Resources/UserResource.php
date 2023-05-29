<?php

declare(strict_types=1);

namespace io3x1\FilamentUser\Resources;

use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use App\Enums\MediaCollection;
use App\Enums\OfficeList;
use App\Enums\UserType;
use App\Filament\Resources\Blog\PostResource\RelationManagers\MediaRelationManager;
use App\Models\User;
use App\Services\V1\FilamentCacheModel;
use Closure;
use Cog\Laravel\Ban\Scopes\BannedAtScope;
use Filament\{Forms, Forms\Components\SpatieMediaLibraryFileUpload, Tables};
use Filament\Resources\{Form, Resource, Table};
use Filament\Tables\Columns\{SpatieMediaLibraryImageColumn};
use Illuminate\Database\Eloquent\{Builder, SoftDeletingScope};
use Illuminate\Support\Facades\{Auth, Hash};
use Illuminate\Support\Str;
use io3x1\FilamentUser\Resources\UserResource\{Pages, RelationManagers};
use Phpsa\FilamentPasswordReveal\Password;
use STS\FilamentImpersonate\Impersonate;
use Widiu7omo\FilamentBandel\Actions\BanBulkAction;
use Widiu7omo\FilamentBandel\Actions\UnbanBulkAction;
use Wiebenieuwenhuis\FilamentCharCounter\TextInput;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationLabel = 'Users';

    protected static ?int $navigationSort = -9;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('DETAILS')
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('NAME')
                                            ->placeholder('Name')
                                            ->maxLength(191)
                                            ->required(false),

                                        TextInput::make('email')
                                            ->label('EMAIL')
                                            ->placeholder('Email address')
                                            ->email()
                                            ->maxLength(191)
                                            ->required(false),

                                        ...static::getPhone(),

                                        Password::make('password')
                                            ->generatable()
                                            ->label('Password')
                                            ->placeholder('Password')
                                            ->password()
                                            ->maxLength(191)
                                            ->required()
                                            ->visibleOn('create')
                                            ->dehydrateStateUsing(static function ($state) use ($form) {
                                                if (!empty($state)) {
                                                    return Hash::make($state);
                                                }

                                                $user = User::find($form->getColumns());

                                                return $user->password ?? null;
                                            }),
                                    ])
                                    ->columns(2),
                            ])
                            ->compact()
                            ->collapsed(false)
                            ->collapsible(),

                        Forms\Components\Section::make('OTHERS')
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->schema([
                                        ...static::getOther(),
                                    ])
                                    ->columns(2),
                            ])
                            ->compact()
                            ->collapsed(false)
                            ->collapsible(),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('STATUS')
                            ->schema([
                                Forms\Components\Toggle::make('can_access_filament')
                                    ->inline()
                                    ->onIcon('heroicon-s-check-circle')
                                    ->offIcon('heroicon-s-x-circle')
                                    ->onColor('success')
                                    ->offColor('danger')
                                    ->helperText('Extranet access or not?')
                                    ->label('Extranet')
                                    ->default(0)
                                    ->columnSpan(1)
                                    ->required(condition: false),

                                static::getBalance(),

                                Forms\Components\Placeholder::make('email_verified_at')
                                    ->label('Email verified at')
                                    ->content(fn($record): ?string => @$record->email_verified_at?->diffForHumans()),

                                Forms\Components\Placeholder::make('phone_verified_at')
                                    ->label('Phone verified at')
                                    ->content(fn($record): ?string => @$record->phone_verified_at?->diffForHumans()),

                                Forms\Components\Placeholder::make('created_at')
                                    ->label('Created at')
                                    ->content(fn($record): ?string => $record->created_at?->diffForHumans()),

                                Forms\Components\Placeholder::make('updated_at')
                                    ->label('Last modified at')
                                    ->content(fn($record): ?string => $record->updated_at?->diffForHumans()),
                            ])
                            ->compact()
                            ->collapsed(false)
                            ->collapsible(),

                        Forms\Components\Section::make('ROLES')
                            ->schema([
                                Forms\Components\MultiSelect::make('roles')
                                    ->relationship('roles', 'name')
                                    ->label('Roles')
                                    ->required(false),

                                Forms\Components\Select::make('role')
                                    ->label('USER ROLE')
                                    ->options(UserType::forRoleToArray())
                                    ->searchable()
                                    ->required(condition: false),
                            ])
                            ->compact()
                            ->collapsed(false)
                            ->collapsible(),

                        Forms\Components\Section::make('OFFICE')
                            ->schema([
                                static::getOffice(),
                            ])
                            ->compact()
                            ->collapsed(false)
                            ->collapsible(),

                        Forms\Components\Section::make('ASSOCIATION')
                            ->schema([
                                ...static::getAssociation(),
                            ])
                            ->compact()
                            ->collapsed(false)
                            ->collapsible(),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function getPhone(): array
    {
        return [
            TextInput::make('phone')
                ->label('PHONE NO')
                ->placeholder('Phone number')
                ->tel()
                ->telRegex('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/')
                ->maxLength(15)
                ->required(false),

            TextInput::make('mobile')
                ->label('MOBILE NO')
                ->placeholder('Mobile number')
                ->tel()
                ->telRegex('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/')
                ->maxLength(15)
                ->required(false),

            Forms\Components\TextInput::make('address')
                ->label('ADDRESS')
                ->placeholder('Address')
                ->required(false),
        ];
    }

    public static function getOther($hidden = false): array
    {
        return [
            TextInput::make('id_proof')
                ->label('ID PROOF')
                ->placeholder('ID Proof')
                ->maxLength(191)
                ->hidden($hidden)
                ->required(false),

            TextInput::make('fax')
                ->label('FAX')
                ->placeholder('Fax')
                ->maxLength(191)
                ->hidden($hidden)
                ->required(false),

            TextInput::make('endosment')
                ->label('ENDOSMENT')
                ->placeholder('Endosment')
                ->maxLength(191)
                ->hidden($hidden)
                ->required(false),

            TextInput::make('business_name')
                ->label('BUSINESS NAME')
                ->placeholder('Business Name')
                ->maxLength(191)
                ->hidden($hidden)
                ->required(false),

            TextInput::make('gst_no')
                ->label('GST NO')
                ->placeholder('GST No')
                ->maxLength(191)
                ->hidden($hidden)
                ->required(false),

            TextInput::make('pan_no')
                ->label('PAN NO')
                ->placeholder('Pan No')
                ->maxLength(191)
                ->hidden($hidden)
                ->required(false),

            SpatieMediaLibraryFileUpload::make(MediaCollection::CARD->value)
                ->label('PAN PHOTO COPY')
                ->collection(MediaCollection::CARD->value)
                ->disk('media')
                ->hidden($hidden)
                ->customProperties(['prefix' => app(static::$model)->getTable()])
                ->required(false)
                ->columnSpan('full'),
        ];
    }

    public static function getBalance(): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make('balance')
            ->label('WALLET BALANCE')
            ->content(fn($record): ?string => '₹' . numberFormat(@$record->balance ?: @Auth::user()->balance));
    }

    public static function getOffice($hidden = false): Forms\Components\Select
    {
        return Forms\Components\Select::make('office')
            ->label('OFFICE')
            ->options(OfficeList::toArray())
            ->searchable()
            ->hidden($hidden)
            ->required(condition: false);
    }

    public static function getAssociation($hidden = false): array
    {
        return [
            Forms\Components\Toggle::make('association')
                ->inline()
                ->onIcon('heroicon-s-check-circle')
                ->offIcon('heroicon-s-x-circle')
                ->onColor('success')
                ->offColor('danger')
                ->helperText('Register with association or not?')
                ->label('ASSOCIATION STATUS')
                ->hidden($hidden)
                ->default(0)
                ->required(condition: false)
                ->reactive(),

            TextInput::make('association_name')
                ->label('ASSOCIATION NAME')
                ->placeholder('Association Name')
                ->maxLength(191)
                ->hidden($hidden)
                ->visible(fn(Closure $get) => $get('association'))
                ->required(false),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }

    public static function getLabel(): string
    {
        return trans('filament-user::user.resource.single');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getPluralLabel(): string
    {
        return trans('filament-user::user.resource.label');
    }

    public static function table(Table $table): Table
    {
        $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('NAME')
                    ->wrap()
                    ->size('sm')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('EMAIL')
                    ->wrap()
                    ->size('sm')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('TYPE')
                    ->formatStateUsing(fn($record) => @$record->role?->name())
                    ->icon(fn($record) => @$record->role ? 'heroicon-s-shield-exclamation' : '')
                    ->wrap()
                    ->size('sm')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('roles.name')
                    ->formatStateUsing(fn($state) => Str::headline(@$state))
                    ->icon(fn($record) => @count(@$record->roles ?: []) >= 1 ? 'heroicon-s-shield-check' : '')
                    ->label('ROLES')
                    ->wrap()
                    ->size('sm')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->color('warning')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('PHONE')
                    ->wrap()
                    ->size('sm')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('balance')
                    ->label('WALLET')
                    ->color('success')
                    ->icon('heroicon-s-currency-rupee')
                    ->wrap()
                    ->size('sm')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('address')
                    ->label('ADDRESS')
                    ->wrap()
                    ->size('sm')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                SpatieMediaLibraryImageColumn::make(MediaCollection::CARD->value)
                    ->label('PAN COPY')
                    ->collection(MediaCollection::CARD->value)
                    ->conversion(MediaCollection::CARD->name())
                    ->square()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('pan_no')
                    ->label('PAN NO')
                    ->wrap()
                    ->size('sm')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('business_name')
                    ->label('BUSINESS NAME')
                    ->wrap()
                    ->size('sm')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('gst_no')
                    ->label('GST NO')
                    ->wrap()
                    ->size('sm')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('office')
                    ->formatStateUsing(fn($record) => @$record->office?->name())
                    ->label('OFFICE')
                    ->wrap()
                    ->size('sm')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('UPDATED')
                    ->wrap()
                    ->size('sm')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->formatStateUsing(fn($record): string => @$record?->updated_at->diffForHumans(['short' => true]))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->label('Deleted Records'),

                Tables\Filters\SelectFilter::make('role')
                    ->label('User Role')
                    ->placeholder('Select an user role')
                    ->searchable()
                    ->multiple()
                    ->options(UserType::forRoleToArray())
                    ->attribute('role'),

                Tables\Filters\SelectFilter::make('office')
                    ->label('Office')
                    ->placeholder('Select an office')
                    ->searchable()
                    ->multiple()
                    ->options(OfficeList::toArray())
                    ->attribute('office'),
            ])
            ->actions([
                Impersonate::make()->redirectTo(route('filament.pages.dashboard')),
                Tables\Actions\EditAction::make('name'),
                Tables\Actions\DeleteAction::make('id'),
            ])
            ->bulkActions([
                BanBulkAction::make('banned_model'),
                UnbanBulkAction::make('unbanned_model'),
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\ForceDeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
                FilamentExportBulkAction::make('export'),
            ])
            ->reorderable(column: 'order_column')
            ->poll('300s');

        if (config('filament-user.impersonate')) {
            $table->prependActions([
                Impersonate::make('impersonate'),
            ]);
        }

        return $table;
    }

    protected static function getNavigationGroup(): ?string
    {
        return config('filament-user.group');
    }

    protected static function getNavigationLabel(): string
    {
        return trans('filament-user::user.resource.label');
    }

    protected static function getNavigationBadge(): ?string
    {
        return (string)FilamentCacheModel::getUrlModelList(static fn() => static::getEloquentQuery()?->count(), app(static::$model)?->getTable().'_resource');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['roles', 'media'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
                BannedAtScope::class,
            ])
            ->orderColumn();
    }

    protected static function getNavigationBadgeColor(): ?string
    {
        return (string)FilamentCacheModel::getUrlModelList(static fn() => static::getEloquentQuery()?->count(), app(static::$model)?->getTable().'_resource') < 10 ? 'warning' : 'primary';
    }

    protected function getTitle(): string
    {
        return trans('filament-user::user.resource.title.resource');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TransactionsRelationManager::class,
        ];
    }
}
