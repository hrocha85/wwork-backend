<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 32px 36px 24px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1F2937; font-size: 11px; margin: 0; }
        h1 { font-size: 22px; letter-spacing: 1.5px; margin: 0 0 20px; }
        .kicker { color: #79B4B0; font-size: 9px; letter-spacing: 1px; font-weight: bold; margin: 0 0 6px; }
        .party { border-left: 3px solid #79B4B0; padding: 0 0 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }
        .meta { margin-top: 22px; border-top: 1px solid #1F2937; border-bottom: 1px solid #1F2937; }
        .meta td { padding: 10px 8px 10px 0; }
        .label { color: #79B4B0; font-size: 9px; letter-spacing: 0.8px; font-weight: bold; }
        .big { font-size: 16px; font-weight: bold; margin-top: 4px; }
        .amount { background: #D1DFD2; padding: 10px 12px; }
        .lines { margin-top: 8px; }
        .lines th { text-align: left; font-size: 10px; letter-spacing: 0.6px; padding: 10px 4px; border-bottom: 1px solid #1F2937; }
        .lines td { padding: 9px 4px; border-bottom: 1px solid #D1DFD2; }
        .right { text-align: right; }
        .notes { margin-top: 22px; }
        .total { background: #D1DFD2; font-size: 14px; font-weight: bold; padding: 8px 10px; }
        .bar { background: #1F2937; color: #D1DFD2; margin-top: 28px; }
        .bar td { padding: 14px 16px; vertical-align: middle; }
        .mark { background: #79B4B0; color: #1F2937; font-size: 16px; font-weight: bold; width: 36px; height: 36px; text-align: center; }
        img.logo { height: 36px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <table>
        <tr>
            <td width="50%" class="party">
                <p class="kicker">{{ $labels['bill_to'] }}</p>
                <strong>{{ $clientName }}</strong><br>
                {{ $clientAddress }}
            </td>
            <td width="50%" class="party">
                <p class="kicker">{{ $labels['from'] }}</p>
                <strong>{{ $agencyName }}</strong><br>
                @if (filled($phone)){{ $phone }}<br>@endif
                @if (filled($email)){{ $email }}<br>@endif
                @if (filled($legalAddress)){{ $legalAddress }}<br>@endif
                {{ $region }}
                @if (filled($vat))<br>{{ $labels['vat'] }} {{ $vat }}@endif
            </td>
        </tr>
    </table>
    <table class="meta">
        <tr>
            <td width="25%">
                <div class="label">{{ $labels['number'] }}</div>
                <div class="big">{{ $number }}</div>
            </td>
            <td width="25%">
                <div class="label">{{ $labels['date'] }}</div>
                <div class="big">{{ $issued }}</div>
            </td>
            <td width="25%">
                <div class="label">{{ $labels['due'] }}</div>
                <div class="big">{{ $labels['on_receipt'] }}</div>
            </td>
            <td width="25%" class="amount">
                <div class="label">{{ $labels['amount_due'] }}</div>
                <div class="big">{{ $total }}</div>
            </td>
        </tr>
    </table>
    <table class="lines">
        <thead>
            <tr>
                <th width="22%">{{ $labels['item'] }}</th>
                <th>{{ $labels['description'] }}</th>
                <th width="22%" class="right">{{ $labels['amount'] }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line->service_date->format('d/m/Y') }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="right">{{ $money($line->price_pence) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <table class="notes">
        <tr>
            <td width="58%">
                @if (filled($paymentLabel) || filled($paymentDetails))
                    <div class="label">{{ $labels['notes'] }}</div>
                    @if (filled($paymentLabel))<p>{{ $paymentLabel }}</p>@endif
                    @if (filled($paymentDetails))<p>{{ $paymentDetails }}</p>@endif
                @endif
            </td>
            <td width="42%">
                <table>
                    <tr>
                        <td>{{ $labels['subtotal'] }}</td>
                        <td class="right">{{ $total }}</td>
                    </tr>
                    <tr>
                        <td class="total">{{ $labels['total'] }}</td>
                        <td class="total right">{{ $total }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <table class="bar">
        <tr>
            <td width="56">
                @if (filled($logo))
                    <img class="logo" src="{{ $logo }}" alt="">
                @else
                    <div class="mark">{{ $initial }}</div>
                @endif
            </td>
            <td>
                <strong>{{ $agencyName }}</strong>
            </td>
            <td class="right">
                @if (filled($email)){{ $email }}@endif
            </td>
        </tr>
    </table>
</body>
</html>
