<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CenterResource\Pages;
use App\Models\Center;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Gestión de centros: SOLO el super administrador.
 * Los demás usuarios editan los datos de su propio centro en la página "Mi centro".
 */
class CenterResource extends Resource
{
    protected static ?string $model = Center::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'Administración usuarios';
    protected static ?int $navigationSort = 3;

    protected static ?string $label = 'Centro';
    protected static ?string $pluralLabel = 'Centros';

    protected static function isSuperAdmin(): bool
    {
        return auth()->user()?->super_admin == true;
    }

    public static function canViewAny(): bool
    {
        return static::isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return static::isSuperAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return static::isSuperAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return static::isSuperAdmin() && $record->canDelete;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * Campos del centro. Se reutilizan en la página "Mi centro".
     * $forOwnCenter = true oculta lo que solo debe tocar el super admin (activo).
     */
    public static function centerSchema(bool $forOwnCenter = false): array
    {
        return [
            Grid::make(12)
                ->schema([
                    Section::make('Logo')
                        ->columnSpan(['default' => 12, 'lg' => 3])
                        ->schema([
                            Forms\Components\FileUpload::make('image')
                                ->hiddenLabel()
                                ->image()
                                ->imageEditor()
                                ->disk('public')
                                ->directory('centers')
                                ->visibility('public')
                                ->maxSize(2048)
                                ->helperText('Se mostrará como logo en el panel.'),
                        ]),

                    Grid::make(1)
                        ->columnSpan(['default' => 12, 'lg' => 9])
                        ->schema([
                            Section::make('Datos generales')
                                ->icon('heroicon-o-building-office')
                                ->columns(['default' => 1, 'sm' => 2])
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label('Nombre')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('nif')
                                        ->label('NIF/CIF')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('email')
                                        ->label('Correo')
                                        ->email()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('phone')
                                        ->label('Teléfono')
                                        ->tel()
                                        ->maxLength(255),
                                    Forms\Components\Toggle::make('active')
                                        ->label('¿Activo?')
                                        ->helperText('Si se desactiva, el centro no aparece para asignar usuarios.')
                                        ->inline(false)
                                        ->default(true)
                                        ->hidden($forOwnCenter),
                                ]),

                            Section::make('Dirección')
                                ->icon('heroicon-o-map-pin')
                                ->columns(['default' => 1, 'sm' => 2])
                                ->schema([
                                    Forms\Components\TextInput::make('address')
                                        ->label('Dirección')
                                        ->maxLength(255)
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('postal_code')
                                        ->label('Código postal')
                                        ->maxLength(255),
                                    Forms\Components\Select::make('country_id')
                                        ->label('País')
                                        ->options(fn(): Collection => Country::where('is_active', 1)->orderBy('name')->pluck('name', 'id'))
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

                            Section::make('Datos bancarios')
                                ->icon('heroicon-o-banknotes')
                                ->collapsible()
                                ->columns(['default' => 1, 'sm' => 2])
                                ->schema([
                                    Forms\Components\TextInput::make('bank_name')
                                        ->label('Entidad bancaria')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('bank_number')
                                        ->label('IBAN / Nº de cuenta')
                                        ->maxLength(255),
                                ]),
                        ]),
                ]),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema(static::centerSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->withCount(['users', 'packages']))
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Logo')
                    ->disk('public')
                    ->circular()
                    ->visibleFrom('sm'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->weight('bold')
                    ->description(fn(Center $record) => $record->nif)
                    ->searchable(['name', 'nif'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('Municipio')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->badge()
                    ->sortable()
                    ->visibleFrom('sm'),
                Tables\Columns\TextColumn::make('packages_count')
                    ->label('Pedidos')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->formatStateUsing(fn($state) => Carbon::parse($state)->format('d-m-Y H:i'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('active')
                    ->label('¿Activo?')
                    ->options(['1' => 'Activo', '0' => 'No activo']),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->tooltip('Editar'),
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->tooltip('Eliminar')
                    ->modalHeading('Eliminar centro')
                    ->modalDescription('¿Estás seguro de que deseas eliminar este centro?')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotificationTitle('Centro eliminado correctamente')
                    // Solo si no tiene usuarios ni pedidos
                    ->visible(fn(Center $record) => $record->canDelete),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCenters::route('/'),
            'create' => Pages\CreateCenter::route('/create'),
            'edit'   => Pages\EditCenter::route('/{record}/edit'),
        ];
    }
}
