<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportPhoto extends Model
{
    use HasFactory, HasUuid;

    public $timestamps = false;

    protected $table = 'report_photos';

    protected $fillable = [
        'report_id',
        'timeline_id',
        'uploaded_by',
        'photo_type',
        'file_path',
        'file_size_bytes',
        'mime_type',
        'created_at',
    ];

    protected $appends = [
        'photo_url',
    ];

    /**
     * ID internal tidak boleh keluar!
     */
    protected $hidden = [
        'id',
        'report_id',
        'timeline_id',
        'uploaded_by',
    ];

    public function getPhotoUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }

    public function timeline(): BelongsTo
    {
        return $this->belongsTo(ReportTimeline::class, 'timeline_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
