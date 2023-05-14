<?php

declare(strict_types=1);

namespace io3x1\FilamentUser\Resources;

use Closure;
use App\Models\User;
use Illuminate\Support\Str;
use Filament\Forms\Components\TextInput;
use STS\FilamentImpersonate\Impersonate;
use Phpsa\FilamentPasswordReveal\Password;
use Illuminate\Support\Facades\{Auth, Hash};
use Filament\Resources\{Form, Resource, Table};
use io3x1\FilamentUser\Resources\UserResource\Pages;
use Illuminate\Database\Eloquent\{Builder, SoftDeletingScope};
use AbanoubNassem\FilamentPhoneField\Forms\Components\PhoneInput;
use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use Filament\{Forms, Forms\Components\SpatieMediaLibraryFileUpload, Tables};
use Filament\Tables\Columns\{BooleanColumn, SpatieMediaLibraryImageColumn, TextColumn};

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
                                        Forms\Components\Hidden::make('can_access_filament')->default(true),
                                        Forms\Components\Hidden::make('email_verified_at')->default(now()),

                                        Forms\Components\TextInput::make('name')
                                            ->label('NAME')
                                            ->placeholder('Name')
                                            ->maxLength(191)
                                            ->required(false),

                                        Forms\Components\TextInput::make('email')
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
                                            ->maxLength(255)
                                            ->required()
                                            ->visibleOn('create')
                                            ->dehydrateStateUsing(fn ($state) => ! empty($state) ? Hash::make($state) : ''),
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
                                static::getBalance(),
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

                                Forms\Components\Select::make('role_id')
                                    ->label('TYPE')
