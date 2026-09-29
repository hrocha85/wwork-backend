{{ __('mail.invite.heading') }}

@if ($inviter)
{{ __('mail.invite.body_by', ['inviter' => $inviter, 'agency' => $agency]) }}
@else
{{ __('mail.invite.body', ['agency' => $agency]) }}
@endif

{{ __('mail.invite.steps') }}

{{ __('mail.invite.button') }}: {{ $url }}

{{ __('mail.invite.expires', ['days' => $days]) }}

{{ __('mail.invite.ignore') }}
