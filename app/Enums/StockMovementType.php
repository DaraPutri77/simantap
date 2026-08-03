<?php

namespace App\Enums;

enum StockMovementType: string
{
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
            self::RequestOut => 'Barang Keluar',
            self::AdjustmentIn => 'Penyesuaian Masuk',
            self::AdjustmentOut => 'Penyesuaian Keluar',
            self::ReturnIn => 'Pengembalian Masuk',
            self::DamagedOut => 'Barang Rusak Keluar',
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
        return ! $this->isInbound();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }
}
