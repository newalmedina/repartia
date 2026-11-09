<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PackageResource\Pages;
use App\Models\Package;
use App\Models\User;
use App\Support\Permissions;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;

class PackageResource extends Resource
{
    protected static ?string $model = Package::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Reparto';
    protected static ?string $navigationLabel = 'Pedidos';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'order_number';

    public static function getModelLabel(): string
    {
        return 'Pedido';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pedidos';
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
        return Auth::user()?->hasAnyPermission([
            Permissions::PACKAGES_VIEW_OWN,
            Permissions::PACKAGES_VIEW_ALL,
        ]) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::userCan(Permissions::PACKAGES_CREATE);
    }

    /** Entregado/devuelto hace más de 24 h: solo quien tenga "Editar y eliminar pedidos cerrados" */
    public static function canEdit(Model $record): bool
    {
        return static::userCan(Permissions::PACKAGES_EDIT) && !$record->isLockedFor(Auth::user());
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCan(Permissions::PACKAGES_DELETE) && !$record->isLockedFor(Auth::user());
    }

    public static function canDeleteAny(): bool
    {
        return static::userCan(Permissions::PACKAGES_DELETE);
    }

    /**
     * Solo pedidos de su centro; y si no tiene "ver todos", solo los suyos.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visibleTo(Auth::user())
            ->with(['category', 'driver', 'payment']);
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getEloquentQuery()->pending()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pedidos pendientes';
    }

    /** ¿Puede asignar pedidos a cualquier repartidor? (con "Ver todos los pedidos del centro") */
    public static function canAssignAnyDriver(): bool
    {
        return (bool) Auth::user()?->canViewAllPackages();
    }

    /** Repartidores activos del mismo centro */
    public static function driverQuery(Builder $query): Builder
    {
        return $query
            ->where('center_id', Auth::user()?->center_id ?? -1)
            ->where('active', true)
            ->orderBy('name');
    }

