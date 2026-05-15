<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LessonNoteAttachment extends Model
{
    use HasTenantScope;

    const FILE_TYPES = [
        'pdf'   => 'PDF Document',
        'word'  => 'Word Document',
        'image' => 'Image',
        'audio' => 'Audio File',
        'video' => 'Video File',
        'link'  => 'External Link',
    ];

    protected $fillable = [
        'tenant_id', 'lesson_note_id', 'file_type',
        'original_name', 'file_path', 'url', 'size_bytes',
    ];

    protected $casts = ['size_bytes' => 'integer'];

    public function lessonNote(): BelongsTo
    {
        return $this->belongsTo(LessonNote::class);
    }

    /** Public URL for downloading/displaying the file. */
    public function publicUrl(): string
    {
        if ($this->file_type === 'link') {
            return $this->url ?? '#';
        }
        return $this->file_path ? asset('storage/' . $this->file_path) : '#';
    }

    /** Human-readable file size. */
    public function humanSize(): string
    {
        if (! $this->size_bytes) {
            return '—';
        }
        if ($this->size_bytes < 1024) {
            return $this->size_bytes . ' B';
        }
        if ($this->size_bytes < 1_048_576) {
            return round($this->size_bytes / 1024, 1) . ' KB';
        }
        return round($this->size_bytes / 1_048_576, 1) . ' MB';
    }

    /** Icon emoji for file type. */
    public function typeIcon(): string
    {
        return match ($this->file_type) {
            'pdf'   => '📄',
            'word'  => '📝',
            'image' => '🖼',
            'audio' => '🎵',
            'video' => '🎬',
            'link'  => '🔗',
            default => '📎',
        };
    }

    /** Delete the physical file when the model is deleted. */
    protected static function booted(): void
    {
        static::deleting(function (LessonNoteAttachment $att) {
            if ($att->file_path) {
                Storage::disk('public')->delete($att->file_path);
            }
        });
    }
}
