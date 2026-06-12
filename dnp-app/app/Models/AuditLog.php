<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'table_name', 'record_id', 'operation',
        'old_data', 'new_data', 'changed_by', 'changed_by_name',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    // Audit logs are append-only — disable update/delete
    public function update(array $attributes = [], array $options = []): bool { return false; }
    public function delete(): bool|null { return false; }
}
