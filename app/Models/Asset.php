<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'assets';

    protected $fillable = [
        'asset_no',
        'qr_code',
        'name',
        'category_id',
        'building_id',
        'room_name',
        'is_active',
    ];

    /**
     * ID internal tidak boleh keluar!
     */
    protected $hidden = [
        'id',
        'category_id',
        'building_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DamageCategory::class, 'category_id');
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'building_id');
    }
}
