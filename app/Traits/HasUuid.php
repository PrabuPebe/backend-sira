<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasUuid
{
    /**
     * Boot function from Laravel to automatically generate ordered UUID on creation.
     */
    protected static function bootHasUuid(): void
    {
        static::creating(function (Model $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::orderedUuid();
            }
        });
    }

    /**
     * Scope query to find by UUID.
     */
    public function scopeWhereUuid($query, string $uuid)
    {
        return $query->where('uuid', $uuid);
    }
}
