<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBuildingScope extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = ['user_id', 'building_id'];

    protected $table = 'user_building_scopes';

    protected $fillable = [
        'user_id',
        'building_id',
        'granted_by',
        'created_at',
    ];

    /**
     * ID internal tidak boleh keluar!
     */
    protected $hidden = [
        'user_id',
        'building_id',
        'granted_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'building_id');
    }

    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
