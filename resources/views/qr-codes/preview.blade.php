<article class="panel p-5 sm:p-6">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="panel-title">QR Identitas</h2>
            <p class="mt-1 text-xs font-medium text-slate-500">
                Pindai untuk membuka detail {{ $entityLabel }}
            </p>
        </div>
        <span class="status-badge">UUID Aman</span>
    </div>

    <div class="mx-auto mt-5 w-fit max-w-full rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
        {!! $qrCodeSvg !!}
    </div>

    <p class="mt-4 break-all rounded-2xl bg-slate-50 p-3 font-mono text-[10px] leading-5 text-slate-500">
        {{ $qrTargetUrl }}
    </p>
    <p class="mt-3 text-xs leading-5 text-slate-500">
        Hasil pindai tetap meminta login dan mengikuti hak akses SIMANTAP.
    </p>

    @if ($canManage)
        <div class="mt-4 grid gap-2 sm:grid-cols-2">
            <a href="{{ $svgDownloadUrl }}" class="secondary-button text-center">
                Unduh SVG
            </a>
            <a href="{{ $labelDownloadUrl }}" class="button-primary-inline text-center">
                Cetak Label PDF
            </a>
        </div>
    @endif
</article>
