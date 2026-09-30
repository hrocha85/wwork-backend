<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#D1DFD2;font-family:Arial,Helvetica,sans-serif;color:#1F2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#D1DFD2;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:480px;background:#FFFFFF;border-radius:24px;padding:32px;">
                    <tr>
                        <td>
                            <p style="margin:0 0 24px;font-size:28px;font-weight:bold;">WWork</p>
                            <p style="margin:0 0 12px;font-size:20px;font-weight:bold;">{{ $heading }}</p>
                            @foreach ($lines as $line)
                                <p style="margin:0 0 12px;font-size:16px;line-height:24px;">{{ $line }}</p>
                            @endforeach
                            @if ($details !== [])
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:12px 0 24px;background:#F3F4F6;border-radius:16px;">
                                    @foreach ($details as $label => $value)
                                        <tr>
                                            <td style="padding:8px 16px;font-size:14px;color:#6B7280;vertical-align:top;white-space:nowrap;">{{ $label }}</td>
                                            <td style="padding:8px 16px;font-size:14px;font-weight:bold;">{{ $value }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif
                            @if ($button)
                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:12px 0 24px;">
                                    <tr>
                                        <td style="border-radius:999px;background:#F59E0B;">
                                            <a href="{{ $button['url'] }}" style="display:inline-block;padding:14px 28px;font-size:16px;font-weight:bold;color:#1F2937;text-decoration:none;">{{ $button['label'] }}</a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin:0 0 24px;font-size:14px;line-height:20px;">{{ __('mail.common.fallback') }}<br><a href="{{ $button['url'] }}" style="color:#0F766E;word-break:break-all;">{{ $button['url'] }}</a></p>
                            @endif
                            @if ($note)
                                <p style="margin:0 0 16px;font-size:12px;line-height:18px;color:#6B7280;">{{ $note }}</p>
                            @endif
                            <p style="margin:0;font-size:12px;line-height:18px;color:#6B7280;">{{ __('mail.common.footer') }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
