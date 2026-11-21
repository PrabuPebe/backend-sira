<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportAssignment extends Model
{
    use HasFactory, HasUuid;

    public $timestamps = false;

    protected $table = 'report_assignments';

    protected $fillable = [
        'report_id',
        'technician_id',
        'assigned_by',
        'assignment_type',
        'response_status',
        'reject_reason',
        'assigned_at',
        'responded_at',
    ];

    /**
     * ID internal tidak boleh keluar!
     */
    protected $hidden = [
        'id',
        'report_id',
        'technician_id',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
