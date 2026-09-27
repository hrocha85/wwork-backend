<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('panel.nav.overview');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('panel.dashboard.subheading');
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->columns(4)->components([
            Select::make('period')
                ->label(__('panel.dashboard.period'))
                ->helperText(__('panel.dashboard.period_help'))
                ->options([
                    'today' => __('panel.dashboard.periods.today'),
                    '7' => __('panel.dashboard.periods.7'),
                    '30' => __('panel.dashboard.periods.30'),
                    'month' => __('panel.dashboard.periods.month'),
                    'year' => __('panel.dashboard.periods.year'),
                ])
                ->default('30')
                ->native(false)
                ->columnSpan(1),
        ]);
    }
}