    // ------------------------------------------------------------------
    // Formulario
    // ------------------------------------------------------------------

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
                                Section::make('Datos del pedido')
                                    ->icon('heroicon-o-cube')
                                    ->columns(['default' => 1, 'sm' => 2, 'xl' => 3])
                                    ->schema([
                                        Forms\Components\TextInput::make('order_number')
                                            ->label('Número de pedido')
                                            ->required()
                                            ->maxLength(100)
                                            ->unique(
                                                ignoreRecord: true,
                                                modifyRuleUsing: fn(Unique $rule) => $rule->where('center_id', Auth::user()?->center_id),
                                            )
                                            ->validationMessages([
                                                'unique' => 'Ese número de pedido ya existe en tu centro.',
                                            ]),
                                        Forms\Components\DatePicker::make('shipping_date')
                                            ->label('Fecha de envío')
                                            ->default(today())
                                            ->displayFormat('d/m/Y')
                                            ->native(false)
                                            ->closeOnDateSelection()
                                            ->required(),
                                        Forms\Components\Select::make('package_category_id')
                                            ->label('Categoría')
                                            ->relationship(
                                                'category',
                                                'name',
                                                fn(Builder $query) => $query
                                                    ->where('center_id', Auth::user()?->center_id ?? -1)
                                                    ->where('active', true)
                                                    ->orderBy('name'),
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('Nombre')
                                                    ->required()
                                                    ->maxLength(255),
                                            ])
                                            ->createOptionModalHeading('Nueva categoría')
                                            // El "+" solo aparece con permiso para crear categorías
                                            ->createOptionAction(fn(Forms\Components\Actions\Action $action) => $action
                                                ->visible(fn() => static::userCan(Permissions::CATEGORIES_CREATE))),
                                    ]),

                                Section::make('Destinatario')
                                    ->icon('heroicon-o-user')
                                    ->columns(['default' => 1, 'sm' => 2])
                                    ->schema([
                                        Forms\Components\TextInput::make('recipient_name')
                                            ->label('Destinatario')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('recipient_document')
                                            ->label('Documento (DNI/NIE/CIF)')
                                            ->maxLength(50),
                                    ]),

                                Section::make('Dirección de entrega')
                                    ->icon('heroicon-o-map-pin')
                                    ->columns(['default' => 1, 'sm' => 2])
                                    ->schema([
                                        Forms\Components\TextInput::make('address')
                                            ->label('Dirección')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('postal_code')
                                            ->label('Código postal')
                                            ->maxLength(10),
                                        Forms\Components\TextInput::make('municipality')
                                            ->label('Municipio')
                                            ->maxLength(150),
                                        Forms\Components\TextInput::make('province')
                                            ->label('Provincia')
                                            ->maxLength(150),
                                        Forms\Components\TextInput::make('country')
                                            ->label('País')
                                            ->default('ESPAÑA')
                                            ->required()
                                            ->maxLength(100),
                                    ]),

                                Section::make('Fotos')
                                    ->icon('heroicon-o-camera')
                                    ->collapsible()
                                    ->schema([
                                        Forms\Components\FileUpload::make('photos')
                                            ->hiddenLabel()
                                            ->image()
                                            ->multiple()
                                            ->reorderable()
                                            ->appendFiles()
                                            ->imageEditor()
                                            ->panelLayout('grid')
                                            ->maxFiles(10)
                                            ->maxSize(5120) // 5 MB por foto
                                            ->disk('public')
                                            ->directory('packages')
                                            ->visibility('public')
                                            ->openable()
                                            ->downloadable()
                                            ->helperText('Puedes subir varias fotos (máx. 10, 5 MB cada una). Desde el móvil puedes usar la cámara.'),
                                    ]),
                            ]),

                        // ---------------- Columna lateral ----------------
                        Grid::make(1)
                            ->columnSpan(['default' => 12, 'lg' => 4])
                            ->schema([
                                Section::make('Reparto')
                                    ->icon('heroicon-o-truck')
                                    ->schema([
                                        Forms\Components\Placeholder::make('payment_info')
                                            ->label('Pagado al repartidor')
                                            ->visible(fn(?Package $record) => $record?->isPaid() ?? false)
                                            ->content(function (?Package $record) {
                                                $payment = $record->payment;
                                                $text    = e($payment->number) . ($payment->paid_at ? ' · ' . $payment->paid_at->format('d/m/Y') : '');

                                                $link = PaymentResource::canViewAny()
                                                    ? '<a href="' . PaymentResource::getUrl('view', ['record' => $payment]) . '" class="text-primary-600 underline">' . $text . '</a>'
                                                    : $text;

                                                return new \Illuminate\Support\HtmlString($link . '<br><span class="text-xs text-gray-500">Un pedido pagado no se puede modificar.</span>');
                                            }),
                                        Forms\Components\Select::make('user_id')
                                            ->label('Repartidor')
                                            // Sin "Ver todos los pedidos del centro": solo a su nombre
                                            ->relationship('driver', 'name', fn(Builder $query) => static::canAssignAnyDriver()
                                                ? static::driverQuery($query)
                                                : $query->whereKey(Auth::id() ?? -1))
                                            ->default(fn() => static::canAssignAnyDriver() ? null : Auth::id())
                                            ->disabled(fn() => !static::canAssignAnyDriver())
                                            ->helperText(fn() => static::canAssignAnyDriver() ? null : 'Los pedidos que crees quedan a tu nombre.')
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->required(fn(Get $get) => $get('status') === Package::STATUS_DELIVERED)
                                            ->validationMessages([
                                                'required' => 'Para marcarlo como entregado tiene que tener repartidor.',
                                            ])
                                            ->helperText(function (Get $get) {
                                                $price = User::find($get('user_id'))?->price_per_package;

                                                return $price !== null
                                                    ? 'Cobra ' . number_format((float) $price, 2, ',', '.') . ' € por paquete.'
                                                    : null;
                                            }),
                                        Forms\Components\ToggleButtons::make('status')
                                            ->label('Estado')
                                            ->options(Package::statusOptions())
                                            ->colors(Package::statusColors())
                                            ->icons(Package::statusIcons())
                                            ->default(Package::STATUS_PENDING)
                                            ->inline()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                                                // Propone "ahora" si todavía no hay fecha
                                                if ($state === Package::STATUS_DELIVERED && blank($get('delivered_at'))) {
                                                    $set('delivered_at', now()->format('Y-m-d H:i:00'));
                                                }
                                                if ($state === Package::STATUS_RETURNED && blank($get('returned_at'))) {
                                                    $set('returned_at', now()->format('Y-m-d H:i:00'));
                                                }
                                            }),
                                        static::closingDatePicker('delivered_at', 'Fecha de entrega', Package::STATUS_DELIVERED),
                                        static::closingDatePicker('returned_at', 'Fecha de devolución', Package::STATUS_RETURNED),
                                        Forms\Components\Placeholder::make('editable_until_info')
                                            ->label('Se puede modificar hasta')
                                            ->content(fn(?Package $record) => $record?->editableUntil()?->format('d-m-Y H:i'))
                                            ->visible(fn(?Package $record) => $record?->editableUntil() !== null && !$record->isLocked()),
                                        Forms\Components\Placeholder::make('same_address_info')
                                            ->label('Misma dirección ese día')
                                            ->visible(fn(?Package $record) => $record?->hasSameAddressSiblings() ?? false)
                                            ->content(function (?Package $record) {
                                                $lines = $record->sameAddressSiblings()->get()
                                                    ->map(fn(Package $p) => e($p->order_number) . ' · ' . e($p->recipient_name)
                                                        . ' · ' . ($p->same_address ? 'no cobra' : '<strong>cobra</strong>'))
                                                    ->implode('<br>');

                                                return new \Illuminate\Support\HtmlString($lines);
                                            }),
                                        Forms\Components\TextInput::make('delivery_price')
                                            ->label('Comisión de la entrega')
                                            ->prefix('€')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->helperText(fn(?Package $record) => $record?->same_address
                                                ? 'Sin comisión: el repartidor cobra por dirección y día, y ya se pagó otra entrega en esta dirección ese día.'
                                                : 'Se rellena con el precio por paquete del repartidor al marcarlo como entregado. Los devueltos no generan comisión.'),
                                    ]),

                                Section::make('Comentarios')
                                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                                    ->schema([
                                        Forms\Components\Textarea::make('comments')
                                            ->hiddenLabel()
                                            ->placeholder('Cualquier cosa que quieras anotar…')
                                            ->rows(5)
                                            ->autosize(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    // ------------------------------------------------------------------
    // Tabla
    // ------------------------------------------------------------------

    /** Acción rápida para cambiar el estado desde la tabla */
    protected static function statusAction(string $status, string $label, string $icon, string $color): Tables\Actions\Action
    {
        $dateColumn = Package::closingDateColumn($status);

        return Tables\Actions\Action::make('mark_' . $status)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->modalHeading($label)
            ->modalWidth('md')
            ->modalSubmitActionLabel('Guardar')
            ->visible(fn(Package $record) => $record->status !== $status && static::canEdit($record))
            // Entregado / devuelto: pide fecha y hora (ahora por defecto). Pendiente: solo confirmación.
            ->requiresConfirmation(fn() => $dateColumn === null)
            ->form(function (Package $record) use ($dateColumn, $status) {
                if (!$dateColumn) {
                    return [];
                }

                $fields = [
                    static::closingDateInput('date', Package::closingDateLabel($status))
                        ->minDate($record->shipping_date),
                ];

                // Al entregar en una dirección ya facturada con otra forma de pago / precio: preguntar
                if ($status === Package::STATUS_DELIVERED) {
                    $diff = Package::pricingStaleness($record->user_id, now()->toDateString(), $record->address_key);
                    if ($diff) {
                        $fields[] = static::pricingPolicyRadio($diff);
                    }
                }

                return $fields;
            })
            ->action(function (Package $record, array $data) use ($status, $dateColumn) {
                if ($status === Package::STATUS_DELIVERED && !$record->user_id) {
                    Notification::make()
                        ->title('Asigna un repartidor antes de marcarlo como entregado')
                        ->warning()
                        ->send();

                    return;
                }

                $attributes = Package::statusAttributes($status);
                if ($dateColumn) {
                    $attributes[$dateColumn] = $data['date'] ?? now();
                }

                $record->pricingPolicy = $data['policy'] ?? Package::POLICY_KEEP;
                $record->update($attributes);

                Notification::make()->title('Pedido actualizado')->success()->send();
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            // Tarjetas con contadores encima de la tabla (debajo de las pestañas):
            // las del mes y el resumen según los filtros aplicados
            ->header(fn($livewire) => view('filament.packages.summary-cards', [
                'cards'    => Pages\ListPackages::summaryCards(),
                'filtered' => method_exists($livewire, 'filteredSummaryCards') ? $livewire->filteredSummaryCards() : [],
                'applied'  => method_exists($livewire, 'appliedFiltersLegend') ? $livewire->appliedFiltersLegend() : [],
            ]))
            ->modifyQueryUsing(fn(Builder $query) => $query
                ->select('packages.*')
                ->addSelect(['same_address_count' => \Illuminate\Support\Facades\DB::table('packages as siblings')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('siblings.user_id', 'packages.user_id')
                    ->whereColumn('siblings.address_key', 'packages.address_key')
                    ->whereColumn('siblings.id', '!=', 'packages.id')
                    ->where('siblings.delivered', true)
                    ->where('packages.delivered', true)
                    ->whereRaw('DATE(siblings.delivered_at) = DATE(packages.delivered_at)'),
                ]))
            ->columns([
                Tables\Columns\ImageColumn::make('photos')
                    ->label('Fotos')
                    ->disk('public')
                    ->circular()
                    ->stacked()
                    ->limit(3)
                    ->limitedRemainingText()
                    ->visibleFrom('md'),
                // En móvil esta columna muestra también destinatario y municipio debajo
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Nº pedido')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->description(fn(Package $record) => trim($record->recipient_name . ' · ' . ($record->municipality ?? ''), ' ·')),
                // Icono: misma dirección, repartidor y día
                Tables\Columns\IconColumn::make('same_address_count')
                    ->label('')
                    ->alignCenter()
                    ->icon(fn(Package $record) => (int) $record->same_address_count > 0
                        ? ($record->same_address ? 'heroicon-o-map-pin' : 'heroicon-s-map-pin')
                        : null)
                    ->color(fn(Package $record) => match (true) {
                        $record->same_address                      => 'gray',    // no cobra
                        (bool) $record->driver?->paysPerAddress()  => 'success', // cobra por la dirección
                        default                                    => 'info',    // por paquete: cobran todos
                    })
                    ->tooltip(function (Package $record) {
                        $others = (int) $record->same_address_count;

                        if ($others === 0) {
                            return null;
                        }

                        if ($record->same_address) {
                            $paid = $record->paidSameAddressSibling();

                            return 'Misma dirección, repartidor y día: NO cobra' . ($paid ? " (cobra {$paid->order_number})" : '');
                        }

                        return $record->driver?->paysPerAddress()
                            ? "Cobra por esta dirección · +{$others} paquete(s) más en la misma dirección ese día"
                            : "Misma dirección ese día (+{$others}) · el repartidor cobra por paquete: cobran todos";
                    }),
                Tables\Columns\TextColumn::make('shipping_date')
                    ->label('Fecha envío')
                    ->date('d-m-Y')
                    ->sortable()
                    ->visibleFrom('sm'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => Package::statusOptions()[$state] ?? $state)
                    ->color(fn(string $state) => Package::statusColors()[$state] ?? 'gray')
                    ->icon(fn(string $state) => Package::statusIcons()[$state] ?? null),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('recipient_document')
                    ->label('Documento')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('address')
                    ->label('Dirección')
                    ->sortable()
                    ->searchable(['address', 'municipality', 'province', 'postal_code', 'recipient_name'])
                    ->limit(40)
                    ->tooltip(fn(Package $record) => $record->full_address)
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('province')
                    ->label('Provincia')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Repartidor')
                    ->placeholder('Sin asignar')
                    ->sortable()
                    ->visible(fn() => Auth::user()?->canViewAllPackages())
                    ->visibleFrom('sm'),
                Tables\Columns\TextColumn::make('delivery_price')
                    ->label('Comisión')
                    ->money('EUR')
                    ->placeholder('—')
                    // Pago por dirección y día: quién cobra y cuántos comparten dirección
                    ->description(function (Package $record) {
                        if ($record->same_address) {
                            $paid = $record->paidSameAddressSibling();

                            return 'Misma dirección' . ($paid ? ' · cobra ' . $paid->order_number : '');
                        }

                        $others = (int) $record->same_address_count;
                        if ($others > 0 && $record->driver?->paysPerAddress()) {
                            return "Cobra · +{$others} en la misma dirección";
                        }

                        return null;
                    })
                    ->color(fn(Package $record) => $record->same_address ? 'gray' : null)
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\IconColumn::make('payment_id')
                    ->label('Pagado')
                    ->alignCenter()
                    ->icon(fn(Package $record) => $record->payment_id ? 'heroicon-s-banknotes' : null)
                    ->color('success')
                    ->tooltip(fn(Package $record) => $record->payment
                        ? 'Pagado en ' . $record->payment->number . ($record->payment->paid_at ? ' el ' . $record->payment->paid_at->format('d/m/Y') : '') . '. No se puede modificar.'
                        : null)
                    ->url(fn(Package $record) => $record->payment_id && PaymentResource::canViewAny()
                        ? PaymentResource::getUrl('view', ['record' => $record->payment_id])
                        : null),
                Tables\Columns\IconColumn::make('locked')
                    ->label('')
                    ->state(fn(Package $record) => $record->isLocked())
                    ->icon(fn(bool $state) => $state ? 'heroicon-o-lock-closed' : null)
                    ->color('gray')
                    ->tooltip(fn(bool $state) => match (true) {
                        !$state => null,
                        (bool) Auth::user()?->hasPermission(Permissions::PACKAGES_BYPASS_LOCK) => 'Cerrado hace más de 24 h (tienes permiso para modificarlo)',
                        default => 'Cerrado hace más de 24 h: ya no se puede modificar',
                    }),
                Tables\Columns\TextColumn::make('delivered_at')
                    ->label('Entregado')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('returned_at')
                    ->label('Devuelto')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('shipping_date', 'desc')
            // Filtros siempre visibles encima de la tabla
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(['default' => 1, 'md' => 2, 'xl' => 4])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Repartidor')
                    ->relationship('driver', 'name', fn(Builder $query) => static::driverQuery($query))
                    ->searchable()
                    ->preload()
                    ->indicator('Repartidor')
                    // Filtrar por repartidor solo tiene sentido si ve todos los pedidos
                    ->visible(fn() => Auth::user()?->canViewAllPackages()),
                Tables\Filters\SelectFilter::make('package_category_id')
                    ->label('Categoría')
                    ->relationship('category', 'name', fn(Builder $query) => $query->where('center_id', Auth::user()?->center_id ?? -1))
                    ->preload()
                    ->indicator('Categoría'),
                Tables\Filters\TernaryFilter::make('paid')
                    ->label('Pago al repartidor')
                    ->placeholder('Todos')
                    ->trueLabel('Pagados')
                    ->falseLabel('Entregados sin pagar')
                    ->queries(
                        true: fn(Builder $query) => $query->whereNotNull('packages.payment_id'),
                        false: fn(Builder $query) => $query->where('packages.delivered', true)->whereNull('packages.payment_id'),
                        blank: fn(Builder $query) => $query,
                    ),
                // Casilla: paquetes de la misma dirección, mismo repartidor y mismo día
                // (el que cobra y los que no), para ver cuánto se ha cobrado por ellos
                Tables\Filters\Filter::make('shared_address')
                    ->label('Paquetes misma dirección, repartidor y día')
                    ->checkbox()
                    ->query(fn(Builder $query) => $query->inSharedAddress())
                    ->indicator('Misma dirección, repartidor y día'),
                // Fecha de ENVÍO del pedido (no created_at). Por defecto: semana en curso, de lunes a domingo
                static::dateRangeFilter(
                    'shipping_date',
                    'Fecha de envío',
                    'Envío',
                    fn() => now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString(),
                    fn() => now()->endOfWeek(\Carbon\Carbon::SUNDAY)->toDateString(),
                )
                    ->query(fn(Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn($q, $d) => $q->whereDate('packages.shipping_date', '>=', \App\Support\Dates::day($d)))
                        ->when($data['until'] ?? null, fn($q, $d) => $q->whereDate('packages.shipping_date', '<=', \App\Support\Dates::day($d)))),
                // Fecha de ENTREGA (la de "Entregados hoy" y las comisiones).
                // Los devueltos cuentan por su fecha de devolución (el intento de entrega).
                static::dateRangeFilter('closed_date', 'Fecha de entrega', 'Entrega')
                    ->query(function (Builder $query, array $data) {
                        $from  = \App\Support\Dates::day($data['from'] ?? null);
                        $until = \App\Support\Dates::day($data['until'] ?? null);

                        if (!$from && !$until) {
                            return $query;
                        }

                        $range = fn(Builder $q, string $column) => $q
                            ->when($from, fn($q) => $q->whereDate($column, '>=', $from))
                            ->when($until, fn($q) => $q->whereDate($column, '<=', $until));

                        return $query->where(fn(Builder $q) => $q
                            ->where(fn(Builder $d) => $range($d->where('delivered', true), 'delivered_at'))
                            ->orWhere(fn(Builder $r) => $range($r->where('returned', true), 'returned_at')));
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    static::statusAction(Package::STATUS_DELIVERED, 'Marcar como entregado', 'heroicon-o-check-circle', 'success'),
                    static::statusAction(Package::STATUS_RETURNED, 'Marcar como devuelto', 'heroicon-o-arrow-uturn-left', 'danger'),
                    static::statusAction(Package::STATUS_PENDING, 'Volver a pendiente', 'heroicon-o-clock', 'warning'),
                ])
                    ->icon('heroicon-o-arrows-right-left')
                    ->tooltip('Cambiar estado')
                    ->visible(fn(Package $record) => static::canEdit($record)),
                Tables\Actions\ViewAction::make()
                    ->label('')
                    ->tooltip('Ver')
                    // Si se puede editar se entra directamente a editar
                    ->visible(fn(Package $record) => !static::canEdit($record)),
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->tooltip('Editar')
                    ->visible(fn(Package $record) => static::canEdit($record)),
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->tooltip('Eliminar')
                    ->modalHeading('Eliminar pedido')
                    ->modalDescription('¿Estás seguro de que deseas eliminar este pedido?')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->modalCancelActionLabel('Cancelar')
                    ->successNotificationTitle('Pedido eliminado correctamente')
                    ->visible(fn(Package $record) => static::canDelete($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('assignDriver')
                        ->label('Asignar repartidor')
                        ->icon('heroicon-o-truck')
                        ->form([
                            Forms\Components\Select::make('user_id')
                                ->label('Repartidor')
                                ->options(fn() => static::driverQuery(User::query())->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $updated = static::bulkUpdate($records, ['user_id' => $data['user_id']]);

                            Notification::make()->title("Repartidor asignado a {$updated} pedido(s)")->success()->send();
                        })
                        ->visible(fn() => static::userCan(Permissions::PACKAGES_EDIT) && Auth::user()?->canViewAllPackages())
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('markDelivered')
                        ->label('Marcar como entregados')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->modalWidth('md')
                        ->modalSubmitActionLabel('Guardar')
                        ->form([
                            static::closingDateInput('date', 'Fecha de entrega'),
                            Forms\Components\Radio::make('policy')
                                ->label('Si alguna dirección ya está facturada con otra forma de pago o precio')
                                ->options([
                                    Package::POLICY_KEEP   => 'Mantener los importes ya calculados',
                                    Package::POLICY_MODE   => 'Modificar forma de cobro',
                                    Package::POLICY_AMOUNT => 'Modificar importe de cobro',
                                    Package::POLICY_BOTH   => 'Modificar ambos',
                                ])
                                ->default(Package::POLICY_KEEP)
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $updated = static::bulkUpdate(
                                $records->filter(fn(Package $p) => $p->user_id && $p->status !== Package::STATUS_DELIVERED),
                                Package::statusAttributes(Package::STATUS_DELIVERED) + ['delivered_at' => $data['date']],
                                $data['policy'] ?? Package::POLICY_KEEP,
                            );

                            Notification::make()
                                ->title("{$updated} pedido(s) marcados como entregados")
                                ->body($updated < $records->count() ? 'Se han omitido los que no tienen repartidor, ya estaban entregados o están cerrados.' : null)
                                ->success()
                                ->send();
                        })
                        ->visible(fn() => static::userCan(Permissions::PACKAGES_EDIT))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('markReturned')
                        ->label('Marcar como devueltos')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('danger')
                        ->modalWidth('md')
                        ->modalSubmitActionLabel('Guardar')
                        ->form([static::closingDateInput('date', 'Fecha de devolución')])
                        ->action(function (Collection $records, array $data) {
                            $updated = static::bulkUpdate(
                                $records->filter(fn(Package $p) => $p->status !== Package::STATUS_RETURNED),
                                Package::statusAttributes(Package::STATUS_RETURNED) + ['returned_at' => $data['date']],
                            );

                            Notification::make()->title("{$updated} pedido(s) marcados como devueltos")->success()->send();
                        })
                        ->visible(fn() => static::userCan(Permissions::PACKAGES_EDIT))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            $deleted = 0;
                            $records->each(function (Package $record) use (&$deleted) {
                                if (static::canDelete($record)) {
                                    $record->delete();
                                    $deleted++;
                                }
                            });

                            Notification::make()
                                ->title("{$deleted} pedido(s) eliminados")
                                ->body($deleted < $records->count() ? 'Los pedidos cerrados hace más de 24 h no se pueden eliminar.' : null)
                                ->success()
                                ->send();
                        })
                        ->visible(fn() => static::canDeleteAny()),
                ]),
            ]);
    }

    /**
     * Campo fecha y hora de entrega/devolución: "ahora" por defecto y editable.
     * Sin fechas futuras.
     */
    protected static function closingDateInput(string $name, string $label): Forms\Components\DateTimePicker
    {
        return Forms\Components\DateTimePicker::make($name)
            ->label($label)
            // Calendario de Filament (no el del navegador): admite fechas guardadas con segundos
            ->native(false)
            ->displayFormat('d/m/Y H:i')
            ->seconds(false)
            ->firstDayOfWeek(1)
            ->closeOnDateSelection(false)
            ->default(fn() => now()->format('Y-m-d H:i:00'))
            ->maxDate(now()->endOfDay())
            ->required()
            ->helperText('Por defecto, ahora. Cámbiala si fue en otro momento.');
    }

    /** En el formulario: visible solo con ese estado y no anterior a la fecha de envío */
    protected static function closingDatePicker(string $name, string $label, string $status): Forms\Components\DateTimePicker
    {
        return static::closingDateInput($name, $label)
            ->minDate(fn(Get $get) => $get('shipping_date'))
            ->visible(fn(Get $get) => $get('status') === $status)
            ->required(fn(Get $get) => $get('status') === $status);
    }

    /**
     * Filtro "desde / hasta" con fechas en formato dd/mm/aaaa
     * y su leyenda (pastilla) cuando está aplicado.
     */
    protected static function dateRangeFilter(string $name, string $label, string $short, ?\Closure $defaultFrom = null, ?\Closure $defaultUntil = null): Tables\Filters\Filter
    {
        return Tables\Filters\Filter::make($name)
            ->label($label)
            ->form([
                Forms\Components\Fieldset::make($label)
                    ->columns(['default' => 1, 'sm' => 2])
                    ->schema([
                        Forms\Components\DatePicker::make('from')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->firstDayOfWeek(1)
                            ->default($defaultFrom)
                            ->closeOnDateSelection(),
                        Forms\Components\DatePicker::make('until')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->firstDayOfWeek(1)
                            ->default($defaultUntil)
                            ->closeOnDateSelection(),
                    ]),
            ])
            ->indicateUsing(function (array $data) use ($short): array {
                $indicator = static::dateRangeLabel($data['from'] ?? null, $data['until'] ?? null);

                return $indicator ? ["{$short}: {$indicator}"] : [];
            });
    }

    /** "08/11/2026", "desde 01/11/2026", "hasta 08/11/2026" o "01/11/2026 – 08/11/2026" */
    public static function dateRangeLabel(?string $from, ?string $until): ?string
    {
        $format = fn(?string $date) => $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : null;
        [$from, $until] = [$format($from), $format($until)];

        return match (true) {
            $from && $until && $from === $until => $from,
            $from && $until => "{$from} – {$until}",
            (bool) $from    => "desde {$from}",
            (bool) $until   => "hasta {$until}",
            default         => null,
        };
    }

    // ------------------------------------------------------------------
    // Aviso: importes calculados con otra forma de pago / precio
    // ------------------------------------------------------------------

    /** Texto del aviso a partir de Package::pricingStaleness() */
    public static function pricingPolicyDescription(array $diff): HtmlString
    {
        $euros = fn($v) => number_format((float) $v, 2, ',', '.') . ' €';
        $modes = User::paymentModeOptions();

        $lines = [
            "En esta dirección, ese día y con este repartidor hay <strong>{$diff['count']} pedido(s) ya facturados</strong>, con un total de <strong>{$euros($diff['total'])}</strong>.",
        ];

        if (isset($diff['amount'])) {
            $lines[] = 'Están facturados con <strong>' . implode(', ', array_map($euros, $diff['amount'][0]))
                . '</strong> y el precio del repartidor ahora es <strong>' . $euros($diff['amount'][1]) . '</strong>.';
        }

        if (isset($diff['mode'])) {
            $lines[] = 'Se calcularon <strong>' . implode(', ', array_map(fn($m) => $modes[$m] ?? $m, $diff['mode'][0]))
                . '</strong> y la forma de pago ahora es <strong>' . ($modes[$diff['mode'][1]] ?? $diff['mode'][1]) . '</strong>.';
        }

        $lines[] = '¿Quieres modificar estos valores? Los pedidos ya pagados no se modifican nunca.';

        return new HtmlString(implode('<br><br>', $lines));
    }

    /** Opciones del aviso (solo las que aplican) */
    public static function pricingPolicyOptions(array $diff): array
    {
        $options = [Package::POLICY_KEEP => 'Solo guardar (mantener los importes ya calculados)'];

        if (isset($diff['mode'])) {
            $options[Package::POLICY_MODE] = 'Modificar forma de cobro';
        }
        if (isset($diff['amount'])) {
            $options[Package::POLICY_AMOUNT] = 'Modificar importe de cobro';
        }
        if (isset($diff['mode'], $diff['amount'])) {
            $options[Package::POLICY_BOTH] = 'Modificar ambos';
        }

        return $options;
    }

    /** Radio con las opciones, para las ventanas de la tabla */
    public static function pricingPolicyRadio(array $diff): Forms\Components\Radio
    {
        return Forms\Components\Radio::make('policy')
            ->label('Importes ya facturados en esa dirección')
            ->helperText(static::pricingPolicyDescription($diff))
            ->options(static::pricingPolicyOptions($diff))
            ->default(Package::POLICY_KEEP)
            ->required();
    }

    /** Actualiza uno a uno (para aplicar reglas del modelo) saltando los bloqueados */
    protected static function bulkUpdate($records, array $data, string $policy = Package::POLICY_KEEP): int
    {
        $updated = 0;

        foreach ($records as $record) {
            if (static::canEdit($record)) {
                $record->pricingPolicy = $policy;
                $record->update($data);
                $updated++;
            }
        }

        return $updated;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPackages::route('/'),
            'create' => Pages\CreatePackage::route('/create'),
            'view'   => Pages\ViewPackage::route('/{record}'),
            'edit'   => Pages\EditPackage::route('/{record}/edit'),
        ];
    }
}
