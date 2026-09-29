<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('mail.invite.subject', ['agency' => $agency]) }}</title>
</head>
<body style="margin:0;padding:0;background:#D1DFD2;font-family:Arial,Helvetica,sans-serif;color:#1F2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#D1DFD2;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:480px;background:#FFFFFF;border-radius:24px;padding:32px;">
                    <tr>
                        <td>
                            <p style="margin:0 0 24px;font-size:28px;font-weight:bold;">WWork</p>
                            <p style="margin:0 0 12px;font-size:20px;font-weight:bold;">{{ __('mail.invite.heading') }}</p>
                            <p style="margin:0 0 12px;font-size:16px;line-height:24px;">
                                @if ($inviter)
                                    {{ __('mail.invite.body_by', ['inviter' => $inviter, 'agency' => $agency]) }}
                                @else
                                    {{ __('mail.invite.body', ['agency' => $agency]) }}
                                @endif
                            </p>
                            <p style="margin:0 0 24px;font-size:16px;line-height:24px;">{{ __('mail.invite.steps') }}</p>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="border-radius:999px;background:#F59E0B;">
                                        <a href="{{ $url }}" style="display:inline-block;padding:14px 28px;font-size:16px;font-weight:bold;color:#1F2937;text-decoration:none;">{{ __('mail.invite.button') }}</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 8px;font-size:14px;line-height:20px;">{{ __('mail.invite.expires', ['days' => $days]) }}</p>
                            <p style="margin:0 0 24px;font-size:14px;line-height:20px;">{{ __('mail.invite.fallback') }}<br><a href="{{ $url }}" style="color:#0F766E;word-break:break-all;">{{ $url }}</a></p>
                            <p style="margin:0;font-size:12px;line-height:18px;color:#6B7280;">{{ __('mail.invite.ignore') }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
