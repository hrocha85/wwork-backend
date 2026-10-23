<!DOCTYPE html>
<html lang="{{ $locale ?? 'pt' }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 32px 36px 24px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1F2937; font-size: 11px; margin: 0; }
        h1 { font-size: 22px; letter-spacing: 1.5px; margin: 0; }
        .header-table { width: 100%; margin-bottom: 20px; }
        .stamp-cell { text-align: right; vertical-align: middle; }
        .stamp-paid {
            display: inline-block;
            border: 2px solid #16A34A;
            color: #16A34A;
            background: #DCFCE7;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 2px;
            padding: 5px 12px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .kicker { color: #79B4B0; font-size: 9px; letter-spacing: 1px; font-weight: bold; margin: 0 0 6px; }
        .party { border-left: 3px solid #79B4B0; padding: 0 0 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }
        .meta { margin-top: 22px; border-top: 1px solid #1F2937; border-bottom: 1px solid #1F2937; }
        .meta td { padding: 10px 8px 10px 0; }
        .meta td.amount-cell { padding: 0; }
        .label { color: #79B4B0; font-size: 9px; letter-spacing: 0.8px; font-weight: bold; }
        .big { font-size: 16px; font-weight: bold; margin-top: 4px; }
        .amount-box {
            background: #D1DFD2;
            padding: 10px 14px;
            min-height: 38px;
        }
        .amount-box.paid-box {
            background: #DCFCE7;
            border-left: 3px solid #16A34A;
            padding: 10px 14px;
        }
        .amount-box.paid-box .label {
            color: #15803D;
            font-size: 9px;
            letter-spacing: 0.8px;
            font-weight: bold;
        }
        .amount-box.paid-box .big {
            color: #15803D;
            font-size: 16px;
            font-weight: bold;
            margin-top: 4px;
        }
        .lines { margin-top: 8px; }
        .lines th { text-align: left; font-size: 10px; letter-spacing: 0.6px; padding: 10px 4px; border-bottom: 1px solid #1F2937; }
        .lines td { padding: 9px 4px; border-bottom: 1px solid #D1DFD2; }
        .right { text-align: right; }
        .notes { margin-top: 24px; }
        .notes-left { padding-right: 14px; vertical-align: top; }
        .notes-right { vertical-align: top; }
        .receipt-card {
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-left: 3px solid #16A34A;
            padding: 10px 14px;
            border-radius: 4px;
        }
        .receipt-card-title {
            font-size: 9px;
            font-weight: bold;
            color: #15803D;
            letter-spacing: 0.8px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .receipt-table { width: 100%; }
        .receipt-table td { padding: 2px 0; font-size: 10.5px; vertical-align: top; }
        .receipt-key { font-weight: bold; color: #374151; width: 42%; }
        .receipt-val { color: #1F2937; width: 58%; }
        .receipt-status-badge { color: #16A34A; font-weight: bold; }
        .totals-card {
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 4px;
            padding: 10px 14px;
        }
        .totals-table { width: 100%; }
        .totals-table td { padding: 2px 0; vertical-align: middle; }
        .subtotal-row td {
            border-bottom: 1px solid #E5E7EB;
            padding-top: 2px;
            padding-bottom: 7px;
        }
        .subtotal-label {
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.8px;
            color: #6B7280;
            text-transform: uppercase;
        }
        .subtotal-val {
            font-size: 12px;
            font-weight: bold;
            color: #374151;
        }
        .total-row td {
            padding-top: 7px;
            padding-bottom: 2px;
        }
        .total-label {
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.8px;
            color: #1F2937;
            text-transform: uppercase;
        }
        .total-val {
            font-size: 14px;
            font-weight: bold;
            color: #1F2937;
        }
        .total-row.is-paid .total-label {
            color: #15803D;
        }
        .total-row.is-paid .total-val {
            color: #15803D;
        }
        .bar { background: #1F2937; color: #D1DFD2; margin-top: 28px; }
        .bar td { padding: 14px 16px; vertical-align: middle; }
        .mark { background: #79B4B0; color: #1F2937; font-size: 16px; font-weight: bold; width: 36px; height: 36px; text-align: center; }
        img.logo { height: 36px; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <h1>{{ $title }}</h1>
            </td>
            @if (!empty($isPaid))
                <td class="stamp-cell">
                    <span class="stamp-paid">{{ $labels['paid'] }}</span>
                </td>
            @endif
        </tr>
    </table>

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
                @if (!empty($isPaid))
                    <div class="label">{{ $labels['paid_at'] }}</div>
                    <div class="big">{{ $paidAt ?? $issued }}</div>
                @else
                    <div class="label">{{ $labels['due'] }}</div>
                    <div class="big">{{ $labels['on_receipt'] }}</div>
                @endif
            </td>
            <td width="25%" class="amount-cell">
                <div class="amount-box {{ !empty($isPaid) ? 'paid-box' : '' }}">
                    <div class="label">{{ !empty($isPaid) ? $labels['amount_paid'] : $labels['amount_due'] }}</div>
                    <div class="big">{{ $total }}</div>
                </div>
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
            <td width="55%" class="notes-left">
                @if (!empty($isPaid))
                    <div class="receipt-card">
                        <div class="receipt-card-title">{{ $labels['paid_receipt'] }}</div>
                        <table class="receipt-table">
                            <tr>
                                <td class="receipt-key">{{ $labels['status'] }}:</td>
                                <td class="receipt-val"><span class="receipt-status-badge">{{ $labels['paid'] }}</span></td>
                            </tr>
                            <tr>
                                <td class="receipt-key">{{ $labels['paid_at'] }}:</td>
                                <td class="receipt-val">{{ $paidAt ?? $issued }}</td>
                            </tr>
                            @if (filled($paidMethodLabel))
                                <tr>
                                    <td class="receipt-key">{{ $labels['paid_method'] }}:</td>
                                    <td class="receipt-val">{{ $paidMethodLabel }}</td>
                                </tr>
                            @endif
                            @if (filled($paidNote))
                                <tr>
                                    <td class="receipt-key">{{ $labels['paid_note'] }}:</td>
                                    <td class="receipt-val">{{ $paidNote }}</td>
                                </tr>
                            @endif
                        </table>
                    </div>
                @else
                    @if (filled($paymentLabel) || filled($paymentDetails) || filled($invoicePayBy))
                        <div class="label">{{ $labels['notes'] }}</div>
                        @if (filled($paymentLabel))<p>{{ $paymentLabel }}</p>@endif
                        @if (filled($paymentDetails))<p>{{ $paymentDetails }}</p>@endif
                        @if (filled($invoicePayBy))
                            <p>{{ $invoicePaymentLabel }}</p>
                        @endif
                    @endif
                @endif
            </td>
            <td width="45%" class="notes-right">
                <div class="totals-card">
                    <table class="totals-table">
                        <tr class="subtotal-row">
                            <td class="subtotal-label">{{ $labels['subtotal'] }}</td>
                            <td class="subtotal-val right">{{ $total }}</td>
                        </tr>
                        <tr class="total-row {{ !empty($isPaid) ? 'is-paid' : '' }}">
                            <td class="total-label">{{ !empty($isPaid) ? $labels['amount_paid'] : $labels['total'] }}</td>
                            <td class="total-val right">{{ $total }}</td>
                        </tr>
                    </table>
                </div>
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