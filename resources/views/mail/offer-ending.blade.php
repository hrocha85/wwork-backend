{!! __('mail.offer_ending.hello', ['name' => $ownerName]) !!}

{!! __($annual ? 'mail.offer_ending.body_annual' : 'mail.offer_ending.body_monthly', ['agency' => $agencyName, 'today' => $today, 'next' => $next, 'date' => $date]) !!}

{!! __('mail.offer_ending.nothing_to_do') !!}

{!! __('mail.offer_ending.manage') !!}
{!! $manageUrl !!}
