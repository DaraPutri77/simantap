<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta
        name="robots"
        content="noindex,nofollow"
    >

    <title>Verifikasi Dokumen SIMANTAP</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f8fafc;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
        }

        .page {
            width: min(720px, calc(100% - 32px));
            margin: 40px auto;
        }

        .card {
            overflow: hidden;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            background: #ffffff;
        }

        .header {
            border-bottom: 1px solid #e2e8f0;
            padding: 24px;
        }

        .header h1 {
            margin: 0;
            font-size: 22px;
        }

        .header p {
            margin: 8px 0 0;
            color: #475569;
            line-height: 1.5;
        }

        .content {
            padding: 24px;
        }

        .status {
            margin-bottom: 22px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 14px 16px;
        }

        .status strong {
            display: block;
            margin-bottom: 6px;
            font-size: 17px;
        }

        .status p {
            margin: 0;
            color: #475569;
            line-height: 1.5;
        }

        .detail {
            width: 100%;
            border-collapse: collapse;
        }

        .detail th,
        .detail td {
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 6px;
            text-align: left;
            vertical-align: top;
        }

        .detail th {
            width: 34%;
            color: #475569;
            font-size: 14px;
        }

        .detail td {
            font-size: 14px;
            font-weight: 600;
        }

        .hash {
            font-family: Consolas, Monaco, monospace;
            font-size: 12px;
            font-weight: 400;
            overflow-wrap: anywhere;
        }

        .notice {
            margin-top: 22px;
            border-top: 1px solid #e2e8f0;
            padding-top: 18px;
            color: #475569;
            font-size: 13px;
            line-height: 1.6;
        }

        .notice strong {
            color: #0f172a;
        }
    </style>
</head>

<body>
    <main class="page">
        <section class="card">
            <header class="header">
                <h1>Verifikasi Dokumen SIMANTAP</h1>

                <p>
                    {{ $institutionName }}
                </p>
            </header>

            <div class="content">
                <div class="status">
                    @if ($verificationStatus === 'revoked')
                        <strong>Dokumen Dicabut</strong>

                        <p>
                            Versi dokumen ini telah dicabut dari status
                            verifikasi aktif.
                        </p>
                    @elseif ($verificationStatus === 'superseded')
                        <strong>Versi Lama</strong>

                        <p>
                            Dokumen tercatat, tetapi versi yang lebih baru
                            telah diterbitkan oleh SIMANTAP.
                        </p>
                    @else
                        <strong>Valid</strong>

                        <p>
                            Dokumen dan versi ini tercatat pada SIMANTAP.
                        </p>
                    @endif
                </div>

                <table class="detail">
                    <tbody>
                        <tr>
                            <th>Jenis Dokumen</th>
                            <td>{{ $documentLabel }}</td>
                        </tr>

                        <tr>
                            <th>Referensi</th>
                            <td>
                                {{ $verification->document_reference }}
                            </td>
                        </tr>

                        <tr>
                            <th>Versi</th>
                            <td>
                                {{ $verification->version }}
                            </td>
                        </tr>

                        <tr>
                            <th>Diterbitkan</th>
                            <td>
                                {{
                                    $verification
                                        ->issued_at
                                        ->timezone($displayTimezone)
                                        ->translatedFormat(
                                            'd F Y, H:i:s',
                                        )
                                }}
                                WIB
                            </td>
                        </tr>

                        <tr>
                            <th>Algoritma</th>
                            <td>
                                {{
                                    strtoupper(
                                        $verification->hash_algorithm,
                                    )
                                }}
                            </td>
                        </tr>

                        <tr>
                            <th>Fingerprint</th>
                            <td class="hash">
                                {{ $verification->payload_hash }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="notice">
                    <strong>
                        QR ini bukan tanda tangan digital.
                    </strong>

                    Verifikasi ini membuktikan bahwa versi keadaan dokumen
                    tersebut tercatat pada SIMANTAP. Halaman ini tidak
                    membuka isi transaksi, data pribadi, tanda tangan,
                    maupun bukti lampiran.
                </div>
            </div>
        </section>
    </main>
</body>
</html>