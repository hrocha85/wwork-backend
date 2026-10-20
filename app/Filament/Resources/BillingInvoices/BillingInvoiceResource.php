<?php

namespace App\Filament\Resources\BillingInvoices;

use App\Filament\Resources\BillingInvoices\Pages\ListBillingInvoices;
use App\Filament\Resources\Concerns\ReadOnlyResource;
use App\Filament\Support\PanelLabels;
use App\Models\Invoice;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BillingInvoiceResource extends Resource
{
    use ReadOnlyResource;

    protected static ?string $model = Invoice::class;

    protected static ?string $slug = 'billing-invoices';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('panel.nav.billing_invoices');
    }

    public static function getModelLabel(): string
    {
        return __('panel.billing_invoices.one');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.nav.billing_invoices');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('panel.nav.operations');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['agency', 'client', 'lines']))
            ->columns([
                TextColumn::make('number')->label(__('panel.billing_invoices.number'))->formatStateUsing(fn (mixed $state): string => sprintf('INV-%04d', (int) $state)),
                TextColumn::make('client.name')->label(__('panel.billing_invoices.client')),
                TextColumn::make('agency.name')->label(__('panel.billing_invoices.agency')),
                TextColumn::make('service_date')->label(__('panel.billing_invoices.service_date'))->state(fn (Invoice $record): ?string => $record->lines->min(fn ($line) => $line->service_date?->toDateString()))->placeholder('—'),
                TextColumn::make('total_pence')->label(__('panel.billing_invoices.total'))->formatStateUsing(fn (mixed $state, Invoice $record): string => PanelLabels::money((int) $state, (string) $record->agency?->currency)),
                TextColumn::make('agency.currency')->label(__('panel.billing_invoices.currency')),
                TextColumn::make('status')->label(__('panel.billing_invoices.status'))->badge()->formatStateUsing(fn (mixed $state): string => $state instanceof \BackedEnum ? $state->value : (string) $state),
                TextColumn::make('paid_at')->label(__('panel.billing_invoices.paid_at'))->dateTime()->placeholder('—'),
                TextColumn::make('paid_method')->label(__('panel.billing_invoices.paid_method'))->formatStateUsing(fn (mixed $state): string => $state instanceof \BackedEnum ? $state->value : (string) ($state ?? ''))->placeholder('—'),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBillingInvoices::route('/'),
        ];
    }
}
