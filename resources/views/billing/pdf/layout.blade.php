<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle }} {{ $documentNumber }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111;
            margin: 0;
            padding: 24px;
        }
        h1 {
            font-size: 22px;
            margin: 0 0 8px;
        }
        .muted { color: #555; }
        .header {
            width: 100%;
            margin-bottom: 28px;
        }
        .header td { vertical-align: top; }
        .seller-name {
            font-size: 16px;
            font-weight: bold;
        }
        .meta {
            text-align: right;
        }
        .parties {
            width: 100%;
            margin-bottom: 24px;
        }
        .parties td {
            width: 50%;
            vertical-align: top;
        }
        .box-title {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #666;
            margin-bottom: 6px;
        }
        table.lines {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.lines th,
        table.lines td {
            border-bottom: 1px solid #ddd;
            padding: 8px 6px;
            text-align: left;
        }
        table.lines th {
            font-size: 10px;
            text-transform: uppercase;
            color: #555;
        }
        table.lines .num { text-align: right; }
        .totals {
            width: 40%;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals td {
            padding: 4px 0;
        }
        .totals .label { color: #555; }
        .totals .value { text-align: right; }
        .totals .grand {
            font-weight: bold;
            font-size: 14px;
            border-top: 1px solid #111;
            padding-top: 8px;
        }
        .notes {
            margin-top: 24px;
            padding-top: 12px;
            border-top: 1px solid #eee;
        }
        .footer {
            margin-top: 36px;
            font-size: 10px;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    @yield('content')

    @if (! empty($seller['footer']))
        <div class="footer">{{ $seller['footer'] }}</div>
    @endif
</body>
</html>
