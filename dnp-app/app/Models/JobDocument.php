<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobDocument extends Model
{
    protected $fillable = [
        'job_id', 'stage', 'type', 'original_name',
        'stored_path', 'mime_type', 'file_size', 'uploaded_by',
    ];

    protected $casts = [
        'stage'     => 'integer',
        'file_size' => 'integer',
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }
}
