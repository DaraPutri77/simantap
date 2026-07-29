<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum StockMovementType: string
{
    use HasEnumOptions;

    case InitialStock = 'initial_stock';
    case StockIn = 'stock_in';
    case RequestOut = 'request_out';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case ReturnIn = 'return_in';
    case DamagedOut = 'damaged_out';

    public function label(): string
    {
        return match ($this) {
            self::InitialStock => 'Stok Awal',
            self::StockIn => 'Barang Masuk',
            self::RequestOut => 'Pengeluaran Permintaan',
            self::AdjustmentIn => 'Penyesuaian Stok Masuk',
            self::AdjustmentOut => 'Penyesuaian Stok Keluar',
            self::ReturnIn => 'Pengembalian Barang',
            self::DamagedOut => 'Pengeluaran Barang Rusak',
        };
    }

    public function isInbound(): bool
    {
        return in_array($this, [
            self::InitialStock,
            self::StockIn,
            self::AdjustmentIn,
            self::ReturnIn,
        ], true);
    }

    public function isOutbound(): bool
    {
        return in_array($this, [
            self::RequestOut,
            self::AdjustmentOut,
            self::DamagedOut,
        ], true);
    }
}
