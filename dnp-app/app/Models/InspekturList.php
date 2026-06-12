<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspekturList extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'nama', 'skp', 'skp_expired', 'spesialisasi',
        'phone', 'domisili', 'senior_level', 'joined_year', 'active', 'user_id',
    ];

    protected $casts = [
        'spesialisasi' => 'array',
        'active'       => 'boolean',
        'skp_expired'  => 'date',
        'senior_level' => 'integer',
        'joined_year'  => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getSkpExpiredInDaysAttribute(): int
    {
        if (!$this->skp_expired) return -9999;
        return now()->diffInDays($this->skp_expired, false);
    }
}
