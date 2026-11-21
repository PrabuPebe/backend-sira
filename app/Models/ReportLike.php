<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportLike extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $table = 'report_likes';

    protected $fillable = [
        'report_id',
        'user_id',
        'created_at',
    ];

    /**
     * ID internal tidak boleh keluar!
     */
    protected $hidden = [
        'report_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
