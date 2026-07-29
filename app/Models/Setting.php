<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function scopeInGroup(
        Builder $query,
        string $group,
    ): Builder {
        return $query->where('group', $group);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function isPublic(): bool
    {
        return $this->is_public;
    }
}
