<?php

namespace App\Models;

use App\Enums\AttachmentCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'file_category',
        'disk',
        'original_name',
        'stored_name',
        'file_path',
        'mime_type',
        'file_size',
        'checksum',
        'metadata',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_category' => AttachmentCategory::class,
            'file_size' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
