<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use LogicException;

trait IsImmutable
{
    public static function bootIsImmutable(): void
    {
        static::updating(function (Model $model): void {
            throw new LogicException(sprintf(
                '%s bersifat immutable dan tidak boleh diubah.',
                class_basename($model),
            ));
        });

        static::deleting(function (Model $model): void {
            throw new LogicException(sprintf(
                '%s bersifat immutable dan tidak boleh dihapus.',
                class_basename($model),
            ));
        });
    }
}
