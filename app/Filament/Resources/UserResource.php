<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Models\User;
use App\Support\Permissions;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;

/**
 * - Super admin: ve y gestiona los usuarios de TODOS los centros.
 * - Resto: según los permisos de su rol, y solo con los usuarios de su centro
 *   (nunca ve a los super admin).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Administración usuarios';
    protected static ?string $navigationLabel = 'Usuarios';
    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'Usuario';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Usuarios';
    }

    protected static function isSuperAdmin(): bool
    {
        return Auth::user()?->super_admin == true;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withCount('packages')->with('roles');

        if (!static::isSuperAdmin()) {
            $query->where('center_id', Auth::user()?->center_id ?? -1)
                ->where('super_admin', false);
        }

        return $query;
    }

    protected static function userCan(string $permission): bool
    {
        return (bool) Auth::user()?->hasPermission($permission);
    }

    public static function canViewAny(): bool
    {
        return static::userCan(Permissions::USERS_VIEW);
    }

    public static function canCreate(): bool
    {
        return static::userCan(Permissions::USERS_CREATE);
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCan(Permissions::USERS_EDIT);
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCan(Permissions::USERS_DELETE)
            && $record->id !== Auth::id()          // no puede borrarse a sí mismo
            && $record->packages()->doesntExist() // ni a un repartidor con pedidos
            && \App\Models\Payment::where('user_id', $record->id)->doesntExist(); // ni con pagos
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(12)
                    ->schema([
                        Section::make('Foto')
                            ->columnSpan(['default' => 12, 'lg' => 3])
                            ->schema([
                                Forms\Components\FileUpload::make('image')
                                    ->hiddenLabel()
                                    ->image()
                                    ->avatar()
                                    ->imageEditor()
                                    ->disk('public')
                                    ->directory('users')
                                    ->visibility('public')
                                    ->maxSize(2048)
                                    ->alignCenter(),
                            ]),

                        Grid::make(1)
                            ->columnSpan(['default' => 12, 'lg' => 9])
                            ->schema([
                                Section::make('Acceso')
                                    ->icon('heroicon-o-key')
                                    ->columns(['default' => 1, 'sm' => 2])
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nombre')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('email')
                                            ->label('Email')
                                            ->email()
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('password')
                                            ->label('Contraseña')
                                            ->password()
                                            ->revealable()
                                            ->minLength(8)
                                            ->required(fn(string $operation) => $operation === 'create')
                                            ->dehydrated(fn($state) => filled($state))
                                            ->helperText(fn(string $operation) => $operation === 'edit'
                                                ? new HtmlString('<span style="color:#00B5D8">Solo se cambia si escribes una nueva.</span>')
                                                : null),
                                        Forms\Components\Select::make('center_id')
                                            ->label('Centro')
                                            ->relationship('center', 'name', fn(Builder $query) => $query->where('active', true))
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->visible(fn() => static::isSuperAdmin()),
                                    ]),

                                Section::make('Permisos y reparto')
                                    ->icon('heroicon-o-shield-check')
                                    ->columns(['default' => 1, 'sm' => 2])
                                    ->schema([
                                        Forms\Components\Select::make('roles')
                                            ->label('Roles')
                                            ->relationship('roles', 'name')
                                            ->multiple()
                                            ->preload()
                                            ->searchable()
                                            ->placeholder('Sin roles (sin permisos)')
                                            ->helperText('Puede tener varios; los permisos se suman.')
                                            // Solo el super admin asigna roles
                                            ->visible(fn() => static::isSuperAdmin())
                                            ->columnSpanFull(),
                                        Forms\Components\Toggle::make('active')
                                            ->label('¿Activo?')
                                            ->helperText('Si se desactiva, no puede entrar.')
                                            ->inline(false)
                                            ->default(true),
                                        Forms\Components\Toggle::make('can_admin_panel')
                                            ->label('¿Acceso al panel de administración?')
                                            ->helperText('Los repartidores que solo usan la app no lo necesitan.')
                                            ->inline(false)
                                            ->default(false),
                                        Forms\Components\TextInput::make('price_per_package')
                                            ->label('Precio por paquete')
                                            ->helperText('Lo que se le paga por cada paquete entregado.')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->prefix('€')
                                            ->default(User::DEFAULT_PRICE_PER_PACKAGE)
                                            ->required()
                                            // Con el permiso "Modificar precio por paquete" (el super admin siempre)
                                            ->visible(fn() => static::userCan(Permissions::USERS_EDIT_PRICE)),
                                        Forms\Components\ToggleButtons::make('payment_mode')
                                            ->label('Tipo de pago')
                                            ->options(User::paymentModeOptions())
                                            ->icons([
                                                User::PAYMENT_PER_PACKAGE => 'heroicon-o-cube',
                                                User::PAYMENT_PER_ADDRESS => 'heroicon-o-map-pin',
                                            ])
                                            ->default(User::PAYMENT_PER_PACKAGE)
                                            ->inline()
                                            ->required()
                                            ->helperText('Por dirección y día: si entrega varios paquetes en la misma dirección exacta el mismo día, se paga solo uno (aunque sean para distintos destinatarios).')
                                            ->visible(fn() => static::userCan(Permissions::USERS_EDIT_PRICE)),
                                        Forms\Components\Toggle::make('super_admin')
                                            ->label('¿Super administrador?')
                                            ->helperText('Ve y gestiona todos los centros y usuarios.')
                                            ->inline(false)
                                            ->visible(fn() => static::isSuperAdmin()),
                                    ]),

                                Section::make('Información personal')
                                    ->icon('heroicon-o-identification')
                                    ->collapsible()
                                    ->columns(['default' => 1, 'sm' => 2])
                                    ->schema([
                                        Forms\Components\TextInput::make('identification')
                                            ->label('DNI/NIE')
                                            ->maxLength(50),
                                        Forms\Components\TextInput::make('phone')
                                            ->label('Teléfono')
                                            ->tel()
                                            ->maxLength(255),
                                        Forms\Components\DatePicker::make('birth_date')
                                            ->label('Fecha de nacimiento'),
                                        Forms\Components\Radio::make('gender')
                                            ->label('Género')
                                            ->options([
                                                'masc' => 'Masculino',
                                                'fem'  => 'Femenino',
                                            ])
                                            ->inline()
                                            ->inlineLabel(false),
                                    ]),

                                Section::make('Dirección')
                                    ->icon('heroicon-o-map-pin')
                                    ->collapsible()
                                    ->collapsed(fn(string $operation) => $operation === 'create')
                                    ->columns(['default' => 1, 'sm' => 2])
                                    ->schema([
                                        Forms\Components\TextInput::make('address')
                                            ->label('Dirección')
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('postal_code')
                                            ->label('Código postal')
                                            ->maxLength(10),
                                        Forms\Components\Select::make('country_id')
                                            ->label('País')
                                            ->options(fn(): Collection => Country::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(function (Set $set) {
                                                $set('state_id', null);
                                                $set('city_id', null);
                                            }),
                                        Forms\Components\Select::make('state_id')
                                            ->label('Provincia')
                                            ->options(fn(Get $get): Collection => State::where('country_id', $get('country_id'))->orderBy('name')->pluck('name', 'id'))
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(fn(Set $set) => $set('city_id', null)),
                                        Forms\Components\Select::make('city_id')
                                            ->label('Municipio')
                                            ->options(fn(Get $get): Collection => City::where('state_id', $get('state_id'))->orderBy('name')->pluck('name', 'id'))
                                            ->searchable(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn(User $record) => $record->getFilamentAvatarUrl())
                    ->visibleFrom('sm'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->weight('bold')
                    ->description(fn(User $record) => $record->email)
                    ->searchable(['name', 'email'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Sin roles')
                    ->visibleFrom('sm'),
                Tables\Columns\TextColumn::make('center.name')
                    ->label('Centro')
                    ->sortable()
                    ->visible(fn() => static::isSuperAdmin())
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('price_per_package')
                    ->label('€/paquete')
                    ->money('EUR')
                    ->description(fn(User $record) => User::paymentModeOptions()[$record->payment_mode] ?? null)
                    ->sortable()
                    ->visibleFrom('sm'),
                Tables\Columns\TextColumn::make('packages_count')
                    ->label('Pedidos')
                    ->badge()
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\IconColumn::make('can_admin_panel')
                    ->label('Panel')
                    ->boolean()
                    ->visibleFrom('lg'),
                Tables\Columns\IconColumn::make('super_admin')
                    ->label('Super admin')
                    ->boolean()
                    ->visible(fn() => static::isSuperAdmin())
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Alta')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('active')
                    ->label('¿Activo?')
                    ->options(['1' => 'Activo', '0' => 'No activo']),
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name')
                    ->preload()
                    ->visible(fn() => static::isSuperAdmin()),
                Tables\Filters\SelectFilter::make('can_admin_panel')
                    ->label('Acceso al panel')
                    ->options(['1' => 'Sí', '0' => 'No']),
                Tables\Filters\SelectFilter::make('center_id')
                    ->label('Centro')
                    ->relationship('center', 'name')
                    ->preload()
                    ->visible(fn() => static::isSuperAdmin()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->tooltip('Editar')
                    ->visible(fn(User $record) => static::canEdit($record)),
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->tooltip('Eliminar')
                    ->modalHeading('Eliminar usuario')
                    ->modalDescription('¿Estás seguro de que deseas eliminar este usuario?')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotificationTitle('Usuario eliminado correctamente')
                    ->visible(fn(User $record) => static::canDelete($record)),
                Impersonate::make()
                    ->tooltip('Entrar como este usuario')
                    ->visible(fn(User $record) => static::isSuperAdmin() && $record->id !== Auth::id()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
