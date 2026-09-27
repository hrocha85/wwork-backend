<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Filament\Resources\Agencies\AgencyResource;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('panel.subscriptions.view_help');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('agency')
                ->label(__('panel.subscriptions.open_agency'))
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->url(function (): ?string {
                    $record = $this->getRecord();

                    return $record instanceof Subscription && $record->agency_id !== null
                        ? AgencyResource::getUrl('view', ['record' => $record->agency_id])
                        : null;
                }),
        ];
    }
}
