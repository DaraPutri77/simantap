<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Label QR {{ $code }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #0f172a;
            font-family: DejaVu Sans, sans-serif;
        }
        .label {
            width: 100%;
            height: 100%;
            padding: 9mm;
            border: 1.2mm solid #075985;
        }
        .brand {
            margin: 0 0 3mm;
            color: #0369a1;
            font-size: 8pt;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .qr {
            float: left;
            width: 38mm;
            margin-right: 6mm;
        }
        .qr svg { width: 38mm; height: 38mm; }
        .content { padding-top: 1mm; }
        .type {
            color: #64748b;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        h1 {
            margin: 2mm 0 1mm;
            font-size: 13pt;
            line-height: 1.2;
        }
        .code {
            margin: 0 0 2mm;
            color: #0369a1;
            font-size: 10pt;
            font-weight: bold;
        }
        p { margin: 0 0 1.5mm; font-size: 7.5pt; line-height: 1.3; }
        .url { color: #64748b; font-size: 5.5pt; word-break: break-all; }
    </style>
</head>
<body>
    <main class="label">
        <p class="brand">SIMANTAP · IDENTITAS OPERASIONAL</p>
        <div class="qr">{!! $qrCodeSvg !!}</div>
        <div class="content">
            <p class="type">{{ $type }}</p>
            <h1>{{ $title }}</h1>
            <p class="code">{{ $code }}</p>
            <p>{{ $subtitle }}</p>
            <p>{{ $location }}</p>
            <p class="url">{{ $targetUrl }}</p>
        </div>
    </main>
</body>
</html>
