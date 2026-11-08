<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Filament\Resources\PaymentResource\RelationManagers;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Support\Permissions;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * Pagos de comisiones a repartidores.
 * Se eligen repartidor y periodo, y se pagan sus pedidos ENTREGADOS en ese periodo
 * (se pueden quitar), más importes extra con comentario.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Reparto';
    protected static ?string $navigationLabel = 'Pagos a repartidores';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'number';

    public static function getModelLabel(): string
    {
        return 'Pago';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pagos a repartidores';
    }

    // ------------------------------------------------------------------
    // Permisos
    // ------------------------------------------------------------------

    protected static function userCan(string $permission): bool
    {
        return (bool) Auth::user()?->hasPermission($permission);
    }

    public static function canViewAny(): bool
    {
        return static::userCan(Permissions::PAYMENTS_VIEW);
    }

    public static function canView(Model $record): bool
    {
        return static::userCan(Permissions::PAYMENTS_VIEW);
    }

    public static function canCreate(): bool
    {
        return static::userCan(Permissions::PAYMENTS_CREATE);
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCan(Permissions::PAYMENTS_EDIT);
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCan(Permissions::PAYMENTS_DELETE);
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /** Solo pagos de su centro */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('center_id', Auth::user()?->center_id ?? -1)
            ->with(['driver', 'creator']);
    }

    /** [id pedido => id pago] de las opciones mostradas (evita una consulta por casilla) */
    protected static array $optionPayments = [];

    public static function euros($amount): string
    {
        return number_format((float) $amount, 2, ',', '.') . ' €';
    }

    // ------------------------------------------------------------------
    // Formulario
    // ------------------------------------------------------------------

    /** Al cambiar repartidor o fechas: se marcan todos los entregados sin pagar */
    protected static function selectAllUnpaid(Get $get, Set $set, ?Payment $record): void
    {
        $ids = Payment::deliveredPackagesFor($get('user_id'), $get('period_from'), $get('period_to'))
            ->filter(fn(Package $p) => $p->payment_id === null || $p->payment_id === $record?->id)
            ->pluck('id')
            ->map(fn($id) => (string) $id)
            ->values()
            ->all();

        $set('package_ids', $ids);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(12)
                    ->schema([
                        // ---------------- Columna principal ----------------
                        Grid::make(1)
                            ->columnSpan(['default' => 12, 'lg' => 8])
                            ->schema([
                                Section::make('Repartidor y periodo')
                                    ->icon('heroicon-o-user')
                                    ->description('Se buscan sus pedidos ENTREGADOS entre estas fechas (por fecha de entrega).')
                                    ->columns(['default' => 1, 'sm' => 3])
                                    ->schema([
                                        Forms\Components\Select::make('user_id')
                                            ->label('Repartidor')
                                            ->options(fn() => User::query()
                                                ->where('center_id', Auth::user()?->center_id ?? -1)
                                                ->orderBy('name')
                                                ->pluck('name', 'id'))
                                            ->searchable()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn(Get $get, Set $set, ?Payment $record) => static::selectAllUnpaid($get, $set, $record)),
                                        Forms\Components\DatePicker::make('period_from')
                                            ->label('Desde')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->firstDayOfWeek(1)
                                            ->default(fn() => now()->subWeek()->startOfWeek(Carbon::MONDAY)->toDateString())
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn(Get $get, Set $set, ?Payment $record) => static::selectAllUnpaid($get, $set, $record)),
                                        Forms\Components\DatePicker::make('period_to')
                                            ->label('Hasta')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->firstDayOfWeek(1)
                                            ->default(fn() => now()->subWeek()->endOfWeek(Carbon::SUNDAY)->toDateString())
                                            ->minDate(fn(Get $get) => $get('period_from'))
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn(Get $get, Set $set, ?Payment $record) => static::selectAllUnpaid($get, $set, $record)),
                                    ]),

                                Section::make('Pedidos entregados en el periodo')
                                    ->icon('heroicon-o-cube')
                                    ->description('Desmarca los que no quieras pagar ahora. Los ya pagados en otro pago aparecen deshabilitados.')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('package_ids')
                                            ->hiddenLabel()
                                            ->options(function (Get $get, ?Payment $record) {
                                                $packages = Payment::deliveredPackagesFor($get('user_id'), $get('period_from'), $get('period_to'));
                                                static::$optionPayments = $packages->pluck('payment_id', 'id')->all();

                                                return $packages
                                                    ->mapWithKeys(function (Package $p) use ($record) {
                                                        $label = $p->order_number
                                                            . ' · ' . $p->delivered_at?->format('d/m H:i')
                                                            . ' · ' . $p->address
                                                            . ' · ' . static::euros($p->delivery_price);

                                                        if ($p->same_address) {
                                                            $label .= ' (misma dirección)';
                                                        }
                                                        if ($p->payment_id && $p->payment_id !== $record?->id) {
                                                            $label .= ' — YA PAGADO en ' . $p->payment?->number;
                                                        }

                                                        return [(string) $p->id => $label];
                                                    })
                                                    ->all();
                                            })
                                            // Pagados en OTRO pago: no se pueden marcar
                                            ->disableOptionWhen(function (string $value, ?Payment $record) {
                                                $paymentId = static::$optionPayments[(int) $value]
                                                    ?? Package::whereKey($value)->value('payment_id');

                                                return $paymentId !== null && (int) $paymentId !== $record?->id;
                                            })
                                            ->bulkToggleable()
                                            ->searchable()
                                            ->columns(1)
                                            ->live()
                                            ->noSearchResultsMessage('No hay pedidos que coincidan.')
                                            ->helperText(fn(Get $get) => $get('user_id')
                                                ? null
                                                : 'Elige un repartidor y el periodo para ver sus pedidos entregados.'),
                                    ]),

                                Section::make('Importes extra')
                                    ->icon('heroicon-o-plus-circle')
                                    ->description('Por ejemplo: gasolina, bonus, ajuste... Usa importes negativos para descontar.')
                                    ->collapsible()
                                    ->schema([
                                        Forms\Components\Repeater::make('extras')
                                            ->hiddenLabel()
                                            ->relationship()
                                            ->schema([
                                                Forms\Components\TextInput::make('amount')
                                                    ->label('Importe')
                                                    ->numeric()
                                                    ->step(0.01)
                                                    ->prefix('€')
                                                    ->required()
                                                    ->live(onBlur: true),
                                                Forms\Components\TextInput::make('concept')
                                                    ->label('Comentario')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                                            ])
                                            ->columns(['default' => 1, 'sm' => 3])
                                            ->addActionLabel('Añadir importe extra')
                                            ->defaultItems(0)
                                            ->reorderable(false)
                                            ->live(),
                                    ]),
                            ]),

                        // ---------------- Columna lateral ----------------
                        Grid::make(1)
                            ->columnSpan(['default' => 12, 'lg' => 4])
                            ->schema([
                                Section::make('Total a pagar')
                                    ->icon('heroicon-o-banknotes')
                                    ->schema([
                                        Forms\Components\Placeholder::make('totals')
                                            ->hiddenLabel()
                                            ->content(function (Get $get) {
                                                $ids      = array_map('intval', $get('package_ids') ?? []);
                                                $packages = $ids ? (float) Package::whereIn('id', $ids)->sum('delivery_price') : 0;
                                                $extras   = collect($get('extras') ?? [])->sum(fn($e) => (float) ($e['amount'] ?? 0));

                                                return new HtmlString(
                                                    '<div class="space-y-1 text-sm">'
                                                    . '<div class="flex justify-between"><span>Pedidos (' . count($ids) . ')</span><span>' . static::euros($packages) . '</span></div>'
                                                    . '<div class="flex justify-between"><span>Extras</span><span>' . static::euros($extras) . '</span></div>'
                                                    . '<div class="flex justify-between border-t pt-2 text-lg font-bold dark:border-white/10"><span>Total</span><span>' . static::euros($packages + $extras) . '</span></div>'
                                                    . '</div>'
                                                );
                                            }),
                                    ]),

                                Section::make('Pago')
                                    ->icon('heroicon-o-calendar')
                                    ->schema([
                                        Forms\Components\DatePicker::make('paid_at')
                                            ->label('Fecha de pago')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->firstDayOfWeek(1)
                                            ->default(today()),
                                        Forms\Components\Textarea::make('notes')
                                            ->label('Notas')
                                            ->rows(3)
                                            ->autosize(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    // ------------------------------------------------------------------
    // Tabla
    // ------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Nº')
                    ->formatStateUsing(fn(Payment $record) => $record->number)
                    ->weight('bold')
                    ->sortable()
                    ->description(fn(Payment $record) => $record->driver?->name),
                Tables\Columns\TextColumn::make('period_from')
                    ->label('Periodo')
                    ->formatStateUsing(fn(Payment $record) => $record->period_from->format('d/m/Y') . ' – ' . $record->period_to->format('d/m/Y'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('packages_count')
                    ->label('Pedidos')
                    ->badge()
                    ->visibleFrom('sm'),
                Tables\Columns\TextColumn::make('packages_amount')
                    ->label('Comisiones')
                    ->money('EUR')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('extras_amount')
                    ->label('Extras')
                    ->money('EUR')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('EUR')
                    ->weight('bold')
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Pagado el')
                    ->date('d/m/Y')
                    ->sortable()
                    ->visibleFrom('sm'),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Creado por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Repartidor')
                    ->relationship('driver', 'name', fn(Builder $query) => $query->where('center_id', Auth::user()?->center_id ?? -1))
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('paid_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Pagado desde')->native(false)->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('until')->label('Pagado hasta')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn(Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn($q, $d) => $q->whereDate('paid_at', '>=', $d))
                        ->when($data['until'] ?? null, fn($q, $d) => $q->whereDate('paid_at', '<=', $d))),
            ])
            ->actions([
                Tables\Actions\Action::make('pdf')
                    ->label('')
                    ->tooltip('Descargar recibo en PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->action(fn(Payment $record) => \App\Support\Reports::paymentReceiptPdf($record)),
                Tables\Actions\Action::make('excel')
                    ->label('')
                    ->tooltip('Descargar recibo en Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn(Payment $record) => \App\Support\Reports::paymentReceiptExcel($record)),
                Tables\Actions\ViewAction::make()->label('')->tooltip('Ver'),
                Tables\Actions\EditAction::make()->label('')->tooltip('Editar'),
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->tooltip('Eliminar')
                    ->modalHeading('Eliminar pago')
                    ->modalDescription('Los pedidos de este pago volverán a quedar sin pagar. ¿Continuar?')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->successNotificationTitle('Pago eliminado'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PackagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'view'   => Pages\ViewPayment::route('/{record}'),
            'edit'   => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
