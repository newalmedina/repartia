<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DailyClosingResource\Pages;
use App\Models\DailyClosing;
use App\Models\User;
use App\Support\Permissions;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;

/**
 * Cuadre de jornada: apertura (administrador) y cierre (repartidor) de cada repartidor y día.
 */
class DailyClosingResource extends Resource
{
    protected static ?string $model = DailyClosing::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Reparto';
    protected static ?string $navigationLabel = 'Cuadres de jornada';
    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return 'Cuadre de jornada';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Cuadres de jornada';
    }

    // ------------------------------------------------------------------
    // Permisos
    // ------------------------------------------------------------------

    protected static function userCan(string $permission): bool
    {
        return (bool) Auth::user()?->hasPermission($permission);
    }

    /** Puede fijar los paquetes entregados y elegir cualquier repartidor */
    public static function isAdministrator(): bool
    {
        return static::userCan(Permissions::CLOSINGS_CONFIRM_ADMIN);
    }

    public static function canViewAny(): bool
    {
        return static::userCan(Permissions::CLOSINGS_VIEW_OWN) || static::userCan(Permissions::CLOSINGS_VIEW_ALL);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::userCan(Permissions::CLOSINGS_CREATE);
    }

    /** Cerrado (confirmado por ambos): ya no se edita */
    public static function canEdit(Model $record): bool
    {
        return static::userCan(Permissions::CLOSINGS_EDIT) && !$record->isClosed();
    }

    /** Confirmado por el administrador: ya no se elimina */
    public static function canDelete(Model $record): bool
    {
        return static::userCan(Permissions::CLOSINGS_DELETE) && !$record->isAdminConfirmed();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visibleTo(Auth::user())
            ->with(['driver', 'driverConfirmer', 'adminConfirmer']);
    }

    /** ¿El usuario puede escribir repartidos/devueltos de este cuadre? (el propio repartidor o un administrador) */
    protected static function canFillDriverPart(?DailyClosing $record, Get $get): bool
    {
        $driverId = $record?->user_id ?? $get('user_id');

        return static::isAdministrator() || (int) $driverId === Auth::id();
    }

    // ------------------------------------------------------------------
    // Formulario
    // ------------------------------------------------------------------

    /** Recuentos del sistema para lo que hay en el formulario */
    protected static function systemFor(Get $get, ?DailyClosing $record): array
    {
        return DailyClosing::systemCountsFor(
            $get('user_id') ? (int) $get('user_id') : null,
            $get('date'),
            $record?->center_id,
        );
    }

    /** "En el sistema: 12 ✓" / "En el sistema: 12 ✗" */
    protected static function systemHint(?int $value, int $system, string $what = ''): HtmlString
    {
        $label = trim("En el sistema: <strong>{$system}</strong> {$what}");

        if ($value === null) {
            return new HtmlString($label);
        }

        return $value === $system
            ? new HtmlString("<span style=\"color: rgb(var(--success-600))\">{$label} ✓</span>")
            : new HtmlString("<span style=\"color: rgb(var(--danger-600))\">{$label} ✗ no cuadra</span>");
    }

    /** "Devueltos + pendientes: 4 · sin entregar en el sistema: 4 ✓" */
    protected static function notDeliveredHint(Get $get, ?DailyClosing $record): HtmlString
    {
        $system   = static::systemFor($get, $record)['not_delivered'];
        $returned = static::intOrNull($get('returned_count'));
        $pending  = static::intOrNull($get('pending_count'));

        if ($returned === null && $pending === null) {
            return new HtmlString("Sin entregar en el sistema: <strong>{$system}</strong>");
        }

        $sum   = ($returned ?? 0) + ($pending ?? 0);
        $label = "Devueltos + pendientes: <strong>{$sum}</strong> · sin entregar en el sistema: <strong>{$system}</strong>";

        return $sum === $system
            ? new HtmlString("<span style=\"color: rgb(var(--success-600))\">{$label} ✓</span>")
            : new HtmlString("<span style=\"color: rgb(var(--danger-600))\">{$label} ✗</span>");
    }

    /** Pendientes = paquetes − repartidos − devueltos (se puede cambiar a mano) */
    protected static function fillPending(Get $get, \Filament\Forms\Set $set): void
    {
        $assigned  = static::intOrNull($get('assigned_count'));
        $delivered = static::intOrNull($get('delivered_count'));
        $returned  = static::intOrNull($get('returned_count')) ?? 0;

        if ($assigned !== null && $delivered !== null) {
            $set('pending_count', max(0, $assigned - $delivered - $returned));
        }
    }

    protected static function intOrNull($value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(12)
                    ->schema([
                        Grid::make(1)
                            ->columnSpan(['default' => 12, 'lg' => 8])
                            ->schema([
                                Section::make('Apertura')
                                    ->description('La rellena el administrador: paquetes que se le entregan al repartidor ese día.')
                                    ->icon('heroicon-o-arrow-up-tray')
                                    ->columns(['default' => 1, 'sm' => 3])
                                    ->schema([
                                        Forms\Components\Select::make('user_id')
                                            ->label('Repartidor')
                                            ->options(function () {
                                                // Sin permiso de administrador: solo uno mismo
                                                if (!static::isAdministrator()) {
                                                    return User::whereKey(Auth::id())->pluck('name', 'id');
                                                }

                                                return PackageResource::driverQuery(User::query())->pluck('name', 'id');
                                            })
                                            ->default(fn() => static::isAdministrator() ? null : Auth::id())
                                            ->searchable()
                                            ->required()
                                            ->live()
                                            ->disabled(fn(?DailyClosing $record) => !static::isAdministrator() || $record?->isAdminConfirmed()),
                                        Forms\Components\DatePicker::make('date')
                                            ->label('Fecha')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->firstDayOfWeek(1)
                                            ->default(today())
                                            ->maxDate(today())
                                            ->required()
                                            ->live()
                                            // Un solo cuadre por repartidor y día
                                            ->unique(
                                                ignoreRecord: true,
                                                modifyRuleUsing: fn(Unique $rule, Get $get) => $rule->where('user_id', $get('user_id')),
                                            )
                                            ->validationMessages(['unique' => 'Ya existe un cuadre para este repartidor en esa fecha.'])
                                            // Al abrir: estado del cuadre anterior de ese repartidor
                                            ->helperText(function (Get $get, ?DailyClosing $record) {
                                                if ($record) {
                                                    return null;
                                                }

                                                $driverId = $get('user_id') ? (int) $get('user_id') : null;
                                                $previous = DailyClosing::previousFor($driverId, $get('date'));

                                                if (!$driverId || !$previous) {
                                                    return null;
                                                }

                                                return $previous->isClosed()
                                                    ? new HtmlString('<span style="color: rgb(var(--success-600))">Cuadre anterior (' . $previous->date->format('d/m/Y') . ') cerrado ✓</span>')
                                                    : new HtmlString('<span style="color: rgb(var(--danger-600))">' . e(DailyClosing::openingBlockedReason($driverId, $get('date'))) . '</span>');
                                            })
                                            ->disabled(fn(?DailyClosing $record) => !static::isAdministrator() || $record?->isAdminConfirmed()),
                                        Forms\Components\TextInput::make('assigned_count')
                                            ->label('Paquetes entregados al repartidor')
                                            ->numeric()
                                            ->minValue(0)
                                            ->integer()
                                            ->live(onBlur: true)
                                            ->disabled(fn() => !static::isAdministrator())
                                            ->helperText(fn(Get $get, ?DailyClosing $record) => static::systemHint(
                                                static::intOrNull($get('assigned_count')),
                                                static::systemFor($get, $record)['assigned'],
                                            )),
                                    ]),

                                Section::make('Cierre')
                                    ->description('La rellena el repartidor al terminar la jornada. Pendientes se calcula solo (paquetes − repartidos − devueltos).')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->columns(['default' => 1, 'sm' => 3])
                                    ->schema([
                                        Forms\Components\TextInput::make('delivered_count')
                                            ->label('Repartidos')
                                            ->numeric()
                                            ->minValue(0)
                                            ->integer()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn(Get $get, \Filament\Forms\Set $set) => static::fillPending($get, $set))
                                            ->disabled(fn(?DailyClosing $record, Get $get) => !static::canFillDriverPart($record, $get))
                                            ->helperText(fn(Get $get, ?DailyClosing $record) => static::systemHint(
                                                static::intOrNull($get('delivered_count')),
                                                static::systemFor($get, $record)['delivered'],
                                            )),
                                        Forms\Components\TextInput::make('returned_count')
                                            ->label('Devueltos')
                                            ->numeric()
                                            ->minValue(0)
                                            ->integer()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn(Get $get, \Filament\Forms\Set $set) => static::fillPending($get, $set))
                                            ->disabled(fn(?DailyClosing $record, Get $get) => !static::canFillDriverPart($record, $get))
                                            ->helperText(fn(Get $get, ?DailyClosing $record) => static::notDeliveredHint($get, $record)),
                                        Forms\Components\TextInput::make('pending_count')
                                            ->label('Pendientes')
                                            ->numeric()
                                            ->minValue(0)
                                            ->integer()
                                            ->live(onBlur: true)
                                            ->disabled(fn(?DailyClosing $record, Get $get) => !static::canFillDriverPart($record, $get))
                                            ->helperText(fn(Get $get, ?DailyClosing $record) => static::notDeliveredHint($get, $record)),
                                    ]),

                                Section::make('Notas')
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        Forms\Components\Textarea::make('notes')
                                            ->hiddenLabel()
                                            ->rows(3)
                                            ->autosize(),
                                    ]),
                            ]),

                        Grid::make(1)
                            ->columnSpan(['default' => 12, 'lg' => 4])
                            ->schema([
                                Section::make('¿Cuadra?')
                                    ->icon('heroicon-o-scale')
                                    ->schema([
                                        Forms\Components\Placeholder::make('balance')
                                            ->hiddenLabel()
                                            ->content(function (Get $get, ?DailyClosing $record) {
                                                $system   = static::systemFor($get, $record);
                                                $problems = DailyClosing::problemsFor(
                                                    static::intOrNull($get('assigned_count')),
                                                    static::intOrNull($get('delivered_count')),
                                                    static::intOrNull($get('returned_count')),
                                                    static::intOrNull($get('pending_count')),
                                                    $system,
                                                );

                                                $summary = "Sistema: <strong>{$system['assigned']}</strong> pedidos · "
                                                    . "<strong>{$system['delivered']}</strong> entregados · "
                                                    . "<strong>{$system['not_delivered']}</strong> sin entregar";

                                                if (!$problems) {
                                                    return new HtmlString('<div style="color: rgb(var(--success-600)); font-weight: 600;">✓ Todo cuadra. Ya se puede confirmar.</div>'
                                                        . '<div class="mt-2 text-xs text-gray-500">' . $summary . '</div>');
                                                }

                                                return new HtmlString(
                                                    '<div style="color: rgb(var(--danger-600)); font-weight: 600;">✗ No cuadra todavía:</div>'
                                                    . '<ul class="mt-1 list-disc ps-4 text-sm">' . collect($problems)->map(fn($p) => '<li>' . e($p) . '</li>')->implode('') . '</ul>'
                                                    . '<div class="mt-2 text-xs text-gray-500">' . $summary . '</div>'
                                                );
                                            }),
                                    ]),

                                Section::make('Confirmaciones')
                                    ->icon('heroicon-o-check-badge')
                                    ->visible(fn(?DailyClosing $record) => $record !== null)
                                    ->schema([
                                        Forms\Components\Placeholder::make('driver_confirmation')
                                            ->label('Repartidor')
                                            ->content(fn(?DailyClosing $record) => $record?->isDriverConfirmed()
                                                ? '✓ ' . $record->driverConfirmer?->name . ' · ' . $record->driver_confirmed_at->format('d/m/Y H:i')
                                                : 'Sin confirmar'),
                                        Forms\Components\Placeholder::make('admin_confirmation')
                                            ->label('Administrador')
                                            ->content(fn(?DailyClosing $record) => $record?->isAdminConfirmed()
                                                ? '✓ ' . $record->adminConfirmer?->name . ' · ' . $record->admin_confirmed_at->format('d/m/Y H:i')
                                                : 'Sin confirmar'),
                                        Forms\Components\Placeholder::make('confirmation_info')
                                            ->hiddenLabel()
                                            ->content('Si se cambia cualquier número, las confirmaciones se quitan y hay que volver a confirmar.'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    // ------------------------------------------------------------------
    // Acciones de confirmar (tabla y páginas)
    // ------------------------------------------------------------------

    /**
     * Botón "Confirmar como repartidor" / "Confirmar como administrador".
     * Si no cuadra, explica qué falla y no confirma.
     * $actionClass: Tables\Actions\Action o Filament\Actions\Action
     */
    public static function confirmAction(string $actionClass, string $who)
    {
        $isDriver = $who === 'driver';

        return $actionClass::make('confirm_' . $who)
            ->label($isDriver ? 'Confirmar como repartidor' : 'Confirmar como administrador')
            ->icon($isDriver ? 'heroicon-o-user' : 'heroicon-o-shield-check')
            ->color($isDriver ? 'info' : 'success')
            ->visible(fn(DailyClosing $record) => $isDriver
                ? $record->canConfirmAsDriver(Auth::user())
                : $record->canConfirmAsAdmin(Auth::user()))
            ->requiresConfirmation()
            ->modalHeading($isDriver ? 'Confirmar cuadre como repartidor' : 'Confirmar cuadre como administrador')
            ->modalDescription(function (DailyClosing $record) {
                $problems = $record->problems();

                return $problems
                    ? new HtmlString('<strong>No se puede confirmar, no cuadra:</strong><br>' . collect($problems)->map(fn($p) => '• ' . e($p))->implode('<br>'))
                    : 'Todo cuadra. Se guardará tu usuario y la hora como confirmación.';
            })
            ->modalSubmitAction(fn($action, DailyClosing $record) => $action->disabled(!$record->isBalanced()))
            ->modalSubmitActionLabel('Confirmar')
            ->action(function (DailyClosing $record) use ($isDriver) {
                if (!$record->isBalanced()) {
                    Notification::make()->title('No cuadra: no se puede confirmar')->danger()->send();

                    return;
                }

                $isDriver ? $record->confirmAsDriver(Auth::user()) : $record->confirmAsAdmin(Auth::user());

                Notification::make()
                    ->title($record->fresh()->isClosed() ? 'Cuadre cerrado (confirmado por ambos)' : 'Confirmación guardada')
                    ->success()
                    ->send();
            });
    }

    // ------------------------------------------------------------------
    // Tabla
    // ------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        $hint = function (DailyClosing $record, string $key, ?int $value) {
            $system = $record->systemCounts()[$key];

            return $value === null ? "Sistema: {$system}" : ($value === $system ? "Sistema: {$system} ✓" : "Sistema: {$system} ✗");
        };

        $color = fn(DailyClosing $record, string $key, ?int $value) => $value === null
            ? 'gray'
            : ($value === $record->systemCounts()[$key] ? 'success' : 'danger');

        // Devueltos + pendientes frente a "sin entregar" del sistema
        $notDeliveredSum = fn(DailyClosing $record) => $record->returned_count === null && $record->pending_count === null
            ? null
            : (int) $record->returned_count + (int) $record->pending_count;

        $notDeliveredHint = function (DailyClosing $record) use ($notDeliveredSum) {
            $system = $record->systemCounts()['not_delivered'];
            $sum    = $notDeliveredSum($record);

            return $sum === null ? "Sin entregar: {$system}" : ($sum === $system ? "Sin entregar: {$system} ✓" : "Sin entregar: {$system} ✗");
        };

        $notDeliveredColor = function (DailyClosing $record) use ($notDeliveredSum) {
            $sum = $notDeliveredSum($record);

            return $sum === null ? 'gray' : ($sum === $record->systemCounts()['not_delivered'] ? 'success' : 'danger');
        };

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->weight('bold')
                    ->sortable()
                    ->description(fn(DailyClosing $record) => $record->driver?->name),
                Tables\Columns\TextColumn::make('assigned_count')
                    ->label('Entregados al repartidor')
                    ->placeholder('—')
                    ->alignCenter()
                    ->description(fn(DailyClosing $record) => $hint($record, 'assigned', $record->assigned_count))
                    ->color(fn(DailyClosing $record) => $color($record, 'assigned', $record->assigned_count)),
                Tables\Columns\TextColumn::make('delivered_count')
                    ->label('Repartidos')
                    ->placeholder('—')
                    ->alignCenter()
                    ->description(fn(DailyClosing $record) => $hint($record, 'delivered', $record->delivered_count))
                    ->color(fn(DailyClosing $record) => $color($record, 'delivered', $record->delivered_count))
                    ->visibleFrom('sm'),
                Tables\Columns\TextColumn::make('returned_count')
                    ->label('Devueltos')
                    ->placeholder('—')
                    ->alignCenter()
                    ->description(fn(DailyClosing $record) => $notDeliveredHint($record))
                    ->color(fn(DailyClosing $record) => $notDeliveredColor($record))
                    ->visibleFrom('sm'),
                Tables\Columns\TextColumn::make('pending_count')
                    ->label('Pendientes')
                    ->placeholder('—')
                    ->alignCenter()
                    ->description(fn(DailyClosing $record) => $notDeliveredHint($record))
                    ->color(fn(DailyClosing $record) => $notDeliveredColor($record))
                    ->visibleFrom('sm'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => DailyClosing::statusOptions()[$state] ?? $state)
                    ->color(fn(string $state) => DailyClosing::statusColors()[$state] ?? 'gray'),
                Tables\Columns\IconColumn::make('driver_confirmed_at')
                    ->label('Conf. repartidor')
                    ->alignCenter()
                    ->state(fn(DailyClosing $record) => $record->isDriverConfirmed())
                    ->boolean()
                    ->tooltip(fn(DailyClosing $record) => $record->isDriverConfirmed()
                        ? $record->driverConfirmer?->name . ' · ' . $record->driver_confirmed_at->format('d/m/Y H:i')
                        : 'Sin confirmar')
                    ->visibleFrom('md'),
                Tables\Columns\IconColumn::make('admin_confirmed_at')
                    ->label('Conf. administrador')
                    ->alignCenter()
                    ->state(fn(DailyClosing $record) => $record->isAdminConfirmed())
                    ->boolean()
                    ->tooltip(fn(DailyClosing $record) => $record->isAdminConfirmed()
                        ? $record->adminConfirmer?->name . ' · ' . $record->admin_confirmed_at->format('d/m/Y H:i')
                        : 'Sin confirmar')
                    ->visibleFrom('md'),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Repartidor')
                    ->relationship('driver', 'name', fn(Builder $query) => PackageResource::driverQuery($query))
                    ->searchable()
                    ->preload()
                    ->visible(fn() => static::userCan(Permissions::CLOSINGS_VIEW_ALL)),
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Desde')->native(false)->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('until')->label('Hasta')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn(Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn($q, $d) => $q->whereDate('date', '>=', \App\Support\Dates::day($d)))
                        ->when($data['until'] ?? null, fn($q, $d) => $q->whereDate('date', '<=', \App\Support\Dates::day($d)))),
                Tables\Filters\TernaryFilter::make('closed')
                    ->label('Cerrados')
                    ->placeholder('Todos')
                    ->trueLabel('Cerrados (confirmados por ambos)')
                    ->falseLabel('Sin cerrar')
                    ->queries(
                        true: fn(Builder $query) => $query->whereNotNull('driver_confirmed_at')->whereNotNull('admin_confirmed_at'),
                        false: fn(Builder $query) => $query->where(fn($q) => $q->whereNull('driver_confirmed_at')->orWhereNull('admin_confirmed_at')),
                        blank: fn(Builder $query) => $query,
                    ),
            ])
            ->actions([
                static::confirmAction(Tables\Actions\Action::class, 'driver')->label('')->tooltip('Confirmar como repartidor'),
                static::confirmAction(Tables\Actions\Action::class, 'admin')->label('')->tooltip('Confirmar como administrador'),
                Tables\Actions\ViewAction::make()->label('')->tooltip('Ver'),
                Tables\Actions\EditAction::make()->label('')->tooltip('Editar'),
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->tooltip('Eliminar')
                    ->modalHeading('Eliminar cuadre')
                    ->successNotificationTitle('Cuadre eliminado'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDailyClosings::route('/'),
            'create' => Pages\CreateDailyClosing::route('/create'),
            'view'   => Pages\ViewDailyClosing::route('/{record}'),
            'edit'   => Pages\EditDailyClosing::route('/{record}/edit'),
        ];
    }
}
