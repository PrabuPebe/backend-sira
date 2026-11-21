<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory, HasUuid;

    public $timestamps = false;

    protected $table = 'notifications';

    protected $fillable = [
        'recipient_id',
        'report_id',
        'type',
        'message',
        'is_read',
        'read_at',
        'email_status',
        'email_error',
        'created_at',
    ];

    /**
     * ID internal tidak boleh keluar!
     */
    protected $hidden = [
        'id',
        'recipient_id',
        'report_id',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }
}