//                                    ->options(getUserRoleArray())
                                    ->searchable()
                                    ->required(condition: false),
                            ])
                            ->compact()
                            ->collapsed(false)
                            ->collapsible(),

                        Forms\Components\Section::make('LOCATION')
                            ->schema([
                                static::getLocation(),
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

        $rows = [
            TextInput::make('name')->required()->label(trans('filament-user::user.resource.name')),
            TextInput::make('email')->email()->required()->label(trans('filament-user::user.resource.email')),
            Forms\Components\TextInput::make('password')->label(trans('filament-user::user.resource.password'))
                ->password()
                ->maxLength(255)
                ->dehydrateStateUsing(static function ($state) use ($form) {
                    if ( ! empty($state)) {
                        return Hash::make($state);
                    }

                    $user = User::find($form->getColumns());
                    if ($user) {
                        return $user->password;
                    }
                }),
        ];

        if (config('filament-user.shield')) {
            $rows[] = Forms\Components\MultiSelect::make('roles')->relationship('roles', 'name')->label(trans('filament-user::user.resource.roles'));
        }

        $form->schema($rows);

        return $form;
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

            Forms\Components\TextInput::make('association_name')
                ->label('ASSOCIATION NAME')
                ->placeholder('Association Name')
                ->maxLength(191)
                ->hidden($hidden)
                ->visible(fn (Closure $get) => $get('association'))
                ->required(false),
        ];
    }

    public static function getBalance(): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make('balance')
            ->label('WALLET BALANCE')
            ->content(fn ($record): ?string => '₹' . numberFormat(@$record->balance ?: @Auth::user()->balance));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['roles', 'role', 'location', 'media'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->orderColumn();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone', 'role.name', 'location.name'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        $details = [];

        if (@$record->role) {
            $details['Type'] = @$record->role->name;
        }

        if (@$record->location) {
            $details['Location'] = @$record->location->name;
        }

        return $details;
    }

    public static function getLabel(): string
    {
        return trans('filament-user::user.resource.single');
    }

    public static function getLocation($hidden = false): Forms\Components\Select
    {
        return Forms\Components\Select::make('location_id')
            ->label('LOCATION')
//            ->options(getSettingRootLevelSettings(categories: ['user-location'], key: 'id'))
            ->searchable()
            ->hidden($hidden)
            ->required(condition: false);
    }

    public static function getOther($hidden = false): array
    {
        return [
            Forms\Components\TextInput::make('id_proof')
                ->label('ID PROOF')
                ->placeholder('ID Proof')
                ->maxLength(191)
                ->hidden($hidden)
                ->required(false),

            Forms\Components\TextInput::make('fax')
                ->label('FAX')
                ->placeholder('Fax')
                ->maxLength(191)
                ->hidden($hidden)
                ->required(false),

            Forms\Components\TextInput::make('endosment')
                ->label('ENDOSMENT')
                ->placeholder('Endosment')
                ->maxLength(191)
                ->hidden($hidden)
                ->required(false),

            Forms\Components\TextInput::make('business_name')
                ->label('BUSINESS NAME')
                ->placeholder('Business Name')
                ->maxLength(191)
                ->hidden($hidden)
                ->required(false),

            Forms\Components\TextInput::make('gst_no')
                ->label('GST NO')
                ->placeholder('GST No')
                ->maxLength(191)
                ->hidden($hidden)
                ->required(false),

            Forms\Components\TextInput::make('pan_no')
                ->label('PAN NO')
                ->placeholder('Pan No')
                ->maxLength(191)
                ->hidden($hidden)
                ->required(false),

            SpatieMediaLibraryFileUpload::make('pan')
                ->label('PAN PHOTO COPY')
                ->collection('pan')
                ->disk('media')
                ->hidden($hidden)
                ->customProperties(['prefix' => app(static::$model)->getTable()])
                ->required(false)
                ->columnSpan('full'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getPhone(): array
    {
        return [
            PhoneInput::make('phone')
                ->label('PHONE NO')
                ->placeholder('Phone number')
                ->initialCountry('in')
                ->tel()
                ->telRegex('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/')
                ->maxLength(191)
                ->required(false),

            PhoneInput::make('mobile')
                ->label('MOBILE NO')
                ->placeholder('Mobile number')
                ->initialCountry('in')
                ->tel()
                ->telRegex('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/')
                ->maxLength(191)
                ->required(false),

            Forms\Components\TextInput::make('address')
                ->label('ADDRESS')
                ->placeholder('Address')
                ->maxLength(191)
                ->required(false),
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
                //                TextColumn::make('id')->sortable()->label(trans('filament-user::user.resource.id')),
                //                TextColumn::make('name')->sortable()->searchable()->label(trans('filament-user::user.resource.name')),
                //                TextColumn::make('email')->sortable()->searchable()->label(trans('filament-user::user.resource.email')),
                //                BooleanColumn::make('email_verified_at')->sortable()->searchable()->label(trans('filament-user::user.resource.email_verified_at')),
                //                Tables\Columns\TextColumn::make('created_at')->label(trans('filament-user::user.resource.created_at'))
                //                    ->dateTime('M j, Y')->sortable(),
                //                Tables\Columns\TextColumn::make('updated_at')->label(trans('filament-user::user.resource.updated_at'))
                //                    ->dateTime('M j, Y')->sortable(),

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

                Tables\Columns\TextColumn::make('role.name')
                    ->label('TYPE')
                    ->icon(fn ($record) => @$record->role ? 'heroicon-s-shield-exclamation' : '')
                    ->wrap()
                    ->size('sm')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('roles.name')
                    ->formatStateUsing(fn ($state): string => Str::headline(@$state))
                    ->icon(fn ($record) => @count(@$record->roles ?: []) >= 1 ? 'heroicon-s-shield-check' : '')
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
                    ->label('BALANCE')
                    ->color('success')
                    ->icon(fn ($record) => @$record->balance ? 'heroicon-s-currency-rupee' : '')
                    ->wrap()
                    ->size('sm')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('address')
                    ->label('ADDRESS')
                    ->wrap()
                    ->size('sm')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                SpatieMediaLibraryImageColumn::make('pan')
                    ->label('PAN COPY')
                    ->collection('pan')
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

                Tables\Columns\TextColumn::make('location.name')
                    ->label('LOCATION')
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
                    ->formatStateUsing(fn ($record): string => @$record?->updated_at->diffForHumans(['short' => true]))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                //                Tables\Filters\Filter::make('verified')
                //                    ->label(trans('filament-user::user.resource.verified'))
                //                    ->query(fn (Builder $query): Builder => $query->whereNotNull('email_verified_at')),
                //                Tables\Filters\Filter::make('unverified')
                //                    ->label(trans('filament-user::user.resource.unverified'))
                //                    ->query(fn (Builder $query): Builder => $query->whereNull('email_verified_at')),

                Tables\Filters\TrashedFilter::make()
                    ->label('Deleted Records'),

                //                Tables\Filters\SelectFilter::make('role_id')
                //                    ->label('Type')
                //                    ->placeholder('Select an type')
                //                    ->searchable()
                //                    ->multiple()
                //                    ->options(getSettingRootLevelSettings(categories: ['user-role'], key: 'id'))
                //                    ->attribute('role_id'),
                //
                //                Tables\Filters\SelectFilter::make('location_id')
                //                    ->label('Location')
                //                    ->placeholder('Select an location')
                //                    ->searchable()
                //                    ->multiple()
                //                    ->options(getSettingRootLevelSettings(categories: ['user-location'], key: 'id'))
                //                    ->attribute('location_id'),
            ])
            ->actions([
                Impersonate::make()
                    ->redirectTo(route('filament.pages.dashboard')),
                Tables\Actions\EditAction::make('name'),
                Tables\Actions\DeleteAction::make('id'),
            ])
            ->bulkActions([
                //                Ban::make('ban'),
                //                Unban::make('unban'),
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

    protected function getTitle(): string
    {
        return trans('filament-user::user.resource.title.resource');
    }

//    protected static function getNavigationBadge(): ?string
//    {
//        return getNavigationBadgeHelper(static::$model);
//    }
//
//    protected static function getNavigationBadgeColor(): ?string
//    {
//        return getNavigationBadgeHelper(static::$model) < 10 ? 'warning' : 'primary';
//    }
}
