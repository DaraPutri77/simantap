<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Vehicle;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    public function itemUrl(Item $item): string
    {
        return route('items.show', $item);
    }

    public function vehicleUrl(Vehicle $vehicle): string
    {
        return route('vehicles.show', $vehicle);
    }

    public function svg(string $url, int $size = 180): string
    {
        $svg = (string) QrCode::format('svg')
            ->size($size)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($url);

        return trim((string) preg_replace('/<\?xml.+?\?>/s', '', $svg));
    }

    public function filename(string $type, string $code, string $extension): string
    {
        return 'QR-'.Str::upper($type).'-'.Str::upper(Str::slug($code))
            .'.'.ltrim($extension, '.');
    }
}
