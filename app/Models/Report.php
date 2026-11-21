<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'reports';

    protected $fillable = [
        'ticket_no',
        'title',
        'reporter_id',
        'assigned_technician_id',
        'building_id',
        'room_name',
        'floor',
        'category_id',
        'asset_id',
        'description',
        'status',
        'priority',
        'like_count',
        'is_major_damage',
        'major_damage_note',
        'estimated_completion_date',
        'duplicate_of_report_id',
        'resolved_at',
    ];

    /**
     * ID internal tidak boleh keluar!
     */
    protected $hidden = [
        'id',
        'reporter_id',
        'assigned_technician_id',
        'building_id',
        'category_id',
        'asset_id',
        'duplicate_of_report_id',
    ];

    protected function casts(): array
    {
        return [
            'is_major_damage' => 'boolean',
            'like_count' => 'integer',
            'estimated_completion_date' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'building_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DamageCategory::class, 'category_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function duplicateOfReport(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'duplicate_of_report_id');
    }

    public function timelines(): HasMany
    {
        return $this->hasMany(ReportTimeline::class, 'report_id')->orderBy('created_at', 'asc');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ReportPhoto::class, 'report_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ReportAssignment::class, 'report_id')->orderBy('assigned_at', 'desc');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(ReportLike::class, 'report_id');
    }

    public function confirmation(): HasOne
    {
        return $this->hasOne(ReportConfirmation::class, 'report_id');
    }
}
