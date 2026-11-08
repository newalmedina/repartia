<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PackageCategoryResource\Pages;
use App\Models\PackageCategory;
use App\Support\Permissions;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Unique;

class PackageCategoryResource extends Resource
{
    protected static ?string $model = PackageCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Reparto';
    protected static ?string $navigationLabel = 'Categorías de pedido';
    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return 'Categoría de pedido';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Categorías de pedido';
    }

    protected static function userCan(string $permission): bool
    {
        return (bool) Auth::user()?->hasPermission($permission);
    }

    public static function canViewAny(): bool
    {
        return static::userCan(Permissions::CATEGORIES_VIEW);
    }

    public static function canCreate(): bool
    {
        return static::userCan(Permissions::CATEGORIES_CREATE);
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCan(Permissions::CATEGORIES_EDIT);
    }

    public static function canDelete(Model $record): bool
    {
        // Además, si ya está usada en pedidos no se puede eliminar
        return static::userCan(Permissions::CATEGORIES_DELETE) && $record->canDelete;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * Cada usuario solo ve las categorías de su centro.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('center_id', Auth::user()?->center_id ?? -1)
            ->withCount('packages');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn(Unique $rule) => $rule->where('center_id', Auth::user()?->center_id),
                    )
                    ->validationMessages([
                        'unique' => 'Ya existe una categoría con ese nombre en tu centro.',
                    ]),
                Forms\Components\Toggle::make('active')
                    ->label('¿Activo?')
                    ->inline(false)
                    ->default(true)
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('Descripción')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('packages_count')
                    ->label('Pedidos')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->label('¿Activo?')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha creación')
                    ->formatStateUsing(fn($state) => Carbon::parse($state)->format('d-m-Y H:i'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('active')
                    ->label('¿Activo?')
                    ->options([
                        '1' => 'Activo',
                        '0' => 'No activo',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->tooltip('Editar')
                    ->visible(fn($record) => static::canEdit($record)),
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->tooltip('Eliminar')
                    ->modalHeading('Eliminar categoría')
                    ->modalDescription('¿Estás seguro de que deseas eliminar esta categoría?')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotificationTitle('Categoría eliminada correctamente')
                    // Si ya está usada en algún pedido no se puede eliminar
                    ->visible(fn($record) => static::canDelete($record)),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPackageCategories::route('/'),
        ];
    }
}
