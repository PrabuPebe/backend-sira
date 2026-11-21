<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'locations';

    protected $fillable = [
        'type',
        'name',
        'code',
        'faculty',
        'floor_count',
        'is_active',
        'parent_id',
    ];

    /**
     * ID internal tidak boleh keluar!
     */
    protected $hidden = [
        'id',
        'parent_id',
        'pivot',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'floor_count' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'building_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_building_scopes', 'building_id', 'user_id');
    }
}
