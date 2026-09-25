<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        h1 { font-size: 18px; margin-bottom: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { text-align: left; padding: 6px 0; border-bottom: 1px solid #d1dfd2; }
        .total { font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $agencyName }}</h1>
    <p>{{ $region }}</p>
    @if (filled($legalAddress))
        <p>{{ $legalAddress }}</p>
    @endif
    @if (filled($vat))
        <p>{{ $labels['vat'] }} {{ $vat }}</p>
    @endif
    <p>{{ $labels['invoice'] }} {{ $number }}</p>
    <p>{{ $clientName }}<br>{{ $clientAddress }}</p>
    <table>
        <thead>
            <tr>
                <th>{{ $labels['description'] }}</th>
                <th>{{ $labels['amount'] }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line->service_date->toDateString() }} — {{ $line->description }}</td>
                    <td>{{ $currency }} {{ number_format($line->price_pence / 100, 2, '.', '') }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>{{ $labels['total'] }}</td>
                <td>{{ $currency }} {{ $total }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
