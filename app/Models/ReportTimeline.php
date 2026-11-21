<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportTimeline extends Model
{
    use HasFactory, HasUuid;

    public $timestamps = false;

    protected $table = 'report_timelines';

    protected $fillable = [
        'report_id',
        'actor_id',
        'event_type',
        'from_status',
        'to_status',
        'from_building_id',
        'to_building_id',
        'note',
        'visible_to_reporter',
        'created_at',
    ];

    /**
     * ID internal tidak boleh keluar!
     */
    protected $hidden = [
        'id',
        'report_id',
        'actor_id',
        'from_building_id',
        'to_building_id',
    ];

    protected function casts(): array
    {
        return [
            'visible_to_reporter' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function fromBuilding(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_building_id');
    }

    public function toBuilding(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_building_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ReportPhoto::class, 'timeline_id');
    }
}
