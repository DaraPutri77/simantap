<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_number',
        'movement_number',
        'reference_number',
        'item_id',
        'movement_type',
        'reference_type',
        'reference_id',
        'quantity_in',
        'quantity_out',
        'stock_before',
        'stock_after',
        'transaction_date',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity_in' => 'decimal:2',
            'quantity_out' => 'decimal:2',
            'stock_before' => 'decimal:2',
            'stock_after' => 'decimal:2',
            'transaction_date' => 'datetime',
        ];
    }

    protected function movementType(): Attribute
    {
        return Attribute::make(
            get: static function (
                StockMovementType|string|null $value,
            ): ?StockMovementType {
                if ($value instanceof StockMovementType) {
                    return $value;
                }

                return match ($value) {
                    null, '' => null,
                    'return' => StockMovementType::ReturnIn,
                    'damaged' => StockMovementType::DamagedOut,
                    default => StockMovementType::from($value),
                };
            },
            set: static function (
                StockMovementType|string|null $value,
            ): ?string {
                if ($value instanceof StockMovementType) {
                    return $value->value;
                }

                return match ($value) {
                    null, '' => null,
                    'return' => StockMovementType::ReturnIn->value,
                    'damaged' => StockMovementType::DamagedOut->value,
                    default => StockMovementType::from($value)->value,
                };
            },
        );
    }

    protected static function booted(): void
    {
        static::creating(function (self $movement): void {
            $movement->synchronizeNumberColumns();
        });

        static::updating(function (): never {
            throw new LogicException('Kartu stok tidak boleh diubah.');
        });

        static::deleting(function (): never {
            throw new LogicException('Kartu stok tidak boleh dihapus.');
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isInbound(): bool
    {
        return $this->movement_type?->isInbound() ?? false;
    }

    public function isOutbound(): bool
    {
        return $this->movement_type?->isOutbound() ?? false;
    }

    private function synchronizeNumberColumns(): void
    {
        $schema = $this->getConnection()->getSchemaBuilder();
        $table = $this->getTable();
        $hasTransactionNumber = $schema->hasColumn(
            $table,
            'transaction_number',
        );
        $hasMovementNumber = $schema->hasColumn(
            $table,
            'movement_number',
        );
        $hasReferenceNumber = $schema->hasColumn(
            $table,
            'reference_number',
        );
        $movementNumber = $this->firstFilledAttribute([
            'transaction_number',
            'movement_number',
        ]);

        if ($movementNumber !== null) {
            if ($hasTransactionNumber) {
                $this->setAttribute(
                    'transaction_number',
                    $movementNumber,
                );
            }

            if ($hasMovementNumber) {
                $this->setAttribute(
                    'movement_number',
                    $movementNumber,
                );
            }
        }

        if (
            $hasReferenceNumber
            && blank($this->getAttribute('reference_number'))
            && $movementNumber !== null
        ) {
            $this->setAttribute(
                'reference_number',
                $movementNumber,
            );
        }

        foreach ([
            'transaction_number' => $hasTransactionNumber,
            'movement_number' => $hasMovementNumber,
            'reference_number' => $hasReferenceNumber,
        ] as $column => $exists) {
            if (! $exists) {
                unset($this->attributes[$column]);
            }
        }
    }

    /**
     * @param  list<string>  $attributes
     */
    private function firstFilledAttribute(array $attributes): ?string
    {
        foreach ($attributes as $attribute) {
            $value = trim(
                (string) $this->getAttribute($attribute),
            );

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
