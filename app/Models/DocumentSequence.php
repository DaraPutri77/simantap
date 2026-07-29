<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $fillable = [
        'document_type',
        'year',
        'month',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'year' => 'integer',
            'month' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
