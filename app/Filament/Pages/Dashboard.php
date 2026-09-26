<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/dashboard';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')
                ->label('Período')
                ->options([
                    'today' => 'Hoje',
                    '7' => '7 dias',
                    '30' => '30 dias',
                    'month' => 'Mês',
                    'year' => 'Ano',
                ])
                ->default('30'),
        ]);
    }
}
