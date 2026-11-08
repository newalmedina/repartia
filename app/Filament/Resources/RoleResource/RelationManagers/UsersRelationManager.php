<?php

namespace App\Filament\Resources\RoleResource\RelationManagers;

use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Usuarios que tienen el rol (de todos los centros).
 * Solo lo ve el super admin, igual que el resto de "Roles y permisos".
 */
class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Usuarios con este rol';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static ?string $icon = 'heroicon-o-users';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn(Builder $query) => $query->with('center'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->weight('bold')
                    ->description(fn(User $record) => $record->email)
                    ->searchable(['name', 'email'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('center.name')
                    ->label('Centro')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Sin centro')
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->visibleFrom('sm'),
                Tables\Columns\IconColumn::make('super_admin')
                    ->label('Super admin')
                    ->boolean()
                    ->visibleFrom('md'),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('center_id')
                    ->label('Centro')
                    ->relationship('center', 'name')
                    ->preload(),
            ])
            ->headerActions([
                // Solo aparecen los usuarios que todavía NO tienen este rol
                Tables\Actions\AttachAction::make()
                    ->label('Añadir usuarios')
                    ->icon('heroicon-o-user-plus')
                    ->modalHeading('Añadir usuarios al rol')
                    ->modalSubmitActionLabel('Añadir')
                    ->attachAnother(false)
                    ->multiple()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'email'])
                    ->recordSelectOptionsQuery(fn(Builder $query) => $query->with('center')->orderBy('name'))
                    ->recordTitle(fn(User $record) => $record->name . ' · ' . $record->email . ' · ' . ($record->center?->name ?? 'Sin centro'))
                    ->recordSelect(fn(\Filament\Forms\Components\Select $select) => $select
                        ->label('Usuarios')
                        ->placeholder('Busca por nombre o email')
                        ->helperText('Solo aparecen los usuarios que todavía no tienen este rol.'))
                    ->successNotificationTitle('Usuarios añadidos al rol'),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()
                    ->label('Quitar')
                    ->icon('heroicon-o-user-minus')
                    ->modalHeading('Quitar usuario del rol')
                    ->modalDescription('El usuario dejará de tener los permisos de este rol (salvo los que le den otros roles).')
                    ->modalSubmitActionLabel('Sí, quitar')
                    ->successNotificationTitle('Usuario quitado del rol'),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make()
                    ->label('Quitar seleccionados')
                    ->modalHeading('Quitar usuarios del rol')
                    ->modalSubmitActionLabel('Sí, quitar')
                    ->successNotificationTitle('Usuarios quitados del rol'),
            ])
            ->emptyStateHeading('Nadie tiene este rol todavía')
            ->emptyStateDescription('Pulsa "Añadir usuarios" para asignarlo.');
    }
}
