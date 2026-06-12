<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlatInventory extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'nama', 'merk', 'serial', 'kategori',
        'kalibrasi_terakhir', 'kalibrasi_expired', 'lab', 'status',
    ];

    protected $casts = [
        'kategori'          => 'array',
        'kalibrasi_terakhir'=> 'date',
        'kalibrasi_expired' => 'date',
    ];
}
