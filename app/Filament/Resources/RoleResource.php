<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\Role;
use App\Support\Permissions;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Roles y permisos: solo el super administrador.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Administración usuarios';
    protected static ?string $navigationLabel = 'Roles y permisos';
    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return 'Rol';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Roles';
    }

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

    public static function form(Form $form): Form
    {
        // Una sección por módulo con sus permisos.
        // Se guardan como {"packages": [...], "users": [...], ...} en la columna permissions.
        $permissionSections = collect(Permissions::groups())
            ->map(fn(array $group, string $key) => Section::make($group['label'])
                ->compact()
                ->columnSpan(['default' => 2, 'md' => 1])
                ->schema([
                    Forms\Components\CheckboxList::make("permissions.{$key}")
                        ->hiddenLabel()
                        ->options($group['permissions'])
                        ->bulkToggleable()
                        ->columns(1),
                ]))
            ->values()
            ->all();

        return $form
            ->schema([
                Section::make('Rol')
                    ->columns(['default' => 1, 'sm' => 2])
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100),
                        Forms\Components\TextInput::make('description')
                            ->label('Descripción')
                            ->maxLength(255),
                    ]),

                Section::make('Permisos')
                    ->description('El super administrador tiene siempre todos los permisos, tenga el rol que tenga.')
                    ->columns(2)
                    ->schema($permissionSections),
            ]);
    }

    public static function table(Table $table): Table
    {
        $total = count(Permissions::all());

        return $table
            ->modifyQueryUsing(fn($query) => $query->withCount('users'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Rol')
                    ->weight('bold')
                    ->description(fn(Role $record) => $record->description)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('permissions')
                    ->label('Permisos')
                    ->state(fn(Role $record) => count($record->permissionList()) . " de {$total}")
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->badge()
                    ->sortable()
                    ->visibleFrom('sm'),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->tooltip('Editar'),
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->tooltip('Eliminar')
                    ->modalHeading('Eliminar rol')
                    ->modalDescription('¿Estás seguro de que deseas eliminar este rol?')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->successNotificationTitle('Rol eliminado correctamente')
                    // Si hay usuarios con este rol no se puede borrar
                    ->visible(fn(Role $record) => $record->canDelete),
            ]);
    }

    /** Tabla de usuarios con el rol (debajo del formulario al editar) */
    public static function getRelations(): array
    {
        return [
            RoleResource\RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit'   => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
