<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Label QR {{ $code }}</title>

    <style>
        @page {
            size: 100mm 60mm;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            width: 100mm;
            height: 60mm;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        body {
            color: #0f172a;
            font-family: DejaVu Sans, sans-serif;
            font-size: 6pt;
        }

        .label {
            position: absolute;
            top: 1mm;
            left: 1mm;
            width: 98mm;
            height: 58mm;
            overflow: hidden;
            border: 0.5mm solid #075985;
        }

        .institution-logo {
            position: absolute;
            top: 3.5mm;
            left: 3.5mm;
            width: 29mm;
            height: auto;
        }

        .brand {
            position: absolute;
            top: 4mm;
            right: 3.5mm;
            width: 42mm;
            color: #0369a1;
            font-size: 6.2pt;
            font-weight: bold;
            line-height: 1.2;
            letter-spacing: 0.5px;
            text-align: right;
        }

        .qr-box {
            position: absolute;
            top: 18mm;
            left: 4mm;
            width: 30mm;
            height: 30mm;
            overflow: hidden;
        }

        .qr-image {
            display: block;
            width: 30mm;
            height: 30mm;
        }

        .content {
            position: absolute;
            top: 17.5mm;
            left: 38mm;
            width: 55mm;
            height: 35mm;
            overflow: hidden;
        }

        .type {
            margin: 0 0 1mm;
            color: #64748b;
            font-size: 5.7pt;
            font-weight: bold;
            line-height: 1.15;
            text-transform: uppercase;
        }

        .title {
            margin: 0 0 1mm;
            color: #0f172a;
            font-size: 10.5pt;
            font-weight: bold;
            line-height: 1.12;
        }

        .code {
            margin: 0 0 0.7mm;
            color: #0369a1;
            font-size: 8.2pt;
            font-weight: bold;
            line-height: 1.15;
        }

        .subtitle {
            margin: 0 0 0.6mm;
            font-size: 6.3pt;
            line-height: 1.15;
        }

        .location {
            margin: 0 0 1mm;
            color: #334155;
            font-size: 5.7pt;
            line-height: 1.15;
        }

        .scan-note {
            margin: 0;
            color: #64748b;
            font-size: 5pt;
            line-height: 1.15;
        }
    </style>
</head>

<body>
    <main class="label">
        <img
            class="institution-logo"
            src="{{ public_path(config('simantap.institution.logo')) }}"
            alt="Badan Pusat Statistik Kabupaten Jombang"
        >

        <div class="brand">
            SIMANTAP
            <br>
            IDENTITAS OPERASIONAL
        </div>

        <div class="qr-box">
            <img
                class="qr-image"
                src="data:image/svg+xml;base64,{{ base64_encode((string) $qrCodeSvg) }}"
                alt="QR SIMANTAP"
            >
        </div>

        <div class="content">
            <p class="type">
                {{ $type }}
            </p>

            <p class="title">
                {{ $title }}
            </p>

            <p class="code">
                {{ $code }}
            </p>

            <p class="subtitle">
                {{ $subtitle }}
            </p>

            <p class="location">
                {{ $location }}
            </p>

            <p class="scan-note">
                Pindai QR untuk membuka detail pada SIMANTAP.
            </p>
        </div>
    </main>
</body>
</html>