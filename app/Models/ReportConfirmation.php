<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportConfirmation extends Model
{
    use HasFactory, HasUuid;

    public $timestamps = false;

    protected $table = 'report_confirmations';

    protected $fillable = [
        'report_id',
        'reporter_id',
        'rating',
        'comment',
        'confirmed_at',
    ];

    /**
     * ID internal tidak boleh keluar!
     */
    protected $hidden = [
        'id',
        'report_id',
        'reporter_id',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
